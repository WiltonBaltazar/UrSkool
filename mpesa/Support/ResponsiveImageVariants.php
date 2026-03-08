<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ResponsiveImageVariants
{
    /**
     * @return array{
     *     src: string,
     *     fallback: string,
     *     sources: array<int, array{url: string, width: int, type: string}>
     * }|null
     */
    public function payload(?string $path): ?array
    {
        if (! $path) {
            return null;
        }

        if ($this->isAbsoluteUrl($path)) {
            return [
                'src' => $path,
                'fallback' => $path,
                'sources' => [],
            ];
        }

        $normalized = $this->normalizePath($path);
        if (! $normalized) {
            return null;
        }

        $disk = Storage::disk('public');
        $fallbackUrl = $disk->url($normalized);

        $sources = [];
        foreach ($this->configuredWidths() as $width) {
            $variantPath = $this->variantPath($normalized, $width);

            if (! $disk->exists($variantPath)) {
                continue;
            }

            $sources[] = [
                'url' => $disk->url($variantPath),
                'width' => $width,
                'type' => 'image/webp',
            ];
        }

        usort($sources, static fn (array $left, array $right): int => $left['width'] <=> $right['width']);

        $preferredSrc = $sources !== []
            ? $sources[array_key_last($sources)]['url']
            : $fallbackUrl;

        return [
            'src' => $preferredSrc,
            'fallback' => $fallbackUrl,
            'sources' => $sources,
        ];
    }

    public function generateForPath(?string $path, bool $force = false): int
    {
        if (! config('media.responsive.enabled', true)) {
            return 0;
        }

        if (! $path || $this->isAbsoluteUrl($path)) {
            return 0;
        }

        $normalized = $this->normalizePath($path);
        if (! $normalized) {
            return 0;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($normalized)) {
            return 0;
        }

        $sourceAbsolutePath = $disk->path($normalized);
        if (! is_file($sourceAbsolutePath)) {
            return 0;
        }

        $sourceInfo = @getimagesize($sourceAbsolutePath);
        if ($sourceInfo === false) {
            return 0;
        }

        $sourceWidth = (int) ($sourceInfo[0] ?? 0);
        $sourceHeight = (int) ($sourceInfo[1] ?? 0);
        $sourceMime = (string) ($sourceInfo['mime'] ?? '');

        if ($sourceWidth < 1 || $sourceHeight < 1) {
            return 0;
        }

        $sourceImage = $this->createSourceImage($sourceAbsolutePath, $sourceMime);
        if (! $sourceImage) {
            return 0;
        }

        $sourceMTime = @filemtime($sourceAbsolutePath) ?: null;
        $quality = $this->quality();
        $generatedCount = 0;

        try {
            foreach ($this->configuredWidths() as $targetWidth) {
                if ($targetWidth < 1 || $targetWidth > $sourceWidth) {
                    continue;
                }

                $variantPath = $this->variantPath($normalized, $targetWidth);
                $variantAbsolutePath = $disk->path($variantPath);

                if (! $force && $disk->exists($variantPath) && $sourceMTime !== null) {
                    $variantMTime = @filemtime($variantAbsolutePath) ?: null;
                    if ($variantMTime !== null && $variantMTime >= $sourceMTime) {
                        continue;
                    }
                }

                $targetHeight = (int) max(1, round(($sourceHeight / $sourceWidth) * $targetWidth));
                $resized = imagecreatetruecolor($targetWidth, $targetHeight);

                if ($resized === false) {
                    continue;
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

                $tmpPath = tempnam(sys_get_temp_dir(), 'resp_img_');

                if ($tmpPath === false) {
                    imagedestroy($resized);
                    continue;
                }

                $encoded = imagewebp($resized, $tmpPath, $quality);
                imagedestroy($resized);

                if (! $encoded) {
                    @unlink($tmpPath);
                    continue;
                }

                $content = @file_get_contents($tmpPath);
                @unlink($tmpPath);

                if (! is_string($content)) {
                    continue;
                }

                $disk->put($variantPath, $content, ['visibility' => 'public']);
                $generatedCount++;
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to generate responsive image variants.', [
                'path' => $path,
                'normalized_path' => $normalized,
                'error' => $exception->getMessage(),
            ]);
        } finally {
            imagedestroy($sourceImage);
        }

        return $generatedCount;
    }

    public function normalizePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = ltrim($path, '/');

        if (str_starts_with($normalized, 'public/storage/')) {
            $normalized = substr($normalized, strlen('public/storage/'));
        }

        if (str_starts_with($normalized, 'storage/app/public/')) {
            $normalized = substr($normalized, strlen('storage/app/public/'));
        }

        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        if (str_starts_with($normalized, 'public/')) {
            $normalized = substr($normalized, strlen('public/'));
        }

        return $normalized !== '' ? $normalized : null;
    }

    private function variantPath(string $normalizedPath, int $width): string
    {
        $info = pathinfo($normalizedPath);
        $directory = $info['dirname'] ?? '';
        $filename = $info['filename'] ?? 'image';

        $variantFilename = sprintf('%s__w%d.webp', $filename, $width);

        if ($directory === '' || $directory === '.') {
            return $variantFilename;
        }

        return $directory.'/'.$variantFilename;
    }

    private function configuredWidths(): array
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

    private function quality(): int
    {
        return (int) max(0, min(100, (int) config('media.responsive.quality', 80)));
    }

    private function isAbsoluteUrl(string $path): bool
    {
        return preg_match('/^https?:\/\//i', $path) === 1;
    }

    private function createSourceImage(string $absolutePath, string $mime): mixed
    {
        if (! function_exists('imagewebp')) {
            return null;
        }

        $loader = match (strtolower($mime)) {
            'image/jpeg' => 'imagecreatefromjpeg',
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
