<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $topics = Topic::withCount('vocabularies')->paginate($request->per_page ?? 20);
        return response()->json($topics);
    }

    public function show(Topic $topic): JsonResponse
    {
        $topic->loadCount('vocabularies');
        return response()->json(['data' => $topic]);
    }

    public function withProgress(Request $request): JsonResponse
    {
        $user = $request->user();
        $topics = Topic::withCount('vocabularies')->get();

        if ($user) {
            $progress = \App\Models\UserWordProgress::where('user_id', $user->id)
                ->selectRaw('vocabulary_id, mastery')
                ->get()
                ->groupBy('vocabulary_id');

            $topics->each(function ($topic) use ($progress) {
                $topicVocabIds = $topic->vocabularies()->pluck('id');
                $topic->learned = $topicVocabIds->filter(fn($id) => isset($progress[$id]))->count();
                $topic->mastered = $topicVocabIds->filter(fn($id) => isset($progress[$id]) && $progress[$id]->first()->mastery === 'mastered')->count();
            });
        }

        return response()->json(['data' => $topics]);
    }
}