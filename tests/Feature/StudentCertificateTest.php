<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentCertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_requires_full_course_completion(): void
    {
        [$user, $course, $lessons] = $this->createEnrolledCourseWithLessons(2);
        LessonProgress::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lessons[0]->id,
            'status' => 'completed',
            'code_is_correct' => false,
            'quiz_passed' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/student/courses/{$course->id}/certificate");
        $response->assertStatus(422);
    }

    public function test_completed_course_can_issue_and_reuse_certificate(): void
    {
        [$user, $course, $lessons] = $this->createEnrolledCourseWithLessons(2);

        foreach ($lessons as $lesson) {
            LessonProgress::query()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'lesson_id' => $lesson->id,
                'status' => 'completed',
                'code_is_correct' => false,
                'quiz_passed' => false,
            ]);
        }

        Sanctum::actingAs($user);

        $first = $this->getJson("/api/student/courses/{$course->id}/certificate");
        $first->assertOk();
        $first->assertJsonPath('data.courseTitle', 'Curso de Certificado');
        $first->assertJsonPath('data.recipientName', $user->name);
        $firstCode = (string) $first->json('data.shareCode');

        $second = $this->getJson("/api/student/courses/{$course->id}/certificate");
        $second->assertOk();
        $second->assertJsonPath('data.shareCode', $firstCode);

        $this->assertDatabaseCount('course_certificates', 1);
    }

    public function test_public_certificate_endpoint_returns_certificate_data(): void
    {
        [$user, $course, $lessons] = $this->createEnrolledCourseWithLessons(1);
        LessonProgress::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lessons[0]->id,
            'status' => 'completed',
            'code_is_correct' => false,
            'quiz_passed' => false,
        ]);

        Sanctum::actingAs($user);
        $issued = $this->getJson("/api/student/courses/{$course->id}/certificate");
        $shareCode = (string) $issued->json('data.shareCode');

        $public = $this->getJson("/api/certificates/{$shareCode}");
        $public->assertOk();
        $public->assertJsonPath('data.courseTitle', 'Curso de Certificado');
        $public->assertJsonPath('data.recipientName', $user->name);
        $public->assertJsonPath('data.shareCode', $shareCode);
    }

    private function createEnrolledCourseWithLessons(int $lessonCount): array
    {
        $user = User::factory()->create();

        $course = Course::query()->create([
            'title' => 'Curso de Certificado',
            'subtitle' => 'Subtítulo',
            'instructor' => 'Instrutor',
            'rating' => 4.8,
            'review_count' => 10,
            'student_count' => 1,
            'price' => 100,
            'original_price' => 150,
            'image' => 'https://example.com/course.jpg',
            'category' => 'Desenvolvimento Web',
            'level' => 'Iniciante',
            'total_hours' => 5,
            'total_lessons' => $lessonCount,
            'description' => 'Descrição',
        ]);

        $section = Section::query()->create([
            'course_id' => $course->id,
            'title' => 'Secção 1',
            'sort_order' => 1,
        ]);

        $lessons = [];
        for ($index = 1; $index <= $lessonCount; $index += 1) {
            $lessons[] = Lesson::query()->create([
                'section_id' => $section->id,
                'title' => "Lição {$index}",
                'duration' => '05:00',
                'type' => 'text',
                'sort_order' => $index,
            ]);
        }

        Enrollment::query()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'amount' => 100,
            'status' => 'completed',
            'payment_reference' => 'REF-CERT-1',
        ]);

        return [$user, $course, $lessons];
    }
}

