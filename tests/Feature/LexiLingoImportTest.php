<?php

namespace Tests\Feature;

use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\LexiLingoImportFailure;
use App\Models\Unit;
use App\Services\Import\CategoryImporter;
use App\Services\Import\CourseImporter;
use App\Services\LexiLingoVocabularySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
            'external_id' => 'cat-1',
            'name' => 'Business English',
            'slug' => 'business-english',
        ]);
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
        $this->assertDatabaseHas('units', ['external_id' => 'unit-1', 'title' => 'Unit One']);
        $this->assertDatabaseHas('lessons', ['external_id' => 'lesson-1', 'title' => 'Lesson One']);
        $this->assertDatabaseHas('lessons', ['external_id' => 'lesson-2', 'title' => 'Lesson Two']);
        $this->assertSame(1, Unit::query()->count());
        $this->assertSame(2, Lesson::query()->count());
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

    /**
     * Verify that changing the limit between import runs does not skip or
     * duplicate items when the upstream API is page-based (categories, courses).
     *
     * Sequence: limit 50 → 100 → 25 → 50 with 250 total items should
     * process 175 unique items with no gaps and no repeats.
     */
    public function test_page_based_cursor_is_stable_across_varying_limits(): void
    {
        $totalItems = 250;
        $allItems = array_map(
            fn (int $i) => [
                'id' => "cat-{$i}",
                'name' => "Category {$i}",
                'slug' => "category-{$i}",
                'description' => null,
                'icon' => null,
                'color' => null,
                'course_count' => 0,
            ],
            range(0, $totalItems - 1),
        );

        $serverPageSize = 100;

        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/categories*' => function (Request $request) use (&$allItems, $serverPageSize) {
                $query = [];
                parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
                $page = max(1, (int) ($query['page'] ?? 1));
                $pageSize = max(1, (int) ($query['page_size'] ?? $serverPageSize));

                $offset = ($page - 1) * $pageSize;
                $slice = array_slice($allItems, $offset, $pageSize);

                return Http::response($slice);
            },
        ]);

        $importer = $this->app->make(CategoryImporter::class);

        // Run 1: limit 50, cursor starts at 0
        $r1 = $importer->import(limit: 50, reset: true);
        $this->assertSame(50, $r1->processed);
        $this->assertSame(50, $r1->nextCursor);

        // Run 2: limit 100, cursor = 50, should process items 50-99 from page 1 (50 items)
        $r2 = $importer->import(limit: 100);
        $this->assertSame(50, $r2->processed, 'Second run should process 50 remaining items on page 1');
        $this->assertSame(100, $r2->nextCursor);

        // Run 3: limit 25, cursor = 100, should fetch page 2 and process items 100-124
        $r3 = $importer->import(limit: 25);
        $this->assertSame(25, $r3->processed);
        $this->assertSame(125, $r3->nextCursor);

        // Run 4: continue with limit 50, cursor = 125, page 2 skip 25, process items 125-174
        $r4 = $importer->import(limit: 50);
        $this->assertSame(50, $r4->processed);
        $this->assertSame(175, $r4->nextCursor);

        // The processed counts across all runs must equal the number of unique
        // rows actually persisted -- if any item had been reprocessed by a
        // later run, the row count would be lower than the processed sum
        // because upserts on a duplicate external_id do not create new rows.
        $totalProcessed = $r1->processed + $r2->processed + $r3->processed + $r4->processed;
        $this->assertSame(175, $totalProcessed, 'Total imported items must equal sum of limits processed');
        $this->assertDatabaseCount('course_categories', 175);

        // Verify external IDs 0-174 exist with no gaps.
        $dbIds = CourseCategory::query()->pluck('external_id')->all();
        $expectedIds = array_map(fn (int $i) => "cat-{$i}", range(0, 174));
        sort($dbIds, SORT_NATURAL);
        sort($expectedIds, SORT_NATURAL);
        $this->assertSame($expectedIds, $dbIds);

        // Checkpoint cursor = 175
        $this->assertDatabaseHas('lexilingo_import_checkpoints', [
            'entity' => 'categories',
            'cursor' => 175,
        ]);
    }

    public function test_reset_starts_from_beginning_and_clears_checkpoint(): void
    {
        $items = array_map(
            fn (int $i) => [
                'id' => "cat-{$i}",
                'name' => "Category {$i}",
                'slug' => "category-{$i}",
                'description' => null,
                'icon' => null,
                'color' => null,
                'course_count' => 0,
            ],
            range(0, 149),
        );

        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/categories*' => function (Request $request) use (&$items) {
                $query = [];
                parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
                $page = max(1, (int) ($query['page'] ?? 1));
                $pageSize = max(1, (int) ($query['page_size'] ?? 100));
                $offset = ($page - 1) * $pageSize;

                return Http::response(array_slice($items, $offset, $pageSize));
            },
        ]);

        $importer = $this->app->make(CategoryImporter::class);

        // First import: process 60 items, cursor goes to 60
        $r1 = $importer->import(limit: 60);
        $this->assertSame(60, $r1->processed);
        $this->assertSame(60, $r1->nextCursor);
        $this->assertDatabaseHas('lexilingo_import_checkpoints', [
            'entity' => 'categories',
            'cursor' => 60,
        ]);

        // Reset: should start from cursor 0 again
        $r2 = $importer->import(limit: 50, reset: true);
        $this->assertSame(50, $r2->processed);
        $this->assertSame(50, $r2->nextCursor);
        $this->assertDatabaseHas('lexilingo_import_checkpoints', [
            'entity' => 'categories',
            'cursor' => 50,
        ]);

        // Verify items 0-59 exist (from first run) and are exactly 60
        $dbIds = CourseCategory::query()->pluck('external_id')->all();
        sort($dbIds, SORT_NATURAL);
        $expectedIds = array_map(fn (int $i) => "cat-{$i}", range(0, 59));
        $this->assertSame($expectedIds, $dbIds);
    }

    public function test_import_is_idempotent_when_rerun_with_same_cursor(): void
    {
        $items = array_map(
            fn (int $i) => [
                'id' => "cat-{$i}",
                'name' => "Category {$i}",
                'slug' => "category-{$i}",
                'description' => null,
                'icon' => null,
                'color' => null,
                'course_count' => 0,
            ],
            range(0, 99),
        );

        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/categories*' => function (Request $request) use (&$items) {
                $query = [];
                parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
                $page = max(1, (int) ($query['page'] ?? 1));
                $pageSize = max(1, (int) ($query['page_size'] ?? 100));

                return Http::response(array_slice($items, ($page - 1) * $pageSize, $pageSize));
            },
        ]);

        $importer = $this->app->make(CategoryImporter::class);

        // First run: process 30 items, cursor = 30
        $importer->import(limit: 30, reset: true);
        $countBefore = CourseCategory::query()->count();
        $this->assertSame(30, $countBefore);

        // Rerun with explicit cursor=0 — idempotent, same items upserted
        $r2 = $importer->import(limit: 30, cursor: 0);
        $this->assertSame(30, $r2->processed);
        $countAfter = CourseCategory::query()->count();
        $this->assertSame(30, $countAfter, 'Rerun with same cursor should not create duplicates');

        // Rerun with cursor=10 (partial overlap)
        $r3 = $importer->import(limit: 20, cursor: 10);
        $this->assertSame(20, $r3->processed);
        $countAfter2 = CourseCategory::query()->count();
        $this->assertSame(30, $countAfter2, 'Partial overlap rerun should not create duplicates');
    }
}
