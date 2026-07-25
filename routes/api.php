<?php

use App\Http\Controllers\Api\AnswerController;
use App\Http\Controllers\Api\AttemptController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\StudyHistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/status', fn () => ['status' => 'ok', 'version' => 'v1']);

Route::middleware(['auth', 'throttle:api'])->group(function (): void {
    Route::apiResource('lessons', LessonController::class);
    Route::apiResource('quizzes', QuizController::class);
    Route::apiResource('quizzes.questions', QuestionController::class);
    Route::apiResource('quizzes.questions.answers', AnswerController::class);
    Route::post('quizzes/{quiz}/start', [AttemptController::class, 'start'])->name('quizzes.start');
    Route::post('attempts/{attempt}/answer', [AttemptController::class, 'answer'])->middleware('throttle:60,1')->name('attempts.answer');
    Route::post('attempts/{attempt}/finish', [AttemptController::class, 'finish'])->middleware('throttle:60,1')->name('attempts.finish');
    Route::get('attempts/{attempt}/result', [AttemptController::class, 'result'])->name('attempts.result');
    Route::patch('lessons/{lesson}/progress', [ProgressController::class, 'update'])->name('lessons.progress.update');
    Route::get('bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('vocabulary/{vocabulary}/bookmark', [BookmarkController::class, 'store'])->name('vocabulary.bookmark.store');
    Route::delete('vocabulary/{vocabulary}/bookmark', [BookmarkController::class, 'destroy'])->name('vocabulary.bookmark.destroy');
    Route::get('vocabulary/{vocabulary}', fn (\App\Models\Vocabulary $vocabulary) => $vocabulary)->name('vocabulary.show');
    Route::get('study-history', [StudyHistoryController::class, 'index'])->name('study-history.index');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
});
