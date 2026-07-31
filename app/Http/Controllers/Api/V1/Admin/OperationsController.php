<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlertRule;
use App\Models\Course;
use App\Models\LearningAssistanceResult;
use App\Models\OperationsAudit;
use App\Models\QuotaPolicy;
use App\Models\SupervisionAlert;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\RecentGoogleAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
                'stt' => (bool) config('services.lexilingo.ai_url'),
                'tts' => (bool) config('services.lexilingo.ai_url'),
                'partner_content' => (bool) config('services.lexilingo.partner_api_key'),
            ],
            'open_alerts' => SupervisionAlert::query()->where('state', 'open')->count(),
            'users' => User::query()->count(),
            'courses' => Course::query()->count(),
            'environment' => app()->environment(),
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

    public function quotaPolicy(Request $request): JsonResponse
    {
        $this->authorizeOperations($request);

        $policy = QuotaPolicy::query()->where('is_active', true)->latest('version')->first();

        return ApiResponse::success($policy
            ? ['type' => 'quota_policy', ...$policy->toArray()]
            : ['type' => 'quota_policy', 'id' => null, 'version' => 0, 'limits' => [], 'is_active' => false]);
    }

    public function createQuota(Request $request, RecentGoogleAdmin $recentGoogle): JsonResponse
    {
        $this->authorizeOperations($request);
        $recentGoogle->require($request);
        $data = $request->validate([
            'limits' => ['required', 'array', 'min:1'],
            'limits.*' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);
        if ($audit = $this->requestAudit($request)) {
            if ($audit->action !== 'quota_policy.activated' || data_get($audit->after_state, 'limits') !== $data['limits']) {
                throw new ConflictHttpException('X-Request-ID was already used for another operation.');
            }

            return ApiResponse::success(['type' => 'quota_policy', ...$audit->after_state]);
        }
        $policy = DB::transaction(function () use ($request, $data): QuotaPolicy {
            $next = (int) QuotaPolicy::query()->lockForUpdate()->max('version') + 1;
            $before = QuotaPolicy::query()->where('is_active', true)->first();
            QuotaPolicy::query()->where('is_active', true)->update(['is_active' => false]);
            $policy = QuotaPolicy::create([
                'version' => $next, 'limits' => $data['limits'], 'is_active' => true,
                'created_by' => $request->user()->id, 'activated_at' => now('UTC'),
            ]);
            $this->audit($request, 'quota_policy.activated', $policy->id, $policy->toArray(), $before?->toArray());

            return $policy;
        });

        return ApiResponse::success(['type' => 'quota_policy', ...$policy->toArray()]);
    }

    public function rules(Request $request): JsonResponse
    {
        $this->authorizeOperations($request);

        return ApiResponse::success(AlertRule::query()->latest('version')->get()
            ->map(fn (AlertRule $rule) => ['type' => 'alert_rule', ...$rule->toArray()]));
    }

    public function updateRule(Request $request, AlertRule $alertRule, RecentGoogleAdmin $recentGoogle): JsonResponse
    {
        $this->authorizeOperations($request);
        $recentGoogle->require($request);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'parameters' => ['required', 'array'],
        ]);
        if ($audit = $this->requestAudit($request)) {
            if ($audit->action !== 'alert_rule.versioned'
                || data_get($audit->after_state, 'enabled') !== $data['enabled']
                || data_get($audit->after_state, 'parameters') !== $data['parameters']) {
                throw new ConflictHttpException('X-Request-ID was already used for another operation.');
            }

            return ApiResponse::success(['type' => 'alert_rule', ...$audit->after_state]);
        }
        $rule = DB::transaction(function () use ($request, $data, $alertRule): AlertRule {
            AlertRule::query()->where('rule_key', $alertRule->rule_key)->lockForUpdate()->get();
            $rule = AlertRule::create([
                'rule_key' => $alertRule->rule_key,
                'version' => (int) AlertRule::where('rule_key', $alertRule->rule_key)->max('version') + 1,
                ...$data,
                'created_by' => $request->user()->id,
                'activated_at' => now('UTC'),
            ]);
            $this->audit($request, 'alert_rule.versioned', $rule->id, $rule->toArray(), $alertRule->toArray(), 'alert_rule');

            return $rule;
        });

        return ApiResponse::success(['type' => 'alert_rule', ...$rule->toArray()]);
    }

    public function contracts(Request $request): JsonResponse
    {
        $this->authorizeOperations($request);
        $path = base_path('docs/openapi/trace-cag-external-v1.schema.json');

        return ApiResponse::success([
            'type' => 'service_status',
            'trace_cag' => ['version' => 'v1', 'sha256' => is_file($path) ? hash_file('sha256', $path) : null],
        ]);
    }

    public function usage(Request $request): JsonResponse
    {
        $this->authorizeOperations($request);
        $query = LearningAssistanceResult::query();

        return ApiResponse::success([
            'type' => 'service_status',
            'last_24_hours' => (clone $query)->where('created_at', '>=', now('UTC')->subDay())->count(),
            'last_30_days' => (clone $query)->where('created_at', '>=', now('UTC')->subDays(30))->count(),
            'degraded_30_days' => (clone $query)->where('created_at', '>=', now('UTC')->subDays(30))
                ->where('status', 'degraded')->count(),
        ]);
    }

    public function audits(Request $request): JsonResponse
    {
        $this->authorizeOperations($request);

        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:150'],
            'search' => ['nullable', 'string', 'max:150'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = (int) ($data['per_page'] ?? 20);
        $page = OperationsAudit::query()->with('actor:id,name,email')
            ->when($data['action'] ?? null, fn ($query, string $action) => $query->where('action', $action))
            ->when($data['search'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                $query->where('action', 'like', "%{$search}%")
                    ->orWhere('target_type', 'like', "%{$search}%")
                    ->orWhere('target_id', 'like', "%{$search}%");
            }))
            ->when($data['from'] ?? null, fn ($query, string $from) => $query->where('occurred_at', '>=', "{$from} 00:00:00"))
            ->when($data['to'] ?? null, fn ($query, string $to) => $query->where('occurred_at', '<=', "{$to} 23:59:59"))
            ->latest('occurred_at')->paginate($perPage);

        return ApiResponse::success(collect($page->items())
            ->map(fn (OperationsAudit $audit) => [
                'type' => 'audit_event',
                'id' => $audit->id,
                'action' => $audit->action,
                'target_type' => $audit->target_type,
                'target_id' => $audit->target_id,
                'actor' => $audit->actor ? $audit->actor->only(['id', 'name', 'email']) : null,
                'occurred_at' => $audit->occurred_at?->toISOString(),
            ]), meta: [
                'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
            ]);
    }

    private function authorizeOperations(Request $request): void
    {
        abort_unless($request->user()->can('manage-operations'), 403);
    }

    private function requestAudit(Request $request): ?OperationsAudit
    {
        $requestId = $request->header('X-Request-ID');
        validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();

        return OperationsAudit::query()->where('request_id', $requestId)->first();
    }

    private function audit(
        Request $request,
        string $action,
        int|string $target,
        array $after,
        ?array $before = null,
        string $targetType = 'quota_policy',
    ): void {
        OperationsAudit::create([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => (string) $target,
            'request_id' => Str::isUuid($request->header('X-Request-ID')) ? $request->header('X-Request-ID') : null,
            'before_state' => $before,
            'after_state' => $after,
            'occurred_at' => now('UTC'),
        ]);
    }
}
