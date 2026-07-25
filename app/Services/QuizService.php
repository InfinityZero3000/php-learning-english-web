<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuizService
{
    private const CACHE_TTL = 3600;

    public function getQuiz(Quiz $quiz): Quiz
    {
        return Cache::remember("quiz:{$quiz->id}", self::CACHE_TTL, function () use ($quiz) {
            return $quiz->load(['questions.answers', 'lesson', 'lesson.course']);
        });
    }

    public function createQuiz(array $data): Quiz
    {
        try {
            $quiz = Quiz::create($data);
            $this->clearCache();
            Log::info('Quiz created', ['id' => $quiz->id, 'title' => $quiz->title]);
            return $quiz;
        } catch (\Exception $e) {
            Log::error('Failed to create quiz', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    public function updateQuiz(Quiz $quiz, array $data): Quiz
    {
        try {
            $quiz->update($data);
            Cache::forget("quiz:{$quiz->id}");
            $this->clearCache();
            Log::info('Quiz updated', ['id' => $quiz->id]);
            return $quiz;
        } catch (\Exception $e) {
            Log::error('Failed to update quiz', ['id' => $quiz->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function deleteQuiz(Quiz $quiz): void
    {
        try {
            $quiz->delete();
            Cache::forget("quiz:{$quiz->id}");
            $this->clearCache();
            Log::info('Quiz deleted', ['id' => $quiz->id]);
        } catch (\Exception $e) {
            Log::error('Failed to delete quiz', ['id' => $quiz->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function addQuestion(Quiz $quiz, array $data): Question
    {
        $question = $quiz->questions()->create([
            'question_text' => $data['question'],
            'type'          => $data['type'] ?? 'single_choice',
            'explanation'   => $data['explanation'] ?? null,
        ]);

        foreach ($data['answers'] as $index => $answerData) {
            $question->answers()->create([
                'answer_text' => $answerData['text'],
                'is_correct'  => $answerData['is_correct'] ?? false,
                'sort_order'  => $index,
            ]);
        }

        Cache::forget("quiz:{$quiz->id}");
        return $question->load('answers');
    }

    public function updateQuestion(Question $question, array $data): Question
    {
        $question->update([
            'question_text' => $data['question'],
            'explanation'   => $data['explanation'] ?? $question->explanation,
        ]);

        // Delete old answers and recreate
        $question->answers()->delete();
        foreach ($data['answers'] as $index => $answerData) {
            $question->answers()->create([
                'answer_text' => $answerData['text'],
                'is_correct'  => $answerData['is_correct'] ?? false,
                'sort_order'  => $index,
            ]);
        }

        Cache::forget("quiz:{$question->quiz_id}");
        return $question->load('answers');
    }

    public function deleteQuestion(Question $question): void
    {
        $quizId = $question->quiz_id;
        $question->delete();
        Cache::forget("quiz:{$quizId}");
    }

    public function submitAttempt(Quiz $quiz, int $userId, array $answers): Attempt
    {
        $quiz->load('questions.answers');

        $correctCount = 0;
        $totalQuestions = $quiz->questions->count();

        foreach ($quiz->questions as $question) {
            $correctAnswerIds = $question->answers->where('is_correct', true)->pluck('id')->toArray();
            $userAnswerIds = $answers[$question->id] ?? [];

            if (!is_array($userAnswerIds)) {
                $userAnswerIds = [$userAnswerIds];
            }

            $userAnswerIds = array_map('intval', $userAnswerIds);

            $isCorrect = count(array_diff($correctAnswerIds, $userAnswerIds)) === 0
                && count(array_diff($userAnswerIds, $correctAnswerIds)) === 0
                && count($correctAnswerIds) === count($userAnswerIds);

            if ($isCorrect) {
                $correctCount++;
            }
        }

        $score = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0;

        $attempt = Attempt::create([
            'user_id'         => $userId,
            'quiz_id'         => $quiz->id,
            'answers_data'    => $answers,
            'score'           => $score,
            'correct_answers' => $correctCount,
            'total_questions' => $totalQuestions,
            'passed'          => $score >= ($quiz->passing_score ?? 60),
            'completed_at'    => now(),
        ]);

        Log::info('Quiz attempt submitted', [
            'user_id' => $userId,
            'quiz_id' => $quiz->id,
            'score'   => $score,
        ]);

        return $attempt;
    }

    public function getUserScoreStats(int $userId): array
    {
        return Cache::remember("user:{$userId}:quiz_stats", self::CACHE_TTL, function () use ($userId) {
            $attempts = Attempt::where('user_id', $userId);
            $total = $attempts->count();
            $avgScore = $attempts->avg('score') ?? 0;
            $passed = $attempts->where('passed', true)->count();
            $highestScore = $attempts->max('score') ?? 0;

            return [
                'total_attempts'  => $total,
                'avg_score'       => round($avgScore, 2),
                'passed_count'    => $passed,
                'failed_count'    => $total - $passed,
                'highest_score'   => $highestScore,
                'recent_attempts' => $attempts->with('quiz:id,title,lesson_id,course_id')
                    ->latest('created_at')
                    ->limit(10)
                    ->get(),
            ];
        });
    }

    private function clearCache(): void
    {
        Cache::forget('dashboard:stats');
    }
}