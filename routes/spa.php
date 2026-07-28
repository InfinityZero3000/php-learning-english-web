<?php

use App\Http\Controllers\Api\V1\Admin\OperationsController;
use App\Http\Controllers\Api\V1\AiProxyController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\FsrsController;
use App\Http\Controllers\Api\V1\LearningSessionController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProgressController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\TraceCagController;
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
    Route::get('/catalog/categories', [CatalogController::class, 'categories']);
    Route::get('/catalog/courses', [CatalogController::class, 'courses']);
    Route::get('/catalog/courses/{course}', [CatalogController::class, 'course']);
    Route::get('/catalog/courses/{course}/lessons', [CatalogController::class, 'courseLessons']);
    Route::get('/catalog/lessons', [CatalogController::class, 'lessons']);
    Route::get('/catalog/lessons/{lesson}', [CatalogController::class, 'lesson']);

    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
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
        Route::get('/fsrs/due', [FsrsController::class, 'due']);
        Route::get('/fsrs/stats', [FsrsController::class, 'stats']);
        Route::post('/fsrs/review', [FsrsController::class, 'review']);
        Route::get('/enrollments', [EnrollmentController::class, 'index']);
        Route::post('/enrollments', [EnrollmentController::class, 'store']);
        Route::get('/learning/plan', [LearningSessionController::class, 'plan']);
        Route::post('/learning/sessions', [LearningSessionController::class, 'store']);
        Route::get('/learning/sessions/{session}/next', [LearningSessionController::class, 'next']);
        Route::post('/learning/sessions/{session}/attempts', [LearningSessionController::class, 'attempt']);
        Route::post('/learning/sessions/{session}/complete', [LearningSessionController::class, 'complete']);
        Route::get('/progress', [ProgressController::class, 'myProgress']);
        Route::get('/progress/dashboard', [ProgressController::class, 'dashboard']);
        Route::get('/progress/course/{course}', [ProgressController::class, 'courseProgress']);
        Route::post('/progress/lesson/{lesson}/complete', [ProgressController::class, 'markCompleted']);
        Route::middleware('throttle:20,1')->group(function (): void {
            Route::post('/ai/translate', [AiProxyController::class, 'translate']);
            Route::post('/ai/pronunciation', [AiProxyController::class, 'pronunciation']);
            Route::post('/ai/speech-to-text', [AiProxyController::class, 'speechToText']);
            Route::post('/ai/text-to-speech', [AiProxyController::class, 'textToSpeech']);
            Route::post('/ai/trace-cag', [TraceCagController::class, 'analyze']);
        });
        Route::get('/teacher/learners', [TeacherController::class, 'learners']);
        Route::get('/teacher/learners/{learner}', [TeacherController::class, 'learner']);
        Route::get('/teacher/learners/{learner}/progress', [TeacherController::class, 'progress']);
        Route::get('/teacher/learners/{learner}/evidence', [TeacherController::class, 'evidence']);
        Route::get('/teacher/alerts', [TeacherController::class, 'alerts']);
        Route::get('/teacher/alerts/{alert}', [TeacherController::class, 'alert']);
        Route::post('/teacher/alerts/{alert}/resolve', [TeacherController::class, 'resolve']);
        Route::get('/teacher/assignments', [TeacherController::class, 'assignments']);
        Route::post('/teacher/assignments', [TeacherController::class, 'createAssignment']);
        Route::post('/teacher/intervention-notes', [TeacherController::class, 'note']);
        Route::get('/admin/operations', [OperationsController::class, 'overview']);
        Route::post('/admin/operations/service-probes', [OperationsController::class, 'probe']);
        Route::get('/admin/operations/contracts', [OperationsController::class, 'contracts']);
        Route::get('/admin/operations/usage', [OperationsController::class, 'usage']);
        Route::get('/admin/operations/quota-policy', [OperationsController::class, 'quotaPolicy']);
        Route::put('/admin/operations/quota-policy', [OperationsController::class, 'createQuota']);
        Route::get('/admin/operations/alert-rules', [OperationsController::class, 'rules']);
        Route::put('/admin/operations/alert-rules/{alertRule}', [OperationsController::class, 'updateRule']);
        Route::get('/admin/operations/audit-events', [OperationsController::class, 'audits']);
    });
});
