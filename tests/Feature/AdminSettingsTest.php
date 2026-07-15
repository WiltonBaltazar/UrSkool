<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function defaultSettingsPayload(array $overrides = []): array
    {
        return array_merge([
            'platformName' => 'UrSkool Test',
            'supportEmail' => 'support@test.com',
            'currency' => 'MZN',
            'maintenanceMode' => false,
            'allowSelfSignup' => true,
            'defaultCourseVisibility' => 'public',
            'certificateSchoolName' => 'UrSkool',
            'certificateIssuerTitle' => 'Diretor',
            'certificateIssuerName' => 'João Silva',
        ], $overrides);
    }

    public function test_admin_can_read_settings(): void
    {
        $this->makeAdmin();

        $response = $this->getJson('/api/admin/settings');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'platformName',
                'supportEmail',
                'currency',
                'maintenanceMode',
                'allowSelfSignup',
                'defaultCourseVisibility',
                'certificateSchoolName',
                'certificateIssuerTitle',
                'certificateIssuerName',
            ],
        ]);
    }

    public function test_admin_can_update_settings(): void
    {
        $this->makeAdmin();

        $payload = $this->defaultSettingsPayload([
            'platformName' => 'Plataforma Actualizada',
            'maintenanceMode' => true,
            'allowSelfSignup' => false,
        ]);

        $response = $this->putJson('/api/admin/settings', $payload);

        $response->assertOk();

        // Read back to confirm persistence
        $readBack = $this->getJson('/api/admin/settings');
        $readBack->assertOk();
        $readBack->assertJsonFragment([
            'platformName' => 'Plataforma Actualizada',
            'maintenanceMode' => true,
            'allowSelfSignup' => false,
        ]);
    }

    public function test_settings_update_validates_required_fields(): void
    {
        $this->makeAdmin();

        $response = $this->putJson('/api/admin/settings', [
            'platformName' => '',
            'supportEmail' => 'not-an-email',
            'currency' => 'USD',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['platformName', 'supportEmail', 'currency']);
    }

    public function test_non_admin_cannot_read_or_update_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->putJson('/api/admin/settings', $this->defaultSettingsPayload())->assertForbidden();
    }

    public function test_maintenance_mode_defaults_to_false(): void
    {
        $this->makeAdmin();

        $response = $this->getJson('/api/admin/settings');

        $response->assertOk();
        $response->assertJsonFragment(['maintenanceMode' => false]);
    }
}
