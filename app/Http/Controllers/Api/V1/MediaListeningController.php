<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MediaListeningSession;
use App\Support\ApiResponse;
use App\Support\LexiLingoClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaListeningController extends Controller
{
    public function __construct(private readonly LexiLingoClient $client) {}

    public function channels(Request $request): JsonResponse
    {
        return $this->partner('/api/v1/media/youtube/channels', $request, ['category', 'cefr_level', 'page_token', 'per_page']);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'max:200']]);

        return $this->partner('/api/v1/media/youtube/search', $request, ['q', 'channel', 'category', 'cefr_level', 'page_token', 'per_page']);
    }

    public function videos(Request $request, string $channel): JsonResponse
    {
        return $this->partner('/api/v1/media/youtube/channels/'.rawurlencode($channel).'/videos', $request, ['category', 'cefr_level', 'page_token', 'per_page']);
    }

    public function captions(Request $request, string $video): JsonResponse
    {
        return $this->partner('/api/v1/media/youtube/videos/'.rawurlencode($video).'/captions', $request, ['language']);
    }

    public function store(Request $request): JsonResponse
    {
        if ($disabled = $this->disabled()) return $disabled;
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:255'],
            'thumbnail_url' => ['nullable', 'url', 'max:2048'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);
        $session = MediaListeningSession::firstOrCreate(
            ['user_id' => $request->user()->id, 'source' => 'youtube', 'external_id' => $data['external_id']],
            $data + ['user_id' => $request->user()->id, 'source' => 'youtube']
        );

        return ApiResponse::success($session);
    }

    public function update(Request $request, MediaListeningSession $session): JsonResponse
    {
        if ($disabled = $this->disabled()) return $disabled;
        $this->owns($request, $session);
        $data = $request->validate([
            'position_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
        ]);
        $position = min($data['position_seconds'], $data['duration_seconds']);
        $session->update([
            ...$data,
            'position_seconds' => $position,
            'watched_percentage' => max($session->watched_percentage, min(100, (int) round($position / $data['duration_seconds'] * 100))),
        ]);

        return ApiResponse::success($session->fresh());
    }

    public function pronunciation(Request $request, MediaListeningSession $session): JsonResponse
    {
        if ($disabled = $this->disabled()) return $disabled;
        $this->owns($request, $session);
        $request->validate([
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg,webm', 'max:'.config('services.lexilingo.max_audio_kb', 10240)],
            'reference_text' => ['required', 'string', 'max:500'],
        ]);
        try {
            $response = $this->client->internalAi()->attach(
                'audio', $request->file('audio')->get(), $request->file('audio')->getClientOriginalName(),
                ['Content-Type' => $request->file('audio')->getMimeType()]
            )->post('/api/v1/stt/assess-pronunciation', ['reference_text' => $request->string('reference_text')->toString(), 'language' => 'en'])->throw();
            $score = min(100, max(0, (float) data_get($response->json(), 'score', data_get($response->json(), 'pronunciation_score', 0))));
            $session->increment('shadowing_attempts');
            if ($session->best_pronunciation_score === null || $score > $session->best_pronunciation_score) {
                $session->update(['best_pronunciation_score' => $score]);
            }

            return ApiResponse::success(['score' => $score, 'feedback' => (string) data_get($response->json(), 'feedback', '')]);
        } catch (ConnectionException|RequestException) {
            return ApiResponse::error('UPSTREAM_ERROR', 'Pronunciation service is temporarily unavailable.', 502);
        }
    }

    public function complete(Request $request, MediaListeningSession $session): JsonResponse
    {
        if ($disabled = $this->disabled()) return $disabled;
        $this->owns($request, $session);
        if ($session->watched_percentage < 80 || $session->shadowing_attempts < 1) {
            return ApiResponse::error('COMPLETION_REQUIREMENTS', 'Watch at least 80% and complete one shadowing attempt.', 422);
        }
        if ($session->status !== 'completed') {
            $session->update(['status' => 'completed', 'completed_at' => now('UTC')]);
        }

        return ApiResponse::success($session->fresh());
    }

    public function progress(Request $request): JsonResponse
    {
        if ($disabled = $this->disabled()) return $disabled;

        return ApiResponse::success(MediaListeningSession::where('user_id', $request->user()->id)->latest()->get());
    }

    private function partner(string $path, Request $request, array $allowed): JsonResponse
    {
        if ($disabled = $this->disabled()) return $disabled;
        $query = $request->only($allowed);
        if (isset($query['per_page'])) $query['per_page'] = min(50, max(1, (int) $query['per_page']));
        try {
            return ApiResponse::success($this->client->partnerOnce()->get($path, $query)->throw()->json());
        } catch (ConnectionException) {
            return ApiResponse::error('UPSTREAM_TIMEOUT', 'Listening service timed out.', 504);
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            return ApiResponse::error($status === 429 ? 'UPSTREAM_RATE_LIMITED' : 'UPSTREAM_ERROR', $status === 429 ? 'Listening quota reached.' : 'Listening service is unavailable.', $status === 429 ? 429 : 502)
                ->withHeaders(array_filter(['Retry-After' => $exception->response->header('Retry-After')]));
        }
    }

    private function disabled(): ?JsonResponse
    {
        return config('features.youtube_listening') ? null : ApiResponse::error('FEATURE_DISABLED', 'YouTube listening is disabled.', 503);
    }

    private function owns(Request $request, MediaListeningSession $session): void
    {
        abort_unless((int) $session->user_id === (int) $request->user()->id, 403);
    }
}
