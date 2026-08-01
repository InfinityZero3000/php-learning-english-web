<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperationsAudit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\RecentGoogleAdmin;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage', User::class);
        $query = User::query()->with('role:id,name,slug')
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->string('role')->toString(), fn ($query, string $role) => $query
                ->whereHas('role', fn ($query) => $query->where('slug', $role)));
        $page = $query->latest()->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return ApiResponse::success(collect($page->items())->map(fn (User $user) => $this->user($user)), meta: [
            'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        Gate::authorize('manage', User::class);

        return ApiResponse::success($this->user($user->load('role:id,name,slug')));
    }

    public function store(Request $request, RecentGoogleAdmin $recentGoogle): JsonResponse
    {
        abort_unless($request->user()->can('manage-roles'), 403);
        $recentGoogle->require($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'exists:roles,slug'],
        ]);
        $fingerprint = $this->fingerprint([$data['email'], $data['role']]);
        if ($audit = $this->replay($request, 'user.created', $fingerprint)) {
            return ApiResponse::success($this->user(User::with('role:id,name,slug')->findOrFail($audit->target_id)), status: 201);
        }

        $role = Role::query()->where('slug', $data['role'])->firstOrFail();
        $user = DB::transaction(function () use ($request, $data, $role, $fingerprint): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role_id' => $role->id,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->audit($request, 'user.created', 'user', $user->id, $fingerprint, null, $this->user($user->load('role:id,name,slug')));

            return $user;
        });

        return ApiResponse::success($this->user($user), status: 201);
    }

    public function destroy(Request $request, User $user, RecentGoogleAdmin $recentGoogle): JsonResponse
    {
        abort_unless($request->user()->can('manage-roles'), 403);
        $recentGoogle->require($request);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'Không thể tự xóa tài khoản của chính mình.']);
        }
        if ($user->hasRole('super_admin')
            && User::query()->whereHas('role', fn ($query) => $query->where('slug', 'super_admin'))->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'Không thể xóa Super Admin cuối cùng.']);
        }

        $fingerprint = $this->fingerprint([$user->id, 'delete']);
        if ($this->replay($request, 'user.deleted', $fingerprint)) {
            return response()->json(null, 204);
        }

        try {
            DB::transaction(function () use ($request, $user, $fingerprint): void {
                $locked = User::query()->lockForUpdate()->findOrFail($user->id);
                $before = $this->user($locked->load('role:id,name,slug'));
                TeacherAssignment::query()->where('teacher_id', $locked->id)->orWhere('learner_id', $locked->id)->delete();
                $locked->delete();
                $this->audit($request, 'user.deleted', 'user', $user->id, $fingerprint, $before, null);
            });
        } catch (QueryException $exception) {
            throw ValidationException::withMessages([
                'user' => 'Không thể xóa: tài khoản vẫn còn dữ liệu liên kết (phân công giám sát, ghi chú, lịch sử thao tác...).',
            ]);
        }

        return response()->json(null, 204);
    }

    public function roles(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage-roles'), 403);

        return ApiResponse::success(Role::query()->withCount('users')->orderBy('id')->get()
            ->map(fn (Role $role) => ['type' => 'role', ...$role->toArray()]));
    }

    public function updateRole(Request $request, User $user, RecentGoogleAdmin $recentGoogle): JsonResponse
    {
        abort_unless($request->user()->can('manage-roles'), 403);
        $data = $request->validate(['role' => ['required', 'string', 'exists:roles,slug']]);
        $role = Role::query()->where('slug', $data['role'])->firstOrFail();
        Gate::authorize('updateRole', [$user, $role]);
        $recentGoogle->require($request);
        $fingerprint = $this->fingerprint([$user->id, $role->slug]);
        if ($audit = $this->replay($request, 'user.role_updated', $fingerprint)) {
            return ApiResponse::success($this->user(User::with('role:id,name,slug')->findOrFail($audit->target_id)));
        }

        try {
            DB::transaction(function () use ($request, $user, $role, $fingerprint): void {
                Role::query()->where('slug', 'super_admin')->lockForUpdate()->firstOrFail();
                $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                $target = $actor->is($user) ? $actor : User::query()->lockForUpdate()->findOrFail($user->id);
                Gate::forUser($actor)->authorize('updateRole', [$target, $role]);
                $before = $target->load('role:id,slug')->toArray();
                $target->update(['role_id' => $role->id]);
                if ($role->slug !== 'teacher') {
                    TeacherAssignment::query()->where('teacher_id', $target->id)->delete();
                }
                if ($role->slug !== 'learner') {
                    TeacherAssignment::query()->where('learner_id', $target->id)->delete();
                }
                $this->audit($request, 'user.role_updated', 'user', $target->id, $fingerprint, $before, [
                    'role' => $role->slug,
                ]);
            });
        } catch (QueryException $exception) {
            if (! $this->replay($request, 'user.role_updated', $fingerprint)) {
                throw $exception;
            }
        }

        return ApiResponse::success($this->user($user->refresh()->load('role:id,name,slug')));
    }

    public function teacherAssignments(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('assign-teachers'), 403);

        return ApiResponse::success(TeacherAssignment::query()
            ->with(['teacher:id,name,email', 'learner:id,name,email'])->latest()->get()
            ->map(fn (TeacherAssignment $assignment) => ['type' => 'teacher_assignment', ...$assignment->toArray()]));
    }

    public function assignTeacher(Request $request, RecentGoogleAdmin $recentGoogle): JsonResponse
    {
        abort_unless($request->user()->can('assign-teachers'), 403);
        $recentGoogle->require($request);
        $data = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'learner_id' => ['required', 'integer', 'exists:users,id', 'different:teacher_id'],
        ]);
        $fingerprint = $this->fingerprint($data);
        if ($audit = $this->replay($request, 'teacher_scope.assigned', $fingerprint)) {
            $existing = TeacherAssignment::with(['teacher:id,name,email', 'learner:id,name,email'])->findOrFail($audit->target_id);

            return ApiResponse::success(['type' => 'teacher_assignment', ...$existing->toArray()]);
        }
        try {
            $assignment = DB::transaction(function () use ($request, $data, $fingerprint): TeacherAssignment {
                $users = User::query()->with('role')->whereIn('id', [$data['teacher_id'], $data['learner_id']])
                    ->lockForUpdate()->get()->keyBy('id');
                abort_unless($users->get($data['teacher_id'])?->hasRole('teacher'), 422, 'Selected user is not a teacher.');
                abort_unless($users->get($data['learner_id'])?->hasRole('learner'), 422, 'Selected user is not a learner.');
                $assignment = TeacherAssignment::firstOrCreate($data, ['assigned_at' => now('UTC')]);
                $this->audit($request, 'teacher_scope.assigned', 'teacher_assignment', $assignment->id, $fingerprint, null, $assignment->toArray());

                return $assignment;
            });
        } catch (QueryException $exception) {
            $audit = $this->replay($request, 'teacher_scope.assigned', $fingerprint);
            if (! $audit) {
                throw $exception;
            }
            $assignment = TeacherAssignment::findOrFail($audit->target_id);
        }

        return ApiResponse::success([
            'type' => 'teacher_assignment',
            ...$assignment->load(['teacher:id,name,email', 'learner:id,name,email'])->toArray(),
        ], status: $assignment->wasRecentlyCreated ? 201 : 200);
    }

    public function unassignTeacher(Request $request, int $teacherAssignment, RecentGoogleAdmin $recentGoogle): JsonResponse
    {
        abort_unless($request->user()->can('assign-teachers'), 403);
        $recentGoogle->require($request);
        $fingerprint = $this->fingerprint([$teacherAssignment, 'delete']);
        if ($this->replay($request, 'teacher_scope.removed', $fingerprint)) {
            return response()->json(null, 204);
        }
        try {
            DB::transaction(function () use ($request, $teacherAssignment, $fingerprint): void {
                $assignment = TeacherAssignment::query()->lockForUpdate()->findOrFail($teacherAssignment);
                $this->audit($request, 'teacher_scope.removed', 'teacher_assignment', $assignment->id, $fingerprint, $assignment->toArray(), null);
                $assignment->delete();
            });
        } catch (QueryException $exception) {
            if (! $this->replay($request, 'teacher_scope.removed', $fingerprint)) {
                throw $exception;
            }
        }

        return response()->json(null, 204);
    }

    public function updateTeacherAssignment(
        Request $request,
        TeacherAssignment $teacherAssignment,
        RecentGoogleAdmin $recentGoogle,
    ): JsonResponse {
        abort_unless($request->user()->can('assign-teachers'), 403);
        $recentGoogle->require($request);
        $data = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'learner_id' => ['required', 'integer', 'exists:users,id', 'different:teacher_id'],
        ]);
        $fingerprint = $this->fingerprint([$teacherAssignment->id, $data]);
        if ($audit = $this->replay($request, 'teacher_scope.updated', $fingerprint)) {
            $existing = TeacherAssignment::with(['teacher:id,name,email', 'learner:id,name,email'])->findOrFail($audit->target_id);

            return ApiResponse::success(['type' => 'teacher_assignment', ...$existing->toArray()]);
        }
        try {
            $teacherAssignment = DB::transaction(function () use ($request, $teacherAssignment, $data, $fingerprint): TeacherAssignment {
                $users = User::query()->with('role')->whereIn('id', [$data['teacher_id'], $data['learner_id']])
                    ->lockForUpdate()->get()->keyBy('id');
                abort_unless($users->get($data['teacher_id'])?->hasRole('teacher'), 422);
                abort_unless($users->get($data['learner_id'])?->hasRole('learner'), 422);
                $locked = TeacherAssignment::query()->lockForUpdate()->findOrFail($teacherAssignment->id);
                $before = $locked->toArray();
                $locked->update([...$data, 'assigned_at' => now('UTC')]);
                $this->audit($request, 'teacher_scope.updated', 'teacher_assignment', $locked->id, $fingerprint, $before, $locked->toArray());

                return $locked;
            });
        } catch (QueryException $exception) {
            $audit = $this->replay($request, 'teacher_scope.updated', $fingerprint);
            if (! $audit) {
                throw $exception;
            }
            $teacherAssignment = TeacherAssignment::findOrFail($audit->target_id);
        }

        return ApiResponse::success([
            'type' => 'teacher_assignment',
            ...$teacherAssignment->load(['teacher:id,name,email', 'learner:id,name,email'])->toArray(),
        ]);
    }

    private function user(User $user): array
    {
        return [
            'type' => 'user', 'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'role' => $user->role?->slug, 'created_at' => $user->created_at?->toISOString(),
        ];
    }

    private function replay(Request $request, string $action, string $fingerprint): ?OperationsAudit
    {
        $requestId = $request->header('X-Request-ID');
        validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();
        $audit = OperationsAudit::query()->where('request_id', $requestId)->first();
        if ($audit && ($audit->action !== $action || data_get($audit->context, 'fingerprint') !== $fingerprint)) {
            throw new ConflictHttpException('X-Request-ID was already used for another operation.');
        }

        return $audit;
    }

    private function audit(
        Request $request,
        string $action,
        string $targetType,
        int $targetId,
        string $fingerprint,
        ?array $before,
        ?array $after,
    ): void {
        OperationsAudit::create([
            'actor_id' => $request->user()->id, 'action' => $action,
            'target_type' => $targetType, 'target_id' => (string) $targetId,
            'request_id' => $request->header('X-Request-ID'),
            'context' => ['fingerprint' => $fingerprint], 'before_state' => $before,
            'after_state' => $after, 'occurred_at' => now('UTC'),
        ]);
    }

    private function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
