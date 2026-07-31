<?php

use App\Http\Controllers\AdminGoogleAuthController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\UserController as LegacyAdminUserController;
use App\Http\Controllers\Api\V1\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Api\V1\Admin\ContentOperationsController as AdminContentOperationsController;
use App\Http\Controllers\Api\V1\Admin\CourseAdminController;
use App\Http\Controllers\Api\V1\Admin\CourseCategoryController;
use App\Http\Controllers\Api\V1\Admin\LearningController as AdminLearningController;
use App\Http\Controllers\Api\V1\Admin\LessonAdminController;
use App\Http\Controllers\Api\V1\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Api\V1\Admin\LevelController;
use App\Http\Controllers\Api\V1\Admin\MediaController;
use App\Http\Controllers\Api\V1\Admin\OperationsController;
use App\Http\Controllers\Api\V1\Admin\QuizAdminController;
use App\Http\Controllers\Api\V1\Admin\QuizController as AdminQuizController;
use App\Http\Controllers\Api\V1\Admin\TopicController;
use App\Http\Controllers\Api\V1\Admin\UnitController as AdminUnitController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Admin\VocabularyAdminController;
use App\Http\Controllers\Api\V1\AiProxyController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookmarkApiController;
use App\Http\Controllers\Api\V1\BookmarkController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\FsrsController;
use App\Http\Controllers\Api\V1\LearnerToolsController;
use App\Http\Controllers\Api\V1\LearningSessionController;
use App\Http\Controllers\Api\V1\LessonQuizController;
use App\Http\Controllers\Api\V1\LexiLingoContentController;
use App\Http\Controllers\Api\V1\MediaListeningController;
use App\Http\Controllers\Api\V1\OAuthController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProgressController;
use App\Http\Controllers\Api\V1\QuizController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\TraceCagController;
use App\Http\Controllers\Api\V1\VocabularyController;
use App\Http\Controllers\Api\V1\VocabularyQuizController;
use App\Models\Vocabulary;
use App\Services\VocabularyEnrichmentService;
use App\Support\ApiResponse;
use App\Support\HealthCheck;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
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
    Route::get('/vocabulary/{vocabulary}', [VocabularyController::class, 'show']);
    Route::get('/catalog/topics', [CatalogController::class, 'topics']);
    Route::get('/catalog/categories', [CatalogController::class, 'categories']);
    Route::get('/catalog/courses', [CatalogController::class, 'courses']);
    Route::get('/catalog/courses/{course}', [CatalogController::class, 'course']);
    Route::get('/catalog/courses/{course}/lessons', [CatalogController::class, 'courseLessons']);
    Route::get('/catalog/lessons', [CatalogController::class, 'lessons']);
    Route::get('/catalog/lessons/{lesson}', [CatalogController::class, 'lesson']);
    Route::get('/content/news', [LexiLingoContentController::class, 'news']);
    Route::get('/content/youtube', [LexiLingoContentController::class, 'youtube']);

    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/auth/oauth/{provider}', [OAuthController::class, 'redirect'])->middleware('throttle:10,1');
    Route::get('/auth/oauth/google/admin', [AdminGoogleAuthController::class, 'entry'])->middleware('throttle:10,1');
    Route::get('/auth/oauth/google/admin/start', [AdminGoogleAuthController::class, 'redirect'])->middleware('throttle:10,1');
    Route::get('/auth/oauth/{provider}/callback', [OAuthController::class, 'callback'])->middleware('throttle:10,1');
    Route::post('/auth/oauth/google/admin/handoff', [AdminGoogleAuthController::class, 'handoff'])->middleware('throttle:10,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/auth/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:3,1');
    Route::get('/auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('api.verification.verify');
    Route::post('/auth/password/forgot', [PasswordController::class, 'forgot'])->middleware('throttle:3,1');
    Route::post('/auth/password/reset', [PasswordController::class, 'reset']);

    Route::middleware(['auth', 'can:manage-content'])->prefix('admin')->group(function (): void {
        Route::apiResource('topics', TopicController::class);
        Route::apiResource('levels', LevelController::class);
        Route::apiResource('categories', CourseCategoryController::class);
        Route::apiResource('courses', CourseAdminController::class);
        Route::apiResource('lessons', LessonAdminController::class);
        Route::apiResource('vocabulary', VocabularyAdminController::class);
        Route::apiResource('quizzes', QuizAdminController::class);
        Route::post('/media/upload', [MediaController::class, 'upload']);
        Route::delete('/media/{path?}', [MediaController::class, 'destroy'])->where('path', '.*');
    });

    Route::middleware('auth')->group(function (): void {
        Route::get('/catalog/courses/{course}/path', [CatalogController::class, 'coursePath']);
        Route::get('/bookmarks', [BookmarkApiController::class, 'index']);
        Route::post('/bookmarks/vocabulary/{vocabulary}/toggle', [BookmarkApiController::class, 'toggleVocabulary']);
        Route::post('/bookmarks/lesson/{lesson}/toggle', [BookmarkApiController::class, 'toggleLesson']);
        Route::post('/enrichment/words/{vocabulary}', fn (Vocabulary $vocabulary, VocabularyEnrichmentService $service) => response()->json([
            'data' => $service->enrich($vocabulary),
        ]))->middleware('can:manage-content');
        Route::put('/vocabulary/{vocabulary}/bookmark', [BookmarkController::class, 'update']);
        Route::get('/lesson-quizzes', [LessonQuizController::class, 'index']);
        Route::post('/lesson-quizzes/{quiz}/attempts', [LessonQuizController::class, 'start']);
        Route::put('/lesson-quiz-attempts/{attempt}/answers/{question}', [LessonQuizController::class, 'answer']);
        Route::post('/lesson-quiz-attempts/{attempt}/complete', [LessonQuizController::class, 'complete']);
        Route::get('/lesson-quiz-attempts/{attempt}', [LessonQuizController::class, 'show']);
        Route::get('/quizzes/{quiz}', [QuizController::class, 'show']);
        Route::post('/quizzes/{quiz}/submit', [QuizController::class, 'submit']);
        Route::get('/quizzes/{quiz}/history', [QuizController::class, 'history']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/password', [ProfileController::class, 'password']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);
        Route::get('/fsrs/due', [FsrsController::class, 'due']);
        Route::get('/fsrs/stats', [FsrsController::class, 'stats']);
        Route::post('/fsrs/preview', [FsrsController::class, 'preview']);
        Route::post('/fsrs/review', [FsrsController::class, 'review']);
        Route::get('/notifications', [LearnerToolsController::class, 'notifications']);
        Route::post('/notifications/read-all', [LearnerToolsController::class, 'readAllNotifications']);
        Route::post('/notifications/{key}/read', [LearnerToolsController::class, 'readNotification']);
        Route::get('/flashcards/recommendations', [LearnerToolsController::class, 'recommendations']);
        Route::post('/flashcards/recommendations/{vocabulary}/save', [LearnerToolsController::class, 'saveRecommendation']);
        Route::get('/learner-imports', [LearnerToolsController::class, 'importRuns']);
        Route::post('/learner-imports', [LearnerToolsController::class, 'import']);
        Route::get('/dictionary/{word}', [LearnerToolsController::class, 'dictionary']);
        Route::get('/vocabulary-filters', [LearnerToolsController::class, 'filters']);
        Route::post('/vocabulary-quizzes', [VocabularyQuizController::class, 'start']);
        Route::put('/vocabulary-quizzes/{session}/answers/{vocabulary}', [VocabularyQuizController::class, 'answer']);
        Route::post('/vocabulary-quizzes/{session}/complete', [VocabularyQuizController::class, 'complete']);
        Route::get('/enrollments', [EnrollmentController::class, 'index']);
        Route::post('/enrollments', [EnrollmentController::class, 'store']);
        Route::get('/learning/plan', [LearningSessionController::class, 'plan']);
        Route::get('/assignments', [LearningSessionController::class, 'assignments']);
        Route::post('/learning/sessions', [LearningSessionController::class, 'store']);
        Route::get('/learning/sessions/{session}/next', [LearningSessionController::class, 'next']);
        Route::post('/learning/sessions/{session}/attempts', [LearningSessionController::class, 'attempt']);
        Route::post('/learning/sessions/{session}/complete', [LearningSessionController::class, 'complete']);
        Route::get('/media/youtube/channels', [MediaListeningController::class, 'channels']);
        Route::get('/media/youtube/search', [MediaListeningController::class, 'search']);
        Route::get('/media/youtube/channels/{channel}/videos', [MediaListeningController::class, 'videos']);
        Route::get('/media/youtube/videos/{video}/captions', [MediaListeningController::class, 'captions']);
        Route::get('/media/listening-sessions', [MediaListeningController::class, 'progress']);
        Route::post('/media/listening-sessions', [MediaListeningController::class, 'store']);
        Route::put('/media/listening-sessions/{session}', [MediaListeningController::class, 'update']);
        Route::post('/media/listening-sessions/{session}/pronunciation', [MediaListeningController::class, 'pronunciation']);
        Route::post('/media/listening-sessions/{session}/complete', [MediaListeningController::class, 'complete']);
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
        Route::put('/teacher/assignments/{assignment}', [TeacherController::class, 'updateAssignment']);
        Route::post('/teacher/intervention-notes', [TeacherController::class, 'note']);
        Route::middleware('google.admin')->group(function (): void {
            Route::get('/admin/session', fn (Request $request) => ApiResponse::success([
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->role?->slug,
            ]));
            Route::get('/admin/operations', [OperationsController::class, 'overview']);
            Route::post('/admin/operations/service-probes', [OperationsController::class, 'probe']);
            Route::get('/admin/operations/contracts', [OperationsController::class, 'contracts']);
            Route::get('/admin/operations/usage', [OperationsController::class, 'usage']);
            Route::get('/admin/operations/quota-policy', [OperationsController::class, 'quotaPolicy']);
            Route::put('/admin/operations/quota-policy', [OperationsController::class, 'createQuota']);
            Route::get('/admin/operations/alert-rules', [OperationsController::class, 'rules']);
            Route::put('/admin/operations/alert-rules/{alertRule}', [OperationsController::class, 'updateRule']);
            Route::get('/admin/operations/audit-events', [OperationsController::class, 'audits']);
            Route::get('/admin/audit-logs', [AuditLogController::class, 'index']);
            Route::get('/admin/users', [AdminUserController::class, 'index']);
            Route::get('/admin/users/{user}', [AdminUserController::class, 'show']);
            Route::put('/admin/users/{user}/role', [AdminUserController::class, 'updateRole']);
            Route::get('/admin/roles', [AdminUserController::class, 'roles']);
            Route::get('/admin/operations/teacher-assignments', [AdminUserController::class, 'teacherAssignments']);
            Route::post('/admin/operations/teacher-assignments', [AdminUserController::class, 'assignTeacher']);
            Route::put('/admin/operations/teacher-assignments/{teacherAssignment}', [AdminUserController::class, 'updateTeacherAssignment']);
            Route::delete('/admin/operations/teacher-assignments/{teacherAssignment}', [AdminUserController::class, 'unassignTeacher'])
                ->whereNumber('teacherAssignment');
            Route::get('/admin/catalog/courses', [AdminCatalogController::class, 'courses']);
            Route::post('/admin/catalog/courses', [AdminCatalogController::class, 'createCourse']);
            Route::get('/admin/catalog/courses/{course}', [AdminCatalogController::class, 'course']);
            Route::put('/admin/catalog/courses/{course}', [AdminCatalogController::class, 'updateCourse']);
            Route::post('/admin/catalog/courses/{course}/publish', [AdminCatalogController::class, 'publishCourse']);
            Route::post('/admin/catalog/courses/{course}/archive', [AdminCatalogController::class, 'archiveCourse']);
            Route::delete('/admin/catalog/courses/{course}', [AdminCatalogController::class, 'deleteCourse']);
            Route::middleware('can:manage-content')->group(function (): void {
                Route::get('/admin/catalog/units', [AdminUnitController::class, 'index']);
                Route::post('/admin/catalog/units', [AdminUnitController::class, 'store']);
                Route::put('/admin/catalog/units/reorder', [AdminUnitController::class, 'reorder']);
                Route::get('/admin/catalog/units/{unit}', [AdminUnitController::class, 'show']);
                Route::put('/admin/catalog/units/{unit}', [AdminUnitController::class, 'update']);
                Route::delete('/admin/catalog/units/{unit}', [AdminUnitController::class, 'destroy'])->whereNumber('unit');
                Route::post('/admin/catalog/units/{unit}/publish', [AdminUnitController::class, 'publish']);
                Route::post('/admin/catalog/units/{unit}/archive', [AdminUnitController::class, 'archive']);
            });
            Route::apiResource('/admin/catalog/lessons', AdminLessonController::class)->except(['create', 'edit']);
            Route::post('/admin/catalog/lessons/{lesson}/publish', [AdminLessonController::class, 'publish']);
            Route::post('/admin/catalog/lessons/{lesson}/archive', [AdminLessonController::class, 'archive']);
            Route::apiResource('/admin/catalog/quizzes', AdminQuizController::class)->except(['create', 'edit']);
            Route::get('/admin/catalog/summary', [AdminCatalogController::class, 'summary']);
            Route::get('/admin/catalog/levels', [AdminCatalogController::class, 'levels']);
            Route::post('/admin/catalog/levels', [AdminCatalogController::class, 'createLevel']);
            Route::put('/admin/catalog/levels/{level}', [AdminCatalogController::class, 'updateLevel']);
            Route::delete('/admin/catalog/levels/{level}', [AdminCatalogController::class, 'deleteLevel'])->whereNumber('level');
            Route::get('/admin/catalog/topics', [AdminCatalogController::class, 'topics']);
            Route::post('/admin/catalog/topics', [AdminCatalogController::class, 'createTopic']);
            Route::put('/admin/catalog/topics/{topic}', [AdminCatalogController::class, 'updateTopic']);
            Route::delete('/admin/catalog/topics/{topic}', [AdminCatalogController::class, 'deleteTopic'])->whereNumber('topic');
            Route::get('/admin/catalog/vocabularies', [AdminCatalogController::class, 'vocabularies']);
            Route::post('/admin/catalog/vocabularies', [AdminCatalogController::class, 'createVocabulary']);
            Route::get('/admin/catalog/vocabularies/{vocabulary}', [AdminCatalogController::class, 'vocabulary']);
            Route::put('/admin/catalog/vocabularies/{vocabulary}', [AdminCatalogController::class, 'updateVocabulary']);
            Route::post('/admin/catalog/vocabularies/{vocabulary}/media', [AdminCatalogController::class, 'uploadVocabularyMedia']);
            Route::delete('/admin/catalog/vocabularies/{vocabulary}', [AdminCatalogController::class, 'deleteVocabulary'])->whereNumber('vocabulary');
            Route::get('/admin/catalog/decks', [AdminCatalogController::class, 'decks']);
            Route::post('/admin/catalog/decks', [AdminCatalogController::class, 'createDeck']);
            Route::put('/admin/catalog/decks/{deck}', [AdminCatalogController::class, 'updateDeck']);
            Route::delete('/admin/catalog/decks/{deck}', [AdminCatalogController::class, 'deleteDeck'])->whereNumber('deck');
            Route::get('/admin/learning/overview', [AdminLearningController::class, 'overview']);
            Route::get('/admin/learning/quizzes', [AdminLearningController::class, 'quizzes']);
            Route::get('/admin/learning/fsrs', [AdminLearningController::class, 'fsrs']);
            Route::get('/admin/learning/progress', [AdminLearningController::class, 'progress']);
            Route::get('/admin/learning/reports.csv', [AdminLearningController::class, 'report']);
            Route::get('/admin/imports', [AdminContentOperationsController::class, 'checkpoints']);
            Route::get('/admin/imports/runs', [AdminContentOperationsController::class, 'history']);
            Route::post('/admin/imports', [AdminContentOperationsController::class, 'start']);
            Route::post('/admin/imports/reset', [AdminContentOperationsController::class, 'reset']);
            Route::get('/admin/imports/runs/{adminImportRun}', [AdminContentOperationsController::class, 'run']);
            Route::get('/admin/imports/runs/{adminImportRun}/items', [AdminContentOperationsController::class, 'items']);
            Route::put('/admin/imports/runs/{adminImportRun}/draft', [AdminContentOperationsController::class, 'draft']);
            Route::post('/admin/imports/runs/{adminImportRun}/approve', [AdminContentOperationsController::class, 'approve']);
            Route::post('/admin/imports/runs/{adminImportRun}/apply', [AdminContentOperationsController::class, 'apply']);
            Route::post('/admin/imports/runs/{adminImportRun}/cancel', [AdminContentOperationsController::class, 'cancel']);
            Route::post('/admin/imports/runs/{adminImportRun}/retry', [AdminContentOperationsController::class, 'retry']);
            Route::get('/admin/content-feed', [AdminContentOperationsController::class, 'feed']);
            Route::get('/admin/notifications', [AdminContentOperationsController::class, 'notifications']);
            Route::post('/admin/notifications/{supervisionAlert}/read', [AdminContentOperationsController::class, 'readNotification']);
            Route::get('/admin/preferences', [AdminContentOperationsController::class, 'preferences']);
            Route::put('/admin/preferences', [AdminContentOperationsController::class, 'updatePreferences']);
            Route::post('/admin/users/{learner}/evidence', [TeacherController::class, 'operationalEvidence']);
        });
    });
});

// Compatibility for the existing admin client while it migrates to the v1 envelope.
Route::prefix('api/admin')->middleware(['auth', 'role:admin'])->group(function (): void {
    Route::get('/users', [LegacyAdminUserController::class, 'index']);
    Route::get('/users/{user}', [LegacyAdminUserController::class, 'show']);
    Route::get('/users/{user}/history', [LegacyAdminUserController::class, 'history']);
    Route::put('/users/{user}/lock', [LegacyAdminUserController::class, 'lock']);
    Route::put('/users/{user}/unlock', [LegacyAdminUserController::class, 'unlock']);
    Route::post('/users/{user}/reset-password', [LegacyAdminUserController::class, 'resetPassword']);
    Route::put('/users/{user}/role', [LegacyAdminUserController::class, 'updateRole']);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
});
