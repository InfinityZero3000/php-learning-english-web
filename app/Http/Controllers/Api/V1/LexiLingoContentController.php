<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\LexiLingoClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class LexiLingoContentController extends Controller
{
    public function __construct(private readonly LexiLingoClient $client) {}

    public function news(Request $request): JsonResponse
    {
        return $this->get('/api/v1/integrations/news', $request, ['page', 'page_size', 'category', 'language']);
    }

    public function youtube(Request $request): JsonResponse
    {
        return $this->get('/api/v1/integrations/youtube/search', $request, ['q', 'page', 'page_size', 'language']);
    }

    private function get(string $path, Request $request, array $allowed): JsonResponse
    {
        $query = $request->only($allowed);

        try {
            return response()->json(
                $this->client->partnerBackend()->get($path, $query)->throw()->json()
            );
        } catch (Throwable) {
            return response()->json(['message' => 'LexiLingo content is temporarily unavailable.'], 503);
        }
    }
}
