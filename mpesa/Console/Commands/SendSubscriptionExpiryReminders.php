<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiringSoon;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendSubscriptionExpiryReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:send-expiry-reminders
        {--days=5 : Number of days before expiry to alert users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send subscription expiry reminder e-mails to users before their subscriptions expire.';

    /**
     * Reminder tracking field by interval days.
     *
     * @var array<int, string>
     */
    protected array $reminderFieldMap = [
        5 => 'expiry_reminder_sent_at',
        1 => 'final_expiry_reminder_sent_at',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        if (! isset($this->reminderFieldMap[$days])) {
            $this->error("Unsupported --days value '{$days}'. Allowed values: 5 or 1.");
            return self::INVALID;
        }

        $trackingField = $this->reminderFieldMap[$days];
        $targetDate = now()->addDays($days)->toDateString();
        $renewUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/') . '/app/profile';

        $this->info("Sending expiry reminders for subscriptions ending on {$targetDate} ({$days} days).");

        $subscriptions = Subscription::query()
            ->with([
                'user:id,first_name,email',
                'plan:id,name',
            ])
            ->where('status', 'active')
            ->whereNull('cancelled_at')
            ->whereNotNull('end_date')
            ->whereDate('end_date', $targetDate)
            ->whereNull($trackingField)
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions eligible for reminder.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            $user = $subscription->user;

            if (! $user || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                $this->warn("Skipping subscription {$subscription->id}: user/e-mail not valid.");
                continue;
            }

            $endDate = Carbon::parse($subscription->end_date);
            $daysRemaining = max(0, now()->startOfDay()->diffInDays($endDate->startOfDay(), false));

            try {
                Mail::to($user->email)->send(new SubscriptionExpiringSoon(
                    firstName: (string) ($user->first_name ?: 'Utilizador'),
                    planName: (string) ($subscription->plan?->name ?: 'Plano'),
                    expiresAt: $endDate->format('d/m/Y'),
                    daysRemaining: $daysRemaining,
                    renewUrl: $renewUrl,
                ));

                $subscription->forceFill([
                    $trackingField => now(),
                ])->saveQuietly();

                $sent++;
            } catch (Throwable $exception) {
                $failed++;
                Log::error('Failed to send subscription expiry reminder.', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'email' => $user->email,
                    'error' => $exception->getMessage(),
                ]);
                $this->error("Failed subscription {$subscription->id}: {$exception->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Sent: {$sent}");
        $this->line("Skipped: {$skipped}");
        $this->line("Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
