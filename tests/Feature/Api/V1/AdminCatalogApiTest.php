<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCatalogApiTest extends TestCase
{
    use RefreshDatabase;

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
