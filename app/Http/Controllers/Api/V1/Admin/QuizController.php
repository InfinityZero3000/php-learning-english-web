<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\WriteQuizRequest;
use App\Models\Quiz;
use App\Services\AdminQuizService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class QuizController extends Controller
{
    public function __construct(private readonly AdminQuizService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeContent($request);
        $validator = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:255'], 'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'status' => ['nullable', 'in:draft,published,archived'], 'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        if ($validator->fails()) throw new HttpResponseException(ApiResponse::error('VALIDATION_ERROR', 'The given data was invalid.', 422, ['errors' => $validator->errors()]));
        $validated = $validator->validated();
        $search = trim((string) ($validated['search'] ?? ''));
        $page = Quiz::query()->with('lesson.course')->withCount(['questions', 'attempts'])
            ->when($search, fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->when($validated['lesson_id'] ?? null, fn ($query, $id) => $query->where('lesson_id', $id))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate($validated['per_page'] ?? 20);

        return ApiResponse::success(collect($page->items())->map(fn (Quiz $quiz) => $this->summary($quiz))->all(), meta: [
            'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]);
    }

    public function store(WriteQuizRequest $request): JsonResponse
    {
        return ApiResponse::success($this->detail($this->service->create($request->validated())), status: 201);
    }

    public function show(Request $request, Quiz $quiz): JsonResponse
    {
        $this->authorizeContent($request);
        return ApiResponse::success($this->detail($quiz));
    }

    public function update(WriteQuizRequest $request, Quiz $quiz): JsonResponse
    {
        try {
            return ApiResponse::success($this->detail($this->service->update($quiz, $request->validated())));
        } catch (ConflictHttpException $exception) {
            if ($exception->getMessage() === 'ACTIVE_ATTEMPTS') return ApiResponse::error('ACTIVE_ATTEMPTS', 'The quiz has active attempts.', 409);
            throw $exception;
        }
    }

    public function destroy(Request $request, Quiz $quiz): JsonResponse
    {
        $this->authorizeContent($request);
        if (! $this->service->delete($quiz)) return ApiResponse::error('DEPENDENCY_EXISTS', 'The quiz has learner attempts.', 409);
        return response()->json(null, 204);
    }

    private function summary(Quiz $quiz): array
    {
        return [
            'id' => $quiz->id, 'lesson_id' => $quiz->lesson_id, 'lesson' => ['id' => $quiz->lesson->id, 'title' => $quiz->lesson->title],
            'title' => $quiz->title, 'passing_score' => $quiz->passing_score, 'status' => $quiz->status,
            'questions_count' => $quiz->questions_count, 'attempts_count' => $quiz->attempts_count,
        ];
    }

    private function detail(Quiz $quiz): array
    {
        $quiz->load(['lesson.course', 'questions' => fn ($query) => $query->orderBy('sort_order'), 'questions.answers']);
        return [
            'id' => $quiz->id, 'lesson_id' => $quiz->lesson_id, 'lesson' => ['id' => $quiz->lesson->id, 'title' => $quiz->lesson->title],
            'title' => $quiz->title, 'passing_score' => $quiz->passing_score, 'status' => $quiz->status,
            'questions' => $quiz->questions->map(fn ($question) => [
                'id' => $question->id, 'content' => $question->content, 'explanation' => $question->explanation,
                'answers' => $question->answers->map(fn ($answer) => ['id' => $answer->id, 'content' => $answer->content, 'is_correct' => $answer->is_correct])->all(),
            ])->all(),
        ];
    }

    private function authorizeContent(Request $request): void
    {
        abort_unless($request->user()->can('manage-content'), 403);
    }
}
