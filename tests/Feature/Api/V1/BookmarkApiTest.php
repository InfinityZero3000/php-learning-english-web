<?php

namespace Tests\Feature\Api\V1;

use App\Models\Bookmark;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Vocabulary;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LessonQuizSeeder;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarkApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    private int $lesson1Id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->seed(CatalogSeeder::class);
        $this->seed(LessonQuizSeeder::class);

        $this->user = User::where('email', 'user@example.com')->first();
        $this->otherUser = User::factory()->create();
        $this->lesson1Id = Lesson::where('slug', 'dong-vat-hoang-da')->value('id');
    }

    public function test_guest_gets_401_on_bookmark_routes(): void
    {
        $this->getJson('/api/v1/bookmarks')->assertUnauthorized();
        $this->postJson('/api/v1/bookmarks/vocabulary/1/toggle')->assertUnauthorized();
        $this->postJson("/api/v1/bookmarks/lesson/{$this->lesson1Id}/toggle")->assertUnauthorized();
    }

    public function test_user_can_toggle_vocabulary_bookmark(): void
    {
        $this->actingAs($this->user);

        $vocabulary = Vocabulary::create([
            'external_id' => 'test-word',
            'word' => 'test',
            'meaning' => 'test',
        ]);

        $this->postJson("/api/v1/bookmarks/vocabulary/{$vocabulary->id}/toggle")
            ->assertCreated()
            ->assertJsonPath('data.status', 'bookmarked');

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $this->user->id,
            'vocabulary_id' => $vocabulary->id,
            'bookmark_type' => 'vocabulary',
        ]);
    }

    public function test_user_can_toggle_vocabulary_unbookmark(): void
    {
        $this->actingAs($this->user);

        $vocabulary = Vocabulary::create([
            'external_id' => 'test-word',
            'word' => 'test',
            'meaning' => 'test',
        ]);

        $this->postJson("/api/v1/bookmarks/vocabulary/{$vocabulary->id}/toggle")
            ->assertCreated()
            ->assertJsonPath('data.status', 'bookmarked');

        $this->postJson("/api/v1/bookmarks/vocabulary/{$vocabulary->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.status', 'unbookmarked');

        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $this->user->id,
            'vocabulary_id' => $vocabulary->id,
            'bookmark_type' => 'vocabulary',
        ]);
    }

    public function test_user_cannot_see_other_user_bookmarks(): void
    {
        $this->actingAs($this->otherUser);
        $vocabulary = Vocabulary::create([
            'external_id' => 'test-word',
            'word' => 'test',
            'meaning' => 'test',
        ]);
        Bookmark::create([
            'user_id' => $this->user->id,
            'vocabulary_id' => $vocabulary->id,
            'bookmark_type' => 'vocabulary',
        ]);

        $this->getJson('/api/v1/bookmarks')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_user_can_toggle_lesson_bookmark(): void
    {
        $this->actingAs($this->user);

        $this->postJson("/api/v1/bookmarks/lesson/{$this->lesson1Id}/toggle")
            ->assertCreated()
            ->assertJsonPath('data.status', 'bookmarked');

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $this->user->id,
            'lesson_id' => $this->lesson1Id,
            'bookmark_type' => 'lesson',
        ]);
    }

    public function test_user_can_toggle_lesson_unbookmark(): void
    {
        $this->actingAs($this->user);

        $this->postJson("/api/v1/bookmarks/lesson/{$this->lesson1Id}/toggle")
            ->assertCreated()
            ->assertJsonPath('data.status', 'bookmarked');

        $this->postJson("/api/v1/bookmarks/lesson/{$this->lesson1Id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.status', 'unbookmarked');

        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $this->user->id,
            'lesson_id' => $this->lesson1Id,
            'bookmark_type' => 'lesson',
        ]);
    }

    public function test_toggle_returns_404_for_nonexistent_target(): void
    {
        $this->actingAs($this->user);

        $this->postJson('/api/v1/bookmarks/vocabulary/999999/toggle')->assertNotFound();
        $this->postJson('/api/v1/bookmarks/lesson/999999/toggle')->assertNotFound();
    }

    public function test_bookmark_index_paginates(): void
    {
        $this->actingAs($this->user);

        $vocabulary = Vocabulary::create([
            'external_id' => 'test-word',
            'word' => 'test',
            'meaning' => 'test',
        ]);
        Bookmark::create([
            'user_id' => $this->user->id,
            'vocabulary_id' => $vocabulary->id,
            'bookmark_type' => 'vocabulary',
        ]);

        $this->getJson('/api/v1/bookmarks?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_bookmark_index_can_filter_by_type(): void
    {
        $this->actingAs($this->user);

        $vocabulary = Vocabulary::create([
            'external_id' => 'test-word',
            'word' => 'test',
            'meaning' => 'test',
        ]);
        Bookmark::create([
            'user_id' => $this->user->id,
            'vocabulary_id' => $vocabulary->id,
            'bookmark_type' => 'vocabulary',
        ]);

        $this->getJson('/api/v1/bookmarks?bookmark_type=lesson')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/v1/bookmarks?bookmark_type=vocabulary')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }
}
