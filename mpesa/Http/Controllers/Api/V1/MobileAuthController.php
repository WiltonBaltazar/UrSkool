<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MobileLoginRequest;
use App\Http\Requests\MobileRefreshRequest;
use App\Models\MobileRefreshToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class MobileAuthController extends Controller
{
    private function authErrorResponse(
        string $message,
        string $code = 'AUTH_UNAUTHORIZED',
        int $status = 401,
        mixed $details = null
    ): JsonResponse
    {
        return $this->apiErrorResponse($code, $message, $details, $status);
    }

    private function issueTokenPair(User $user, Request $request, ?MobileRefreshToken $previousRefreshToken = null): array
    {
        $accessTokenTtlMinutes = (int) env('MOBILE_ACCESS_TOKEN_TTL_MINUTES', 60);
        $refreshTokenTtlDays = (int) env('MOBILE_REFRESH_TOKEN_TTL_DAYS', 30);

        $deviceName = (string) ($request->input('device_name') ?: $previousRefreshToken?->device_name ?: 'mobile-device');

        $newToken = $user->createToken(
            name: 'mobile_auth_token',
            abilities: ['mobile:api'],
            expiresAt: now()->addMinutes($accessTokenTtlMinutes)
        );

        $refreshTokenPlainText = Str::random(96);
        $refreshTokenHash = hash('sha256', $refreshTokenPlainText);

        $refreshToken = MobileRefreshToken::create([
            'user_id' => $user->id,
            'personal_access_token_id' => $newToken->accessToken->id,
            'token_hash' => $refreshTokenHash,
            'device_name' => $deviceName,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => now()->addDays($refreshTokenTtlDays),
            'last_used_at' => now(),
        ]);

        if ($previousRefreshToken) {
            $previousRefreshToken->markRevoked();
        }

        return [
            'access_token' => $newToken->plainTextToken,
            'access_token_expires_at' => $newToken->accessToken->expires_at,
            'refresh_token' => $refreshTokenPlainText,
            'refresh_token_expires_at' => $refreshToken->expires_at,
            'token_type' => 'Bearer',
        ];
    }

    private function ensureMobileTokenContext(Request $request): ?JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if (!$token || !$token->can('mobile:api')) {
            return $this->authErrorResponse('Forbidden for non-mobile token context', 'AUTH_FORBIDDEN', 403);
        }

        return null;
    }

    public function login(MobileLoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return $this->authErrorResponse('Invalid credentials', 'AUTH_INVALID_CREDENTIALS');
        }

        $tokenPair = $this->issueTokenPair($user, $request);

        return response()->json([
            'success' => true,
            'message' => 'Mobile login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'full_name' => $user->fullName,
                    'phone_number' => $user->phone_number,
                ],
                ...$tokenPair,
            ],
        ]);
    }

    public function refresh(MobileRefreshRequest $request): JsonResponse
    {
        $tokenHash = hash('sha256', $request->validated()['refresh_token']);

        $storedRefreshToken = MobileRefreshToken::query()
            ->with('user')
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->first();

        if (!$storedRefreshToken || $storedRefreshToken->isExpired() || !$storedRefreshToken->user) {
            if ($storedRefreshToken) {
                $storedRefreshToken->markRevoked();
            }

            return $this->authErrorResponse('Invalid or expired refresh token', 'AUTH_INVALID_REFRESH_TOKEN');
        }

        $storedRefreshToken->accessToken?->delete();

        $tokenPair = $this->issueTokenPair($storedRefreshToken->user, $request, $storedRefreshToken);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => $tokenPair,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        if ($forbiddenResponse = $this->ensureMobileTokenContext($request)) {
            return $forbiddenResponse;
        }

        $user = $request->user();

        if (!$user) {
            return $this->authErrorResponse('Unauthenticated.', 'AUTH_UNAUTHORIZED');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'full_name' => $user->fullName,
                    'phone_number' => $user->phone_number,
                ],
                'subscription' => $user->currentSubscription(),
                'has_active_subscription' => $user->hasActiveSubscription(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($forbiddenResponse = $this->ensureMobileTokenContext($request)) {
            return $forbiddenResponse;
        }

        $token = $request->user()?->currentAccessToken();

        if (!$token) {
            return $this->authErrorResponse('Unauthenticated.', 'AUTH_UNAUTHORIZED');
        }

        MobileRefreshToken::query()
            ->where('personal_access_token_id', $token->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $token->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mobile logout successful',
        ]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        if ($forbiddenResponse = $this->ensureMobileTokenContext($request)) {
            return $forbiddenResponse;
        }

        $user = $request->user();

        if (!$user) {
            return $this->authErrorResponse('Unauthenticated.', 'AUTH_UNAUTHORIZED');
        }

        $mobileTokenIds = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->where('name', 'mobile_auth_token')
            ->pluck('id');

        if ($mobileTokenIds->isNotEmpty()) {
            MobileRefreshToken::query()
                ->whereIn('personal_access_token_id', $mobileTokenIds)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            PersonalAccessToken::query()->whereIn('id', $mobileTokenIds)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'All mobile sessions revoked successfully',
        ]);
    }
}
