<?php

namespace App\Models;

use App\Services\ContentPublicationNotifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Throwable;

class Ebook extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ebook_serie_id',
        'title',
        'slug',
        'status',
        'published_at',
        'cover_image',
        'chapters',
        'file',
        'year',
        'ebook_serie_id',
        'description',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'cover_image' => 'string',
        'description' => 'string',
        'chapters' => 'integer',
        'year' => 'datetime',
        'ebook_serie_id' => 'integer',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $ebook): void {
            if ($ebook->status === 'published' && ! $ebook->published_at) {
                $ebook->published_at = now();
            }

            if ($ebook->status === 'draft') {
                $ebook->published_at = null;
            }
        });

        static::saved(function (self $ebook): void {
            if (! $ebook->shouldNotifyPublication()) {
                return;
            }

            try {
                app(ContentPublicationNotifier::class)->notifyEbookPublished($ebook);
            } catch (Throwable $exception) {
                Log::warning('Failed to dispatch ebook publication notification.', [
                    'ebook_id' => $ebook->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    //one ebook belongs to a serie
    public function ebookSerie()
    {
        return $this->belongsTo(EbookSerie::class);
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
