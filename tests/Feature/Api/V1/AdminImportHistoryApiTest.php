<?php

namespace Tests\Feature\Api\V1;

use App\Models\AdminImportRun;
use App\Models\Role;
use App\Models\StagedItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminImportHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_run_history_with_pagination_and_filters(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $this->makeRun($admin, 'categories', 'review-ready');
        $this->makeRun($admin, 'courses', 'review-ready');
        $this->makeRun($admin, 'courses', 'failed');

        $this->actingAs($admin)->getJson('/api/v1/admin/imports/runs?entity=courses&status=review-ready')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entity', 'courses')
            ->assertJsonPath('data.0.status', 'review-ready')
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($admin)->getJson('/api/v1/admin/imports/runs?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_admin_can_list_staged_items_for_a_run_filtered_by_classification(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $run = $this->makeRun($admin, 'categories', 'review-ready');
        StagedItem::create([
            'admin_import_run_id' => $run->id, 'entity' => 'categories', 'external_id' => 'cat-new',
            'classification' => 'new', 'incoming_snapshot' => ['name' => 'New Category'],
        ]);
        StagedItem::create([
            'admin_import_run_id' => $run->id, 'entity' => 'categories', 'external_id' => 'cat-bad',
            'classification' => 'invalid', 'errors' => ['name is required'],
        ]);

        $this->actingAs($admin)->getJson("/api/v1/admin/imports/runs/{$run->id}/items?classification=invalid")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.classification', 'invalid')
            ->assertJsonPath('data.0.external_id', 'cat-bad');
    }

    public function test_staged_item_diff_only_exposes_allowlisted_fields(): void
    {
        $this->seed();
        config()->set('services.lexilingo.backend_url', 'http://localhost');
        config()->set('services.lexilingo.partner_api_key', 'partner-test');
        Http::fake([
            'http://localhost/api/v1/integrations/categories*' => Http::response(['data' => [[
                'id' => 'cat-secret', 'name' => 'Everyday English', 'slug' => 'everyday-english',
                'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
                'secret_provider_token' => 'do-not-leak-me',
            ]]]),
        ]);
        $admin = $this->user('admin');

        $response = $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 50])
            ->assertStatus(202);
        $runId = $response->json('data.id');

        $items = $this->actingAs($admin)->getJson("/api/v1/admin/imports/runs/{$runId}/items")
            ->assertOk()
            ->assertJsonPath('data.0.classification', 'new')
            ->assertJsonPath('data.0.incoming_snapshot.name', 'Everyday English');
        $json = json_encode($items->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('secret_provider_token', $json);
        $this->assertStringNotContainsString('do-not-leak-me', $json);
    }

    public function test_run_transitions_to_review_ready_and_stages_new_and_update_items(): void
    {
        $this->seed();
        config()->set('services.lexilingo.backend_url', 'http://localhost');
        config()->set('services.lexilingo.partner_api_key', 'partner-test');
        Http::fake([
            'http://localhost/api/v1/integrations/categories*' => Http::response(['data' => [[
                'id' => 'cat-1', 'name' => 'Everyday English', 'slug' => 'everyday-english',
                'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
            ]]]),
        ]);
        $admin = $this->user('admin');

        $first = $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 50])
            ->assertStatus(202);
        $firstRunId = $first->json('data.id');
        $this->assertDatabaseHas('admin_import_runs', ['id' => $firstRunId, 'status' => 'review-ready']);
        $this->assertDatabaseHas('staged_items', [
            'admin_import_run_id' => $firstRunId, 'external_id' => 'cat-1', 'classification' => 'new',
        ]);

        $second = $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 50])
            ->assertStatus(202);
        $secondRunId = $second->json('data.id');
        $this->assertDatabaseHas('admin_import_runs', ['id' => $secondRunId, 'status' => 'review-ready']);
        $this->assertDatabaseHas('staged_items', [
            'admin_import_run_id' => $secondRunId, 'external_id' => 'cat-1', 'classification' => 'update',
        ]);
    }

    public function test_guest_cannot_list_run_history(): void
    {
        $this->getJson('/api/v1/admin/imports/runs')->assertUnauthorized();
    }

    public function test_non_admin_cannot_list_run_history(): void
    {
        $this->actingAs($this->user('learner'))
            ->getJson('/api/v1/admin/imports/runs')
            ->assertForbidden();
    }

    private function makeRun(User $admin, string $entity, string $status): AdminImportRun
    {
        return AdminImportRun::create([
            'request_id' => (string) Str::uuid(),
            'entity' => $entity,
            'payload_fingerprint' => hash('sha256', Str::uuid()),
            'actor_id' => $admin->id,
            'status' => $status,
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
