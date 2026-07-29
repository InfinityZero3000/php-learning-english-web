<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LexiLingoTraceCag;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TraceCagController extends Controller
{
    public function analyze(Request $request, LexiLingoTraceCag $traceCag): JsonResponse
    {
        if (! config('features.ai')) {
            return ApiResponse::error('FEATURE_DISABLED', 'AI features are disabled.', 503);
        }

        $requestId = $request->header('X-Request-ID');
        $input = $request->validate([
            'session_id' => ['required', 'integer'],
            'activity_id' => ['required', 'string', 'max:128'],
            'input_type' => ['required', 'string', 'in:answer,hint_request,pronunciation,tutor_message'],
            'text' => ['required', 'string', 'max:2000'],
        ]);
        validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();

        return ApiResponse::success($traceCag->analyze($request->user(), $input, $requestId));
    }
}
