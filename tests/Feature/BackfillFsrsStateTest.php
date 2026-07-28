<?php

namespace Tests\Feature;

use App\Domain\Fsrs\FsrsConfig;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use App\Models\VocabularyReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillFsrsStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_replays_reviews_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['title' => 'Course', 'slug' => 'course']);
        $lesson = Lesson::create(['course_id' => $course->id, 'title' => 'Lesson', 'slug' => 'lesson']);
        $word = Vocabulary::create([
            'lesson_id' => $lesson->id,
            'word' => 'hello',
            'meaning' => 'xin chào',
        ]);
        $state = UserVocabulary::create([
            'user_id' => $user->id,
            'vocabulary_id' => $word->id,
            'state' => 'learning',
        ]);
        foreach ([[3, '2026-01-01 12:00:00'], [3, '2026-01-01 12:10:00'], [2, '2026-01-08 12:10:00']] as [$rating, $at]) {
            VocabularyReview::create([
                'user_vocabulary_id' => $state->id,
                'rating' => $rating,
                'stability' => 0,
                'difficulty' => 0,
                'scheduled_days' => 0,
                'elapsed_days' => 0,
                'reviewed_at' => $at,
            ]);
        }

        $this->artisan('fsrs:backfill')->assertSuccessful();

        $state->refresh();
        $this->assertSame(FsrsConfig::VERSION, $state->algorithm_version);
        $this->assertSame(3, $state->revision);
        $this->assertSame('review', $state->state);
        $this->assertNotNull($state->stability);
        $this->assertNotNull($state->difficulty);
        $snapshot = $state->only(['revision', 'due_at', 'stability', 'difficulty']);

        $this->artisan('fsrs:backfill')->expectsOutput('Backfilled 0 vocabulary states.')->assertSuccessful();
        $this->assertEquals($snapshot, $state->fresh()->only(array_keys($snapshot)));
    }

    public function test_it_initializes_never_reviewed_cards_without_inventing_memory_state(): void
    {
        $user = User::factory()->create();
        $word = Vocabulary::create(['word' => 'new', 'meaning' => 'mới']);
        $state = UserVocabulary::create(['user_id' => $user->id, 'vocabulary_id' => $word->id]);

        $this->artisan('fsrs:backfill --chunk=1')->assertSuccessful();

        $state->refresh();
        $this->assertSame('learning', $state->state);
        $this->assertNull($state->stability);
        $this->assertNull($state->difficulty);
        $this->assertSame(0, $state->revision);
        $this->assertSame(FsrsConfig::VERSION, $state->algorithm_version);
    }
}
