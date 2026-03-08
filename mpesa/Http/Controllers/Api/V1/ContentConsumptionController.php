<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Audiobook;
use App\Models\ContentConsumptionEvent;
use App\Models\Ebook;
use App\Models\Episode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ContentConsumptionController extends Controller
{
    /**
     * @var array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    private const CONTENT_MODEL_MAP = [
        'ebook' => Ebook::class,
        'audiobook' => Audiobook::class,
        'episode' => Episode::class,
    ];

    public function track(Request $request)
    {
        $validated = $request->validate([
            'content_id' => ['required', 'integer', 'min:1'],
            'content_type' => ['required', 'string', Rule::in(array_keys(self::CONTENT_MODEL_MAP))],
            'event_type' => ['required', 'string', Rule::in([ContentConsumptionEvent::EVENT_VIEW, ContentConsumptionEvent::EVENT_PLAY])],
            'source' => ['nullable', 'string', 'max:32'],
        ]);

        $modelClass = self::CONTENT_MODEL_MAP[$validated['content_type']];
        $contentId = (int) $validated['content_id'];
        $eventType = $validated['event_type'];
        $source = $validated['source'] ?? 'web';

        if (! $modelClass::query()->whereKey($contentId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Content not found for the provided type and id.',
            ], 404);
        }

        $userId = (string) $request->user()?->id;
        $dedupeWindowSeconds = $eventType === ContentConsumptionEvent::EVENT_VIEW ? 60 : 30;

        $dedupeKey = implode(':', [
            'content-consumption',
            $userId ?: 'guest',
            $modelClass,
            $contentId,
            $eventType,
            $source,
        ]);

        if (! Cache::add($dedupeKey, true, now()->addSeconds($dedupeWindowSeconds))) {
            return response()->json([
                'success' => true,
                'tracked' => false,
                'message' => 'Event ignored due to dedupe window.',
            ]);
        }

        ContentConsumptionEvent::query()->create([
            'user_id' => $userId ?: null,
            'content_id' => $contentId,
            'content_type' => $modelClass,
            'event_type' => $eventType,
            'source' => $source,
        ]);

        return response()->json([
            'success' => true,
            'tracked' => true,
        ], 201);
    }
}

