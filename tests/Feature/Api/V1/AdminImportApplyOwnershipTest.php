<?php

namespace Tests\Feature\Api\V1;

use App\Models\AdminImportRun;
use App\Models\CourseCategory;
use App\Models\Role;
use App\Models\StagedItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Apply is the only path that writes a staged row into the catalog, so it has
 * to obey the same ownership rules the importers do: rows it creates must be
 * marked as provider-owned, and a row an admin has edited locally must never
 * be silently replaced.
 */
class AdminImportApplyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config()->set('features.lexilingo_import', true);
        config()->set('features.lexilingo_import_apply', true);
        config()->set('services.lexilingo.backend_url', 'http://localhost');
        config()->set('services.lexilingo.partner_api_key', 'partner-test');
    }

    public function test_applied_row_is_provider_owned_so_the_next_import_does_not_duplicate_it(): void
    {
        $super = $this->user('super_admin');
        $run = $this->makeRun($super);
        $this->item($run, 'cat-1', ['name' => 'Everyday', 'slug' => 'everyday']);

        $this->actingAs($super)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply", [])->assertOk();

        $applied = CourseCategory::query()->where('external_id', 'cat-1')->firstOrFail();
        $this->assertSame('lexilingo', $applied->source_system, 'Apply must claim provider ownership.');
        $this->assertNotNull($applied->source_fingerprint, 'Apply must record the canonical fingerprint.');

        // The next fetch must recognise the row it just applied instead of
        // creating a second one under the same external id.
        Http::fake(['http://localhost/api/v1/integrations/categories*' => Http::response(['data' => [[
            'id' => 'cat-1', 'name' => 'Everyday', 'slug' => 'everyday',
            'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
        ]]])]);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 50])->assertStatus(202);

        $this->assertSame(1, CourseCategory::query()->where('external_id', 'cat-1')->count());
        $this->assertDatabaseHas('staged_items', ['external_id' => 'cat-1', 'classification' => 'unchanged']);
    }

    public function test_apply_refuses_to_overwrite_a_locally_edited_row(): void
    {
        $super = $this->user('super_admin');
        [$category] = CourseCategory::syncFromSource('category', 'lexilingo', 'cat-1', [
            'name' => 'Upstream', 'slug' => 'upstream', 'description' => null, 'icon' => null, 'color' => null,
        ]);
        $run = $this->makeRun($super);
        $item = $this->item($run, 'cat-1', ['name' => 'Replacement', 'slug' => 'replacement'], $category);
        $category->applyLocalEdit(['name' => 'Curated by editor']);

        $this->actingAs($super)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply", [])->assertOk();

        $this->assertSame('Curated by editor', $category->fresh()->name);
        $this->assertSame('stale', $item->fresh()->status);
    }

    private function makeRun(User $actor): AdminImportRun
    {
        return AdminImportRun::create([
            'request_id' => (string) Str::uuid(), 'entity' => 'categories',
            'payload_fingerprint' => hash('sha256', (string) Str::uuid()),
            'actor_id' => $actor->id, 'status' => 'review-ready',
            'requested_limit' => 10, 'starting_cursor' => 0,
        ]);
    }

    private function item(AdminImportRun $run, string $externalId, array $snapshot, ?CourseCategory $existing = null): StagedItem
    {
        return StagedItem::create([
            'admin_import_run_id' => $run->id, 'entity' => 'categories', 'external_id' => $externalId,
            'classification' => $existing ? 'update' : 'new', 'incoming_snapshot' => $snapshot,
            'existing_snapshot' => $existing?->only(['name', 'slug']),
            'base_revision' => $existing?->catalog_revision,
            'base_fingerprint' => $existing?->source_fingerprint,
            'status' => 'staged',
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::query()->where('slug', $role)->value('id')]);
    }
}
