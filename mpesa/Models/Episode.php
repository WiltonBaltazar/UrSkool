<?php

namespace App\Models;

use App\Services\ContentPublicationNotifier;
use App\Support\AudioDuration;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Throwable;

class Episode extends Model
{
    use HasFactory;

    protected $fillable = [
        'podcast_id',
        'cover_image',
        'title',
        'slug',
        'status',
        'published_at',
        'description',
        'guest',
        'audio_file',
        'release_date',
        'duration',
    ];

    protected $casts = [
        'id' => 'integer',
        'release_date' => 'datetime',
        'podcast_id' => 'integer',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $episode): void {
            if ($episode->isDirty('audio_file') || blank($episode->duration)) {
                $calculatedDuration = AudioDuration::fromPublicDiskPath($episode->audio_file);

                if ($calculatedDuration !== null) {
                    $episode->duration = $calculatedDuration;
                }
            }

            if (blank($episode->duration)) {
                $episode->duration = '00:00:00';
            }

            if ($episode->status === 'published' && ! $episode->published_at) {
                $episode->published_at = now();
            }

            if ($episode->status === 'draft') {
                $episode->published_at = null;
            }
        });

        static::saved(function (self $episode): void {
            if (! $episode->wasChanged('audio_file') && $episode->duration !== '00:00:00') {
                return;
            }

            $calculatedDuration = AudioDuration::fromPublicDiskPath($episode->audio_file);

            if ($calculatedDuration === null || $calculatedDuration === $episode->duration) {
                return;
            }

            $episode->duration = $calculatedDuration;
            $episode->saveQuietly();
        });

        static::saved(function (self $episode): void {
            if (! $episode->shouldNotifyPublication()) {
                return;
            }

            try {
                app(ContentPublicationNotifier::class)->notifyEpisodePublished($episode);
            } catch (Throwable $exception) {
                Log::warning('Failed to dispatch episode publication notification.', [
                    'episode_id' => $episode->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
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

    public function podcast()
    {
        return $this->belongsTo(Podcast::class);
    }


    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'content');
    }

    public function consumptionEvents(): MorphMany
    {
        return $this->morphMany(ContentConsumptionEvent::class, 'content');
    }

    public function scopePublished(Builder $query): Builder
    {
        if (! Schema::hasColumn($this->getTable(), 'status')) {
            return $query;
        }

        return $query->where('status', 'published');
    }
}
