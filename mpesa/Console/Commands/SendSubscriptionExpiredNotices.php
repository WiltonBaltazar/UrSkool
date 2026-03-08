<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpired;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendSubscriptionExpiredNotices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:send-expired-notices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send e-mails to users whose subscriptions have expired.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $renewUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/') . '/app/profile';

        $subscriptions = Subscription::query()
            ->with([
                'user:id,first_name,email',
                'plan:id,name',
            ])
            ->whereIn('status', ['active', 'expired'])
            ->whereNull('cancelled_at')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now())
            ->whereNull('expired_notice_sent_at')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No expired subscriptions pending notice.');
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

            $endDate = Carbon::parse($subscription->end_date);

            try {
                Mail::to($user->email)->send(new SubscriptionExpired(
                    firstName: (string) ($user->first_name ?: 'Utilizador'),
                    planName: (string) ($subscription->plan?->name ?: 'Plano'),
                    expiredAt: $endDate->format('d/m/Y'),
                    renewUrl: $renewUrl,
                ));

                $subscription->forceFill([
                    'status' => $subscription->status === 'active' ? 'expired' : $subscription->status,
                    'expired_notice_sent_at' => now(),
                ])->saveQuietly();

                $sent++;
            } catch (Throwable $exception) {
                $failed++;
                Log::error('Failed to send expired subscription notice.', [
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

