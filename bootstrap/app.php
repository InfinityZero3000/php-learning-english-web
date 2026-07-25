<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            return $request->is('api/*') ? response()->json(['message' => 'Unauthenticated.'], 401) : null;
        });
        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            return $request->is('api/*') ? response()->json(['message' => 'Bạn không có quyền thực hiện hành động này.'], 403) : null;
        });
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            return $request->is('api/*') ? response()->json(['message' => 'Không tìm thấy dữ liệu.'], 404) : null;
        });
        $exceptions->render(function (ValidationException $exception, Request $request) {
            return $request->is('api/*') ? response()->json(['message' => 'Dữ liệu không hợp lệ.', 'errors' => $exception->errors()], 422) : null;
        });
        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            return $request->is('api/*') ? response()->json(['message' => 'Bạn đã gửi quá nhiều yêu cầu, vui lòng thử lại sau.'], 429) : null;
        });
    })->create();
