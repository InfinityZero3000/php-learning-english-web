<?php

namespace App\Http\Controllers;

use App\Models\Level;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LevelAdminController extends Controller
{
    public function index(): View
    {
        $levels = Level::withCount('courses')->orderBy('sort_order')->paginate(15);
        return view('admin.levels.index', compact('levels'));
    }

    public function create(): View
    {
        return view('admin.levels.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:levels,slug',
            'sort_order' => 'required|integer|min:0',
        ]);

        Level::create($data);

        return redirect()->route('admin.levels.index')->with('success', 'Level đã được tạo.');
    }

    public function edit(Level $level): View
    {
        return view('admin.levels.edit', compact('level'));
    }

    public function update(Request $request, Level $level): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:levels,slug,' . $level->id,
            'sort_order' => 'required|integer|min:0',
        ]);

        $level->update($data);

        return redirect()->route('admin.levels.index')->with('success', 'Level đã được cập nhật.');
    }

    public function destroy(Level $level): RedirectResponse
    {
        $level->delete();
        return redirect()->route('admin.levels.index')->with('success', 'Level đã được xóa.');
    }
}