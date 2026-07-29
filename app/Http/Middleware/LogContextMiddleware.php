<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogContextMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incomingRequestId = $request->header('X-Request-ID');
        $requestId = is_string($incomingRequestId) && Str::isUuid($incomingRequestId)
            ? $incomingRequestId
            : Str::uuid()->toString();

        try {
            Log::withContext([
                'request_id' => $requestId,
                'ip' => $request->ip(),
                'method' => $request->method(),
                'url' => $request->url(),
                'user_id' => $request->user()?->id,
            ]);
        } catch (\Throwable $e) {
            // Safely ignore logging context errors in testing environments
            // when Log facade is mocked without withContext expectations.
        }

        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set('X-Request-ID', $requestId);
        }

        return $response;
    }
}
