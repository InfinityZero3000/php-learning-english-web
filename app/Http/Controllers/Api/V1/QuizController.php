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

class QuizController extends Controller
{
    public function show(Quiz $quiz): JsonResponse
    {
        $quiz->load(['questions.answers', 'lesson']);

        return ApiResponse::success(new QuizResource($quiz));
    }

    public function submit(Request $request, Quiz $quiz): JsonResponse
    {
        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'exists:questions,id,quiz_id,'.$quiz->id],
            'answers.*.answer_id' => ['required', 'integer', 'exists:answers,id'],
        ]);

        $userId = $request->user()->id;

        $existing = Attempt::where('user_id', $userId)
            ->where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->first();

        if ($existing) {
            return ApiResponse::success(new AttemptResource($existing->load('quiz')));
        }

        $questions = $quiz->questions()->with('answers')->get()->keyBy('id');
        $correctAnswers = Answer::whereIn('question_id', $questions->keys())
            ->where('is_correct', true)
            ->get()
            ->keyBy('question_id');

        $totalQuestions = $questions->count();
        $correctCount = 0;

        foreach ($validated['answers'] as $submitted) {
            $questionId = $submitted['question_id'];
            $submittedAnswerId = $submitted['answer_id'];

            if (! isset($questions[$questionId])) {
                continue;
            }

            $question = $questions[$questionId];
            $validAnswerIds = $question->answers->pluck('id')->toArray();

            if (! in_array($submittedAnswerId, $validAnswerIds)) {
                continue;
            }

            $correctAnswer = $correctAnswers->get($questionId);
            if ($correctAnswer && $correctAnswer->id == $submittedAnswerId) {
                $correctCount++;
            }
        }

        $score = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0;

        $attempt = Attempt::create([
            'user_id' => $userId,
            'quiz_id' => $quiz->id,
            'score' => $score,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return ApiResponse::success(new AttemptResource($attempt->load('quiz')), 201);
    }

    public function history(Request $request, Quiz $quiz): JsonResponse
    {
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
}
