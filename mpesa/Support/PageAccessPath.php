<?php

namespace App\Support;

class PageAccessPath
{
    public static function normalize(string $path): string
    {
        $trimmedPath = trim($path);

        if ($trimmedPath === '') {
            return '/';
        }

        if (str_starts_with($trimmedPath, 'http://') || str_starts_with($trimmedPath, 'https://')) {
            $parsedPath = parse_url($trimmedPath, PHP_URL_PATH);
            $trimmedPath = is_string($parsedPath) ? $parsedPath : '/';
        }

        if (! str_starts_with($trimmedPath, '/')) {
            $trimmedPath = "/{$trimmedPath}";
        }

        $trimmedPath = preg_replace('#/+#', '/', $trimmedPath) ?: '/';

        if ($trimmedPath !== '/') {
            $trimmedPath = rtrim($trimmedPath, '/');
        }

        return $trimmedPath === '' ? '/' : $trimmedPath;
    }
}
