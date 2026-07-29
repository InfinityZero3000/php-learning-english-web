<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $query = User::query()->with('role');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('role', fn ($q) => $q->where('slug', $request->string('role')));
        }

        $query->orderByDesc('created_at');

        $page = $query->paginate($perPage);

        return ApiResponse::success(
            UserResource::collection($page->items()),
            meta: [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }

    public function show(User $user): JsonResponse
    {
        $user->load('role');

        return ApiResponse::success(new UserResource($user));
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $adminRoleId = Role::query()->where('slug', 'admin')->value('id');

        // Prevent last admin demotion or self-demotion
        if ((int) $validated['role_id'] !== (int) $adminRoleId
            && (int) $user->role_id === (int) $adminRoleId
            && ($user->is($request->user()) || User::query()->where('role_id', $adminRoleId)->count() <= 1)) {
            throw ValidationException::withMessages([
                'role_id' => 'Không thể hạ quyền quản trị viên cuối cùng hoặc tự hạ quyền tài khoản này.',
            ]);
        }

        $user->update(['role_id' => $validated['role_id']]);

        Log::info('admin.user.role_updated', [
            'actor_id' => $request->user()->id,
            'user_id' => $user->id,
            'new_role_id' => $validated['role_id'],
        ]);

        $user->load('role');

        return ApiResponse::success(new UserResource($user));
    }
}
