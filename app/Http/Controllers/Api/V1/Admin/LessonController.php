<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\WriteLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeContent($request);
        $validator = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:255'], 'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'status' => ['nullable', 'in:draft,published,archived'], 'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        if ($validator->fails()) {
            throw new HttpResponseException(ApiResponse::error('VALIDATION_ERROR', 'The given data was invalid.', 422, ['errors' => $validator->errors()]));
        }
        $validated = $validator->validated();
        $search = trim((string) ($validated['search'] ?? ''));
        $page = Lesson::query()->with('course')->withCount(['vocabularies', 'quizzes'])
            ->when($search, fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->when($validated['course_id'] ?? null, fn ($query, $id) => $query->where('course_id', $id))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('course_id')->orderBy('sort_order')->paginate($validated['per_page'] ?? 20);

        return ApiResponse::success(LessonResource::collection(collect($page->items()))->resolve(), meta: [
            'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]);
    }

    public function store(WriteLessonRequest $request): JsonResponse
    {
        $lesson = Lesson::create($request->validated());

        return ApiResponse::success($this->resource($lesson), status: 201);
    }

    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorizeContent($request);

        return ApiResponse::success($this->resource($lesson));
    }

    public function update(WriteLessonRequest $request, Lesson $lesson): JsonResponse
    {
        $data = $request->validated();
        unset($data['status']);
        $lesson->update($data);

        return ApiResponse::success($this->resource($lesson));
    }

    public function publish(Request $request, Lesson $lesson): JsonResponse
    {
        return $this->transition($request, $lesson, 'published');
    }

    public function archive(Request $request, Lesson $lesson): JsonResponse
    {
        return $this->transition($request, $lesson, 'archived');
    }

    public function destroy(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorizeContent($request);
        $result = DB::transaction(function () use ($lesson): ?array {
            $locked = Lesson::query()->lockForUpdate()->findOrFail($lesson->id);
            $counts = [
                'progress' => $locked->progress()->count(),
                'active_assignments' => DB::table('assignments')->where('lesson_id', $locked->id)->whereIn('status', ['pending', 'in_progress'])->count(),
                'attempts' => DB::table('attempts')->whereIn('quiz_id', $locked->quizzes()->select('id'))->count(),
                'learning_sessions' => DB::table('learning_sessions')->where('lesson_id', $locked->id)->count(),
            ];
            if ($locked->status !== 'draft' || array_sum($counts) > 0) {
                return $counts;
            }
            $locked->delete();

            return null;
        });
        if ($result !== null) {
            return ApiResponse::error('DEPENDENCY_EXISTS', 'The lesson has protected learning evidence.', 409, ['dependencies' => $result]);
        }

        return response()->json(null, 204);
    }

    private function transition(Request $request, Lesson $lesson, string $status): JsonResponse
    {
        $this->authorizeContent($request);
        $lesson = DB::transaction(function () use ($lesson, $status): Lesson|false {
            $locked = Lesson::query()->lockForUpdate()->findOrFail($lesson->id);
            if (($status === 'published' && $locked->status !== 'draft') || ($status === 'archived' && $locked->status === 'archived')) {
                return false;
            }
            $locked->update(['status' => $status]);

            return $locked;
        });
        if ($lesson === false) {
            return ApiResponse::error('INVALID_STATE', 'Invalid lesson state transition.', 409);
        }

        return ApiResponse::success($this->resource($lesson));
    }

    private function resource(Lesson $lesson): array
    {
        return (new LessonResource($lesson->load(['course', 'quizzes'])->loadCount(['vocabularies', 'quizzes'])))->resolve();
    }

    private function authorizeContent(Request $request): void
    {
        abort_unless($request->user()->can('manage-content'), 403);
    }
}
