<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentCoursesNPlusOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_courses_uses_a_single_lesson_progress_query_and_returns_expected_progress(): void
    {
        $user = User::factory()->create();

        $courseA = Course::query()->create([
            'title' => 'Curso A',
            'subtitle' => 'Sub A',
            'instructor' => 'Instrutor A',
            'rating' => 4.9,
            'review_count' => 10,
            'student_count' => 1,
            'price' => 100,
            'original_price' => 150,
            'image' => 'https://example.com/course-a.jpg',
            'category' => 'Desenvolvimento Web',
            'level' => 'Iniciante',
            'total_hours' => 4,
            'total_lessons' => 2,
            'description' => 'Descrição A',
        ]);

        $courseB = Course::query()->create([
            'title' => 'Curso B',
            'subtitle' => 'Sub B',
            'instructor' => 'Instrutor B',
            'rating' => 4.5,
            'review_count' => 8,
            'student_count' => 1,
            'price' => 200,
            'original_price' => 250,
            'image' => 'https://example.com/course-b.jpg',
            'category' => 'JavaScript',
            'level' => 'Intermediário',
            'total_hours' => 6,
            'total_lessons' => 2,
            'description' => 'Descrição B',
        ]);

        $sectionA = Section::query()->create([
            'course_id' => $courseA->id,
            'title' => 'Secção A',
            'sort_order' => 1,
        ]);

        $sectionB = Section::query()->create([
            'course_id' => $courseB->id,
            'title' => 'Secção B',
            'sort_order' => 1,
        ]);

        $lessonA1 = Lesson::query()->create([
            'section_id' => $sectionA->id,
            'title' => 'Lição A1',
            'duration' => '10m',
            'is_free' => false,
            'type' => 'video',
            'sort_order' => 1,
        ]);

        $lessonA2 = Lesson::query()->create([
            'section_id' => $sectionA->id,
            'title' => 'Lição A2',
            'duration' => '12m',
            'is_free' => false,
            'type' => 'video',
            'sort_order' => 2,
        ]);

        $lessonB1 = Lesson::query()->create([
            'section_id' => $sectionB->id,
            'title' => 'Lição B1',
            'duration' => '9m',
            'is_free' => false,
            'type' => 'video',
            'sort_order' => 1,
        ]);

        $lessonB2 = Lesson::query()->create([
            'section_id' => $sectionB->id,
            'title' => 'Lição B2',
            'duration' => '11m',
            'is_free' => false,
            'type' => 'video',
            'sort_order' => 2,
        ]);

        Enrollment::query()->create([
            'course_id' => $courseA->id,
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'amount' => 100,
            'status' => 'completed',
            'payment_reference' => 'REF-A',
        ]);

        Enrollment::query()->create([
            'course_id' => $courseB->id,
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'amount' => 200,
            'status' => 'completed',
            'payment_reference' => 'REF-B',
        ]);

        LessonProgress::query()->create([
            'user_id' => $user->id,
            'course_id' => $courseA->id,
            'lesson_id' => $lessonA1->id,
            'status' => 'completed',
            'code_is_correct' => false,
            'quiz_score' => null,
            'quiz_passed' => false,
            'completed_at' => now(),
        ]);

        LessonProgress::query()->create([
            'user_id' => $user->id,
            'course_id' => $courseB->id,
            'lesson_id' => $lessonB1->id,
            'status' => 'completed',
            'code_is_correct' => false,
            'quiz_score' => null,
            'quiz_passed' => false,
            'completed_at' => now(),
        ]);

        Sanctum::actingAs($user);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/student/courses');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $coursesById = collect($response->json('data'))->keyBy('id');

        $courseAData = $coursesById->get((string) $courseA->id);
        $this->assertNotNull($courseAData);
        $this->assertSame(1, $courseAData['progress']['completedLessons']);
        $this->assertSame(2, $courseAData['progress']['totalLessons']);
        $this->assertEquals(50.0, $courseAData['progress']['completionPercent']);
        $this->assertSame((string) $lessonA2->id, $courseAData['resumeLessonId']);

        $courseBData = $coursesById->get((string) $courseB->id);
        $this->assertNotNull($courseBData);
        $this->assertSame(1, $courseBData['progress']['completedLessons']);
        $this->assertSame(2, $courseBData['progress']['totalLessons']);
        $this->assertEquals(50.0, $courseBData['progress']['completionPercent']);
        $this->assertSame((string) $lessonB2->id, $courseBData['resumeLessonId']);

        $lessonProgressQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains(strtolower((string) ($query['query'] ?? '')), 'lesson_progress'))
            ->values();

        $this->assertCount(1, $lessonProgressQueries);

        DB::disableQueryLog();
    }
}
