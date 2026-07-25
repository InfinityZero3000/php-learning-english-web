<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->role?->slug !== 'admin') {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Bạn không có quyền admin.'], 403);
            }

            abort(403, 'Bạn không có quyền admin.');
        }

        return $next($request);
    }
}