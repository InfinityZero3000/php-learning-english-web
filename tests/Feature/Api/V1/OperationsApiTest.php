<?php

namespace Tests\Feature\Api\V1;

use App\Models\AlertRule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_read_operations(): void
    {
        $this->seed();

        $this->actingAs($this->user('admin'))->getJson('/api/v1/admin/operations')->assertForbidden();
        $this->actingAs($this->user('super_admin'))->getJson('/api/v1/admin/operations')
            ->assertOk()
            ->assertJsonStructure(['data' => ['features', 'services', 'open_alerts']]);
    }

    public function test_super_admin_can_version_quota_and_alert_rule_with_recent_password(): void
    {
        $this->seed();
        $admin = $this->user('super_admin');
        $rule = AlertRule::create([
            'rule_key' => 'inactivity', 'version' => 1, 'enabled' => true, 'parameters' => [],
        ]);

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson('/api/v1/admin/operations/quota-policy', [
                'limits' => ['trace_cag_daily' => 100],
                'password' => 'password',
            ])->assertOk()->assertJsonPath('data.version', 1);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/operations/alert-rules/{$rule->id}", [
                'enabled' => false,
                'parameters' => ['days' => 10],
                'password' => 'password',
            ])->assertOk()->assertJsonPath('data.version', 2);
        $this->assertDatabaseCount('operations_audits', 2);
    }

    public function test_sensitive_operation_replays_an_identical_request_and_rejects_a_mismatch(): void
    {
        $this->seed();
        $requestId = (string) Str::uuid();
        $request = $this->actingAs($this->user('super_admin'))->withHeader('X-Request-ID', $requestId);

        $request->putJson('/api/v1/admin/operations/quota-policy', [
            'limits' => ['trace_cag_daily' => 100],
            'password' => 'password',
        ])->assertOk();
        $request->putJson('/api/v1/admin/operations/quota-policy', [
            'limits' => ['trace_cag_daily' => 100],
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.version', 1);
        $request->putJson('/api/v1/admin/operations/quota-policy', [
            'limits' => ['trace_cag_daily' => 200],
            'password' => 'password',
        ])->assertConflict();

        $this->assertDatabaseCount('quota_policies', 1);
        $this->assertDatabaseCount('operations_audits', 1);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'password' => 'password',
            'role_id' => Role::where('slug', $role)->value('id'),
        ]);
    }
}
