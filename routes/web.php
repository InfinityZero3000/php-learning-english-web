<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\QuizAttemptController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\VocabularyController;
use App\Http\Controllers\WordsController;
use App\Support\HealthCheck;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
|--------------------------------------------------------------------------
| Trang chủ
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return app()->isProduction()
        ? redirect()->away(config('app.frontend_url'))
        : view('home');
});

Route::get('/health', fn (HealthCheck $health) => $health->response())
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ]);

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::get('/register', fn () => redirect()->away(config('app.frontend_url').'/register'))->name('register');
Route::get('/login', fn () => redirect()->away(config('app.frontend_url').'/login'))->name('login');
Route::get('/forgot-password', fn () => redirect()->away(config('app.frontend_url').'/forgot-password'))->name('password.request');
Route::get('/reset-password/{token}', fn (string $token) => redirect()->away(
    config('app.frontend_url').'/reset-password?'.http_build_query(['token' => $token, 'email' => request()->query('email', '')])
))->name('password.reset');

// Đăng xuất
Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout');

Route::get('/verify-email', fn () => redirect()->away(config('app.frontend_url').'/verify-email'))
    ->name('verification.notice');

/*
|--------------------------------------------------------------------------
| Profile (Yêu cầu đăng nhập)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // Hồ sơ người học
    Route::get('/profile', [ProfileController::class, 'index'])
        ->name('profile');

    // Cập nhật hồ sơ
    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    // Xóa tài khoản
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

// ==========================================
// Admin Routes (Yêu cầu quyền admin)
// ==========================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => to_route('admin.dashboard'))->name('home');
    Route::get('/dashboard', [UserController::class, 'index'])->name('dashboard');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.updateRole');

    // CRUD Khóa học
    Route::resource('courses', CourseController::class);

    // CRUD Bài học
    Route::resource('lessons', LessonController::class);

    // CRUD Trắc nghiệm (gồm câu hỏi & đáp án)
    Route::resource('quizzes', QuizController::class);

    // CRUD Từ vựng
    Route::resource('vocabularies', VocabularyController::class);
});

// ==========================================
// Learner Routes (Yêu cầu đăng nhập thông thường)
// ==========================================
Route::middleware(['auth'])->group(function () {
    // Làm bài quiz
    Route::get('/quizzes/{quiz}/attempt', [QuizAttemptController::class, 'show'])->name('quizzes.attempt');
    Route::post('/quizzes/{quiz}/attempt', [QuizAttemptController::class, 'submit'])->name('quizzes.submit');
    Route::get('/quizzes/{quiz}/result', [QuizAttemptController::class, 'result'])->name('quizzes.result');

    // Từ vựng (học viên xem)
    Route::get('/words', [WordsController::class, 'index'])->name('words.index');
    Route::get('/words/{vocabulary}', [WordsController::class, 'show'])->name('words.show');

    // Tiến độ học
    Route::get('/progress', [ProgressController::class, 'index'])->name('progress.index');
    Route::post('/lessons/{lesson}/complete', [ProgressController::class, 'markComplete'])->name('lessons.complete');

    // Bookmark từ vựng
    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('/bookmarks/{vocabulary}/toggle', [BookmarkController::class, 'toggle'])->name('bookmarks.toggle');
    Route::delete('/bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])->name('bookmarks.destroy');
});
