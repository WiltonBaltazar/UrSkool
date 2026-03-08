<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewsletterRequest;
use App\Http\Requests\UpdateNewsletterRequest;
use App\Http\Resources\NewsletterResource;
use App\Models\Newsletter;

class NewsletterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return NewsletterResource::collection(
            Newsletter::query()
                ->latest('id')
                ->paginate(15)
        );
    }

    //get the latest newsletter
    public function getLatestNewsletter()
    {
        $newsletter = Newsletter::latest()->first();
        return new NewsletterResource($newsletter);
    }

    //get newsletter by slug
    public function showBySlug($slug)
    {
        $newsletter = Newsletter::where('slug', $slug)->firstOrFail();
        return (new NewsletterResource($newsletter));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreNewsletterRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Newsletter $newsletter)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateNewsletterRequest $request, Newsletter $newsletter)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Newsletter $newsletter)
    {
        //
    }
}
