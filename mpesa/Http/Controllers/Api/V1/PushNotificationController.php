<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendPushTestNotificationRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class PushNotificationController extends Controller
{
    public function publicKey(WebPushService $webPushService): JsonResponse
    {
        $publicKey = $webPushService->vapidPublicKey();

        if ($publicKey === null) {
            return $this->apiErrorResponse(
                'WEB_PUSH_NOT_CONFIGURED',
                'Web push is not configured on the server.',
                null,
                503
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'public_key' => $publicKey,
            ],
        ]);
    }

    public function subscribe(StorePushSubscriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $endpoint = (string) $validated['endpoint'];
        $endpointHash = hash('sha256', $endpoint);
        $expirationTime = $validated['expirationTime'] ?? null;
        $userId = (string) $request->user()->id;

        $existingSubscription = PushSubscription::query()
            ->where('endpoint_hash', $endpointHash)
            ->first();

        if ($existingSubscription && $existingSubscription->user_id !== $userId) {
            return $this->apiErrorResponse(
                'WEB_PUSH_ENDPOINT_CONFLICT',
                'This push endpoint is already associated with another account.',
                null,
                409
            );
        }

        $subscription = PushSubscription::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'endpoint_hash' => $endpointHash,
            ],
            [
                'user_id' => $userId,
                'endpoint' => $endpoint,
                'public_key' => (string) $validated['keys']['p256dh'],
                'auth_token' => (string) $validated['keys']['auth'],
                'content_encoding' => (string) ($validated['contentEncoding'] ?? 'aes128gcm'),
                'expiration_time' => is_int($expirationTime) ? Carbon::createFromTimestampMs($expirationTime) : null,
                'user_agent' => $request->userAgent(),
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Push subscription saved.',
            'data' => [
                'id' => $subscription->id,
            ],
        ], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:4000'],
        ]);

        $deleted = PushSubscription::query()
            ->where('user_id', (string) $request->user()->id)
            ->where('endpoint_hash', hash('sha256', (string) $validated['endpoint']))
            ->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted > 0 ? 'Push subscription removed.' : 'Push subscription not found.',
            'data' => [
                'deleted' => $deleted > 0,
            ],
        ]);
    }

    public function sendTest(SendPushTestNotificationRequest $request, WebPushService $webPushService): JsonResponse
    {
        if (! $webPushService->isConfigured()) {
            return $this->apiErrorResponse(
                'WEB_PUSH_NOT_CONFIGURED',
                'Web push is not configured on the server.',
                $webPushService->configurationErrors(),
                503
            );
        }

        $user = $request->user();
        if ($user->pushSubscriptions()->count() === 0) {
            return $this->apiErrorResponse(
                'WEB_PUSH_NO_SUBSCRIPTIONS',
                'No push subscriptions registered for this user.',
                null,
                422
            );
        }

        $validated = $request->validated();
        $payload = [
            'title' => $validated['title'] ?? 'Lenda +',
            'body' => $validated['body'] ?? 'Notificação de teste enviada com sucesso.',
            'icon' => $validated['icon'] ?? '/logo192.png',
            'badge' => $validated['badge'] ?? '/logo192.png',
            'url' => $validated['url'] ?? '/app',
            'tag' => $validated['tag'] ?? 'lenda-push-test',
            'data' => [
                'kind' => 'test',
                'sent_at' => now()->toIso8601String(),
            ],
        ];

        try {
            $result = $webPushService->sendToUser($user, $payload);
        } catch (RuntimeException $exception) {
            return $this->apiErrorResponse(
                'WEB_PUSH_DELIVERY_FAILED',
                $exception->getMessage(),
                null,
                500
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->apiErrorResponse(
                'WEB_PUSH_DELIVERY_FAILED',
                'Failed to send push notification.',
                null,
                500
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Test notification processed.',
            'data' => $result,
        ]);
    }
}
