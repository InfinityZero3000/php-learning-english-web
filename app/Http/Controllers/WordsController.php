<?php

namespace App\Http\Controllers;

use App\Models\Vocabulary;
use App\Models\Bookmark;
use App\Models\Topic;
use Illuminate\Http\Request;

class WordsController extends Controller
{
    public function index(Request $request)
    {
        $query = Vocabulary::with(['topic', 'lesson']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('word', 'like', '%' . $request->search . '%')
                  ->orWhere('meaning', 'like', '%' . $request->search . '%');
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

    public function show(Vocabulary $vocabulary)
    {
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
