<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionPaymentFailed;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendSubscriptionPaymentFailedNotices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:send-payment-failed-notices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send e-mails for failed subscription payments.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $retryUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/') . '/app/profile';

        $subscriptions = Subscription::query()
            ->with([
                'user:id,first_name,email',
                'plan:id,name',
            ])
            ->where('payment_status', 'failed')
            ->whereNull('payment_failed_notice_sent_at')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No failed-payment subscriptions pending notice.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            $user = $subscription->user;

            if (! $user || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            $paymentReference = (string) ($subscription->payment_reference ?: $subscription->mpesa_transaction_id ?: '-');

            try {
                Mail::to($user->email)->send(new SubscriptionPaymentFailed(
                    firstName: (string) ($user->first_name ?: 'Utilizador'),
                    planName: (string) ($subscription->plan?->name ?: 'Plano'),
                    paymentReference: $paymentReference,
                    retryUrl: $retryUrl,
                ));

                $subscription->forceFill([
                    'payment_failed_notice_sent_at' => now(),
                ])->saveQuietly();

                $sent++;
            } catch (Throwable $exception) {
                $failed++;
                Log::error('Failed to send payment-failed subscription notice.', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'email' => $user->email,
                    'error' => $exception->getMessage(),
                ]);
                $this->error("Failed subscription {$subscription->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Sent: {$sent}");
        $this->line("Skipped: {$skipped}");
        $this->line("Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

