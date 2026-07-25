<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VocabularySet;
use App\Models\Vocabulary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VocabularySetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sets = VocabularySet::where('user_id', $request->user()->id)
            ->withCount('vocabularies')
            ->paginate($request->per_page ?? 20);
        return response()->json($sets);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $set = VocabularySet::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json(['data' => $set], 201);
    }

    public function show(VocabularySet $vocabularySet): JsonResponse
    {
        if ($vocabularySet->user_id !== request()->user()->id) {
            abort(403);
        }
        $vocabularySet->load('vocabularies.topic');
        return response()->json(['data' => $vocabularySet]);
    }

    public function update(Request $request, VocabularySet $vocabularySet): JsonResponse
    {
        if ($vocabularySet->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
        ]);

        $vocabularySet->update($request->only('name', 'description'));
        return response()->json(['data' => $vocabularySet]);
    }

    public function destroy(VocabularySet $vocabularySet): JsonResponse
    {
        if ($vocabularySet->user_id !== request()->user()->id) {
            abort(403);
        }
        $vocabularySet->delete();
        return response()->json(['message' => 'Set deleted']);
    }

    public function listWords(VocabularySet $vocabularySet): JsonResponse
    {
        if ($vocabularySet->user_id !== request()->user()->id) {
            abort(403);
        }
        $vocabularySet->load('vocabularies.topic');
        return response()->json($vocabularySet->vocabularies);
    }

    public function addSingleWord(VocabularySet $vocabularySet, int $wordId): JsonResponse
    {
        if ($vocabularySet->user_id !== request()->user()->id) {
            abort(403);
        }

        $vocabulary = Vocabulary::findOrFail($wordId);
        $vocabularySet->vocabularies()->syncWithoutDetaching([$wordId]);

        return response()->json([
            'message' => 'Word added to set',
            'count' => $vocabularySet->vocabularies()->count(),
        ]);
    }

    public function removeSingleWord(VocabularySet $vocabularySet, int $wordId): JsonResponse
    {
        if ($vocabularySet->user_id !== request()->user()->id) {
            abort(403);
        }

        $vocabularySet->vocabularies()->detach($wordId);

        return response()->json([
            'message' => 'Word removed from set',
            'count' => $vocabularySet->vocabularies()->count(),
        ]);
    }

    public function addWords(Request $request, VocabularySet $vocabularySet): JsonResponse
    {
        if ($vocabularySet->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'vocabulary_ids' => 'required|array',
            'vocabulary_ids.*' => 'exists:vocabularies,id',
        ]);

        $vocabularySet->vocabularies()->syncWithoutDetaching($request->vocabulary_ids);

        return response()->json([
            'message' => 'Words added successfully',
            'count' => $vocabularySet->vocabularies()->count(),
        ]);
    }

    public function removeWords(Request $request, VocabularySet $vocabularySet): JsonResponse
    {
        if ($vocabularySet->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'vocabulary_ids' => 'required|array',
            'vocabulary_ids.*' => 'exists:vocabularies,id',
        ]);

        $vocabularySet->vocabularies()->detach($request->vocabulary_ids);

        return response()->json(['message' => 'Words removed']);
    }
}