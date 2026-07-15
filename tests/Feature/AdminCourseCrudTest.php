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

class AdminCourseCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function makeCoursePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Curso de HTML',
            'subtitle' => 'Aprende HTML do zero',
            'instructor' => 'Instrutor Silva',
            'rating' => 4.5,
            'reviewCount' => 10,
            'studentCount' => 50,
            'price' => 199,
            'originalPrice' => 299,
            'image' => 'https://example.com/course.jpg',
            'category' => 'Desenvolvimento Web',
            'level' => 'Iniciante',
            'totalHours' => 8,
            'description' => 'Curso completo de HTML.',
            'sections' => [],
        ], $overrides);
    }

    public function test_admin_can_list_courses(): void
    {
        $this->makeAdmin();
        Course::query()->create($this->makeCoursePayload() + [
            'review_count' => 10,
            'student_count' => 50,
            'original_price' => 299,
            'total_hours' => 8,
            'total_lessons' => 0,
        ]);

        $response = $this->getJson('/api/admin/courses');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [['id', 'title', 'instructor', 'category', 'level', 'price', 'studentCount']],
        ]);
    }

    public function test_admin_can_create_course(): void
    {
        $this->makeAdmin();
        $payload = $this->makeCoursePayload([
            'sections' => [
                [
                    'title' => 'Módulo 1',
                    'lessons' => [
                        [
                            'title' => 'Lição 1',
                            'duration' => '05:00',
                            'type' => 'video',
                            'videoUrl' => 'https://youtube.com/watch?v=abc',
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson('/api/admin/courses', $payload);

        $response->assertCreated();
        $response->assertJsonFragment(['title' => 'Curso de HTML']);
        $this->assertDatabaseHas('courses', ['title' => 'Curso de HTML']);
        $this->assertDatabaseHas('sections', ['title' => 'Módulo 1']);
        $this->assertDatabaseHas('lessons', ['title' => 'Lição 1']);
    }

    public function test_admin_can_update_course(): void
    {
        $this->makeAdmin();
        $course = Course::query()->create([
            'title' => 'Curso Antigo',
            'subtitle' => 'Sub',
            'instructor' => 'Instrutor',
            'rating' => 4.0,
            'review_count' => 0,
            'student_count' => 0,
            'price' => 100,
            'original_price' => 150,
            'image' => 'https://example.com/old.jpg',
            'category' => 'Desenvolvimento Web',
            'level' => 'Iniciante',
            'total_hours' => 2,
            'total_lessons' => 0,
            'description' => 'Desc antiga',
        ]);

        $payload = $this->makeCoursePayload(['title' => 'Curso Atualizado']);

        $response = $this->putJson("/api/admin/courses/{$course->id}", $payload);

        $response->assertOk();
        $response->assertJsonFragment(['title' => 'Curso Atualizado']);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => 'Curso Atualizado']);
    }

    public function test_admin_can_soft_delete_course(): void
    {
        $this->makeAdmin();
        $course = Course::query()->create([
            'title' => 'Curso Para Eliminar',
            'subtitle' => 'Sub',
            'instructor' => 'Instrutor',
            'rating' => 4.0,
            'review_count' => 0,
            'student_count' => 0,
            'price' => 100,
            'original_price' => 150,
            'image' => 'https://example.com/del.jpg',
            'category' => 'Desenvolvimento Web',
            'level' => 'Iniciante',
            'total_hours' => 2,
            'total_lessons' => 0,
            'description' => 'Desc',
        ]);

        $response = $this->deleteJson("/api/admin/courses/{$course->id}");

        $response->assertOk();
        // Soft deleted: should not appear in normal queries
        $this->assertSoftDeleted('courses', ['id' => $course->id]);
        // But record still exists in DB
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_soft_delete_cascades_to_sections_and_lessons(): void
    {
        $this->makeAdmin();

        $course = Course::query()->create([
            'title' => 'Curso Com Lições',
            'subtitle' => 'Sub',
            'instructor' => 'Instrutor',
            'rating' => 4.0,
            'review_count' => 0,
            'student_count' => 0,
            'price' => 100,
            'original_price' => 150,
            'image' => 'https://example.com/c.jpg',
            'category' => 'Desenvolvimento Web',
            'level' => 'Iniciante',
            'total_hours' => 2,
            'total_lessons' => 1,
            'description' => 'Desc',
        ]);
        $section = Section::query()->create([
            'course_id' => $course->id,
            'title' => 'Secção',
            'sort_order' => 1,
        ]);
        $lesson = Lesson::query()->create([
            'section_id' => $section->id,
            'title' => 'Lição',
            'duration' => '05:00',
            'type' => 'video',
            'sort_order' => 1,
        ]);

        $course->delete();

        $this->assertSoftDeleted('courses', ['id' => $course->id]);
        $this->assertSoftDeleted('sections', ['id' => $section->id]);
        $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
    }

    public function test_non_admin_cannot_access_admin_course_endpoints(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/courses')->assertForbidden();
        $this->postJson('/api/admin/courses', [])->assertForbidden();
    }

    public function test_admin_course_validation_rejects_invalid_category(): void
    {
        $this->makeAdmin();
        $payload = $this->makeCoursePayload(['category' => 'Categoria Inválida']);

        $response = $this->postJson('/api/admin/courses', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['category']);
    }
}
