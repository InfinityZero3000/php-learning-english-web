<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('adminReadRoutes')]
    public function test_admin_and_super_admin_can_read_catalog_contracts(string $role, string $uri): void
    {
        $this->seed();
        $this->actingAs($this->user($role))->getJson($uri)->assertOk();
    }

    public static function adminReadRoutes(): array
    {
        $routes = ['summary', 'courses', 'levels', 'topics', 'vocabularies', 'decks'];

        return array_merge(...array_map(
            fn (string $role) => array_map(fn (string $route) => [$role, "/api/v1/admin/catalog/{$route}"], $routes),
            ['admin', 'super_admin'],
        ));
    }

    public function test_catalog_requires_authentication_role_and_google_marker(): void
    {
        $this->seed();
        $this->getJson('/api/v1/admin/catalog/summary')->assertUnauthorized();
        $this->actingAs($this->user('learner'))->getJson('/api/v1/admin/catalog/summary')->assertForbidden();

        $admin = $this->user('admin');
        parent::actingAs($admin);
        $this->withSession(['google_admin' => null])->getJson('/api/v1/admin/catalog/summary')->assertUnauthorized();
    }

    public function test_deck_mutations_are_idempotent_and_audited(): void
    {
        $this->seed();
        $requestId = (string) Str::uuid();
        $request = $this->actingAs($this->user('admin'))->withHeader('X-Request-ID', $requestId);
        $payload = ['name' => 'Admin Travel', 'slug' => 'admin-travel-deck', 'description' => 'Useful words', 'is_public' => true];

        $id = $request->postJson('/api/v1/admin/catalog/decks', $payload)
            ->assertCreated()->json('data.id');
        $this->withHeader('X-Request-ID', $requestId)->postJson('/api/v1/admin/catalog/decks', $payload)
            ->assertCreated()->assertJsonPath('data.id', $id);
        $this->withHeader('X-Request-ID', $requestId)->postJson('/api/v1/admin/catalog/decks', [...$payload, 'name' => 'Other'])
            ->assertConflict();
        $this->assertDatabaseCount('operations_audits', 1);

        $deleteId = (string) Str::uuid();
        $this->withHeader('X-Request-ID', $deleteId)->deleteJson("/api/v1/admin/catalog/decks/{$id}")->assertNoContent();
        $this->withHeader('X-Request-ID', $deleteId)->deleteJson("/api/v1/admin/catalog/decks/{$id}")->assertNoContent();
        $this->assertDatabaseCount('operations_audits', 2);
    }

    public function test_admin_can_manage_level_topic_and_vocabulary_contracts(): void
    {
        $this->seed();
        $this->actingAs($this->user('admin'));

        $level = $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/catalog/levels', ['name' => 'C3', 'slug' => 'admin-c3', 'sort_order' => 7])
            ->assertCreated()->json('data.id');
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/catalog/levels/{$level}", ['name' => 'C3+', 'slug' => 'admin-c3', 'sort_order' => 8])
            ->assertOk()->assertJsonPath('data.name', 'C3+');

        $topic = $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/catalog/topics', ['name' => 'Admin Topic', 'slug' => 'admin-topic'])
            ->assertCreated()->json('data.id');
        $vocabulary = $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/catalog/vocabularies', ['word' => 'deploy', 'meaning' => 'triển khai', 'topic_id' => $topic])
            ->assertCreated()->json('data.id');
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->deleteJson("/api/v1/admin/catalog/vocabularies/{$vocabulary}")->assertNoContent();
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->deleteJson("/api/v1/admin/catalog/topics/{$topic}")->assertNoContent();
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->deleteJson("/api/v1/admin/catalog/levels/{$level}")->assertNoContent();
    }

    public function test_admin_can_manage_courses_and_learner_cannot(): void
    {
        $this->seed();
        $learner = $this->user('learner');
        $admin = $this->user('admin');
        $createRequestId = (string) Str::uuid();

        $this->actingAs($learner)->getJson('/api/v1/admin/catalog/courses')->assertForbidden();
        $courseId = $this->actingAs($admin)->withHeader('X-Request-ID', $createRequestId)
            ->postJson('/api/v1/admin/catalog/courses', [
                'title' => 'Business English', 'slug' => 'business-english', 'status' => 'draft', 'language' => 'en',
            ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
        $this->withHeader('X-Request-ID', $createRequestId)->postJson('/api/v1/admin/catalog/courses', [
            'title' => 'Business English', 'slug' => 'business-english', 'status' => 'draft', 'language' => 'en',
        ])->assertCreated()->assertJsonPath('data.id', $courseId);
        $this->withHeader('X-Request-ID', (string) Str::uuid())->putJson("/api/v1/admin/catalog/courses/{$courseId}", [
            'title' => 'Business English', 'slug' => 'business-english', 'status' => 'published', 'language' => 'en',
        ])->assertOk()->assertJsonPath('data.status', 'published');
        $this->getJson('/api/v1/admin/catalog/courses?search=Business%20English')
            ->assertOk()->assertJsonPath('data.0.id', $courseId);
        $this->assertDatabaseHas('courses', ['id' => $courseId, 'status' => 'published']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]);
    }
}
