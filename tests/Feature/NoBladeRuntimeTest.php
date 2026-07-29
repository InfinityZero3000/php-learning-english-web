<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NoBladeRuntimeTest extends TestCase
{
    public function test_no_production_route_uses_a_legacy_blade_controller(): void
    {
        $legacy = [
            \App\Http\Controllers\AuthController::class,
            \App\Http\Controllers\BookmarkController::class,
            \App\Http\Controllers\CourseController::class,
            \App\Http\Controllers\EmailVerificationController::class,
            \App\Http\Controllers\ForgotPasswordController::class,
            \App\Http\Controllers\LessonController::class,
            \App\Http\Controllers\ProfileController::class,
            \App\Http\Controllers\ProgressController::class,
            \App\Http\Controllers\QuizAttemptController::class,
            \App\Http\Controllers\QuizController::class,
            \App\Http\Controllers\VocabularyController::class,
            \App\Http\Controllers\WordsController::class,
            \App\Http\Controllers\Admin\UserController::class,
        ];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            foreach ($legacy as $controller) {
                $this->assertStringNotContainsString("{$controller}@", $action, "{$route->uri()} still uses {$controller}");
            }
        }
    }

    public function test_legacy_html_mutations_are_retired(): void
    {
        foreach (['/register', '/forgot-password', '/reset-password', '/logout', '/profile', '/bookmarks/1/toggle', '/lessons/1/complete', '/quizzes/1/attempt'] as $uri) {
            $response = $this->post($uri);
            $this->assertContains($response->getStatusCode(), [404, 405], "POST {$uri} is still active");
        }
    }

    public function test_next_frontends_do_not_call_unversioned_laravel_apis(): void
    {
        foreach (['frontend/src', 'admin-frontend/src'] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($directory)));
            foreach ($files as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['ts', 'tsx', 'js', 'mjs'], true)) {
                    continue;
                }

                $source = file_get_contents($file->getPathname());
                $this->assertDoesNotMatchRegularExpression(
                    '~[\"\'`](/api/(?!v1(?:/|[\"\'`])))~',
                    $source,
                    "{$file->getPathname()} calls an unversioned Laravel API",
                );
            }
        }
    }
}
