<?php

namespace App\Http\Resources;

use App\Services\ProtectedMediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AudiobookResource extends JsonResource
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
        $coverImageUrl = null;
        $coverImageResponsive = null;

        if ($canAccessPremiumMedia) {
            $protectedMedia->ensureStoredOnPrivateDisk(
                $this->resource,
                'cover_image',
                ProtectedMediaService::AUDIOBOOK_COVER_DIRECTORY
            );

            $coverImageUrl = route('api.media.audiobooks.cover', ['audiobook' => $this->id]);
            $coverImageResponsive = $protectedMedia->responsivePayloadForRoute(
                $this->cover_image,
                'api.media.audiobooks.cover',
                ['audiobook' => $this->id]
            );
        }

        return [
            'id' => $this->id,
            'audiobook_serie_id' => $this->audiobook_serie_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_image' => $coverImageUrl,
            'cover_image_responsive' => $coverImageResponsive,
            'chapters' => AudiobookChapterResource::collection($this->whenLoaded('audiobookChapters')),
            'year' => $this->year,
            'narrator' => $this->narrator,
            'duration' => $this->duration,
            'published_at' => DateTimeResource::make($this->published_at),
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
