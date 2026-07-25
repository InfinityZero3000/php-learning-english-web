<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::with('role');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        $users = $query->orderBy('id', 'desc')->paginate(15);
        $roles = Role::orderBy('name')->get();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function show(User $user): View
    {
        $user->load('role');
        $roles = Role::orderBy('name')->get();

        $stats = [
            'courses_count' => \App\Models\Course::count(),
            'vocabulary_count' => \App\Models\Vocabulary::count(),
            'quiz_attempts' => \App\Models\QuizSession::where('user_id', $user->id)->count(),
            'avg_score' => round(\App\Models\QuizSession::where('user_id', $user->id)->avg('score') ?? 0, 1),
            'bookmarks' => \DB::table('vocabulary_bookmarks')->where('user_id', $user->id)->count(),
            'progress_count' => \App\Models\UserWordProgress::where('user_id', $user->id)->count(),
        ];

        return view('admin.users.show', compact('user', 'roles', 'stats'));
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        $request->validate(['role_id' => 'required|exists:roles,id']);
        $user->update(['role_id' => $request->role_id]);
        return redirect()->route('admin.users.show', $user)->with('success', 'Vai trò đã được cập nhật.');
    }

    public function lock(User $user): RedirectResponse
    {
        $user->update(['locked_at' => now()]);
        return redirect()->route('admin.users.show', $user)->with('success', 'Tài khoản đã bị khóa.');
    }

    public function unlock(User $user): RedirectResponse
    {
        $user->update(['locked_at' => null]);
        return redirect()->route('admin.users.show', $user)->with('success', 'Tài khoản đã được mở khóa.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return redirect()->route('admin.users.show', $user)->with('success', 'Mật khẩu đã được đặt lại.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'Người dùng đã được xóa.');
    }
}