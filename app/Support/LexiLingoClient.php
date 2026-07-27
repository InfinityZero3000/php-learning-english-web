<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
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

    public function partnerBackend(): PendingRequest
    {
        return $this->backend()->withHeader(
            'X-LexiLingo-API-Key',
            $this->credential('partner_api_key'),
        );
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

    private function client(string $urlKey): PendingRequest
    {
        $url = config("services.lexilingo.{$urlKey}");

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || ! $this->allowedUrl($url)) {
            throw new RuntimeException("LexiLingo {$urlKey} is not configured.");
        }

        return Http::baseUrl(rtrim($url, '/'))
            ->acceptJson()
            ->timeout(min(60, max(1, (int) config('services.lexilingo.timeout', 30))));
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
