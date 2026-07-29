<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LexiLingoClient
{
    public function backend(): PendingRequest
    {
        return $this->client('backend_url');
    }

    public function protectedBackend(): PendingRequest
    {
        return $this->backend()->withHeader(
            'X-Import-Key',
            $this->credential('import_key'),
        );
    }

    public function partner(): PendingRequest
    {
        $maxRetries = min(5, max(0, (int) config('services.lexilingo.import_max_retries', 3)));

        return $this->backend()
            ->withHeader('X-LexiLingo-API-Key', $this->credential('partner_api_key'))
            ->withOptions(['delay' => min(5000, max(0, (int) config('services.lexilingo.import_delay_ms', 250)))])
            ->retry(
                $maxRetries + 1,
                fn (int $attempt, \Exception $exception): int => $this->retryDelay($attempt, $exception),
                fn (\Exception $exception): bool => $this->retryable($exception),
                throw: false,
            );
    }

    public function partnerOnce(): PendingRequest
    {
        return $this->backend()->withHeader('X-LexiLingo-API-Key', $this->credential('partner_api_key'));
    }

    public function partnerBackend(): PendingRequest
    {
        return $this->partnerOnce();
    }

    public function ai(): PendingRequest
    {
        return $this->client('ai_url');
    }

    public function internalAi(): PendingRequest
    {
        return $this->ai()->withHeader(
            'X-AI-Service-Secret',
            $this->credential('ai_service_secret'),
        );
    }

    public function traceCag(): PendingRequest
    {
        return $this->ai()
            ->withHeader('X-LexiLingo-Service-Token', $this->credential('trace_cag_service_token'))
            ->timeout(15);
    }

    private function client(string $urlKey): PendingRequest
    {
        $url = config("services.lexilingo.{$urlKey}");

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || ! $this->allowedUrl($url)) {
            throw new RuntimeException("LexiLingo {$urlKey} is not configured.");
        }

        return Http::baseUrl(rtrim($url, '/'))
            ->acceptJson()
            ->connectTimeout(min(30, max(1, (int) config('services.lexilingo.connect_timeout', 5))))
            ->timeout(min(60, max(1, (int) config('services.lexilingo.timeout', 30))));
    }

    private function retryable(\Exception $exception): bool
    {
        if (! $exception instanceof RequestException) {
            return true;
        }

        $status = $exception->response->status();

        return $status === 429 || $status >= 500;
    }

    private function retryDelay(int $attempt, \Exception $exception): int
    {
        $cap = min(30000, max(0, (int) config('services.lexilingo.import_max_backoff_ms', 10000)));
        $local = min($cap, 250 * (2 ** max(0, $attempt - 1)));

        if (! $exception instanceof RequestException) {
            return $local;
        }

        $header = $exception->response->header('Retry-After');
        if (! is_string($header) || $header === '') {
            return $local;
        }

        $seconds = ctype_digit($header)
            ? (int) $header
            : max(0, (int) ceil(strtotime($header) - time()));

        return min($cap, max($local, $seconds * 1000));
    }

    private function credential(string $key): string
    {
        $value = config("services.lexilingo.{$key}");

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("LexiLingo {$key} is not configured.");
        }

        return $value;
    }

    private function allowedUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return $scheme === 'https'
            || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true));
    }
}
