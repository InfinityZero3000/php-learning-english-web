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
        config()->set('services.lexilingo.import_key', 'import-key-secret');
        config()->set('cache.stores.redis', ['driver' => 'array']);
    }

    public function test_valid_lesson_content_is_synced_including_quiz(): void
    {
        // Setup local course and lesson outline
        $level = Level::create(['name' => 'Beginner', 'slug' => 'beginner', 'sort_order' => 1]);
        $course = Course::create([
            'level_id' => $level->id,
            'title' => 'Basic English',
            'slug' => 'basic-english',
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'external_id' => 'lesson-123',
            'title' => 'Greetings',
            'slug' => 'greetings',
        ]);

        // Mock LexiLingo lesson content response
        Http::fake([
            'backend.lexilingo.test/api/v1/admin/lessons/lesson-123' => Http::response([
                'data' => [
                    'description' => 'Learn how to say hello',
                    'prerequisites' => [],
                    'estimated_minutes' => 12,
                    'pass_threshold' => 75,
                    'content' => [
                        'blocks' => [
                            ['type' => 'text', 'value' => 'Hello World!'],
                        ],
                        'quiz' => [
                            'id' => 'quiz-999',
                            'title' => 'Greetings Review',
                            'passing_score' => 75,
                            'status' => 'published',
                            'questions' => [
                                [
                                    'id' => 'question-888',
                                    'content' => 'How do you say hello in Vietnamese?',
                                    'explanation' => 'Xin chao is the most common way.',
                                    'sort_order' => 1,
                                    'answers' => [
                                        ['id' => 'answer-777', 'content' => 'Xin chao', 'is_correct' => true],
                                        ['id' => 'answer-776', 'content' => 'Tam biet', 'is_correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'meta' => [],
            ]),
        ]);

        $importer = $this->app->make(LessonContentImporter::class);
        $result = $importer->import(limit: 50);

        // Assert importer stats
        $this->assertSame(1, $result->processed);
        $this->assertSame(0, $result->skipped);

        // Assert database updates
        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'estimated_minutes' => 12,
            'pass_threshold' => 75,
        ]);

        $updatedLesson = Lesson::find($lesson->id);
        $content = json_decode($updatedLesson->content, true);
        $this->assertSame('text', $content['blocks'][0]['type']);

        // Assert Quiz creation
        $this->assertDatabaseHas('quizzes', [
            'external_id' => 'quiz-999',
            'lesson_id' => $lesson->id,
            'title' => 'Greetings Review',
            'passing_score' => 75,
            'status' => 'published',
        ]);

        $quiz = Quiz::where('external_id', 'quiz-999')->firstOrFail();

        // Assert Question creation
        $this->assertDatabaseHas('questions', [
            'external_id' => 'question-888',
            'quiz_id' => $quiz->id,
            'content' => 'How do you say hello in Vietnamese?',
        ]);

        $question = Question::where('external_id', 'question-888')->firstOrFail();

        // Assert Answer creation
        $this->assertDatabaseHas('answers', [
            'external_id' => 'answer-777',
            'question_id' => $question->id,
            'content' => 'Xin chao',
            'is_correct' => true,
        ]);
        $this->assertDatabaseHas('answers', [
            'external_id' => 'answer-776',
            'question_id' => $question->id,
            'content' => 'Tam biet',
            'is_correct' => false,
        ]);

        // Assert headers sent
        Http::assertSent(fn ($request) => $request->hasHeader('X-Import-Key', 'import-key-secret'));
    }

    public function test_lesson_content_sync_is_idempotent(): void
    {
        $level = Level::create(['name' => 'Beginner', 'slug' => 'beginner', 'sort_order' => 1]);
        $course = Course::create([
            'level_id' => $level->id,
            'title' => 'Basic English',
            'slug' => 'basic-english',
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'external_id' => 'lesson-123',
            'title' => 'Greetings',
            'slug' => 'greetings',
        ]);

        $payload = [
            'data' => [
                'description' => 'Hello',
                'prerequisites' => [],
                'estimated_minutes' => 10,
                'pass_threshold' => 80,
                'content' => [
                    'quiz' => [
                        'id' => 'quiz-999',
                        'title' => 'Quiz',
                        'questions' => [
                            [
                                'id' => 'question-888',
                                'content' => 'Question',
                                'answers' => [
                                    ['id' => 'answer-777', 'content' => 'Correct', 'is_correct' => true],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'meta' => [],
        ];

        Http::fake([
            'backend.lexilingo.test/api/v1/admin/lessons/lesson-123' => Http::response($payload),
        ]);

        $importer = $this->app->make(LessonContentImporter::class);

        // Run once
        $importer->import(limit: 50);

        // Run twice
        $importer->import(limit: 50, reset: true);

        // Assert no duplicates
        $this->assertSame(1, Lesson::count());
        $this->assertSame(1, Quiz::count());
        $this->assertSame(1, Question::count());
        $this->assertSame(1, Answer::count());
    }

    public function test_invalid_lesson_content_skipped_and_archived(): void
    {
        $level = Level::create(['name' => 'Beginner', 'slug' => 'beginner', 'sort_order' => 1]);
        $course = Course::create([
            'level_id' => $level->id,
            'title' => 'Basic English',
            'slug' => 'basic-english',
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'external_id' => 'lesson-123',
            'title' => 'Greetings',
            'slug' => 'greetings',
        ]);

        // Mock invalid payload (missing data.estimated_minutes, data.pass_threshold etc.)
        Http::fake([
            'backend.lexilingo.test/api/v1/admin/lessons/lesson-123' => Http::response([
                'data' => [
                    'description' => 'Broken payload',
                    'content' => null,
                ],
            ]),
        ]);

        $importer = $this->app->make(LessonContentImporter::class);
        $result = $importer->import(limit: 50);

        $this->assertSame(0, $result->processed);
        $this->assertSame(1, $result->skipped);

        // Verify lesson content is NOT overwritten
        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'estimated_minutes' => null,
            'content' => null,
        ]);

        // Verify failure is logged
        $this->assertDatabaseHas('lexilingo_import_failures', [
            'entity' => 'lessons',
            'external_id' => 'lesson-123',
        ]);
    }

    public function test_lesson_content_sync_handles_retry_and_timeout(): void
    {
        $level = Level::create(['name' => 'Beginner', 'slug' => 'beginner', 'sort_order' => 1]);
        $course = Course::create([
            'level_id' => $level->id,
            'title' => 'Basic English',
            'slug' => 'basic-english',
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'external_id' => 'lesson-123',
            'title' => 'Greetings',
            'slug' => 'greetings',
        ]);

        // Mock 2 failures then 1 success
        Http::fake([
            'backend.lexilingo.test/api/v1/admin/lessons/lesson-123' => Http::sequence()
                ->pushStatus(500)
                ->pushStatus(503)
                ->push([
                    'data' => [
                        'description' => 'Succeeds eventually',
                        'prerequisites' => [],
                        'estimated_minutes' => 5,
                        'pass_threshold' => 60,
                        'content' => ['blocks' => []],
                    ],
                ], 200),
        ]);

        $importer = $this->app->make(LessonContentImporter::class);
        $result = $importer->import(limit: 50);

        $this->assertSame(1, $result->processed);
        $this->assertSame(0, $result->skipped);
        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'estimated_minutes' => 5,
        ]);
    }
}
