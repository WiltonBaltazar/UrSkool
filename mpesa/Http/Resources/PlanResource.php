<?php

namespace App\Http\Resources;

use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'promo_price' => $this->promo_price,
            'effective_price' => $this->effective_price,
            'duration_days' => $this->duration_days,
            'formatted_price' => $this->formatted_price,
            'formatted_promo_price' => $this->formatted_promo_price,
            'formatted_effective_price' => $this->formatted_effective_price,
            'slug' => $this->slug,
            'bg_color' => $this->bg_color,
            'text_color' => $this->text_color,
            'description' => HtmlSanitizer::sanitize($this->description),
        ];
    }
}
