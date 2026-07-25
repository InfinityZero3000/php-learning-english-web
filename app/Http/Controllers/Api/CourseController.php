<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\LevelResource;
use App\Models\Course;
use App\Models\Level;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Course::query()
            ->with(['level', 'topics', 'lessons'])
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->level_id, fn ($q, $id) => $q->where('level_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->topic_id, fn ($q, $id) => $q->whereHas('topics', fn ($t) => $t->where('topics.id', $id)));

        return CourseResource::collection($query->paginate($request->per_page ?? 15));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'level_id' => 'nullable|exists:levels,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:courses,slug',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:draft,published,archived',
            'topic_ids' => 'sometimes|array',
            'topic_ids.*' => 'exists:topics,id',
        ]);

        $course = Course::create($data);

        if ($request->has('topic_ids')) {
            $course->topics()->sync($request->topic_ids);
        }

        return (new CourseResource($course->load(['level', 'topics'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Course $course): CourseResource
    {
        return new CourseResource($course->load(['level', 'topics', 'lessons']));
    }

    public function update(Request $request, Course $course): CourseResource
    {
        $data = $request->validate([
            'level_id' => 'nullable|exists:levels,id',
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:courses,slug,' . $course->id,
            'description' => 'nullable|string',
            'status' => 'sometimes|in:draft,published,archived',
            'topic_ids' => 'sometimes|array',
            'topic_ids.*' => 'exists:topics,id',
        ]);

        $course->update($data);

        if ($request->has('topic_ids')) {
            $course->topics()->sync($request->topic_ids);
        }

        return new CourseResource($course->fresh(['level', 'topics']));
    }

    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json(['message' => 'Khóa học đã được xóa.'], 200);
    }

    // Level management methods
    public function levels(Request $request): AnonymousResourceCollection
    {
        $query = Level::query()->withCount('courses')->orderBy('sort_order');

        return LevelResource::collection($query->paginate($request->per_page ?? 20));
    }

    public function levelShow(Level $level): LevelResource
    {
        return new LevelResource($level->loadCount('courses'));
    }

    public function levelStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:levels,slug',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $level = Level::create($data);

        return (new LevelResource($level))
            ->response()
            ->setStatusCode(201);
    }

    public function levelUpdate(Request $request, Level $level): LevelResource
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:levels,slug,' . $level->id,
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $level->update($data);

        return new LevelResource($level->fresh());
    }

    public function levelDestroy(Level $level): JsonResponse
    {
        $level->delete();

        return response()->json(['message' => 'Level đã được xóa.'], 200);
    }
}
