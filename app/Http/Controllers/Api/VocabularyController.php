<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vocabulary;
use App\Models\Topic;
use App\Models\UserWordProgress;
use App\Models\WordOfTheDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class VocabularyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Vocabulary::query()->with('topic');

        if ($request->filled('topic_id')) {
            $query->where('topic_id', $request->topic_id);
        }
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }
        if ($request->filled('cefr_level')) {
            $query->where('cefr_level', $request->cefr_level);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $query->where('word', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('part_of_speech')) {
            $query->where('part_of_speech', $request->part_of_speech);
        }

        $query->orderBy('id', 'desc');

        // Pagination (Laravel paginate returns {data, current_page, per_page, total, last_page})
        $perPage = min((int) ($request->size ?? $request->per_page ?? 20), 100);
        if ($request->has('page') || $request->has('size')) {
            $page = $query->paginate($perPage);
            return response()->json([
                'data' => $page->items(),
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ]);
        }

        $vocabularies = $query->get();
        return response()->json($vocabularies);
    }

    public function count(): JsonResponse
    {
        return response()->json(['count' => Vocabulary::count()]);
    }

    public function show(Vocabulary $vocabulary): JsonResponse
    {
        $vocabulary->load('topic');
        return response()->json($vocabulary);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'word' => 'required|string|max:255',
            'translation' => 'nullable|string',
            'pronunciation' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'difficulty' => 'nullable|in:BEGINNER,INTERMEDIATE,ADVANCED',
            'cefr_level' => 'nullable|in:A1,A2,B1,B2,C1,C2',
            'part_of_speech' => 'nullable|string|max:100',
            'image_url' => 'nullable|string|max:500',
            'topic_id' => 'nullable|exists:topics,id',
            'enrichment_status' => 'nullable|string',
        ]);

        $vocabulary = Vocabulary::create($validated);
        $vocabulary->load('topic');

        return response()->json($vocabulary, 201);
    }

    public function update(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $validated = $request->validate([
            'word' => 'sometimes|string|max:255',
            'translation' => 'nullable|string',
            'pronunciation' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'difficulty' => 'nullable|in:BEGINNER,INTERMEDIATE,ADVANCED',
            'cefr_level' => 'nullable|in:A1,A2,B1,B2,C1,C2',
            'part_of_speech' => 'nullable|string|max:100',
            'image_url' => 'nullable|string|max:500',
            'topic_id' => 'nullable|exists:topics,id',
            'enrichment_status' => 'nullable|string',
        ]);

        $vocabulary->update($validated);
        $vocabulary->load('topic');

        return response()->json($vocabulary);
    }

    public function destroy(Vocabulary $vocabulary): JsonResponse
    {
        $vocabulary->delete();
        return response()->json(null, 204);
    }

    public function categories(): JsonResponse
    {
        $categories = Vocabulary::select('category')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->values();

        return response()->json($categories);
    }

    public function partsOfSpeech(): JsonResponse
    {
        $pos = Vocabulary::select('part_of_speech')
            ->whereNotNull('part_of_speech')
            ->distinct()
            ->pluck('part_of_speech')
            ->values();

        return response()->json($pos);
    }

    public function random(Request $request): JsonResponse
    {
        $limit = min((int) ($request->limit ?? 1), 50);
        $vocabs = Vocabulary::inRandomOrder()->with('topic')->limit($limit)->get();
        return response()->json($vocabs);
    }

    public function wordOfTheDay(): JsonResponse
    {
        $today = now()->toDateString();
        $wordOfDay = WordOfTheDay::where('date', $today)
            ->with('vocabulary.topic')
            ->first();

        if (!$wordOfDay) {
            $randomVocab = Vocabulary::inRandomOrder()->with('topic')->first();
            return response()->json($randomVocab);
        }

        return response()->json($wordOfDay->vocabulary->load('topic'));
    }

    public function enrich(Vocabulary $vocabulary): JsonResponse
    {
        // Enrich word with dictionary data (stub - can be extended with real API)
        $vocabulary->update(['enrichment_status' => 'enriched']);
        $vocabulary->load('topic');

        return response()->json($vocabulary);
    }

    public function translateRows(Request $request): JsonResponse
    {
        $request->validate([
            'rows' => 'required|array',
            'rows.*.word' => 'required|string',
        ]);

        $rows = $request->rows;
        // Simple stub: just return rows with empty translation (can extend with translation API)
        foreach ($rows as &$row) {
            if (empty($row['translation'])) {
                $row['translation'] = '';
            }
        }

        return response()->json(['rows' => $rows]);
    }

    public function dictionaryLookup(string $word): JsonResponse
    {
        // Free dictionary lookup - stub (can integrate real dictionary API)
        $vocabulary = Vocabulary::where('word', $word)->first();

        if ($vocabulary) {
            return response()->json([
                'word' => $vocabulary->word,
                'bestPhonetic' => $vocabulary->pronunciation,
                'firstDefinition' => $vocabulary->translation,
                'primaryPartOfSpeech' => $vocabulary->part_of_speech,
                'allSynonyms' => $vocabulary->synonyms,
                'allAntonyms' => $vocabulary->antonyms,
            ]);
        }

        return response()->json(null);
    }
}