<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttemptResource;
use App\Http\Resources\QuizResource;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Quiz;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizController extends Controller
{
    public function show(Quiz $quiz): JsonResponse
    {
        $this->ensurePublished($quiz);
        $quiz->load(['questions.answers', 'lesson']);

        return ApiResponse::success(new QuizResource($quiz));
    }

    public function submit(Request $request, Quiz $quiz): JsonResponse
    {
        $this->ensurePublished($quiz);

        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'distinct', 'exists:questions,id,quiz_id,'.$quiz->id],
            'answers.*.answer_id' => ['required', 'integer', 'exists:answers,id'],
        ]);

        $userId = $request->user()->id;

        $attempt = DB::transaction(function () use ($quiz, $userId, $validated): Attempt {
            Quiz::whereKey($quiz->id)->lockForUpdate()->firstOrFail();

            $existing = Attempt::where('user_id', $userId)
                ->where('quiz_id', $quiz->id)
                ->whereNotNull('completed_at')
                ->first();

            if ($existing) {
                return $existing;
            }

            $questions = $quiz->questions()->with('answers')->get()->keyBy('id');
            $correctAnswers = Answer::whereIn('question_id', $questions->keys())
                ->where('is_correct', true)
                ->get()
                ->keyBy('question_id');
            $correctCount = 0;

            foreach ($validated['answers'] as $submitted) {
                $question = $questions->get($submitted['question_id']);
                if (! $question?->answers->contains('id', $submitted['answer_id'])) {
                    throw ValidationException::withMessages([
                        'answers' => 'Each answer must belong to its submitted question.',
                    ]);
                }

                if ($correctAnswers->get($question->id)?->id === $submitted['answer_id']) {
                    $correctCount++;
                }
            }

            $totalQuestions = $questions->count();

            return Attempt::create([
                'user_id' => $userId,
                'quiz_id' => $quiz->id,
                'score' => $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0,
                'started_at' => now(),
                'completed_at' => now(),
            ]);
        }, 3);

        return ApiResponse::success(new AttemptResource($attempt->load('quiz')), $attempt->wasRecentlyCreated ? 201 : 200);
    }

    public function history(Request $request, Quiz $quiz): JsonResponse
    {
        $this->ensurePublished($quiz);

        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $attempts = Attempt::where('user_id', $request->user()->id)
            ->where('quiz_id', $quiz->id)
            ->with('quiz')
            ->orderByDesc('completed_at')
            ->paginate($perPage);

        return ApiResponse::success(
            AttemptResource::collection($attempts->items()),
            meta: [
                'current_page' => $attempts->currentPage(),
                'last_page' => $attempts->lastPage(),
                'per_page' => $attempts->perPage(),
                'total' => $attempts->total(),
            ],
        );
    }

    private function ensurePublished(Quiz $quiz): void
    {
        abort_unless(
            $quiz->status === 'published'
            && $quiz->lesson()->where('status', 'published')
                ->whereHas('course', fn ($query) => $query->where('status', 'published'))
                ->exists(),
            404,
        );
    }
}
