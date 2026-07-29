<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\RequireGoogleAdmin;
use App\Support\ApiResponse;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: fn () => Route::middleware('web')->group(base_path('routes/spa.php')),
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('supervision:evaluate')->dailyAt('02:00')->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => CheckRole::class,
            'google.admin' => RequireGoogleAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (ModelNotFoundException $exception, Request $request) => $request->is('api/*')
            ? ApiResponse::error('NOT_FOUND', 'The requested resource was not found.', 404)
            : null);
        $exceptions->render(fn (NotFoundHttpException $exception, Request $request) => $request->is('api/*')
            ? ApiResponse::error('NOT_FOUND', 'The requested resource was not found.', 404)
            : null);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
