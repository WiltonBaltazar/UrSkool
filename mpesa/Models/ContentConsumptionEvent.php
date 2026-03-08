<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentConsumptionEvent extends Model
{
    public const EVENT_VIEW = 'view';
    public const EVENT_PLAY = 'play';

    protected $fillable = [
        'user_id',
        'content_id',
        'content_type',
        'event_type',
        'source',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function content(): MorphTo
    {
        return $this->morphTo();
    }
}

