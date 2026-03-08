<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAudiobookRequest;
use App\Http\Requests\UpdateAudiobookRequest;
use App\Http\Resources\AudiobookResource;
use App\Http\Resources\AudiobookSerieResource;
use App\Models\Audiobook;
use App\Models\AudiobookSerie;
class AudiobookController extends Controller
{
    public function getAllSeries()
    {
        return AudiobookSerieResource::collection(
            AudiobookSerie::query()->latest()->get()
        );
    }

    public function index()
    {
        return AudiobookResource::collection(
            Audiobook::query()
                ->with('audiobookChapters')
                ->published()
                ->latest()
                ->get()
        );
    }
    // getLatestAudiobook

    public function getLatestAudiobook()
    {
        // get latest audiobook limit by 3 order by date desc and return as a collection
        return AudiobookResource::collection(
            Audiobook::query()
                ->with('audiobookChapters')
                ->published()
                ->latest()
                ->limit(1)
                ->get()
        );
    }

    // display audiobook by slug a show it's chapters and other details
    public function showBySlug($slug)
    {
        $audiobook = Audiobook::query()
            ->with('audiobookChapters')
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return (new AudiobookResource($audiobook));
    }

    public function show(Audiobook $audiobook)
    {
        abort_unless($audiobook->status === 'published', 404);

        return new AudiobookResource(
            $audiobook->loadMissing('audiobookChapters')
        );
    }

    /**
     * Get audiobook series by slug
     */
    public function getSeriesBySlug(string $slug)
    {
        $serie = AudiobookSerie::where('slug', $slug)->firstOrFail();

        return (new AudiobookSerieResource($serie));
    }

    /**
     * Get audiobooks by series slug. Guests receive only free audiobooks.
     */
    public function getBooksBySeriesSlug(string $slug)
    {
        $serie = AudiobookSerie::where('slug', $slug)->firstOrFail();
        $audiobooks = Audiobook::query()
            ->with('audiobookChapters')
            ->where('audiobook_serie_id', $serie->id)
            ->published()
            ->latest()
            ->get();

        return response()->json([
            'serie' => AudiobookSerieResource::make($serie),
            'audiobooks' => AudiobookResource::collection($audiobooks)
        ]);
    }
}
