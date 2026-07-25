<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLessonRequest;
use App\Http\Requests\UpdateLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LessonController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $lessons = Lesson::query()->when(request('topic_id'), fn ($query, $topic) => $query->where('topic_id', $topic))->when(request('level'), fn ($query, $level) => $query->where('level', $level))->when(request('cefr'), fn ($query, $cefr) => $query->where('cefr', $cefr))->orderBy('order')->paginate();

        return LessonResource::collection($lessons);
    }

    public function store(StoreLessonRequest $request): LessonResource
    {
        $lesson = new Lesson;
        $lesson->forceFill($request->validated())->save();

        return new LessonResource($lesson);
    }

    public function show(Lesson $lesson): LessonResource
    {
        return new LessonResource($lesson->load('quizzes.questions.answers'));
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson): LessonResource
    {
        $lesson->forceFill($request->validated())->save();

        return new LessonResource($lesson->refresh());
    }

    public function destroy(Lesson $lesson): Response
    {
        $lesson->delete();

        return response()->noContent();
    }
}
