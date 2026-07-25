<?php

namespace App\Http\Controllers;

use App\Models\Vocabulary;
use App\Models\Lesson;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VocabularyController extends Controller
{
    public function index(Request $request)
    {
        $query = Vocabulary::with(['lesson', 'topic']);

        if ($request->filled('search')) {
            $query->where('word', 'like', '%' . $request->search . '%')
                  ->orWhere('meaning', 'like', '%' . $request->search . '%');
        }

        $vocabularies = $query->paginate(20)->withQueryString();
        return view('vocabularies.index', compact('vocabularies'));
    }

    public function create()
    {
        $lessons = Lesson::orderBy('title')->get();
        $topics = Topic::orderBy('name')->get();
        return view('vocabularies.create', compact('lessons', 'topics'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'word'       => 'required|string|max:255',
            'meaning'    => 'required|string',
            'example'    => 'nullable|string',
            'lesson_id'  => 'nullable|exists:lessons,id',
            'topic_id'   => 'nullable|exists:topics,id',
            'image'      => 'nullable|image|max:2048',
            'audio'      => 'nullable|file|mimes:mp3,wav|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('vocabularies/images', 'public');
        }
        
        if ($request->hasFile('audio')) {
            $validated['audio_path'] = $request->file('audio')->store('vocabularies/audio', 'public');
        }

        Vocabulary::create($validated);

        return redirect()->route('admin.vocabularies.index')->with('success', 'Thêm từ vựng thành công!');
    }

    public function edit(Vocabulary $vocabulary)
    {
        $lessons = Lesson::orderBy('title')->get();
        $topics = Topic::orderBy('name')->get();
        return view('vocabularies.edit', compact('vocabulary', 'lessons', 'topics'));
    }

    public function update(Request $request, Vocabulary $vocabulary)
    {
        $validated = $request->validate([
            'word'       => 'required|string|max:255',
            'meaning'    => 'required|string',
            'example'    => 'nullable|string',
            'lesson_id'  => 'nullable|exists:lessons,id',
            'topic_id'   => 'nullable|exists:topics,id',
            'image'      => 'nullable|image|max:2048',
            'audio'      => 'nullable|file|mimes:mp3,wav|max:5120',
        ]);

        if ($request->hasFile('image')) {
            if ($vocabulary->image_path) Storage::disk('public')->delete($vocabulary->image_path);
            $validated['image_path'] = $request->file('image')->store('vocabularies/images', 'public');
        }
        
        if ($request->hasFile('audio')) {
            if ($vocabulary->audio_path) Storage::disk('public')->delete($vocabulary->audio_path);
            $validated['audio_path'] = $request->file('audio')->store('vocabularies/audio', 'public');
        }

        $vocabulary->update($validated);

        return redirect()->route('admin.vocabularies.index')->with('success', 'Cập nhật từ vựng thành công!');
    }

    public function destroy(Vocabulary $vocabulary)
    {
        if ($vocabulary->image_path) Storage::disk('public')->delete($vocabulary->image_path);
        if ($vocabulary->audio_path) Storage::disk('public')->delete($vocabulary->audio_path);
        
        $vocabulary->delete();
        return redirect()->route('admin.vocabularies.index')->with('success', 'Xóa từ vựng thành công!');
    }
}
