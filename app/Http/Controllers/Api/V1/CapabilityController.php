<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapabilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user?->role?->slug;
        $features = (array) config('features', []);

        $capabilities = [];
        foreach ($features as $key => $enabled) {
            if (! $enabled) {
                $status = 'unconfigured';
            } else {
                $status = match ($key) {
                    'operations' => $role === 'super_admin' ? 'enabled' : 'role_required',
                    'supervision' => in_array($role, ['teacher', 'super_admin'], true) ? 'enabled' : 'role_required',
                    'lexilingo_import' => in_array($role, ['admin', 'super_admin', 'learner'], true) ? 'enabled' : 'role_required',
                    default => 'enabled',
                };
            }

            $capabilities[$key] = [
                'status' => $status,
                'enabled' => $status === 'enabled',
            ];
        }

        return ApiResponse::success([
            'capabilities' => $capabilities,
        ]);
    }
}
