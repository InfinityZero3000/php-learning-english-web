<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarkToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_toggle_a_vocabulary_bookmark(): void
    {
        $user = User::factory()->create();
        $topic = Topic::create(['name' => 'General', 'slug' => 'general']);
        $vocabulary = Vocabulary::create(['topic_id' => $topic->id, 'word' => 'hello', 'meaning' => 'xin chào']);
        $this->actingAs($user)->postJson("/api/vocabulary/{$vocabulary->id}/bookmark", ['note' => 'remember'])->assertCreated();
        $this->assertDatabaseHas('vocabulary_bookmarks', ['user_id' => $user->id, 'vocabulary_id' => $vocabulary->id]);
        $this->actingAs($user)->postJson("/api/vocabulary/{$vocabulary->id}/bookmark")->assertNoContent();
        $this->assertDatabaseMissing('vocabulary_bookmarks', ['user_id' => $user->id, 'vocabulary_id' => $vocabulary->id]);
    }
}
