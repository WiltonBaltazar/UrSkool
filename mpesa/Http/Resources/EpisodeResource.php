<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorageUrls;
use App\Services\ProtectedMediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EpisodeResource extends JsonResource
{
    use ResolvesStorageUrls;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        app(ProtectedMediaService::class)->ensureStoredOnPrivateDisk(
            $this->resource,
            'audio_file',
            ProtectedMediaService::PODCAST_EPISODE_AUDIO_DIRECTORY
        );

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'cover_image' => $this->storageUrl($this->cover_image),
            'cover_image_responsive' => $this->responsiveImage($this->cover_image),
            'audio_file' => route('api.media.episodes.audio', ['episode' => $this->id]),
            'description' => $this->description,
            'guest' => $this->guest,
            'release_date' => $this->release_date,
            'published_at' => DateTimeResource::make($this->published_at),
            'duration' => $this->duration,
            'created_at' => DateTimeResource::make($this->created_at),
        ];
    }

    /**
     * Get additional data that should be return with the resource array.
     */

    public function with(Request $request): array
    {
        return [
            'version' => '1.0',
            'api_url' => url('/api/v1'),
        ];
    }
}
