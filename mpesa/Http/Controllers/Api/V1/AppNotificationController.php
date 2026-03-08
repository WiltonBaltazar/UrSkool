<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppNotificationResource;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $signupCutoff = $request->user()->created_at;

        $perPage = $request->integer(
            'per_page',
            $request->integer('limit', 20)
        );
        $perPage = max(1, min($perPage, 100));

        $notifications = AppNotification::query()
            ->where(function ($query) use ($signupCutoff) {
                $query->where('published_at', '>=', $signupCutoff)
                    ->orWhere(function ($query) use ($signupCutoff) {
                        $query->whereNull('published_at')
                            ->where('created_at', '>=', $signupCutoff);
                    });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AppNotificationResource::collection($notifications->items()),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }
}
