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

    public function test_super_admin_can_version_quota_and_alert_rule_with_recent_google_verification(): void
    {
        $this->seed();
        $admin = $this->user('super_admin');
        $rule = AlertRule::create([
            'rule_key' => 'inactivity', 'version' => 1, 'enabled' => true, 'parameters' => [],
        ]);

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson('/api/v1/admin/operations/quota-policy', [
                'limits' => ['trace_cag_daily' => 100],
            ])->assertOk()->assertJsonPath('data.version', 1);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/operations/alert-rules/{$rule->id}", [
                'enabled' => false,
                'parameters' => ['days' => 10],
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
        ])->assertOk();
        $request->putJson('/api/v1/admin/operations/quota-policy', [
            'limits' => ['trace_cag_daily' => 100],
        ])->assertOk()->assertJsonPath('data.version', 1);
        $request->putJson('/api/v1/admin/operations/quota-policy', [
            'limits' => ['trace_cag_daily' => 200],
        ])->assertConflict();

        $this->assertDatabaseCount('quota_policies', 1);
        $this->assertDatabaseCount('operations_audits', 1);
    }

    public function test_quota_and_alert_rule_authorize_before_requiring_fresh_google_verification(): void
    {
        $this->seed();
        $rule = AlertRule::create([
            'rule_key' => 'inactivity', 'version' => 1, 'enabled' => true, 'parameters' => [],
        ]);

        $this->actingAs($this->user('admin'));
        session()->forget('google_admin_reauthenticated');
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson('/api/v1/admin/operations/quota-policy', ['limits' => ['trace_cag_daily' => 10]])
            ->assertForbidden();
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/operations/alert-rules/{$rule->id}", ['enabled' => false, 'parameters' => []])
            ->assertForbidden();

        $super = $this->user('super_admin');
        $this->actingAs($super);
        session()->put('google_admin_reauthenticated.at', now()->subMinutes(16)->timestamp);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson('/api/v1/admin/operations/quota-policy', ['limits' => ['trace_cag_daily' => 10]])
            ->assertStatus(428)->assertJsonPath('message', 'Recent Google verification is required.');

        $this->actingAs($super);
        session()->put('google_admin_reauthenticated.subject', 'another-google-subject');
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/operations/alert-rules/{$rule->id}", ['enabled' => false, 'parameters' => []])
            ->assertStatus(428);

        $this->assertDatabaseCount('quota_policies', 0);
        $this->assertDatabaseCount('operations_audits', 0);
        $this->assertSame(1, $rule->fresh()->version);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', $role)->value('id'),
        ]);
    }
}
