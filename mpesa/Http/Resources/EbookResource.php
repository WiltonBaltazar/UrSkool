<?php

namespace App\Http\Resources;

use App\Services\ProtectedMediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EbookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canAccessPremiumMedia = $this->canAccessPremiumMedia($request);
        $protectedMedia = app(ProtectedMediaService::class);
        $fileUrl = null;
        $coverImageUrl = null;
        $coverImageResponsive = null;

        if ($canAccessPremiumMedia) {
            $protectedMedia->ensureStoredOnPrivateDisk(
                $this->resource,
                'file',
                ProtectedMediaService::EBOOK_DIRECTORY
            );

            $fileUrl = route('api.media.ebooks.file', ['ebook' => $this->id]);

            $protectedMedia->ensureStoredOnPrivateDisk(
                $this->resource,
                'cover_image',
                ProtectedMediaService::EBOOK_COVER_DIRECTORY
            );

            $coverImageUrl = route('api.media.ebooks.cover', ['ebook' => $this->id]);
            $coverImageResponsive = $protectedMedia->responsivePayloadForRoute(
                $this->cover_image,
                'api.media.ebooks.cover',
                ['ebook' => $this->id]
            );
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'cover_image' => $coverImageUrl,
            'cover_image_responsive' => $coverImageResponsive,
            'description' => $this->description,
            'chapters' => $this->chapters,
            'file' => $fileUrl,
            'year' => $this->year,
            'ebook_serie_id' => $this->ebook_serie_id,
            'created_at' => DateTimeResource::make($this->created_at),
            'updated_at' => $this->updated_at,
        ];
    }

    private function canAccessPremiumMedia(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        return $user->hasActiveSubscription() && $user->hasPlan('premium');
    }

}
