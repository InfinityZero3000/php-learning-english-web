<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index()
    {
        // Kiểm tra quyền qua UserPolicy (nếu không phải Admin sẽ bị chặn 403)
        Gate::authorize('manage', User::class);

        $users = User::paginate(10);
        return view('admin', compact('users'));
    }

    public function updateRole(Request $request, $id)
    {
        // Kiểm tra quyền qua UserPolicy
        Gate::authorize('manage', User::class);

        $user = User::findOrFail($id);
        $user->role_id = $request->input('role_id');
        $user->save();

        return redirect()->back()->with('success', 'Cập nhật quyền thành công!');
    }
}
