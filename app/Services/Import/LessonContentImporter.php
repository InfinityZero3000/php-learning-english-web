<?php

namespace App\Services\Import;

use App\Models\Answer;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Support\Facades\DB;
use Throwable;

class LessonContentImporter extends AbstractLexiLingoImporter
{
    public function entity(): string
    {
        return 'lessons';
    }

    // TODO(#45 follow-up): this importer still writes lesson/quiz/question/
    // answer rows directly (stageOnly() is never checked here) — only the
    // top-level lesson is staged for visibility. Gating lessons behind
    // approval, and staging the nested quiz/question/answer aggregate, is
    // deferred alongside courses.

    public function import(int $limit, bool $dryRun = false, bool $reset = false, ?int $cursor = null): ImportResult
    {
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
                // Partner-scoped read: same auth/retry contract as
                // Category/CourseImporter, no admin session involved.
                $payload = $this->client->partner()
                    ->get("/api/v1/integrations/lessons/{$externalId}/content")
                    ->throw()
                    ->json();

                $errors = $this->validator->validate('LessonContentResponse', $payload);

                if ($errors !== []) {
                    $skipped++;
                    $this->logWarning('Skipped invalid lesson content payload', [
                        'external_id' => $externalId,
                        'errors' => $errors,
                    ]);

                    if (! $dryRun) {
                        $this->archiveFailure($externalId, $payload, $errors, ImportErrorCode::PayloadInvalid);
                        $this->stageItem($externalId, $payload, null, 'invalid', $errors);
                    }

                    break;
                }

                if ($dryRun) {
                    $processed++;

                    continue;
                }

                $data = $payload['data'];
                $exercises = is_array($data['exercises'] ?? null) ? $data['exercises'] : [];

                $snapshot = [
                    ...($lesson->source_snapshot ?? $lesson->only([
                        'title', 'slug', 'sort_order', 'status', 'lesson_type',
                        'estimated_minutes', 'xp_reward', 'pass_threshold',
                    ])),
                    'estimated_minutes' => $data['estimated_minutes'] ?? null,
                    'pass_threshold' => $data['pass_threshold'] ?? null,
                    'content' => $data['description'] ?? null,
                    'exercises' => $exercises,
                ];
                // A LessonContentImporter row always starts from an existing
                // local Lesson (see the query above), so this can only be
                // 'update', 'conflict' or 'unchanged' — never 'new'.
                $this->stageChange($externalId, $data, $lesson, $snapshot);

                DB::transaction(function () use ($lesson, $exercises, $snapshot) {
                    [$synced, $changed] = Lesson::syncFromSource(
                        'lesson',
                        'lexilingo',
                        (string) $lesson->external_id,
                        $snapshot,
                    );

                    if (! $changed) {
                        return;
                    }

                    $this->syncExercises($synced, $exercises);
                });

                $processed++;
            } catch (Throwable $e) {
                $skipped++;
                $errorCode = $this->classifyImportError($e);
                $this->logWarning('Lesson content sync failed, transaction rolled back', [
                    'external_id' => $externalId,
                    'error_code' => $errorCode->value,
                    'error' => $e->getMessage(),
                ]);

                if (! $dryRun) {
                    $this->archiveFailure($externalId, [], [$e->getMessage()], $errorCode);
                    $this->stageItem($externalId, [], null, 'invalid', [$e->getMessage()]);
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

    /**
     * LexiLingo has no separate quiz concept — a lesson's `exercises` list
     * *is* its assessment. One Quiz row represents the whole lesson so the
     * existing Quiz/Question/Answer schema (and the learner-facing quiz UI)
     * keeps working unchanged; each Exercise becomes one Question.
     *
     * external_id is composed (lesson:{lesson}, then :{exercise}, then
     * :{option}) because it is globally unique across the whole table, and
     * an option id like "0"/"1" repeats across every exercise.
     */
    private function syncExercises(Lesson $lesson, array $exercises): void
    {
        $quizExternalId = "lesson:{$lesson->external_id}";

        if ($exercises === []) {
            // Cascades to its questions/answers — see the FK definition in
            // 2026_07_17_000000_create_learning_schema.php.
            Quiz::query()->where('lesson_id', $lesson->id)->where('external_id', $quizExternalId)->delete();

            return;
        }

        $quiz = Quiz::updateOrCreate(
            ['external_id' => $quizExternalId],
            [
                'lesson_id' => $lesson->id,
                'title' => $lesson->title,
                'passing_score' => $lesson->pass_threshold ?? 60,
                'status' => 'published',
            ]
        );

        $questionExternalIds = [];
        foreach ($exercises as $index => $exercise) {
            if (! isset($exercise['id'], $exercise['question'], $exercise['correct_answer'])) {
                continue;
            }

            $questionExternalId = "{$quizExternalId}:{$exercise['id']}";
            $questionExternalIds[] = $questionExternalId;
            $question = Question::updateOrCreate(
                ['external_id' => $questionExternalId],
                [
                    'quiz_id' => $quiz->id,
                    'content' => $exercise['question'],
                    'explanation' => $exercise['explanation'] ?? null,
                    'sort_order' => $index,
                ]
            );

            $this->syncAnswers($question, $questionExternalId, $exercise);
        }

        Question::query()
            ->where('quiz_id', $quiz->id)
            ->whereNotNull('external_id')
            ->whereNotIn('external_id', $questionExternalIds)
            ->delete();
    }

    private function syncAnswers(Question $question, string $questionExternalId, array $exercise): void
    {
        $options = array_values(array_filter(
            is_array($exercise['options'] ?? null) ? $exercise['options'] : [],
            fn (mixed $option): bool => is_array($option) && isset($option['id'], $option['text']),
        ));

        $answerExternalIds = [];
        if ($options !== []) {
            foreach ($options as $option) {
                $answerExternalId = "{$questionExternalId}:{$option['id']}";
                $answerExternalIds[] = $answerExternalId;
                Answer::updateOrCreate(
                    ['external_id' => $answerExternalId],
                    [
                        'question_id' => $question->id,
                        'content' => $option['text'],
                        'is_correct' => (bool) ($option['is_correct'] ?? false),
                    ]
                );
            }
        } else {
            // fill_blank/translate/... exercises carry no discrete option list,
            // only a single correct_answer string — represent it as one answer.
            $answerExternalId = "{$questionExternalId}:answer";
            $answerExternalIds[] = $answerExternalId;
            Answer::updateOrCreate(
                ['external_id' => $answerExternalId],
                ['question_id' => $question->id, 'content' => $exercise['correct_answer'], 'is_correct' => true]
            );
        }

        Answer::query()
            ->where('question_id', $question->id)
            ->whereNotNull('external_id')
            ->whereNotIn('external_id', $answerExternalIds)
            ->delete();
    }
}
