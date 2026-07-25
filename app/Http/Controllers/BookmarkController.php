<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Vocabulary;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    // Danh sách từ vựng đã bookmark
    public function index()
    {
        $bookmarks = Bookmark::where('user_id', auth()->id())
            ->with('vocabulary.topic')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('bookmarks.index', compact('bookmarks'));
    }

    // Toggle: thêm nếu chưa có, xóa nếu đã có
    public function toggle(Request $request, Vocabulary $vocabulary)
    {
        $userId = auth()->id();

        $existing = Bookmark::where('user_id', $userId)
            ->where('vocabulary_id', $vocabulary->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $status = 'removed';
            $message = '"'.$vocabulary->word.'" đã được xóa khỏi bookmark.';
        } else {
            Bookmark::create([
                'user_id' => $userId,
                'vocabulary_id' => $vocabulary->id,
            ]);
            $status = 'added';
            $message = '"'.$vocabulary->word.'" đã được thêm vào bookmark!';
        }

        // Nếu là AJAX (fetch từ JavaScript)
        if ($request->expectsJson()) {
            return response()->json(['status' => $status]);
        }

        return back()->with('success', $message);
    }

    // Xóa một bookmark cụ thể
    public function destroy(Bookmark $bookmark)
    {
        abort_if($bookmark->user_id !== auth()->id(), 403);
        $bookmark->delete();

        return back()->with('success', 'Đã xóa bookmark.');
    }
}
