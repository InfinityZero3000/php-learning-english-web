<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MediaUploadRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function upload(MediaUploadRequest $request): JsonResponse
    {
        Gate::authorize('upload-media');

        $file = $request->file('file');
        $disk = config('filesystems.media_disk', 'media');
        $directory = 'uploads/'.date('Y/m');

        $filename = $request->sanitizedFilename();
        $path = $file->storeAs($directory, $filename, [
            'disk' => $disk,
            'visibility' => 'public',
        ]);

        $url = Storage::disk($disk)->url($path);

        return ApiResponse::success([
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'url' => $url,
            'path' => $path,
            'disk' => $disk,
        ], 201);
    }

    public function destroy(string $path): JsonResponse
    {
        Gate::authorize('delete-media');

        $disk = config('filesystems.media_disk', 'media');

        // Prevent path traversal
        $path = ltrim($path, '/\\');
        if (str_contains($path, '..') || ! str_starts_with($path, 'uploads/')) {
            return ApiResponse::success(['error' => 'Invalid file path'], 400);
        }

        if (! Storage::disk($disk)->exists($path)) {
            return ApiResponse::success(['error' => 'File not found'], 404);
        }

        Storage::disk($disk)->delete($path);

        return ApiResponse::success(null, 204);
    }
}
