<?php

namespace App\Http\Controllers;

use App\Models\Level;
use App\Models\Topic;
use App\Models\Vocabulary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

        $vocabularies = $query->orderBy('created_at', 'desc')->paginate(12);
        $topics = Topic::all();
        $count = Vocabulary::count();

        return view('vocabulary.index', compact('vocabularies', 'topics', 'count'));
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

            $path = $request->file('image')->store('vocabulary', 'public');
            $vocabulary->update(['image_path' => $path]);
        }

        return back()->with('success', 'Hình ảnh đã được tải lên thành công.');
    }

    public function downloadImage(Vocabulary $vocabulary)
    {
        if (!$vocabulary->image_path || !Storage::disk('public')->exists($vocabulary->image_path)) {
            return back()->with('error', 'Hình ảnh không tồn tại.');
        }

        return response()->download(Storage::disk('public')->path($vocabulary->image_path));
    }

    public function update(Request $request, Vocabulary $vocabulary)
    {
        $validated = $request->validate([
            'word' => ['required', 'string'],
            'meaning' => ['required', 'string'],
            'example' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('image')) {
            if ($vocabulary->image_path) {
                Storage::disk('public')->delete($vocabulary->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('vocabulary', 'public');
        }

        $vocabulary->update($validated);

        return back()->with('success', 'Từ vựng đã được cập nhật.');
    }

    public function destroy(Vocabulary $vocabulary)
    {
        if ($vocabulary->image_path) {
            Storage::disk('public')->delete($vocabulary->image_path);
        }

        $vocabulary->delete();

        return back()->with('success', 'Từ vựng đã được xóa.');
    }
}
