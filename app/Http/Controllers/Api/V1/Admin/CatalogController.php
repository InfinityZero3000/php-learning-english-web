<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\Level;
use App\Models\OperationsAudit;
use App\Models\Topic;
use App\Models\Vocabulary;
use App\Models\VocabularyDeck;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CatalogController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $this->authorizeCatalog($request);

        return ApiResponse::success([
            'courses' => Course::count(), 'levels' => Level::count(), 'topics' => Topic::count(),
            'vocabularies' => Vocabulary::count(), 'decks' => VocabularyDeck::count(),
        ]);
    }

    public function levels(Request $request): JsonResponse
    {
        return $this->paginated($request, Level::query()->withCount('courses'), ['name', 'slug']);
    }

    public function topics(Request $request): JsonResponse
    {
        return $this->paginated($request, Topic::query()->withCount(['courses', 'vocabularies']), ['name', 'slug']);
    }

    public function vocabularies(Request $request): JsonResponse
    {
        return $this->paginated($request, Vocabulary::query()->with(['topic:id,name'])->withCount('decks'), ['word', 'meaning', 'definition']);
    }

    public function vocabulary(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $this->authorizeCatalog($request);

        return ApiResponse::success((new \App\Http\Resources\VocabularyResource($vocabulary->load('topic:id,name')->loadCount('decks')))->resolve());
    }

    public function decks(Request $request): JsonResponse
    {
        return $this->paginated($request, VocabularyDeck::query()->withCount('vocabularies'), ['name', 'slug']);
    }

    public function createLevel(Request $request): JsonResponse
    {
        if ($response = $this->replayCreate($request, 'level', $request->only(['name', 'slug', 'sort_order']))) {
            return $response;
        }

        return $this->create($request, Level::class, 'level', $this->levelData($request), 201);
    }

    public function updateLevel(Request $request, Level $level): JsonResponse
    {
        return $this->update($request, $level, 'level', $this->levelData($request, $level));
    }

    public function deleteLevel(Request $request, int $level): JsonResponse
    {
        return $this->delete($request, Level::class, $level, 'level', fn (Level $model) => $model->courses()->exists());
    }

    public function createTopic(Request $request): JsonResponse
    {
        if ($response = $this->replayCreate($request, 'topic', $request->only(['name', 'slug']))) {
            return $response;
        }

        return $this->create($request, Topic::class, 'topic', $this->topicData($request), 201);
    }

    public function updateTopic(Request $request, Topic $topic): JsonResponse
    {
        return $this->update($request, $topic, 'topic', $this->topicData($request, $topic));
    }

    public function deleteTopic(Request $request, int $topic): JsonResponse
    {
        return $this->delete($request, Topic::class, $topic, 'topic', fn (Topic $model) => $model->courses()->exists() || $model->vocabularies()->exists());
    }

    public function createVocabulary(Request $request): JsonResponse
    {
        return $this->create($request, Vocabulary::class, 'vocabulary', $this->vocabularyData($request), 201);
    }

    public function updateVocabulary(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        return $this->update($request, $vocabulary, 'vocabulary', $this->vocabularyData($request, $vocabulary));
    }

    public function deleteVocabulary(Request $request, int $vocabulary): JsonResponse
    {
        $model = Vocabulary::findOrFail($vocabulary);
        $paths = $this->ownedVocabularyMediaPaths([$model->image_path, $model->audio_path]);
        $response = $this->delete($request, Vocabulary::class, $vocabulary, 'vocabulary');
        Storage::disk('public')->delete($paths);
        return $response;
    }

    public function uploadVocabularyMedia(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $this->authorizeCatalog($request);
        $data = $this->validateOrFail($request->all(), [
            'image' => ['nullable', 'image', 'max:5120'],
            'audio' => ['nullable', 'file', 'mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/ogg', 'max:10240'],
            'remove_image' => ['nullable', 'boolean'], 'remove_audio' => ['nullable', 'boolean'],
        ]);
        if (! $request->hasFile('image') && ! $request->hasFile('audio') && ! ($data['remove_image'] ?? false) && ! ($data['remove_audio'] ?? false)) {
            return ApiResponse::error('VALIDATION_ERROR', 'At least one media operation is required.', 422);
        }
        $new = [];
        if ($request->hasFile('image') && ! ($data['remove_image'] ?? false)) $new['image_path'] = $request->file('image')->store('vocabularies/images', 'public');
        if ($request->hasFile('audio') && ! ($data['remove_audio'] ?? false)) $new['audio_path'] = $request->file('audio')->store('vocabularies/audio', 'public');
        $old = ['image_path' => $vocabulary->image_path, 'audio_path' => $vocabulary->audio_path];
        $updates = $new;
        if ($data['remove_image'] ?? false) $updates['image_path'] = null;
        if ($data['remove_audio'] ?? false) $updates['audio_path'] = null;
        try {
            DB::transaction(fn () => $vocabulary->update($updates));
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($this->ownedVocabularyMediaPaths($new));
            throw $exception;
        }
        foreach ($updates as $field => $path) {
            if ($old[$field] && $old[$field] !== $path && $this->ownedVocabularyMediaPaths([$old[$field]])) Storage::disk('public')->delete($old[$field]);
        }

        return ApiResponse::success((new \App\Http\Resources\VocabularyResource($vocabulary->fresh()->load('topic')))->resolve());
    }

    public function createDeck(Request $request): JsonResponse
    {
        $this->authorizeCatalog($request);
        $raw = $request->only(['name', 'slug', 'description', 'is_public', 'vocabulary_ids']);
        $fingerprint = $this->fingerprint(['create', 'deck', array_diff_key($raw, ['vocabulary_ids' => true]), $raw['vocabulary_ids'] ?? []]);
        if ($audit = $this->replayAudit($request, 'deck.created', $fingerprint)) {
            return ApiResponse::success($audit->after_state, status: 201);
        }
        $data = $this->deckData($request);
        $vocabularyIds = $data['vocabulary_ids'] ?? [];
        unset($data['vocabulary_ids']);

        return $this->create($request, VocabularyDeck::class, 'deck', $data, 201, $vocabularyIds);
    }

    public function updateDeck(Request $request, VocabularyDeck $deck): JsonResponse
    {
        $data = $this->deckData($request, $deck);
        $vocabularyIds = $data['vocabulary_ids'] ?? null;
        unset($data['vocabulary_ids']);

        return $this->update($request, $deck, 'deck', $data, $vocabularyIds);
    }

    public function deleteDeck(Request $request, int $deck): JsonResponse
    {
        return $this->delete($request, VocabularyDeck::class, $deck, 'deck');
    }

    public function courses(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage-content'), 403);
        $validated = $this->validateOrFail($request->query(), [
            'search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:draft,published,archived'],
            'level_id' => ['nullable', 'integer', 'exists:levels,id'], 'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $page = Course::query()->with(['level', 'topics'])->withCount(['units', 'lessons'])
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['level_id'] ?? null, fn ($query, int $level) => $query->where('level_id', $level))
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
            'title', 'slug', 'description', 'status', 'language', 'estimated_duration', 'level_id', 'topic_ids',
        ])]);
        if ($course = $this->replay($request, 'course.created', $fingerprint)) {
            return ApiResponse::success($this->courseResource($course), status: 201);
        }
        $data = $this->validated($request);
        $topicIds = $data['topic_ids'] ?? [];
        unset($data['topic_ids']);
        try {
            $course = DB::transaction(function () use ($request, $data, $fingerprint, $topicIds): Course {
                $course = Course::create($data);
                $course->topics()->sync($topicIds);
                $this->audit($request, 'course.created', $course, $fingerprint, null);

                return $course;
            });
        } catch (QueryException $exception) {
            $course = $this->replay($request, 'course.created', $fingerprint);
            if (! $course) {
                throw $exception;
            }
        }

        return ApiResponse::success($this->courseResource($course), status: 201);
    }

    public function course(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->can('manage-content'), 403);

        return ApiResponse::success($this->courseResource($course));
    }

    public function updateCourse(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->can('manage-content'), 403);
        $data = $this->validated($request, $course);
        unset($data['status']);
        $topicIds = $data['topic_ids'] ?? null;
        unset($data['topic_ids']);
        $fingerprint = $this->fingerprint(['update', $course->id, $data, $topicIds]);
        if ($replayed = $this->replay($request, 'course.updated', $fingerprint)) {
            return ApiResponse::success($this->courseResource($replayed));
        }
        try {
            DB::transaction(function () use ($request, $course, $data, $fingerprint, $topicIds): void {
                $before = $course->toArray();
                $course->update($data);
                if ($topicIds !== null) $course->topics()->sync($topicIds);
                $this->audit($request, 'course.updated', $course, $fingerprint, $before);
            });
        } catch (QueryException $exception) {
            $course = $this->replay($request, 'course.updated', $fingerprint);
            if (! $course) {
                throw $exception;
            }
        }

        return ApiResponse::success($this->courseResource($course));
    }

    public function publishCourse(Request $request, Course $course): JsonResponse
    {
        return $this->setStatus($request, $course, 'published');
    }

    public function archiveCourse(Request $request, Course $course): JsonResponse
    {
        return $this->setStatus($request, $course, 'archived');
    }

    public function deleteCourse(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->can('manage-content'), 403);

        $dependencies = DB::transaction(function () use ($course): ?array {
            $locked = Course::query()->lockForUpdate()->findOrFail($course->id);
            $lessonIds = $locked->lessons()->select('id');
            $quizIds = DB::table('quizzes')->whereIn('lesson_id', $lessonIds)->select('id');
            $counts = [
                'enrollments' => DB::table('enrollments')->where('course_id', $locked->id)->count(),
                'progress' => DB::table('progress')->whereIn('lesson_id', $lessonIds)->count(),
                'attempts' => DB::table('attempts')->whereIn('quiz_id', $quizIds)->count(),
                'assignments' => DB::table('assignments')->whereIn('lesson_id', $lessonIds)->count(),
                'learning_sessions' => DB::table('learning_sessions')->whereIn('lesson_id', $lessonIds)->count(),
            ];

            if ($locked->status !== 'draft' || array_sum($counts) > 0) {
                return $counts;
            }

            $locked->delete();

            return null;
        });

        if ($dependencies !== null) {
            return ApiResponse::error('DEPENDENCY_EXISTS', 'The course has protected learning evidence.', 409, [
                'dependencies' => $dependencies,
            ]);
        }

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Course $course = null): array
    {
        return $this->validateOrFail($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('courses', 'slug')->ignore($course)],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'language' => ['nullable', 'string', 'size:2'],
            'estimated_duration' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'level_id' => ['nullable', 'integer', 'exists:levels,id'],
            'topic_ids' => ['sometimes', 'array', 'distinct'],
            'topic_ids.*' => ['integer', 'exists:topics,id'],
        ]);
    }

    private function courseResource(Course $course): array
    {
        return (new CourseResource($course->load(['level', 'topics'])->loadCount(['units', 'lessons'])))->resolve();
    }

    private function setStatus(Request $request, Course $course, string $status): JsonResponse
    {
        abort_unless($request->user()->can('manage-content'), 403);
        $action = "course.{$status}";
        $fingerprint = $this->fingerprint([$action, $course->id]);
        if ($replayed = $this->replay($request, $action, $fingerprint)) {
            return ApiResponse::success($this->courseResource($replayed));
        }
        try {
            $course = DB::transaction(function () use ($request, $course, $status, $action, $fingerprint): Course|false {
                $locked = Course::query()->lockForUpdate()->findOrFail($course->id);
                if (($status === 'published' && $locked->status !== 'draft') || ($status === 'archived' && $locked->status === 'archived')) {
                    return false;
                }
                $before = $locked->toArray();
                $locked->update(['status' => $status]);
                $this->audit($request, $action, $locked, $fingerprint, $before);

                return $locked;
            });
        } catch (QueryException $exception) {
            $course = $this->replay($request, $action, $fingerprint);
            if (! $course) {
                throw $exception;
            }
        }

        if ($course === false) {
            return ApiResponse::error('INVALID_STATE', 'Invalid course state transition.', 409);
        }

        return ApiResponse::success($this->courseResource($course));
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

    private function authorizeCatalog(Request $request): void
    {
        abort_unless($request->user()->can('manage-content'), 403);
    }

    private function paginated(Request $request, $query, array $searchColumns): JsonResponse
    {
        $this->authorizeCatalog($request);
        $search = trim($request->string('search')->toString());
        $query->when($search, function ($query) use ($searchColumns, $search): void {
            $query->where(function ($query) use ($searchColumns, $search): void {
                foreach ($searchColumns as $index => $column) {
                    $query->{$index === 0 ? 'where' : 'orWhere'}($column, 'like', "%{$search}%");
                }
            });
        });
        $page = $query->latest()->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return ApiResponse::success($page->items(), meta: [
            'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    private function create(Request $request, string $modelClass, string $type, array $data, int $status, array $vocabularyIds = []): JsonResponse
    {
        $this->authorizeCatalog($request);
        $action = "{$type}.created";
        $fingerprint = $this->fingerprint(['create', $type, $data, $vocabularyIds]);
        if ($audit = $this->replayAudit($request, $action, $fingerprint)) {
            return ApiResponse::success($audit->after_state, status: $status);
        }

        try {
            $model = DB::transaction(function () use ($request, $modelClass, $type, $data, $action, $fingerprint, $vocabularyIds): Model {
                $model = $modelClass::create($data);
                if ($model instanceof VocabularyDeck && $vocabularyIds) {
                    $model->vocabularies()->sync($this->orderedPivot($vocabularyIds));
                }
                $model->loadCount($model instanceof VocabularyDeck ? 'vocabularies' : []);
                $this->auditModel($request, $action, $type, $model, $fingerprint);

                return $model;
            });
        } catch (QueryException $exception) {
            $audit = $this->replayAudit($request, $action, $fingerprint);
            if (! $audit) {
                throw $exception;
            }

            return ApiResponse::success($audit->after_state, status: $status);
        }

        return ApiResponse::success($model->toArray(), status: $status);
    }

    private function update(Request $request, Model $model, string $type, array $data, ?array $vocabularyIds = null): JsonResponse
    {
        $this->authorizeCatalog($request);
        $action = "{$type}.updated";
        $fingerprint = $this->fingerprint(['update', $type, $model->getKey(), $data, $vocabularyIds]);
        if ($audit = $this->replayAudit($request, $action, $fingerprint)) {
            return ApiResponse::success($audit->after_state);
        }
        try {
            DB::transaction(function () use ($request, $model, $type, $data, $action, $fingerprint, $vocabularyIds): void {
                $before = $model->toArray();
                $model->update($data);
                if ($model instanceof VocabularyDeck && $vocabularyIds !== null) {
                    $model->vocabularies()->sync($this->orderedPivot($vocabularyIds));
                    $model->loadCount('vocabularies');
                }
                $this->auditModel($request, $action, $type, $model, $fingerprint, $before);
            });
        } catch (QueryException $exception) {
            $audit = $this->replayAudit($request, $action, $fingerprint);
            if (! $audit) {
                throw $exception;
            }

            return ApiResponse::success($audit->after_state);
        }

        return ApiResponse::success($model->toArray());
    }

    private function delete(Request $request, string $modelClass, int $id, string $type, ?callable $hasDependencies = null): JsonResponse
    {
        $this->authorizeCatalog($request);
        $action = "{$type}.deleted";
        $fingerprint = $this->fingerprint(['delete', $type, $id]);
        if ($this->replayAudit($request, $action, $fingerprint)) {
            return response()->json(null, 204);
        }
        $model = $modelClass::findOrFail($id);
        if ($hasDependencies && $hasDependencies($model)) {
            throw new ConflictHttpException('The record is still referenced by protected content.');
        }
        try {
            DB::transaction(function () use ($request, $model, $type, $action, $fingerprint): void {
                $before = $model->toArray();
                $id = $model->getKey();
                $model->delete();
                OperationsAudit::create([
                    'actor_id' => $request->user()->id, 'action' => $action, 'target_type' => $type,
                    'target_id' => (string) $id, 'request_id' => $request->header('X-Request-ID'),
                    'context' => ['fingerprint' => $fingerprint], 'before_state' => $before,
                    'after_state' => null, 'occurred_at' => now('UTC'),
                ]);
            });
        } catch (QueryException $exception) {
            if (! $this->replayAudit($request, $action, $fingerprint)) {
                throw $exception;
            }
        }

        return response()->json(null, 204);
    }

    private function replayAudit(Request $request, string $action, string $fingerprint): ?OperationsAudit
    {
        $requestId = $request->header('X-Request-ID');
        validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();
        $audit = OperationsAudit::query()->where('request_id', $requestId)->first();
        if ($audit && ($audit->action !== $action || data_get($audit->context, 'fingerprint') !== $fingerprint)) {
            throw new ConflictHttpException('X-Request-ID was already used for another operation.');
        }

        return $audit;
    }

    private function auditModel(Request $request, string $action, string $type, Model $model, string $fingerprint, ?array $before = null): void
    {
        OperationsAudit::create([
            'actor_id' => $request->user()->id, 'action' => $action, 'target_type' => $type,
            'target_id' => (string) $model->getKey(), 'request_id' => $request->header('X-Request-ID'),
            'context' => ['fingerprint' => $fingerprint], 'before_state' => $before,
            'after_state' => $model->toArray(), 'occurred_at' => now('UTC'),
        ]);
    }

    private function levelData(Request $request, ?Level $level = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('levels')->ignore($level)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    private function topicData(Request $request, ?Topic $topic = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('topics')->ignore($topic)],
        ]);
    }

    private function vocabularyData(Request $request, ?Vocabulary $vocabulary = null): array
    {
        return $request->validate([
            'word' => ['required', 'string', 'max:255'], 'meaning' => ['required', 'string', 'max:5000'],
            'definition' => ['nullable', 'string', 'max:5000'], 'example' => ['nullable', 'string', 'max:5000'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'pronunciation' => ['nullable', 'string', 'max:255'], 'part_of_speech' => ['nullable', 'string', 'max:100'],
            'difficulty_level' => ['nullable', 'string', 'max:100'], 'tags' => ['nullable', 'array'],
            'translation' => ['nullable', 'array'],
        ]);
    }

    private function deckData(Request $request, ?VocabularyDeck $deck = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('vocabulary_decks')->ignore($deck)],
            'description' => ['nullable', 'string', 'max:5000'], 'is_public' => ['sometimes', 'boolean'],
            'vocabulary_ids' => ['sometimes', 'array', 'max:1000'],
            'vocabulary_ids.*' => ['integer', 'distinct', 'exists:vocabularies,id'],
        ]);
    }

    private function orderedPivot(array $ids): array
    {
        return collect($ids)->mapWithKeys(fn (int $id, int $index) => [$id => ['sort_order' => $index]])->all();
    }

    private function ownedVocabularyMediaPaths(array $paths): array
    {
        return array_values(array_filter($paths, fn (?string $path) => $path !== null
            && (str_starts_with($path, 'vocabularies/images/') || str_starts_with($path, 'vocabularies/audio/'))));
    }

    private function validateOrFail(array $input, array $rules): array
    {
        $validator = validator($input, $rules);
        if ($validator->fails()) {
            throw new HttpResponseException(ApiResponse::error('VALIDATION_ERROR', 'The given data was invalid.', 422, [
                'errors' => $validator->errors(),
            ]));
        }

        return $validator->validated();
    }

    private function replayCreate(Request $request, string $type, array $data, array $relatedIds = []): ?JsonResponse
    {
        $this->authorizeCatalog($request);
        $audit = $this->replayAudit(
            $request,
            "{$type}.created",
            $this->fingerprint(['create', $type, $data, $relatedIds]),
        );

        return $audit ? ApiResponse::success($audit->after_state, status: 201) : null;
    }
}
