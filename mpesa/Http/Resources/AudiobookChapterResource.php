<?php

namespace App\Http\Resources;

use App\Services\ProtectedMediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AudiobookChapterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canAccessPremiumMedia = $user && $user->hasActiveSubscription() && $user->hasPlan('premium');
        $audioUrl = null;

        if ($canAccessPremiumMedia) {
            app(ProtectedMediaService::class)->ensureStoredOnPrivateDisk(
                $this->resource,
                'audio_file',
                ProtectedMediaService::AUDIOBOOK_CHAPTER_DIRECTORY
            );

            $audioUrl = route('api.media.audiobook-chapters.audio', ['chapter' => $this->id]);
        }

        return [
            'audiobook_id' => $this->audiobook_id,
            'title' => $this->title,
            'audio_file' => $audioUrl,
        ];
    }
}
