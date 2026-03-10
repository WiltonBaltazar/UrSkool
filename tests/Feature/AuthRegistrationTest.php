<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_availability_is_true_by_default(): void
    {
        $response = $this->getJson('/api/auth/signup-availability');

        $response->assertOk();
        $response->assertExactJson([
            'data' => [
                'allowSelfSignup' => true,
            ],
        ]);
    }

    public function test_signup_availability_is_false_when_disabled(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'allow_self_signup'],
            ['value' => 'false']
        );

        $response = $this->getJson('/api/auth/signup-availability');

        $response->assertOk();
        $response->assertExactJson([
            'data' => [
                'allowSelfSignup' => false,
            ],
        ]);
    }

    public function test_user_can_register_when_self_signup_is_enabled(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'allow_self_signup'],
            ['value' => 'true']
        );

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'message',
            'data' => ['id', 'name', 'email', 'isAdmin'],
            'session' => ['access_token_expires_at', 'refresh_token_expires_at', 'renew_before_seconds'],
        ]);
        $response->assertJsonPath('data.name', 'Ana Silva');
        $response->assertJsonPath('data.email', 'ana@example.com');
        $response->assertJsonPath('data.isAdmin', false);

        $user = User::query()->where('email', 'ana@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('Password@123', (string) $user->password));
    }

    public function test_user_cannot_register_when_self_signup_is_disabled(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'allow_self_signup'],
            ['value' => 'false']
        );

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Joao Costa',
            'email' => 'joao@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['signup']);
        $this->assertDatabaseMissing('users', ['email' => 'joao@example.com']);
    }

    public function test_user_cannot_register_with_duplicate_email(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'allow_self_signup'],
            ['value' => 'true']
        );

        User::factory()->create([
            'email' => 'maria@example.com',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Maria Langa',
            'email' => 'maria@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_user_cannot_register_with_emoji_in_name(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'allow_self_signup'],
            ['value' => 'true']
        );

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ana Silva 😊',
            'email' => 'ana@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }
}
