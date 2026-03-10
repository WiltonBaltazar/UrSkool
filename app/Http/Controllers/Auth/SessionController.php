<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\WebSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class SessionController extends Controller
{
    public function __construct(
        private readonly WebSessionService $webSessionService
    ) {}

    public function user(Request $request): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();

        return response()->json([
            'data' => $user ? $this->transformUser($user) : null,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function signupAvailability(): JsonResponse
    {
        return response()->json([
            'data' => [
                'allowSelfSignup' => $this->allowsSelfSignup(),
            ],
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function register(Request $request): JsonResponse
    {
        if (! $this->allowsSelfSignup()) {
            throw ValidationException::withMessages([
                'signup' => 'O registo de novas contas está desativado no momento.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\s\-\.\']+$/u'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.regex' => 'O nome não pode conter emojis.',
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => strtolower((string) $validated['email']),
            'password' => Hash::make((string) $validated['password']),
            'is_admin' => false,
        ]);

        Auth::login($user, true);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $this->webSessionService->revokeAccessTokenFromRequest($request);
        $this->webSessionService->revokeRefreshTokenFromRequest($request);
        $session = $this->webSessionService->issueSession($user, $request);

        return response()->json([
            'message' => 'Conta criada com sucesso.',
            'data' => $this->transformUser($user),
            'session' => $session['session'],
        ], 201)
            ->cookie($session['access_cookie'])
            ->cookie($session['refresh_cookie']);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, true)) {
            $legacyUser = User::query()
                ->where('email', (string) $credentials['email'])
                ->first();

            if (
                $legacyUser instanceof User
                && hash_equals((string) $legacyUser->password, (string) $credentials['password'])
            ) {
                $legacyUser->forceFill([
                    'password' => Hash::make((string) $credentials['password']),
                ])->save();

                Auth::login($legacyUser, true);
            } else {
                throw ValidationException::withMessages([
                    'email' => 'Credenciais de acesso inválidas.',
                ]);
            }
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'email' => 'Não foi possível iniciar sessão.',
            ]);
        }

        $this->webSessionService->revokeAccessTokenFromRequest($request);
        $this->webSessionService->revokeRefreshTokenFromRequest($request);
        $session = $this->webSessionService->issueSession($user, $request);

        return response()->json([
            'message' => 'Sessão iniciada com sucesso.',
            'data' => $this->transformUser($user),
            'session' => $session['session'],
        ])
            ->cookie($session['access_cookie'])
            ->cookie($session['refresh_cookie']);
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $this->webSessionService->resolveRefreshTokenFromRequest($request);

        if (! $refreshToken || ! ($refreshToken->tokenable instanceof User)) {
            return response()->json([
                'message' => 'Sessão expirada. Inicia sessão novamente.',
            ], 401)
                ->cookie($this->webSessionService->forgetAccessCookie($request))
                ->cookie($this->webSessionService->forgetRefreshCookie($request));
        }

        $user = $refreshToken->tokenable;
        $this->webSessionService->revokeAccessTokenFromRequest($request);
        $refreshToken->delete();

        Auth::login($user, true);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $session = $this->webSessionService->issueSession($user, $request);

        return response()->json([
            'message' => 'Sessão renovada com sucesso.',
            'data' => $this->transformUser($user),
            'session' => $session['session'],
        ])
            ->cookie($session['access_cookie'])
            ->cookie($session['refresh_cookie']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = $request->user('sanctum')?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } elseif ($request->bearerToken()) {
            PersonalAccessToken::findToken($request->bearerToken())?->delete();
        }

        $this->webSessionService->revokeRefreshTokenFromRequest($request);
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Sessão terminada com sucesso.',
        ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->cookie($this->webSessionService->forgetAccessCookie($request))
            ->cookie($this->webSessionService->forgetRefreshCookie($request));
    }

    private function transformUser($user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'isAdmin' => (bool) $user->is_admin,
        ];
    }

    private function allowsSelfSignup(): bool
    {
        $allowSelfSignup = AppSetting::query()
            ->where('key', 'allow_self_signup')
            ->value('value');

        return $allowSelfSignup !== 'false';
    }
}
