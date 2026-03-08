<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Cookie;

class WebSessionService
{
    public const ACCESS_TOKEN_NAME = 'auth_token';
    public const ACCESS_TOKEN_ABILITY = 'web:session';
    public const ACCESS_COOKIE_NAME = 'auth_token';

    public const REFRESH_TOKEN_NAME = 'auth_refresh_token';
    public const REFRESH_TOKEN_ABILITY = 'web:refresh';
    public const REFRESH_COOKIE_NAME = 'auth_refresh_token';

    public function issueSession(User $user, Request $request): array
    {
        $accessExpiresAt = now()->addMinutes($this->accessTokenTtlMinutes());
        $refreshExpiresAt = now()->addDays($this->refreshTokenTtlDays());

        $accessToken = $user->createToken(
            name: self::ACCESS_TOKEN_NAME,
            abilities: [self::ACCESS_TOKEN_ABILITY],
            expiresAt: $accessExpiresAt
        );

        $refreshToken = $user->createToken(
            name: self::REFRESH_TOKEN_NAME,
            abilities: [self::REFRESH_TOKEN_ABILITY],
            expiresAt: $refreshExpiresAt
        );

        return [
            'access_token_plain' => $accessToken->plainTextToken,
            'refresh_token_plain' => $refreshToken->plainTextToken,
            'access_token_expires_at' => $accessExpiresAt,
            'refresh_token_expires_at' => $refreshExpiresAt,
            'session' => $this->sessionPayload($accessExpiresAt, $refreshExpiresAt),
            'access_cookie' => $this->accessCookie($accessToken->plainTextToken, $request, $accessExpiresAt),
            'refresh_cookie' => $this->refreshCookie($refreshToken->plainTextToken, $request, $refreshExpiresAt),
        ];
    }

    public function accessTokenTtlMinutes(): int
    {
        $configured = (int) env('WEB_ACCESS_TOKEN_TTL_MINUTES', 60);
        return max($this->renewBeforeMinutes() + 5, $configured);
    }

    public function refreshTokenTtlDays(): int
    {
        $configured = (int) env('WEB_REFRESH_TOKEN_TTL_DAYS', 30);
        return max(1, $configured);
    }

    public function renewBeforeMinutes(): int
    {
        $configured = (int) env('WEB_TOKEN_RENEW_BEFORE_MINUTES', 10);
        return max(1, $configured);
    }

    public function sessionPayload(
        ?CarbonInterface $accessTokenExpiresAt,
        ?CarbonInterface $refreshTokenExpiresAt = null
    ): ?array {
        if (! $accessTokenExpiresAt) {
            return null;
        }

        return [
            'access_token_expires_at' => $accessTokenExpiresAt->toIso8601String(),
            'refresh_token_expires_at' => $refreshTokenExpiresAt?->toIso8601String(),
            'renew_before_seconds' => $this->renewBeforeMinutes() * 60,
        ];
    }

    public function sessionPayloadFromRequest(Request $request): ?array
    {
        $accessToken = $request->user()?->currentAccessToken();

        if (! $accessToken || ! $this->tokenCan($accessToken, self::ACCESS_TOKEN_ABILITY)) {
            return null;
        }

        $refreshToken = $this->resolveRefreshTokenFromRequest($request, false);

        return $this->sessionPayload(
            $this->resolveTokenExpiry($accessToken),
            $this->resolveTokenExpiry($refreshToken)
        );
    }

    public function resolveRefreshTokenFromRequest(Request $request, bool $mustBeValid = true): ?PersonalAccessToken
    {
        $token = $this->findTokenFromCookie($request, self::REFRESH_COOKIE_NAME);

        if (! $token || ! $this->tokenCan($token, self::REFRESH_TOKEN_ABILITY)) {
            return null;
        }

        if ($mustBeValid && ! $this->isTokenStillValid($token)) {
            return null;
        }

        return $token;
    }

    public function resolveAccessTokenFromRequest(Request $request): ?PersonalAccessToken
    {
        $token = $this->findTokenFromCookie($request, self::ACCESS_COOKIE_NAME);

        if (! $token || ! $this->tokenCan($token, self::ACCESS_TOKEN_ABILITY)) {
            return null;
        }

        return $token;
    }

    public function revokeAccessTokenFromRequest(Request $request): void
    {
        $this->resolveAccessTokenFromRequest($request)?->delete();
    }

    public function revokeRefreshTokenFromRequest(Request $request): void
    {
        $this->resolveRefreshTokenFromRequest($request, false)?->delete();
    }

