<?php

namespace App\Services;

use App\Models\AdminImportItem;
use App\Models\AdminImportRun;
use App\Models\Answer;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\LexiLingoImportCheckpoint;
use App\Models\OperationsAudit;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vocabulary;
use App\Services\Import\StagedImportClassifier;
use App\Support\CatalogFingerprint;
use App\Support\RecentGoogleAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AdminImportApproval
{
    private const ACTIONS = [
        'new' => ['add', 'exclude'],
        'exact_duplicate' => ['skip', 'exclude'],
        'upstream_update' => ['replace', 'keep_local', 'exclude'],
        'local_conflict' => ['keep_local', 'replace', 'exclude'],
        'invalid' => ['exclude'],
    ];

    private const ORDER = [
        'category' => 10,
        'course' => 20,
        'unit' => 30,
        'lesson' => 40,
        'topic' => 50,
        'vocabulary' => 60,
    ];

    public function __construct(
        private readonly StagedImportClassifier $classifier,
        private readonly RecentGoogleAdmin $recentGoogle,
    ) {}

    public function saveDraft(AdminImportRun $run, User $reviewer, array $selections): AdminImportRun
    {
        return DB::transaction(function () use ($run, $reviewer, $selections): AdminImportRun {
            $run = AdminImportRun::query()->lockForUpdate()->findOrFail($run->id);
            abort_unless(in_array($run->status, ['review_ready', 'approved', 'apply_failed'], true), 409, 'Run is not reviewable.');
            $items = $run->items()->lockForUpdate()->get()->keyBy('id');

            foreach ($selections as $selection) {
                $item = $items->get((int) $selection['id']);
                abort_unless($item, 422, 'Selected item does not belong to this run.');
                $action = (string) $selection['action'];
                abort_unless(in_array($action, self::ACTIONS[$item->classification] ?? [], true), 422, 'Invalid import action.');
                $item->selected_action = $action;
            }

            $this->cascadeExclusions($items);
            foreach ($items as $item) {
                if ($item->isDirty('selected_action')) {
                    $item->forceFill([
                        'reviewed_by' => $reviewer->id,
                        'reviewed_at' => now('UTC'),
                    ])->save();
                }
            }
            $run->update([
                'status' => 'review_ready',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now('UTC'),
                'approved_by' => null,
                'approved_at' => null,
                'error_code' => null,
                'error_message' => null,
            ]);

            return $run->fresh();
        });
    }

    public function approve(AdminImportRun $run, User $approver): AdminImportRun
    {
        return DB::transaction(function () use ($run, $approver): AdminImportRun {
            $run = AdminImportRun::query()->lockForUpdate()->findOrFail($run->id);
            abort_unless($run->status === 'review_ready', 409, 'Run is not ready for approval.');
            $items = $run->items()->lockForUpdate()->get();
            abort_if($items->isEmpty(), 422, 'Run has no staged items.');
            abort_if($items->contains(fn (AdminImportItem $item): bool => $item->classification === 'invalid'
                && $item->selected_action !== 'exclude'), 422, 'Invalid items must be excluded.');
            $this->requireResolvedDependencies($items);

            $run->update([
                'status' => 'approved',
                'reviewed_by' => $run->reviewed_by ?? $approver->id,
                'reviewed_at' => $run->reviewed_at ?? now('UTC'),
                'approved_by' => $approver->id,
                'approved_at' => now('UTC'),
            ]);

            return $run->fresh();
        });
    }

    public function apply(AdminImportRun $run, Request $request): AdminImportRun
    {
        abort_unless(config('features.lexilingo_import_apply'), 503, 'LexiLingo import apply is disabled.');
        Gate::forUser($request->user())->authorize('apply-imports');

        try {
            $result = DB::transaction(function () use ($run, $request): array {
                $run = AdminImportRun::query()->lockForUpdate()->findOrFail($run->id);
                abort_unless($run->status === 'approved', 409, 'Run is not approved.');
                $items = $run->items()->lockForUpdate()->get()
                    ->sortBy(fn (AdminImportItem $item): int => self::ORDER[$item->entity] ?? 999)
                    ->values();
                $this->authorizeFinalActions($items, $request);

                foreach ($items as $item) {
                    if ($item->selected_action === 'exclude') {
                        continue;
                    }
                    $existing = $this->sourceModel($item, true);
                    if ($item->classification === 'new' && $existing) {
                        $this->reclassify($item, $existing);
                        $run->update(['status' => 'review_ready', 'approved_by' => null, 'approved_at' => null]);

                        return ['stale' => true];
                    }
                    if ($item->classification !== 'new'
                        && (! $existing
                            || (int) $existing->catalog_revision !== (int) $item->base_revision
                            || $existing->source_fingerprint !== $item->base_fingerprint
                            || $existing->local_override_at?->toISOString() !== $item->base_override_at?->toISOString())) {
                        $existing
                            ? $this->reclassify($item, $existing)
                            : $item->update([
                                'classification' => 'new',
                                'selected_action' => 'add',
                                'target_id' => null,
                                'base_revision' => null,
                                'base_fingerprint' => null,
                                'base_snapshot' => null,
                                'base_override_at' => null,
                            ]);
                        $run->update(['status' => 'review_ready', 'approved_by' => null, 'approved_at' => null]);

                        return ['stale' => true];
                    }
                }

                $this->requireResolvedDependencies($items, true);
                $run->update(['status' => 'applying']);
                $applied = [];
                foreach ($items as $item) {
                    if (in_array($item->selected_action, ['add', 'replace'], true)) {
                        $model = $this->applyItem($item);
                        $item->update(['target_id' => $model->getKey(), 'applied_at' => now('UTC')]);
                        $applied[] = ['id' => $item->id, 'action' => $item->selected_action];
                    }
                }

                $checkpoint = LexiLingoImportCheckpoint::query()->lockForUpdate()
                    ->firstOrNew(['entity' => $run->entity]);
                $checkpoint->cursor = $run->reset
                    ? (int) $run->result_cursor
                    : max((int) $checkpoint->cursor, (int) $run->result_cursor);
                $checkpoint->last_synced_at = now('UTC');
                $checkpoint->save();
                $run->update([
                    'status' => 'completed',
                    'applied_at' => now('UTC'),
                    'error_code' => null,
                    'error_message' => null,
                ]);
                OperationsAudit::create([
                    'actor_id' => $request->user()->id,
                    'action' => 'content_import.applied',
                    'target_type' => 'admin_import_run',
                    'target_id' => (string) $run->id,
                    'request_id' => $request->header('X-Request-ID'),
                    'context' => [
                        'fingerprint' => hash('sha256', json_encode([
                            'run' => $run->id,
                            'action' => 'content_import.applied',
                            'payload' => [],
                        ], JSON_THROW_ON_ERROR)),
                        'applied' => $applied,
                        'selected' => $items->where('selected_action', '!=', 'exclude')
                            ->map(fn (AdminImportItem $item): array => [
                                'id' => $item->id,
                                'action' => $item->selected_action,
                            ])->values()->all(),
                        'excluded' => $items->where('selected_action', 'exclude')->pluck('id')->all(),
                    ],
                    'before_state' => [
                        'status' => 'approved',
                        'fingerprints' => $items->pluck('base_fingerprint', 'id')->all(),
                    ],
                    'after_state' => [
                        'status' => 'completed',
                        'cursor' => $checkpoint->cursor,
                        'fingerprints' => $items->pluck('normalized_fingerprint', 'id')->all(),
                    ],
                    'occurred_at' => now('UTC'),
                ]);

                return ['stale' => false, 'run' => $run->fresh()];
            });
        } catch (Throwable $error) {
            if (! $error instanceof HttpExceptionInterface && ! $error instanceof AuthorizationException) {
                AdminImportRun::query()->whereKey($run->id)->where('status', 'approved')->update([
                    'status' => 'apply_failed',
                    'error_code' => 'apply_failed',
                    'error_message' => 'Catalog apply failed. Review the run and retry.',
                ]);
            }

            throw $error;
        }

        if ($result['stale']) {
            throw new ConflictHttpException('Catalog changed after review. Refresh the import draft.');
        }

        return $result['run'];
    }

    private function authorizeFinalActions(Collection $items, Request $request): void
    {
        $upstream = $items->contains(fn (AdminImportItem $item): bool => $item->classification === 'upstream_update'
            && $item->selected_action === 'replace');
        $conflict = $items->contains(fn (AdminImportItem $item): bool => $item->classification === 'local_conflict'
            && $item->selected_action === 'replace');
        if ($upstream || $conflict) {
            $this->recentGoogle->require($request);
        }
        if ($upstream) {
            Gate::forUser($request->user())->authorize('replace-import-upstream');
        }
        if ($conflict) {
            Gate::forUser($request->user())->authorize('replace-import-conflict');
        }
    }

    private function cascadeExclusions(Collection $items): void
    {
        do {
            $changed = false;
            foreach ($items as $item) {
                if ($item->selected_action === 'exclude') {
                    continue;
                }
                $parent = $item->parent_item_id ? $items->get($item->parent_item_id) : null;
                $parentExcluded = $parent?->selected_action === 'exclude'
                    && (! $parent->external_id || ! $this->dependencyExists([
                        'entity' => $parent->entity,
                        'external_id' => $parent->external_id,
                    ]));
                $dependencyExcluded = collect($item->dependencies ?? [])->contains(function (array $dependency) use ($items): bool {
                    $staged = $items->first(fn (AdminImportItem $candidate): bool => $candidate->entity === $dependency['entity']
                        && $candidate->external_id === (string) $dependency['external_id']);

                    return $staged?->selected_action === 'exclude' && ! $this->dependencyExists($dependency);
                });
                if ($parentExcluded || $dependencyExcluded) {
                    $item->selected_action = 'exclude';
                    $changed = true;
                }
            }
        } while ($changed);
    }

    private function requireResolvedDependencies(Collection $items, bool $lock = false): void
    {
        foreach ($items as $item) {
            if (in_array($item->selected_action, ['exclude', 'skip', 'keep_local'], true)) {
                continue;
            }
            foreach ($item->dependencies ?? [] as $dependency) {
                $staged = $items->first(fn (AdminImportItem $candidate): bool => $candidate->entity === $dependency['entity']
                    && $candidate->external_id === (string) $dependency['external_id']);
                $stagedWillApply = $staged && in_array($staged->selected_action, ['add', 'replace'], true);
                abort_if(! $stagedWillApply
                    && ! $this->dependencyExists($dependency, $lock), 422, 'Selected item has an unresolved dependency.');
            }
        }
    }

    private function dependencyExists(array $dependency, bool $lock = false): bool
    {
        $class = $this->modelClass((string) $dependency['entity']);
        $query = $class::query()->where('source_system', 'lexilingo')
            ->where('external_id', (string) $dependency['external_id']);

        return $lock ? $query->lockForUpdate()->value('id') !== null : $query->exists();
    }

    private function reclassify(AdminImportItem $item, Model $existing): void
    {
        $item->update($this->classifier->classify(
            $item->entity,
            $item->candidate_payload,
            $existing->only([
                'id', 'catalog_revision', 'source_fingerprint',
                'source_snapshot', 'local_override_at',
            ]),
        ));
    }

    private function applyItem(AdminImportItem $item): Model
    {
        $model = $this->sourceModel($item, true) ?? new ($this->modelClass($item->entity));
        $attributes = $this->resolvedAttributes($item);
        $model->fill($attributes)->forceFill([
            'source_system' => 'lexilingo',
            'external_id' => $item->external_id,
            'source_fingerprint' => CatalogFingerprint::make($item->entity, $item->candidate_payload),
            'source_snapshot' => $item->candidate_payload,
            'local_override_at' => null,
            'last_synced_at' => now('UTC'),
            'catalog_revision' => (int) $model->catalog_revision + 1,
        ])->save();

        if ($item->entity === 'lesson') {
            $this->applyQuizTree($model, $item->candidate_payload['quiz_tree'] ?? null);
        }

        return $model;
    }

    private function resolvedAttributes(AdminImportItem $item): array
    {
        $attributes = $item->candidate_payload;
        if ($item->entity === 'course') {
            $attributes['category_id'] = $this->dependencyId('category', $attributes['category_external_id'] ?? null);
            $attributes['level_id'] = Level::query()->where('slug', $attributes['level'] ?? null)->value('id');
        } elseif ($item->entity === 'unit') {
            $attributes['course_id'] = $this->dependencyId('course', $attributes['course_external_id'] ?? null);
        } elseif ($item->entity === 'lesson') {
            $attributes['unit_id'] = $this->dependencyId('unit', $attributes['unit_external_id'] ?? null);
            $attributes['course_id'] = Unit::query()->whereKey($attributes['unit_id'] ?? null)->value('course_id');
        } elseif ($item->entity === 'vocabulary') {
            $attributes['topic_id'] = $this->dependencyId('topic', $attributes['topic_external_ids'][0] ?? null);
        }

        return $attributes;
    }

    private function dependencyId(string $entity, ?string $externalId): ?int
    {
        if (! $externalId) {
            return null;
        }
        $class = $this->modelClass($entity);

        return $class::query()->where('source_system', 'lexilingo')
            ->where('external_id', $externalId)->value('id');
    }

    private function sourceModel(AdminImportItem $item, bool $lock): ?Model
    {
        $query = ($this->modelClass($item->entity))::query()
            ->where('source_system', $item->source_system)
            ->where('external_id', $item->external_id);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    private function modelClass(string $entity): string
    {
        return match ($entity) {
            'category' => CourseCategory::class,
            'course' => Course::class,
            'unit' => Unit::class,
            'lesson' => Lesson::class,
            'topic' => Topic::class,
            'vocabulary' => Vocabulary::class,
            default => throw new \RuntimeException("Unsupported import entity [{$entity}]."),
        };
    }

    private function applyQuizTree(Lesson $lesson, mixed $quizData): void
    {
        $quizExternalIds = [];
        if (is_array($quizData) && isset($quizData['id'])) {
            $quizExternalIds[] = (string) $quizData['id'];
            $quiz = Quiz::updateOrCreate(['external_id' => (string) $quizData['id']], [
                'lesson_id' => $lesson->id,
                'title' => $quizData['title'] ?? $lesson->title,
                'passing_score' => $quizData['passing_score'] ?? $lesson->pass_threshold ?? 60,
                'status' => $quizData['status'] ?? 'published',
            ]);
            $questionIds = [];
            foreach ($quizData['questions'] ?? [] as $questionData) {
                if (! isset($questionData['id'], $questionData['content'])) {
                    continue;
                }
                $questionIds[] = (string) $questionData['id'];
                $question = Question::updateOrCreate(['external_id' => (string) $questionData['id']], [
                    'quiz_id' => $quiz->id,
                    'content' => $questionData['content'],
                    'explanation' => $questionData['explanation'] ?? null,
                    'sort_order' => $questionData['sort_order'] ?? 0,
                ]);
                $answerIds = [];
                foreach ($questionData['answers'] ?? [] as $answerData) {
                    if (! isset($answerData['id'], $answerData['content'])) {
                        continue;
                    }
                    $answerIds[] = (string) $answerData['id'];
                    Answer::updateOrCreate(['external_id' => (string) $answerData['id']], [
                        'question_id' => $question->id,
                        'content' => $answerData['content'],
                        'is_correct' => (bool) ($answerData['is_correct'] ?? false),
                    ]);
                }
                Answer::query()->where('question_id', $question->id)->whereNotNull('external_id')
                    ->when($answerIds !== [], fn ($query) => $query->whereNotIn('external_id', $answerIds))->delete();
            }
            Question::query()->where('quiz_id', $quiz->id)->whereNotNull('external_id')
                ->when($questionIds !== [], fn ($query) => $query->whereNotIn('external_id', $questionIds))->delete();
        }
        Quiz::query()->where('lesson_id', $lesson->id)->whereNotNull('external_id')
            ->when($quizExternalIds !== [], fn ($query) => $query->whereNotIn('external_id', $quizExternalIds))->delete();
    }
}
