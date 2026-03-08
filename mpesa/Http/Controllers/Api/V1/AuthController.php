<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use App\Http\Requests\ForgotPasswordRequest;
use App\Notifications\VerifyNewEmail;
use Illuminate\Http\RedirectResponse;
use App\Services\WebSessionService;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly WebSessionService $webSessionService
    ) {}

    private function resolveSessionSubscription(User $user): ?Subscription
    {
        $activeSubscription = $user->currentSubscription();
        if ($activeSubscription) {
            return $activeSubscription;
        }

        return $user->subscriptions()
            ->with('plan')
            ->where(function ($query) {
                $query->where('status', 'pending')
                    ->orWhere('payment_status', 'unpaid');
            })
            ->latest('created_at')
            ->first();
    }

    private function authErrorResponse(
        string $message,
        string $code = 'AUTH_UNAUTHORIZED',
        int $status = 401,
        mixed $details = null
    ): JsonResponse
    {
        return $this->apiErrorResponse($code, $message, $details, $status);
    }

    private function revokeUserTokens(User $user): void
    {
        $user->tokens()->delete();

        $user->mobileRefreshTokens()
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Authenticate user login
     * Validated credentials and returns authentication token
     * @param \App\Http\Requests\LoginRequest $request
     * @return JsonResponse|mixed
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->authErrorResponse('Invalid credentials', 'AUTH_INVALID_CREDENTIALS');
        }

        $user = Auth::user();
        $session = $this->webSessionService->issueSession($user, $request);

        // Get user active subscription details
        $subscription = $this->resolveSessionSubscription($user);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'full_name' => $user->fullName,
                'phone_number' => $user->phone_number,
                'subscription' => $subscription,
            ],
            'session' => $session['session'],
        ], 200)
            ->cookie($session['access_cookie'])
            ->cookie($session['refresh_cookie']);
    }

    /**
     * Authenticate or register user with Google credential.
     * Users that sign up using Google still require payment completion.
     */
    public function googleAuth(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credential' => ['required', 'string'],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'mode' => ['nullable', 'string', 'in:login,signup'],
        ]);

        $googleClientId = (string) config('services.google.client_id', '');

        if ($googleClientId === '') {
            return $this->apiErrorResponse(
                'AUTH_GOOGLE_NOT_CONFIGURED',
                'Google authentication is not configured.',
                null,
                500
            );
        }

        try {
            $tokenInfoResponse = Http::acceptJson()
                ->timeout(10)
                ->get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $validated['credential'],
                ]);
        } catch (\Throwable $exception) {
            return $this->apiErrorResponse(
                'AUTH_GOOGLE_PROVIDER_UNAVAILABLE',
                'Google authentication is temporarily unavailable.',
                null,
                503
            );
        }

        if (! $tokenInfoResponse->successful()) {
            return $this->authErrorResponse(
                'Invalid Google credential.',
                'AUTH_GOOGLE_INVALID_CREDENTIAL',
                401
            );
        }

        $tokenInfo = $tokenInfoResponse->json();

        if (!is_array($tokenInfo)) {
            return $this->authErrorResponse(
                'Invalid Google credential payload.',
                'AUTH_GOOGLE_INVALID_CREDENTIAL',
                401
            );
        }

        $audience = (string) ($tokenInfo['aud'] ?? '');
        if ($audience !== $googleClientId) {
            return $this->authErrorResponse(
                'Google credential audience mismatch.',
                'AUTH_GOOGLE_AUDIENCE_MISMATCH',
                401
            );
        }

        $issuer = (string) ($tokenInfo['iss'] ?? '');
        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            return $this->authErrorResponse(
                'Google credential issuer mismatch.',
                'AUTH_GOOGLE_ISSUER_MISMATCH',
                401
            );
        }

        $emailVerified = filter_var(($tokenInfo['email_verified'] ?? false), FILTER_VALIDATE_BOOLEAN);
        $email = strtolower(trim((string) ($tokenInfo['email'] ?? '')));
        if (!$emailVerified || $email === '') {
            return $this->authErrorResponse(
                'Google account email is not verified.',
                'AUTH_GOOGLE_EMAIL_NOT_VERIFIED',
                401
            );
        }

        $fullName = trim((string) ($tokenInfo['name'] ?? ''));
        $firstName = trim((string) ($tokenInfo['given_name'] ?? ''));
        $lastName = trim((string) ($tokenInfo['family_name'] ?? ''));
        if ($firstName === '' && $fullName !== '') {
            [$derivedFirstName, $derivedLastName] = $this->splitName($fullName);
            $firstName = $derivedFirstName;
            $lastName = $lastName !== '' ? $lastName : $derivedLastName;
        }
        if ($firstName === '') {
            $firstName = 'Google';
        }
        if ($lastName === '') {
            $lastName = 'User';
        }

        $existingUser = User::query()->where('email', $email)->first();
        $existingUserHasSubscriptions = $existingUser?->subscriptions()->exists() ?? false;
        $shouldCreatePendingSubscription = ! $existingUserHasSubscriptions;
        $selectedPlan = null;

        if ($shouldCreatePendingSubscription) {
            $selectedPlan = $this->resolveGoogleSignupPlan(isset($validated['plan_id']) ? (int) $validated['plan_id'] : null);

            if (! $selectedPlan) {
                return $this->apiErrorResponse(
                    'PLAN_NOT_FOUND',
                    'No subscription plan is available for Google signup.',
                    null,
                    422
                );
            }
        }

        $user = $existingUser;
        $isNewUser = false;

        DB::transaction(function () use (
            &$user,
            &$isNewUser,
            $firstName,
            $lastName,
            $email,
            $tokenInfo,
            $shouldCreatePendingSubscription,
            $selectedPlan
        ) {
            if (! $user) {
                $isNewUser = true;

                $user = User::query()->create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'password' => Hash::make(Str::random(40)),
                    'profile_photo' => isset($tokenInfo['picture']) ? (string) $tokenInfo['picture'] : null,
                    'email_verified_at' => now(),
                ]);
            }

            if (! $user->hasVerifiedEmail()) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            if (
                $shouldCreatePendingSubscription &&
                $selectedPlan &&
                ! $user->subscriptions()->exists()
            ) {
                $user->subscriptions()->create([
                    'plan_id' => $selectedPlan->id,
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'amount_paid' => 0,
                    'currency' => 'MZN',
                    'payment_source' => 'manual',
                    'start_date' => now(),
                    'end_date' => now(),
                ]);
            }
        });

        Auth::login($user);
        $session = $this->webSessionService->issueSession($user, $request);
        $subscription = $this->resolveSessionSubscription($user);
        $requiresPayment = ! $user->hasActiveSubscription();

        return response()->json([
            'success' => true,
            'message' => $requiresPayment
                ? 'Google authentication successful. Complete payment to finish signup.'
                : 'Google authentication successful.',
            'requires_payment' => $requiresPayment,
            'is_new_user' => $isNewUser,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'full_name' => $user->fullName,
                'phone_number' => $user->phone_number,
                'subscription' => $subscription,
            ],
            'session' => $session['session'],
        ], 200)
            ->cookie($session['access_cookie'])
            ->cookie($session['refresh_cookie']);
    }

    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        if (count($parts) === 0) {
            return ['Google', 'User'];
        }

        if (count($parts) === 1) {
            return [$parts[0], 'User'];
        }

        $firstName = array_shift($parts);
        $lastName = implode(' ', $parts);

        return [$firstName, $lastName];
    }

    private function resolveGoogleSignupPlan(?int $planId): ?Plan
    {
        if ($planId) {
            return Plan::query()->find($planId);
        }

        return Plan::query()->where('slug', 'premium')->first()
            ?? Plan::query()->orderBy('id')->first();
    }

    /**
     * Update user profile details
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        // Update standard profile fields
        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone_number' => $request->phone_number,
        ]);

        if ($request->email !== $user->email) {
            // Store the new email in the temporary column
            $user->pending_email = $request->email;
            $user->save();

            // Send notification to the NEW email address
            // You would create a custom Notification for this
            $user->notify(new VerifyNewEmail($request->email));

            return response()->json([
                'success' => true,
                'message' => 'Perfil atualizado. Verifique o seu novo e-mail para confirmar a alteração.',
                'user' => $user
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Perfil atualizado.', 'user' => $user]);
    }

    /**
     * Confirms the email change and redirects to the frontend.
     */
    // AuthController.php

    // AuthController.php

    public function confirmEmailChange(Request $request, $userId)
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        // 1. Validate the signature (This is your security, so the route can be public)
        if (! $request->hasValidSignature()) {
            return redirect($frontendUrl . '/app/profile?error=invalid_link');
        }

        // 2. Find user by ID since they aren't "authenticated" in this request
        $user = \App\Models\User::find($userId);

        if (!$user) {
            return redirect($frontendUrl . '/app/profile?error=user_not_found');
        }

        // 3. Perform the update from server-side pending email only.
        if (is_string($user->pending_email) && trim($user->pending_email) !== '') {
            $user->email = $user->pending_email;
            $user->pending_email = null;
            $user->email_verified_at = now();
            $user->save();

            return redirect($frontendUrl . '/app/profile?status=email-updated');
        }

        return redirect($frontendUrl . '/app/profile?error=no_pending_email');
    }

    /**
     * Cancels a pending email change request.
     */
    // AuthController.php

    public function cancelEmailChange(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->pending_email) {
            return $this->apiErrorResponse(
                'PROFILE_NO_PENDING_EMAIL',
                'Não há nenhuma alteração de e-mail pendente.',
                null,
                400
            );
        }

        $user->update(['pending_email' => null]); //

        return response()->json([
            'success' => true,
            'message' => 'Solicitação de alteração de e-mail cancelada.',
            'user' => $user // This user object now has pending_email = null
        ]);
    }


    /**
     * Send password reset link
     * Generates and emails password reset token
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'success' => true,
            'message' => 'If the account exists, a password reset link has been sent.',
        ]);
    }

    /**
     * Change user password
     * Validates current password and updates to new password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'new_password' => 'required|confirmed|min:8|different:current_password',
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);
        $this->revokeUserTokens($user);

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Password updated. Please sign in again.',
        ])->cookie($this->webSessionService->forgetAccessCookie($request))
            ->cookie($this->webSessionService->forgetRefreshCookie($request));
    }

    /**
     * Reset user password
     * Validates reset token and update password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ]);
                $user->save();
                $this->revokeUserTokens($user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully.'
            ]);
        }

        return $this->authErrorResponse(
            'Invalid or expired reset token.',
            'AUTH_INVALID_RESET_TOKEN',
            400
        );
    }

    /**
     * Refresh web session using HttpOnly refresh token.
     */
    public function refreshSession(Request $request): JsonResponse
    {
        $refreshToken = $this->webSessionService->resolveRefreshTokenFromRequest($request);

        if (! $refreshToken || ! ($refreshToken->tokenable instanceof User)) {
            return $this->authErrorResponse('Invalid or expired refresh token.', 'AUTH_INVALID_REFRESH_TOKEN')
                ->cookie($this->webSessionService->forgetAccessCookie($request))
                ->cookie($this->webSessionService->forgetRefreshCookie($request));
        }

        $user = $refreshToken->tokenable;

        $this->webSessionService->revokeAccessTokenFromRequest($request);
        $refreshToken->delete();

        Auth::login($user);
        $session = $this->webSessionService->issueSession($user, $request);

        return response()->json([
            'success' => true,
            'message' => 'Session refreshed successfully.',
            'data' => [
                'session' => $session['session'],
            ],
        ])->cookie($session['access_cookie'])
            ->cookie($session['refresh_cookie']);
    }

    /**
     * Logout user
     * Revokes current authentication token
     * 
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse|mixed
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        } elseif ($request->bearerToken()) {
            PersonalAccessToken::findToken($request->bearerToken())?->delete();
        }

        $this->webSessionService->revokeRefreshTokenFromRequest($request);

        // Invalidate session when available (stateful web requests).
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout bem-sucedido'
        ])->cookie($this->webSessionService->forgetAccessCookie($request))
            ->cookie($this->webSessionService->forgetRefreshCookie($request));
    }

    /**
     * Get authenticated user profile
     * Returns current user data
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $request->user()->id,
                'first_name' => $request->user()->first_name,
                'last_name' => $request->user()->last_name,
                'email' => $request->user()->email,
                'full_name' => $request->user()->fullName,
            ]
        ]);
    }

    /**
     * Verify email address
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $authenticatedUser = $request->user();

        if (! $authenticatedUser || (string) $authenticatedUser->id !== (string) $request->route('id')) {
            return $this->apiErrorResponse(
                'AUTH_VERIFICATION_FORBIDDEN',
                'You are not authorized to verify this email address.',
                null,
                403
            );
        }

        $user = User::findOrFail($request->route('id'));

        if (!hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return $this->apiErrorResponse(
                'AUTH_INVALID_VERIFICATION_LINK',
                'Invalid verification link.',
                null,
                400
            );
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified.'
            ]);
        }

        if ($user->markEmailAsVerified()) {
            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully.'
            ]);
        }

        return $this->apiErrorResponse(
            'AUTH_EMAIL_VERIFICATION_FAILED',
            'Failed to verify email.',
            null,
            500
        );
    }

    /**
     * Resend email verification notification
     */
    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->apiErrorResponse(
                'AUTH_EMAIL_ALREADY_VERIFIED',
                'Email already verified.',
                null,
                400
            );
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent.'
        ]);
    }

    /**
     * Check email verification status
     */
    public function checkVerificationStatus(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'email_verified' => $request->user()->hasVerifiedEmail(),
            'email_verified_at' => $request->user()->email_verified_at
        ]);
    }

    /**
     * Get current authenticated user
     * 
     * GET /api/user
     */
    public function user(Request $request)
    {
        $user = $request->user();
        $subscription = $this->resolveSessionSubscription($user);
        $session = $this->webSessionService->sessionPayloadFromRequest($request);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'phone_number' => $user->phone_number,
                ],
                'subscription' => $subscription,
                'has_active_subscription' => $user->hasActiveSubscription(),
                'session' => $session,
            ]
        ]);
    }
}
