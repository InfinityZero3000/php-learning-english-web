<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookmarkApiController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\OAuthController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProgressController;
use App\Http\Controllers\Api\V1\QuizController;
use App\Http\Controllers\Api\V1\VocabularyController;
use App\Support\HealthCheck;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::prefix('api/v1')->group(function (): void {
    Route::get('/', fn () => response()->json([
        'name' => config('app.name'),
        'version' => 'v1',
        'status' => 'ok',
        'health' => url('/api/v1/health'),
    ]));
    Route::get('/health', fn (HealthCheck $health) => $health->response('v1'))
        ->withoutMiddleware([
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
        ]);
    Route::get('/csrf-cookie', fn () => response()->noContent());
    Route::get('/vocabulary', [VocabularyController::class, 'index']);

    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/auth/oauth/{provider}', [OAuthController::class, 'redirect']);
    Route::get('/auth/oauth/{provider}/callback', [OAuthController::class, 'callback']);
    Route::post('/auth/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:3,1');
    Route::get('/auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('api.verification.verify');
    Route::post('/auth/password/forgot', [PasswordController::class, 'forgot'])->middleware('throttle:3,1');
    Route::post('/auth/password/reset', [PasswordController::class, 'reset']);

    Route::middleware('auth')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/password', [ProfileController::class, 'password']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);
    });

    // Public catalog routes
    Route::get('/catalog/courses', [CatalogController::class, 'courses']);
    Route::get('/catalog/courses/{course}', [CatalogController::class, 'course']);
    Route::get('/catalog/courses/{course}/lessons', [CatalogController::class, 'courseLessons']);
    Route::get('/catalog/lessons', [CatalogController::class, 'lessons']);
    Route::get('/catalog/lessons/{lesson}', [CatalogController::class, 'lesson']);

    // Authenticated learning routes
    Route::middleware('auth')->group(function (): void {
        // Bookmarks
        Route::get('/bookmarks', [BookmarkApiController::class, 'index']);
        Route::post('/bookmarks/vocabulary/{vocabulary}/toggle', [BookmarkApiController::class, 'toggleVocabulary']);
        Route::post('/bookmarks/lesson/{lesson}/toggle', [BookmarkApiController::class, 'toggleLesson']);

        // Quizzes
        Route::get('/quizzes/{quiz}', [QuizController::class, 'show']);
        Route::post('/quizzes/{quiz}/submit', [QuizController::class, 'submit']);
        Route::get('/quizzes/{quiz}/history', [QuizController::class, 'history']);

        // Progress & Dashboard
        Route::get('/progress', [ProgressController::class, 'myProgress']);
        Route::get('/progress/dashboard', [ProgressController::class, 'dashboard']);
        Route::get('/progress/course/{course}', [ProgressController::class, 'courseProgress']);
        Route::post('/progress/lesson/{lesson}/complete', [ProgressController::class, 'markCompleted']);
    });
});
