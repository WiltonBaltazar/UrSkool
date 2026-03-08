<?php

namespace App\Models;

use App\Services\ContentPublicationNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Throwable;

class Audiobook extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'audiobook_serie_id',
        'title',
        'slug',
        'status',
        'published_at',
        'description',
        'cover_image',
        'year',
        'narrator',
        'duration',
    ];


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
      protected $casts = [
        'id' => 'integer',
        'audiobook_serie_id' => 'integer',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $audiobook): void {
            if ($audiobook->status === 'published' && ! $audiobook->published_at) {
                $audiobook->published_at = now();
            }

            if ($audiobook->status === 'draft') {
                $audiobook->published_at = null;
            }
        });

        static::saved(function (self $audiobook): void {
            if (! $audiobook->shouldNotifyPublication()) {
                return;
            }

            try {
                app(ContentPublicationNotifier::class)->notifyAudiobookPublished($audiobook);
            } catch (Throwable $exception) {
                Log::warning('Failed to dispatch audiobook publication notification.', [
                    'audiobook_id' => $audiobook->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }
    
    public function audiobookChapters(): HasMany
    {
        return $this->hasMany(AudiobookChapter::class);
    }

    public function audiobookSerie()
    {
        return $this->belongsTo(AudiobookSerie::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        if (! Schema::hasColumn($this->getTable(), 'status')) {
            return $query;
        }

        return $query->where('status', 'published');
    }

    public function shouldNotifyPublication(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        if ($this->wasRecentlyCreated) {
            return true;
        }

        if ($this->wasChanged('status')) {
            return true;
        }

        return $this->wasChanged('published_at') && $this->getOriginal('published_at') === null;
    }

    public function consumptionEvents(): MorphMany
    {
        return $this->morphMany(ContentConsumptionEvent::class, 'content');
    }
}
