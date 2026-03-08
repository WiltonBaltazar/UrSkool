<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorageUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PodcastResource extends JsonResource
{
    use ResolvesStorageUrls;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return 
        [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,  
            'cover_image' => $this->storageUrl($this->cover_image),
            'cover_image_responsive' => $this->responsiveImage($this->cover_image),
            'description' => $this->description,
            'episodes_count' => $this->whenCounted('episodes'),
            'episodes' => EpisodeResource::collection($this->whenLoaded('episodes')),
            'created_at' => DateTimeResource::make($this->created_at),
            'updated_at' => $this->updated_at
        ];
    }
}
