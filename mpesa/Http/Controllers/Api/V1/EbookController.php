<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\EbookSeriesResource;
use App\Models\Ebook;
use App\Models\EbookSerie;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\EbookResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreEbookRequest;
use App\Http\Requests\UpdateEbookRequest;

class EbookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return EbookResource::collection(Ebook::published()->paginate());
    }

    public function getLatestEbook()
    {
        $ebooks = Ebook::latest()
            ->published()
            ->limit(4)
            ->get();

        return EbookResource::collection($ebooks);
    }

    //Display all ebooks
    public function getAllEbooks()
    {
        $ebooks = Ebook::published()->get();

        return EbookResource::collection($ebooks);
    }

    public function showBySlug($slug)
    {
        $ebook = Ebook::published()->where('slug', $slug)->firstOrFail();

        return new EbookResource($ebook);
    }


    public function getAllSeries()
    {
        // Fetch all series that actually have ebooks associated with them
        // Assuming you have a 'Series' model and an 'ebooks' relationship
        $series = EbookSerie::withCount('ebooks')
            ->having('ebooks_count', '>', 0) // Optional: Hide empty series
            ->get();

        return EbookSeriesResource::collection($series);
    }

    public function showDetailsBySlug($slug)
    {

        $ebook = Ebook::published()->where('slug', $slug)->firstOrFail();

        // \Log::info('Ebook serie_id: ' . $ebook->ebook_serie_id);

        $otherBooks = collect();

        if ($ebook->ebook_serie_id) {
            $otherBooks = Ebook::where('id', '!=', $ebook->id)
                ->published()
                ->where('ebook_serie_id', $ebook->ebook_serie_id)
                ->inRandomOrder()
                ->limit(4)
                ->get();

            // \Log::info('Other books count: ' . $otherBooks->count());
        }

        return response()->json([
            'ebook' => new EbookResource($ebook),
            'otherBooks' => EbookResource::collection($otherBooks),
        ]);
    }

    /**
     * Get ebooks by series slug. Guests receive only free ebooks.
     */
    public function getBySeriesSlug(string $slug)
    {
        $serie = EbookSerie::where('slug', $slug)->firstOrFail();


        $query = Ebook::where('ebook_serie_id', $serie->id)
            ->published()
            ->latest();

        $ebooks = $query->get();
        return response()->json([
            'serie' => EbookSeriesResource::make($serie),
            'ebooks' =>  EbookResource::collection($ebooks)
        ]);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEbookRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Ebook $ebook)
    {
        abort_unless($ebook->status === 'published', 404);
        return new EbookResource($ebook);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEbookRequest $request, Ebook $ebook)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ebook $ebook)
    {
        //
    }
}
