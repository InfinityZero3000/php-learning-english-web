<?php

namespace Tests\Feature\Api\V1;

use App\Models\CourseCategory;
use App\Models\LexiLingoImportCheckpoint;
use App\Models\Role;
use App\Models\StagedItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The checkpoint records how far the catalog has actually been brought up to
 * date. For a staged entity nothing is written at fetch time, so advancing it
 * there would move the fetch window past rows nobody has approved yet — if the
 * run is then cancelled or never applied, those rows are silently skipped and
 * only a `--reset` brings them back.
 */
class AdminImportCheckpointStagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config()->set('features.lexilingo_import', true);
        config()->set('services.lexilingo.backend_url', 'http://localhost');
        config()->set('services.lexilingo.partner_api_key', 'partner-test');
    }

    public function test_staged_fetch_does_not_advance_the_checkpoint_but_a_direct_write_does(): void
    {
        Http::fake([
            'http://localhost/api/v1/integrations/categories*' => Http::response(['data' => [[
                'id' => 'cat-1', 'name' => 'Everyday', 'slug' => 'everyday',
                'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
            ]]]),
            'http://localhost/api/v1/integrations/courses/course-1' => Http::response(['data' => ['id' => 'course-1', 'units' => []]]),
            'http://localhost/api/v1/integrations/courses*' => Http::response([[
                'id' => 'course-1', 'title' => 'English Basics', 'description' => null,
                'language' => 'en', 'level' => 'beginner', 'tags' => [], 'thumbnail_url' => null,
                'total_lessons' => 0, 'total_xp' => 0, 'estimated_duration' => 30,
            ]]),
        ]);
        $this->actingAs($this->user('admin'));

        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 50])->assertStatus(202);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'courses', 'limit' => 50])->assertStatus(202);

        // Staged: the row is only queued for review, so the fetch window must not move on.
        $this->assertDatabaseHas('staged_items', ['external_id' => 'cat-1', 'status' => 'staged']);
        $this->assertDatabaseMissing('course_categories', ['external_id' => 'cat-1']);
        $this->assertSame(0, $this->cursor('categories'), 'A staged fetch must leave the checkpoint alone.');

        // Direct write: the catalog really did move on, so the checkpoint may too.
        $this->assertDatabaseHas('courses', ['external_id' => 'course-1']);
        $this->assertSame(1, $this->cursor('courses'));
    }

    public function test_applying_a_staged_run_advances_the_checkpoint_past_it(): void
    {
        config()->set('features.lexilingo_import_apply', true);
        Http::fake(['http://localhost/api/v1/integrations/categories*' => Http::response(['data' => [[
            'id' => 'cat-1', 'name' => 'Everyday', 'slug' => 'everyday',
            'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
        ]]])]);

        $response = $this->actingAs($this->user('admin'))->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 50])->assertStatus(202);
        $runId = $response->json('data.id');
        $this->assertSame(0, $this->cursor('categories'));

        $this->actingAs($this->user('super_admin'))->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$runId}/apply", [])->assertOk();

        $this->assertDatabaseHas('course_categories', ['external_id' => 'cat-1', 'source_system' => 'lexilingo']);
        $this->assertSame(1, $this->cursor('categories'), 'Apply is when a staged run really lands.');
    }

    public function test_a_cancelled_staged_run_leaves_the_rows_fetchable_again(): void
    {
        Http::fake(['http://localhost/api/v1/integrations/categories*' => Http::response(['data' => [[
            'id' => 'cat-1', 'name' => 'Everyday', 'slug' => 'everyday',
            'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
        ]]])]);
        $this->actingAs($this->user('admin'));

        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 50])->assertStatus(202);
        StagedItem::query()->update(['status' => 'discarded']);

        // Nothing was applied, so a fresh fetch must see the same page again.
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 50])->assertStatus(202);

        $this->assertSame(2, StagedItem::query()->where('external_id', 'cat-1')->count());
        $this->assertSame(0, CourseCategory::query()->count());
    }

    private function cursor(string $entity): int
    {
        return (int) LexiLingoImportCheckpoint::query()->where('entity', $entity)->value('cursor');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::query()->where('slug', $role)->value('id')]);
    }
}
