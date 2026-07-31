<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunAdminImport;
use App\Models\AdminImportLock;
use App\Models\AdminImportRun;
use App\Models\AdminPreference;
use App\Models\LexiLingoImportCheckpoint;
use App\Models\LexiLingoImportFailure;
use App\Models\StagedItem;
use App\Models\SupervisionAlert;
use App\Support\ApiResponse;
use App\Support\LexiLingoClient;
use App\Support\RecentPassword;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ContentOperationsController extends Controller
{
    private const ENTITIES = ['categories', 'courses', 'vocabulary', 'lessons'];

    public function checkpoints(Request $request): JsonResponse
    {
        $this->content($request);
        // TODO(#45): expose failure resolution lifecycle once staged-item
        // review lands — today failures are a count only.
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

    public function runs(Request $request): JsonResponse
    {
        $this->content($request);

        $data = $request->validate([
            'entity' => ['nullable', 'in:'.implode(',', self::ENTITIES)],
            'status' => ['nullable', 'in:pending,running,succeeded,review-ready,approved,failed'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = (int) ($data['per_page'] ?? 20);

        $page = AdminImportRun::query()
            ->withCount([
                'stagedItems as staged_new_count' => fn ($q) => $q->where('classification', 'new'),
                'stagedItems as staged_update_count' => fn ($q) => $q->where('classification', 'update'),
                'stagedItems as staged_invalid_count' => fn ($q) => $q->where('classification', 'invalid'),
            ])
            ->when($data['entity'] ?? null, fn ($q, $entity) => $q->where('entity', $entity))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', "{$from} 00:00:00"))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', "{$to} 23:59:59"))
            ->latest('created_at')
            ->paginate($perPage);

        return ApiResponse::success(
            collect($page->items())->map(fn (AdminImportRun $run): array => [
                ...$this->runData($run),
                'staged_new_count' => $run->staged_new_count,
                'staged_update_count' => $run->staged_update_count,
                'staged_invalid_count' => $run->staged_invalid_count,
            ]),
            meta: ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        );
    }

    public function run(Request $request, AdminImportRun $adminImportRun): JsonResponse
    {
        $this->content($request);

        return ApiResponse::success($this->runData($adminImportRun));
    }

    public function items(Request $request, AdminImportRun $adminImportRun): JsonResponse
    {
        $this->content($request);

        $data = $request->validate([
            'classification' => ['nullable', 'in:new,update,invalid,conflict,unchanged'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = (int) ($data['per_page'] ?? 25);

        $page = $adminImportRun->stagedItems()
            ->when($data['classification'] ?? null, fn ($q, $classification) => $q->where('classification', $classification))
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::success(
            collect($page->items())->map(fn (StagedItem $item): array => $item->only([
                'id', 'admin_import_run_id', 'entity', 'external_id', 'classification',
                'incoming_snapshot', 'existing_snapshot', 'errors', 'status', 'created_at', 'updated_at',
            ])),
            meta: ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        );
    }

    public function start(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('start-content-sync'), 403);

        return $this->reserve($request, false);
    }

    public function reset(Request $request, RecentPassword $recentPassword): JsonResponse
    {
        abort_unless($request->user()->can('retry-content-sync'), 403);
        $recentPassword->require($request);

        return $this->reserve($request, true);
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

    private function reserve(Request $request, bool $reset): JsonResponse
    {
        $data = $request->validate([
            'entity' => ['required', 'in:'.implode(',', self::ENTITIES)],
            'limit' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $requestId = $request->header('X-Request-ID');
        validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();
        $limit = config('queue.default') === 'sync' ? min(10, (int) $data['limit']) : (int) $data['limit'];
        $fingerprint = hash('sha256', json_encode([$data['entity'], $limit, $reset], JSON_THROW_ON_ERROR));

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
                if ($active && in_array($active->status, ['pending', 'running'], true)
                    && $active->updated_at->gt(now('UTC')->subMinutes(15))) {
                    throw new ConflictHttpException('An import for this entity is already running.');
                }
                $active?->update(['status' => 'failed', 'error_code' => 'stale_run', 'error_message' => 'Import worker timed out.']);
            }

            $cursor = $reset ? 0 : (int) (LexiLingoImportCheckpoint::query()
                ->where('entity', $data['entity'])->value('cursor') ?? 0);
            $run = AdminImportRun::query()->create([
                'request_id' => $requestId,
                'entity' => $data['entity'],
                'payload_fingerprint' => $fingerprint,
                'actor_id' => $request->user()->id,
                'status' => 'pending',
                'requested_limit' => $limit,
                'reset' => $reset,
                'starting_cursor' => $cursor,
            ]);
            $lock->update(['current_run_id' => $run->id, 'locked_at' => now('UTC')]);

            return $run;
        });

        if ($run->wasRecentlyCreated) {
            RunAdminImport::dispatch($run->id);
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
}
