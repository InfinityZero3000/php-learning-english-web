<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VocabularyResource;
use App\Models\Vocabulary;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VocabularyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $query = Vocabulary::query()->orderBy('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('word', 'like', "%{$search}%");
        }

        $page = $query->paginate($perPage);

        return ApiResponse::success(
            VocabularyResource::collection($page->items()),
            meta: [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }
}
