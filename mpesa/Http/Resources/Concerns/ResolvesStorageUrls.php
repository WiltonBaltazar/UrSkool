<?php

namespace App\Http\Resources\Concerns;

use App\Support\ResponsiveImageVariants;
use Illuminate\Support\Facades\Storage;

trait ResolvesStorageUrls
{
    protected function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Keep absolute URLs untouched (e.g., already migrated CDN links).
        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        $normalized = app(ResponsiveImageVariants::class)->normalizePath($path);

        if (! $normalized) {
            return null;
        }

        return Storage::disk('public')->url($normalized);
    }

    /**
     * @return array{
     *     src: string,
     *     fallback: string,
     *     sources: array<int, array{url: string, width: int, type: string}>
     * }|null
     */
    protected function responsiveImage(?string $path): ?array
    {
        return app(ResponsiveImageVariants::class)->payload($path);
    }
}
