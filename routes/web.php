<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AdminGoogleAuthController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\QuizAttemptController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\SocialController;
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
    return view('home');
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

// Đăng ký
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.store');

// Đăng nhập
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('login.store');
// Đăng nhập Google
Route::get('/auth/google', [SocialController::class, 'google'])
    ->name('google.login');

Route::get('/auth/google/callback', [SocialController::class, 'googleCallback'])
    ->name('google.callback');
Route::get('/auth/admin/google', [AdminGoogleAuthController::class, 'entry'])
    ->middleware('throttle:10,1')
    ->name('admin.google.login');
// Đăng nhập Facebook
Route::get('/auth/facebook', [SocialController::class, 'facebook'])
    ->name('facebook.login');

Route::get('/auth/facebook/callback', [SocialController::class, 'facebookCallback'])
    ->name('facebook.callback');
// Quên mật khẩu
Route::get('/forgot-password', [ForgotPasswordController::class, 'showForgotForm'])
    ->name('password.request');

Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])
    ->middleware('throttle:3,1')
    ->name('password.email');

Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])
    ->name('password.reset');

Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])
    ->name('password.update');

// Đăng xuất
Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout');

// Xác nhận email
Route::get('/verify-email', [EmailVerificationController::class, 'notice'])
    ->name('verification.notice');

Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:3,1')
    ->name('verification.send');

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

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
Route::middleware(['auth', 'google.admin', 'can:manage-content'])->prefix('admin')->name('admin.')->group(function () {
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
