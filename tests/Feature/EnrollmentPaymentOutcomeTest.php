<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class EnrollmentPaymentOutcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_returns_failed_when_mpesa_initiation_fails_definitively(): void
    {
        config()->set('mpesa.mock', false);

        $user = $this->createCheckoutUser('pay-fail@example.com');
        $course = $this->createPaidCourse('Curso Falha Pagamento');

        $this->mock(MpesaService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('hasLiveConfiguration')->once()->andReturn(true);
            $mock->shouldReceive('initiatePayment')->once()->andReturn([
                'success' => false,
                'message' => 'Saldo insuficiente na conta M-Pesa.',
                'response_code' => 'INS-2006',
                'third_party_reference' => 'TFAIL001',
            ]);
            $mock->shouldReceive('isTimeoutResponseCode')->once()->with('INS-2006')->andReturn(false);
            $mock->shouldNotReceive('queryTransactionStatus');
        });

        $response = $this->postJson('/api/checkout', [
            'courseId' => $course->id,
            'fullName' => $user->name,
            'email' => $user->email,
            'password' => 'Password@123',
            'mpesaContact' => '841234567',
        ]);

        $response->assertStatus(402);
        $response->assertJsonPath('data.status', 'failed');
        $response->assertJsonPath('data.paymentReference', 'TFAIL001');

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $course->id,
            'user_id' => $user->id,
            'status' => 'failed',
            'payment_reference' => 'TFAIL001',
        ]);
    }

    public function test_checkout_returns_pending_when_mpesa_failure_is_ambiguous(): void
    {
        config()->set('mpesa.mock', false);

        $user = $this->createCheckoutUser('pay-pending@example.com');
        $course = $this->createPaidCourse('Curso Pending Ambiguo');

        $this->mock(MpesaService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('hasLiveConfiguration')->once()->andReturn(true);
            $mock->shouldReceive('initiatePayment')->once()->andReturn([
                'success' => false,
                'message' => 'Timeout no gateway.',
                'response_code' => 'INS-9',
                'third_party_reference' => 'TPEND001',
            ]);
            $mock->shouldReceive('isTimeoutResponseCode')->once()->with('INS-9')->andReturn(true);
            $mock->shouldNotReceive('queryTransactionStatus');
        });

        $response = $this->postJson('/api/checkout', [
            'courseId' => $course->id,
            'fullName' => $user->name,
            'email' => $user->email,
            'password' => 'Password@123',
            'mpesaContact' => '841234567',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.paymentReference', 'TPEND001');

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $course->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_reference' => 'TPEND001',
        ]);
    }

    public function test_checkout_returns_completed_when_query_confirms_success(): void
    {
        config()->set('mpesa.mock', false);

        $user = $this->createCheckoutUser('pay-success@example.com');
        $course = $this->createPaidCourse('Curso Sucesso Pagamento');

        $this->mock(MpesaService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('hasLiveConfiguration')->once()->andReturn(true);
            $mock->shouldReceive('initiatePayment')->once()->andReturn([
                'success' => true,
                'response_code' => 'INS-0',
                'transaction_id' => 'TXN0001',
                'third_party_reference' => 'TDONE001',
            ]);
            $mock->shouldReceive('queryTransactionStatus')->once()->andReturn([
                'success' => true,
                'status' => 'COMPLETED',
            ]);
            $mock->shouldReceive('isSuccessfulTransactionStatus')->once()->with('COMPLETED')->andReturn(true);
        });

        $response = $this->postJson('/api/checkout', [
            'courseId' => $course->id,
            'fullName' => $user->name,
            'email' => $user->email,
            'password' => 'Password@123',
            'mpesaContact' => '841234567',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonPath('data.paymentReference', 'TDONE001');
        $response->assertJsonPath('data.accountCreated', false);

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $course->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'payment_reference' => 'TDONE001',
        ]);

        $this->assertSame(1, Course::query()->findOrFail($course->id)->student_count);
    }

    public function test_checkout_returns_failed_when_query_confirms_failure(): void
    {
        config()->set('mpesa.mock', false);

        $user = $this->createCheckoutUser('pay-query-failed@example.com');
        $course = $this->createPaidCourse('Curso Falha na Consulta');

        $this->mock(MpesaService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('hasLiveConfiguration')->once()->andReturn(true);
            $mock->shouldReceive('initiatePayment')->once()->andReturn([
                'success' => true,
                'response_code' => 'INS-0',
                'transaction_id' => 'TXN0002',
                'third_party_reference' => 'TQFAIL01',
            ]);
            $mock->shouldReceive('queryTransactionStatus')->once()->andReturn([
                'success' => true,
                'status' => 'FAILED',
            ]);
            $mock->shouldReceive('isSuccessfulTransactionStatus')->once()->with('FAILED')->andReturn(false);
            $mock->shouldReceive('isFailedTransactionStatus')->once()->with('FAILED')->andReturn(true);
        });

        $response = $this->postJson('/api/checkout', [
            'courseId' => $course->id,
            'fullName' => $user->name,
            'email' => $user->email,
            'password' => 'Password@123',
            'mpesaContact' => '841234567',
        ]);

        $response->assertStatus(402);
        $response->assertJsonPath('data.status', 'failed');
        $response->assertJsonPath('data.paymentReference', 'TQFAIL01');

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $course->id,
            'user_id' => $user->id,
            'status' => 'failed',
            'payment_reference' => 'TQFAIL01',
        ]);

        $this->assertSame(0, Course::query()->findOrFail($course->id)->student_count);
    }

    public function test_checkout_returns_pending_when_query_is_inconclusive_after_successful_initiation(): void
    {
        config()->set('mpesa.mock', false);

        $user = $this->createCheckoutUser('pay-query-pending@example.com');
        $course = $this->createPaidCourse('Curso Consulta Inconclusiva');

        $this->mock(MpesaService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('hasLiveConfiguration')->once()->andReturn(true);
            $mock->shouldReceive('initiatePayment')->once()->andReturn([
                'success' => true,
                'response_code' => 'INS-0',
                'transaction_id' => 'TXN0003',
                'third_party_reference' => 'TQPEND01',
            ]);
            $mock->shouldReceive('queryTransactionStatus')->once()->andReturn([
                'success' => false,
                'technical_message' => 'timeout while querying provider',
            ]);
            $mock->shouldReceive('isSuccessfulResponseCode')->once()->with('INS-0')->andReturn(true);
        });

        $response = $this->postJson('/api/checkout', [
            'courseId' => $course->id,
            'fullName' => $user->name,
            'email' => $user->email,
            'password' => 'Password@123',
            'mpesaContact' => '841234567',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.paymentReference', 'TQPEND01');

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $course->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_reference' => 'TQPEND01',
        ]);

        $this->assertSame(0, Course::query()->findOrFail($course->id)->student_count);
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
}
