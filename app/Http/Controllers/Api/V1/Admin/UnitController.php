<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\WriteUnitRequest;
use App\Models\Course;
use App\Models\OperationsAudit;
use App\Models\Unit;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $this->validate($request->query(), [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
        ]);
        $units = Unit::query()
            ->where('course_id', $data['course_id'])
            ->with(['lessons' => fn ($query) => $query->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success($units->map(fn (Unit $unit) => $this->resource($unit))->all());
    }

    public function store(WriteUnitRequest $request): JsonResponse
    {
        $data = $request->validated();
        $fingerprint = $this->fingerprint(['create', $data]);
        if ($audit = $this->replay($request, 'unit.created', $fingerprint)) {
            return ApiResponse::success($audit->after_state, status: 201);
        }

        try {
            $unit = DB::transaction(function () use ($request, $data, $fingerprint): Unit {
                $unit = Unit::create([...$data, 'status' => 'draft']);
                $unit->applyLocalEdit([]);
                $resource = $this->resource($unit);
                $this->audit($request, 'unit.created', $unit, $fingerprint, null, $resource);

                return $unit;
            });
        } catch (QueryException $exception) {
            if ($audit = $this->replay($request, 'unit.created', $fingerprint)) {
                return ApiResponse::success($audit->after_state, status: 201);
            }
            throw $exception;
        }

        return ApiResponse::success($this->resource($unit), status: 201);
    }

    public function show(Unit $unit): JsonResponse
    {
        return ApiResponse::success($this->resource($unit));
    }

    public function update(WriteUnitRequest $request, Unit $unit): JsonResponse
    {
        return $this->mutate($request, $unit, 'unit.updated', $request->validated(), function (Unit $locked, array $data): void {
            if ($data['course_id'] !== $locked->course_id) {
                throw new HttpResponseException(ApiResponse::error(
                    'VALIDATION_ERROR',
                    'The given data was invalid.',
                    422,
                    ['errors' => ['course_id' => ['A unit cannot be moved to another course.']]],
                ));
            }
            $locked->applyLocalEdit($data);
        });
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $this->validate($request->all(), [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['required', 'integer', 'distinct', 'exists:units,id'],
        ]);
        $fingerprint = $this->fingerprint(['reorder', $data]);
        if ($audit = $this->replay($request, 'unit.reordered', $fingerprint)) {
            return ApiResponse::success($audit->after_state);
        }

        try {
            $resources = DB::transaction(function () use ($request, $data, $fingerprint): array {
                Course::query()->lockForUpdate()->findOrFail($data['course_id']);
                $units = Unit::query()->where('course_id', $data['course_id'])->lockForUpdate()->get()->keyBy('id');
                if ($units->keys()->sort()->values()->all() !== collect($data['unit_ids'])->sort()->values()->all()) {
                    throw new HttpResponseException(ApiResponse::error(
                        'VALIDATION_ERROR',
                        'The unit list must contain every unit in the course exactly once.',
                        422,
                        ['errors' => ['unit_ids' => ['The unit list does not match the course.']]],
                    ));
                }

                $offset = max(1000, (int) $units->max('sort_order') + $units->count() + 1);
                foreach ($data['unit_ids'] as $index => $id) {
                    Unit::query()->whereKey($id)->update(['sort_order' => $offset + $index]);
                }
                foreach ($data['unit_ids'] as $index => $id) {
                    $units[$id]->applyLocalEdit(['sort_order' => $index]);
                }

                $resources = collect($data['unit_ids'])
                    ->map(fn (int $id) => $this->resource(Unit::findOrFail($id)))
                    ->all();
                $this->audit($request, 'unit.reordered', null, $fingerprint, null, $resources, (string) $data['course_id']);

                return $resources;
            });
        } catch (QueryException $exception) {
            if ($audit = $this->replay($request, 'unit.reordered', $fingerprint)) {
                return ApiResponse::success($audit->after_state);
            }
            throw $exception;
        }

        return ApiResponse::success($resources);
    }

    public function publish(Request $request, Unit $unit): JsonResponse
    {
        return $this->mutate($request, $unit, 'unit.published', ['status' => 'published'], function (Unit $locked): void {
            if ($locked->status !== 'draft'
                || $locked->course()->where('status', 'published')->doesntExist()
                || $locked->lessons()->where('status', 'published')->doesntExist()) {
                throw new ConflictHttpException('A draft unit needs a published course and published lesson before publishing.');
            }
            $locked->applyLocalEdit(['status' => 'published']);
        });
    }

    public function archive(Request $request, Unit $unit): JsonResponse
    {
        return $this->mutate($request, $unit, 'unit.archived', ['status' => 'archived'], function (Unit $locked): void {
            if ($locked->status === 'archived') {
                throw new ConflictHttpException('The unit is already archived.');
            }
            $locked->applyLocalEdit(['status' => 'archived']);
        });
    }

    public function destroy(Request $request, int $unit): JsonResponse
    {
        $fingerprint = $this->fingerprint(['delete', $unit]);
        if ($this->replay($request, 'unit.deleted', $fingerprint)) {
            return response()->json(null, 204);
        }

        try {
            $deleted = DB::transaction(function () use ($request, $unit, $fingerprint): bool {
                $locked = Unit::query()->lockForUpdate()->find($unit);
                if (! $locked) {
                    return false;
                }
                if ($locked->status !== 'draft' || $locked->lessons()->exists()) {
                    throw new ConflictHttpException('Only an empty draft unit can be deleted.');
                }
                $before = $this->resource($locked);
                $locked->delete();
                $this->audit($request, 'unit.deleted', $locked, $fingerprint, $before, null);

                return true;
            });
        } catch (QueryException $exception) {
            if ($this->replay($request, 'unit.deleted', $fingerprint)) {
                return response()->json(null, 204);
            }
            throw $exception;
        }
        if (! $deleted && ! $this->replay($request, 'unit.deleted', $fingerprint)) {
            abort(404);
        }

        return response()->json(null, 204);
    }

    private function mutate(Request $request, Unit $unit, string $action, array $data, callable $mutation): JsonResponse
    {
        $fingerprint = $this->fingerprint([$action, $unit->id, $data]);
        if ($audit = $this->replay($request, $action, $fingerprint)) {
            return ApiResponse::success($audit->after_state);
        }

        try {
            $resource = DB::transaction(function () use ($request, $unit, $action, $data, $mutation, $fingerprint): array {
                $locked = Unit::query()->lockForUpdate()->findOrFail($unit->id);
                $before = $this->resource($locked);
                $mutation($locked, $data);
                $resource = $this->resource($locked->fresh());
                $this->audit($request, $action, $locked, $fingerprint, $before, $resource);

                return $resource;
            });
        } catch (QueryException $exception) {
            if ($audit = $this->replay($request, $action, $fingerprint)) {
                return ApiResponse::success($audit->after_state);
            }
            throw $exception;
        }

        return ApiResponse::success($resource);
    }

    private function resource(Unit $unit): array
    {
        $unit->loadMissing(['course:id,title', 'lessons' => fn ($query) => $query->orderBy('sort_order')]);

        return [
            'id' => $unit->id,
            'course_id' => $unit->course_id,
            'course' => $unit->course,
            'title' => $unit->title,
            'description' => $unit->description,
            'sort_order' => $unit->sort_order,
            'icon_url' => $unit->icon_url,
            'background_color' => $unit->background_color,
            'status' => $unit->status,
            'source_system' => $unit->source_system,
            'local_override_at' => $unit->local_override_at?->toISOString(),
            'catalog_revision' => $unit->catalog_revision,
            'lessons' => $unit->lessons->map(fn ($lesson) => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'slug' => $lesson->slug,
                'sort_order' => $lesson->sort_order,
                'status' => $lesson->status,
                'unit_id' => $lesson->unit_id,
            ])->all(),
        ];
    }

    private function replay(Request $request, string $action, string $fingerprint): ?OperationsAudit
    {
        $requestId = $request->header('X-Request-ID');
        $this->validate(['request_id' => $requestId], ['request_id' => ['required', 'uuid']]);
        $audit = OperationsAudit::query()->where('request_id', $requestId)->first();
        if ($audit && ($audit->action !== $action || data_get($audit->context, 'fingerprint') !== $fingerprint)) {
            throw new ConflictHttpException('X-Request-ID was already used for another operation.');
        }

        return $audit;
    }

    private function audit(
        Request $request,
        string $action,
        ?Unit $unit,
        string $fingerprint,
        ?array $before,
        ?array $after,
        ?string $targetId = null,
    ): void {
        OperationsAudit::create([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'target_type' => 'unit',
            'target_id' => $targetId ?? (string) $unit?->id,
            'request_id' => $request->header('X-Request-ID'),
            'context' => ['fingerprint' => $fingerprint],
            'before_state' => $before,
            'after_state' => $after,
            'occurred_at' => now('UTC'),
        ]);
    }

    private function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function validate(array $input, array $rules): array
    {
        $validator = validator($input, $rules);
        if ($validator->fails()) {
            throw new HttpResponseException(ApiResponse::error('VALIDATION_ERROR', 'The given data was invalid.', 422, [
                'errors' => $validator->errors(),
            ]));
        }

        return $validator->validated();
    }
}
