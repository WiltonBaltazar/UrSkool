<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Episode;
use App\Models\Podcast;
use App\Http\Controllers\Controller;
use App\Http\Resources\EpisodeResource;
use App\Http\Resources\PodcastResource;
use App\Http\Requests\StorePodcastRequest;
use App\Http\Requests\UpdatePodcastRequest;
use Illuminate\Http\Request;

class PodcastController extends Controller
{
    private function rumoresDaLendaQuery()
    {
        return Podcast::query()
            ->where('slug', 'rumores-da-lenda')
            ->orWhere('name', 'Rumores da Lenda');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $podcasts = Podcast::query()
            ->whereHas('episodes', fn ($query) => $query->published())
            ->withCount(['episodes' => fn ($query) => $query->published()])
            ->latest('id')
            ->get();

        return PodcastResource::collection($podcasts);
    }
    
    public function getEpisodesForSlider()
    {
        $podcast = $this->rumoresDaLendaQuery()
            ->with(['episodes' => function ($query) {
                $query->published()
                    ->latest('created_at')
                    ->offset(1)
                    ->limit(3);
            }])
            ->first();

        if (!$podcast) {
            return EpisodeResource::collection(collect());
        }

        return EpisodeResource::collection($podcast->episodes);
    }

    public function getLatestPodcastEpisode()
    {
        $podcast = $this->rumoresDaLendaQuery()
            ->with(['episodes' => function ($query) {
                $query->published()->latest('created_at')->limit(1);
            }])
            ->first();

        if (!$podcast) {
            return EpisodeResource::collection(collect());
        }

        return EpisodeResource::collection($podcast->episodes);
    }

    public function getEpisodesBySlug(Request $request, string $slug)
    {
        $podcast = Podcast::query()
            ->where('slug', $slug)
            ->firstOrFail();
        $podcast->loadCount(['episodes' => fn ($query) => $query->published()]);

        $episodes = Episode::query()
            ->where('podcast_id', $podcast->id)
            ->published()
            ->latest('created_at')
            ->paginate(15);

        return response()->json([
            'podcast' => PodcastResource::make($podcast),
            'episodes' => EpisodeResource::collection($episodes->items()),
            'meta' => [
                'current_page' => $episodes->currentPage(),
                'last_page' => $episodes->lastPage(),
                'per_page' => $episodes->perPage(),
                'total' => $episodes->total(),
            ],
        ]);
    }

    // public function getRumoresDaLenda(): JsonResponse
    // {
    //     // Find the category by name
    //     $podcast = Podcast::where('name', 'Rumores da Lenda')
    //         ->with(['episodes' => function ($query) {
    //             $query->orderBy('release_date', 'desc');
    //         }])
    //         ->firstOrFail();

    //     return response()->json([
    //         'podcast' => $podcast->name,
    //         'episodes' => EpisodeResource::collection($podcast->episodes)
    //     ]);
    // }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePodcastRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Podcast $categoryPodcast)
    {
        $categoryPodcast->loadMissing(['episodes' => fn ($query) => $query->published()]);
        return new PodcastResource($categoryPodcast);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePodcastRequest $request, Podcast $categoryPodcast)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Podcast $categoryPodcast)
    {
        //
    }
}
