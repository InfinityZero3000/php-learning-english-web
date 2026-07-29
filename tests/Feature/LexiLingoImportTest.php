<?php

namespace Tests\Feature;

use App\Jobs\RunAdminImport;
use App\Models\AdminImportLock;
use App\Models\AdminImportRun;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\LexiLingoImportCheckpoint;
use App\Models\LexiLingoImportFailure;
use App\Models\OperationsAudit;
use App\Models\Unit;
use App\Models\User;
use App\Services\AdminImportRunner;
use App\Services\Import\CategoryImporter;
use App\Services\Import\CourseImporter;
use App\Services\LexiLingoVocabularySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LexiLingoImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.lexilingo.backend_url', 'https://backend.lexilingo.test');
        config()->set('services.lexilingo.partner_api_key', 'partner-secret');
        config()->set('cache.stores.redis', ['driver' => 'array']);
        config()->set('features.lexilingo_import_apply', true);
    }

    public function test_valid_category_payload_is_upserted_and_rerun_is_idempotent(): void
    {
        Http::fake([
            'backend.lexilingo.test/*' => Http::response([[
                'id' => 'cat-1',
                'name' => 'Business English',
                'slug' => 'business-english',
                'description' => 'Business focused content',
                'icon' => 'briefcase',
                'color' => '#123456',
                'course_count' => 2,
            ]]),
        ]);

        $importer = $this->app->make(CategoryImporter::class);

        $importer->import(limit: 50, reset: true);
        $importer->import(limit: 50, reset: true);

        $this->assertDatabaseCount('course_categories', 1);
        $this->assertDatabaseHas('course_categories', [
            'source_system' => 'lexilingo',
            'external_id' => 'cat-1',
            'name' => 'Business English',
            'slug' => 'business-english',
            'catalog_revision' => 1,
        ]);
    }

    public function test_changed_upstream_category_updates_until_a_local_override_exists(): void
    {
        $payload = fn (string $name): array => [[
            'id' => 'cat-owned', 'name' => $name, 'slug' => 'owned',
            'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
        ]];
        Http::fake(['backend.lexilingo.test/*' => Http::sequence()
            ->push($payload('Remote one'))
            ->push($payload('Remote two'))
            ->push($payload('Remote three'))]);
        $importer = $this->app->make(CategoryImporter::class);
        $importer->import(10, reset: true);

        $importer->import(10, reset: true);
        $category = CourseCategory::query()->where('external_id', 'cat-owned')->firstOrFail();
        $this->assertSame('Remote two', $category->name);
        $this->assertSame(2, $category->catalog_revision);

        $category->applyLocalEdit(['name' => 'Local wins']);
        $importer->import(10, reset: true);

        $category->refresh();
        $this->assertSame('Local wins', $category->name);
        $this->assertSame(3, $category->catalog_revision);
    }

    public function test_invalid_category_is_archived_and_valid_sibling_still_imports(): void
    {
        Http::fake([
            'backend.lexilingo.test/*' => Http::response([
                [
                    'id' => 'cat-1',
                    'name' => 'Business English',
                    'slug' => 'business-english',
                    'description' => null,
                    'icon' => null,
                    'color' => null,
                    'course_count' => 0,
                ],
                [
                    'id' => 'cat-broken',
                    'name' => 'Missing Slug',
                    'description' => null,
                    'icon' => null,
                    'color' => null,
                    'course_count' => 0,
                ],
            ]),
        ]);

        $result = $this->app->make(CategoryImporter::class)->import(limit: 50);

        $this->assertSame(1, $result->processed);
        $this->assertSame(1, $result->skipped);
        $this->assertDatabaseHas('course_categories', ['external_id' => 'cat-1']);
        $this->assertDatabaseMissing('course_categories', ['external_id' => 'cat-broken']);
        $this->assertDatabaseHas('lexilingo_import_failures', [
            'entity' => 'categories',
            'external_id' => 'cat-broken',
        ]);

        $failure = LexiLingoImportFailure::query()->where('external_id', 'cat-broken')->firstOrFail();
        $this->assertSame('Missing Slug', $failure->payload['name']);
    }

    public function test_course_import_creates_nested_units_and_lessons(): void
    {
        Level::create(['name' => 'Beginner', 'slug' => 'beginner', 'sort_order' => 1]);

        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/lessons/*/content' => Http::response([], 404),
            'backend.lexilingo.test/api/v1/integrations/courses/course-1' => Http::response(['data' => [
                'id' => 'course-1',
                'units' => [[
                    'id' => 'unit-1',
                    'title' => 'Unit One',
                    'description' => 'first unit',
                    'order_index' => 1,
                    'background_color' => '#fff',
                    'icon_url' => null,
                    'lessons' => [
                        ['id' => 'lesson-1', 'title' => 'Lesson One', 'order_index' => 1, 'lesson_type' => 'vocabulary', 'xp_reward' => 10],
                        ['id' => 'lesson-2', 'title' => 'Lesson Two', 'order_index' => 2, 'lesson_type' => 'grammar', 'xp_reward' => 15],
                    ],
                ]],
            ]]),
            'backend.lexilingo.test/api/v1/integrations/courses*' => Http::response([[
                'id' => 'course-1',
                'title' => 'English Basics',
                'description' => 'Intro course',
                'language' => 'en',
                'level' => 'beginner',
                'tags' => ['grammar'],
                'thumbnail_url' => null,
                'total_lessons' => 2,
                'total_xp' => 100,
                'estimated_duration' => 60,
            ]]),
        ]);

        $result = $this->app->make(CourseImporter::class)->import(limit: 50);

        $this->assertSame(1, $result->processed);
        $this->assertDatabaseHas('courses', ['external_id' => 'course-1', 'title' => 'English Basics']);
        $this->assertDatabaseHas('units', ['source_system' => 'lexilingo', 'external_id' => 'unit-1', 'title' => 'Unit One']);
        $this->assertDatabaseHas('lessons', ['external_id' => 'lesson-1', 'title' => 'Lesson One']);
        $this->assertDatabaseHas('lessons', ['external_id' => 'lesson-2', 'title' => 'Lesson Two']);
        $this->assertSame(1, Unit::query()->count());
        $this->assertSame(2, Lesson::query()->count());
    }

    public function test_course_reimport_does_not_demote_published_legacy_outline(): void
    {
        $level = Level::create(['name' => 'Beginner', 'slug' => 'beginner', 'sort_order' => 1]);
        $course = Course::create([
            'level_id' => $level->id, 'source_system' => 'lexilingo', 'external_id' => 'course-published',
            'title' => 'Published', 'slug' => 'published-legacy', 'status' => 'published', 'language' => 'en',
        ]);
        $unit = Unit::create([
            'course_id' => $course->id, 'source_system' => 'lexilingo', 'external_id' => 'unit-published',
            'title' => 'Published unit', 'sort_order' => 1, 'status' => 'published',
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'unit_id' => $unit->id, 'source_system' => 'lexilingo',
            'external_id' => 'lesson-published', 'title' => 'Published lesson', 'slug' => 'published-lesson',
            'sort_order' => 1, 'status' => 'published', 'lesson_type' => 'vocabulary', 'xp_reward' => 10,
        ]);
        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/courses/course-published' => Http::response(['data' => ['units' => [[
                'id' => 'unit-published', 'title' => 'Published unit', 'description' => null, 'order_index' => 1,
                'background_color' => null, 'icon_url' => null, 'lessons' => [[
                    'id' => 'lesson-published', 'title' => 'Published lesson', 'order_index' => 1,
                    'lesson_type' => 'vocabulary', 'xp_reward' => 10,
                ]],
            ]]]]),
            'backend.lexilingo.test/api/v1/integrations/courses*' => Http::response([[
                'id' => 'course-published', 'title' => 'Published', 'description' => null, 'language' => 'en',
                'level' => 'beginner', 'tags' => [], 'thumbnail_url' => null, 'total_lessons' => 1,
                'total_xp' => 10, 'estimated_duration' => 10,
            ]]),
        ]);

        $this->app->make(CourseImporter::class)->import(10, reset: true);

        $this->assertSame('published', $course->fresh()->status);
        $this->assertSame('published', $unit->fresh()->status);
        $this->assertSame('published', $lesson->fresh()->status);
    }

    public function test_course_transaction_rolls_back_only_the_failing_course(): void
    {
        Level::create(['name' => 'Beginner', 'slug' => 'beginner', 'sort_order' => 1]);

        $courseFixture = fn (string $id, string $title) => [
            'id' => $id,
            'title' => $title,
            'description' => null,
            'language' => 'en',
            'level' => 'beginner',
            'tags' => [],
            'thumbnail_url' => null,
            'total_lessons' => 1,
            'total_xp' => 10,
            'estimated_duration' => 10,
        ];

        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/lessons/*/content' => Http::response([], 404),
            'backend.lexilingo.test/api/v1/integrations/courses/course-good' => Http::response(['data' => [
                'id' => 'course-good',
                'units' => [[
                    'id' => 'unit-good',
                    'title' => 'Good Unit',
                    'description' => null,
                    'order_index' => 1,
                    'background_color' => null,
                    'icon_url' => null,
                    'lessons' => [
                        ['id' => 'lesson-good', 'title' => 'Good Lesson', 'order_index' => 1, 'lesson_type' => 'vocabulary', 'xp_reward' => 5],
                    ],
                ]],
            ]]),
            // Two units sharing order_index=1 violates the units.(course_id, sort_order)
            // unique constraint on the second insert, forcing a real QueryException.
            'backend.lexilingo.test/api/v1/integrations/courses/course-bad' => Http::response(['data' => [
                'id' => 'course-bad',
                'units' => [
                    [
                        'id' => 'unit-bad-1',
                        'title' => 'Bad Unit One',
                        'description' => null,
                        'order_index' => 1,
                        'background_color' => null,
                        'icon_url' => null,
                        'lessons' => [],
                    ],
                    [
                        'id' => 'unit-bad-2',
                        'title' => 'Bad Unit Two',
                        'description' => null,
                        'order_index' => 1,
                        'background_color' => null,
                        'icon_url' => null,
                        'lessons' => [],
                    ],
                ],
            ]]),
            'backend.lexilingo.test/api/v1/integrations/courses*' => Http::response([
                $courseFixture('course-good', 'Good Course'),
                $courseFixture('course-bad', 'Bad Course'),
            ]),
        ]);

        $result = $this->app->make(CourseImporter::class)->import(limit: 50);

        $this->assertSame(1, $result->processed);
        $this->assertSame(1, $result->skipped);

        // The good course's transaction committed in full.
        $this->assertDatabaseHas('courses', ['external_id' => 'course-good']);
        $this->assertDatabaseHas('units', ['external_id' => 'unit-good']);
        $this->assertDatabaseHas('lessons', ['external_id' => 'lesson-good']);

        // The bad course's transaction rolled back completely -- including
        // the course row and the first (otherwise valid) unit.
        $this->assertDatabaseMissing('courses', ['external_id' => 'course-bad']);
        $this->assertDatabaseMissing('units', ['external_id' => 'unit-bad-1']);
        $this->assertDatabaseMissing('units', ['external_id' => 'unit-bad-2']);
    }

    public function test_vocabulary_dry_run_validates_without_writing(): void
    {
        Http::fake([
            'backend.lexilingo.test/*' => Http::response([[
                'id' => 'lexi-word-1',
                'course_id' => null,
                'lesson_id' => null,
                'word' => 'hello',
                'definition' => 'a greeting',
                'translation' => ['vi' => 'xin chào'],
                'pronunciation' => null,
                'audio_url' => null,
                'part_of_speech' => 'interjection',
                'difficulty_level' => 'beginner',
                'tags' => ['greeting'],
                'usage_frequency' => 10,
            ]]),
        ]);

        $sync = $this->app->make(LexiLingoVocabularySync::class);
        $result = $sync->import(limit: 50, dryRun: true);

        Http::assertSentCount(1);
        $this->assertSame(1, $result->processed);
        $this->assertDatabaseCount('vocabularies', 0);
        $this->assertDatabaseMissing('lexilingo_import_checkpoints', ['entity' => 'vocabulary']);

        $sync->import(limit: 50);

        $this->assertDatabaseHas('vocabularies', ['external_id' => 'lexi-word-1']);
        $this->assertDatabaseHas('lexilingo_import_checkpoints', ['entity' => 'vocabulary', 'cursor' => 1]);
    }

    public function test_invalid_vocabulary_item_is_archived_and_skipped(): void
    {
        Http::fake([
            'backend.lexilingo.test/*' => Http::response([[
                'id' => 'lexi-word-bad',
                'word' => 'oops',
                // Missing every other required VocabularyItem field.
            ]]),
        ]);

        $result = $this->app->make(LexiLingoVocabularySync::class)->import(limit: 50);

        $this->assertSame(0, $result->processed);
        $this->assertSame(1, $result->skipped);
        $this->assertDatabaseMissing('vocabularies', ['external_id' => 'lexi-word-bad']);
        $this->assertDatabaseHas('lexilingo_import_failures', [
            'entity' => 'vocabulary',
            'external_id' => 'lexi-word-bad',
        ]);
    }

    public function test_disabled_feature_blocks_both_cli_entry_points_before_network_access(): void
    {
        config()->set('features.lexilingo_import', false);
        Http::fake();

        $this->artisan('lexilingo:import categories --limit=1')->assertFailed();
        $this->artisan('lexilingo:sync-vocabulary --limit=1')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_cli_stages_for_admin_review_and_apply_is_disabled_by_default(): void
    {
        config()->set('features.lexilingo_import', true);
        config()->set('features.lexilingo_import_apply', false);
        Http::fake([
            'backend.lexilingo.test/*' => Http::response(['data' => [[
                'id' => 'cat-cli',
                'name' => 'CLI category',
                'slug' => 'cli-category',
                'description' => null,
                'icon' => null,
                'color' => null,
                'course_count' => 0,
            ]], 'meta' => []]),
        ]);

        $this->artisan('lexilingo:import categories --limit=1')
            ->assertSuccessful()
            ->expectsOutputToContain('review_ready');

        $this->assertFalse(config('features.lexilingo_import_apply'));
        $this->assertDatabaseHas('admin_import_runs', [
            'entity' => 'categories',
            'initiator_type' => 'cli',
            'actor_id' => null,
            'status' => 'review_ready',
        ]);
        $this->assertDatabaseHas('admin_import_items', ['external_id' => 'cat-cli', 'classification' => 'new']);
        $this->assertDatabaseMissing('course_categories', ['external_id' => 'cat-cli']);
        $this->assertDatabaseMissing('lexilingo_import_checkpoints', ['entity' => 'categories']);
    }

    public function test_cli_dry_run_does_not_create_a_staged_run(): void
    {
        config()->set('features.lexilingo_import', true);
        Http::fake([
            'backend.lexilingo.test/*' => Http::response([[
                'id' => 'cat-dry',
                'name' => 'Dry category',
                'slug' => 'dry-category',
                'description' => null,
                'icon' => null,
                'color' => null,
                'course_count' => 0,
            ]]),
        ]);

        $this->artisan('lexilingo:import categories --limit=1 --dry-run')->assertSuccessful();

        $this->assertDatabaseCount('admin_import_runs', 0);
        $this->assertDatabaseCount('course_categories', 0);
    }

    public function test_direct_apply_is_blocked_before_network_when_flag_is_disabled(): void
    {
        config()->set('features.lexilingo_import_apply', false);
        Http::fake();

        try {
            app(CategoryImporter::class)->import(1);
            $this->fail('Direct apply should be disabled.');
        } catch (RuntimeException $error) {
            $this->assertSame('LexiLingo import apply is disabled.', $error->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_course_staging_records_parent_and_dependency_edges_without_catalog_writes(): void
    {
        config()->set('features.lexilingo_import', true);
        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/courses/course-stage' => Http::response(['data' => [
                'id' => 'course-stage', 'category_id' => 'cat-stage', 'title' => 'Staged course',
                'description' => null, 'language' => 'en', 'level' => 'beginner', 'tags' => [],
                'thumbnail_url' => null, 'total_lessons' => 1, 'total_xp' => 10,
                'estimated_duration' => 15,
                'units' => [[
                    'id' => 'unit-stage', 'title' => 'Staged unit', 'description' => null,
                    'order_index' => 1, 'background_color' => null, 'icon_url' => null,
                    'lessons' => [[
                        'id' => 'lesson-stage', 'title' => 'Staged lesson', 'order_index' => 1,
                        'lesson_type' => 'vocabulary', 'xp_reward' => 10,
                    ]],
                ]],
            ], 'meta' => []]),
            'backend.lexilingo.test/api/v1/integrations/courses*' => Http::response(['data' => [[
                'id' => 'course-stage', 'category_id' => 'cat-stage', 'title' => 'Staged course',
                'description' => null, 'language' => 'en', 'level' => 'beginner', 'tags' => [],
                'thumbnail_url' => null, 'total_lessons' => 1, 'total_xp' => 10,
                'estimated_duration' => 15,
            ]], 'pagination' => ['total_pages' => 1], 'meta' => []]),
        ]);

        $this->artisan('lexilingo:import courses --limit=1')->assertSuccessful();

        $run = AdminImportRun::query()->firstOrFail();
        $course = $run->items()->where('entity', 'course')->firstOrFail();
        $unit = $run->items()->where('entity', 'unit')->firstOrFail();
        $lesson = $run->items()->where('entity', 'lesson')->firstOrFail();
        $this->assertSame($course->id, $unit->parent_item_id);
        $this->assertSame($unit->id, $lesson->parent_item_id);
        $this->assertSame([['entity' => 'course', 'external_id' => 'course-stage']], $unit->dependencies);
        $this->assertSame([['entity' => 'unit', 'external_id' => 'unit-stage']], $lesson->dependencies);
        $this->assertDatabaseCount('courses', 0);
        $this->assertDatabaseCount('units', 0);
        $this->assertDatabaseCount('lessons', 0);
    }

    public function test_vocabulary_staging_maps_tags_to_topic_dependencies_without_writes(): void
    {
        config()->set('features.lexilingo_import', true);
        Http::fake([
            'backend.lexilingo.test/*' => Http::response([[
                'id' => 'word-stage', 'course_id' => null, 'lesson_id' => null,
                'word' => 'journey', 'definition' => 'a trip',
                'translation' => ['vi' => 'hành trình'], 'pronunciation' => null,
                'audio_url' => null, 'part_of_speech' => 'noun',
                'difficulty_level' => 'beginner', 'tags' => ['Travel', 'Basics'],
                'usage_frequency' => 10,
            ]]),
        ]);

        $this->artisan('lexilingo:sync-vocabulary --offset=0 --limit=1')->assertSuccessful();

        $run = AdminImportRun::query()->firstOrFail();
        $word = $run->items()->where('entity', 'vocabulary')->firstOrFail();
        $this->assertCount(2, $run->items()->where('entity', 'topic')->get());
        $this->assertCount(2, $word->dependencies);
        $this->assertSame(['Basics', 'Travel'], $word->candidate_payload['tags']);
        $this->assertDatabaseCount('vocabularies', 0);
        $this->assertDatabaseCount('topics', 0);
    }

    public function test_transport_failure_marks_staging_terminal_without_checkpoint_or_catalog_mutation(): void
    {
        config()->set('features.lexilingo_import', true);
        Http::fake(['backend.lexilingo.test/*' => Http::response([], 503)]);
        LexiLingoImportCheckpoint::create(['entity' => 'categories', 'cursor' => 12]);
        $actor = User::factory()->create();
        $run = AdminImportRun::create([
            'request_id' => fake()->uuid(),
            'entity' => 'categories',
            'payload_fingerprint' => str_repeat('a', 64),
            'actor_id' => $actor->id,
            'initiator_type' => 'admin',
            'status' => 'fetching',
            'requested_limit' => 1,
            'starting_cursor' => 12,
        ]);
        AdminImportLock::query()->whereKey('categories')->update([
            'current_run_id' => $run->id,
            'locked_at' => now(),
        ]);
        $runner = app(AdminImportRunner::class);

        try {
            $runner->run($run);
            $this->fail('Provider failure should stop staging.');
        } catch (\Throwable $error) {
            $runner->fail($run, $error);
        }

        $this->assertDatabaseHas('admin_import_runs', [
            'id' => $run->id,
            'status' => 'validation_failed',
            'error_code' => 'provider_unavailable',
        ]);
        $this->assertDatabaseHas('lexilingo_import_checkpoints', ['entity' => 'categories', 'cursor' => 12]);
        $this->assertDatabaseCount('course_categories', 0);
        $this->assertDatabaseCount('admin_import_items', 0);
        $this->assertNull(AdminImportLock::query()->findOrFail('categories')->current_run_id);
    }

    public function test_malformed_envelope_fails_the_run(): void
    {
        config()->set('features.lexilingo_import', true);
        $runner = app(AdminImportRunner::class);

        Http::fake(['backend.lexilingo.test/*' => Http::response(['unexpected' => []])]);
        try {
            $runner->runCli('categories', 1);
            $this->fail('Malformed batch should fail staging.');
        } catch (RuntimeException) {
        }

        $malformed = AdminImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame('validation_failed', $malformed->status);
        $this->assertDatabaseCount('admin_import_items', 0);
    }

    public function test_oversized_page_fails_the_bounded_run(): void
    {
        config()->set('features.lexilingo_import', true);
        $items = array_map(fn (int $id): array => [
            'id' => "oversized-{$id}",
            'name' => "Oversized {$id}",
            'slug' => "oversized-{$id}",
            'description' => null,
            'icon' => null,
            'color' => null,
            'course_count' => 0,
        ], range(1, 101));
        Http::fake(['backend.lexilingo.test/*' => Http::response([
            'data' => $items,
            'meta' => [],
        ])]);

        try {
            app(AdminImportRunner::class)->runCli('categories', 100);
            $this->fail('Oversized batch should fail staging.');
        } catch (RuntimeException $error) {
            $this->assertSame('LexiLingo returned an oversized batch.', $error->getMessage());
        }

        $this->assertDatabaseHas('admin_import_runs', [
            'entity' => 'categories',
            'status' => 'validation_failed',
        ]);
        $this->assertDatabaseCount('admin_import_items', 0);
    }

    public function test_conflicting_source_id_fails_the_run(): void
    {
        config()->set('features.lexilingo_import', true);
        Http::fake(['backend.lexilingo.test/*' => Http::response([
            'data' => [
                [
                    'id' => 'duplicate-id', 'name' => 'First', 'slug' => 'first',
                    'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
                ],
                [
                    'id' => 'duplicate-id', 'name' => 'Second', 'slug' => 'second',
                    'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
                ],
            ],
            'meta' => [],
        ])]);
        $duplicateError = null;
        try {
            app(AdminImportRunner::class)->runCli('categories', 2);
            $this->fail('Conflicting source identity should fail staging.');
        } catch (RuntimeException $error) {
            $duplicateError = $error;
        }

        $duplicate = AdminImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame('LexiLingo returned conflicting category identities.', $duplicateError?->getMessage());
        $this->assertSame('validation_failed', $duplicate->status);
        $this->assertDatabaseHas('admin_import_items', [
            'admin_import_run_id' => $duplicate->id,
            'external_id' => 'duplicate-id',
        ]);
        $this->assertDatabaseCount('course_categories', 0);
    }

    public function test_malformed_course_detail_fails_staging(): void
    {
        config()->set('features.lexilingo_import', true);
        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/courses/course-invalid-detail' => Http::response([
                'data' => ['id' => 'course-invalid-detail'],
                'meta' => [],
            ]),
            'backend.lexilingo.test/api/v1/integrations/courses*' => Http::response([
                'data' => [[
                    'id' => 'course-invalid-detail', 'category_id' => null, 'title' => 'Invalid detail',
                    'description' => null, 'language' => 'en', 'level' => 'beginner', 'tags' => [],
                    'thumbnail_url' => null, 'total_lessons' => 0, 'total_xp' => 0,
                    'estimated_duration' => 0,
                ]],
                'pagination' => ['total_pages' => 1],
                'meta' => [],
            ]),
        ]);

        try {
            app(AdminImportRunner::class)->runCli('courses', 1);
            $this->fail('Malformed course detail should fail staging.');
        } catch (RuntimeException) {
        }

        $this->assertDatabaseHas('admin_import_runs', [
            'entity' => 'courses',
            'status' => 'validation_failed',
        ]);
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_duplicate_delivery_cannot_downgrade_a_review_ready_run(): void
    {
        config()->set('features.lexilingo_import', true);
        Http::fake(['backend.lexilingo.test/*' => Http::response([
            'data' => [[
                'id' => 'job-once', 'name' => 'One job', 'slug' => 'one-job',
                'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
            ]],
            'meta' => [],
        ])]);
        $runner = app(AdminImportRunner::class);
        $run = $runner->runCli('categories', 1);
        $job = new RunAdminImport($run->id);

        $job->handle($runner);
        $job->failed(new RuntimeException('Late duplicate failure.'));

        $this->assertSame('review_ready', $run->fresh()->status);
        $this->assertSame(1, $run->items()->count());
        $this->assertSame(1, OperationsAudit::query()
            ->where('request_id', $run->request_id)->count());
    }

    public function test_stale_worker_cannot_complete_after_lock_takeover(): void
    {
        config()->set('features.lexilingo_import', true);
        $actor = User::factory()->create();
        $old = AdminImportRun::create([
            'request_id' => fake()->uuid(),
            'entity' => 'categories',
            'payload_fingerprint' => str_repeat('a', 64),
            'actor_id' => $actor->id,
            'status' => 'fetching',
            'requested_limit' => 1,
            'starting_cursor' => 0,
        ]);
        $next = AdminImportRun::create([
            'request_id' => fake()->uuid(),
            'entity' => 'categories',
            'payload_fingerprint' => str_repeat('b', 64),
            'actor_id' => $actor->id,
            'status' => 'fetching',
            'requested_limit' => 1,
            'starting_cursor' => 0,
        ]);
        AdminImportLock::query()->whereKey('categories')->update(['current_run_id' => $old->id]);
        Http::fake(function () use ($old, $next) {
            $old->update(['status' => 'validation_failed', 'error_code' => 'stale_run']);
            AdminImportLock::query()->whereKey('categories')->update(['current_run_id' => $next->id]);

            return Http::response([
                'data' => [[
                    'id' => 'late-item', 'name' => 'Late', 'slug' => 'late',
                    'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
                ]],
                'meta' => [],
            ]);
        });

        app(AdminImportRunner::class)->run($old);

        $this->assertSame('validation_failed', $old->fresh()->status);
        $this->assertSame($next->id, AdminImportLock::query()->findOrFail('categories')->current_run_id);
        $this->assertDatabaseMissing('operations_audits', ['request_id' => $old->request_id]);
    }

    public function test_disabled_feature_blocks_queued_job_and_runner(): void
    {
        config()->set('features.lexilingo_import', false);
        $runner = $this->app->make(AdminImportRunner::class);

        try {
            (new RunAdminImport(999))->handle($runner);
            $this->fail('Disabled queued import was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('LexiLingo import is disabled.', $exception->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $runner->run(new AdminImportRun);
    }
}
