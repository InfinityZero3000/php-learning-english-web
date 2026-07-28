<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\RecentPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'canUpdateRoles' => Gate::allows('manage-operations'),
        ]);
    }

    public function updateRole(Request $request, User $user, RecentPassword $recentPassword): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);
        $role = Role::query()->findOrFail($validated['role_id']);

        Gate::authorize('updateRole', [$user, $role]);
        $recentPassword->require($request);

        DB::transaction(function () use ($request, $role, $user): void {
            Role::query()->where('slug', 'super_admin')->lockForUpdate()->firstOrFail();
            $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $lockedUser = $actor->is($user)
                ? $actor
                : User::query()->lockForUpdate()->findOrFail($user->id);
            Gate::forUser($actor)->authorize('updateRole', [$lockedUser, $role]);
            $lockedUser->update(['role_id' => $role->id]);
        });

        return to_route('admin.users.index')->with('success', 'Cập nhật quyền thành công!');
    }
}
