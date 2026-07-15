<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentProgressCodeValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_code_lesson_does_not_accept_forged_completion_without_valid_submission(): void
    {
        [$user, $course, $lesson] = $this->createEnrolledCodeLesson([
            ['kind' => 'html_includes', 'value' => '<button'],
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/student/courses/{$course->id}/lessons/{$lesson->id}/progress", [
            'status' => 'completed',
            'codeIsCorrect' => true,
            'submittedCode' => [
                'html' => '<div>Sem botão</div>',
                'css' => '',
                'js' => '',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'lessonId' => (string) $lesson->id,
            'status' => 'in_progress',
            'codeIsCorrect' => false,
        ]);
    }

    public function test_code_lesson_marks_completed_when_server_rules_pass(): void
    {
        [$user, $course, $lesson] = $this->createEnrolledCodeLesson([
            ['kind' => 'selector_exists', 'value' => '#cta'],
            ['kind' => 'text_includes', 'value' => 'comprar'],
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/student/courses/{$course->id}/lessons/{$lesson->id}/progress", [
            'status' => 'completed',
            'codeIsCorrect' => true,
            'submittedCode' => [
                'html' => '<button id="cta">Comprar agora</button>',
                'css' => '',
                'js' => '',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'lessonId' => (string) $lesson->id,
            'status' => 'completed',
            'codeIsCorrect' => true,
        ]);
    }

    public function test_code_lesson_fails_closed_when_no_validation_rules_exist(): void
    {
        [$user, $course, $lesson] = $this->createEnrolledCodeLesson(null);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/student/courses/{$course->id}/lessons/{$lesson->id}/progress", [
            'status' => 'completed',
            'submittedCode' => [
                'html' => '<button>Comprar</button>',
                'css' => '',
                'js' => '',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'lessonId' => (string) $lesson->id,
            'status' => 'in_progress',
            'codeIsCorrect' => false,
        ]);
    }

    public function test_code_lesson_requires_meaningful_code_changes_before_completion(): void
    {
        [$user, $course, $lesson] = $this->createEnrolledCodeLesson(
            [
                ['kind' => 'html_includes', 'value' => '<button'],
            ],
            [
                'html_code' => '<button id="cta">Comprar agora</button>',
                'css_code' => '',
                'js_code' => '',
            ]
        );

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/student/courses/{$course->id}/lessons/{$lesson->id}/progress", [
            'status' => 'completed',
            'submittedCode' => [
                'html' => '<button id="cta">Comprar agora</button>',
                'css' => '',
                'js' => '',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'lessonId' => (string) $lesson->id,
            'status' => 'in_progress',
            'codeIsCorrect' => false,
        ]);
    }

    public function test_quiz_lesson_scores_answers_server_side(): void
    {
        $user = User::factory()->create();
        $course = $this->createCourse();
        $section = $this->createSection($course->id);

        $lesson = Lesson::query()->create([
            'section_id' => $section->id,
            'title' => 'Quiz 1',
            'duration' => '05:00',
            'type' => 'quiz',
            'quiz_pass_percentage' => 80,
            'quiz_questions' => [
                ['id' => 'q1', 'question' => 'Pergunta 1?', 'options' => ['A', 'B', 'C'], 'correctOptionIndex' => 0],
                ['id' => 'q2', 'question' => 'Pergunta 2?', 'options' => ['X', 'Y', 'Z'], 'correctOptionIndex' => 2],
            ],
            'sort_order' => 1,
        ]);

        Enrollment::query()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'amount' => 100,
            'status' => 'completed',
            'payment_reference' => 'REF-QUIZ-1',
        ]);

        Sanctum::actingAs($user);

        // Both answers correct → 100% → should pass (≥80%)
        $response = $this->putJson("/api/student/courses/{$course->id}/lessons/{$lesson->id}/progress", [
            'status' => 'completed',
            'quizAnswers' => ['q1' => 0, 'q2' => 2],
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'lessonId' => (string) $lesson->id,
            'status' => 'completed',
            'quizScore' => 100,
            'quizPassed' => true,
        ]);
    }

    public function test_quiz_lesson_client_cannot_forge_passing_score(): void
    {
        $user = User::factory()->create();
        $course = $this->createCourse();
        $section = $this->createSection($course->id);

        $lesson = Lesson::query()->create([
            'section_id' => $section->id,
            'title' => 'Quiz 2',
            'duration' => '05:00',
            'type' => 'quiz',
            'quiz_pass_percentage' => 80,
            'quiz_questions' => [
                ['id' => 'q1', 'question' => 'Pergunta 1?', 'options' => ['A', 'B', 'C'], 'correctOptionIndex' => 0],
                ['id' => 'q2', 'question' => 'Pergunta 2?', 'options' => ['X', 'Y', 'Z'], 'correctOptionIndex' => 2],
            ],
            'sort_order' => 1,
        ]);

        Enrollment::query()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'amount' => 100,
            'status' => 'completed',
            'payment_reference' => 'REF-QUIZ-2',
        ]);

        Sanctum::actingAs($user);

        // Wrong answers but client claims passed — server must reject
        $response = $this->putJson("/api/student/courses/{$course->id}/lessons/{$lesson->id}/progress", [
            'status' => 'completed',
            'quizAnswers' => ['q1' => 1, 'q2' => 0],
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'lessonId' => (string) $lesson->id,
            'status' => 'in_progress',
            'quizScore' => 0,
            'quizPassed' => false,
        ]);
    }

    private function createEnrolledCodeLesson(?array $validationRules, array $lessonOverrides = []): array
    {
        $user = User::factory()->create();
        $course = $this->createCourse();
        $section = $this->createSection($course->id);

        $lesson = Lesson::query()->create(array_merge([
            'section_id' => $section->id,
            'title' => 'Prática 1',
            'duration' => '08:00',
            'type' => 'code',
            'validation_rules' => $validationRules,
            'sort_order' => 1,
        ], $lessonOverrides));

        Enrollment::query()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'amount' => 100,
            'status' => 'completed',
            'payment_reference' => 'REF-CODE-1',
        ]);

        return [$user, $course, $lesson];
    }

    private function createCourse(): Course
    {
        return Course::query()->create([
            'title' => 'Curso de Teste',
            'subtitle' => 'Subtítulo',
            'instructor' => 'Instrutor Teste',
            'rating' => 4.8,
            'review_count' => 12,
            'student_count' => 1,
            'price' => 100,
            'original_price' => 150,
            'image' => 'https://example.com/course-test.jpg',
            'category' => 'Desenvolvimento Web',
            'level' => 'Iniciante',
            'total_hours' => 5,
            'total_lessons' => 1,
            'description' => 'Curso para validar progressos.',
        ]);
    }

    private function createSection(int $courseId): Section
    {
        return Section::query()->create([
            'course_id' => $courseId,
            'title' => 'Secção 1',
            'sort_order' => 1,
        ]);
    }
}
