<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flashcard;
use App\Models\FlashcardSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlashcardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Flashcard::query();

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }
        if ($request->has('source')) {
            $query->where('source_name', $request->source);
        }
        if ($request->has('topic')) {
            $query->where('topic', $request->topic);
        }
        if ($request->has('search')) {
            $query->where('word', 'like', '%'.$request->search.'%');
        }

        $flashcards = $query->paginate($request->per_page ?? 20);
        return response()->json($flashcards);
    }

    public function show(Flashcard $flashcard): JsonResponse
    {
        return response()->json(['data' => $flashcard]);
    }

    public function sources(): JsonResponse
    {
        $sources = FlashcardSource::withCount('flashcards')->get();
        return response()->json(['data' => $sources]);
    }

    public function levels(): JsonResponse
    {
        $levels = Flashcard::select('level')->distinct()->whereNotNull('level')->pluck('level');
        return response()->json(['data' => $levels]);
    }

    public function topics(): JsonResponse
    {
        $topics = Flashcard::select('topic')->distinct()->whereNotNull('topic')->pluck('topic');
        return response()->json(['data' => $topics]);
    }

    public function review(Request $request): JsonResponse
    {
        $request->validate([
            'level' => 'nullable|string',
            'topic' => 'nullable|string',
            'count' => 'integer|min:5|max:50',
        ]);

        $query = Flashcard::query();
        if ($request->has('level')) {
            $query->where('level', $request->level);
        }
        if ($request->has('topic')) {
            $query->where('topic', $request->topic);
        }

        $count = $request->count ?? 10;
        $flashcards = $query->inRandomOrder()->limit($count)->get();

        return response()->json([
            'flashcards' => $flashcards->map(fn($f) => [
                'id' => $f->id,
                'front' => $f->front ?? $f->word,
                'back' => $f->back ?? $f->translation ?? $f->definition,
                'word' => $f->word,
                'example' => $f->example,
                'level' => $f->level,
            ]),
            'total' => $flashcards->count(),
        ]);
    }
}