<?php

namespace App\Services;

use App\Exceptions\LendaException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionService
{
    private const PENDING_PAYMENT_GRACE_MINUTES = 5;

    public function __construct(
        protected MpesaService $mpesaService
    ) {}

    /**
     * Renew the user's last subscription
     */
    public function renewSubscription(User $user, string $mpesaContact): Subscription
    {
        $lastSubscription = $user->subscriptions()->with('plan')->latest()->first();

        if (!$lastSubscription) {
            throw new LendaException("Nenhuma subscrição anterior encontrada para renovar.", 404);
        }

        $plan = $lastSubscription->plan;
        if (!$plan) {
            throw new LendaException("Plano da subscrição anterior não encontrado.", 404);
        }

        if (
            $lastSubscription->status === 'pending'
            && in_array($lastSubscription->payment_status, ['unpaid', 'pending', 'failed'], true)
        ) {
            $recoveredSubscription = $this->recoverPendingSignupPayment($lastSubscription, $plan);
            if ($recoveredSubscription instanceof Subscription) {
                return $recoveredSubscription;
            }

            $reference = $this->createPaymentReference('R');
            $paymentResponse = $this->mpesaService->initiatePayment(
                phoneNumber: $mpesaContact,
                amount: $plan->effective_price,
                reference: $reference
            );
            $resolution = $this->attemptImmediatePaymentResolution($paymentResponse, $reference);

            if ($resolution['status'] === 'failed') {
                throw new LendaException(
                    $resolution['message'],
                    400,
                    'PAYMENT_FAILED'
                );
            }

            if ($resolution['status'] === 'paid') {
                return $this->activatePendingSignupSubscription(
                    $lastSubscription,
                    $plan,
                    $reference,
                    $resolution['transaction_id']
                );
            }

            $lastSubscription->update(
                $this->pendingMpesaPaymentAttributes($plan, $reference, $resolution['transaction_id'])
            );

            return $lastSubscription->fresh(['plan']);
        }

        $now = Carbon::now();
        $startDate = $now;

        if ($lastSubscription->end_date && Carbon::parse($lastSubscription->end_date)->isFuture()) {
            $startDate = Carbon::parse($lastSubscription->end_date);
        }

        $reference = $this->createPaymentReference('R');
        $paymentResponse = $this->mpesaService->initiatePayment(
            phoneNumber: $mpesaContact,
            amount: $plan->effective_price,
            reference: $reference
        );
        $resolution = $this->attemptImmediatePaymentResolution($paymentResponse, $reference);

        if ($resolution['status'] === 'failed') {
            throw new LendaException(
                $resolution['message'],
                400,
                'PAYMENT_FAILED'
            );
        }

        if ($resolution['status'] === 'paid') {
            return Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'payment_status' => 'paid',
                'amount_paid' => $plan->effective_price,
                'currency' => 'MZN',
                'payment_source' => 'mpesa',
                'payment_reference' => $reference,
                'mpesa_transaction_id' => $resolution['transaction_id'],
                'start_date' => $startDate,
                'end_date' => (clone $startDate)->addDays($plan->duration_days),
            ]);
        }

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'amount_paid' => $plan->effective_price,
            'currency' => 'MZN',
            'payment_source' => 'mpesa',
            'payment_reference' => $reference,
            'mpesa_transaction_id' => $resolution['transaction_id'],
            'start_date' => $now,
            'end_date' => $now,
        ]);
    }

    private function recoverPendingSignupPayment(Subscription $subscription, Plan $plan): ?Subscription
    {
        if (
            $subscription->payment_source !== 'mpesa'
            || !is_string($subscription->payment_reference)
            || trim($subscription->payment_reference) === ''
        ) {
            return null;
        }

        $queryReference = $subscription->mpesa_transaction_id ?: $subscription->payment_reference;
        $response = $this->mpesaService->queryTransactionStatus($queryReference, $subscription->payment_reference);

        if (
            ($response['success'] ?? false)
            && $this->mpesaService->isSuccessfulTransactionStatus($response['status'] ?? null)
        ) {
            return $this->activatePendingSignupSubscription(
                $subscription,
                $plan,
                $subscription->payment_reference,
                $response['data']['output_TransactionID'] ?? $subscription->mpesa_transaction_id
            );
        }

        if (
            ($response['success'] ?? false)
            && $this->mpesaService->isFailedTransactionStatus($response['status'] ?? null)
        ) {
            $subscription->update([
                'status' => 'pending',
                'payment_status' => 'failed',
            ]);

            return null;
        }

        if (
            $subscription->payment_status === 'pending'
            && $subscription->updated_at
            && $subscription->updated_at->greaterThan(now()->subMinutes(self::PENDING_PAYMENT_GRACE_MINUTES))
        ) {
            throw new LendaException(
                'Já existe um pagamento em confirmação. Aguarde alguns minutos antes de tentar novamente.',
                409,
                'PAYMENT_STILL_PROCESSING'
            );
        }

        return null;
    }

    private function activatePendingSignupSubscription(
        Subscription $subscription,
        Plan $plan,
        string $reference,
        ?string $transactionId
    ): Subscription {
        $startDate = Carbon::now();
        $durationDays = $plan->duration_days > 0 ? $plan->duration_days : 30;

        $subscription->update([
            'status' => 'active',
            'payment_status' => 'paid',
            'amount_paid' => $plan->effective_price,
            'currency' => 'MZN',
            'payment_source' => 'mpesa',
            'payment_reference' => $reference,
            'mpesa_transaction_id' => $transactionId ?: $subscription->mpesa_transaction_id,
            'start_date' => $startDate,
            'end_date' => (clone $startDate)->addDays($durationDays),
        ]);

        return $subscription->fresh(['plan']);
    }

    private function createPaymentReference(string $prefix): string
    {
        return $prefix . strtoupper(Str::random(7));
    }

    /**
     * @param array<string, mixed> $paymentResponse
     * @return array{status:string,transaction_id:?string,message:string}
     */
    private function attemptImmediatePaymentResolution(array $paymentResponse, string $reference): array
    {
        $initialMessage = (string) ($paymentResponse['message'] ?? 'Não foi possível processar o pagamento M-Pesa.');
        $initialTransactionId = isset($paymentResponse['transaction_id']) && is_string($paymentResponse['transaction_id'])
            ? $paymentResponse['transaction_id']
            : null;

        if (!($paymentResponse['success'] ?? false) && ! $this->isAmbiguousPaymentFailure($paymentResponse)) {
            return [
                'status' => 'failed',
                'transaction_id' => $initialTransactionId,
                'message' => $initialMessage,
            ];
        }

        $queryReference = $initialTransactionId ?: $reference;
        $queryResponse = $this->mpesaService->queryTransactionStatus($queryReference, $reference);

        if (
            ($queryResponse['success'] ?? false)
            && $this->mpesaService->isSuccessfulTransactionStatus($queryResponse['status'] ?? null)
        ) {
            $transactionId = isset($queryResponse['data']['output_TransactionID']) && is_string($queryResponse['data']['output_TransactionID'])
                ? $queryResponse['data']['output_TransactionID']
                : $initialTransactionId;

            return [
                'status' => 'paid',
                'transaction_id' => $transactionId,
                'message' => '',
            ];
        }

        if (
            ($queryResponse['success'] ?? false)
            && $this->mpesaService->isFailedTransactionStatus($queryResponse['status'] ?? null)
        ) {
            return [
                'status' => 'failed',
                'transaction_id' => $initialTransactionId,
                'message' => $initialMessage,
            ];
        }

        return [
            'status' => 'pending',
            'transaction_id' => $initialTransactionId,
            'message' => $initialMessage,
        ];
    }

    /**
     * @param array<string, mixed> $paymentResponse
     */
    private function isAmbiguousPaymentFailure(array $paymentResponse): bool
    {
        $responseCode = $paymentResponse['response_code'] ?? null;
        if ($this->mpesaService->isTimeoutResponseCode(is_string($responseCode) ? $responseCode : null)) {
            return true;
        }

        $message = strtoupper((string) ($paymentResponse['message'] ?? ''));

        return str_contains($message, 'TIMEOUT') || str_contains($message, 'INS-9');
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingMpesaPaymentAttributes(Plan $plan, string $reference, ?string $transactionId): array
    {
        return [
            'plan_id' => $plan->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'amount_paid' => $plan->effective_price,
            'currency' => 'MZN',
            'payment_source' => 'mpesa',
            'payment_reference' => $reference,
            'mpesa_transaction_id' => $transactionId,
        ];
    }

    /**
     * Create a new subscription
     */
    public function subscribeWithMpesa(User $user, int|string $planId, string $mpesaContact): Subscription
    {
        $plan = $this->resolvePlan($planId);

        if ($user->hasActiveSubscription()) {
            throw new LendaException("O utilizador já possui uma subscrição ativa.", 400);
        }

        $reference = $this->createPaymentReference('S');

        $paymentResponse = $this->mpesaService->initiatePayment(
            phoneNumber: $mpesaContact,
            amount: $plan->effective_price,
            reference: $reference
        );
        $resolution = $this->attemptImmediatePaymentResolution($paymentResponse, $reference);

        if ($resolution['status'] === 'failed') {
            throw new LendaException(
                $resolution['message'],
                400,
                'PAYMENT_FAILED'
            );
        }

        $now = Carbon::now();
        $durationDays = $plan->duration_days > 0 ? $plan->duration_days : 30;

        if ($resolution['status'] === 'paid') {
            return Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'payment_status' => 'paid',
                'amount_paid' => $plan->effective_price,
                'currency' => 'MZN',
                'payment_source' => 'mpesa',
                'payment_reference' => $reference,
                'mpesa_transaction_id' => $resolution['transaction_id'],
                'start_date' => $now,
                'end_date' => (clone $now)->addDays($durationDays),
            ]);
        }

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'amount_paid' => $plan->effective_price,
            'currency' => 'MZN',
            'payment_source' => 'mpesa',
            'payment_reference' => $reference,
            'mpesa_transaction_id' => $resolution['transaction_id'],
            'start_date' => $now,
            'end_date' => $now,
        ]);
    }

    /**
     * Cancel without refund
     */
    public function cancelWithoutRefund(Subscription $subscription): void
    {
        if ($subscription->status === 'cancelled') {
            throw new LendaException("Esta subscrição já está cancelada.", 400);
        }
        $subscription->update(['status' => 'cancelled']);
    }

    /**
     * Upgrade Plan
     */
    public function upgradePlan(User $user, int $newPlanId, string $mpesaContact): Subscription
    {
        $newPlan = Plan::findOrFail($newPlanId);
        $currentSub = $user->currentSubscription();

        if ($currentSub && $currentSub->plan_id == $newPlanId) {
            throw new LendaException("O utilizador já está subscrito a este plano.", 400);
        }

        $reference = $this->createPaymentReference('U');
        
        $paymentResponse = $this->mpesaService->initiatePayment(
            phoneNumber: $mpesaContact,
            amount: $newPlan->effective_price,
            reference: $reference
        );
        $resolution = $this->attemptImmediatePaymentResolution($paymentResponse, $reference);

        if ($resolution['status'] === 'failed') {
            throw new LendaException(
                $resolution['message'],
                400,
                'PAYMENT_FAILED'
            );
        }

        DB::beginTransaction();
        try {
            if ($currentSub) {
                $currentSub->update(['status' => 'upgraded', 'is_active' => false]);
            }

            $now = Carbon::now();
            $durationDays = $newPlan->duration_days > 0 ? $newPlan->duration_days : 30;
            $isPaid = $resolution['status'] === 'paid';

            $newSub = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $newPlan->id,
                'status' => $isPaid ? 'active' : 'pending',
                'payment_status' => $isPaid ? 'paid' : 'pending',
                'amount_paid' => $newPlan->effective_price,
                'currency' => 'MZN',
                'payment_source' => 'mpesa',
                'payment_reference' => $reference,
                'mpesa_transaction_id' => $resolution['transaction_id'],
                'start_date' => $now,
                'end_date' => $isPaid ? (clone $now)->addDays($durationDays) : $now,
            ]);

            DB::commit();
            return $newSub;

        } catch (\Exception $e) {
            DB::rollBack();
            throw new LendaException("Erro ao processar atualização.", 500);
        }
    }

    public function getUserSubscriptionStats(User $user): array
    {
        $totalSpent = (float) $user->subscriptions()
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.payment_status', 'paid')
            ->sum(DB::raw("
                CASE
                    WHEN subscriptions.amount_paid IS NOT NULL THEN subscriptions.amount_paid
                    WHEN COALESCE(subscriptions.payment_source, '') = 'manual' AND subscriptions.mpesa_transaction_id IS NULL THEN 0
                    ELSE COALESCE(plans.promo_price, plans.price, 0)
                END
            "));

        $activeSub = $user->currentSubscription();

        return [
            'total_spent' => $totalSpent,
            'total_subscriptions' => $user->subscriptions()->count(),
            'current_plan' => $activeSub ? $activeSub->plan->name : 'Gratuito',
            'member_since' => $user->created_at->format('M Y'),
        ];
    }

    private function resolvePlan(int|string $identifier): Plan
    {
        if (is_numeric($identifier)) {
            return Plan::findOrFail($identifier);
        }
        return Plan::where('slug', $identifier)->firstOrFail();
    }
}
