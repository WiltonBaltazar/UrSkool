<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Page;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;

class PageController extends Controller
{
    //display terms-and-conditions page content using resource api
    public function termsAndConditions()
    {
        $page = Page::where('slug', 'termos-e-condicoes')->first();
        return new PageResource($page);
    }

    //display privacy-policy page content using resource api
    public function privacyPolicy()
    {
        $page = Page::where('slug', 'politicas-de-privacidade')->first();
        return new PageResource($page);
    }

    // display sobre-nos page content using resource api
    public function aboutUs()
    {
        $page = Page::where('slug', 'sobre-a-lenda')->first();
        return new PageResource($page);
    }
}
