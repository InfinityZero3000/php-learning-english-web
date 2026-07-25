<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TopicAdminController extends Controller
{
    public function index(): View
    {
        $topics = Topic::withCount('vocabularies')->orderBy('name')->paginate(15);
        return view('admin.topics.index', compact('topics'));
    }

    public function create(): View
    {
        return view('admin.topics.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:topics,slug',
            'description' => 'nullable|string',
        ]);

        Topic::create($data);

        return redirect()->route('admin.topics.index')->with('success', 'Topic đã được tạo.');
    }

    public function edit(Topic $topic): View
    {
        return view('admin.topics.edit', compact('topic'));
    }

    public function update(Request $request, Topic $topic): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:topics,slug,' . $topic->id,
            'description' => 'nullable|string',
        ]);

        $topic->update($data);

        return redirect()->route('admin.topics.index')->with('success', 'Topic đã được cập nhật.');
    }

    public function destroy(Topic $topic): RedirectResponse
    {
        $topic->delete();
        return redirect()->route('admin.topics.index')->with('success', 'Topic đã được xóa.');
    }
}