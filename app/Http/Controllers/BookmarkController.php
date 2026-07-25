<?php

namespace App\Http\Controllers;

use App\Models\Vocabulary;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function toggle(Request $request, Vocabulary $vocabulary)
    {
        $user = auth()->user();
        $bookmark = \App\Models\Bookmark::where('user_id', $user->id)
            ->where('vocabulary_id', $vocabulary->id)
            ->first();

        if ($bookmark) {
            $bookmark->delete();
            $message = 'Đã bỏ bookmark từ vựng.';
        } else {
            \App\Models\Bookmark::create([
                'user_id' => $user->id,
                'vocabulary_id' => $vocabulary->id,
            ]);
            $message = 'Đã bookmark từ vựng.';
        }

        return back()->with('success', $message);
    }

    public function index(Request $request)
    {
        $vocabularies = \App\Models\Vocabulary::whereHas('bookmarks', function ($q) {
            $q->where('user_id', auth()->id());
        })->paginate(20);

        return view('vocabularies.bookmarks', compact('vocabularies'));
    }
}