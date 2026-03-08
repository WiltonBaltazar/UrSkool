<?php

namespace App\Models;

use App\Services\ContentPublicationNotifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Throwable;

class Newsletter extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'body',
        'slug',
        'image_cover',
        'author',
    ];

    protected static function booted(): void
    {
        static::created(function (self $newsletter): void {
            try {
                app(ContentPublicationNotifier::class)->notifyNewsletterPublished($newsletter);
            } catch (Throwable $exception) {
                Log::warning('Failed to dispatch newsletter publication notification.', [
                    'newsletter_id' => $newsletter->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }
    
}
