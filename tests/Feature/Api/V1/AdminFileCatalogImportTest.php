<?php

namespace Tests\Feature\Api\V1;

use App\Models\AdminImportRun;
use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminFileCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'course_title,course_slug,unit_title,lesson_title,lesson_content,word,meaning,pronunciation,part_of_speech,example';

    public function test_upload_is_disabled_by_default(): void
    {
        $this->seed();
        $admin = $this->user('admin');

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports/file', ['file' => $this->csv($this->validCsv())])
            ->assertStatus(503);
    }

    public function test_non_admin_cannot_upload(): void
    {
        $this->seed();
        config()->set('features.file_catalog_import', true);

        $this->actingAs($this->user('learner'))->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports/file', ['file' => $this->csv($this->validCsv())])
            ->assertForbidden();
    }

    public function test_happy_path_csv_upload_stages_the_full_tree_and_apply_creates_it(): void
    {
        $this->seed();
        config()->set('features.file_catalog_import', true);
        $admin = $this->user('admin');
        $superAdmin = $this->user('super_admin');

        $upload = $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports/file', ['file' => $this->csv($this->validCsv())])
            ->assertStatus(201)
            ->assertJsonPath('data.run.entity', 'file_catalog')
            ->assertJsonPath('data.run.status', 'review-ready')
            ->assertJsonPath('data.staged_items.data.0.classification', 'new')
            ->assertJsonPath('data.staged_items.data.0.incoming_snapshot.title', 'Everyday English');
        $runId = $upload->json('data.run.id');

        // Review: list runs + items scoped to file_catalog only.
        $this->actingAs($admin)->getJson('/api/v1/admin/imports/file/runs')
            ->assertOk()->assertJsonCount(1, 'data');
        $items = $this->actingAs($admin)->getJson("/api/v1/admin/imports/file/runs/{$runId}/items")
            ->assertOk()->assertJsonCount(1, 'data');
        $itemId = $items->json('data.0.id');

        $apply = $this->actingAs($superAdmin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/file/runs/{$runId}/apply", [])
            ->assertOk()
            ->assertJsonPath('data.applied', [$itemId])
            ->assertJsonPath('data.failed', []);

        $course = Course::query()->where('slug', 'everyday-english')->firstOrFail();
        $this->assertSame('Everyday English', $course->title);
        $this->assertSame(1, $course->units()->count());
        $unit = $course->units()->firstOrFail();
        $this->assertSame('Greetings', $unit->title);
        $this->assertSame(1, $unit->lessons()->count());
        $lesson = $unit->lessons()->firstOrFail();
        $this->assertSame('Saying Hello', $lesson->title);
        $this->assertSame(2, $lesson->vocabularies()->count());
        $this->assertDatabaseHas('vocabularies', ['lesson_id' => $lesson->id, 'word' => 'hello', 'meaning' => 'a greeting']);
        $this->assertDatabaseHas('vocabularies', ['lesson_id' => $lesson->id, 'word' => 'goodbye']);
        $this->assertDatabaseHas('admin_import_runs', ['id' => $runId, 'status' => 'approved']);
    }

    public function test_a_malformed_row_is_reported_and_excluded_without_failing_the_whole_file(): void
    {
        $this->seed();
        config()->set('features.file_catalog_import', true);
        $admin = $this->user('admin');

        $csv = self::HEADER."\n"
            .'Course A,,Unit A,,Some content,word1,meaning1,,,'."\n" // missing lesson_title
            .'Course A,,Unit A,Lesson A,Real content,word2,meaning2,,,'."\n";

        $upload = $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports/file', ['file' => $this->csv($csv)])
            ->assertStatus(201);
        $runId = $upload->json('data.run.id');

        $items = $this->actingAs($admin)->getJson("/api/v1/admin/imports/file/runs/{$runId}/items")
            ->assertOk()->assertJsonCount(1, 'data');
        $item = $items->json('data.0');
        $this->assertSame('new', $item['classification']);
        $this->assertNotEmpty($item['errors']);
        $this->assertStringContainsString('lesson_title', $item['errors'][0]);
        // The bad row is dropped but the valid one still made it into the tree.
        $this->assertCount(1, $item['incoming_snapshot']['units'][0]['lessons']);
        $this->assertSame('Lesson A', $item['incoming_snapshot']['units'][0]['lessons'][0]['title']);
    }

    public function test_a_header_that_does_not_match_the_template_is_rejected(): void
    {
        $this->seed();
        config()->set('features.file_catalog_import', true);
        $admin = $this->user('admin');

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports/file', ['file' => $this->csv("wrong,header\nfoo,bar")])
            ->assertStatus(422);
    }

    public function test_apply_is_idempotent(): void
    {
        $this->seed();
        config()->set('features.file_catalog_import', true);
        $admin = $this->user('admin');
        $superAdmin = $this->user('super_admin');

        $upload = $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports/file', ['file' => $this->csv($this->validCsv())])
            ->assertStatus(201);
        $runId = $upload->json('data.run.id');

        $this->actingAs($superAdmin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/file/runs/{$runId}/apply", [])
            ->assertOk()->assertJsonCount(1, 'data.applied');

        $this->actingAs($superAdmin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/file/runs/{$runId}/apply", [])
            ->assertOk()
            ->assertJsonPath('data.applied', [])
            ->assertJsonPath('data.failed', []);

        $this->assertSame(1, Course::query()->where('slug', 'everyday-english')->count());
    }

    public function test_apply_requires_the_gate_and_a_recent_google_step_up(): void
    {
        $this->seed();
        config()->set('features.file_catalog_import', true);
        $admin = $this->user('admin');
        $superAdmin = $this->user('super_admin');

        $upload = $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports/file', ['file' => $this->csv($this->validCsv())])
            ->assertStatus(201);
        $runId = $upload->json('data.run.id');

        // Admin can stage but not apply.
        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/file/runs/{$runId}/apply", [])
            ->assertForbidden();

        // Super admin without a recent Google step-up gets a 428, not a silent write.
        $this->actingAs($superAdmin);
        session()->put('google_admin_reauthenticated', [
            'user_id' => $superAdmin->id,
            'subject' => $superAdmin->google_id,
            'session_id' => session()->getId(),
            'at' => now()->subMinutes(16)->timestamp,
        ]);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/file/runs/{$runId}/apply", [])
            ->assertStatus(428);

        $this->assertDatabaseMissing('courses', ['slug' => 'everyday-english']);
    }

    public function test_apply_reports_a_slug_collision_without_touching_other_items(): void
    {
        $this->seed();
        config()->set('features.file_catalog_import', true);
        $admin = $this->user('admin');
        $superAdmin = $this->user('super_admin');
        Course::create(['title' => 'Existing', 'slug' => 'everyday-english', 'status' => 'draft']);

        $upload = $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports/file', ['file' => $this->csv($this->validCsv())])
            ->assertStatus(201);
        $runId = $upload->json('data.run.id');

        $this->actingAs($superAdmin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/file/runs/{$runId}/apply", [])
            ->assertOk()
            ->assertJsonPath('data.applied', [])
            ->assertJsonCount(1, 'data.failed');

        // Only the pre-existing course owns that slug; the import did not overwrite it.
        $this->assertSame(1, Course::query()->where('slug', 'everyday-english')->count());
        $this->assertDatabaseHas('courses', ['slug' => 'everyday-english', 'title' => 'Existing']);
    }

    private function validCsv(): string
    {
        return self::HEADER."\n"
            .'Everyday English,,Greetings,Saying Hello,Learn common greetings.,hello,a greeting,,interjection,Hello!'."\n"
            .'Everyday English,,Greetings,Saying Hello,,goodbye,a farewell,,interjection,Bye!'."\n";
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::query()->where('slug', $role)->value('id')]);
    }
}
