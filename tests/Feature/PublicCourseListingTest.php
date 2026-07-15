<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCourseListingTest extends TestCase
{
    use RefreshDatabase;

    private function createCourse(array $overrides = []): Course
    {
        return Course::query()->create(array_merge([
            'title' => 'Curso de HTML',
            'subtitle' => 'Aprende HTML',
            'instructor' => 'João Silva',
            'rating' => 4.5,
            'review_count' => 10,
            'student_count' => 20,
            'price' => 150,
            'original_price' => 200,
            'image' => 'https://example.com/img.jpg',
            'category' => 'Desenvolvimento Web',
            'level' => 'Iniciante',
            'total_hours' => 5,
            'total_lessons' => 3,
            'description' => 'Aprende HTML do zero.',
        ], $overrides));
    }

    public function test_public_course_listing_returns_paginated_response(): void
    {
        $this->createCourse(['title' => 'Curso A']);
        $this->createCourse(['title' => 'Curso B']);

        $response = $this->getJson('/api/courses');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [['id', 'title', 'instructor', 'category', 'level', 'price']],
            'meta' => ['currentPage', 'lastPage', 'perPage', 'total'],
        ]);
        $response->assertJsonFragment(['total' => 2]);
    }

    public function test_public_course_listing_filters_by_search(): void
    {
        $this->createCourse(['title' => 'Curso de CSS Avançado']);
        $this->createCourse(['title' => 'Curso de JavaScript']);

        $response = $this->getJson('/api/courses?search=CSS');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Curso de CSS Avançado', $data[0]['title']);
    }

    public function test_public_course_listing_filters_by_category(): void
    {
        $this->createCourse(['title' => 'Web Dev', 'category' => 'Desenvolvimento Web']);
        $this->createCourse(['title' => 'JS Course', 'category' => 'JavaScript']);

        $response = $this->getJson('/api/courses?category=JavaScript');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('JS Course', $data[0]['title']);
    }

    public function test_public_course_detail_returns_sections_and_lessons(): void
    {
        $course = $this->createCourse();
        $section = \App\Models\Section::query()->create([
            'course_id' => $course->id,
            'title' => 'Introdução',
            'sort_order' => 1,
        ]);
        \App\Models\Lesson::query()->create([
            'section_id' => $section->id,
            'title' => 'Primeira Lição',
            'duration' => '05:00',
            'type' => 'video',
            'sort_order' => 1,
        ]);

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id', 'title', 'sections' => [
                    ['id', 'title', 'lessons' => [['id', 'title', 'type']]],
                ],
            ],
        ]);
        $response->assertJsonFragment(['title' => 'Primeira Lição']);
    }

    public function test_public_course_detail_includes_has_access_false_for_paid_course(): void
    {
        $course = $this->createCourse(['price' => 199]);

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertOk();
        $response->assertJsonFragment(['hasAccess' => false]);
    }

    public function test_free_course_is_accessible_without_enrollment(): void
    {
        $course = $this->createCourse(['price' => 0]);

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertOk();
        $response->assertJsonFragment(['hasAccess' => true]);
    }

    public function test_categories_endpoint_returns_unique_normalized_categories(): void
    {
        $this->createCourse(['category' => 'Desenvolvimento Web']);
        $this->createCourse(['category' => 'JavaScript']);
        $this->createCourse(['category' => 'Desenvolvimento Web']); // duplicate

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $categories = $response->json('data');
        $this->assertContains('Todas', $categories);
        $this->assertContains('Desenvolvimento Web', $categories);
        $this->assertContains('JavaScript', $categories);
        // No duplicates
        $this->assertCount(count(array_unique($categories)), $categories);
    }

    public function test_soft_deleted_course_does_not_appear_in_listing(): void
    {
        $course = $this->createCourse(['title' => 'Curso Eliminado']);
        $course->delete(); // soft delete

        $response = $this->getJson('/api/courses');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEmpty($data);
        $response->assertJsonFragment(['total' => 0]);
    }
}
