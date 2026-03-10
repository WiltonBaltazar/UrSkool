<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnrollmentCheckoutLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_blocks_another_course_when_recent_pending_exists(): void
    {
        $user = $this->createCheckoutUser('student1@example.com');
        $firstCourse = $this->createPaidCourse('Curso Pendente 1');
        $secondCourse = $this->createPaidCourse('Curso Pendente 2');

        $this->createPendingEnrollment($firstCourse, $user, 'REF-LOCK-NEW', 2);

        $response = $this->postJson('/api/checkout', [
            'courseId' => $secondCourse->id,
            'fullName' => $user->name,
            'email' => $user->email,
            'password' => 'Password@123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['courseId']);

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $firstCourse->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_reference' => 'REF-LOCK-NEW',
        ]);
    }

    public function test_checkout_releases_another_course_pending_after_lock_window(): void
    {
        $user = $this->createCheckoutUser('student2@example.com');
        $firstCourse = $this->createPaidCourse('Curso Antigo 1');
        $secondCourse = $this->createPaidCourse('Curso Antigo 2');

        $this->createPendingEnrollment($firstCourse, $user, 'REF-LOCK-OLD', 8);

        $response = $this->postJson('/api/checkout', [
            'courseId' => $secondCourse->id,
            'fullName' => $user->name,
            'email' => $user->email,
            'password' => 'Password@123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mpesaContact']);

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $firstCourse->id,
            'user_id' => $user->id,
            'status' => 'failed',
            'payment_reference' => 'REF-LOCK-OLD',
        ]);
    }

    public function test_checkout_reuses_same_course_pending_reference_when_recent_pending_exists(): void
    {
        $user = $this->createCheckoutUser('student3@example.com');
        $course = $this->createPaidCourse('Curso Com Mesmo Pendente');

        $this->createPendingEnrollment($course, $user, 'REF-SAME-PENDING', 1);

        $response = $this->postJson('/api/checkout', [
            'courseId' => $course->id,
            'fullName' => $user->name,
            'email' => $user->email,
            'password' => 'Password@123',
        ]);

        $this->assertContains($response->status(), [200, 202]);
        $response->assertJsonPath('data.paymentReference', 'REF-SAME-PENDING');

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $course->id,
            'user_id' => $user->id,
            'payment_reference' => 'REF-SAME-PENDING',
        ]);

        $this->assertContains(
            Enrollment::query()->where('course_id', $course->id)->where('user_id', $user->id)->value('status'),
            ['pending', 'completed']
        );
    }

    private function createCheckoutUser(string $email): User
    {
        return User::query()->create([
            'name' => 'Checkout Student',
            'email' => $email,
            'password' => Hash::make('Password@123'),
            'is_admin' => false,
        ]);
    }

    private function createPaidCourse(string $title): Course
    {
        return Course::query()->create([
            'title' => $title,
            'subtitle' => 'Subtitulo',
            'instructor' => 'Instrutor Teste',
            'rating' => 4.7,
            'review_count' => 0,
            'student_count' => 0,
            'price' => 1200,
            'original_price' => 1500,
            'image' => 'https://example.com/course.jpg',
            'category' => 'Desenvolvimento Web',
            'level' => 'Iniciante',
            'total_hours' => 5,
            'total_lessons' => 3,
            'description' => 'Curso de teste.',
        ]);
    }

    private function createPendingEnrollment(Course $course, User $user, string $reference, int $minutesAgo): Enrollment
    {
        $enrollment = Enrollment::query()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'amount' => 1200,
            'status' => 'pending',
            'payment_reference' => $reference,
        ]);

        $timestamp = now()->subMinutes($minutesAgo);
        $enrollment->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->save();

        return $enrollment;
    }
}
