<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminImportRun;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\OperationsAudit;
use App\Models\StagedItem;
use App\Models\Unit;
use App\Models\Vocabulary;
use App\Services\Import\FileCatalogRowExtractor;
use App\Services\Import\FileCatalogTreeBuilder;
use App\Support\ApiResponse;
use App\Support\RecentGoogleAdmin;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * One-shot admin file upload (CSV/XLSX/XLS/PDF-text) that stages a brand-new
 * Course -> Unit -> Lesson -> [Vocabulary] tree for review before it is
 * written to the catalog. Deliberately separate from ContentOperationsController:
 * that controller's apply() is mid-rewrite for the LexiLingo checkpoint/cursor
 * re-sync model (issue #46), which has nothing in common with a one-shot
 * upload that only ever creates new records. This controller reuses the same
 * StagedItem/AdminImportRun tables (entity = 'file_catalog' scopes every
 * query to this feature only) and the same Gate/step-up/audit conventions.
 */
class FileCatalogImportController extends Controller
{
    public function upload(Request $request, FileCatalogRowExtractor $extractor, FileCatalogTreeBuilder $treeBuilder): JsonResponse
    {
        $this->content($request);
        abort_unless(config('features.file_catalog_import'), 503, 'File catalog import is disabled.');

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls,pdf', 'max:5120'],
        ]);
        $requestId = $request->header('X-Request-ID');
        validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();

        $file = $data['file'];
        $fingerprint = hash('sha256', (string) file_get_contents($file->getRealPath()));

        if ($existing = AdminImportRun::query()->where('request_id', $requestId)->first()) {
            if ($existing->payload_fingerprint !== $fingerprint || $existing->entity !== 'file_catalog') {
                throw new ConflictHttpException('X-Request-ID was already used for another import.');
            }

            return ApiResponse::success(['run' => $this->runData($existing), 'staged_items' => $this->stagedItemsPayload($existing)]);
        }

        try {
            $rows = $extractor->extract($file->getRealPath(), strtolower((string) $file->getClientOriginalExtension()));
            $tree = $treeBuilder->build($rows);
        } catch (InvalidArgumentException $exception) {
            throw new HttpResponseException(ApiResponse::error('VALIDATION_ERROR', $exception->getMessage(), 422));
        }

        $run = DB::transaction(function () use ($request, $requestId, $fingerprint, $tree): AdminImportRun {
            $run = AdminImportRun::create([
                'request_id' => $requestId,
                'entity' => 'file_catalog',
                'payload_fingerprint' => $fingerprint,
                'actor_id' => $request->user()->id,
                'status' => 'review-ready',
                'requested_limit' => max(1, count($tree['courses'])),
                'reset' => false,
                'starting_cursor' => 0,
                'processed' => count($tree['courses']),
                'skipped' => 0,
            ]);

            foreach ($tree['courses'] as $courseTree) {
                $errors = $courseTree['errors'];
                unset($courseTree['errors']);
                StagedItem::create([
                    'admin_import_run_id' => $run->id,
                    'entity' => 'file_catalog',
                    'external_id' => $courseTree['slug'],
                    'classification' => 'new',
                    'incoming_snapshot' => $courseTree,
                    'existing_snapshot' => null,
                    'base_revision' => null,
                    'base_fingerprint' => null,
                    'errors' => $errors ?: null,
                    'status' => 'staged',
                ]);
            }

            // Rows that never named a determinable course still need to be
            // seen by the admin — they ride along as one 'invalid' item
            // instead of a silently dropped line (reuses the classification
            // ContentOperationsController already gives a meaning to).
            if ($tree['unassignedErrors'] !== []) {
                StagedItem::create([
                    'admin_import_run_id' => $run->id,
                    'entity' => 'file_catalog',
                    'external_id' => null,
                    'classification' => 'invalid',
                    'incoming_snapshot' => null,
                    'existing_snapshot' => null,
                    'errors' => $tree['unassignedErrors'],
                    'status' => 'staged',
                ]);
            }

            return $run;
        });

        return ApiResponse::success(['run' => $this->runData($run), 'staged_items' => $this->stagedItemsPayload($run)], 201);
    }

    public function runs(Request $request): JsonResponse
    {
        $this->content($request);

        $data = $request->validate([
            'status' => ['nullable', 'in:pending,running,succeeded,review-ready,approved,failed'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = (int) ($data['per_page'] ?? 20);

        $page = AdminImportRun::query()
            ->where('entity', 'file_catalog')
            ->withCount([
                'stagedItems as staged_new_count' => fn ($q) => $q->where('classification', 'new'),
                'stagedItems as staged_invalid_count' => fn ($q) => $q->where('classification', 'invalid'),
            ])
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('created_at')
            ->paginate($perPage);

        return ApiResponse::success(
            collect($page->items())->map(fn (AdminImportRun $run): array => [
                ...$this->runData($run),
                'staged_new_count' => $run->staged_new_count,
                'staged_invalid_count' => $run->staged_invalid_count,
            ]),
            meta: ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        );
    }

    public function run(Request $request, AdminImportRun $adminImportRun): JsonResponse
    {
        $this->content($request);
        abort_unless($adminImportRun->entity === 'file_catalog', 404);

        return ApiResponse::success($this->runData($adminImportRun));
    }

    public function items(Request $request, AdminImportRun $adminImportRun): JsonResponse
    {
        $this->content($request);
        abort_unless($adminImportRun->entity === 'file_catalog', 404);

        $perPage = (int) ($request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']])['per_page'] ?? 25);
        $payload = $this->stagedItemsPayload($adminImportRun, $perPage);

        return ApiResponse::success($payload['data'], meta: $payload['meta']);
    }

    /**
     * Same security bar as ContentOperationsController::apply(): capability
     * gate before the feature flag (a role that may not apply gets a plain
     * 403, never a 428 that would confirm the flag is on), then a recent
     * Google step-up. Each staged course applies independently inside its
     * own transaction so one bad course never blocks the rest of the run.
     */
    public function apply(Request $request, AdminImportRun $adminImportRun, RecentGoogleAdmin $recentGoogle): JsonResponse
    {
        abort_unless($request->user()->can('apply-content-import'), 403);
        abort_unless(config('features.file_catalog_import'), 503, 'File catalog import is disabled.');
        $recentGoogle->require($request);
        abort_unless($adminImportRun->entity === 'file_catalog', 422, 'Apply is only available for file_catalog runs here.');

        $data = $request->validate([
            'item_ids' => ['sometimes', 'array'],
            'item_ids.*' => ['integer'],
        ]);
        validator(['request_id' => $request->header('X-Request-ID')], ['request_id' => ['required', 'uuid']])->validate();

        $itemsQuery = $adminImportRun->stagedItems()->where('status', 'staged')->where('classification', 'new');
        if (! empty($data['item_ids'])) {
            $itemsQuery->whereIn('id', $data['item_ids']);
        }

        $applied = [];
        $failed = [];

        foreach ($itemsQuery->get() as $item) {
            try {
                DB::transaction(function () use ($item, &$applied, &$failed): void {
                    $locked = StagedItem::query()->whereKey($item->id)->lockForUpdate()->first();
                    if (! $locked || $locked->status !== 'staged') {
                        return; // already resolved by an earlier/concurrent apply call
                    }

                    $tree = $locked->incoming_snapshot ?? [];
                    if (Course::query()->where('slug', $tree['slug'] ?? null)->exists()) {
                        $locked->update([
                            'status' => 'failed',
                            'errors' => [...($locked->errors ?? []), "Course slug '{$tree['slug']}' already exists."],
                        ]);
                        $failed[] = $locked->id;

                        return;
                    }

                    $this->createCourseTree($tree);
                    $locked->update(['status' => 'applied']);
                    $applied[] = $locked->id;
                });
            } catch (Throwable $exception) {
                $item->update(['status' => 'failed', 'errors' => [...($item->errors ?? []), $exception->getMessage()]]);
                $failed[] = $item->id;
            }
        }

        OperationsAudit::create([
            'actor_id' => $request->user()->id,
            'action' => 'file_catalog_import.applied',
            'target_type' => 'admin_import_run',
            'target_id' => (string) $adminImportRun->id,
            'request_id' => $request->header('X-Request-ID'),
            'context' => ['run_id' => $adminImportRun->id, 'requested_item_ids' => $data['item_ids'] ?? null],
            'after_state' => ['applied' => count($applied), 'failed' => count($failed)],
            'occurred_at' => now('UTC'),
        ]);

        if (count($applied) + count($failed) > 0) {
            $adminImportRun->update(['status' => 'approved']);
        }

        return ApiResponse::success(['applied' => $applied, 'failed' => $failed]);
    }

    /**
     * Downloadable CSV that is also the spec for XLSX (opens the same way)
     * and PDF (ship the header + rows as pipe-delimited plain text lines —
     * there is no table extraction without OCR, so PDF import expects the
     * exact same columns as plain text joined by '|' instead of commas).
     */
    public function template(Request $request): StreamedResponse
    {
        $this->content($request);
        abort_unless(config('features.file_catalog_import'), 503, 'File catalog import is disabled.');

        $rows = [
            FileCatalogTreeBuilder::COLUMNS,
            ['Everyday English', 'everyday-english', 'Greetings', 'Saying Hello', 'Learn common greetings.', 'hello', 'a word used to greet someone', 'həˈloʊ', 'interjection', 'Hello, how are you?'],
            ['Everyday English', 'everyday-english', 'Greetings', 'Saying Hello', '', 'goodbye', 'a farewell expression', 'ɡʊdˈbaɪ', 'interjection', 'Goodbye, see you tomorrow!'],
            ['Everyday English', 'everyday-english', 'Numbers', 'Counting to Ten', 'Learn to count from one to ten.', 'one', 'the number 1', 'wʌn', 'noun', 'I have one apple.'],
        ];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'file-catalog-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array<string, mixed>  $tree
     */
    private function createCourseTree(array $tree): void
    {
        $course = Course::create(['title' => $tree['title'], 'slug' => $tree['slug'], 'status' => 'draft']);
        $course->applyLocalEdit([]);

        foreach ($tree['units'] as $unitIndex => $unitTree) {
            $unit = Unit::create([
                'course_id' => $course->id, 'title' => $unitTree['title'],
                'sort_order' => $unitIndex, 'status' => 'draft',
            ]);
            $unit->applyLocalEdit([]);

            foreach ($unitTree['lessons'] as $lessonIndex => $lessonTree) {
                $lesson = Lesson::create([
                    'course_id' => $course->id,
                    'unit_id' => $unit->id,
                    'title' => $lessonTree['title'],
                    'slug' => Str::slug($lessonTree['title']).'-'.Str::random(6),
                    'content' => $lessonTree['content'] !== '' ? $lessonTree['content'] : null,
                    'sort_order' => $lessonIndex,
                    'status' => 'draft',
                ]);
                $lesson->applyLocalEdit([]);

                foreach ($lessonTree['vocabulary'] as $vocab) {
                    $vocabulary = Vocabulary::create([
                        'lesson_id' => $lesson->id,
                        'word' => $vocab['word'],
                        'meaning' => $vocab['meaning'],
                        'pronunciation' => $vocab['pronunciation'],
                        'part_of_speech' => $vocab['part_of_speech'],
                        'example' => $vocab['example'],
                    ]);
                    $vocabulary->applyLocalEdit([]);
                }
            }
        }
    }

    /**
     * @return array{data: \Illuminate\Support\Collection, meta: array}
     */
    private function stagedItemsPayload(AdminImportRun $run, int $perPage = 25): array
    {
        $page = $run->stagedItems()->latest('id')->paginate($perPage);

        return [
            'data' => collect($page->items())->map(fn (StagedItem $item): array => $item->only([
                'id', 'admin_import_run_id', 'entity', 'external_id', 'classification',
                'incoming_snapshot', 'existing_snapshot', 'errors', 'status', 'created_at', 'updated_at',
            ])),
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ];
    }

    private function runData(AdminImportRun $run): array
    {
        return $run->only([
            'id', 'request_id', 'entity', 'status', 'requested_limit', 'reset',
            'starting_cursor', 'processed', 'skipped', 'result_cursor',
            'error_code', 'error_message', 'created_at', 'updated_at',
        ]);
    }

    // ponytail: same one-liner Gate check ContentOperationsController::content()
    // uses — not worth extracting to a trait for a single reused line.
    private function content(Request $request): void
    {
        abort_unless($request->user()->can('manage-content'), 403);
    }
}
