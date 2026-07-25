<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\Vocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VocabularyAdminController extends Controller
{
    public function index(Request $request): View
    {
        $query = Vocabulary::query()->with('topic');

        if ($request->filled('search')) {
            $query->where('word', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('topic_id')) {
            $query->where('topic_id', $request->topic_id);
        }
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        $vocabularies = $query->orderBy('id', 'desc')->paginate(15);
        $topics = Topic::orderBy('name')->get();

        return view('admin.vocabularies.index', compact('vocabularies', 'topics'));
    }

    public function create(): View
    {
        $topics = Topic::orderBy('name')->get();
        return view('admin.vocabularies.create', compact('topics'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'word' => 'required|string|max:255',
            'translation' => 'nullable|string',
            'pronunciation' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'difficulty' => 'nullable|in:BEGINNER,INTERMEDIATE,ADVANCED',
            'cefr_level' => 'nullable|in:A1,A2,B1,B2,C1,C2',
            'part_of_speech' => 'nullable|string|max:100',
            'image_url' => 'nullable|string|max:500',
            'audio_url' => 'nullable|string|max:500',
            'example' => 'nullable|string',
            'topic_id' => 'nullable|exists:topics,id',
            'synonyms' => 'nullable|string',
            'antonyms' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            $data['image_url'] = $request->file('image')->store('vocabularies', 'public');
        }
        if ($request->hasFile('audio')) {
            $data['audio_url'] = $request->file('audio')->store('vocabularies/audio', 'public');
        }

        Vocabulary::create($data);

        return redirect()->route('admin.vocabularies.index')->with('success', 'Từ vựng đã được tạo.');
    }

    public function edit(Vocabulary $vocabulary): View
    {
        $topics = Topic::orderBy('name')->get();
        return view('admin.vocabularies.edit', compact('vocabulary', 'topics'));
    }

    public function update(Request $request, Vocabulary $vocabulary): RedirectResponse
    {
        $data = $request->validate([
            'word' => 'required|string|max:255',
            'translation' => 'nullable|string',
            'pronunciation' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'difficulty' => 'nullable|in:BEGINNER,INTERMEDIATE,ADVANCED',
            'cefr_level' => 'nullable|in:A1,A2,B1,B2,C1,C2',
            'part_of_speech' => 'nullable|string|max:100',
            'image_url' => 'nullable|string|max:500',
            'audio_url' => 'nullable|string|max:500',
            'example' => 'nullable|string',
            'topic_id' => 'nullable|exists:topics,id',
            'synonyms' => 'nullable|string',
            'antonyms' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            $data['image_url'] = $request->file('image')->store('vocabularies', 'public');
        }
        if ($request->hasFile('audio')) {
            $data['audio_url'] = $request->file('audio')->store('vocabularies/audio', 'public');
        }

        $vocabulary->update($data);

        return redirect()->route('admin.vocabularies.index')->with('success', 'Từ vựng đã được cập nhật.');
    }

    public function destroy(Vocabulary $vocabulary): RedirectResponse
    {
        $vocabulary->delete();
        return redirect()->route('admin.vocabularies.index')->with('success', 'Từ vựng đã được xóa.');
    }
}