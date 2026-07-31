<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\Import\LessonContentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LexiLingoLessonSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.lexilingo.backend_url', 'https://backend.lexilingo.test');
        config()->set('services.lexilingo.partner_api_key', 'partner-secret');
        config()->set('cache.stores.redis', ['driver' => 'array']);
    }

    public function test_valid_lesson_content_is_synced_including_quiz(): void
    {
        $level = Level::create(['name' => 'Beginner', 'slug' => 'beginner', 'sort_order' => 1]);
        $course = Course::create([
            'level_id' => $level->id,
            'title' => 'Basic English',
            'slug' => 'basic-english',
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'source_system' => 'lexilingo',
            'external_id' => 'lesson-123',
            'title' => 'Greetings',
            'slug' => 'greetings',
        ]);

        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/lessons/lesson-123/content' => Http::response($this->payload([
                [
                    'id' => 'ex-1', 'type' => 'multiple_choice',
                    'question' => 'How do you say hello in Vietnamese?',
                    'options' => [
                        ['id' => 'a', 'text' => 'Xin chao', 'is_correct' => true],
                        ['id' => 'b', 'text' => 'Tam biet', 'is_correct' => false],
                    ],
                    'correct_answer' => 'a',
                    'explanation' => 'Xin chao is the most common way.',
                ],
            ])),
        ]);

        $importer = $this->app->make(LessonContentImporter::class);
        $result = $importer->import(limit: 50);

        $this->assertSame(1, $result->processed);
        $this->assertSame(0, $result->skipped);

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'estimated_minutes' => 12,
            'pass_threshold' => 75,
            'content' => 'Learn how to say hello',
        ]);

        $this->assertDatabaseHas('quizzes', [
            'external_id' => 'lesson:lesson-123',
            'lesson_id' => $lesson->id,
            'title' => 'Greetings',
            'passing_score' => 75,
            'status' => 'published',
        ]);
        $quiz = Quiz::where('external_id', 'lesson:lesson-123')->firstOrFail();

        $this->assertDatabaseHas('questions', [
            'external_id' => 'lesson:lesson-123:ex-1',
            'quiz_id' => $quiz->id,
            'content' => 'How do you say hello in Vietnamese?',
            'explanation' => 'Xin chao is the most common way.',
            'sort_order' => 0,
        ]);
        $question = Question::where('external_id', 'lesson:lesson-123:ex-1')->firstOrFail();

        $this->assertDatabaseHas('answers', [
            'external_id' => 'lesson:lesson-123:ex-1:a',
            'question_id' => $question->id,
            'content' => 'Xin chao',
            'is_correct' => true,
        ]);
        $this->assertDatabaseHas('answers', [
            'external_id' => 'lesson:lesson-123:ex-1:b',
            'question_id' => $question->id,
            'content' => 'Tam biet',
            'is_correct' => false,
        ]);

        Http::assertSent(fn ($request) => $request->hasHeader('X-LexiLingo-API-Key', 'partner-secret')
            && ! $request->hasHeader('X-Import-Key'));
    }

    public function test_exercise_without_discrete_options_synthesizes_one_correct_answer(): void
    {
        $course = Course::create(['title' => 'Basic', 'slug' => 'basic']);
        Lesson::create([
            'course_id' => $course->id, 'source_system' => 'lexilingo', 'external_id' => 'lesson-fill',
            'title' => 'Fill blank', 'slug' => 'fill-blank',
        ]);

        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/lessons/lesson-fill/content' => Http::response($this->payload([
                ['id' => 'ex-1', 'type' => 'fill_blank', 'question' => 'The sky is ___.', 'correct_answer' => 'blue'],
            ])),
        ]);

        $this->app->make(LessonContentImporter::class)->import(limit: 50);

        $question = Question::where('external_id', 'lesson:lesson-fill:ex-1')->firstOrFail();
        $this->assertDatabaseHas('answers', [
            'external_id' => 'lesson:lesson-fill:ex-1:answer',
            'question_id' => $question->id,
            'content' => 'blue',
            'is_correct' => true,
        ]);
        $this->assertSame(1, Answer::where('question_id', $question->id)->count());
    }

    public function test_lesson_content_sync_is_idempotent(): void
    {
        $course = Course::create(['title' => 'Basic English', 'slug' => 'basic-english']);
        Lesson::create([
            'course_id' => $course->id, 'source_system' => 'lexilingo', 'external_id' => 'lesson-123',
            'title' => 'Greetings', 'slug' => 'greetings',
        ]);

        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/lessons/lesson-123/content' => Http::response($this->payload([
                [
                    'id' => 'ex-1', 'type' => 'multiple_choice', 'question' => 'Question',
                    'options' => [['id' => 'a', 'text' => 'Correct', 'is_correct' => true]],
                    'correct_answer' => 'a',
                ],
            ])),
        ]);

        $importer = $this->app->make(LessonContentImporter::class);
        $importer->import(limit: 50);
        $importer->import(limit: 50, reset: true);

        $this->assertSame(1, Lesson::count());
        $this->assertSame(1, Quiz::count());
        $this->assertSame(1, Question::count());
        $this->assertSame(1, Answer::count());
    }

    public function test_lesson_content_sync_removes_upstream_children_missing_from_latest_snapshot(): void
    {
        $course = Course::create(['title' => 'Basic', 'slug' => 'basic']);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'source_system' => 'lexilingo', 'external_id' => 'lesson-prune',
            'title' => 'Prune', 'slug' => 'prune',
        ]);
        $exercise = fn (array $options): array => [[
            'id' => 'ex-1', 'type' => 'multiple_choice', 'question' => 'Question',
            'options' => $options, 'correct_answer' => 'keep',
        ]];

        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/lessons/lesson-prune/content' => Http::sequence()
                ->push($this->payload($exercise([
                    ['id' => 'keep', 'text' => 'Keep', 'is_correct' => true],
                    ['id' => 'remove', 'text' => 'Remove', 'is_correct' => false],
                ])))
                ->push($this->payload($exercise([['id' => 'keep', 'text' => 'Keep', 'is_correct' => true]])))
                ->push($this->payload([])),
        ]);
        $importer = $this->app->make(LessonContentImporter::class);

        $importer->import(10, reset: true);
        $importer->import(10, reset: true);
        $this->assertDatabaseMissing('answers', ['external_id' => 'lesson:lesson-prune:ex-1:remove']);
        $this->assertDatabaseHas('answers', ['external_id' => 'lesson:lesson-prune:ex-1:keep']);

        $importer->import(10, reset: true);
        $this->assertDatabaseMissing('quizzes', ['external_id' => 'lesson:lesson-prune']);
        $this->assertSame(3, $lesson->fresh()->catalog_revision);
    }

    public function test_invalid_lesson_content_skipped_and_archived(): void
    {
        $course = Course::create(['title' => 'Basic English', 'slug' => 'basic-english']);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'source_system' => 'lexilingo', 'external_id' => 'lesson-123',
            'title' => 'Greetings', 'slug' => 'greetings',
        ]);

        // Missing every required field (title, lesson_type, order_index, xp_reward, ...)
        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/lessons/lesson-123/content' => Http::response([
                'data' => ['description' => 'Broken payload'],
            ]),
        ]);

        $importer = $this->app->make(LessonContentImporter::class);
        $result = $importer->import(limit: 50);

        $this->assertSame(0, $result->processed);
        $this->assertSame(1, $result->skipped);

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'estimated_minutes' => null,
            'content' => null,
        ]);

        $this->assertDatabaseHas('lexilingo_import_failures', [
            'entity' => 'lessons',
            'external_id' => 'lesson-123',
        ]);
        $this->assertSame(0, $result->nextCursor);
        $this->assertDatabaseHas('lexilingo_import_checkpoints', [
            'entity' => 'lessons',
            'cursor' => 0,
        ]);
    }

    public function test_lesson_content_sync_handles_retry_and_timeout(): void
    {
        $course = Course::create(['title' => 'Basic English', 'slug' => 'basic-english']);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'source_system' => 'lexilingo', 'external_id' => 'lesson-123',
            'title' => 'Greetings', 'slug' => 'greetings',
        ]);

        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/lessons/lesson-123/content' => Http::sequence()
                ->pushStatus(500)
                ->pushStatus(503)
                ->push($this->payload([]), 200),
        ]);

        $importer = $this->app->make(LessonContentImporter::class);
        $result = $importer->import(limit: 50);

        $this->assertSame(1, $result->processed);
        $this->assertSame(0, $result->skipped);
        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'estimated_minutes' => 12,
        ]);
    }

    /** @param  list<array<string, mixed>>  $exercises */
    private function payload(array $exercises): array
    {
        return [
            'data' => [
                'id' => 'lesson-123',
                'title' => 'Greetings',
                'description' => 'Learn how to say hello',
                'lesson_type' => 'vocabulary',
                'order_index' => 1,
                'xp_reward' => 10,
                'pass_threshold' => 75,
                'estimated_minutes' => 12,
                'total_exercises' => count($exercises),
                'exercises' => $exercises,
            ],
            'meta' => [],
        ];
    }
}
