<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\LessonQuizException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitLessonQuizAnswerRequest;
use App\Http\Resources\LessonQuizResource;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\LessonQuizService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LessonQuizController extends Controller
{
    public function index(Request $request, LessonQuizService $service): JsonResponse
    {
        if ($response = $this->learner($request)) {
            return $response;
        }
        $validator = Validator::make($request->query(), ['lesson_id' => ['required', 'integer', 'exists:lessons,id']]);
        if ($validator->fails()) {
            return $this->validation($validator->errors()->toArray());
        }
        $lesson = Lesson::query()->with('course')->findOrFail($request->integer('lesson_id'));
        try {
            if ($lesson->status !== 'published' || ! $service->eligibleForLesson($request->user(), $lesson->id, $lesson->course_id)) {
                throw new LessonQuizException('NOT_FOUND', 404, 'The requested lesson is unavailable.');
            }

            return ApiResponse::success(LessonQuizResource::collection(
                Quiz::query()->where('lesson_id', $lesson->id)->where('status', 'published')->withCount('questions')->orderBy('id')->get(),
            ));
        } catch (LessonQuizException $exception) {
            return $this->error($exception);
        }
    }

    public function start(Request $request, Quiz $quiz, LessonQuizService $service): JsonResponse
    {
        if ($response = $this->learner($request)) {
            return $response;
        }
        try {
            [$attempt, $created] = $service->start($request->user(), $quiz);

            return ApiResponse::success($this->attemptPayload($attempt), $created ? 201 : 200);
        } catch (LessonQuizException $exception) {
            return $this->error($exception);
        }
    }

    public function answer(SubmitLessonQuizAnswerRequest $request, Attempt $attempt, Question $question, LessonQuizService $service): JsonResponse
    {
        if ($response = $this->learner($request)) {
            return $response;
        }
        $answer = Answer::findOrFail($request->integer('answer_id'));
        try {
            $submitted = $service->answer($request->user(), $attempt, $question, $answer);

            return ApiResponse::success($this->submittedAnswer($submitted));
        } catch (LessonQuizException $exception) {
            return $this->error($exception);
        }
    }

    public function complete(Request $request, Attempt $attempt, LessonQuizService $service): JsonResponse
    {
        if ($response = $this->learner($request)) {
            return $response;
        }
        try {
            return ApiResponse::success($this->completedPayload($service->complete($request->user(), $attempt)));
        } catch (LessonQuizException $exception) {
            return $this->error($exception);
        }
    }

    public function show(Request $request, Attempt $attempt, LessonQuizService $service): JsonResponse
    {
        if ($response = $this->learner($request)) {
            return $response;
        }
        try {
            $attempt = $service->show($request->user(), $attempt);

            return ApiResponse::success($attempt->status === 'completed' ? $this->completedPayload($attempt) : $this->attemptPayload($attempt));
        } catch (LessonQuizException $exception) {
            return $this->error($exception);
        }
    }

    private function learner(Request $request): ?JsonResponse
    {
        return $request->user()->hasRole('learner') ? null : ApiResponse::error('FORBIDDEN', 'Learner access is required.', 403);
    }

    private function validation(array $errors): JsonResponse
    {
        return ApiResponse::error('VALIDATION_ERROR', 'The given data was invalid.', 422, ['errors' => $errors]);
    }

    private function error(LessonQuizException $exception): JsonResponse
    {
        return ApiResponse::error($exception->apiCode, $exception->getMessage(), $exception->status);
    }

    private function attemptPayload(Attempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'quiz' => ['id' => $attempt->quiz->id, 'title' => $attempt->quiz->title, 'passing_score' => $attempt->quiz->passing_score],
            'status' => $attempt->status,
            'questions' => $attempt->quiz->questions->map(fn (Question $question) => [
                'id' => $question->id,
                'content' => $question->content,
                'answers' => $question->answers->map(fn (Answer $answer) => ['id' => $answer->id, 'content' => $answer->content]),
            ])->values(),
            'submitted_answers' => $attempt->answers->map(fn ($answer) => $this->submittedAnswer($answer))->values(),
        ];
    }

    private function submittedAnswer($answer): array
    {
        return [
            'question_id' => $answer->question_id,
            'answer_id' => $answer->answer_id,
            'answer_content' => $answer->answer_content_snapshot,
            'is_correct' => $answer->is_correct,
            'explanation' => $answer->explanation_snapshot,
        ];
    }

    private function completedPayload(Attempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'status' => 'completed',
            'score' => $attempt->score,
            'correct_answers' => $attempt->correct_answers,
            'total_questions' => $attempt->total_questions,
            'passed' => $attempt->score >= $attempt->quiz()->value('passing_score'),
            'completed_at' => $attempt->completed_at?->toISOString(),
        ];
    }
}
