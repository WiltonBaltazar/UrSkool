<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AudioDuration
{
    public static function fromPublicDiskPath(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $absolutePath = self::resolveAbsolutePath($path);

        if (! is_file($absolutePath)) {
            return null;
        }

        $seconds = self::probeSeconds($absolutePath);

        if ($seconds === null) {
            return null;
        }

        return self::formatSeconds($seconds);
    }

    private static function resolveAbsolutePath(string $path): string
    {
        $value = trim($path);

        if (Str::startsWith($value, ['http://', 'https://'])) {
            $parsedPath = parse_url($value, PHP_URL_PATH);

            if (is_string($parsedPath) && $parsedPath !== '') {
                $value = $parsedPath;
            }
        }

        if (is_file($value)) {
            return $value;
        }

        $value = ltrim($value, '/');
        $publicRelative = preg_replace('#^storage/#', '', $value) ?? $value;

        $localAbsolutePath = Storage::disk('local')->path($value);
        if (is_file($localAbsolutePath)) {
            return $localAbsolutePath;
        }

        $localAbsolutePath = Storage::disk('local')->path($publicRelative);
        if (is_file($localAbsolutePath)) {
            return $localAbsolutePath;
        }

        return Storage::disk('public')->path($publicRelative);
    }

    private static function probeSeconds(string $absolutePath): ?int
    {
        foreach (self::ffprobeCandidates() as $binary) {
            $command = sprintf(
                "%s -v error -show_entries format=duration -of default=nokey=1:noprint_wrappers=1 %s 2>/dev/null",
                escapeshellarg($binary),
                escapeshellarg($absolutePath)
            );

            $output = shell_exec($command);

            if (! is_string($output)) {
                continue;
            }

            $raw = trim($output);

            if ($raw === '' || ! is_numeric($raw)) {
                continue;
            }

            $seconds = (int) round((float) $raw);

            return $seconds > 0 ? $seconds : null;
        }

        return null;
    }

    /**
     * Keep explicit paths first for PHP-FPM/queue workers where PATH is minimal.
     *
     * @return list<string>
     */
    private static function ffprobeCandidates(): array
    {
        $candidates = [
            (string) env('FFPROBE_BINARY', ''),
            '/opt/homebrew/bin/ffprobe',
            '/usr/local/bin/ffprobe',
            '/usr/bin/ffprobe',
            'ffprobe',
        ];

        return array_values(array_unique(array_filter($candidates, static fn (string $value): bool => $value !== '')));
    }

    private static function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }
}
