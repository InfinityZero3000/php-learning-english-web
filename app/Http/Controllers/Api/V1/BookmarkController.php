<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\Vocabulary;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookmarkController extends Controller
{
    public function update(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bookmarked' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', 'The given data was invalid.', 422, [
                'errors' => $validator->errors(),
            ]);
        }

        $attributes = ['user_id' => $request->user()->id, 'vocabulary_id' => $vocabulary->id];
        $bookmarked = $request->boolean('bookmarked');

        if ($bookmarked) {
            Bookmark::query()->updateOrCreate($attributes);
        } else {
            Bookmark::query()->where($attributes)->delete();
        }

        return ApiResponse::success(['id' => $vocabulary->id, 'bookmarked' => $bookmarked]);
    }
}
