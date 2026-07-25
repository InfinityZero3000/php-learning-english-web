<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthCheck
{
    public function response(?string $version = null): JsonResponse
    {
        $checks = [];

        foreach (['database' => 'checkDatabase', 'redis' => 'checkRedis'] as $name => $method) {
            if ($name === 'redis' && ! $this->usesRedis()) {
                continue;
            }

            try {
                $this->{$method}();
            } catch (Throwable $exception) {
                $checks[$name] = 'down';
                Log::warning("Health check failed: {$name}", ['exception' => $exception]);
            }
        }

        $payload = ['status' => $checks === [] ? 'ok' : 'error'];
        if ($version !== null) {
            $payload['version'] = $version;
        }
        if ($checks !== []) {
            $payload['checks'] = $checks;
        }

        return response()->json($payload, $checks === [] ? 200 : 503);
    }

    protected function checkDatabase(): void
    {
        DB::connection()->getPdo();
    }

    protected function checkRedis(): void
    {
        Redis::connection()->command('ping');
    }

    protected function usesRedis(): bool
    {
        $cacheStore = config('cache.default');

        return config("cache.stores.{$cacheStore}.driver") === 'redis'
            || config('session.driver') === 'redis'
            || config('queue.connections.'.config('queue.default').'.driver') === 'redis';
    }
}
