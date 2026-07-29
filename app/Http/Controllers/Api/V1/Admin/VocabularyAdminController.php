<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\VocabularyResource;
use App\Models\Vocabulary;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VocabularyAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $query = Vocabulary::query();

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('word', 'like', "%{$search}%")
                    ->orWhere('meaning', 'like', "%{$search}%")
                    ->orWhere('definition', 'like', "%{$search}%");
            });
        }

        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->integer('lesson_id'));
        }

        if ($request->filled('difficulty_level')) {
            $query->where('difficulty_level', $request->string('difficulty_level'));
        }

        if ($request->filled('part_of_speech')) {
            $query->where('part_of_speech', $request->string('part_of_speech'));
        }

        $query->orderBy('word');

        $page = $query->paginate($perPage);

        return ApiResponse::success(
            VocabularyResource::collection($page->items()),
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
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'word' => ['required', 'string', 'max:255'],
            'meaning' => ['required', 'string'],
            'definition' => ['nullable', 'string'],
            'translation' => ['nullable', 'array'],
            'pronunciation' => ['nullable', 'string', 'max:255'],
            'part_of_speech' => ['nullable', 'string', 'max:50'],
            'difficulty_level' => ['nullable', 'string', 'max:50'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'example' => ['nullable', 'string'],
            'external_audio_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $vocabulary = Vocabulary::create($validated);

        Log::info('admin.vocabulary.created', [
            'user_id' => $request->user()->id,
            'vocabulary_id' => $vocabulary->id,
        ]);

        return ApiResponse::success(new VocabularyResource($vocabulary), 201);
    }

    public function show(Vocabulary $vocabulary): JsonResponse
    {
        return ApiResponse::success(new VocabularyResource($vocabulary));
    }

    public function update(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $validated = $request->validate([
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'word' => ['sometimes', 'required', 'string', 'max:255'],
            'meaning' => ['sometimes', 'required', 'string'],
            'definition' => ['nullable', 'string'],
            'translation' => ['nullable', 'array'],
            'pronunciation' => ['nullable', 'string', 'max:255'],
            'part_of_speech' => ['nullable', 'string', 'max:50'],
            'difficulty_level' => ['nullable', 'string', 'max:50'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'example' => ['nullable', 'string'],
            'external_audio_url' => ['nullable', 'url', 'max:2048'],
        ]);

        // Never overwrite external_id from LexiLingo
        unset($validated['external_id']);

        $vocabulary->update($validated);

        Log::info('admin.vocabulary.updated', [
            'user_id' => $request->user()->id,
            'vocabulary_id' => $vocabulary->id,
        ]);

        return ApiResponse::success(new VocabularyResource($vocabulary));
    }

    public function destroy(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        Log::info('admin.vocabulary.deleted', [
            'user_id' => $request->user()->id,
            'vocabulary_id' => $vocabulary->id,
            'word' => $vocabulary->word,
        ]);

        $vocabulary->delete();

        return response()->json(null, 204);
    }
}
