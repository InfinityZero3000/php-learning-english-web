<?php

use App\Http\Controllers\AdminGoogleAuthController;
use App\Http\Controllers\SocialController;
use App\Models\Attempt;
use App\Models\Vocabulary;
use App\Support\HealthCheck;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

$nextUrl = static function (string $origin, string $path, array $query = []): string {
    $url = rtrim($origin, '/').'/'.ltrim($path, '/');

    return $query === [] ? $url : $url.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
};

$allowedQuery = static fn (Request $request, array $keys): array => array_filter(
    $request->only($keys),
    static fn ($value): bool => is_scalar($value) && (string) $value !== '',
);

$learner = static fn (string $path, array $query = []) => redirect()->away(
    $nextUrl((string) config('app.frontend_url'), $path, $query),
);
$admin = static fn (string $path, array $query = []) => redirect()->away(
    $nextUrl((string) config('app.admin_frontend_url'), $path, $query),
);

Route::get('/', fn () => $learner('/'));

Route::get('/health', fn (HealthCheck $health) => $health->response())
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class]);

Route::get('/register', fn () => $learner('/register'))->name('register');
Route::get('/login', fn () => $learner('/login'))->name('login');
Route::get('/forgot-password', fn () => $learner('/forgot-password'))->name('password.request');
Route::get('/reset-password/{token}', fn (Request $request, string $token) => $learner('/reset-password', array_filter([
    'token' => $token,
    'email' => $request->query('email'),
], static fn ($value) => is_string($value) && $value !== '')))->name('password.reset');
Route::get('/verify-email', fn () => $learner('/verify-email'))->name('verification.notice');

Route::get('/auth/google/callback', [SocialController::class, 'googleCallback'])->name('google.callback');
Route::get('/auth/admin/google', [AdminGoogleAuthController::class, 'entry'])
    ->middleware('throttle:10,1')->name('admin.google.login');
Route::get('/auth/facebook', [SocialController::class, 'facebook'])->name('facebook.login');
Route::get('/auth/facebook/callback', [SocialController::class, 'facebookCallback'])->name('facebook.callback');

Route::middleware('auth')->group(function () use ($learner, $allowedQuery): void {
    Route::get('/profile', fn () => $learner('/profile'))->name('profile');
    Route::get('/words', fn (Request $request) => $learner('/vocabulary', $allowedQuery($request, ['search', 'topic_id', 'page'])))->name('words.index');
    Route::get('/words/{vocabulary}', fn (Vocabulary $vocabulary) => $learner('/vocabulary', ['word' => $vocabulary->id]))->name('words.show');
    Route::get('/bookmarks', fn () => $learner('/vocabulary', ['view' => 'saved']))->name('bookmarks.index');
    Route::get('/progress', fn () => $learner('/progress'))->name('progress.index');
    Route::get('/quizzes/{quiz}/attempt', fn (int $quiz) => $learner('/quiz', ['lesson_quiz' => $quiz]))->name('quizzes.attempt');
    Route::get('/quizzes/{quiz}/result', function (Request $request, int $quiz) use ($learner) {
        $attemptId = filter_var($request->query('attempt_id'), FILTER_VALIDATE_INT);
        abort_unless($attemptId && Attempt::query()->whereKey($attemptId)->where('quiz_id', $quiz)->where('user_id', $request->user()->id)->exists(), 404);

        return $learner('/quiz', ['attempt' => $attemptId]);
    })->name('quizzes.result');
});

Route::middleware(['auth', 'google.admin', 'can:manage-content'])->prefix('admin')->name('admin.')->group(function () use ($admin, $allowedQuery): void {
    Route::get('/', fn () => $admin('/dashboard'))->name('home');
    Route::get('/dashboard', fn () => $admin('/dashboard'))->name('dashboard');
    Route::get('/users', fn (Request $request) => $admin('/users', $allowedQuery($request, ['search', 'role', 'page'])))->name('users.index');
    Route::get('/users/{user}', fn (int $user) => $admin("/users/{$user}"))->name('users.show');

    Route::get('/courses', fn (Request $request) => $admin('/courses', $allowedQuery($request, ['search', 'status', 'level_id', 'page'])))->name('courses.index');
    Route::get('/courses/create', fn () => $admin('/courses', ['mode' => 'create']))->name('courses.create');
    Route::get('/courses/{course}', fn (int $course) => $admin('/courses', ['course' => $course, 'mode' => 'view']))->name('courses.show');
    Route::get('/courses/{course}/edit', fn (int $course) => $admin('/courses', ['course' => $course, 'mode' => 'edit']))->name('courses.edit');

    Route::get('/lessons', fn (Request $request) => $admin('/lessons', $allowedQuery($request, ['search', 'course_id', 'status', 'page'])))->name('lessons.index');
    Route::get('/lessons/create', fn (Request $request) => $admin('/lessons', array_merge(['mode' => 'create'], $allowedQuery($request, ['course_id']))))->name('lessons.create');
    Route::get('/lessons/{lesson}', fn (int $lesson) => $admin('/lessons', ['lesson' => $lesson, 'mode' => 'view']))->name('lessons.show');
    Route::get('/lessons/{lesson}/edit', fn (int $lesson) => $admin('/lessons', ['lesson' => $lesson, 'mode' => 'edit']))->name('lessons.edit');

    Route::get('/quizzes', fn (Request $request) => $admin('/quiz-management', $allowedQuery($request, ['search', 'lesson_id', 'status', 'page'])))->name('quizzes.index');
    Route::get('/quizzes/create', fn (Request $request) => $admin('/quiz-management', array_merge(['mode' => 'create'], $allowedQuery($request, ['lesson_id']))))->name('quizzes.create');
    Route::get('/quizzes/{quiz}', fn (int $quiz) => $admin('/quiz-management', ['quiz' => $quiz, 'mode' => 'view']))->name('quizzes.show');
    Route::get('/quizzes/{quiz}/edit', fn (int $quiz) => $admin('/quiz-management', ['quiz' => $quiz, 'mode' => 'edit']))->name('quizzes.edit');

    Route::get('/vocabularies', fn (Request $request) => $admin('/flashcards', $allowedQuery($request, ['search', 'page'])))->name('vocabularies.index');
    Route::get('/vocabularies/create', fn () => $admin('/flashcards', ['mode' => 'create']))->name('vocabularies.create');
    Route::get('/vocabularies/{vocabulary}', fn (int $vocabulary) => $admin('/flashcards', ['word' => $vocabulary, 'mode' => 'view']))->name('vocabularies.show');
    Route::get('/vocabularies/{vocabulary}/edit', fn (int $vocabulary) => $admin('/flashcards', ['word' => $vocabulary, 'mode' => 'edit']))->name('vocabularies.edit');
});
