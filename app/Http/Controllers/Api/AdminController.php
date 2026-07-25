<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vocabulary;
use App\Models\Topic;
use App\Models\QuizSession;
use App\Models\ImportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function listUsers(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('locked')) {
            $query->where('is_locked', filter_var($request->locked, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min((int) ($request->per_page ?? $request->size ?? 20), 100);
        $users = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($users);
    }

    public function getUser(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function userHistory(User $user): JsonResponse
    {
        $sessions = QuizSession::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'user' => $user->only('id', 'name', 'email', 'created_at'),
            'quizSessions' => $sessions,
            'totalSessions' => $sessions->count(),
        ]);
    }

    public function lockUser(User $user): JsonResponse
    {
        $user->update(['is_locked' => true]);
        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json(['message' => 'User locked', 'user' => $user->fresh()]);
    }

    public function unlockUser(User $user): JsonResponse
    {
        $user->update(['is_locked' => false]);

        return response()->json(['message' => 'User unlocked', 'user' => $user->fresh()]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['message' => 'Password reset successfully']);
    }

    public function assignRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role' => 'required|in:admin,user,moderator',
        ]);

        $user->update(['role' => $request->role]);

        return response()->json(['message' => 'Role updated', 'user' => $user->fresh()]);
    }

    // Dashboard stats
    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'users' => [
                'total' => User::count(),
                'active' => User::where('is_locked', false)->count(),
                'locked' => User::where('is_locked', true)->count(),
                'new_this_month' => User::whereMonth('created_at', now()->month)->count(),
            ],
            'vocabulary' => [
                'total' => Vocabulary::count(),
                'by_difficulty' => [
                    'BEGINNER' => Vocabulary::where('difficulty', 'BEGINNER')->count(),
                    'INTERMEDIATE' => Vocabulary::where('difficulty', 'INTERMEDIATE')->count(),
                    'ADVANCED' => Vocabulary::where('difficulty', 'ADVANCED')->count(),
                ],
            ],
            'quizzes' => [
                'total_sessions' => QuizSession::count(),
                'completed' => QuizSession::where('completed', true)->count(),
                'active' => QuizSession::where('completed', false)->count(),
            ],
            'topics' => [
                'total' => Topic::count(),
            ],
        ]);
    }

    // Admin CRUD for topics
    public function listTopics(Request $request): JsonResponse
    {
        $topics = Topic::withCount('vocabularies')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($topics);
    }

    public function createTopic(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        $topic = Topic::create($validated);

        return response()->json($topic, 201);
    }

    public function updateTopic(Request $request, Topic $topic): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        $topic->update($validated);

        return response()->json($topic);
    }

    public function deleteTopic(Topic $topic): JsonResponse
    {
        $topic->delete();
        return response()->json(null, 204);
    }

    // Admin CRUD for vocabulary
    public function listVocabularies(Request $request): JsonResponse
    {
        $query = Vocabulary::query()->with('topic');

        if ($request->filled('search')) {
            $query->where('word', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }
        if ($request->filled('cefr_level')) {
            $query->where('cefr_level', $request->cefr_level);
        }

        $perPage = min((int) ($request->per_page ?? $request->size ?? 20), 100);
        $vocabs = $query->orderByDesc('id')->paginate($perPage);

        return response()->json($vocabs);
    }

    public function createVocabulary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'word' => 'required|string|max:255',
            'translation' => 'nullable|string',
            'pronunciation' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'difficulty' => 'nullable|in:BEGINNER,INTERMEDIATE,ADVANCED',
            'cefr_level' => 'nullable|in:A1,A2,B1,B2,C1,C2',
            'part_of_speech' => 'nullable|string|max:100',
            'image_url' => 'nullable|string|max:500',
            'topic_id' => 'nullable|exists:topics,id',
        ]);

        $vocabulary = Vocabulary::create($validated);
        $vocabulary->load('topic');

        return response()->json($vocabulary, 201);
    }

    public function updateVocabulary(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $validated = $request->validate([
            'word' => 'sometimes|string|max:255',
            'translation' => 'nullable|string',
            'pronunciation' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'difficulty' => 'nullable|in:BEGINNER,INTERMEDIATE,ADVANCED',
            'cefr_level' => 'nullable|in:A1,A2,B1,B2,C1,C2',
            'part_of_speech' => 'nullable|string|max:100',
            'image_url' => 'nullable|string|max:500',
            'topic_id' => 'nullable|exists:topics,id',
        ]);

        $vocabulary->update($validated);
        $vocabulary->load('topic');

        return response()->json($vocabulary);
    }

    public function deleteVocabulary(Vocabulary $vocabulary): JsonResponse
    {
        $vocabulary->delete();
        return response()->json(null, 204);
    }

    // Import jobs admin
    public function listImports(Request $request): JsonResponse
    {
        $jobs = ImportJob::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($jobs);
    }

    public function getImport(ImportJob $importJob): JsonResponse
    {
        $importJob->load('user:id,name,email');
        return response()->json($importJob);
    }
}