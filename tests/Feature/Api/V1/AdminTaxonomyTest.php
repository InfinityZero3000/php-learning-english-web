<?php

namespace Tests\Feature\Api\V1;

use App\Models\CourseCategory;
use App\Models\Level;
use App\Models\Role;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::upsert([
            ['name' => 'Admin',   'slug' => 'admin'],
            ['name' => 'Learner', 'slug' => 'learner'],
        ], ['slug'], ['name']);

        $adminRoleId = Role::where('slug', 'admin')->value('id');
        $learnerRoleId = Role::where('slug', 'learner')->value('id');

        // Create admin user
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.local',
            'role_id' => $adminRoleId,
        ]);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'email' => 'user@test.local',
            'role_id' => $learnerRoleId,
        ]);
    }

    // ── Authorization ──────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_taxonomy_api(): void
    {
        $this->getJson('/api/v1/admin/topics')->assertUnauthorized();
        $this->getJson('/api/v1/admin/levels')->assertUnauthorized();
        $this->getJson('/api/v1/admin/categories')->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_access_taxonomy_api(): void
    {
        $this->actingAs($this->regularUser);

        $this->getJson('/api/v1/admin/topics')->assertForbidden();
        $this->getJson('/api/v1/admin/levels')->assertForbidden();
        $this->getJson('/api/v1/admin/categories')->assertForbidden();
    }

    // ── Topics CRUD ────────────────────────────────────────────

    public function test_admin_can_list_topics(): void
    {
        Topic::factory()->count(3)->create();

        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/topics')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'courses_count', 'vocabularies_count']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_admin_can_search_topics(): void
    {
        Topic::factory()->create(['name' => 'Business English', 'slug' => 'business-english']);
        Topic::factory()->create(['name' => 'Travel English', 'slug' => 'travel-english']);

        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/topics?search=Business')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Business English');
    }

    public function test_admin_can_create_topic(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/topics', [
                'name' => 'New Topic',
                'slug' => 'new-topic',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Topic')
            ->assertJsonPath('data.slug', 'new-topic');

        $this->assertDatabaseHas('topics', ['name' => 'New Topic', 'slug' => 'new-topic']);
    }

    public function test_topic_create_validates_unique_slug(): void
    {
        Topic::factory()->create(['slug' => 'existing-slug']);

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/topics', [
                'name' => 'Another Topic',
                'slug' => 'existing-slug',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_admin_can_update_topic(): void
    {
        $topic = Topic::factory()->create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/admin/topics/{$topic->id}", [
                'name' => 'Updated Name',
                'slug' => 'updated-slug',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.slug', 'updated-slug');

        $this->assertDatabaseHas('topics', ['id' => $topic->id, 'name' => 'Updated Name']);
    }

    public function test_admin_can_delete_topic(): void
    {
        $topic = Topic::factory()->create();

        $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/topics/{$topic->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('topics', ['id' => $topic->id]);
    }

    // ── Levels CRUD ────────────────────────────────────────────

    public function test_admin_can_list_levels(): void
    {
        Level::factory()->count(2)->create();

        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/levels')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'courses_count']],
                'meta',
            ])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_admin_can_create_level(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/levels', [
                'name' => 'Advanced',
                'slug' => 'advanced',
                'sort_order' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Advanced');

        $this->assertDatabaseHas('levels', ['name' => 'Advanced', 'sort_order' => 5]);
    }

    public function test_admin_can_update_level(): void
    {
        $level = Level::factory()->create(['name' => 'Beginner', 'sort_order' => 1]);

        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/admin/levels/{$level->id}", [
                'name' => 'Updated Level',
                'sort_order' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Level')
            ->assertJsonPath('data.sort_order', 10);
    }

    public function test_admin_can_delete_level(): void
    {
        $level = Level::factory()->create();

        $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/levels/{$level->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('levels', ['id' => $level->id]);
    }

    // ── Categories CRUD ────────────────────────────────────────

    public function test_admin_can_list_categories(): void
    {
        CourseCategory::factory()->count(2)->create();

        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/categories')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'courses_count']],
                'meta',
            ])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_admin_can_create_category(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'New Category',
                'slug' => 'new-category',
                'description' => 'A test category',
                'icon' => 'star',
                'color' => '#ff0000',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Category');

        $this->assertDatabaseHas('course_categories', [
            'name' => 'New Category',
            'description' => 'A test category',
            'icon' => 'star',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_category(): void
    {
        $category = CourseCategory::factory()->create(['name' => 'Old Cat', 'is_active' => true]);

        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/admin/categories/{$category->id}", [
                'name' => 'Updated Cat',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Cat')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_delete_category(): void
    {
        $category = CourseCategory::factory()->create();

        $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/categories/{$category->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('course_categories', ['id' => $category->id]);
    }

    public function test_category_create_validates_unique_slug(): void
    {
        CourseCategory::factory()->create(['slug' => 'existing-cat']);

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Another',
                'slug' => 'existing-cat',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    // ── Not found ──────────────────────────────────────────────

    public function test_topic_show_update_destroy_return_404_for_nonexistent_id(): void
    {
        $this->actingAs($this->adminUser);

        $this->getJson('/api/v1/admin/topics/999999')->assertNotFound();
        $this->putJson('/api/v1/admin/topics/999999', ['name' => 'X'])->assertNotFound();
        $this->deleteJson('/api/v1/admin/topics/999999')->assertNotFound();
    }

    public function test_level_show_update_destroy_return_404_for_nonexistent_id(): void
    {
        $this->actingAs($this->adminUser);

        $this->getJson('/api/v1/admin/levels/999999')->assertNotFound();
        $this->putJson('/api/v1/admin/levels/999999', ['name' => 'X'])->assertNotFound();
        $this->deleteJson('/api/v1/admin/levels/999999')->assertNotFound();
    }

    public function test_category_show_update_destroy_return_404_for_nonexistent_id(): void
    {
        $this->actingAs($this->adminUser);

        $this->getJson('/api/v1/admin/categories/999999')->assertNotFound();
        $this->putJson('/api/v1/admin/categories/999999', ['name' => 'X'])->assertNotFound();
        $this->deleteJson('/api/v1/admin/categories/999999')->assertNotFound();
    }

    // ── Pagination ─────────────────────────────────────────────

    public function test_topics_pagination_respects_per_page(): void
    {
        Topic::factory()->count(3)->create();

        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/topics?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonCount(1, 'data');
    }

    public function test_levels_pagination_respects_per_page(): void
    {
        Level::factory()->count(3)->create();

        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/levels?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonCount(1, 'data');
    }

    public function test_categories_pagination_respects_per_page(): void
    {
        CourseCategory::factory()->count(3)->create();

        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/categories?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonCount(1, 'data');
    }

    // ── Idempotency ────────────────────────────────────────────

    public function test_topic_creation_is_idempotent_by_slug(): void
    {
        // Create topic with same slug twice; first succeeds, second fails validation
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/topics', [
                'name' => 'Idempotent Topic',
                'slug' => 'idempotent-topic',
            ])
            ->assertCreated();

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/topics', [
                'name' => 'Idempotent Topic Duplicate',
                'slug' => 'idempotent-topic',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('topics', 1);
    }

    public function test_level_creation_is_idempotent_by_slug(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/levels', ['name' => 'L1', 'slug' => 'level-one'])
            ->assertCreated();

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/levels', ['name' => 'L1 Dup', 'slug' => 'level-one'])
            ->assertUnprocessable();

        $this->assertEquals(1, Level::where('slug', 'level-one')->count());
    }

    public function test_category_creation_is_idempotent_by_slug(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/categories', ['name' => 'Cat One', 'slug' => 'cat-one'])
            ->assertCreated();

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/categories', ['name' => 'Cat One Dup', 'slug' => 'cat-one'])
            ->assertUnprocessable();

        $this->assertEquals(1, CourseCategory::where('slug', 'cat-one')->count());
    }
}
