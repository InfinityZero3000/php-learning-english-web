<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage', User::class);

        return view('admin', [
            'users' => User::query()->with('role')->paginate(10),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage', User::class);

        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $user->update(['role_id' => $validated['role_id']]);

        return to_route('admin.users.index')->with('success', 'Cập nhật quyền thành công!');
    }
}
