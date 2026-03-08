<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Audiobook;
use App\Models\AudiobookChapter;
use App\Models\Ebook;
use App\Models\EbookSerie;
use App\Models\AudiobookSerie;
use App\Models\Episode;
use App\Services\ProtectedMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProtectedMediaController extends Controller
{
    public function __construct(
        private readonly ProtectedMediaService $protectedMediaService
    ) {}

    public function ebook(Request $request, Ebook $ebook): Response
    {
        abort_unless($ebook->status === 'published', 404);

        if ($externalUrl = $this->externalUrl($ebook->file)) {
            return redirect()->away($externalUrl);
        }

        $privatePath = $this->protectedMediaService->ensureStoredOnPrivateDisk(
            $ebook,
            'file',
            ProtectedMediaService::EBOOK_DIRECTORY
        );

        if (! is_string($privatePath) || $privatePath === '') {
            abort(404);
        }

        return $this->privateFileResponse($privatePath);
    }

    public function audiobookChapter(Request $request, AudiobookChapter $chapter): Response
    {
        $chapter->loadMissing('audiobook');
        abort_unless($chapter->audiobook && $chapter->audiobook->status === 'published', 404);

        if ($externalUrl = $this->externalUrl($chapter->audio_file)) {
            return redirect()->away($externalUrl);
        }

        $privatePath = $this->protectedMediaService->ensureStoredOnPrivateDisk(
            $chapter,
            'audio_file',
            ProtectedMediaService::AUDIOBOOK_CHAPTER_DIRECTORY
        );

        if (! is_string($privatePath) || $privatePath === '') {
            abort(404);
        }

        return $this->privateFileResponse($privatePath);
    }

    public function ebookCover(Request $request, Ebook $ebook): Response
    {
        abort_unless($ebook->status === 'published', 404);

        if ($externalUrl = $this->externalUrl($ebook->cover_image)) {
            return redirect()->away($externalUrl);
        }

        $privatePath = $this->protectedMediaService->ensureStoredOnPrivateDisk(
            $ebook,
            'cover_image',
            ProtectedMediaService::EBOOK_COVER_DIRECTORY
        );

        if (! is_string($privatePath) || $privatePath === '') {
            abort(404);
        }

        return $this->privateFileResponse($privatePath, $request, true);
    }

    public function audiobookCover(Request $request, Audiobook $audiobook): Response
    {
        abort_unless($audiobook->status === 'published', 404);

        if ($externalUrl = $this->externalUrl($audiobook->cover_image)) {
            return redirect()->away($externalUrl);
        }

        $privatePath = $this->protectedMediaService->ensureStoredOnPrivateDisk(
            $audiobook,
            'cover_image',
            ProtectedMediaService::AUDIOBOOK_COVER_DIRECTORY
        );

        if (! is_string($privatePath) || $privatePath === '') {
            abort(404);
        }

        return $this->privateFileResponse($privatePath, $request, true);
    }

    public function ebookSeriesCover(Request $request, EbookSerie $serie): Response
    {
        if ($externalUrl = $this->externalUrl($serie->cover_image)) {
            return redirect()->away($externalUrl);
        }

        $privatePath = $this->protectedMediaService->ensureStoredOnPrivateDisk(
            $serie,
            'cover_image',
            ProtectedMediaService::EBOOK_SERIE_COVER_DIRECTORY
        );

        if (! is_string($privatePath) || $privatePath === '') {
            abort(404);
        }

        return $this->privateFileResponse($privatePath, $request, true);
    }

    public function audiobookSeriesCover(Request $request, AudiobookSerie $serie): Response
    {
        if ($externalUrl = $this->externalUrl($serie->cover_image)) {
            return redirect()->away($externalUrl);
        }

        $privatePath = $this->protectedMediaService->ensureStoredOnPrivateDisk(
            $serie,
            'cover_image',
            ProtectedMediaService::AUDIOBOOK_SERIE_COVER_DIRECTORY
        );

        if (! is_string($privatePath) || $privatePath === '') {
            abort(404);
        }

        return $this->privateFileResponse($privatePath, $request, true);
    }

    public function episodeAudio(Request $request, Episode $episode): Response
    {
        abort_unless($episode->status === 'published', 404);

        if ($externalUrl = $this->externalUrl($episode->audio_file)) {
            return redirect()->away($externalUrl);
        }

        $privatePath = $this->protectedMediaService->ensureStoredOnPrivateDisk(
            $episode,
            'audio_file',
            ProtectedMediaService::PODCAST_EPISODE_AUDIO_DIRECTORY
        );

        if (! is_string($privatePath) || $privatePath === '') {
            abort(404);
        }

        return $this->privateFileResponse($privatePath);
    }

    private function privateFileResponse(string $privatePath, ?Request $request = null, bool $allowResponsiveImageVariants = false): BinaryFileResponse
    {
        if ($allowResponsiveImageVariants && $request !== null) {
            $requestedWidth = max(0, min(4096, (int) $request->query('w', 0)));

            if ($requestedWidth > 0) {
                $variantPath = $this->protectedMediaService->resolveResponsiveVariantPath($privatePath, $requestedWidth);

                if (is_string($variantPath) && $variantPath !== '') {
                    $privatePath = $variantPath;
                }
            }
        }

        $absolutePath = $this->protectedMediaService->resolveAbsolutePath($privatePath);

        if (
            ! is_string($absolutePath)
            || $absolutePath === ''
            || ! is_file($absolutePath)
            || ! is_readable($absolutePath)
        ) {
            abort(404);
        }

        $mimeType = $this->resolveMimeType($privatePath, $absolutePath);

        try {
            return response()->file($absolutePath, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable $exception) {
            Log::warning('Failed to stream protected media file.', [
                'private_path' => $privatePath,
                'absolute_path' => $absolutePath,
                'error' => $exception->getMessage(),
            ]);

            abort(404);
        }
    }

    private function resolveMimeType(string $privatePath, string $absolutePath): string
    {
        try {
            $mimeFromDisk = Storage::disk('local')->mimeType($privatePath);
            if (is_string($mimeFromDisk) && $mimeFromDisk !== '') {
                return $mimeFromDisk;
            }
        } catch (Throwable) {
            // Fallbacks below cover environments where mime detection is unavailable.
        }

        if (function_exists('finfo_open') && function_exists('finfo_file')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                try {
                    $mime = @finfo_file($finfo, $absolutePath);
                    if (is_string($mime) && $mime !== '') {
                        return $mime;
                    }
                } finally {
                    finfo_close($finfo);
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($absolutePath);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }

    private function externalUrl(?string $path): ?string
    {
        $value = trim((string) $path);

        if ($value === '') {
            return null;
        }

        return preg_match('/^https?:\/\//i', $value) === 1 ? $value : null;
    }
}
