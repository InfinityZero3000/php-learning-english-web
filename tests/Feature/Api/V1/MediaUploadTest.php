<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
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

        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.local',
            'role_id' => $adminRoleId,
        ]);

        $this->regularUser = User::factory()->create([
            'email' => 'user@test.local',
            'role_id' => $learnerRoleId,
        ]);

        Storage::fake('media');
    }

    // ── Authorization ──────────────────────────────────────────

    public function test_unauthenticated_user_cannot_upload(): void
    {
        $file = UploadedFile::fake()->create('test.jpg', 10, 'image/jpeg');

        $this->postJson('/api/v1/admin/media/upload', ['file' => $file])
            ->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_upload(): void
    {
        $file = UploadedFile::fake()->create('test.jpg', 10, 'image/jpeg');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/admin/media/upload', ['file' => $file])
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_delete_media(): void
    {
        $this->deleteJson('/api/v1/admin/media/some-path.jpg')
            ->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_delete_media(): void
    {
        $this->actingAs($this->regularUser)
            ->deleteJson('/api/v1/admin/media/some-path.jpg')
            ->assertForbidden();
    }

    // ── Valid uploads ──────────────────────────────────────────

    public function test_admin_can_upload_valid_image(): void
    {
        Storage::fake('media');

        $file = UploadedFile::fake()->create('photo.jpg', 500, 'image/jpeg');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/media/upload', [
                'file' => $file,
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['url', 'path', 'mime_type', 'size']]);

        $path = $response->json('data.path');
        $this->assertNotNull($path);
        Storage::disk('media')->assertExists($path);
    }

    public function test_admin_can_upload_valid_audio(): void
    {
        Storage::fake('media');

        $file = UploadedFile::fake()->create('audio.mp3', 1024, 'audio/mpeg');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/media/upload', [
                'file' => $file,
            ])
            ->assertCreated();

        $path = $response->json('data.path');
        Storage::disk('media')->assertExists($path);
    }

    // ── Invalid uploads ───────────────────────────────────────

    public function test_upload_rejects_invalid_mime_type(): void
    {
        Storage::fake('media');

        $file = UploadedFile::fake()->create('script.exe', 512, 'application/x-msdownload');

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/media/upload', [
                'file' => $file,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_rejects_oversized_file(): void
    {
        Storage::fake('media');

        // Simulate a file larger than the max allowed (e.g., 10MB + 1KB)
        $file = UploadedFile::fake()->create('large.jpg', 10241, 'image/jpeg');

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/media/upload', [
                'file' => $file,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_rejects_missing_file(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/media/upload', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_rejects_non_file_input(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/media/upload', [
                'file' => 'not-a-file',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    // ── Path traversal prevention ──────────────────────────────

    public function test_delete_rejects_path_traversal(): void
    {
        // Path traversal attempts should be sanitized/rejected
        $this->actingAs($this->adminUser)
            ->deleteJson('/api/v1/admin/media/..%2F..%2F..%2F.env')
            ->assertStatus(400); // or 404/422 depending on implementation
    }

    // ── Delete media ───────────────────────────────────────────

    public function test_admin_can_delete_uploaded_media(): void
    {
        Storage::fake('media');
        $disk = Storage::disk('media');

        // Upload a file first
        $file = UploadedFile::fake()->create('delete-me.jpg', 200, 'image/jpeg');
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/media/upload', ['file' => $file])
            ->assertCreated();

        $path = $response->json('data.path');
        $disk->assertExists($path);

        // Now delete it
        $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/media/{$path}")
            ->assertNoContent();

        $disk->assertMissing($path);
    }

    // ── Filename sanitization ──────────────────────────────────

    public function test_upload_sanitizes_filenames(): void
    {
        Storage::fake('media');

        $file = UploadedFile::fake()->create('my photo (copy).png', 100, 'image/png');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/media/upload', [
                'file' => $file,
            ])
            ->assertCreated();

        $path = $response->json('data.path');
        // Filename should not contain spaces or special chars in stored path
        $this->assertStringNotContainsString(' ', $path);
        $this->assertStringNotContainsString('(', $path);
    }

    // ── Valid MIME variations ─────────────────────────────────

    public function test_upload_accepts_common_image_types(): void
    {
        Storage::fake('media');

        $mimes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        foreach (['jpg', 'png', 'gif', 'webp'] as $ext) {
            $file = UploadedFile::fake()->create("test.{$ext}", 100, $mimes[$ext]);

            $this->actingAs($this->adminUser)
                ->postJson('/api/v1/admin/media/upload', ['file' => $file])
                ->assertCreated();
        }
    }
}
