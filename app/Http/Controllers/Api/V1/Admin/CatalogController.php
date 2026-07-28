<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\OperationsAudit;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CatalogController extends Controller
{
    public function courses(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage-content'), 403);
        $page = Course::query()->withCount(['units', 'lessons'])
            ->when($request->string('search')->toString(), fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->latest()->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return ApiResponse::success(CourseResource::collection(collect($page->items()))->resolve(), meta: [
            'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function createCourse(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage-content'), 403);
        $fingerprint = $this->fingerprint(['create', $request->only([
            'title', 'slug', 'description', 'status', 'language', 'estimated_duration',
        ])]);
        if ($course = $this->replay($request, 'course.created', $fingerprint)) {
            return ApiResponse::success((new CourseResource($course->loadCount(['units', 'lessons'])))->resolve(), status: 201);
        }
        $data = $this->validated($request);
        try {
            $course = DB::transaction(function () use ($request, $data, $fingerprint): Course {
                $course = Course::create($data);
                $this->audit($request, 'course.created', $course, $fingerprint, null);

                return $course;
            });
        } catch (QueryException $exception) {
            $course = $this->replay($request, 'course.created', $fingerprint);
            if (! $course) {
                throw $exception;
            }
        }

        return ApiResponse::success((new CourseResource($course->loadCount(['units', 'lessons'])))->resolve(), status: 201);
    }

    public function course(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->can('manage-content'), 403);

        return ApiResponse::success((new CourseResource($course->loadCount(['units', 'lessons'])))->resolve());
    }

    public function updateCourse(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->can('manage-content'), 403);
        $data = $this->validated($request, $course);
        $fingerprint = $this->fingerprint(['update', $course->id, $data]);
        if ($replayed = $this->replay($request, 'course.updated', $fingerprint)) {
            return ApiResponse::success((new CourseResource($replayed->loadCount(['units', 'lessons'])))->resolve());
        }
        try {
            DB::transaction(function () use ($request, $course, $data, $fingerprint): void {
                $before = $course->toArray();
                $course->update($data);
                $this->audit($request, 'course.updated', $course, $fingerprint, $before);
            });
        } catch (QueryException $exception) {
            $course = $this->replay($request, 'course.updated', $fingerprint);
            if (! $course) {
                throw $exception;
            }
        }

        return ApiResponse::success((new CourseResource($course->loadCount(['units', 'lessons'])))->resolve());
    }

    public function publishCourse(Request $request, Course $course): JsonResponse
    {
        return $this->setStatus($request, $course, 'published');
    }

    public function archiveCourse(Request $request, Course $course): JsonResponse
    {
        return $this->setStatus($request, $course, 'archived');
    }

    private function validated(Request $request, ?Course $course = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('courses', 'slug')->ignore($course)],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'language' => ['nullable', 'string', 'size:2'],
            'estimated_duration' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    private function setStatus(Request $request, Course $course, string $status): JsonResponse
    {
        abort_unless($request->user()->can('manage-content'), 403);
        $action = "course.{$status}";
        $fingerprint = $this->fingerprint([$action, $course->id]);
        if ($replayed = $this->replay($request, $action, $fingerprint)) {
            return ApiResponse::success((new CourseResource($replayed->loadCount(['units', 'lessons'])))->resolve());
        }
        try {
            DB::transaction(function () use ($request, $course, $status, $action, $fingerprint): void {
                $before = $course->toArray();
                $course->update(['status' => $status]);
                $this->audit($request, $action, $course, $fingerprint, $before);
            });
        } catch (QueryException $exception) {
            $course = $this->replay($request, $action, $fingerprint);
            if (! $course) {
                throw $exception;
            }
        }

        return ApiResponse::success((new CourseResource($course->loadCount(['units', 'lessons'])))->resolve());
    }

    private function replay(Request $request, string $action, string $fingerprint): ?Course
    {
        $requestId = $request->header('X-Request-ID');
        validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();
        $audit = OperationsAudit::query()->where('request_id', $requestId)->first();
        if (! $audit) {
            return null;
        }
        if ($audit->action !== $action || data_get($audit->context, 'fingerprint') !== $fingerprint) {
            throw new ConflictHttpException('X-Request-ID was already used for another operation.');
        }

        return (new Course)->newFromBuilder($audit->after_state);
    }

    private function audit(Request $request, string $action, Course $course, string $fingerprint, ?array $before): void
    {
        OperationsAudit::create([
            'actor_id' => $request->user()->id, 'action' => $action,
            'target_type' => 'course', 'target_id' => (string) $course->id,
            'request_id' => $request->header('X-Request-ID'),
            'context' => ['fingerprint' => $fingerprint], 'before_state' => $before,
            'after_state' => $course->toArray(), 'occurred_at' => now('UTC'),
        ]);
    }

    private function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
