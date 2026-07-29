<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLessonApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_admin_can_filter_and_manage_lessons(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $course = Course::create(['title' => 'English', 'slug' => 'english', 'status' => 'draft', 'language' => 'en']);
        $other = Course::create(['title' => 'Other', 'slug' => 'other', 'status' => 'draft', 'language' => 'en']);
        Lesson::create(['course_id' => $other->id, 'title' => 'Ignore', 'slug' => 'ignore', 'sort_order' => 0, 'status' => 'draft']);

        $payload = ['course_id' => $course->id, 'title' => 'Greetings', 'slug' => 'greetings', 'content' => 'Hello', 'sort_order' => 1, 'estimated_minutes' => 10, 'status' => 'draft'];
        $id = $this->actingAs($admin)->postJson('/api/v1/admin/catalog/lessons', $payload)->assertCreated()->json('data.id');
        $this->getJson("/api/v1/admin/catalog/lessons?course_id={$course->id}&search=Greet&status=draft")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->postJson("/api/v1/admin/catalog/lessons/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'published');
        $this->postJson("/api/v1/admin/catalog/lessons/{$id}/archive")->assertOk()->assertJsonPath('data.status', 'archived');
        $this->putJson("/api/v1/admin/catalog/lessons/{$id}", [...$payload, 'title' => 'Updated', 'status' => 'draft'])
            ->assertOk()->assertJsonPath('data.status', 'archived');
        $this->getJson('/api/v1/admin/catalog/lessons/999999')->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_lesson_validation_and_capability_are_enforced(): void
    {
        $this->seed();
        $learner = User::factory()->create(['role_id' => Role::where('slug', 'learner')->value('id')]);
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $this->actingAs($learner)->getJson('/api/v1/admin/catalog/lessons')->assertForbidden();
        $this->actingAs($admin)->postJson('/api/v1/admin/catalog/lessons', [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    }
}
