<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlertRule;
use App\Models\OperationsAudit;
use App\Models\QuotaPolicy;
use App\Models\SupervisionAlert;
use App\Support\ApiResponse;
use App\Support\RecentPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OperationsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $this->authorizeOperations($request);

        return ApiResponse::success([
            'type' => 'service_status',
            'features' => config('features'),
            'services' => [
                'backend' => (bool) config('services.lexilingo.backend_url'),
                'ai' => (bool) config('services.lexilingo.ai_url'),
                'trace_cag' => (bool) config('services.lexilingo.trace_cag_service_token'),
                'partner_content' => (bool) config('services.lexilingo.partner_api_key'),
            ],
            'open_alerts' => SupervisionAlert::query()->where('state', 'open')->count(),
        ]);
    }

    public function probe(Request $request): JsonResponse
    {
        $this->authorizeOperations($request);
        $data = $request->validate(['service' => ['required', 'in:backend,ai,trace_cag,stt,tts']]);
        $isBackend = $data['service'] === 'backend';
        $url = config('services.lexilingo.'.($isBackend ? 'backend_url' : 'ai_url'));
        abort_unless(is_string($url) && $url !== '', 503, 'Service URL is not configured.');
        $path = match ($data['service']) {
            'backend' => '/backend-health',
            'ai', 'trace_cag' => '/ai-health',
            'stt' => '/api/v1/stt/health',
            'tts' => '/api/v1/tts/health',
        };
        $started = microtime(true);
        $response = Http::acceptJson()->timeout(3)->get(rtrim($url, '/').$path);

        return ApiResponse::success([
            'type' => 'service_status',
            'service' => $data['service'],
            'healthy' => $response->successful(),
            'status' => $response->status(),
            'latency_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
    }

    public function quotas(Request $request): JsonResponse
    {
        $this->authorizeOperations($request);

        return ApiResponse::success(QuotaPolicy::query()->latest('version')->get());
    }

    public function createQuota(Request $request, RecentPassword $recentPassword): JsonResponse
    {
        $this->authorizeOperations($request);
        $recentPassword->require($request);
        $data = $request->validate(['limits' => ['required', 'array']]);
        $next = (int) QuotaPolicy::max('version') + 1;
        QuotaPolicy::query()->where('is_active', true)->update(['is_active' => false]);
        $policy = QuotaPolicy::create([
            'version' => $next, 'limits' => $data['limits'], 'is_active' => true,
            'created_by' => $request->user()->id, 'activated_at' => now('UTC'),
        ]);
        $this->audit($request, 'quota_policy.activated', $policy->id, $policy->toArray());

        return ApiResponse::success($policy, status: 201);
    }

    public function rules(Request $request): JsonResponse
    {
        $this->authorizeOperations($request);

        return ApiResponse::success(AlertRule::query()->latest('version')->get());
    }

    public function audits(Request $request): JsonResponse
    {
        $this->authorizeOperations($request);

        return ApiResponse::success(OperationsAudit::query()->latest('occurred_at')->limit(200)->get());
    }

    private function authorizeOperations(Request $request): void
    {
        abort_unless($request->user()->can('manage-operations'), 403);
    }

    private function audit(Request $request, string $action, int|string $target, array $after): void
    {
        OperationsAudit::create([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'target_type' => 'quota_policy',
            'target_id' => (string) $target,
            'request_id' => Str::isUuid($request->header('X-Request-ID')) ? $request->header('X-Request-ID') : null,
            'after_state' => $after,
            'occurred_at' => now('UTC'),
        ]);
    }
}
