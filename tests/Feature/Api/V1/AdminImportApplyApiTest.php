<?php

namespace Tests\Feature\Api\V1;

use App\Models\AdminImportRun;
use App\Models\CourseCategory;
use App\Models\OperationsAudit;
use App\Models\Role;
use App\Models\StagedItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminImportApplyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_apply(): void
    {
        $this->seed();
        config()->set('features.lexilingo_import', true);
        $run = $this->makeRun($this->user('admin'), 'categories');

        $this->actingAs($this->user('admin'))->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply", [])
            ->assertForbidden();
    }

    public function test_super_admin_without_recent_google_step_up_is_rejected(): void
    {
        $this->seed();
        config()->set('features.lexilingo_import', true);
        $run = $this->makeRun($this->user('admin'), 'categories');
        $super = $this->user('super_admin');

        $this->actingAs($super);
        session()->put('google_admin_reauthenticated_at', now()->subMinutes(16)->timestamp);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply", [])
            ->assertStatus(428);
    }

    public function test_super_admin_can_apply_a_new_staged_category(): void
    {
        $this->seed();
        config()->set('features.lexilingo_import', true);
        $run = $this->makeRun($this->user('admin'), 'categories');
        $item = $this->stageCategory($run, 'cat-1', 'new', [
            'name' => 'Everyday English', 'slug' => 'everyday-english',
        ], null);

        $this->actingAs($this->user('super_admin'))->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply", ['item_ids' => [$item->id]])
            ->assertOk()
            ->assertJsonPath('data.applied', [$item->id])
            ->assertJsonPath('data.stale', [])
            ->assertJsonPath('data.failed', []);

        $this->assertDatabaseHas('course_categories', ['external_id' => 'cat-1', 'name' => 'Everyday English']);
        $this->assertDatabaseHas('staged_items', ['id' => $item->id, 'status' => 'applied']);
        $this->assertDatabaseHas('admin_import_runs', ['id' => $run->id, 'status' => 'approved']);
    }

    public function test_apply_rejects_a_stale_item_without_overwriting_the_row(): void
    {
        $this->seed();
        config()->set('features.lexilingo_import', true);
        $category = CourseCategory::create(['external_id' => 'cat-2', 'name' => 'Old Name', 'slug' => 'old-slug']);
        $run = $this->makeRun($this->user('admin'), 'categories');
        $item = $this->stageCategory($run, 'cat-2', 'update', [
            'name' => 'New Name', 'slug' => 'new-slug',
        ], now()->subDay()->toISOString());

        $this->actingAs($this->user('super_admin'))->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply", ['item_ids' => [$item->id]])
            ->assertOk()
            ->assertJsonPath('data.stale', [$item->id])
            ->assertJsonPath('data.applied', []);

        $this->assertDatabaseHas('course_categories', ['id' => $category->id, 'name' => 'Old Name']);
        $this->assertDatabaseHas('staged_items', ['id' => $item->id, 'status' => 'stale']);
    }

    public function test_replaying_apply_on_an_already_applied_item_is_a_no_op(): void
    {
        $this->seed();
        config()->set('features.lexilingo_import', true);
        $run = $this->makeRun($this->user('admin'), 'categories');
        $item = $this->stageCategory($run, 'cat-3', 'new', [
            'name' => 'Idioms', 'slug' => 'idioms',
        ], null);
        $superAdmin = $this->user('super_admin');

        $this->actingAs($superAdmin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply", ['item_ids' => [$item->id]])
            ->assertOk()->assertJsonPath('data.applied', [$item->id]);

        $this->actingAs($superAdmin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply", ['item_ids' => [$item->id]])
            ->assertOk()->assertJsonPath('data.applied', []);

        $this->assertDatabaseCount('course_categories', 1);
    }

    public function test_apply_is_not_supported_for_other_entities_yet(): void
    {
        $this->seed();
        config()->set('features.lexilingo_import', true);
        $run = $this->makeRun($this->user('admin'), 'courses');

        $this->actingAs($this->user('super_admin'))->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply", [])
            ->assertStatus(422);
    }

    public function test_apply_writes_a_single_audit_row_with_only_counts(): void
    {
        $this->seed();
        config()->set('features.lexilingo_import', true);
        $run = $this->makeRun($this->user('admin'), 'categories');
        $item = $this->stageCategory($run, 'cat-4', 'new', [
            'name' => 'Travel', 'slug' => 'travel',
        ], null);

        $this->actingAs($this->user('super_admin'))->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply", ['item_ids' => [$item->id]])
            ->assertOk();

        $this->assertSame(1, OperationsAudit::query()->where('action', 'content_import.applied')->count());
        $audit = OperationsAudit::query()->where('action', 'content_import.applied')->first();
        // assertEquals, not assertSame: MySQL's JSON column type does not
        // guarantee preserving key insertion order on round-trip, and
        // assertSame's array comparison is order-sensitive.
        $this->assertEquals(['applied' => 1, 'stale' => 0, 'failed' => 0], $audit->after_state);
        $this->assertEquals(['run_id' => $run->id, 'requested_item_ids' => [$item->id]], $audit->context);
    }

    private function stageCategory(AdminImportRun $run, string $externalId, string $classification, array $incoming, ?string $existingRevision): StagedItem
    {
        return StagedItem::create([
            'admin_import_run_id' => $run->id,
            'entity' => 'categories',
            'external_id' => $externalId,
            'classification' => $classification,
            'incoming_snapshot' => $incoming,
            'existing_snapshot' => null,
            'existing_revision' => $existingRevision,
            'status' => 'staged',
        ]);
    }

    private function makeRun(User $admin, string $entity): AdminImportRun
    {
        return AdminImportRun::create([
            'request_id' => (string) Str::uuid(),
            'entity' => $entity,
            'payload_fingerprint' => hash('sha256', Str::uuid()),
            'actor_id' => $admin->id,
            'status' => 'review-ready',
            'requested_limit' => 50,
            'reset' => false,
            'starting_cursor' => 0,
            'processed' => 1,
            'skipped' => 0,
            'result_cursor' => 1,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::query()->where('slug', $role)->value('id')]);
    }
}
