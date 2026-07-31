<?php

namespace Tests\Feature\Api\V1;

use App\Models\AdminImportItem;
use App\Models\AdminImportRun;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\OperationsAudit;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Support\CatalogFingerprint;
use App\Support\RecentGoogleAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminImportApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config()->set('features.lexilingo_import_apply', true);
    }

    public function test_history_show_items_and_explicit_import_policies(): void
    {
        $admin = $this->user('admin');
        $run = $this->importRun($admin);
        $item = $this->item($run, 'new', 'add', ['name' => 'History', 'slug' => 'history']);

        $this->actingAs($admin)->getJson('/api/v1/admin/imports/runs')
            ->assertOk()->assertJsonPath('data.0.id', $run->id)
            ->assertJsonPath('data.0.items_count', 1);
        $this->getJson("/api/v1/admin/imports/runs/{$run->id}")
            ->assertOk()->assertJsonPath('data.counts.new', 1);
        $this->getJson("/api/v1/admin/imports/runs/{$run->id}/items")
            ->assertOk()->assertJsonPath('data.0.id', $item->id);

        foreach (['review-imports', 'approve-imports', 'apply-imports', 'replace-import-upstream', 'cancel-imports'] as $ability) {
            $this->assertTrue(Gate::forUser($admin)->allows($ability), $ability);
        }
        $this->assertFalse(Gate::forUser($admin)->allows('replace-import-conflict'));
    }

    public function test_recent_google_freshness_is_bound_to_session_user_and_subject(): void
    {
        $admin = $this->user('admin');
        $this->startSession();
        $session = $this->app['session']->driver();
        $session->put('google_admin_reauthenticated', [
            'user_id' => $admin->id,
            'subject' => $admin->google_id,
            'session_id' => $session->getId(),
            'at' => now()->timestamp,
        ]);
        $request = Request::create('/api/v1/admin/imports');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $admin);
        $recent = app(RecentGoogleAdmin::class);
        $recent->require($request);

        $session->migrate(true);
        try {
            $recent->require($request);
            $this->fail('Rotated session reused Google freshness.');
        } catch (HttpException $error) {
            $this->assertSame(428, $error->getStatusCode());
        }
        $this->assertFalse($session->has('google_admin_reauthenticated'));
    }

    public function test_rotated_google_session_is_rejected_by_real_quota_endpoint(): void
    {
        $super = $this->user('super_admin');
        $this->actingAs($super);
        $session = $this->app['session']->driver();
        $session->migrate(true);
        $this->withCookie((string) config('session.cookie'), $session->getId())->withCredentials();

        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson('/api/v1/admin/operations/quota-policy', [
                'limits' => ['translate_daily' => 10],
            ])->assertStatus(428);
        $this->assertFalse($session->has('google_admin_reauthenticated'));
    }

    public function test_draft_exclusion_cascades_to_children_and_invalid_selection_blocks_approval(): void
    {
        $admin = $this->user('admin');
        $run = $this->importRun($admin, entity: 'courses');
        $course = $this->item($run, 'new', 'add', [
            'title' => 'Course', 'slug' => 'course', 'category_external_id' => null,
            'level' => null,
        ], entity: 'course', externalId: 'course-1');
        $unit = $this->item($run, 'new', 'add', [
            'title' => 'Unit', 'course_external_id' => 'course-1',
        ], entity: 'unit', externalId: 'unit-1', parent: $course);

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/imports/runs/{$run->id}/draft", [
                'items' => [['id' => $course->id, 'action' => 'exclude']],
            ])->assertOk();
        $this->assertSame('exclude', $unit->fresh()->selected_action);

        $invalidRun = $this->importRun($admin);
        $this->item($invalidRun, 'invalid', 'add', ['name' => 'Bad']);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$invalidRun->id}/approve")
            ->assertStatus(422);
    }

    public function test_draft_replay_binds_payload_and_keeps_child_when_excluded_parent_exists_locally(): void
    {
        $admin = $this->user('admin');
        $existing = Course::syncFromSource('course', 'lexilingo', 'existing-course', [
            'category_external_id' => null,
            'level' => null,
            'title' => 'Existing',
            'slug' => 'existing',
            'description' => null,
            'language' => 'en',
            'thumbnail_url' => null,
            'estimated_duration' => 1,
            'total_xp' => 1,
        ])[0];
        $run = $this->importRun($admin, entity: 'courses');
        $course = $this->item($run, 'exact_duplicate', 'skip', $existing->source_snapshot,
            entity: 'course', externalId: 'existing-course', existing: $existing);
        $unit = $this->item($run, 'new', 'add', [
            'course_external_id' => 'existing-course',
            'title' => 'Child',
        ], entity: 'unit', externalId: 'child-unit', parent: $course, dependencies: [[
            'entity' => 'course', 'external_id' => 'existing-course',
        ]]);
        $requestId = (string) Str::uuid();

        $this->actingAs($admin)->withHeader('X-Request-ID', $requestId)
            ->putJson("/api/v1/admin/imports/runs/{$run->id}/draft", [
                'items' => [['id' => $course->id, 'action' => 'exclude']],
            ])->assertOk();
        $this->assertSame('add', $unit->fresh()->selected_action);

        $this->withHeader('X-Request-ID', $requestId)
            ->putJson("/api/v1/admin/imports/runs/{$run->id}/draft", [
                'items' => [['id' => $course->id, 'action' => 'skip']],
            ])->assertConflict();
        $this->assertSame('exclude', $course->fresh()->selected_action);
    }

    public function test_new_item_applies_without_step_up_and_advances_checkpoint_after_commit(): void
    {
        $admin = $this->user('admin');
        $run = $this->importRun($admin);
        $this->item($run, 'new', 'add', ['name' => 'Local first', 'slug' => 'local-first']);
        $duplicate = CourseCategory::syncFromSource('category', 'lexilingo', 'category-duplicate', [
            'name' => 'Duplicate', 'slug' => 'duplicate',
        ])[0];
        $duplicateItem = $this->item($run, 'exact_duplicate', 'skip', $duplicate->source_snapshot,
            externalId: 'category-duplicate', existing: $duplicate);

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');
        session()->forget('google_admin_reauthenticated');
        $requestId = (string) Str::uuid();
        $this->withHeader('X-Request-ID', $requestId)
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply")
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->withHeader('X-Request-ID', $requestId)
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply")
            ->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('course_categories', [
            'external_id' => 'category-1',
            'name' => 'Local first',
        ]);
        $this->assertDatabaseHas('lexilingo_import_checkpoints', [
            'entity' => 'categories',
            'cursor' => 1,
        ]);
        $audit = OperationsAudit::query()->where('request_id', $requestId)->firstOrFail();
        $this->assertContains([
            'id' => $duplicateItem->id,
            'action' => 'skip',
        ], $audit->context['selected']);
        $this->assertArrayHasKey((string) $duplicateItem->id, $audit->after_state['fingerprints']);
    }

    public function test_apply_is_disabled_by_default(): void
    {
        config()->set('features.lexilingo_import_apply', false);
        $admin = $this->user('admin');
        $run = $this->importRun($admin, status: 'approved');
        $this->item($run, 'new', 'add', ['name' => 'Disabled', 'slug' => 'disabled']);

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply")
            ->assertStatus(503);
        $this->assertDatabaseMissing('course_categories', ['external_id' => 'category-1']);
    }

    public function test_upstream_replace_requires_subject_bound_recent_google_auth(): void
    {
        $admin = $this->user('admin');
        $existing = CourseCategory::syncFromSource('category', 'lexilingo', 'category-1', [
            'name' => 'Before', 'slug' => 'before',
        ])[0];
        $run = $this->importRun($admin, status: 'approved');
        $this->item($run, 'upstream_update', 'replace', [
            'name' => 'After', 'slug' => 'after',
        ], existing: $existing);

        $this->actingAs($admin);
        session()->put('google_admin_reauthenticated.subject', 'wrong-subject');
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply")
            ->assertStatus(428);

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply")
            ->assertOk();
        $this->assertSame('After', $existing->fresh()->name);
    }

    public function test_local_conflict_replace_requires_super_admin_and_fresh_google_auth(): void
    {
        $admin = $this->user('admin');
        $existing = CourseCategory::syncFromSource('category', 'lexilingo', 'category-1', [
            'name' => 'Before', 'slug' => 'before',
        ])[0];
        $existing->applyLocalEdit(['name' => 'Local']);
        $run = $this->importRun($admin, status: 'approved');
        $this->item($run, 'local_conflict', 'replace', [
            'name' => 'Remote replacement', 'slug' => 'before',
        ], existing: $existing);

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply")
            ->assertForbidden();

        $super = $this->user('super_admin');
        $this->actingAs($super)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply")
            ->assertOk();
        $this->assertSame('Remote replacement', $existing->fresh()->name);
        $this->assertNull($existing->fresh()->local_override_at);
    }

    public function test_stale_revision_rolls_back_and_new_identity_race_reclassifies_without_unique_error(): void
    {
        $admin = $this->user('admin');
        $existing = CourseCategory::syncFromSource('category', 'lexilingo', 'category-1', [
            'name' => 'Before', 'slug' => 'before',
        ])[0];
        $run = $this->importRun($admin, status: 'approved');
        $item = $this->item($run, 'upstream_update', 'replace', [
            'name' => 'Reviewed', 'slug' => 'reviewed',
        ], existing: $existing);
        CourseCategory::syncFromSource('category', 'lexilingo', 'category-1', [
            'name' => 'Intervening', 'slug' => 'intervening',
        ]);

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply")
            ->assertConflict();
        $this->assertSame('review_ready', $run->fresh()->status);
        $this->assertSame('Intervening', $existing->fresh()->name);
        $this->assertSame($existing->fresh()->catalog_revision, $item->fresh()->base_revision);

        $race = $this->importRun($admin, status: 'approved');
        $raceItem = $this->item($race, 'new', 'add', ['name' => 'Candidate', 'slug' => 'candidate']);
        CourseCategory::syncFromSource('category', 'lexilingo', 'category-1', [
            'name' => 'Appeared', 'slug' => 'appeared',
        ]);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$race->id}/apply")
            ->assertConflict();
        $this->assertSame('upstream_update', $raceItem->fresh()->classification);
        $this->assertSame('review_ready', $race->fresh()->status);
        $this->assertDatabaseMissing('lexilingo_import_checkpoints', ['entity' => 'categories']);
    }

    public function test_deleted_exact_duplicate_is_reclassified_instead_of_advancing_checkpoint(): void
    {
        $admin = $this->user('admin');
        $existing = CourseCategory::syncFromSource('category', 'lexilingo', 'category-1', [
            'name' => 'Duplicate', 'slug' => 'duplicate',
        ])[0];
        $run = $this->importRun($admin, status: 'approved');
        $item = $this->item($run, 'exact_duplicate', 'skip', $existing->source_snapshot, existing: $existing);
        $existing->delete();

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply")
            ->assertConflict();

        $this->assertSame('new', $item->fresh()->classification);
        $this->assertSame('add', $item->fresh()->selected_action);
        $this->assertSame('review_ready', $run->fresh()->status);
        $this->assertDatabaseMissing('lexilingo_import_checkpoints', ['entity' => 'categories']);
    }

    public function test_admin_can_cancel_and_only_fresh_super_admin_can_retry(): void
    {
        $admin = $this->user('admin');
        $run = $this->importRun($admin);
        $this->item($run, 'new', 'add', ['name' => 'Cancel', 'slug' => 'cancel']);
        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $super = $this->user('super_admin');
        $this->actingAs($super);
        session()->put('google_admin_reauthenticated.at', now()->subMinutes(16)->timestamp);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/retry")
            ->assertStatus(428);

        config()->set('features.lexilingo_import', true);
        config()->set('services.lexilingo.backend_url', 'https://backend.lexilingo.test');
        config()->set('services.lexilingo.partner_api_key', 'partner-secret');
        Http::fake(['backend.lexilingo.test/*' => Http::response([
            'data' => [[
                'id' => 'retry-category', 'name' => 'Retry', 'slug' => 'retry',
                'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
            ]],
            'meta' => [],
        ])]);
        $retryRequestId = (string) Str::uuid();
        $this->actingAs($super)->withHeader('X-Request-ID', $retryRequestId)
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/retry")
            ->assertStatus(202)->assertJsonPath('data.status', 'review_ready');
        $retryAudit = OperationsAudit::query()->where('action', 'content_import.retried')->firstOrFail();
        $this->assertSame($run->id, $retryAudit->context['source_run_id']);

        $other = $this->importRun($super, status: 'cancelled');
        $this->withHeader('X-Request-ID', $retryRequestId)
            ->postJson("/api/v1/admin/imports/runs/{$other->id}/retry")
            ->assertConflict();
    }

    public function test_apply_failure_rolls_back_catalog_and_checkpoint_and_keeps_draft(): void
    {
        $admin = $this->user('admin');
        $run = $this->importRun($admin, status: 'approved');
        $this->item($run, 'new', 'add', ['name' => 'Transient', 'slug' => 'transient']);
        $failOnce = true;
        CourseCategory::creating(function () use (&$failOnce): void {
            if ($failOnce) {
                $failOnce = false;
                throw new \RuntimeException('Transient catalog failure.');
            }
        });

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply")
            ->assertStatus(500);

        $this->assertSame('apply_failed', $run->fresh()->status);
        $this->assertDatabaseMissing('course_categories', ['external_id' => 'category-1']);
        $this->assertDatabaseMissing('lexilingo_import_checkpoints', ['entity' => 'categories']);
        $this->assertDatabaseHas('admin_import_items', [
            'admin_import_run_id' => $run->id,
            'selected_action' => 'add',
            'applied_at' => null,
        ]);
        $item = $run->items()->firstOrFail();
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/imports/runs/{$run->id}/draft", [
                'items' => [['id' => $item->id, 'action' => 'add']],
            ])->assertOk()->assertJsonPath('data.status', 'review_ready')
            ->assertJsonPath('data.error_code', null);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/approve")
            ->assertOk();
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply")
            ->assertOk()->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.error_message', null);
    }

    public function test_dependency_order_applies_course_unit_and_lesson_relationships(): void
    {
        $admin = $this->user('admin');
        $category = CourseCategory::syncFromSource('category', 'lexilingo', 'category-parent', [
            'name' => 'Parent', 'slug' => 'parent',
        ])[0];
        $run = $this->importRun($admin, status: 'approved', entity: 'courses');
        $course = $this->item($run, 'new', 'add', [
            'category_external_id' => $category->external_id,
            'level' => null,
            'title' => 'Applied course',
            'slug' => 'applied-course',
            'description' => null,
            'language' => 'en',
            'thumbnail_url' => null,
            'estimated_duration' => 10,
            'total_xp' => 20,
        ], entity: 'course', externalId: 'course-applied', dependencies: [[
            'entity' => 'category', 'external_id' => $category->external_id,
        ]]);
        $unit = $this->item($run, 'new', 'add', [
            'course_external_id' => 'course-applied',
            'title' => 'Applied unit',
            'description' => null,
            'sort_order' => 1,
            'icon_url' => null,
            'background_color' => null,
        ], entity: 'unit', externalId: 'unit-applied', parent: $course, dependencies: [[
            'entity' => 'course', 'external_id' => 'course-applied',
        ]]);
        $this->item($run, 'new', 'add', [
            'unit_external_id' => 'unit-applied',
            'title' => 'Applied lesson',
            'slug' => 'applied-lesson',
            'sort_order' => 1,
            'lesson_type' => 'vocabulary',
            'xp_reward' => 10,
        ], entity: 'lesson', externalId: 'lesson-applied', parent: $unit, dependencies: [[
            'entity' => 'unit', 'external_id' => 'unit-applied',
        ]]);

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply")
            ->assertOk();

        $courseModel = Course::query()->where('external_id', 'course-applied')->firstOrFail();
        $unitModel = Unit::query()->where('external_id', 'unit-applied')->firstOrFail();
        $lesson = Lesson::query()->where('external_id', 'lesson-applied')->firstOrFail();
        $this->assertSame($category->id, $courseModel->category_id);
        $this->assertSame($courseModel->id, $unitModel->course_id);
        $this->assertSame($unitModel->id, $lesson->unit_id);
        $this->assertSame($courseModel->id, $lesson->course_id);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('slug', $role)->value('id'),
            'google_id' => 'google-'.Str::uuid(),
            'auth_provider' => 'google',
        ]);
    }

    private function importRun(User $actor, string $status = 'review_ready', string $entity = 'categories'): AdminImportRun
    {
        return AdminImportRun::create([
            'request_id' => (string) Str::uuid(),
            'entity' => $entity,
            'payload_fingerprint' => hash('sha256', (string) Str::uuid()),
            'actor_id' => $actor->id,
            'status' => $status,
            'requested_limit' => 10,
            'starting_cursor' => 0,
            'result_cursor' => 1,
        ]);
    }

    private function item(
        AdminImportRun $run,
        string $classification,
        string $action,
        array $candidate,
        string $entity = 'category',
        string $externalId = 'category-1',
        ?AdminImportItem $parent = null,
        ?Model $existing = null,
        array $dependencies = [],
    ): AdminImportItem {
        return AdminImportItem::create([
            'admin_import_run_id' => $run->id,
            'parent_item_id' => $parent?->id,
            'entity' => $entity,
            'source_system' => 'lexilingo',
            'external_id' => $externalId,
            'natural_key' => $candidate['slug'] ?? null,
            'normalized_fingerprint' => CatalogFingerprint::make($entity, $candidate),
            'candidate_payload' => $candidate,
            'target_id' => $existing?->id,
            'base_revision' => $existing?->catalog_revision,
            'base_fingerprint' => $existing?->source_fingerprint,
            'base_snapshot' => $existing?->source_snapshot,
            'base_override_at' => $existing?->local_override_at,
            'classification' => $classification,
            'selected_action' => $action,
            'dependencies' => $dependencies,
            'validation_errors' => $classification === 'invalid' ? ['invalid'] : [],
        ]);
    }
}
