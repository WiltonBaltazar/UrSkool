<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AudiobookSerie extends Model
{
     use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'cover_image',
        'description'
    ];


    //one CategoryPodcast has one or many episodes
    public function audiobooks()
    {
        return $this->hasMany(Audiobook::class);
    }
}
