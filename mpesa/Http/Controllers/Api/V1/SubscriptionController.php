<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller; // <--- Import Base Controller
use App\Exceptions\LendaException;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Mail\WelcomeUser;
use App\Mail\SubscriptionRenewed;
use App\Mail\SubscriptionCancelled;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * Subscribe user to a plan with M-Pesa payment
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required_without:plan_slug|exists:plans,id',
            'plan_slug' => 'required_without:plan_id|exists:plans,slug',
            'mpesa_contact' => [
                'required',
                'string',
                'regex:/^(82|83|84|85|86|87)[0-9]{7}$/'
            ],
        ], [
            'mpesa_contact.regex' => 'Número de M-Pesa inválido. Use o formato sem prefixo: 843334444',
            'plan_id.required_without' => 'Deve fornecer plan_id ou plan_slug',
            'plan_slug.required_without' => 'Deve fornecer plan_id ou plan_slug',
        ]);

        if ($validator->fails()) {
            return $this->apiErrorResponse(
                'VALIDATION_ERROR',
                'Dados inválidos',
                $validator->errors(),
                422
            );
        }

        try {
            $planIdentifier = $request->plan_id ?? $request->plan_slug;

            $subscription = $this->subscriptionService->subscribeWithMpesa(
                user: $request->user(),
                planId: $planIdentifier,
                mpesaContact: $request->mpesa_contact
            );

            $isActiveAndPaid = $subscription->status === 'active' && $subscription->payment_status === 'paid';

            return response()->json([
                'success' => true,
                'message' => $isActiveAndPaid
                    ? 'Subscrição ativada com sucesso!'
                    : 'Pedido de pagamento enviado. Confirme o pagamento no telemóvel para ativar a subscrição.',
                'data' => [
                    'subscription' => $subscription,
                    'plan' => $subscription->plan,
                    'end_date' => $subscription->end_date,
                    'days_remaining' => $subscription->daysRemaining(),
                ]
            ], $isActiveAndPaid ? 201 : 202);
        } catch (LendaException $e) {
            return $this->apiErrorResponse(
                $e->errorCode(),
                $e->getMessage(),
                $e->details(),
                $e->status()
            );
        } catch (\Throwable $e) {
            return $this->apiErrorResponse(
                'INTERNAL_SERVER_ERROR',
                'Erro inesperado.',
                config('app.debug') ? ['exception' => $e->getMessage()] : null,
                500
            );
        }
    }

    /**
     * Get current user's active subscription
     */
    public function current(Request $request)
    {
        $user = $request->user();
        $subscription = $user->currentSubscription();

        if (!$subscription) {
            return $this->apiErrorResponse(
                'SUBSCRIPTION_NOT_FOUND',
                'Nenhuma subscrição ativa encontrada',
                null,
                404
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $subscription,
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'payment_status' => $subscription->payment_status,
                'days_remaining' => $subscription->daysRemaining(),
                'is_active' => $subscription->isActive(),
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
            ]
        ]);
    }

    /**
     * Get user's subscription history
     */
    public function history(Request $request)
    {
        $subscriptions = $request->user()
            ->subscriptions()
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions
        ]);
    }

    /**
     * Get subscription statistics
     */
    public function stats(Request $request)
    {
        $stats = $this->subscriptionService->getUserSubscriptionStats($request->user());

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request, int $subscriptionId)
    {
        $subscription = $request->user()->subscriptions()->findOrFail($subscriptionId);

        try {
            $this->subscriptionService->cancelWithoutRefund($subscription);
            
            try {
                Mail::to($request->user())->send(new SubscriptionCancelled($subscription));
            } catch (\Exception $e) {}

            return response()->json([
                'success' => true,
                'message' => 'Subscrição cancelada com sucesso!',
            ]);
        } catch (LendaException $e) {
            return $this->apiErrorResponse(
                $e->errorCode(),
                $e->getMessage(),
                $e->details(),
                $e->status()
            );
        } catch (\Throwable $e) {
            return $this->apiErrorResponse(
                'INTERNAL_SERVER_ERROR',
                'Erro inesperado',
                config('app.debug') ? ['exception' => $e->getMessage()] : null,
                500
            );
        }
    }

    /**
     * Renew last subscription
     */
  public function renew(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Update regex: ^(258)? makes "258" optional at the start
            'mpesa_contact' => [
                'required', 
                'string', 
                'regex:/^(258)?(82|83|84|85|86|87)[0-9]{7}$/'
            ],
        ], [
            'mpesa_contact.regex' => 'Número inválido. Use o formato: 841234567 ou 258841234567'
        ]);

        if ($validator->fails()) {
            return $this->apiErrorResponse(
                'VALIDATION_ERROR',
                'Dados inválidos',
                $validator->errors(),
                422
            );
        }

        try {
            $pendingSignupSubscription = $request->user()
                ->subscriptions()
                ->where('status', 'pending')
                ->whereIn('payment_status', ['unpaid', 'pending', 'failed'])
                ->latest('created_at')
                ->first();

            $subscription = $this->subscriptionService->renewSubscription(
                user: $request->user(),
                mpesaContact: $request->mpesa_contact
            );
            $isSignupSubscription = $pendingSignupSubscription !== null && $subscription->id === $pendingSignupSubscription->id;
            $isActiveAndPaid = $subscription->status === 'active' && $subscription->payment_status === 'paid';

            try {
                if ($isSignupSubscription) {
                    Mail::to($request->user())->send(new WelcomeUser(
                        $request->user(),
                        $subscription->plan,
                        $subscription
                    ));
                } else {
                    Mail::to($request->user())->send(new SubscriptionRenewed($subscription));
                }
            } catch (\Exception $e) {
                Log::error("Email failed: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => $isActiveAndPaid
                    ? ($isSignupSubscription ? 'Plano assinado com sucesso!' : 'Renovação concluída com sucesso!')
                    : 'Renovação iniciada! Por favor, aprove o pagamento no seu telemóvel.',
                'data' => [
                    'subscription' => $subscription,
                    'plan' => $subscription->plan,
                    'payment_status' => $subscription->payment_status,
                    'flow' => $isSignupSubscription ? 'signup_subscription' : 'renewal',
                ]
            ], $isActiveAndPaid ? 201 : 202);

        } catch (LendaException $e) {
            return $this->apiErrorResponse(
                $e->errorCode(),
                $e->getMessage(),
                $e->details(),
                $e->status()
            );
        } catch (\Throwable $e) {
            Log::error("Renew Error: " . $e->getMessage());
            return $this->apiErrorResponse(
                'INTERNAL_SERVER_ERROR',
                'Erro interno.',
                config('app.debug') ? ['exception' => $e->getMessage()] : null,
                500
            );
        }
    }

    /**
     * Upgrade to a different plan
     */
    public function upgrade(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
            'mpesa_contact' => ['required', 'string', 'regex:/^(82|83|84|85|86|87)[0-9]{7}$/'],
        ]);

        if ($validator->fails()) {
            return $this->apiErrorResponse(
                'VALIDATION_ERROR',
                'Dados inválidos',
                $validator->errors(),
                422
            );
        }

        try {
            $subscription = $this->subscriptionService->upgradePlan(
                user: $request->user(),
                newPlanId: $request->plan_id,
                mpesaContact: $request->mpesa_contact
            );
            $isActiveAndPaid = $subscription->status === 'active' && $subscription->payment_status === 'paid';

            return response()->json([
                'success' => true,
                'message' => $isActiveAndPaid
                    ? 'Plano atualizado com sucesso!'
                    : 'Atualização iniciada! Aprove no M-Pesa.',
                'data' => [
                    'subscription' => $subscription,
                    'plan' => $subscription->plan,
                ]
            ], $isActiveAndPaid ? 201 : 202);
        } catch (LendaException $e) {
            return $this->apiErrorResponse(
                $e->errorCode(),
                $e->getMessage(),
                $e->details(),
                $e->status()
            );
        } catch (\Throwable $e) {
            return $this->apiErrorResponse(
                'INTERNAL_SERVER_ERROR',
                'Erro interno.',
                config('app.debug') ? ['exception' => $e->getMessage()] : null,
                500
            );
        }
    }

    /**
     * Check if user has access to specific content/feature
     */
    public function checkAccess(Request $request, string $planSlug)
    {
        $user = $request->user();
        $hasAccess = $user->hasPlan($planSlug);

        return response()->json([
            'success' => true,
            'data' => [
                'has_access' => $hasAccess,
                'current_plan' => $user->currentPlan()?->slug,
                'required_plan' => $planSlug,
            ]
        ]);
    }
}
