<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorageUrls;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    use ResolvesStorageUrls;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'featured_image' => $this->storageUrl($this->featured_image),
            'featured_image_responsive' => $this->responsiveImage($this->featured_image),
            'content' => HtmlSanitizer::sanitize($this->content),
        ];
    }
}
