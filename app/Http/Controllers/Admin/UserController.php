<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

        $adminRoleId = Role::query()->where('slug', 'admin')->value('id');
        if ((int) $validated['role_id'] !== (int) $adminRoleId
            && ($user->is($request->user()) || User::query()->where('role_id', $adminRoleId)->count() <= 1)) {
            throw ValidationException::withMessages([
                'role_id' => 'Không thể hạ quyền quản trị viên cuối cùng hoặc tự hạ quyền tài khoản này.',
            ]);
        }

        $user->update(['role_id' => $validated['role_id']]);

        return to_route('admin.users.index')->with('success', 'Cập nhật quyền thành công!');
    }
}
