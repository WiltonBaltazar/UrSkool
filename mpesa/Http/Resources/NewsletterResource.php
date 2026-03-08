<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorageUrls;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsletterResource extends JsonResource
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
            'id'=> $this->id,
            'title'=> $this->title,
            'slug'=> $this->slug,
            'body'=> HtmlSanitizer::sanitize($this->body),
            'image_cover'=> $this->storageUrl($this->image_cover),
            'image_cover_responsive'=> $this->responsiveImage($this->image_cover),
            'author'=> $this->author,
        ];
    }
}
