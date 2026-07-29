<?php

namespace App\Services\Import;

use App\Models\AdminImportRun;
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
        $this->requireApplyEnabled($dryRun);
        $offset = $this->startingCursor($reset, $cursor);

        // Get local lessons that have an external_id from LexiLingo
        $lessons = Lesson::where('source_system', 'lexilingo')
            ->whereNotNull('external_id')
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
                    $snapshot = [
                        ...($lesson->source_snapshot ?? $lesson->only([
                            'title', 'slug', 'sort_order', 'status', 'lesson_type',
                            'estimated_minutes', 'xp_reward', 'pass_threshold',
                        ])),
                        'estimated_minutes' => $data['estimated_minutes'] ?? null,
                        'pass_threshold' => $data['pass_threshold'] ?? null,
                        'content' => json_encode($data['content'] ?? null, JSON_THROW_ON_ERROR),
                        'quiz_tree' => data_get($data, 'content.quiz'),
                    ];
                    [, $changed] = Lesson::syncFromSource(
                        'lesson',
                        'lexilingo',
                        (string) $lesson->external_id,
                        $snapshot,
                    );

                    if (! $changed) {
                        return;
                    }

                    // Map quiz, questions, and answers if present in the content
                    $quizData = data_get($data, 'content.quiz');
                    $quizExternalIds = [];
                    if ($quizData && is_array($quizData) && isset($quizData['id'])) {
                        $quizExternalIds[] = (string) $quizData['id'];
                        $quiz = Quiz::updateOrCreate(
                            ['external_id' => (string) $quizData['id']],
                            [
                                'lesson_id' => $lesson->id,
                                'title' => $quizData['title'] ?? $lesson->title,
                                'passing_score' => $quizData['passing_score'] ?? $data['pass_threshold'] ?? 60,
                                'status' => $quizData['status'] ?? 'published',
                            ]
                        );

                        $questionExternalIds = [];
                        foreach ($quizData['questions'] ?? [] as $qData) {
                            if (! isset($qData['id'])) {
                                continue;
                            }

                            $questionExternalIds[] = (string) $qData['id'];
                            $question = Question::updateOrCreate(
                                ['external_id' => (string) $qData['id']],
                                [
                                    'quiz_id' => $quiz->id,
                                    'content' => $qData['content'],
                                    'explanation' => $qData['explanation'] ?? null,
                                    'sort_order' => $qData['sort_order'] ?? 0,
                                ]
                            );

                            $answerExternalIds = [];
                            foreach ($qData['answers'] ?? [] as $aData) {
                                if (! isset($aData['id'])) {
                                    continue;
                                }

                                $answerExternalIds[] = (string) $aData['id'];
                                Answer::updateOrCreate(
                                    ['external_id' => (string) $aData['id']],
                                    [
                                        'question_id' => $question->id,
                                        'content' => $aData['content'],
                                        'is_correct' => (bool) ($aData['is_correct'] ?? false),
                                    ]
                                );
                            }

                            Answer::query()
                                ->where('question_id', $question->id)
                                ->whereNotNull('external_id')
                                ->when($answerExternalIds !== [], fn ($query) => $query->whereNotIn('external_id', $answerExternalIds))
                                ->delete();
                        }

                        Question::query()
                            ->where('quiz_id', $quiz->id)
                            ->whereNotNull('external_id')
                            ->when($questionExternalIds !== [], fn ($query) => $query->whereNotIn('external_id', $questionExternalIds))
                            ->delete();
                    }

                    Quiz::query()
                        ->where('lesson_id', $lesson->id)
                        ->whereNotNull('external_id')
                        ->when($quizExternalIds !== [], fn ($query) => $query->whereNotIn('external_id', $quizExternalIds))
                        ->delete();
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

    public function stage(AdminImportRun $run, StagedImportClassifier $classifier): ImportResult
    {
        $offset = (int) $run->starting_cursor;
        $lessons = Lesson::query()->where('source_system', 'lexilingo')
            ->whereNotNull('external_id')->orderBy('id')->offset($offset)
            ->limit($run->requested_limit)->get();
        $invalid = 0;

        foreach ($lessons as $lesson) {
            $response = $this->client->protectedBackend()
                ->retry(3, 200, throw: false)
                ->get("/api/v1/admin/lessons/{$lesson->external_id}");
            if ($response->failed()) {
                throw new \RuntimeException('LexiLingo lesson content fetch failed.');
            }
            $payload = $response->json();
            $errors = is_array($payload)
                ? $this->validator->validate('LessonContentResponse', $payload)
                : ['Invalid lesson content response.'];
            $data = is_array($payload) ? (array) ($payload['data'] ?? []) : [];
            $candidate = [
                ...($lesson->source_snapshot ?? $lesson->only([
                    'unit_external_id', 'title', 'slug', 'sort_order', 'status',
                    'lesson_type', 'estimated_minutes', 'xp_reward', 'pass_threshold',
                ])),
                'estimated_minutes' => $data['estimated_minutes'] ?? null,
                'pass_threshold' => $data['pass_threshold'] ?? null,
                'content' => array_key_exists('content', $data)
                    ? json_encode($data['content'], JSON_THROW_ON_ERROR)
                    : null,
                'quiz_tree' => data_get($data, 'content.quiz'),
            ];
            $staged = $this->stageItem(
                $run,
                $classifier,
                'lesson',
                (string) $lesson->external_id,
                $lesson->slug,
                $candidate,
                $lesson,
                validationErrors: $errors,
                dependencies: $lesson->unit?->external_id ? [[
                    'entity' => 'unit', 'external_id' => (string) $lesson->unit->external_id,
                ]] : [],
            );
            $invalid += $staged->classification === 'invalid' ? 1 : 0;
        }

        return new ImportResult($lessons->count() - $invalid, $invalid, $offset + $lessons->count());
    }
}
