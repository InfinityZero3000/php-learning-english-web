<?php

namespace App\Services\Import;

use App\Models\Answer;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Throwable;

class LessonContentImporter extends AbstractLexiLingoImporter
{
    public function entity(): string
    {
        return 'lessons';
    }

    public function import(int $limit, bool $dryRun = false, bool $reset = false, ?int $cursor = null): ImportResult
    {
        $offset = $this->startingCursor($reset, $cursor);

        // Get local lessons that have an external_id from LexiLingo
        $lessons = Lesson::whereNotNull('external_id')
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $processed = 0;
        $skipped = 0;

        foreach ($lessons as $lesson) {
            $externalId = $lesson->external_id;

            try {
                // Fetch the protected lesson content with retries and a timeout
                $response = $this->client->protectedBackend()
                    ->retry(3, 200, function (Throwable $exception) {
                        return $exception instanceof ConnectionException
                            || ($exception instanceof RequestException
                                && $exception->response->serverError());
                    }, throw: false)
                    ->throwIf(fn ($response) => $response->serverError())
                    ->get("/api/v1/admin/lessons/{$externalId}");

                if ($response->failed()) {
                    $skipped++;
                    $this->logWarning('LexiLingo lesson content API failed', [
                        'external_id' => $externalId,
                        'status' => $response->status(),
                    ]);

                    if (! $dryRun) {
                        $this->archiveFailure($externalId, [], ["API returned status {$response->status()}"]);
                    }

                    break;
                }

                $payload = $response->json();

                // Validate the payload
                $errors = $this->validator->validate('LessonContentResponse', $payload);

                if ($errors !== []) {
                    $skipped++;
                    $this->logWarning('Skipped invalid lesson content payload', [
                        'external_id' => $externalId,
                        'errors' => $errors,
                    ]);

                    if (! $dryRun) {
                        $this->archiveFailure($externalId, $payload, $errors);
                    }

                    break;
                }

                if ($dryRun) {
                    $processed++;

                    continue;
                }

                // Parse payload data
                $data = $payload['data'];

                DB::transaction(function () use ($lesson, $data) {
                    $lesson->update([
                        'estimated_minutes' => $data['estimated_minutes'] ?? null,
                        'pass_threshold' => $data['pass_threshold'] ?? null,
                        'content' => json_encode($data['content'] ?? null),
                    ]);

                    // Map quiz, questions, and answers if present in the content
                    $quizData = data_get($data, 'content.quiz');
                    if ($quizData && is_array($quizData) && isset($quizData['id'])) {
                        $quiz = Quiz::updateOrCreate(
                            ['external_id' => (string) $quizData['id']],
                            [
                                'lesson_id' => $lesson->id,
                                'title' => $quizData['title'] ?? $lesson->title,
                                'passing_score' => $quizData['passing_score'] ?? $data['pass_threshold'] ?? 60,
                                'status' => $quizData['status'] ?? 'published',
                            ]
                        );

                        foreach ($quizData['questions'] ?? [] as $qData) {
                            if (! isset($qData['id'])) {
                                continue;
                            }

                            $question = Question::updateOrCreate(
                                ['external_id' => (string) $qData['id']],
                                [
                                    'quiz_id' => $quiz->id,
                                    'content' => $qData['content'],
                                    'explanation' => $qData['explanation'] ?? null,
                                    'sort_order' => $qData['sort_order'] ?? 0,
                                ]
                            );

                            foreach ($qData['answers'] ?? [] as $aData) {
                                if (! isset($aData['id'])) {
                                    continue;
                                }

                                Answer::updateOrCreate(
                                    ['external_id' => (string) $aData['id']],
                                    [
                                        'question_id' => $question->id,
                                        'content' => $aData['content'],
                                        'is_correct' => (bool) ($aData['is_correct'] ?? false),
                                    ]
                                );
                            }
                        }
                    }
                });

                $processed++;
            } catch (Throwable $e) {
                $skipped++;
                $this->logWarning('Lesson content sync failed, transaction rolled back', [
                    'external_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);

                if (! $dryRun) {
                    $this->archiveFailure($externalId, [], [$e->getMessage()]);
                }

                break;
            }
        }

        $nextCursor = $offset + $processed;

        if (! $dryRun) {
            $this->advanceCheckpoint($nextCursor);
        }

        $this->logInfo('Lesson content import page complete', [
            'offset' => $offset,
            'processed' => $processed,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ]);

        return new ImportResult($processed, $skipped, $nextCursor);
    }
}
