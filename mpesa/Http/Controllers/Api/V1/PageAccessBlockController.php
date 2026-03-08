<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PageAccessBlock;
use App\Support\BlockableFrontendPathOptions;
use App\Support\PageAccessPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageAccessBlockController extends Controller
{
    public function active(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:2048'],
        ]);

        $normalizedPath = PageAccessPath::normalize($validated['path']);

        $block = $this->resolveActiveBlock($normalizedPath);

        if (! $block) {
            return response()->json([
                'blocked' => false,
                'path' => $normalizedPath,
            ]);
        }

        return response()->json([
            'blocked' => true,
            'path' => $normalizedPath,
            'data' => [
                'id' => $block->id,
                'headline' => $block->headline,
                'complementary_text' => $block->complementary_text,
                'blocked_path' => $block->blocked_path,
                'updated_at' => $block->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function unlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:2048'],
            'code' => ['required', 'string', 'max:255'],
        ]);

        $normalizedPath = PageAccessPath::normalize($validated['path']);

        $block = $this->resolveActiveBlock($normalizedPath);

        if (! $block) {
            return response()->json([
                'unlocked' => false,
                'message' => 'A pagina nao esta bloqueada.',
            ], 404);
        }

        if (! $block->verifyAccessCode($validated['code'])) {
            return response()->json([
                'unlocked' => false,
                'message' => 'Codigo invalido para esta pagina.',
            ], 422);
        }

        return response()->json([
            'unlocked' => true,
            'path' => $normalizedPath,
        ]);
    }

    protected function resolveLegacyBlock(string $normalizedPath): ?PageAccessBlock
    {
        $legacyLabel = BlockableFrontendPathOptions::labelForPath($normalizedPath);

        if (! $legacyLabel) {
            return null;
        }

        $legacyBlock = PageAccessBlock::query()
            ->whereIn('blocked_path', [$legacyLabel, "/{$legacyLabel}"])
            ->where('is_active', true)
            ->first();

        if (! $legacyBlock) {
            return null;
        }

        $legacyBlock->blocked_path = $normalizedPath;
        $legacyBlock->save();

        return $legacyBlock->refresh();
    }

    protected function resolveActiveBlock(string $normalizedPath): ?PageAccessBlock
    {
        // Priority: exact path block first.
        $exactBlock = PageAccessBlock::query()
            ->where('blocked_path', $normalizedPath)
            ->where('is_active', true)
            ->first();

        if (! $exactBlock) {
            $exactBlock = $this->resolveLegacyBlock($normalizedPath);
        }

        if ($exactBlock) {
            return $exactBlock;
        }

        // Fallback: root block ("/") applies globally to every route.
        $rootBlock = PageAccessBlock::query()
            ->where('blocked_path', '/')
            ->where('is_active', true)
            ->first();

        if (! $rootBlock) {
            $rootBlock = $this->resolveLegacyBlock('/');
        }

        return $rootBlock;
    }
}
