<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Level;
use App\Models\LexiLingoImportFailure;
use App\Models\Unit;
use App\Services\Import\CategoryImporter;
use App\Services\Import\CourseImporter;
use App\Services\LexiLingoVocabularySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
