<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminContentSchemaTest extends TestCase
{
    use RefreshDatabase;

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
