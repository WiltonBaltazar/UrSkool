<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\WebPushEndpointValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;
use Throwable;

class WebPushService
{
    /**
     * Get public VAPID key for browser registration.
     */
    public function vapidPublicKey(): ?string
    {
        $publicKey = (string) data_get($this->vapidConfig(), 'public_key', '');

        if ($publicKey === '' || ! $this->hasValidVapidPublicKey($publicKey)) {
            return null;
        }

        return $publicKey;
    }

    /**
     * Check whether all mandatory VAPID values are configured.
     */
    public function isConfigured(): bool
    {
        return $this->configurationErrors() === [];
    }

    /**
     * Return missing VAPID keys for diagnostics.
     *
     * @return list<string>
     */
    public function configurationErrors(): array
    {
        $config = $this->vapidConfig();
        $missing = [];

        foreach (['subject', 'public_key', 'private_key'] as $requiredKey) {
            $value = trim((string) data_get($config, $requiredKey, ''));
            if ($value === '') {
                $missing[] = $requiredKey;
            }
        }

        $publicKey = trim((string) data_get($config, 'public_key', ''));
        if ($publicKey !== '' && ! $this->hasValidVapidPublicKey($publicKey)) {
            $missing[] = 'public_key_format';
        }

        return $missing;
    }

    /**
     * Send a push payload to all subscriptions of a user.
     *
     * @param  array<string, mixed>  $payload
     * @return array{total:int,sent:int,failed:int,removed:int}
     */
    public function sendToUser(User $user, array $payload): array
    {
        return $this->sendToSubscriptions($user->pushSubscriptions()->get(), $payload);
    }

    /**
     * Send a push payload to a subscription collection.
     *
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @param  array<string, mixed>  $payload
     * @return array{total:int,sent:int,failed:int,removed:int}
     */
    public function sendToSubscriptions(Collection $subscriptions, array $payload): array
    {
        if ($subscriptions->isEmpty()) {
            return [
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'removed' => 0,
            ];
        }

        $webPush = $this->buildClient();
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stats = [
            'total' => $subscriptions->count(),
            'sent' => 0,
            'failed' => 0,
            'removed' => 0,
        ];

        foreach ($subscriptions as $subscription) {
            if (! WebPushEndpointValidator::isAllowed((string) $subscription->endpoint)) {
                $stats['failed']++;
                $deleted = (bool) $subscription->delete();
                if ($deleted) {
                    $stats['removed']++;
                }

                Log::warning('Skipped web push delivery for disallowed endpoint host.', [
                    'push_subscription_id' => $subscription->id,
                    'endpoint_hash' => $subscription->endpoint_hash,
                ]);

                continue;
            }

            try {
                $webPush->queueNotification(
                    $this->toMinishlinkSubscription($subscription),
                    $payloadJson
                );
            } catch (Throwable $exception) {
                $stats['failed']++;
                Log::warning('Failed to queue web push notification.', [
                    'push_subscription_id' => $subscription->id,
                    'endpoint_hash' => $subscription->endpoint_hash,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $stats['sent']++;
                $this->touchByEndpoint($report->getEndpoint());
                continue;
            }

            $stats['failed']++;
            $statusCode = $report->getResponse()?->getStatusCode();
            $endpoint = $report->getEndpoint();

            Log::warning('Web push delivery failed.', [
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'reason' => $report->getReason(),
            ]);

            if ($statusCode === 404 || $statusCode === 410) {
                $stats['removed'] += $this->deleteByEndpoint($endpoint);
            }
        }

        return $stats;
    }

    private function buildClient(): WebPush
    {
        if (! class_exists(WebPush::class) || ! class_exists(Subscription::class)) {
            throw new RuntimeException('Web push dependency is missing. Run composer install.');
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('Web push is not configured (missing VAPID keys).');
        }

        $vapidConfig = $this->vapidConfig();
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $vapidConfig['subject'],
                'publicKey' => $vapidConfig['public_key'],
                'privateKey' => $vapidConfig['private_key'],
            ],
        ], [
            'TTL' => 300,
        ]);

        $webPush->setReuseVAPIDHeaders(true);

        return $webPush;
    }

    /**
     * @return array{subject:string,public_key:string,private_key:string}
     */
    private function vapidConfig(): array
    {
        return [
            'subject' => trim((string) config('services.webpush.vapid.subject')),
            'public_key' => trim((string) config('services.webpush.vapid.public_key')),
            'private_key' => trim((string) config('services.webpush.vapid.private_key')),
        ];
    }

    private function hasValidVapidPublicKey(string $publicKey): bool
    {
        $decoded = $this->decodeBase64Url($publicKey);
        if (! is_string($decoded) || strlen($decoded) !== 65) {
            return false;
        }

        return ord($decoded[0]) === 4;
    }

    private function decodeBase64Url(string $value): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        $normalized = strtr($normalized, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        return is_string($decoded) ? $decoded : null;
    }

    private function toMinishlinkSubscription(PushSubscription $subscription): Subscription
    {
        return Subscription::create([
            'endpoint' => $subscription->endpoint,
            'publicKey' => $subscription->public_key,
            'authToken' => $subscription->auth_token,
            'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
        ]);
    }

    private function deleteByEndpoint(string $endpoint): int
    {
        if ($endpoint === '') {
            return 0;
        }

        return PushSubscription::query()
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->delete();
    }

    private function touchByEndpoint(string $endpoint): void
    {
        if ($endpoint === '') {
            return;
        }

        PushSubscription::query()
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->update(['last_seen_at' => now()]);
    }
}
