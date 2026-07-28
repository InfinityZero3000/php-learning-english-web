<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => $meta ?: (object) []], $status);
    }

    public static function error(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code, ...$extra], $status);
    }
}
