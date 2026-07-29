<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuizResource;
use App\Models\Answer;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuizAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $query = Quiz::query()
            ->with('lesson')
            ->withCount('questions');

        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->integer('lesson_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('title', 'like', "%{$search}%");
        }

        $query->orderByDesc('updated_at');

        $page = $query->paginate($perPage);

        return ApiResponse::success(
            QuizResource::collection($page->items()),
            meta: [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
            'questions' => ['nullable', 'array'],
            'questions.*.content' => ['required', 'string'],
            'questions.*.explanation' => ['nullable', 'string'],
            'questions.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'questions.*.answers' => ['required', 'array', 'min:2'],
            'questions.*.answers.*.content' => ['required', 'string'],
            'questions.*.answers.*.is_correct' => ['required', 'boolean'],
        ]);

        $quiz = DB::transaction(function () use ($validated) {
            $quiz = Quiz::create([
                'lesson_id' => $validated['lesson_id'],
                'title' => $validated['title'],
                'passing_score' => $validated['passing_score'] ?? 60,
                'status' => $validated['status'] ?? 'draft',
            ]);

            foreach ($validated['questions'] ?? [] as $i => $qData) {
                $question = Question::create([
                    'quiz_id' => $quiz->id,
                    'content' => $qData['content'],
                    'explanation' => $qData['explanation'] ?? null,
                    'sort_order' => $qData['sort_order'] ?? $i,
                ]);

                foreach ($qData['answers'] as $aData) {
                    Answer::create([
                        'question_id' => $question->id,
                        'content' => $aData['content'],
                        'is_correct' => $aData['is_correct'],
                    ]);
                }
            }
            Lesson::query()->findOrFail($quiz->lesson_id)->applyLocalEdit([]);

            return $quiz;
        });

        Log::info('admin.quiz.created', [
            'user_id' => $request->user()->id,
            'quiz_id' => $quiz->id,
        ]);

        $quiz->load(['lesson', 'questions.answers']);
        $quiz->loadCount('questions');

        return ApiResponse::success(new QuizResource($quiz), 201);
    }

    public function show(Quiz $quiz): JsonResponse
    {
        $quiz->load(['lesson', 'questions.answers']);
        $quiz->loadCount('questions');

        return ApiResponse::success(new QuizResource($quiz));
    }

    public function update(Request $request, Quiz $quiz): JsonResponse
    {
        $validated = $request->validate([
            'lesson_id' => ['sometimes', 'required', 'integer', 'exists:lessons,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
        ]);

        DB::transaction(function () use ($quiz, $validated): void {
            $oldLessonId = $quiz->lesson_id;
            $quiz->update($validated);
            foreach (array_unique([$oldLessonId, $quiz->lesson_id]) as $lessonId) {
                Lesson::query()->findOrFail($lessonId)->applyLocalEdit([]);
            }
        });

        Log::info('admin.quiz.updated', [
            'user_id' => $request->user()->id,
            'quiz_id' => $quiz->id,
        ]);

        $quiz->load(['lesson', 'questions.answers']);
        $quiz->loadCount('questions');

        return ApiResponse::success(new QuizResource($quiz));
    }

    public function destroy(Request $request, Quiz $quiz): JsonResponse
    {
        Log::info('admin.quiz.deleted', [
            'user_id' => $request->user()->id,
            'quiz_id' => $quiz->id,
            'title' => $quiz->title,
        ]);

        DB::transaction(function () use ($quiz): void {
            Lesson::query()->findOrFail($quiz->lesson_id)->applyLocalEdit([]);
            $quiz->delete();
        });

        return response()->json(null, 204);
    }
}