    public function accessCookie(
        string $token,
        Request $request,
        ?CarbonInterface $expiresAt = null
    ): Cookie {
        $minutes = $expiresAt
            ? max(1, now()->diffInMinutes($expiresAt, false))
            : (int) env('AUTH_COOKIE_MINUTES', $this->accessTokenTtlMinutes());

        return cookie(
            name: self::ACCESS_COOKIE_NAME,
            value: $token,
            minutes: $minutes,
            path: '/',
            domain: $this->resolveCookieDomain($request),
            secure: (bool) config('session.secure', false),
            httpOnly: true,
            raw: false,
            sameSite: config('session.same_site', 'lax')
        );
    }

    public function refreshCookie(
        string $token,
        Request $request,
        ?CarbonInterface $expiresAt = null
    ): Cookie {
        $minutes = $expiresAt
            ? max(1, now()->diffInMinutes($expiresAt, false))
            : $this->refreshTokenTtlDays() * 24 * 60;

        return cookie(
            name: self::REFRESH_COOKIE_NAME,
            value: $token,
            minutes: $minutes,
            path: '/',
            domain: $this->resolveCookieDomain($request),
            secure: (bool) config('session.secure', false),
            httpOnly: true,
            raw: false,
            sameSite: config('session.same_site', 'lax')
        );
    }

    public function forgetAccessCookie(Request $request): Cookie
    {
        return cookie()->forget(
            self::ACCESS_COOKIE_NAME,
            '/',
            $this->resolveCookieDomain($request)
        );
    }

    public function forgetRefreshCookie(Request $request): Cookie
    {
        return cookie()->forget(
            self::REFRESH_COOKIE_NAME,
            '/',
            $this->resolveCookieDomain($request)
        );
    }

    private function tokenCan(PersonalAccessToken $token, string $ability): bool
    {
        $abilities = is_array($token->abilities) ? $token->abilities : [];
        return in_array($ability, $abilities, true);
    }

    private function isTokenStillValid(PersonalAccessToken $token): bool
    {
        $expiry = $this->resolveTokenExpiry($token);
        return $expiry ? ! $expiry->isPast() : true;
    }

    private function resolveTokenExpiry(?PersonalAccessToken $token): ?CarbonInterface
    {
        if (! $token) {
            return null;
        }

        if ($token->expires_at) {
            return $token->expires_at;
        }

        $sanctumExpiration = (int) config('sanctum.expiration', 0);
        if ($sanctumExpiration > 0 && $token->created_at) {
            return $token->created_at->copy()->addMinutes($sanctumExpiration);
        }

        return null;
    }

    private function findTokenFromCookie(Request $request, string $cookieName): ?PersonalAccessToken
    {
        $plainText = trim((string) $request->cookie($cookieName, ''));
        if ($plainText === '') {
            return null;
        }

        return PersonalAccessToken::findToken($plainText);
    }

    private function resolveCookieDomain(Request $request): ?string
    {
        $host = $this->normalizeHost((string) $request->getHost());
        if ($host === null) {
            return null;
        }

        $originHost = $this->extractOriginHost((string) $request->headers->get('origin', ''));
        $candidates = array_values(array_filter([
            $this->normalizeCookieDomain((string) config('lenda.auth.cookie_domain', '')),
            $this->normalizeCookieDomain((string) config('session.domain', '')),
        ]));

        if ($originHost !== null && $originHost !== $host) {
            foreach ($candidates as $candidate) {
                if ($this->hostMatchesDomain($host, $candidate) && $this->hostMatchesDomain($originHost, $candidate)) {
                    return $candidate;
                }
            }
        }

        foreach ($candidates as $candidate) {
            if ($this->hostMatchesDomain($host, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeCookieDomain(string $domain): ?string
    {
        $trimmed = trim($domain);
        if ($trimmed === '' || strtolower($trimmed) === 'null') {
            return null;
        }

        return $trimmed;
    }

    private function normalizeHost(string $host): ?string
    {
        $normalized = strtolower(trim($host));
        if ($normalized === '' || $normalized === 'localhost' || filter_var($normalized, FILTER_VALIDATE_IP)) {
            return null;
        }

        return $normalized;
    }

    private function extractOriginHost(string $originHeader): ?string
    {
        $origin = trim($originHeader);
        if ($origin === '') {
            return null;
        }

        $parsedHost = parse_url($origin, PHP_URL_HOST);
        if (!is_string($parsedHost)) {
            return null;
        }

        return $this->normalizeHost($parsedHost);
    }

    private function hostMatchesDomain(string $host, string $domain): bool
    {
        $normalizedDomain = ltrim(strtolower(trim($domain)), '.');
        if ($normalizedDomain === '') {
            return false;
        }

        return $host === $normalizedDomain || str_ends_with($host, '.'.$normalizedDomain);
    }
}
