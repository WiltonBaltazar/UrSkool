<?php

namespace App\Http\Controllers\Api\V1;

use App\Mail\WelcomeUser;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MpesaService;
use App\Services\WebSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    private const PENDING_PAYMENT_GRACE_MINUTES = 5;

    public function __construct(
        protected MpesaService $mpesa,
        private readonly WebSessionService $webSessionService
    )
    {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'phone_number' => 'required|string|min:9',
            'password' => 'required|string|min:8',
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        if (!$plan->isValid()) {
            return $this->apiErrorResponse(
                'PLAN_INVALID',
                'The selected plan is not valid.',
                null,
                400
            );
        }

        try {
            [$user, $subscription] = DB::transaction(function () use ($validated, $plan) {
                $user = User::query()
                    ->where('email', $validated['email'])
                    ->lockForUpdate()
                    ->first();

                if ($user) {
                    if (!Hash::check($validated['password'], (string) $user->password)) {
                        throw new \RuntimeException('password_mismatch');
                    }

                    if ($user->hasActiveSubscription()) {
                        throw new \RuntimeException('already_active');
                    }

                    $user->forceFill([
                        'first_name' => $validated['first_name'],
                        'last_name' => $validated['last_name'],
                        'phone_number' => $validated['phone_number'],
                    ]);
                    $user->save();
                } else {
                    $user = User::query()->create([
                        'first_name' => $validated['first_name'],
                        'last_name' => $validated['last_name'],
                        'email' => $validated['email'],
                        'phone_number' => $validated['phone_number'],
                        'password' => Hash::make($validated['password']),
                    ]);
                }

                $subscription = $user->subscriptions()
                    ->where('status', 'pending')
                    ->latest('created_at')
                    ->first();

                if (!$subscription) {
                    $subscription = $user->subscriptions()->create([
                        'plan_id' => $plan->id,
                        'status' => 'pending',
                        'payment_status' => 'unpaid',
                        'amount_paid' => null,
                        'currency' => 'MZN',
                        'payment_source' => 'mpesa',
                        'start_date' => now(),
                        'end_date' => now(),
                    ]);
                } else {
                    $shouldUpdatePendingSubscription = (int) $subscription->plan_id !== (int) $plan->id
                        || $subscription->payment_source !== 'mpesa';

                    if ($shouldUpdatePendingSubscription) {
                        $subscription->forceFill([
                            'plan_id' => $plan->id,
                            'payment_source' => 'mpesa',
                        ])->save();
                    }
                }

                return [$user->fresh(), $subscription->fresh(['plan'])];
            });
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'password_mismatch') {
                return $this->apiErrorResponse(
                    'REGISTRATION_NOT_ALLOWED',
                    'Unable to start registration with the provided credentials.',
                    null,
                    409
                );
            }

            if ($exception->getMessage() === 'already_active') {
                return $this->apiErrorResponse(
                    'REGISTRATION_NOT_ALLOWED',
                    'Unable to start registration with the provided credentials.',
                    null,
                    409
                );
            }

            Log::error('Registration pending-flow error: '.$exception->getMessage());

            return $this->apiErrorResponse(
                'REGISTRATION_FAILED',
                'Unable to start registration. Please try again.',
                null,
                500
            );
        } catch (\Throwable $exception) {
            Log::error('Registration DB Error: '.$exception->getMessage());

            return $this->apiErrorResponse(
                'REGISTRATION_FAILED',
                'Unable to start registration. Please try again.',
                config('app.debug') ? ['exception' => $exception->getMessage()] : null,
                500
            );
        }

        $recovery = $this->tryRecoverExistingPendingPayment($subscription, $plan);
        if ($recovery instanceof Subscription) {
            $this->queueWelcomeEmail($user, $plan, $recovery);
            return $this->successResponse($user, $recovery, $request, 'Registration and payment successful.', 200);
        }

        if ($recovery === 'pending') {
            return $this->pendingPaymentResponse(
                $user,
                $subscription,
                $request,
                'Payment already in confirmation. Please wait a few minutes before retrying.'
            );
        }

        $reference = $this->generateUniqueReference('T');

        $subscription->forceFill([
            'payment_reference' => $reference,
            'payment_status' => 'pending',
            'payment_source' => 'mpesa',
            'amount_paid' => $plan->effective_price,
            'currency' => 'MZN',
            'mpesa_transaction_id' => null,
        ])->save();

        $paymentResult = $this->mpesa->initiatePayment(
            $validated['phone_number'],
            $plan->effective_price,
            $reference
        );

        if ($paymentResult['success']) {
            $queryReference = (string) ($paymentResult['transaction_id'] ?? $reference);
            $queryResult = $this->mpesa->queryTransactionStatus($queryReference, $reference);

            if (
                ($queryResult['success'] ?? false)
                && $this->mpesa->isSuccessfulTransactionStatus($queryResult['status'] ?? null)
            ) {
                $paidSubscription = $this->activatePendingSubscription(
                    $subscription,
                    $plan,
                    $reference,
                    $queryResult['data']['output_TransactionID'] ?? ($paymentResult['transaction_id'] ?? null)
                );
                $this->queueWelcomeEmail($user, $plan, $paidSubscription);

                return $this->successResponse($user, $paidSubscription, $request, 'Registration and payment successful.', 201);
            }

            if (
                ($queryResult['success'] ?? false)
                && $this->mpesa->isFailedTransactionStatus($queryResult['status'] ?? null)
            ) {
                $subscription->forceFill([
                    'status' => 'pending',
                    'payment_status' => 'failed',
                    'mpesa_transaction_id' => $paymentResult['transaction_id'] ?? $subscription->mpesa_transaction_id,
                ])->save();

                return $this->apiErrorResponse(
                    'PAYMENT_FAILED',
                    'Não foi possível confirmar o pagamento agora. Tente novamente em instantes.',
                    [
                        'provider_message' => $paymentResult['message'] ?? null,
                        'requires_payment' => true,
                    ],
                    402
                );
            }

            $subscription->forceFill([
                'status' => 'pending',
                'payment_status' => 'pending',
                'mpesa_transaction_id' => $paymentResult['transaction_id'] ?? $subscription->mpesa_transaction_id,
            ])->save();

            return $this->pendingPaymentResponse(
                $user,
                $subscription->fresh(['plan']),
                $request,
                'Payment request sent. Approve your M-Pesa PIN to finish activating the account.'
            );
        }

        if ($this->isAmbiguousPaymentFailure($paymentResult)) {
            $queryReference = (string) ($paymentResult['transaction_id'] ?? $reference);
            $queryResult = $this->mpesa->queryTransactionStatus($queryReference, $reference);

            if (
                ($queryResult['success'] ?? false)
                && $this->mpesa->isSuccessfulTransactionStatus($queryResult['status'] ?? null)
            ) {
                $paidSubscription = $this->activatePendingSubscription(
                    $subscription,
                    $plan,
                    $reference,
                    $queryResult['data']['output_TransactionID'] ?? ($paymentResult['transaction_id'] ?? null)
                );
                $this->queueWelcomeEmail($user, $plan, $paidSubscription);

                return $this->successResponse($user, $paidSubscription, $request, 'Registration and payment successful.', 201);
            }

            $subscription->forceFill([
                'status' => 'pending',
                'payment_status' => 'pending',
                'mpesa_transaction_id' => $paymentResult['transaction_id'] ?? $subscription->mpesa_transaction_id,
            ])->save();

            return $this->pendingPaymentResponse(
                $user,
                $subscription->fresh(['plan']),
                $request,
                'Payment request sent. If you approve your M-Pesa PIN after timeout, your account will activate automatically.'
            );
        }

        $subscription->forceFill([
            'status' => 'pending',
            'payment_status' => 'failed',
            'mpesa_transaction_id' => $paymentResult['transaction_id'] ?? $subscription->mpesa_transaction_id,
        ])->save();

        return $this->apiErrorResponse(
            'PAYMENT_FAILED',
            'Não foi possível confirmar o pagamento agora. Tente novamente em instantes.',
            [
                'provider_message' => $paymentResult['message'] ?? null,
                'requires_payment' => true,
            ],
            402
        );
    }

    private function tryRecoverExistingPendingPayment(Subscription $subscription, Plan $plan): Subscription|string|null
    {
        if (
            $subscription->payment_source !== 'mpesa'
            || !is_string($subscription->payment_reference)
            || trim($subscription->payment_reference) === ''
        ) {
            return null;
        }

        $queryReference = $subscription->mpesa_transaction_id ?: $subscription->payment_reference;
        $response = $this->mpesa->queryTransactionStatus($queryReference, $subscription->payment_reference);

        if (($response['success'] ?? false) && $this->mpesa->isSuccessfulTransactionStatus($response['status'] ?? null)) {
            return $this->activatePendingSubscription(
                $subscription,
                $plan,
                $subscription->payment_reference,
                $response['data']['output_TransactionID'] ?? $subscription->mpesa_transaction_id
            );
        }

        if (($response['success'] ?? false) && $this->mpesa->isFailedTransactionStatus($response['status'] ?? null)) {
            $subscription->forceFill([
                'status' => 'pending',
                'payment_status' => 'failed',
            ])->save();

            return null;
        }

        if (
            $subscription->payment_status === 'pending'
            && $subscription->updated_at
            && $subscription->updated_at->greaterThan(now()->subMinutes(self::PENDING_PAYMENT_GRACE_MINUTES))
        ) {
            return 'pending';
        }

        return null;
    }

    private function isAmbiguousPaymentFailure(array $paymentResult): bool
    {
        $responseCode = $paymentResult['response_code'] ?? null;
        if ($this->mpesa->isTimeoutResponseCode(is_string($responseCode) ? $responseCode : null)) {
            return true;
        }

        $message = strtoupper((string) ($paymentResult['message'] ?? ''));

        return str_contains($message, 'TIMEOUT') || str_contains($message, 'INS-9');
    }

    private function activatePendingSubscription(
        Subscription $subscription,
        Plan $plan,
        string $reference,
        ?string $transactionId
    ): Subscription {
        $startDate = Carbon::now();
        $days = $plan->duration_days > 0 ? $plan->duration_days : 30;

        $subscription->forceFill([
            'plan_id' => $plan->id,
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addDays($days),
            'status' => 'active',
            'payment_status' => 'paid',
            'amount_paid' => $plan->effective_price,
            'currency' => 'MZN',
            'payment_source' => 'mpesa',
            'payment_reference' => $reference,
            'mpesa_transaction_id' => $transactionId ?: $subscription->mpesa_transaction_id,
        ])->save();

        return $subscription->fresh(['plan']);
    }

    private function queueWelcomeEmail(User $user, Plan $plan, Subscription $subscription): void
    {
        try {
            Mail::to($user->email)->queue(new WelcomeUser($user, $plan, $subscription));
        } catch (\Throwable $exception) {
            Log::error('Failed to send welcome email: '.$exception->getMessage());
        }
    }

    private function pendingPaymentResponse(
        User $user,
        Subscription $subscription,
        Request $request,
        string $message
    ) {
        $session = $this->webSessionService->issueSession($user, $request);
        Auth::login($user);

        return response()->json([
            'success' => true,
            'message' => $message,
            'requires_payment' => true,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'full_name' => $user->fullName,
                'phone_number' => $user->phone_number,
                'subscription' => $subscription->loadMissing('plan'),
                'email_verified' => $user->hasVerifiedEmail(),
            ],
            'session' => $session['session'],
        ], 202)
            ->cookie($session['access_cookie'])
            ->cookie($session['refresh_cookie']);
    }

    private function successResponse(
        User $user,
        Subscription $subscription,
        Request $request,
        string $message,
        int $status
    ) {
        $session = $this->webSessionService->issueSession($user, $request);
        Auth::login($user);

        return response()->json([
            'success' => true,
            'message' => $message,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'full_name' => $user->fullName,
                'phone_number' => $user->phone_number,
                'subscription' => $subscription->loadMissing('plan'),
                'email_verified' => $user->hasVerifiedEmail(),
            ],
            'session' => $session['session'],
        ], $status)
            ->cookie($session['access_cookie'])
            ->cookie($session['refresh_cookie']);
    }

    private function generateUniqueReference(string $prefix): string
    {
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt += 1) {
            $reference = $prefix . strtoupper(Str::random(7));
            $exists = Subscription::query()
                ->where('payment_reference', $reference)
                ->exists();

            if (!$exists) {
                return $reference;
            }
        }

        throw new \RuntimeException('Unable to generate unique payment reference.');
    }
}
