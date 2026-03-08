<?php

namespace App\Services;

use App\Support\ResponsiveImageVariants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProtectedMediaService
{
    public const EBOOK_DIRECTORY = 'protected/ebooks';
    public const EBOOK_COVER_DIRECTORY = 'protected/ebook-covers';
    public const EBOOK_SERIE_COVER_DIRECTORY = 'protected/ebook-series-covers';
    public const AUDIOBOOK_COVER_DIRECTORY = 'protected/audiobook-covers';
    public const AUDIOBOOK_SERIE_COVER_DIRECTORY = 'protected/audiobook-series-covers';
    public const AUDIOBOOK_CHAPTER_DIRECTORY = 'protected/audiobook-chapters';
    public const PODCAST_EPISODE_AUDIO_DIRECTORY = 'protected/podcast-episodes';
    public const RESPONSIVE_VARIANT_DIRECTORY = 'protected/responsive';

    /**
     * Ensure a model attribute that points to a file path is stored on the local/private disk.
     * Returns the resolved path on the private disk when available.
     */
    public function ensureStoredOnPrivateDisk(Model $model, string $attribute, string $targetDirectory): ?string
    {
        $value = trim((string) $model->getAttribute($attribute));
        if ($value === '' || $this->isAbsoluteUrl($value)) {
            return null;
        }

        try {
            $localDisk = Storage::disk('local');
        } catch (Throwable) {
            return null;
        }

        foreach ($this->candidatePaths($value) as $candidate) {
            try {
                $existsOnLocal = $localDisk->exists($candidate);
            } catch (Throwable) {
                $existsOnLocal = false;
            }

            if ($existsOnLocal) {
                if ($value !== $candidate) {
                    $this->persistAttributeSafely($model, $attribute, $candidate);
                }

                return $candidate;
            }
        }

        try {
            $publicDisk = Storage::disk('public');
        } catch (Throwable) {
            return null;
        }
        $sourcePath = null;

        foreach ($this->candidatePaths($value) as $candidate) {
            try {
                $existsOnPublic = $publicDisk->exists($candidate);
            } catch (Throwable) {
                $existsOnPublic = false;
            }

            if ($existsOnPublic) {
                $sourcePath = $candidate;
                break;
            }
        }

        if ($sourcePath === null) {
            return null;
        }

        try {
            $destinationPath = $this->uniqueDestinationPath($targetDirectory, $sourcePath, (string) $model->getKey());
            $readStream = $publicDisk->readStream($sourcePath);

            if (! is_resource($readStream)) {
                return null;
            }

            try {
                $written = $localDisk->writeStream($destinationPath, $readStream);
            } finally {
                fclose($readStream);
            }

            if ($written === false || ! $localDisk->exists($destinationPath)) {
                return null;
            }

            $persisted = $this->persistAttributeSafely($model, $attribute, $destinationPath);

            // Keep source file when persistence fails to avoid orphaning DB references.
            if ($persisted) {
                $publicDisk->delete($sourcePath);
            }

            return $destinationPath;
        } catch (Throwable) {
            return null;
        }
    }

    public function resolveAbsolutePath(string $path): ?string
    {
        $value = trim($path);
        if ($value === '' || $this->isAbsoluteUrl($value)) {
            return null;
        }

        try {
            $localDisk = Storage::disk('local');
        } catch (Throwable) {
            return null;
        }

        foreach ($this->candidatePaths($value) as $candidate) {
            try {
                if (! $localDisk->exists($candidate)) {
                    continue;
                }

                $absolutePath = $localDisk->path($candidate);
                if (is_file($absolutePath)) {
                    return $absolutePath;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     src: string,
     *     fallback: string,
     *     sources: array<int, array{url: string, width: int, type: string}>
     * }|null
     */
    public function responsivePayloadForRoute(?string $sourcePath, string $routeName, array $routeParameters = []): ?array
    {
        $value = trim((string) $sourcePath);

        if ($value === '') {
            return null;
        }

        if ($this->isAbsoluteUrl($value)) {
            return [
                'src' => $value,
                'fallback' => $value,
                'sources' => [],
            ];
        }

        $fallbackUrl = route($routeName, $routeParameters);
        $sources = [];

        foreach ($this->configuredResponsiveWidths() as $width) {
            $sources[] = [
                'url' => $this->appendQueryString($fallbackUrl, ['w' => $width]),
                'width' => $width,
                'type' => 'image/webp',
            ];
        }

        $preferredSrc = $sources !== []
            ? $sources[array_key_last($sources)]['url']
            : $fallbackUrl;

        return [
            'src' => $preferredSrc,
            'fallback' => $fallbackUrl,
            'sources' => $sources,
        ];
    }

    public function resolveResponsiveVariantPath(string $privatePath, int $requestedWidth): ?string
    {
        if ($requestedWidth < 1 || ! function_exists('imagewebp')) {
            return null;
        }

        $absoluteSourcePath = $this->resolveAbsolutePath($privatePath);

        if (
            ! is_string($absoluteSourcePath)
            || $absoluteSourcePath === ''
            || ! is_file($absoluteSourcePath)
            || ! is_readable($absoluteSourcePath)
        ) {
            return null;
        }

        $sourceInfo = @getimagesize($absoluteSourcePath);
        if ($sourceInfo === false) {
            return null;
        }

        $sourceWidth = (int) ($sourceInfo[0] ?? 0);
        $sourceHeight = (int) ($sourceInfo[1] ?? 0);
        $sourceMime = strtolower((string) ($sourceInfo['mime'] ?? ''));

        if ($sourceWidth < 1 || $sourceHeight < 1) {
            return null;
        }

        $targetWidth = $this->selectResponsiveWidth($requestedWidth, $sourceWidth);
        if ($targetWidth < 1) {
            return null;
        }

        $sourceMTime = (int) (@filemtime($absoluteSourcePath) ?: 0);
        $variantPath = $this->responsiveVariantPath($privatePath, $targetWidth, $sourceMTime);
        $localDisk = Storage::disk('local');

        if ($localDisk->exists($variantPath)) {
            $variantAbsolutePath = $localDisk->path($variantPath);
            $variantMTime = (int) (@filemtime($variantAbsolutePath) ?: 0);

            if ($sourceMTime === 0 || $variantMTime >= $sourceMTime) {
                return $variantPath;
            }
        }

        $sourceImage = $this->createSourceImage($absoluteSourcePath, $sourceMime);
        if (! $sourceImage) {
            return null;
        }

        $targetHeight = (int) max(1, round(($sourceHeight / $sourceWidth) * $targetWidth));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($resized === false) {
            imagedestroy($sourceImage);
            return null;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);

        imagecopyresampled(
            $resized,
            $sourceImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        $tmpPath = tempnam(sys_get_temp_dir(), 'priv_img_');
        if ($tmpPath === false) {
            imagedestroy($resized);
            imagedestroy($sourceImage);
            return null;
        }

        $encoded = imagewebp($resized, $tmpPath, $this->responsiveQuality());
        imagedestroy($resized);
        imagedestroy($sourceImage);

        if (! $encoded) {
            @unlink($tmpPath);
            return null;
        }

        $content = @file_get_contents($tmpPath);
        @unlink($tmpPath);

        if (! is_string($content)) {
            return null;
        }

        $localDisk->put($variantPath, $content);

        return $localDisk->exists($variantPath) ? $variantPath : null;
    }

    /**
     * @return list<string>
     */
    private function candidatePaths(string $path): array
    {
        $normalized = app(ResponsiveImageVariants::class)->normalizePath($path);
        $paths = [
            ltrim(trim($path), '/'),
            $normalized,
        ];

        return array_values(array_unique(array_filter($paths, static fn (?string $value): bool => is_string($value) && $value !== '')));
    }

    private function uniqueDestinationPath(string $targetDirectory, string $sourcePath, string $key): string
    {
        $directory = trim($targetDirectory, '/');
        $extension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
        $baseName = (string) pathinfo($sourcePath, PATHINFO_FILENAME);
        $safeBaseName = Str::limit(Str::slug($baseName), 80, '');

        if ($safeBaseName === '') {
            $safeBaseName = 'media';
        }

        $localDisk = Storage::disk('local');

        do {
            $fileName = sprintf(
                '%s-%s-%s%s',
                $safeBaseName,
                Str::lower(Str::substr(sha1($key), 0, 10)),
                Str::lower(Str::random(12)),
                $extension !== '' ? '.'.$extension : ''
            );
            $path = $directory.'/'.$fileName;
        } while ($localDisk->exists($path));

        return $path;
    }

    private function responsiveVariantPath(string $privatePath, int $width, int $sourceMTime): string
    {
        $normalized = ltrim(trim($privatePath), '/');
        $signature = sha1($normalized.'|w'.$width.'|t'.$sourceMTime.'|q'.$this->responsiveQuality());

        return trim(self::RESPONSIVE_VARIANT_DIRECTORY, '/').'/'.substr($signature, 0, 40).'.webp';
    }

    private function selectResponsiveWidth(int $requestedWidth, int $sourceWidth): int
    {
        $requestedWidth = max(1, $requestedWidth);
        $sourceWidth = max(1, $sourceWidth);
        $configured = array_values(array_filter(
            $this->configuredResponsiveWidths(),
            static fn (int $width): bool => $width > 0 && $width <= $sourceWidth
        ));

        if ($configured === []) {
            return min($requestedWidth, $sourceWidth);
        }

        $selected = $configured[0];

        foreach ($configured as $width) {
            if ($width > $requestedWidth) {
                break;
            }

            $selected = $width;
        }

        return $selected;
    }

    /**
     * @return list<int>
     */
    private function configuredResponsiveWidths(): array
    {
        $configured = config('media.responsive.widths', []);

        if (! is_array($configured)) {
            return [];
        }

        $widths = array_values(array_unique(array_map(
            static fn (mixed $width): int => max(0, (int) $width),
            $configured
        )));

        sort($widths);

        return array_values(array_filter($widths, static fn (int $width): bool => $width > 0));
    }

    private function responsiveQuality(): int
    {
        return (int) max(0, min(100, (int) config('media.responsive.quality', 80)));
    }

    private function appendQueryString(string $url, array $query): string
    {
        $queryString = http_build_query($query);

        if ($queryString === '') {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').$queryString;
    }

    private function isAbsoluteUrl(string $path): bool
    {
        return preg_match('/^https?:\/\//i', $path) === 1;
    }

    private function persistAttributeSafely(Model $model, string $attribute, string $value): bool
    {
        try {
            $model->forceFill([$attribute => $value])->saveQuietly();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function createSourceImage(string $absolutePath, string $mime): mixed
    {
        $loader = match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/gif' => 'imagecreatefromgif',
            'image/webp' => 'imagecreatefromwebp',
            default => null,
        };

        if (! $loader || ! function_exists($loader)) {
            return null;
        }

        try {
            return @$loader($absolutePath);
        } catch (Throwable) {
            return null;
        }
    }
}
