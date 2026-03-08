<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'title',
        'duration',
        'video_url',
        'is_free',
        'type',
        'language',
        'content',
        'starter_code',
        'html_code',
        'css_code',
        'js_code',
        'quiz_questions',
        'quiz_pass_percentage',
        'quiz_randomize_questions',
        'sort_order',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'sort_order' => 'integer',
        'quiz_questions' => 'array',
        'quiz_pass_percentage' => 'integer',
        'quiz_randomize_questions' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
