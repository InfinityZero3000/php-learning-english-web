<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminQuizService
{
    public function create(array $data): Quiz
    {
        return DB::transaction(function () use ($data): Quiz {
            $quiz = Quiz::create(collect($data)->except('questions')->all());
            $this->replaceQuestions($quiz, $data['questions']);
            Lesson::query()->findOrFail($quiz->lesson_id)->applyLocalEdit([]);

            return $quiz;
        });
    }

    public function update(Quiz $quiz, array $data): Quiz
    {
        return DB::transaction(function () use ($quiz, $data): Quiz {
            $quiz = Quiz::query()->lockForUpdate()->findOrFail($quiz->id);
            if ($quiz->attempts()->where('status', 'active')->exists()) {
                throw new ConflictHttpException('ACTIVE_ATTEMPTS');
            }
            $oldLessonId = $quiz->lesson_id;
            $quiz->update(collect($data)->except('questions')->all());
            $quiz->questions()->delete();
            $this->replaceQuestions($quiz, $data['questions']);
            foreach (array_unique([$oldLessonId, $quiz->lesson_id]) as $lessonId) {
                Lesson::query()->findOrFail($lessonId)->applyLocalEdit([]);
            }

            return $quiz;
        });
    }

    public function delete(Quiz $quiz): bool
    {
        return DB::transaction(function () use ($quiz): bool {
            $quiz = Quiz::query()->lockForUpdate()->findOrFail($quiz->id);
            if ($quiz->attempts()->exists()) {
                return false;
            }
            Lesson::query()->findOrFail($quiz->lesson_id)->applyLocalEdit([]);
            $quiz->delete();

            return true;
        });
    }

    private function replaceQuestions(Quiz $quiz, array $questions): void
    {
        foreach ($questions as $questionIndex => $questionData) {
            $question = $quiz->questions()->create([
                'content' => $questionData['content'], 'explanation' => $questionData['explanation'] ?? null,
                'sort_order' => $questionIndex,
            ]);
            foreach ($questionData['answers'] as $answerData) {
                $question->answers()->create($answerData);
            }
        }
    }
}
