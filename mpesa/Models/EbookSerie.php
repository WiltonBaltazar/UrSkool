<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EbookSerie extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'cover_image',
        'description'
    ];


    // One series has many ebooks
    public function ebooks()
    {
        return $this->hasMany(Ebook::class, 'ebook_serie_id');
    }
}
