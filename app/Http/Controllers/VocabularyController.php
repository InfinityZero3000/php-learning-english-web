<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\Vocabulary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VocabularyController extends Controller
{
    public function index(Request $request)
    {
        $query = Vocabulary::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('word', 'like', "%{$search}%")
                ->orWhere('meaning', 'like', "%{$search}%");
        }

        if ($request->filled('topic')) {
            $query->where('topic_id', $request->input('topic'));
        }

        if ($request->filled('bookmarked') && auth()->check()) {
            $query->whereHas('bookmarks', fn($q) => $q->where('user_id', auth()->id()));
        }

        $vocabularies = $query->with('topic')->orderBy('created_at', 'desc')->paginate(12);
        $topics = Topic::all();
        $count = Vocabulary::count();

        return view('vocabularies.index', compact('vocabularies', 'topics', 'count'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'word'          => ['required', 'string', 'max:255'],
            'meaning'       => ['required', 'string'],
            'pronunciation' => ['nullable', 'string', 'max:255'],
            'example'       => ['nullable', 'string'],
            'topic_id'      => ['nullable', 'exists:topics,id'],
            'image'         => ['nullable', 'image', 'max:2048'],
            'audio'         => ['nullable', 'file', 'mimes:mp3,wav,ogg', 'max:5120'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('vocabulary/images', 'public');
        }

        if ($request->hasFile('audio')) {
            $validated['audio_path'] = $request->file('audio')->store('vocabulary/audio', 'public');
        }

        Vocabulary::create($validated);

        return back()->with('success', 'Từ vựng đã được thêm thành công.');
    }

    public function upload(Request $request, Vocabulary $vocabulary)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('image')) {
            if ($vocabulary->image_path) {
                Storage::disk('public')->delete($vocabulary->image_path);
            }

            $path = $request->file('image')->store('vocabulary/images', 'public');
            $vocabulary->update(['image_path' => $path]);
        }

        return back()->with('success', 'Hình ảnh đã được tải lên thành công.');
    }

    public function update(Request $request, Vocabulary $vocabulary)
    {
        $validated = $request->validate([
            'word'          => ['required', 'string', 'max:255'],
            'meaning'       => ['required', 'string'],
            'pronunciation' => ['nullable', 'string', 'max:255'],
            'example'       => ['nullable', 'string'],
            'topic_id'      => ['nullable', 'exists:topics,id'],
            'image'         => ['nullable', 'image', 'max:2048'],
            'audio'         => ['nullable', 'file', 'mimes:mp3,wav,ogg', 'max:5120'],
        ]);

        if ($request->hasFile('image')) {
            if ($vocabulary->image_path) {
                Storage::disk('public')->delete($vocabulary->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('vocabulary/images', 'public');
        }

        if ($request->hasFile('audio')) {
            if ($vocabulary->audio_path) {
                Storage::disk('public')->delete($vocabulary->audio_path);
            }
            $validated['audio_path'] = $request->file('audio')->store('vocabulary/audio', 'public');
        }

        $vocabulary->update($validated);

        return back()->with('success', 'Từ vựng đã được cập nhật.');
    }

    public function destroy(Vocabulary $vocabulary)
    {
        if ($vocabulary->image_path) {
            Storage::disk('public')->delete($vocabulary->image_path);
        }

        if ($vocabulary->audio_path) {
            Storage::disk('public')->delete($vocabulary->audio_path);
        }

        $vocabulary->delete();

        return back()->with('success', 'Từ vựng đã được xóa.');
    }

    public function importForm()
    {
        $topics = Topic::all();
        return view('vocabularies.import', compact('topics'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'topic_id' => ['nullable', 'exists:topics,id'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;

            Vocabulary::create([
                'word' => trim($row[0]),
                'meaning' => trim($row[1]),
                'pronunciation' => isset($row[2]) ? trim($row[2]) : null,
                'example' => isset($row[3]) ? trim($row[3]) : null,
                'topic_id' => $request->input('topic_id'),
            ]);

            $imported++;
        }

        fclose($handle);

        return back()->with('success', "Đã import thành công {$imported} từ vựng.");
    }

    public function flashcards(Request $request)
    {
        $query = Vocabulary::query();

        if ($request->filled('topic')) {
            $query->where('topic_id', $request->input('topic'));
        }

        $vocabularies = $query->orderBy('created_at', 'desc')->get();
        $topics = Topic::all();

        return view('vocabularies.flashcards', compact('vocabularies', 'topics'));
    }
}
