<?php

namespace App\Support;

class WebPushEndpointValidator
{
    public static function isAllowed(string $endpoint): bool
    {
        if (! filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($endpoint);
        if (! is_array($parsed)) {
            return false;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        if ($scheme !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));
        if (
            $host === ''
            || $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
        ) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        foreach (self::allowedHosts() as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function allowedHosts(): array
    {
        $hosts = config('services.webpush.allowed_hosts', []);

        if (! is_array($hosts)) {
            return [];
        }

        return array_values(array_filter(array_unique(array_map(
            static fn (mixed $host) => strtolower(trim((string) $host)),
            $hosts
        ))));
    }
}
