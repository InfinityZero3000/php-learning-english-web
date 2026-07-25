<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CourseAdminController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\LessonAdminController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LevelAdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuizAdminController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\SocialController;
use App\Http\Controllers\TopicAdminController;
use App\Http\Controllers\VocabularyAdminController;
use App\Http\Controllers\VocabularyController;
use App\Services\CourseService;
use App\Services\DashboardService;
use App\Services\QuizService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Trang chủ
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/health', fn () => response()->json(['status' => 'ok']));

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

// Courses (public)
Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');

// Lessons (public - view, but complete requires auth)
Route::get('/lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');

// Vocabularies (public browse)
Route::get('/vocabularies', [VocabularyController::class, 'index'])->name('vocabularies.index');

// Quizzes (public list + play)
Route::get('/quizzes', [QuizController::class, 'index'])->name('quizzes.index');
Route::get('/quizzes/{quiz}', [QuizController::class, 'show'])->name('quizzes.show');
Route::get('/quizzes/{quiz}/play', [QuizController::class, 'play'])->name('quizzes.play');

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', function (DashboardService $dashboardService, CourseService $courseService, QuizService $quizService) {
        $stats = $dashboardService->getGeneralStats();
        $chartData = $dashboardService->getAdminChartData();
        $courses = $courseService->getAll();
        return view('admin.dashboard', compact('stats', 'chartData', 'courses'));
    })->name('dashboard');

    // Courses
    Route::resource('courses', CourseAdminController::class)->except(['show']);
    // Levels
    Route::resource('levels', LevelAdminController::class)->except(['show']);
    // Topics
    Route::resource('topics', TopicAdminController::class)->except(['show']);
    // Lessons
    Route::resource('lessons', LessonAdminController::class)->except(['show']);
    // Vocabularies
    Route::resource('vocabularies', VocabularyAdminController::class)->except(['show']);
    // Quizzes
    Route::resource('quizzes', QuizAdminController::class)->except(['show']);
    // Quiz Questions
    Route::get('quizzes/{quiz}/questions', [QuizAdminController::class, 'questions'])->name('quizzes.questions');
    Route::post('quizzes/{quiz}/questions', [QuizAdminController::class, 'storeQuestion'])->name('quizzes.questions.store');
    Route::put('questions/{question}', [QuizAdminController::class, 'updateQuestion'])->name('quizzes.questions.update');
    Route::delete('questions/{question}', [QuizAdminController::class, 'destroyQuestion'])->name('quizzes.questions.destroy');
    Route::post('quizzes/{quiz}/import', [QuizAdminController::class, 'import'])->name('quizzes.import');
    // Users
    Route::resource('users', AdminUserController::class)->only(['index', 'show', 'destroy']);
    Route::put('users/{user}/assign-role', [AdminUserController::class, 'assignRole'])->name('users.assign-role');
    Route::post('users/{user}/lock', [AdminUserController::class, 'lock'])->name('users.lock');
    Route::post('users/{user}/unlock', [AdminUserController::class, 'unlock'])->name('users.unlock');
    Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('users.reset-password');
});

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
Route::post('/login', [AuthController::class, 'login'])->name('login.store');

// Đăng nhập Google
Route::get('/auth/google', [SocialController::class, 'google'])->name('google.login');
Route::get('/auth/google/callback', [SocialController::class, 'googleCallback'])->name('google.callback');

// Đăng nhập Facebook
Route::get('/auth/facebook', [SocialController::class, 'facebook'])->name('facebook.login');
Route::get('/auth/facebook/callback', [SocialController::class, 'facebookCallback'])->name('facebook.callback');

// Quên mật khẩu
Route::get('/forgot-password', [ForgotPasswordController::class, 'showForgotForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])->name('password.update');

// Đăng xuất
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Xác nhận email
Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])->name('verification.send');
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    // Dashboard / Learning
    Route::get('/dashboard', function (DashboardService $dashboardService) {
        $stats = $dashboardService->getUserDashboard(auth()->id());
        return view('dashboard', compact('stats'));
    })->name('dashboard');

    // Lessons
    Route::post('/lessons/{lesson}/complete', [LessonController::class, 'complete'])->name('lessons.complete');

    // Quizzes submit + result
    Route::post('/quizzes/{quiz}/submit', [QuizController::class, 'submit'])->name('quizzes.submit');
    Route::get('/attempts/{attempt}/result', [QuizController::class, 'result'])->name('quizzes.result');

    // Vocabulary management (create, upload, update, delete)
    Route::post('/vocabularies', [VocabularyController::class, 'store'])->name('vocabularies.store');
    Route::post('/vocabularies/{vocabulary}/upload', [VocabularyController::class, 'upload'])->name('vocabularies.upload');
    Route::put('/vocabularies/{vocabulary}', [VocabularyController::class, 'update'])->name('vocabularies.update');
    Route::delete('/vocabularies/{vocabulary}', [VocabularyController::class, 'destroy'])->name('vocabularies.destroy');

    // Vocabulary import
    Route::get('/vocabularies/import', [VocabularyController::class, 'importForm'])->name('vocabularies.import');
    Route::post('/vocabularies/import', [VocabularyController::class, 'import'])->name('vocabularies.import.store');

    // Flashcards
    Route::get('/vocabularies/flashcards', [VocabularyController::class, 'flashcards'])->name('vocabularies.flashcards');

    // Bookmarks
    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('/vocabularies/{vocabulary}/bookmark', [BookmarkController::class, 'toggle'])->name('bookmarks.toggle');

    // Profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Words (alias)
    Route::get('/words', [VocabularyController::class, 'index'])->name('words.index');
});