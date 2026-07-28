<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\LexiLingoClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiProxyController extends Controller
{
    public function __construct(private readonly LexiLingoClient $client) {}

    /**
     * Proxy: text translation.
     *
     * Request/response field names for the upstream AI service are a
     * best-effort mapping - the real schema is only published on that
     * service's own Swagger, which is not reachable from here. The upstream
     * JSON body is relayed as-is inside our envelope; tighten field mapping
     * once the actual contract is available.
     */
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

            return ApiResponse::success($response->json());
        });
    }

    public function pronunciation(Request $request): JsonResponse|Response
    {
        if (! config('features.voice')) {
            return ApiResponse::error('FEATURE_DISABLED', 'Voice features are disabled.', 503);
        }

        $request->validate([
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg', 'max:'.$this->maxAudioKb()],
            'reference_text' => ['required', 'string', 'max:500'],
            'language' => ['nullable', 'string', 'size:2'],
        ]);

        return $this->callUpstream('pronunciation', function () use ($request) {
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

            return ApiResponse::success($response->json());
        });
    }

    public function speechToText(Request $request): JsonResponse|Response
    {
        if (! config('features.voice')) {
            return ApiResponse::error('FEATURE_DISABLED', 'Voice features are disabled.', 503);
        }

        $request->validate([
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg', 'max:'.$this->maxAudioKb()],
            'language' => ['nullable', 'string', 'size:2'],
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

            return ApiResponse::success($response->json());
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
