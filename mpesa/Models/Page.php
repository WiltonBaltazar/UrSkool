<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'title',
        'slug', 
        'featured_image',
        'content',
    ];

    protected $casts = [
        'id' => 'integer',
    ];
}
