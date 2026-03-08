<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserProgress extends Model
{
    protected $fillable = [
        'user_id', 'content_id', 'content_type',
        'progress_point', 'current_chapter_index', 'is_completed'
    ];

    /**
     * Get the parent content model (Ebook, Audiobook, or Episode).
     */
    public function content(): MorphTo
    {
        return $this->morphTo();
    }
}