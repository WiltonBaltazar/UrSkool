<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\MpesaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class VerifyMpesaPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mpesa:verify-payments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifies optimistic M-Pesa transactions and updates their subscription status if payment is invalid';

    /**
     * Execute the console command.
     */
    public function handle(MpesaService $mpesaService)
    {
        $this->info('Starting M-Pesa payments verification...');

        // We skip very recent records (< 2 min) to allow users time to enter their PIN.
        $subscriptions = Subscription::query()
            ->with('plan')
            ->where('payment_source', 'mpesa')
            ->whereNotNull('payment_reference')
            ->whereBetween('created_at', [
                Carbon::now()->subHours(24),
                Carbon::now()->subMinutes(2)
            ])
            ->where(function ($query) {
                $query->where(function ($activeQuery) {
                    $activeQuery
                        ->where('payment_status', 'paid')
                        ->where('status', 'active');
                })->orWhere(function ($pendingQuery) {
                    $pendingQuery
                        ->where('status', 'pending')
                        ->whereIn('payment_status', ['pending', 'unpaid', 'failed']);
                });
            })
            ->get();

        $count = $subscriptions->count();
        $this->info("Found $count subscriptions to verify");

        foreach ($subscriptions as $subscription) {
            if (!is_string($subscription->payment_reference) || trim($subscription->payment_reference) === '') {
                $this->warn("Skipping subscription {$subscription->id}: missing payment reference.");
                continue;
            }

            $this->info("Verifying subscription ID {$subscription->id} | Ref: {$subscription->payment_reference}");

            $response = $mpesaService->queryTransactionStatus(
                $subscription->mpesa_transaction_id ?? $subscription->payment_reference,
                $subscription->payment_reference
            );

            if (!$response['success']) {
                $this->error(" - API Call Failed: " . $response['message']);
                continue;
            }

            $mpesaStatus = $mpesaService->normalizeProviderStatus((string) ($response['status'] ?? ''));
            $this->comment(" - M-Pesa Status: {$mpesaStatus}");

            if ($mpesaService->isSuccessfulTransactionStatus($mpesaStatus)) {
                if ($subscription->status !== 'active' || $subscription->payment_status !== 'paid') {
                    $startDate = Carbon::now();
                    $durationDays = ($subscription->plan?->duration_days ?? 0) > 0
                        ? (int) $subscription->plan->duration_days
                        : 30;

                    $subscription->update([
                        'status' => 'active',
                        'payment_status' => 'paid',
                        'amount_paid' => $subscription->amount_paid ?: ($subscription->plan?->effective_price ?? 0),
                        'currency' => $subscription->currency ?: 'MZN',
                        'payment_source' => 'mpesa',
                        'mpesa_transaction_id' => $response['data']['output_TransactionID'] ?? $subscription->mpesa_transaction_id,
                        'start_date' => $startDate,
                        'end_date' => (clone $startDate)->addDays($durationDays),
                    ]);

                    $this->info(' - Pending subscription activated.');
                } else {
                    $this->info(" - Verified Valid.");
                }

                continue;
            }

            if ($mpesaService->isFailedTransactionStatus($mpesaStatus)) {
                $subscription->update([
                    'status' => 'cancelled',
                    'payment_status' => 'failed',
                ]);

                $this->warn(" - Subscription cancelled.");
                continue;
            }

            $this->line(" - Transaction still pending. No changes applied.");
        }

        $this->info('Verification complete.');
    }
}
