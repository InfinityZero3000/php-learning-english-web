<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\Level;
use App\Models\Topic;
use App\Models\Vocabulary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $this->getJson("/api/v1/admin/catalog/vocabularies/{$vocabulary}")
            ->assertOk()->assertJsonPath('data.word', 'deploy');
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
        ])->assertOk()->assertJsonPath('data.status', 'draft');
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/catalog/courses/{$courseId}/publish")
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/catalog/courses/{$courseId}/publish")
            ->assertConflict()->assertJsonPath('code', 'INVALID_STATE');
        $this->getJson('/api/v1/admin/catalog/courses?search=Business%20English')
            ->assertOk()->assertJsonPath('data.0.id', $courseId);
        $this->assertDatabaseHas('courses', ['id' => $courseId, 'status' => 'published']);
    }

    public function test_course_filters_and_relations_round_trip(): void
    {
        $this->seed();
        $level = Level::create(['name' => 'B9', 'slug' => 'b9-admin', 'sort_order' => 9]);
        $topic = Topic::create(['name' => 'Admin Work', 'slug' => 'admin-work']);
        $this->actingAs($this->user('admin'));
        $id = $this->withHeader('X-Request-ID', (string) Str::uuid())->postJson('/api/v1/admin/catalog/courses', [
            'title' => 'Work English', 'slug' => 'work-english', 'status' => 'draft', 'language' => 'en',
            'level_id' => $level->id, 'topic_ids' => [$topic->id],
        ])->assertCreated()->assertJsonPath('data.level.id', $level->id)->assertJsonPath('data.topics.0.id', $topic->id)->json('data.id');
        $this->getJson("/api/v1/admin/catalog/courses?status=draft&level_id={$level->id}")
            ->assertOk()->assertJsonPath('data.0.id', $id);
    }

    public function test_only_an_unused_draft_course_can_be_deleted(): void
    {
        $this->seed();
        $this->actingAs($this->user('admin'));
        $draft = \App\Models\Course::create(['title' => 'Disposable', 'slug' => 'disposable', 'status' => 'draft']);
        $published = \App\Models\Course::create(['title' => 'Protected', 'slug' => 'protected', 'status' => 'published']);

        $this->deleteJson("/api/v1/admin/catalog/courses/{$published->id}")
            ->assertConflict()->assertJsonPath('code', 'DEPENDENCY_EXISTS');
        $this->deleteJson("/api/v1/admin/catalog/courses/{$draft->id}")->assertNoContent();

        $this->assertModelMissing($draft);
        $this->assertModelExists($published);
    }

    public function test_vocabulary_media_can_be_uploaded_replaced_and_removed(): void
    {
        $this->seed();
        Storage::fake('public');
        $word = Vocabulary::create(['word' => 'hello', 'meaning' => 'xin chào']);
        $this->actingAs($this->user('admin'));
        $this->post("/api/v1/admin/catalog/vocabularies/{$word->id}/media", [
            'image' => UploadedFile::fake()->image('hello.jpg'),
            'audio' => UploadedFile::fake()->create('hello.mp3', 100, 'audio/mpeg'),
        ])->assertOk()->assertJsonPath('data.id', $word->id);
        $word->refresh();
        Storage::disk('public')->assertExists($word->image_path);
        Storage::disk('public')->assertExists($word->audio_path);
        $oldImage = $word->image_path;
        $this->post("/api/v1/admin/catalog/vocabularies/{$word->id}/media", ['remove_image' => true])->assertOk();
        Storage::disk('public')->assertMissing($oldImage);
        $this->assertNull($word->fresh()->image_path);

        Storage::disk('public')->put('shared/do-not-delete.jpg', 'shared');
        $unmanaged = Vocabulary::create(['word' => 'shared', 'meaning' => 'chung', 'image_path' => 'shared/do-not-delete.jpg']);
        $this->withHeader('X-Request-ID', (string) Str::uuid())->deleteJson("/api/v1/admin/catalog/vocabularies/{$unmanaged->id}")->assertNoContent();
        Storage::disk('public')->assertExists('shared/do-not-delete.jpg');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]);
    }
}
