<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vocabulary;
use App\Models\VocabularyQuizSession;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VocabularyQuizController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'count' => ['nullable', 'integer', 'min:5', 'max:50'], 'category' => ['nullable', 'string', 'max:80'],
            'difficulty' => ['nullable', 'in:BEGINNER,INTERMEDIATE,ADVANCED'], 'topicId' => ['nullable', 'integer', 'exists:topics,id'],
            'cefrLevel' => ['nullable', 'in:A1,A2,B1,B2,C1,C2'], 'type' => ['required', 'in:en-vi,vi-en'],
        ])->validate();
        $query = Vocabulary::query()->whereNotNull('meaning')->where('meaning', '!=', '');
        if (! empty($data['category'])) {
            $query->where('part_of_speech', $data['category']);
        }
        if (! empty($data['difficulty'])) {
            $query->where('difficulty_level', $data['difficulty']);
        }
        if (! empty($data['topicId'])) {
            $query->where('topic_id', $data['topicId']);
        }
        if (! empty($data['cefrLevel'])) {
            $query->whereJsonContains('tags', $data['cefrLevel']);
        }

        $count = $data['count'] ?? 10;
        $words = $query->inRandomOrder()->limit($count)->get();
        if ($words->isEmpty()) {
            return ApiResponse::error('QUIZ_WORDS_NOT_FOUND', 'No words match the selected filters.', 422);
        }

        $session = VocabularyQuizSession::query()->create([
            'user_id' => $request->user()->id, 'quiz_type' => $data['type'], 'word_ids' => $words->pluck('id')->all(),
            'answers' => [], 'status' => 'active',
        ]);
        $distractors = Vocabulary::query()->whereNotIn('id', $words->pluck('id'))->whereNotNull('meaning')
            ->inRandomOrder()->limit(12)->get();

        $pool = $words->concat($distractors);

        return ApiResponse::success([
            'sessionId' => $session->id,
            'questions' => $words->map(fn (Vocabulary $word) => $this->question($word, $pool, $data['type'])),
            'totalQuestions' => $words->count(),
        ], 201);
    }

    public function answer(Request $request, VocabularyQuizSession $session, Vocabulary $vocabulary): JsonResponse
    {
        $data = Validator::make($request->all(), ['answer' => ['required', 'string', 'max:1000']])->validate();
        $feedback = DB::transaction(function () use ($request, $session, $vocabulary, $data): array {
            $locked = VocabularyQuizSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->owns($request, $locked);
            abort_unless(in_array($vocabulary->id, $locked->word_ids, true), 422);
            $answers = $locked->answers ?? [];
            $key = (string) $vocabulary->id;
            if (isset($answers[$key])) {
                return $answers[$key];
            }
            abort_unless($locked->status === 'active', 422);

            $expected = $locked->quiz_type === 'en-vi' ? $vocabulary->meaning : $vocabulary->word;
            $correct = mb_strtolower(trim($data['answer'])) === mb_strtolower(trim($expected));
            $feedback = ['vocabularyId' => $vocabulary->id, 'isCorrect' => $correct, 'correctAnswer' => $expected];
            $answers[$key] = $feedback;
            $locked->forceFill([
                'answers' => $answers,
                'correct_count' => $locked->correct_count + ($correct ? 1 : 0),
                'incorrect_count' => $locked->incorrect_count + ($correct ? 0 : 1),
            ])->save();

            return $feedback;
        });

        return ApiResponse::success($feedback);
    }

    public function complete(Request $request, VocabularyQuizSession $session): JsonResponse
    {
        $this->owns($request, $session);
        if (count($session->answers ?? []) !== count($session->word_ids)) {
            return ApiResponse::error('QUIZ_INCOMPLETE', 'Answer every question before completing the quiz.', 409);
        }
        if ($session->status !== 'completed') {
            $session->forceFill(['status' => 'completed', 'completed_at' => now('UTC')])->save();
        }
        $total = $session->correct_count + $session->incorrect_count;

        return ApiResponse::success([
            'sessionId' => $session->id, 'correctCount' => $session->correct_count,
            'incorrectCount' => $session->incorrect_count, 'score' => $total ? round($session->correct_count * 100 / $total) : 0,
            'status' => $session->status,
        ]);
    }

    private function owns(Request $request, VocabularyQuizSession $session): void
    {
        abort_unless($session->user_id === $request->user()->id, 404);
    }

    private function question(Vocabulary $word, $pool, string $type): array
    {
        $answer = $type === 'en-vi' ? $word->meaning : $word->word;
        $choices = $pool->reject(fn (Vocabulary $item) => $item->id === $word->id)
            ->map(fn (Vocabulary $item) => $type === 'en-vi' ? $item->meaning : $item->word)
            ->filter()->unique()->shuffle()->take(3)->push($answer)->shuffle()->values();

        return ['id' => $word->id, 'prompt' => $type === 'en-vi' ? $word->word : $word->meaning,
            'choices' => $choices, 'pronunciation' => $type === 'en-vi' ? $word->pronunciation : null,
            'category' => $word->part_of_speech];
    }
}
