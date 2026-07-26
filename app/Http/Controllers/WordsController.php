<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Topic;
use App\Models\Vocabulary;
use Illuminate\Http\Request;

class WordsController extends Controller
{
    public function index(Request $request)
    {
        if (app()->isProduction()) {
            return redirect()->away(config('app.frontend_url').'/vocabulary');
        }

        $query = Vocabulary::with(['topic', 'lesson']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('word', 'like', '%'.$request->search.'%')
                    ->orWhere('meaning', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('topic_id')) {
            $query->where('topic_id', $request->topic_id);
        }

        $vocabularies = $query->orderBy('word')->paginate(20)->withQueryString();
        $topics = Topic::orderBy('name')->get();

        // Lấy danh sách vocab_id mà user hiện tại đã bookmark
        $bookmarkedIds = [];
        if (auth()->check()) {
            $bookmarkedIds = Bookmark::where('user_id', auth()->id())
                ->pluck('vocabulary_id')
                ->toArray();
        }

        return view('words.index', compact('vocabularies', 'topics', 'bookmarkedIds'));
    }

    public function show(string $vocabulary)
    {
        if (app()->isProduction()) {
            return redirect()->away(config('app.frontend_url').'/vocabulary');
        }

        $vocabulary = Vocabulary::findOrFail($vocabulary);
        $vocabulary->load(['lesson', 'topic']);

        $isBookmarked = false;
        if (auth()->check()) {
            $isBookmarked = Bookmark::where('user_id', auth()->id())
                ->where('vocabulary_id', $vocabulary->id)
                ->exists();
        }

        return view('words.show', compact('vocabulary', 'isBookmarked'));
    }
}
