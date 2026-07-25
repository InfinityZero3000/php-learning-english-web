<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCourseLevelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->admin = User::factory()->create(['role_id' => 1]); // admin
        $this->user = User::factory()->create(['role_id' => 2]);  // regular user
    }

    // ---- COURSES ----

    public function test_admin_can_view_course_list(): void
    {
        Course::factory(5)->for(Level::factory())->create();
        $response = $this->actingAs($this->admin)->get(route('admin.courses.index'));
        $response->assertStatus(200)->assertViewHas('courses');
    }

    public function test_non_admin_cannot_view_course_list(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.courses.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_create_course(): void
    {
        $level = Level::factory()->create();
        $payload = [
            'title' => 'English Basics',
            'slug' => 'english-basics',
            'description' => 'A beginner course.',
            'status' => 'published',
            'level_id' => $level->id,
        ];
        $response = $this->actingAs($this->admin)->post(route('admin.courses.store'), $payload);
        $response->assertRedirect(route('admin.courses.index'));
        $this->assertDatabaseHas('courses', ['slug' => 'english-basics']);
    }

    public function test_admin_can_update_course(): void
    {
        $course = Course::factory()->for(Level::factory())->create(['title' => 'Old Title']);
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.courses.update', $course), [
                'title' => 'Updated Title',
                'slug' => $course->slug,
                'level_id' => $course->level_id,
                'status' => 'published',
            ]);
        $response->assertRedirect(route('admin.courses.index'));
        $this->assertEquals('Updated Title', $course->fresh()->title);
    }

    public function test_admin_can_delete_course(): void
    {
        $course = Course::factory()->for(Level::factory())->create();
        $response = $this->actingAs($this->admin)->delete(route('admin.courses.destroy', $course));
        $response->assertRedirect(route('admin.courses.index'));
        $this->assertModelMissing($course);
    }

    // ---- LEVELS ----

    public function test_admin_can_view_level_list(): void
    {
        Level::factory(5)->create();
        $response = $this->actingAs($this->admin)->get(route('admin.levels.index'));
        $response->assertStatus(200)->assertViewHas('levels');
    }

    public function test_non_admin_cannot_view_level_list(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.levels.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_create_level(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.levels.store'), [
            'name' => 'Advanced Plus',
            'slug' => 'advanced-plus',
            'sort_order' => 10,
        ]);
        $response->assertRedirect(route('admin.levels.index'));
        $this->assertDatabaseHas('levels', ['slug' => 'advanced-plus']);
    }

    public function test_admin_can_update_level(): void
    {
        $level = Level::factory()->create(['name' => 'Old Name']);
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.levels.update', $level), [
                'name' => 'New Name',
                'slug' => $level->slug,
                'sort_order' => 5,
            ]);
        $response->assertRedirect(route('admin.levels.index'));
        $this->assertEquals('New Name', $level->fresh()->name);
    }

    public function test_admin_can_delete_level(): void
    {
        $level = Level::factory()->create();
        $response = $this->actingAs($this->admin)->delete(route('admin.levels.destroy', $level));
        $response->assertRedirect(route('admin.levels.index'));
        $this->assertModelMissing($level);
    }

    // ---- VALIDATION ----

    public function test_create_course_validation(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.courses.store'), []);
        $response->assertSessionHasErrors(['title', 'slug']);
    }

    public function test_create_level_validation(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.levels.store'), []);
        $response->assertSessionHasErrors(['name', 'slug']);
    }
}