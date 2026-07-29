<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LearningEvent;
use App\Models\LearningSession;
use App\Models\Vocabulary;
use App\Support\ApiResponse;
use App\Support\LexiLingoClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class AiProxyController extends Controller
{
    public function __construct(private readonly LexiLingoClient $client) {}

    public function translate(Request $request): JsonResponse|Response
    {
        if (! config('features.ai')) {
            return ApiResponse::error('FEATURE_DISABLED', 'AI features are disabled.', 503);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
            'target_lang' => ['required', 'string', 'size:2'],
            'source_lang' => ['nullable', 'string', 'size:2'],
        ]);

        return $this->callUpstream('translate', function () use ($validated) {
            $response = $this->retryable()->post('/api/v1/ai/translate', $validated);

            return ApiResponse::success([
                'translated_text' => (string) data_get($response->json(), 'translated_text', data_get($response->json(), 'translation', '')),
                'source_lang' => (string) ($validated['source_lang'] ?? data_get($response->json(), 'source_lang', 'auto')),
                'target_lang' => $validated['target_lang'],
            ]);
        });
    }

    public function pronunciation(Request $request): JsonResponse|Response
    {
        if (! config('features.voice')) {
            return ApiResponse::error('FEATURE_DISABLED', 'Voice features are disabled.', 503);
        }

        $request->validate([
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg,webm', 'max:'.$this->maxAudioKb()],
            'reference_text' => ['required', 'string', 'max:500'],
            'language' => ['nullable', 'string', 'size:2'],
            'session_id' => ['nullable', 'integer', 'required_with:activity_id'],
            'activity_id' => ['nullable', 'string', 'max:128', 'required_with:session_id'],
        ]);

        $session = null;
        $activityId = null;
        $vocabularyId = null;
        $requestId = null;
        $fingerprint = null;
        if ($request->filled('session_id')) {
            $session = LearningSession::query()->whereKey($request->integer('session_id'))
                ->where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
            $requestId = $request->header('X-Request-ID');
            validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();
            $activityId = $request->string('activity_id')->toString();
            $vocabularyId = str_starts_with($activityId, 'vocabulary:') ? (int) substr($activityId, 11) : 0;
            Vocabulary::query()->whereKey($vocabularyId)->where('lesson_id', $session->lesson_id)->firstOrFail();
            $fingerprint = hash('sha256', implode('|', [
                $session->id, $activityId, $request->string('reference_text')->toString(),
                hash_file('sha256', $request->file('audio')->getRealPath()),
            ]));
            if ($existing = LearningEvent::query()->where('request_id', $requestId)->first()) {
                if ((int) $existing->user_id !== (int) $request->user()->id
                    || $existing->event_type !== 'pronunciation'
                    || data_get($existing->metadata, 'idempotency_fingerprint') !== $fingerprint) {
                    throw new ConflictHttpException('X-Request-ID was already used for another operation.');
                }

                return ApiResponse::success(data_get($existing->metadata, 'upstream_result', []));
            }
        }

        return $this->callUpstream('pronunciation', function () use (
            $request, $session, $activityId, $vocabularyId, $requestId, $fingerprint
        ) {
            $response = $this->retryable()
                ->attach(
                    'audio',
                    $request->file('audio')->get(),
                    $request->file('audio')->getClientOriginalName(),
                    ['Content-Type' => $request->file('audio')->getMimeType()],
                )
                ->post('/api/v1/stt/assess-pronunciation', [
                    'reference_text' => $request->string('reference_text')->toString(),
                    'language' => $request->input('language'),
                ]);

            $raw = $response->json();
            $score = data_get($raw, 'score', data_get($raw, 'pronunciation_score'));
            $payload = [
                'score' => is_numeric($score) ? min(100, max(0, (float) $score)) : 0,
                'feedback' => (string) data_get($raw, 'feedback', data_get($raw, 'message', '')),
            ];
            if ($session) {
                LearningEvent::create([
                    'request_id' => $requestId,
                    'learning_session_id' => $session->id,
                    'user_id' => $request->user()->id,
                    'event_type' => 'pronunciation',
                    'pronunciation_score' => is_numeric($score) && $score >= 0 && $score <= 100 ? (float) $score : null,
                    'occurred_at' => now('UTC'),
                    'metadata' => [
                        'activity_id' => $activityId,
                        'vocabulary_id' => $vocabularyId,
                        'idempotency_fingerprint' => $fingerprint,
                        'upstream_result' => $payload,
                    ],
                ]);
            }

            return ApiResponse::success($payload);
        });
    }

    public function speechToText(Request $request): JsonResponse|Response
    {
        if (! config('features.voice')) {
            return ApiResponse::error('FEATURE_DISABLED', 'Voice features are disabled.', 503);
        }

        $request->validate([
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg,webm', 'max:'.$this->maxAudioKb()],
            'language' => ['nullable', 'string', 'size:2'],
            'session_id' => ['nullable', 'integer', 'required_with:activity_id'],
            'activity_id' => ['nullable', 'string', 'max:128', 'required_with:session_id'],
        ]);

        return $this->callUpstream('speech_to_text', function () use ($request) {
            $response = $this->retryable()
                ->attach(
                    'audio',
                    $request->file('audio')->get(),
                    $request->file('audio')->getClientOriginalName(),
                    ['Content-Type' => $request->file('audio')->getMimeType()],
                )
                ->post('/api/v1/stt/transcribe', [
                    'language' => $request->input('language'),
                ]);

            $raw = $response->json();
            $payload = [
                'transcript' => (string) data_get($raw, 'transcript', data_get($raw, 'text', '')),
                'confidence' => is_numeric(data_get($raw, 'confidence')) ? (float) data_get($raw, 'confidence') : null,
            ];
            if ($request->filled('session_id')) {
                $session = LearningSession::query()->whereKey($request->integer('session_id'))
                    ->where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
                $requestId = $request->header('X-Request-ID');
                validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();
                $activityId = $request->string('activity_id')->toString();
                $vocabularyId = str_starts_with($activityId, 'vocabulary:') ? (int) substr($activityId, 11) : 0;
                Vocabulary::query()->whereKey($vocabularyId)->where('lesson_id', $session->lesson_id)->firstOrFail();
                LearningEvent::create([
                    'request_id' => $requestId,
                    'learning_session_id' => $session->id,
                    'user_id' => $request->user()->id,
                    'event_type' => 'transcription',
                    'response' => mb_substr((string) data_get($payload, 'transcript', data_get($payload, 'text', '')), 0, 2000),
                    'occurred_at' => now('UTC'),
                    'metadata' => ['activity_id' => $activityId, 'vocabulary_id' => $vocabularyId],
                ]);
            }

            return ApiResponse::success($payload);
        });
    }

    public function textToSpeech(Request $request): JsonResponse|Response
    {
        if (! config('features.voice')) {
            return ApiResponse::error('FEATURE_DISABLED', 'Voice features are disabled.', 503);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:1000'],
            'voice' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', 'size:2'],
        ]);

        return $this->callUpstream('text_to_speech', function () use ($validated) {
            $response = $this->retryable()->post('/api/v1/tts/synthesize', $validated);

            return response(
                $response->body(),
                200,
                ['Content-Type' => $response->header('Content-Type') ?: 'audio/mpeg'],
            );
        });
    }

    private function retryable(): PendingRequest
    {
        return $this->client->internalAi()->retry(
            times: (int) config('services.lexilingo.ai_retry_times', 2),
            sleepMilliseconds: (int) config('services.lexilingo.ai_retry_delay_ms', 200),
            when: fn (Throwable $exception) => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && $exception->response->serverError()),
        );
    }

    private function maxAudioKb(): int
    {
        return (int) config('services.lexilingo.max_audio_kb', 10240);
    }

    /**
     * Run an upstream call and normalize any failure into a safe response.
     * Never logs request/response bodies (text, audio) or credentials -
     * only the action name, caller and exception class/status.
     */
    private function callUpstream(string $action, callable $call): JsonResponse|Response
    {
        try {
            return $call();
        } catch (ConnectionException $exception) {
            $this->logFailure($action, $exception);

            return ApiResponse::error('UPSTREAM_TIMEOUT', 'AI service is currently unreachable. Please try again.', 504);
        } catch (RequestException $exception) {
            $this->logFailure($action, $exception, $exception->response->status());

            if ($exception->response->serverError()) {
                return ApiResponse::error('UPSTREAM_ERROR', 'AI service failed to process the request.', 502);
            }

            if ($exception->response->status() === 429) {
                return ApiResponse::error('UPSTREAM_RATE_LIMITED', 'AI service rate limit reached.', 429)
                    ->withHeaders(array_filter([
                        'Retry-After' => $exception->response->header('Retry-After'),
                    ]));
            }

            return ApiResponse::error('UPSTREAM_REJECTED', 'AI service rejected the request.', 422);
        } catch (ConflictHttpException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->logFailure($action, $exception);

            return ApiResponse::error('PROXY_ERROR', 'AI proxy failed.', 500);
        }
    }

    private function logFailure(string $action, Throwable $exception, ?int $status = null): void
    {
        Log::warning('LexiLingo AI proxy call failed', [
            'action' => $action,
            'user_id' => auth()->id(),
            'exception' => $exception::class,
            'status' => $status,
        ]);
    }
}
