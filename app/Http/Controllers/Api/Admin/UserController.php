<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\VocabularyReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const ROLE_TO_FRONTEND = ['admin' => 'ADMIN', 'learner' => 'USER'];

    private const FRONTEND_TO_SLUG = ['ADMIN' => 'admin', 'USER' => 'learner'];

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage', User::class);

        $validated = $request->validate([
            'email' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'locked'])],
            'role' => ['nullable', Rule::in(['USER', 'ADMIN', 'MODERATOR'])],
            'page' => ['nullable', 'integer', 'min:0'],
            'size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = (int) ($validated['page'] ?? 0);
        $size = (int) ($validated['size'] ?? 20);

        $query = User::query()->with('role');

        if (! empty($validated['email'])) {
            $term = $validated['email'];
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }

        if (($validated['status'] ?? null) === 'active') {
            $query->whereNull('locked_at');
        } elseif (($validated['status'] ?? null) === 'locked') {
            $query->whereNotNull('locked_at');
        }

        if (! empty($validated['role'])) {
            $slug = self::FRONTEND_TO_SLUG[$validated['role']] ?? '__none__';
            $query->whereHas('role', fn ($q) => $q->where('slug', $slug));
        }

        $paginator = $query->orderBy('id')->paginate($size, ['*'], 'page', $page + 1);

        return response()->json([
            'users' => $paginator->getCollection()->map(fn (User $user) => $this->mapUser($user))->all(),
            'totalElements' => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
            'page' => $page,
            'size' => $size,
        ]);
    }

    public function show(int $user): JsonResponse
    {
        Gate::authorize('manage', User::class);

        $target = User::with('role')->findOrFail($user);

        $totalWords = UserVocabulary::where('user_id', $target->id)->count();
        $mastered = UserVocabulary::where('user_id', $target->id)->where('state', 'review')->count();
        $learning = UserVocabulary::where('user_id', $target->id)->where('state', 'learning')->count();

        $reviewCounts = VocabularyReview::whereHas('userVocabulary', fn ($q) => $q->where('user_id', $target->id))
            ->selectRaw('SUM(CASE WHEN rating >= 3 THEN 1 ELSE 0 END) as correct, SUM(CASE WHEN rating < 3 THEN 1 ELSE 0 END) as incorrect')
            ->first();

        $correct = (int) ($reviewCounts->correct ?? 0);
        $incorrect = (int) ($reviewCounts->incorrect ?? 0);
        $total = $correct + $incorrect;

        return response()->json([
            'user' => $this->mapUser($target),
            'stats' => [
                'totalWords' => $totalWords,
                'mastered' => $mastered,
                'learning' => $learning,
                'accuracy' => $total > 0 ? round($correct / $total * 100, 1) : 0,
                'correctAnswers' => $correct,
                'incorrectAnswers' => $incorrect,
            ],
            'streak' => [
                'currentStreak' => 0,
                'longestStreak' => 0,
                'totalDaysStudied' => 0,
                'lastStudyDate' => null,
            ],
            'decks' => [],
        ]);
    }

    public function history(Request $request, int $user): JsonResponse
    {
        Gate::authorize('manage', User::class);

        $target = User::findOrFail($user);

        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min(100, $limit));

        $reviews = VocabularyReview::query()
            ->whereHas('userVocabulary', fn ($q) => $q->where('user_id', $target->id))
            ->with('userVocabulary.vocabulary')
            ->orderByDesc('reviewed_at')
            ->limit($limit)
            ->get();

        return response()->json($reviews->map(fn (VocabularyReview $review) => [
            'id' => $review->id,
            'word' => $review->userVocabulary?->vocabulary?->word ?? '',
            'rating' => $review->rating,
            'correct' => $review->rating >= 3,
            'reviewedAt' => optional($review->reviewed_at)->toIso8601String(),
        ])->all());
    }

    public function lock(Request $request, int $user): JsonResponse
    {
        Gate::authorize('manage', User::class);

        $target = User::findOrFail($user);

        if ($target->locked_at === null) {
            $adminRoleId = Role::where('slug', 'admin')->value('id');

            if ((int) $target->role_id === (int) $adminRoleId) {
                if ($target->is($request->user())) {
                    throw ValidationException::withMessages(['user' => 'Không thể tự khóa tài khoản của chính bạn.']);
                }

                $remainingActiveAdmins = User::where('role_id', $adminRoleId)
                    ->whereNull('locked_at')
                    ->where('id', '!=', $target->id)
                    ->count();

                if ($remainingActiveAdmins === 0) {
                    throw ValidationException::withMessages(['user' => 'Không thể khóa quản trị viên đang hoạt động cuối cùng.']);
                }
            }

            $target->forceFill(['locked_at' => now()])->save();
            AuditLog::record($request->user(), 'USER_LOCKED', "User #{$target->id}", "Locked account {$target->email}");
        }

        return response()->json($this->mapUser($target->fresh('role')));
    }

    public function unlock(Request $request, int $user): JsonResponse
    {
        Gate::authorize('manage', User::class);

        $target = User::findOrFail($user);

        if ($target->locked_at !== null) {
            $target->forceFill(['locked_at' => null])->save();
            AuditLog::record($request->user(), 'USER_UNLOCKED', "User #{$target->id}", "Unlocked account {$target->email}");
        }

        return response()->json($this->mapUser($target->fresh('role')));
    }

    public function resetPassword(Request $request, int $user): JsonResponse
    {
        Gate::authorize('manage', User::class);

        $target = User::findOrFail($user);

        $plain = Str::password(16);
        $target->forceFill(['password' => Hash::make($plain)])->save();

        AuditLog::record($request->user(), 'PASSWORD_RESET', "User #{$target->id}", "Password reset for {$target->email}");

        return response()->json(['temporaryPassword' => $plain]);
    }

    public function updateRole(Request $request, int $user): JsonResponse
    {
        Gate::authorize('manage', User::class);

        $target = User::findOrFail($user);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['USER', 'ADMIN'])],
        ]);

        $slug = self::FRONTEND_TO_SLUG[$validated['role']];
        $roleId = Role::where('slug', $slug)->value('id');

        if (! $roleId) {
            throw ValidationException::withMessages(['role' => 'Hệ thống chưa được cấu hình vai trò này.']);
        }

        $adminRoleId = Role::where('slug', 'admin')->value('id');
        if ((int) $roleId !== (int) $adminRoleId && (int) $target->role_id === (int) $adminRoleId) {
            if ($target->is($request->user())) {
                throw ValidationException::withMessages([
                    'role' => 'Không thể hạ quyền quản trị viên cuối cùng hoặc tự hạ quyền tài khoản này.',
                ]);
            }

            $remainingActiveAdmins = User::where('role_id', $adminRoleId)
                ->whereNull('locked_at')
                ->where('id', '!=', $target->id)
                ->count();

            if ($target->locked_at === null && $remainingActiveAdmins === 0) {
                throw ValidationException::withMessages([
                    'role' => 'Không thể hạ quyền quản trị viên cuối cùng hoặc tự hạ quyền tài khoản này.',
                ]);
            }
        }

        $target->update(['role_id' => $roleId]);

        AuditLog::record($request->user(), 'ROLE_CHANGED', "User #{$target->id}", "Changed role to {$validated['role']}");

        return response()->json($this->mapUser($target->fresh('role')));
    }

    private function mapUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'avatarUrl' => '',
            'role' => self::ROLE_TO_FRONTEND[$user->role?->slug] ?? 'USER',
            'locked' => $user->locked_at !== null,
            'createdAt' => optional($user->created_at)->toIso8601String(),
            'lastLoginAt' => optional($user->last_login_at)->toIso8601String(),
        ];
    }
}
