<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserProgress;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache; // Import Cache

class ProgressController extends Controller
{
    public function saveProgress(Request $request)
    {
        // 1. Validate
        $validated = $request->validate([
            'content_id' => 'required|integer',
            'content_type' => ['required', 'string', Rule::in(['ebook', 'audiobook', 'episode'])],
            'progress_point' => 'required',
            'current_chapter_index' => 'nullable|integer',
            'force_save' => 'boolean' // Add validation for flag
        ]);

        $userId = $request->user()->id;
        $modelMap = [
            'ebook' => \App\Models\Ebook::class,
            'audiobook' => \App\Models\Audiobook::class,
            'episode' => \App\Models\Episode::class,
        ];
        $modelClass = $modelMap[$validated['content_type']];

        // 2. Define Cache Keys
        // Key to throttle DB writes (stores "last saved time")
        $throttleKey = "progress_db_lock:{$userId}:{$modelClass}:{$validated['content_id']}";
        
        // Key to store ephemeral progress (fast access)
        $dataKey = "progress_data:{$userId}:{$modelClass}:{$validated['content_id']}";

        // 3. Always update the "Fast Cache" immediately
        // This ensures if getProgress is called, we can return this value even if DB is stale
        $currentData = [
            'progress_point' => (string)$validated['progress_point'],
            'current_chapter_index' => $validated['current_chapter_index'] ?? 0,
        ];
        Cache::put($dataKey, $currentData, now()->addHours(2));

        // 4. Decision: Should we write to the Hard Database?
        // Yes IF: 'force_save' is true (Paused) OR It's been >60s since last DB write
        $shouldPersist = $request->boolean('force_save') || !Cache::has($throttleKey);

        if ($shouldPersist) {
            UserProgress::updateOrCreate(
                [
                    'user_id' => $userId,
                    'content_id' => $validated['content_id'],
                    'content_type' => $modelClass,
                ],
                $currentData
            );

            // Set the throttle lock for 60 seconds
            Cache::put($throttleKey, true, 60);
            
            return response()->json(['status' => 'persisted']);
        }

        return response()->json(['status' => 'buffered']);
    }

    public function getProgress(Request $request)
    {
        // ... (Keep existing validation) ...
        $request->validate([
            'content_id' => 'required|integer',
            'content_type' => ['required', 'string', Rule::in(['ebook', 'audiobook', 'episode'])],
        ]);

        $modelMap = [
            'ebook' => \App\Models\Ebook::class,
            'audiobook' => \App\Models\Audiobook::class,
            'episode' => \App\Models\Episode::class,
        ];
        $modelClass = $modelMap[$request->content_type];

        // 1. Check Fast Cache FIRST
        $dataKey = "progress_data:{$request->user()->id}:{$modelClass}:{$request->content_id}";
        $cachedData = Cache::get($dataKey);

        if ($cachedData) {
            return response()->json(['success' => true, 'data' => $cachedData]);
        }

        // 2. Fallback to DB if cache is empty (e.g., cleared or first load)
        $progress = UserProgress::where('user_id', $request->user()->id)
            ->where('content_id', $request->content_id)
            ->where('content_type', $modelClass)
            ->first();

        return response()->json(['success' => true, 'data' => $progress]);
    }
}