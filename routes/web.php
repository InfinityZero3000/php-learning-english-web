<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuizAdminController;
use App\Http\Controllers\SocialController;
use App\Http\Controllers\VocabularyController;

use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Response;

/*
|--------------------------------------------------------------------------
| Trang chủ
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('home');
});

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::view('/admin', 'admin');

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
Route::get('/auth/google', [SocialController::class, 'google'])
    ->name('google.login');

Route::get('/auth/google/callback', [SocialController::class, 'googleCallback'])
    ->name('google.callback');
// Đăng nhập Facebook
Route::get('/auth/facebook', [SocialController::class, 'facebook'])
    ->name('facebook.login');

Route::get('/auth/facebook/callback', [SocialController::class, 'facebookCallback'])
    ->name('facebook.callback');
// Quên mật khẩu
Route::get('/forgot-password', [ForgotPasswordController::class, 'showForgotForm'])
    ->name('password.request');

Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])
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
    Route::get('/content', function () {
        $lessons = Lesson::query()
            ->withCount('quizzes')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        return view('content.index', compact('lessons'));
    })->name('content.index');

    Route::get('/content/lessons/{lesson}', function (Lesson $lesson) {
        abort_unless(auth()->user()->role?->slug === 'admin' || auth()->user()->role?->slug === 'learner', 403);

        return view('content.quizzes', ['lesson' => $lesson, 'quizzes' => $lesson->quizzes()->withCount('questions')->get()]);
    })->name('content.quizzes');

    Route::get('/content/lessons/{lesson}/quizzes/{quiz}', function (Lesson $lesson, Quiz $quiz) {
        abort_unless($quiz->lesson_id === $lesson->id, 404);
        abort_unless(auth()->user()->role?->slug === 'admin' || auth()->user()->role?->slug === 'learner', 403);

        return view('content.questions', ['lesson' => $lesson, 'quiz' => $quiz->load('questions.answers')]);
    })->name('content.questions');

    Route::post('/content/lessons/{lesson}/quizzes/{quiz}/import', [QuizAdminController::class, 'import'])
        ->middleware('auth')
        ->name('content.quiz.import');

    Route::get('/learning', fn () => view('learning.index', ['bookmarkCount' => DB::table('vocabulary_bookmarks')->where('user_id', auth()->id())->count(), 'history' => DB::table('study_history')->where('user_id', auth()->id())->latest('created_at')->limit(8)->get()]))->name('learning.index');
    
    Route::get('/words', [VocabularyController::class, 'index'])->name('vocabulary.index');
    Route::post('/vocabulary/{vocabulary}/upload', [VocabularyController::class, 'upload'])->name('vocabulary.upload');
    Route::get('/vocabulary/{vocabulary}/download', [VocabularyController::class, 'downloadImage'])->name('vocabulary.download');
    Route::put('/vocabulary/{vocabulary}', [VocabularyController::class, 'update'])->name('vocabulary.update');
    Route::delete('/vocabulary/{vocabulary}', [VocabularyController::class, 'destroy'])->name('vocabulary.delete');
    
    Route::get('/quizzes/{quiz}/play', fn (Quiz $quiz) => view('quiz.play', compact('quiz')))->name('quizzes.play');

    Route::get('/quizzes/{quiz}/results/download', function (Quiz $quiz) {
        $lines = [
            'quiz_id,' . $quiz->id,
            'quiz_title,' . $quiz->title,
            'user_id,user_name,score,status,finished_at',
        ];

        foreach ($quiz->attempts()->with('user')->get() as $attempt) {
            $lines[] = implode(',', [
                $attempt->user_id,
                str_replace(',', ' ', $attempt->user?->name ?? ''),
                $attempt->score,
                $attempt->status,
                $attempt->finished_at?->toDateTimeString() ?? '',
            ]);
        }

        $content = implode("\n", $lines);

        return response($content, 200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="quiz-results-' . $quiz->id . '.csv"');
    })->name('quizzes.results.download');

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
