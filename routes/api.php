<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\VocabularyController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\TopicController;
use App\Http\Controllers\Api\VocabularySetController;
use App\Http\Controllers\Api\FlashcardController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// SPA CSRF cookie
Route::get('/v1/csrf-cookie', function () {
    return response()->noContent();
});

// Auth routes (v1)
Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/password/reset', [AuthController::class, 'resetPassword']);

    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/auth/refresh', [AuthController::class, 'refreshToken']);

        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/profile/password', [AuthController::class, 'changePassword']);
        Route::delete('/profile', [AuthController::class, 'destroyAccount']);
    });
});

// Courses (CRUD)
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{course}', [CourseController::class, 'update']);
    Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
});

// Levels
Route::get('/levels', [CourseController::class, 'levels']);
Route::get('/levels/{level}', [CourseController::class, 'levelShow']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/levels', [CourseController::class, 'levelStore']);
    Route::put('/levels/{level}', [CourseController::class, 'levelUpdate']);
    Route::delete('/levels/{level}', [CourseController::class, 'levelDestroy']);
});

// Words (CRUD + queries)
Route::get('/words', [VocabularyController::class, 'index']);
Route::get('/words/count', [VocabularyController::class, 'count']);
Route::get('/words/categories', [VocabularyController::class, 'categories']);
Route::get('/words/parts-of-speech', [VocabularyController::class, 'partsOfSpeech']);
Route::get('/words/random', [VocabularyController::class, 'random']);
Route::get('/words/{vocabulary}', [VocabularyController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/words', [VocabularyController::class, 'store']);
    Route::put('/words/{vocabulary}', [VocabularyController::class, 'update']);
    Route::delete('/words/{vocabulary}', [VocabularyController::class, 'destroy']);
});

// Topics
Route::get('/topics', [TopicController::class, 'index']);
Route::get('/topics/{topic}', [TopicController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/topics', [TopicController::class, 'store']);
    Route::put('/topics/{topic}', [TopicController::class, 'update']);
    Route::delete('/topics/{topic}', [TopicController::class, 'destroy']);
});

// FSRS
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/fsrs/stats', [ProgressController::class, 'fsrsStats']);
    Route::get('/fsrs/due', [ProgressController::class, 'dueList']);
    Route::post('/fsrs/review', [ProgressController::class, 'reviewWord']);
});

// Progress
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/progress', [ProgressController::class, 'progressList']);
    Route::get('/progress/stats', [ProgressController::class, 'stats']);
    Route::get('/progress/review', [ProgressController::class, 'reviewQueue']);
});

// Quiz
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/quiz/stats', [QuizController::class, 'stats']);
    Route::get('/quiz/sessions/recent', [QuizController::class, 'recentSessions']);
    Route::post('/quiz/start', [QuizController::class, 'start']);
    Route::post('/quiz/session/{session}/answer', [QuizController::class, 'answer']);
    Route::post('/quiz/session/{session}/complete', [QuizController::class, 'complete']);
});

// Streak
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/streak', [ProgressController::class, 'getStreak']);
    Route::post('/streak/check-in', [ProgressController::class, 'checkIn']);
});

// Vocabulary Sets
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sets', [VocabularySetController::class, 'index']);
    Route::post('/sets', [VocabularySetController::class, 'store']);
    Route::get('/sets/{vocabularySet}', [VocabularySetController::class, 'show']);
    Route::put('/sets/{vocabularySet}', [VocabularySetController::class, 'update']);
    Route::delete('/sets/{vocabularySet}', [VocabularySetController::class, 'destroy']);
    Route::get('/sets/{vocabularySet}/words', [VocabularySetController::class, 'listWords']);
    Route::post('/sets/{vocabularySet}/words/{wordId}', [VocabularySetController::class, 'addSingleWord']);
    Route::delete('/sets/{vocabularySet}/words/{wordId}', [VocabularySetController::class, 'removeSingleWord']);
});

// Notifications
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markReadSingle']);
    Route::post('/notifications/mark-read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::get('/notifications/settings', [NotificationController::class, 'settings']);
    Route::put('/notifications/settings', [NotificationController::class, 'updateSettings']);
});

// Flashcards
Route::get('/flashcards', [FlashcardController::class, 'index']);
Route::get('/flashcards/sources', [FlashcardController::class, 'sources']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/flashcards/import', [FlashcardController::class, 'importFromSource']);
    Route::post('/flashcards/{id}/save-to-vocab', [FlashcardController::class, 'saveToVocab']);
});

// Enrichment
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/enrichment/words/{id}', [VocabularyController::class, 'enrich']);
    Route::post('/enrichment/translate', [VocabularyController::class, 'translateRows']);
});

// Dictionary
Route::get('/dictionary/lookup/{word}', [VocabularyController::class, 'dictionaryLookup']);

// Word of the Day
Route::get('/word-of-the-day', [VocabularyController::class, 'wordOfTheDay']);

// Import
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/import/words/commit', [ImportController::class, 'commitImport']);
    Route::get('/import/jobs', [ImportController::class, 'jobs']);
    Route::get('/import/jobs/{importJob}', [ImportController::class, 'jobStatus']);
});

// Content
Route::get('/content/youtube', function () {
    return response()->json(['data' => []]);
});
Route::get('/content/news', function () {
    return response()->json(['data' => []]);
});
Route::get('/content/combined', function () {
    return response()->json(['data' => []]);
});

// Admin routes
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::get('/users/{user}', [AdminController::class, 'getUser']);
    Route::get('/users/{user}/history', [AdminController::class, 'userHistory']);
    Route::put('/users/{user}/lock', [AdminController::class, 'lockUser']);
    Route::put('/users/{user}/unlock', [AdminController::class, 'unlockUser']);
    Route::post('/users/{user}/reset-password', [AdminController::class, 'resetPassword']);
    Route::put('/users/{user}/role', [AdminController::class, 'assignRole']);
});