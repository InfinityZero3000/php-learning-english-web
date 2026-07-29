<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminContentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_rows_have_local_ownership_metadata_by_default(): void
    {
        foreach (['course_categories', 'courses', 'units', 'lessons', 'topics', 'vocabularies'] as $table) {
            foreach (['source_system', 'source_fingerprint', 'source_snapshot', 'local_override_at', 'last_synced_at', 'catalog_revision'] as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "Missing {$table}.{$column}");
            }
        }

        $categoryId = DB::table('course_categories')->insertGetId([
            'name' => 'Local', 'slug' => 'local', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $courseId = DB::table('courses')->insertGetId([
            'category_id' => $categoryId, 'title' => 'Local course', 'slug' => 'local-course',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $unitId = DB::table('units')->insertGetId([
            'course_id' => $courseId, 'title' => 'Local unit', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('local', DB::table('courses')->where('id', $courseId)->value('source_system'));
        $this->assertSame(0, DB::table('courses')->where('id', $courseId)->value('catalog_revision'));
        $this->assertSame('draft', DB::table('units')->where('id', $unitId)->value('status'));
    }

    public function test_external_identity_is_unique_within_each_source_only(): void
    {
        $base = ['name' => 'Topic', 'slug' => 'topic-local', 'external_id' => 'shared-1', 'created_at' => now(), 'updated_at' => now()];
        DB::table('topics')->insert($base + ['source_system' => 'local']);
        DB::table('topics')->insert(array_merge($base, ['name' => 'Upstream', 'slug' => 'topic-upstream', 'source_system' => 'lexilingo']));

        $this->expectException(QueryException::class);
        DB::table('topics')->insert(array_merge($base, ['slug' => 'topic-duplicate', 'source_system' => 'local']));
    }

    public function test_lesson_prerequisites_are_unique_and_cascade_with_lessons(): void
    {
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Path', 'slug' => 'path', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $first = DB::table('lessons')->insertGetId([
            'course_id' => $courseId, 'title' => 'First', 'slug' => 'first',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $second = DB::table('lessons')->insertGetId([
            'course_id' => $courseId, 'title' => 'Second', 'slug' => 'second',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('lesson_prerequisites')->insert([
            'lesson_id' => $second, 'prerequisite_lesson_id' => $first,
        ]);

        DB::table('lessons')->where('id', $first)->delete();

        $this->assertDatabaseMissing('lesson_prerequisites', ['prerequisite_lesson_id' => $first]);
    }

    public function test_catalog_ownership_migrations_round_trip_on_the_ephemeral_database(): void
    {
        $ownership = require database_path('migrations/2026_07_29_020000_add_catalog_ownership_and_unit_lifecycle.php');
        $prerequisites = require database_path('migrations/2026_07_29_021000_create_lesson_prerequisites.php');

        $prerequisites->down();
        $ownership->down();

        $courseId = DB::table('courses')->insertGetId([
            'external_id' => 'course-legacy', 'title' => 'Legacy course', 'slug' => 'legacy-course',
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $unitId = DB::table('units')->insertGetId([
            'course_id' => $courseId, 'external_id' => 'unit-legacy', 'title' => 'Legacy unit',
            'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $emptyUnitId = DB::table('units')->insertGetId([
            'course_id' => $courseId, 'external_id' => 'unit-empty', 'title' => 'Empty unit',
            'sort_order' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('lessons')->insert([
            'course_id' => $courseId, 'unit_id' => $unitId, 'external_id' => 'lesson-legacy',
            'title' => 'Legacy lesson', 'slug' => 'legacy-lesson', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $ownership->up();
        $prerequisites->up();

        $this->assertTrue(Schema::hasTable('lesson_prerequisites'));
        $this->assertSame('lexilingo', DB::table('courses')->where('id', $courseId)->value('source_system'));
        $this->assertSame('published', DB::table('units')->where('id', $unitId)->value('status'));
        $this->assertSame('draft', DB::table('units')->where('id', $emptyUnitId)->value('status'));
    }

    public function test_catalog_ownership_rollback_refuses_to_discard_used_metadata(): void
    {
        DB::table('topics')->insert([
            'name' => 'Edited', 'slug' => 'edited', 'catalog_revision' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ownership = require database_path('migrations/2026_07_29_020000_add_catalog_ownership_and_unit_lifecycle.php');

        try {
            $ownership->down();
            $this->fail('Rollback should reject used catalog metadata.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('ownership metadata has been used', $exception->getMessage());
            $this->assertTrue(Schema::hasColumn('topics', 'catalog_revision'));
        }
    }

    public function test_admin_content_schema_supports_decks_preferences_and_notification_reads(): void
    {
        $this->seed();
        $userId = DB::table('users')->insertGetId([
            'name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('secret'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $alertId = DB::table('supervision_alerts')->insertGetId([
            'learner_id' => $userId, 'rule_key' => 'inactive', 'rule_version' => 1,
            'fingerprint' => 'schema-test', 'severity' => 'low', 'evidence' => '{}',
            'state' => 'open', 'detected_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $deckId = DB::table('vocabulary_decks')->insertGetId([
            'name' => 'Core', 'slug' => 'core', 'is_public' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $vocabularyId = DB::table('vocabularies')->insertGetId([
            'word' => 'hello', 'meaning' => 'xin chao', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('vocabulary_deck_vocabulary')->insert([
            'vocabulary_deck_id' => $deckId, 'vocabulary_id' => $vocabularyId, 'sort_order' => 0,
        ]);
        DB::table('admin_preferences')->insert([
            'user_id' => $userId, 'notifications' => '{}', 'ui' => '{}',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('admin_notification_reads')->insert([
            'user_id' => $userId, 'supervision_alert_id' => $alertId, 'read_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('vocabulary_decks')->insert([
            'name' => 'Duplicate', 'slug' => 'core', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_deck_memberships_cascade_without_deleting_vocabulary(): void
    {
        $deckId = DB::table('vocabulary_decks')->insertGetId([
            'name' => 'Core', 'slug' => 'core', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $vocabularyId = DB::table('vocabularies')->insertGetId([
            'word' => 'hello', 'meaning' => 'xin chao', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('vocabulary_deck_vocabulary')->insert([
            'vocabulary_deck_id' => $deckId, 'vocabulary_id' => $vocabularyId, 'sort_order' => 0,
        ]);

        DB::table('vocabulary_decks')->where('id', $deckId)->delete();

        $this->assertDatabaseMissing('vocabulary_deck_vocabulary', ['vocabulary_deck_id' => $deckId]);
        $this->assertDatabaseHas('vocabularies', ['id' => $vocabularyId]);
    }

    public function test_preferences_are_unique_per_admin(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Admin', 'email' => 'unique-admin@example.com', 'password' => bcrypt('secret'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = ['user_id' => $userId, 'notifications' => '{}', 'ui' => '{}', 'created_at' => now(), 'updated_at' => now()];
        DB::table('admin_preferences')->insert($row);

        $this->expectException(QueryException::class);
        DB::table('admin_preferences')->insert($row);
    }

    public function test_notification_read_state_is_unique_per_admin_and_alert(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Admin', 'email' => 'reads-admin@example.com', 'password' => bcrypt('secret'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $alertId = DB::table('supervision_alerts')->insertGetId([
            'learner_id' => $userId, 'rule_key' => 'inactive', 'rule_version' => 1,
            'fingerprint' => 'read-schema-test', 'severity' => 'low', 'evidence' => '{}',
            'state' => 'open', 'detected_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = ['user_id' => $userId, 'supervision_alert_id' => $alertId, 'read_at' => now()];
        DB::table('admin_notification_reads')->insert($row);

        $this->expectException(QueryException::class);
        DB::table('admin_notification_reads')->insert($row);
    }
}
