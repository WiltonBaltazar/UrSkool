<?php

namespace App\Http\Resources;

use App\Services\ProtectedMediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AudiobookSerieResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $protectedMedia = app(ProtectedMediaService::class);

        $protectedMedia->ensureStoredOnPrivateDisk(
            $this->resource,
            'cover_image',
            ProtectedMediaService::AUDIOBOOK_SERIE_COVER_DIRECTORY
        );

        $coverRoute = route('api.media.audiobooks.series.cover', ['serie' => $this->id]);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'cover_image' => $coverRoute,
            'cover_image_responsive' => $protectedMedia->responsivePayloadForRoute(
                $this->cover_image,
                'api.media.audiobooks.series.cover',
                ['serie' => $this->id]
            ),
            'description' => $this->description,
            'created_at' => DateTimeResource::make($this->created_at),
            'updated_at' => $this->updated_at,
        ];
    }
}
