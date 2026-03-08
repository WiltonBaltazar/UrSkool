<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\EpisodeCollection;
use App\Models\Episode;
use App\Http\Controllers\Controller;
use App\Http\Resources\EpisodeResource;
use App\Http\Requests\StoreEpisodeRequest;
use App\Http\Requests\UpdateEpisodeRequest;
use Illuminate\Http\Request;

class EpisodeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
       
        try {
            $episodes = Episode::published()
                ->latest()
                ->paginate(15);
            
            return new EpisodeCollection($episodes);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse(
                'EPISODE_LIST_FETCH_FAILED',
                'Failed to fetch episodes',
                config('app.debug') ? ['exception' => $e->getMessage()] : null,
                500
            );
        }
    }

    public function showBySlug($slug)
    {
        // Buscar o episódio principal pelo slug
        $episode = Episode::query()
            ->with('podcast')
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();
        $podcast_name = $episode->podcast->name ?? 'Unknown Podcast';

        // Buscar outros 3 episódios aleatórios, excluindo o episódio atual
        $otherEpisodes = Episode::published()
            ->where('id', '!=', $episode->id)
            ->inRandomOrder()
            ->take(3)
            ->get();

        return response()->json([
            'success' => true,
            'episode' => new EpisodeResource($episode),
            'otherEpisodes' =>  EpisodeResource::collection($otherEpisodes),
            'podcast_name' => $podcast_name,
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEpisodeRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Episode $episode)
    {
        abort_unless($episode->status === 'published', 404);
        return new EpisodeResource($episode);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEpisodeRequest $request, Episode $episode)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Episode $episode)
    {
        //
    }

    /**
     * Search episodes
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
            'limit' => 'integer|min:1|max:20',
        ]);

        $query = $request->get('q');
        $limit = $request->get('limit', 10);

        $episodes = Episode::orderBy('release_date', 'desc')
            ->published()
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        return EpisodeResource::collection($episodes)->additional([
            'success' => true,
            'message' => "Search results for: {$query}",
            'meta' => [
                'query' => $query,
                'total_results' => $episodes->count(),
                'limit' => $limit,
            ]
        ]);
    }
}
