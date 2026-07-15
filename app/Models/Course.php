<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'subtitle',
        'instructor',
        'rating',
        'review_count',
        'student_count',
        'price',
        'original_price',
        'image',
        'category',
        'level',
        'total_hours',
        'total_lessons',
        'description',
    ];

    protected $casts = [
        'rating' => 'float',
        'price' => 'float',
        'original_price' => 'float',
        'review_count' => 'integer',
        'student_count' => 'integer',
        'total_hours' => 'integer',
        'total_lessons' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Course $course): void {
            if ($course->isForceDeleting()) {
                return;
            }
            $course->loadMissing('sections.lessons');
            $course->sections->each(function (Section $section): void {
                $section->lessons()->delete();
                $section->delete();
            });
        });
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('sort_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(CourseCertificate::class);
    }
}
