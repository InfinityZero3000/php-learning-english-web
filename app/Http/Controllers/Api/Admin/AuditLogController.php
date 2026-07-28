<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('manage', User::class);

        $logs = AuditLog::query()->orderByDesc('created_at')->limit(200)->get();

        return response()->json($logs->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'timestamp' => optional($log->created_at)->toIso8601String(),
            'action' => $log->action,
            'actor' => $log->actor_email,
            'actorRole' => $log->actor_role,
            'resource' => $log->resource,
            'detail' => $log->detail,
            'ip' => $log->ip,
            'status' => $log->status,
        ])->all());
    }
}
