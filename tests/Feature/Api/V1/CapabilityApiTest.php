<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_capabilities_status_without_exposing_secrets(): void
    {
        $this->seed();

        $response = $this->getJson('/api/v1/capabilities');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'capabilities' => [
                        'lexilingo_import' => ['status', 'enabled'],
                        'ai' => ['status', 'enabled'],
                        'voice' => ['status', 'enabled'],
                        'youtube_listening' => ['status', 'enabled'],
                        'supervision' => ['status', 'enabled'],
                        'operations' => ['status', 'enabled'],
                    ],
                ],
            ]);

        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('API_KEY', $content);
        $this->assertStringNotContainsString('SECRET', $content);
        $this->assertStringNotContainsString('DB_', $content);
    }

    public function test_capability_status_matrix_for_roles_and_flags(): void
    {
        $this->seed();
        config(['features.operations' => true]);
        config(['features.youtube_listening' => false]);

        // Anonymous
        $anon = $this->getJson('/api/v1/capabilities');
        $anon->assertOk();
        $this::assertEquals('unconfigured', $anon->json('data.capabilities.youtube_listening.status'));
        $this::assertEquals('role_required', $anon->json('data.capabilities.operations.status'));

        // Learner
        $learner = User::factory()->create(['role_id' => Role::where('slug', 'learner')->value('id')]);
        $resLearner = $this->actingAs($learner)->getJson('/api/v1/capabilities');
        $this::assertEquals('role_required', $resLearner->json('data.capabilities.operations.status'));

        // Super Admin
        $superAdmin = User::factory()->create(['role_id' => Role::where('slug', 'super_admin')->value('id')]);
        $resSuper = $this->actingAs($superAdmin)->getJson('/api/v1/capabilities');
        $this::assertEquals('enabled', $resSuper->json('data.capabilities.operations.status'));
    }
}
