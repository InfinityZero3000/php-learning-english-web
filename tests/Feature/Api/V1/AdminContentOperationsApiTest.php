<?php

namespace Tests\Feature\Api\V1;

use App\Models\AlertRule;
use App\Models\LexiLingoImportCheckpoint;
use App\Models\OperationsAudit;
use App\Models\Role;
use App\Models\SupervisionAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminContentOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('features.lexilingo_import', true);
    }

    public function test_import_start_is_unavailable_when_the_feature_is_disabled(): void
    {
        $this->seed();
        config()->set('features.lexilingo_import', false);

        $this->actingAs($this->user('admin'))->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 1])
            ->assertStatus(503);

        $this->assertDatabaseCount('admin_import_runs', 0);
    }

    public function test_admin_can_run_a_bounded_idempotent_import(): void
    {
        $this->seed();
        config()->set('services.lexilingo.backend_url', 'http://localhost');
        config()->set('services.lexilingo.partner_api_key', 'partner-test');
        Http::fake([
            'http://localhost/api/v1/integrations/categories*' => Http::response(['data' => [[
                'id' => 'cat-1', 'name' => 'Everyday English', 'slug' => 'everyday-english',
            ]]]),
        ]);
        $requestId = (string) Str::uuid();
        $admin = $this->user('admin');

        $response = $this->actingAs($admin)->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 50])
            ->assertStatus(202)->assertJsonPath('data.entity', 'categories')
            ->assertJsonPath('data.requested_limit', 10);
        $runId = $response->json('data.id');
        // Issue #44: terminal success status renamed to 'review-ready' now that
        // staged items exist for the admin to browse after a run completes.
        $this->assertDatabaseHas('admin_import_runs', ['id' => $runId, 'status' => 'review-ready']);
        $this->assertDatabaseHas('lexilingo_import_checkpoints', ['entity' => 'categories', 'cursor' => 1]);

        $this->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 50])
            ->assertStatus(202)->assertJsonPath('data.id', $runId);
        $this->assertSame(1, OperationsAudit::query()->where('request_id', $requestId)->count());

        $this->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/admin/imports', ['entity' => 'courses', 'limit' => 50])
            ->assertConflict();
    }

    public function test_reset_is_super_admin_only_and_requires_recent_google_verification(): void
    {
        $this->seed();
        $requestId = (string) Str::uuid();
        $this->actingAs($this->user('admin'))->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/admin/imports/reset', ['entity' => 'categories', 'limit' => 1])
            ->assertForbidden();

        $super = $this->user('super_admin');
        $this->actingAs($super);
        session()->put('google_admin_reauthenticated_at', now()->subMinutes(16)->timestamp);
        session()->put('google_admin_reauthenticated.at', now()->subMinutes(16)->timestamp);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports/reset', ['entity' => 'categories', 'limit' => 1])
            ->assertStatus(428);
    }

    public function test_super_admin_reset_replaces_the_checkpoint(): void
    {
        $this->seed();
        config()->set('services.lexilingo.backend_url', 'http://localhost');
        config()->set('services.lexilingo.partner_api_key', 'partner-test');
        Http::fake([
            'http://localhost/api/v1/integrations/categories*' => Http::response(['data' => [[
                'id' => 'cat-reset', 'name' => 'Reset Category', 'slug' => 'reset-category',
            ]]]),
        ]);
        LexiLingoImportCheckpoint::create(['entity' => 'categories', 'cursor' => 500]);

        $this->actingAs($this->user('super_admin'))->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports/reset', ['entity' => 'categories', 'limit' => 10])
            ->assertStatus(202)->assertJsonPath('data.starting_cursor', 0);

        $this->assertDatabaseHas('lexilingo_import_checkpoints', ['entity' => 'categories', 'cursor' => 1]);
    }

    public function test_notifications_never_serialize_learner_identity_or_evidence(): void
    {
        $this->seed();
        $learner = $this->user('learner');
        $rule = AlertRule::create(['rule_key' => 'low_mastery', 'version' => 1, 'enabled' => true, 'parameters' => []]);
        $alert = SupervisionAlert::create([
            'learner_id' => $learner->id,
            'alert_rule_id' => $rule->id,
            'rule_key' => 'low_mastery',
            'rule_version' => 1,
            'fingerprint' => 'privacy-test',
            'active_fingerprint' => 'privacy-test',
            'severity' => 'warning',
            'evidence' => ['response' => 'private answer'],
            'state' => 'open',
            'detected_at' => now('UTC'),
        ]);

        $response = $this->actingAs($this->user('admin'))->getJson('/api/v1/admin/notifications')
            ->assertOk()->assertJsonPath('data.0.id', $alert->id);
        $json = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($learner->email, $json);
        $this->assertStringNotContainsString('private answer', $json);
        $this->assertStringNotContainsString('learner_id', $json);

        $this->postJson("/api/v1/admin/notifications/{$alert->id}/read")->assertOk();
        $this->assertDatabaseHas('admin_notification_reads', [
            'user_id' => auth()->id(), 'supervision_alert_id' => $alert->id,
        ]);
    }

    public function test_admin_preferences_are_private_and_validated(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $this->actingAs($admin)->getJson('/api/v1/admin/preferences')
            ->assertOk()->assertJsonPath('data.notifications.operational', true);
        $this->putJson('/api/v1/admin/preferences', [
            'notifications' => ['operational' => false],
            'ui' => ['compact_sidebar' => true],
        ])->assertOk()->assertJsonPath('data.ui.compact_sidebar', true);
        $this->assertDatabaseHas('admin_preferences', ['user_id' => $admin->id]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::query()->where('slug', $role)->value('id')]);
    }
}
