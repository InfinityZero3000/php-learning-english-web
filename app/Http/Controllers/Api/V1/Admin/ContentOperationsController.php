<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunAdminImport;
use App\Models\AdminImportLock;
use App\Models\AdminImportRun;
use App\Models\AdminPreference;
use App\Models\LexiLingoImportCheckpoint;
use App\Models\LexiLingoImportFailure;
use App\Models\OperationsAudit;
use App\Models\SupervisionAlert;
use App\Services\AdminImportApproval;
use App\Support\ApiResponse;
use App\Support\LexiLingoClient;
use App\Support\RecentGoogleAdmin;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ContentOperationsController extends Controller
{
    private const ENTITIES = ['categories', 'courses', 'vocabulary'];

    public function checkpoints(Request $request): JsonResponse
    {
        $this->content($request);
        $checkpoints = collect(self::ENTITIES)->map(function (string $entity): array {
            $checkpoint = LexiLingoImportCheckpoint::query()->where('entity', $entity)->first();

            return [
                'entity' => $entity,
                'cursor' => (int) ($checkpoint?->cursor ?? 0),
                'last_synced_at' => $checkpoint?->last_synced_at?->toISOString(),
                'failures' => LexiLingoImportFailure::query()->where('entity', $entity)->count(),
            ];
        });

        return ApiResponse::success($checkpoints);
    }

    public function run(Request $request, AdminImportRun $adminImportRun): JsonResponse
    {
        $this->content($request);

        return ApiResponse::success([
            ...$this->runData($adminImportRun),
            'counts' => $adminImportRun->items()->selectRaw('classification, count(*) as total')
                ->groupBy('classification')->pluck('total', 'classification'),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('review-imports'), 403);
        $runs = AdminImportRun::query()->withCount('items')->latest('id')->limit(50)->get()
            ->map(fn (AdminImportRun $run): array => [...$this->runData($run), 'items_count' => $run->items_count]);

        return ApiResponse::success($runs);
    }

    public function items(Request $request, AdminImportRun $adminImportRun): JsonResponse
    {
        abort_unless($request->user()->can('review-imports'), 403);

        return ApiResponse::success($adminImportRun->items()->orderBy('id')->get()->map(
            fn ($item): array => $item->only([
                'id', 'parent_item_id', 'entity', 'source_system', 'external_id',
                'natural_key', 'candidate_payload', 'base_snapshot', 'classification',
                'selected_action', 'dependencies', 'validation_errors', 'target_id',
                'base_revision', 'reviewed_at', 'applied_at',
            ]),
        ));
    }

    public function draft(
        Request $request,
        AdminImportRun $adminImportRun,
        AdminImportApproval $approval,
    ): JsonResponse {
        abort_unless($request->user()->can('review-imports'), 403);
        $data = $request->validate([
            'items' => ['required', 'array', 'max:500'],
            'items.*.id' => ['required', 'integer'],
            'items.*.action' => ['required', 'in:add,skip,replace,keep_local,exclude'],
        ]);
        $items = collect($data['items'])->sortBy('id')->values()->all();
        $run = DB::transaction(function () use ($request, $adminImportRun, $approval, $items): AdminImportRun {
            if ($this->replay($request, 'content_import.draft_saved', $adminImportRun, $items)) {
                return $adminImportRun->fresh();
            }
            $run = $approval->saveDraft($adminImportRun, $request->user(), $items);
            $this->audit($request, 'content_import.draft_saved', $run, [
                'items' => collect($items)->pluck('action', 'id')->all(),
            ], $items);

            return $run;
        });

        return ApiResponse::success($this->runData($run));
    }

    public function approve(
        Request $request,
        AdminImportRun $adminImportRun,
        AdminImportApproval $approval,
    ): JsonResponse {
        abort_unless($request->user()->can('approve-imports'), 403);
        $run = DB::transaction(function () use ($request, $adminImportRun, $approval): AdminImportRun {
            if ($this->replay($request, 'content_import.approved', $adminImportRun)) {
                return $adminImportRun->fresh();
            }
            $run = $approval->approve($adminImportRun, $request->user());
            $this->audit($request, 'content_import.approved', $run, ['status' => 'approved']);

            return $run;
        });

        return ApiResponse::success($this->runData($run));
    }

    public function apply(
        Request $request,
        AdminImportRun $adminImportRun,
        AdminImportApproval $approval,
    ): JsonResponse {
        if ($this->replay($request, 'content_import.applied', $adminImportRun)) {
            return ApiResponse::success($this->runData($adminImportRun->fresh()));
        }

        return ApiResponse::success($this->runData($approval->apply($adminImportRun, $request)));
    }

    public function start(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('start-content-sync'), 403);
        abort_unless(config('features.lexilingo_import'), 503, 'LexiLingo import is disabled.');

        return $this->reserve($request, false);
    }

    public function reset(Request $request, RecentGoogleAdmin $recentGoogle): JsonResponse
    {
        abort_unless($request->user()->can('retry-content-sync'), 403);
        abort_unless(config('features.lexilingo_import'), 503, 'LexiLingo import is disabled.');
        $recentGoogle->require($request);

        return $this->reserve($request, true);
    }

    public function retry(
        Request $request,
        AdminImportRun $adminImportRun,
        RecentGoogleAdmin $recentGoogle,
    ): JsonResponse {
        abort_unless($request->user()->can('retry-content-sync'), 403);
        abort_unless(in_array($adminImportRun->status, ['validation_failed', 'apply_failed', 'cancelled'], true), 409);
        $recentGoogle->require($request);
        $request->merge([
            'entity' => $adminImportRun->entity,
            'limit' => $adminImportRun->requested_limit,
        ]);

        return $this->reserve($request, $adminImportRun->reset, $adminImportRun);
    }

    public function cancel(Request $request, AdminImportRun $adminImportRun): JsonResponse
    {
        abort_unless($request->user()->can('cancel-imports'), 403);
        $run = DB::transaction(function () use ($request, $adminImportRun): AdminImportRun {
            if ($this->replay($request, 'content_import.cancelled', $adminImportRun)) {
                return $adminImportRun->fresh();
            }
            AdminImportLock::query()->lockForUpdate()->findOrFail($adminImportRun->entity);
            $run = AdminImportRun::query()->lockForUpdate()->findOrFail($adminImportRun->id);
            abort_unless(in_array($run->status, ['fetching', 'validating', 'review_ready', 'approved'], true), 409);
            $run->update(['status' => 'cancelled']);
            AdminImportLock::query()->whereKey($run->entity)->where('current_run_id', $run->id)
                ->update(['current_run_id' => null, 'locked_at' => null]);
            $this->audit($request, 'content_import.cancelled', $run, ['status' => 'cancelled']);

            return $run;
        });

        return ApiResponse::success($this->runData($run));
    }

    public function feed(Request $request, LexiLingoClient $client): JsonResponse
    {
        $this->content($request);
        $data = $request->validate([
            'source' => ['required', 'in:youtube,news'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $path = $data['source'] === 'youtube'
            ? '/api/v1/integrations/youtube/search'
            : '/api/v1/integrations/news';

        try {
            $response = $client->partner()->get($path, array_filter(['q' => $data['q'] ?? null]))->throw()->json();
        } catch (ConnectionException|RequestException|RuntimeException) {
            abort(503, 'LexiLingo content provider is temporarily unavailable.');
        }

        $items = is_array($response) ? ($response['data'] ?? $response) : [];

        return ApiResponse::success(array_slice(is_array($items) ? $items : [], 0, 50));
    }

    public function notifications(Request $request): JsonResponse
    {
        $this->content($request);
        $readIds = DB::table('admin_notification_reads')->where('user_id', $request->user()->id)
            ->pluck('supervision_alert_id')->all();
        $alerts = SupervisionAlert::query()->latest('detected_at')->limit(100)->get()->map(
            fn (SupervisionAlert $alert): array => [
                'id' => $alert->id,
                'type' => $alert->rule_key,
                'severity' => $alert->severity,
                'state' => $alert->state,
                'summary' => 'Learning supervision alert requires review.',
                'created_at' => $alert->detected_at?->toISOString(),
                'resolved_at' => $alert->resolved_at?->toISOString(),
                'read' => in_array($alert->id, $readIds, true),
            ],
        );

        return ApiResponse::success($alerts);
    }

    public function readNotification(Request $request, SupervisionAlert $supervisionAlert): JsonResponse
    {
        $this->content($request);
        DB::table('admin_notification_reads')->upsert([[
            'user_id' => $request->user()->id,
            'supervision_alert_id' => $supervisionAlert->id,
            'read_at' => now('UTC'),
        ]], ['user_id', 'supervision_alert_id'], ['read_at']);

        return ApiResponse::success(['id' => $supervisionAlert->id, 'read' => true]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $this->content($request);
        $preference = AdminPreference::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['notifications' => ['operational' => true], 'ui' => []],
        );

        return ApiResponse::success($preference->only(['notifications', 'ui']));
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $this->content($request);
        $data = $request->validate([
            'notifications' => ['required', 'array'],
            'notifications.operational' => ['required', 'boolean'],
            'ui' => ['required', 'array'],
            'ui.compact_sidebar' => ['sometimes', 'boolean'],
        ]);
        $preference = AdminPreference::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return ApiResponse::success($preference->only(['notifications', 'ui']));
    }

    private function reserve(
        Request $request,
        bool $reset,
        ?AdminImportRun $retryOf = null,
    ): JsonResponse {
        $data = $request->validate([
            'entity' => ['required', 'in:'.implode(',', self::ENTITIES)],
            'limit' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $requestId = $request->header('X-Request-ID');
        validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();
        $limit = config('queue.default') === 'sync' ? min(10, (int) $data['limit']) : (int) $data['limit'];
        $fingerprint = hash('sha256', json_encode([
            $data['entity'], $limit, $reset, $retryOf?->id,
        ], JSON_THROW_ON_ERROR));

        $run = DB::transaction(function () use ($request, $data, $requestId, $limit, $reset, $fingerprint): AdminImportRun {
            if ($existing = AdminImportRun::query()->where('request_id', $requestId)->first()) {
                if ($existing->payload_fingerprint !== $fingerprint) {
                    throw new ConflictHttpException('X-Request-ID was already used for another import.');
                }

                return $existing;
            }

            $lock = AdminImportLock::query()->lockForUpdate()->findOrFail($data['entity']);
            if ($lock->current_run_id) {
                $active = AdminImportRun::query()->find($lock->current_run_id);
                if ($active && in_array($active->status, ['pending', 'running', 'fetching', 'validating'], true)
                    && $active->updated_at->gt(now('UTC')->subMinutes(15))) {
                    throw new ConflictHttpException('An import for this entity is already running.');
                }
                $active?->update(['status' => 'validation_failed', 'error_code' => 'stale_run', 'error_message' => 'Import worker timed out.']);
            }

            $cursor = $reset ? 0 : (int) (LexiLingoImportCheckpoint::query()
                ->where('entity', $data['entity'])->value('cursor') ?? 0);
            $run = AdminImportRun::query()->create([
                'request_id' => $requestId,
                'entity' => $data['entity'],
                'payload_fingerprint' => $fingerprint,
                'actor_id' => $request->user()->id,
                'status' => 'fetching',
                'requested_limit' => $limit,
                'reset' => $reset,
                'starting_cursor' => $cursor,
            ]);
            $lock->update(['current_run_id' => $run->id, 'locked_at' => now('UTC')]);

            return $run;
        });

        if ($run->wasRecentlyCreated) {
            RunAdminImport::dispatch($run->id);
            if ($reset || $retryOf) {
                OperationsAudit::create([
                    'actor_id' => $request->user()->id,
                    'action' => $retryOf ? 'content_import.retried' : 'content_import.reset',
                    'target_type' => 'admin_import_run',
                    'target_id' => (string) $run->id,
                    'context' => array_filter([
                        'source_run_id' => $retryOf?->id,
                        'new_run_id' => $run->id,
                        'reset' => $reset,
                    ], fn ($value): bool => $value !== null),
                    'occurred_at' => now('UTC'),
                ]);
            }
        }

        return ApiResponse::success($this->runData($run->fresh()), status: 202);
    }

    private function runData(AdminImportRun $run): array
    {
        return $run->only([
            'id', 'request_id', 'entity', 'status', 'requested_limit', 'reset',
            'starting_cursor', 'processed', 'skipped', 'result_cursor',
            'error_code', 'error_message', 'created_at', 'updated_at',
        ]);
    }

    private function content(Request $request): void
    {
        abort_unless($request->user()->can('manage-content'), 403);
    }

    private function replay(
        Request $request,
        string $action,
        AdminImportRun $run,
        array $payload = [],
    ): ?OperationsAudit {
        $requestId = $request->header('X-Request-ID');
        validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();
        $audit = OperationsAudit::query()->where('request_id', $requestId)->lockForUpdate()->first();
        if ($audit && ($audit->action !== $action
            || $audit->target_type !== 'admin_import_run'
            || $audit->target_id !== (string) $run->id
            || data_get($audit->context, 'fingerprint') !== $this->fingerprint($run, $action, $payload))) {
            throw new ConflictHttpException('X-Request-ID was already used for another operation.');
        }

        return $audit;
    }

    private function audit(
        Request $request,
        string $action,
        AdminImportRun $run,
        array $after,
        array $payload = [],
    ): void {
        OperationsAudit::create([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'target_type' => 'admin_import_run',
            'target_id' => (string) $run->id,
            'request_id' => $request->header('X-Request-ID'),
            'context' => ['fingerprint' => $this->fingerprint($run, $action, $payload)],
            'after_state' => $after,
            'occurred_at' => now('UTC'),
        ]);
    }

    private function fingerprint(AdminImportRun $run, string $action, array $payload): string
    {
        return hash('sha256', json_encode([
            'run' => $run->id,
            'action' => $action,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR));
    }
}
