# Self-owned Learning Path Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a self-owned Course → Unit → Lesson learner path, richer Progress/FSRS insight, and a staged LexiLingo import approval workflow with safe Google step-up authentication.

**Architecture:** Deliver four vertical slices. Laravel owns catalog, eligibility, FSRS calculation, import staging, authorization, and audit; Next.js renders typed server state and never duplicates business rules. Existing models, `FsrsScheduler`, `LearningSessionService`, `AdminImportRunner`, Google handoff, request-ID conventions, and UI tokens are extended in place.

**Tech Stack:** Laravel 13/PHP 8.3, Eloquent/MySQL, Redis queue/cache, PHPUnit, Next.js 16/React 19/TypeScript, Vitest, Tailwind CSS, OpenAPI/Redocly.

**Design:** `docs/superpowers/specs/2026-07-29-self-owned-learning-path-design.md`

---

## File Map

### Slice 1: Learning Path

- Create `database/migrations/2026_07_29_020000_add_catalog_ownership_and_unit_lifecycle.php`: source-scoped identity for Category/Course/Unit/Lesson/Topic/Vocabulary, revision/override metadata, unit lifecycle.
- Create `database/migrations/2026_07_29_021000_create_lesson_prerequisites.php`: same-course prerequisite graph.
- Create `app/Http/Controllers/Api/V1/Admin/UnitController.php`: Unit CRUD/lifecycle/reorder.
- Create `app/Http/Requests/Api/V1/Admin/WriteUnitRequest.php`: Unit write validation.
- Create `app/Services/CourseLearningPath.php`: eligibility, prerequisite explanations, next action.
- Create `app/Support/CatalogFingerprint.php`: canonical business-field fingerprinting shared by sync and staging.
- Create `tests/Feature/Api/V1/AdminUnitApiTest.php` and `tests/Feature/Api/V1/CourseLearningPathApiTest.php`.
- Modify Category/Course/Unit/Lesson/Topic/Vocabulary models, all catalog mutation controllers, import services, `LearningSessionService`, learner catalog/session controllers, and `routes/spa.php`.
- Modify `frontend/src/{lib/api.ts,types/api.ts,app/courses/[id]/page.tsx}` and its focused test.
- Modify `admin-frontend/src/{lib/api.ts,app/courses/page.tsx}` and focused API/page tests.

### Slice 2: Progress and FSRS

- Create `app/Services/LearnerProgressSummary.php`: one aggregate query boundary.
- Create `app/Http/Requests/Api/V1/PreviewFsrsRequest.php`: owned-card/base-revision validation.
- Create `tests/Feature/Api/V1/FsrsInsightApiTest.php`.
- Create `frontend/src/features/progress/progress-page.test.tsx` and `frontend/src/app/review/review-page.test.tsx`.
- Modify `app/Http/Controllers/Api/V1/{ProgressController,FsrsController}.php`, `app/Domain/Fsrs/FsrsScheduler.php`, `routes/spa.php`.
- Modify `frontend/src/{lib/api.ts,types/api.ts,features/progress/progress-page.tsx,app/review/page.tsx}`.

### Slice 3: Safe Import

- Create `database/migrations/2026_07_29_030000_create_admin_import_staging.php`.
- Create `app/Models/AdminImportItem.php`.
- Create `app/Services/Import/StagedImportClassifier.php` and `app/Services/AdminImportApproval.php`.
- Create `app/Support/RecentGoogleAdmin.php`; retain `RecentPassword` only for normal learner password confirmation.
- Create `tests/Feature/Api/V1/AdminImportApprovalApiTest.php` and `tests/Unit/StagedImportClassifierTest.php`.
- Modify `app/Models/AdminImportRun.php`, `app/Jobs/RunAdminImport.php`, `app/Services/AdminImportRunner.php`, importers, `AdminGoogleAuthController`, `ContentOperationsController`, `routes/spa.php`, `config/features.php`.
- Modify `app/Console/Commands/{ImportLexiLingoDataset,SyncLexiLingoVocabulary}.php` so CLI cannot bypass staging.
- Modify `admin-frontend/src/{lib/api.ts,app/import/page.tsx}` and tests.

### Slice 4: Release Gate

- Modify privileged admin controllers to use `RecentGoogleAdmin`.
- Modify `docs/openapi/laravel-v1.yaml`, `docs/PRODUCTION_ENV.md`, `docs/security/production-runbook.md`, `docs/CURRENT_STATUS.md`; create the dated release-evidence record.
- Create `tests/Feature/ApiContractParityTest.php`.

---

## Chunk 1: Self-owned Course Learning Path

### Task 1: Add catalog ownership, Unit lifecycle, and prerequisites

**Files:**
- Create: `database/migrations/2026_07_29_020000_add_catalog_ownership_and_unit_lifecycle.php`
- Create: `database/migrations/2026_07_29_021000_create_lesson_prerequisites.php`
- Create: `app/Support/CatalogFingerprint.php`
- Modify: `app/Models/{CourseCategory,Course,Unit,Lesson,Topic,Vocabulary}.php`
- Test: `tests/Feature/AdminContentSchemaTest.php`

- [ ] **Step 1: Write failing schema tests**

Assert Category/Course/Unit/Lesson/Topic/Vocabulary have non-null `source_system` defaulting to `local`, nullable `external_id`, `source_fingerprint`, `source_snapshot`, `local_override_at`, `last_synced_at`, and unsigned `catalog_revision` defaulting to zero. Assert Unit has `status`; assert composite source identity and prerequisite constraints.

- [ ] **Step 2: Run the focused test and confirm RED**

Run: `php artisan test tests/Feature/AdminContentSchemaTest.php`

Expected: FAIL because ownership columns and `lesson_prerequisites` do not exist.

- [ ] **Step 3: Add forward-only migrations**

Drop each legacy single-column `external_id` unique index before adding a unique composite `(source_system, external_id)`. Backfill external rows to `lexilingo`, other rows to `local`, and legacy units to `published` only when their course and an owned lesson are published. Create the prerequisite pivot. `down()` must reject cross-source duplicate external IDs before restoring a global unique index; application rollback should normally retain these additive columns after multi-source writes begin. Exercise `up()` → `down()` → `up()` inside `AdminContentSchemaTest`'s ephemeral database—never run a bare rollback against the developer database.

- [ ] **Step 4: Add model casts and relationships**

Add JSON/datetime/integer casts and prerequisite relations. `CatalogFingerprint` accepts every business field each importer writes: Category (`name`, `slug`, `description`, `icon`, `color`); Course (`category_external_id`, `title`, `slug`, `description`, `level`, `language`, `thumbnail_url`, `estimated_duration_minutes`, `xp_reward`, `status`); Unit (`course_external_id`, `title`, `description`, `icon`, `background`, `sort_order`, `status`); Lesson aggregate (`unit_external_id`, `title`, `description`, `content`, `type`, `estimated_duration_minutes`, `xp_reward`, `pass_threshold`, `sort_order`, `status`, normalized quiz tree); Topic (`name`, `description`); and Vocabulary (`word`, `definition`, `translation`, `pronunciation`, `part_of_speech`, `difficulty`, `example`, `tags`, `audio_url`, `topic_external_ids`). Confirm these names against importer assignments while implementing; the test enumerates every assigned business column so adding a future write without adding it to the fingerprint fails. Missing and null both normalize to null; strings are Unicode-trimmed with line endings normalized to `\n`; associative keys and set-like tags/topic IDs are sorted; ordered quiz/question/answer lists retain order. Encode canonical JSON and SHA-256 it. Quiz/Question/Answer are one Lesson aggregate and do not get independent provenance.

- [ ] **Step 5: Verify migration lifecycle**

Run:

```bash
php artisan test tests/Feature/AdminContentSchemaTest.php
./vendor/bin/pint --test
```

Expected: PASS; rollback and re-apply preserve the pre-existing schema.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_29_020000_add_catalog_ownership_and_unit_lifecycle.php database/migrations/2026_07_29_021000_create_lesson_prerequisites.php app/Support/CatalogFingerprint.php app/Models/CourseCategory.php app/Models/Course.php app/Models/Unit.php app/Models/Lesson.php app/Models/Topic.php app/Models/Vocabulary.php tests/Feature/AdminContentSchemaTest.php
git commit -m "feat: add self-owned catalog metadata"
```

### Task 2: Make current import writes ownership-safe

**Files:**
- Modify: `app/Services/Import/CourseImporter.php`
- Modify: `app/Services/Import/{CategoryImporter,LessonContentImporter,TagTopicImporter}.php`
- Modify: `app/Services/LexiLingoVocabularySync.php`
- Modify: `app/Jobs/RunAdminImport.php`, `app/Services/AdminImportRunner.php`
- Modify: `app/Http/Controllers/Api/V1/Admin/{CatalogController,CourseAdminController,LessonAdminController,LessonController,TopicController,CourseCategoryController,VocabularyAdminController,ContentOperationsController}.php`
- Modify: `app/Console/Commands/{ImportLexiLingoDataset,SyncLexiLingoVocabulary}.php`, `config/features.php`
- Test: `tests/Feature/{LexiLingoImportTest,LexiLingoLessonSyncTest,LexiLingoVocabularySyncTest}.php`

- [ ] **Step 1: Add failing local-override tests**

Prove re-import updates a changed, non-overridden row, skips a row whose `local_override_at` is set, and is a no-op when the fingerprint is unchanged. Prove accepted changed writes update fingerprint/snapshot and increment revision. Prove source identity is scoped to `lexilingo`.

- [ ] **Step 2: Confirm RED**

Run: `php artisan test --filter='LexiLingoImport|LexiLingoLessonSync|LexiLingoVocabularySync'`

- [ ] **Step 3: Patch the existing shared write points**

Before every upstream update, lock the local row. Create missing rows with provenance; skip overridden rows; do nothing when the canonical fingerprint is unchanged; update accepted changed fingerprint/snapshot/revision in the same transaction. Patch every current admin write path for Category/Course/Unit/Lesson/Topic/Vocabulary—including canonical `/api/v1/admin/catalog/lessons` and compatibility lesson routes—to set `local_override_at` and increment revision. Make `ContentOperationsController::start/reset`, both CLI commands, `RunAdminImport::handle`, and `AdminImportRunner` reject while `features.lexilingo_import` is disabled so queued work cannot bypass the gate. Keep direct apply disabled until Chunk 3; no hidden direct-apply mode.

- [ ] **Step 4: Run focused tests and Pint**

Run: `php artisan test --filter='LexiLingoImport|LexiLingoLessonSync|LexiLingoVocabularySync|AdminCatalog|AdminLesson|AdminCrudApi|AdminTaxonomy|AdminContentOperations' && ./vendor/bin/pint --test`

Expected: all focused tests and Pint pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Import/CategoryImporter.php app/Services/Import/CourseImporter.php app/Services/Import/LessonContentImporter.php app/Services/Import/TagTopicImporter.php app/Services/LexiLingoVocabularySync.php app/Jobs/RunAdminImport.php app/Services/AdminImportRunner.php app/Http/Controllers/Api/V1/Admin/CatalogController.php app/Http/Controllers/Api/V1/Admin/CourseAdminController.php app/Http/Controllers/Api/V1/Admin/LessonAdminController.php app/Http/Controllers/Api/V1/Admin/LessonController.php app/Http/Controllers/Api/V1/Admin/TopicController.php app/Http/Controllers/Api/V1/Admin/CourseCategoryController.php app/Http/Controllers/Api/V1/Admin/VocabularyAdminController.php app/Http/Controllers/Api/V1/Admin/ContentOperationsController.php app/Console/Commands/ImportLexiLingoDataset.php app/Console/Commands/SyncLexiLingoVocabulary.php config/features.php tests/Feature/LexiLingoImportTest.php tests/Feature/LexiLingoLessonSyncTest.php tests/Feature/LexiLingoVocabularySyncTest.php tests/Feature/Api/V1/AdminCatalogApiTest.php tests/Feature/Api/V1/AdminLessonApiTest.php tests/Feature/Api/V1/Admin/AdminCrudApiTest.php tests/Feature/Api/V1/AdminTaxonomyTest.php tests/Feature/Api/V1/AdminContentOperationsApiTest.php
git commit -m "fix: preserve local catalog overrides during sync"
```

### Task 3: Add canonical Unit administration

**Files:**
- Create: `app/Http/Controllers/Api/V1/Admin/UnitController.php`
- Create: `app/Http/Requests/Api/V1/Admin/WriteUnitRequest.php`
- Create: `tests/Feature/Api/V1/AdminUnitApiTest.php`
- Modify: `routes/spa.php`
- Modify: `app/Http/Controllers/Api/V1/Admin/LessonController.php`
- Modify: `app/Http/Requests/Api/V1/Admin/WriteLessonRequest.php`
- Modify: `admin-frontend/src/lib/api.ts`, `admin-frontend/src/app/courses/page.tsx`
- Create: `admin-frontend/src/app/courses/course-outline.test.tsx`
- Test: `admin-frontend/src/lib/api.test.ts`

- [ ] **Step 1: Write failing API tests**

Backend: cover list by course, create/update, `PUT /units/reorder` with `{course_id, unit_ids}`, lifecycle actions, delete-empty-draft, identical request replay, and same request ID with a different payload returning conflict. Extend Lesson write payload with `unit_id` and `prerequisite_ids`; cover same-course membership, full prerequisite replacement, cross-course/self/cycle rejection, role boundaries, revision and local override. Frontend: cover Unit rendering, create/edit, collision-safe reorder request, lesson assignment, provenance/status, loading/empty/error, and failed mutation recovery without optimistic data loss.

- [ ] **Step 2: Confirm RED**

Run:

```bash
php artisan test tests/Feature/Api/V1/AdminUnitApiTest.php
cd admin-frontend && ./node_modules/.bin/vitest run src/app/courses/course-outline.test.tsx src/lib/api.test.ts
```

- [ ] **Step 3: Implement minimum routes and controller**

Register concrete Unit routes beside the canonical `/api/v1/admin/catalog/lessons` routes. Require `manage-content` explicitly on the route group/controller before all list, lifecycle, reorder, and delete actions; Form Requests enforce it again for writes. Reorder with course row lock and a two-phase temporary offset before final positions so the existing `(course_id, sort_order)` unique key never collides. Extend canonical `LessonController`/`WriteLessonRequest` and sync prerequisites in the same locked Lesson transaction. Publish only under a published course with an eligible lesson; delete only an empty draft.

- [ ] **Step 4: Add typed admin calls and course-outline editor**

Extend the existing course page modal/detail rather than adding a new top-level navigation route. Show Unit status, reorder controls, lesson membership, provenance badge, and explicit API error states.

- [ ] **Step 5: Run focused frontend/backend gates**

```bash
php artisan test tests/Feature/Api/V1/AdminUnitApiTest.php
cd admin-frontend && ./node_modules/.bin/vitest run src/app/courses/course-outline.test.tsx src/lib/api.test.ts
./node_modules/.bin/eslint .
./node_modules/.bin/next build
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/Admin/UnitController.php app/Http/Requests/Api/V1/Admin/WriteUnitRequest.php app/Http/Controllers/Api/V1/Admin/LessonController.php app/Http/Requests/Api/V1/Admin/WriteLessonRequest.php routes/spa.php tests/Feature/Api/V1/AdminUnitApiTest.php admin-frontend/src/lib/api.ts admin-frontend/src/lib/api.test.ts admin-frontend/src/app/courses/page.tsx admin-frontend/src/app/courses/course-outline.test.tsx
git commit -m "feat: add unit authoring workflow"
```

### Task 4: Add server-owned course eligibility and session selection

**Files:**
- Create: `app/Services/CourseLearningPath.php`
- Create: `tests/Feature/Api/V1/CourseLearningPathApiTest.php`
- Create: `tests/Feature/Api/V1/CourseLearningPathMysqlConcurrencyTest.php`
- Modify: `app/Http/Controllers/Api/V1/CatalogController.php`
- Modify: `app/Http/Controllers/Api/V1/LearningSessionController.php`
- Modify: `app/Services/LearningSessionService.php`, `routes/spa.php`

- [ ] **Step 1: Write failing learning-path tests**

Cover ordered published units/lessons, completion only when Progress has `completed_at`, prerequisite explanation, `next_action`, and deterministic latest active-session resume. Cover optional `lesson_id`, ineligible rejection, ownership, identical replay, same request ID/different payload conflict, and sequential different request IDs producing one active enrollment/lesson session in the default SQLite suite. When returning an existing active session for a fresh request ID, persist that ID's payload/result binding so later reuse cannot change payload. Put the two-connection race in `CourseLearningPathMysqlConcurrencyTest`, mark it `mysql-concurrency`, and prove concurrent different request IDs against the same enrollment still produce one active session.

- [ ] **Step 2: Confirm RED**

Run: `php artisan test tests/Feature/Api/V1/CourseLearningPathApiTest.php tests/Feature/Api/V1/LearningSessionApiTest.php`

- [ ] **Step 3: Implement one eligibility service**

Return the learner-specific path from `GET /api/v1/catalog/courses/{course}/path` under `auth`. Make `plan()` and `start()` call the same eligibility methods. Add `lesson_id`; lock the enrollment before selecting/creating, then return the latest matching active session if present. This enrollment lock serializes different request IDs without a speculative new uniqueness column. Never calculate unlock order in React.

- [ ] **Step 4: Run tests and commit**

```bash
php artisan test tests/Feature/Api/V1/CourseLearningPathApiTest.php tests/Feature/Api/V1/LearningSessionApiTest.php
git add app/Services/CourseLearningPath.php app/Services/LearningSessionService.php app/Http/Controllers/Api/V1/CatalogController.php app/Http/Controllers/Api/V1/LearningSessionController.php routes/spa.php tests/Feature/Api/V1/CourseLearningPathApiTest.php tests/Feature/Api/V1/CourseLearningPathMysqlConcurrencyTest.php tests/Feature/Api/V1/LearningSessionApiTest.php
git commit -m "feat: add learner course path eligibility"
```

### Task 5: Build the Editorial Learning Course page

**Files:**
- Modify: `frontend/src/lib/api.ts`, `frontend/src/types/api.ts`
- Modify: `frontend/src/app/courses/[id]/page.tsx`
- Create: `frontend/src/app/courses/course-path.test.tsx`
- Modify: `frontend/src/app/session/[id]/summary/page.tsx`

- [ ] **Step 1: Write failing component tests**

Cover hero/progress, Unit grouping, locked explanation, current lesson CTA, resume CTA, enrollment, loading/empty/error/retry, and no client-side index eligibility.

- [ ] **Step 2: Confirm RED**

Run: `cd frontend && ./node_modules/.bin/vitest run src/app/courses/course-path.test.tsx`

- [ ] **Step 3: Add typed API and rebuild in place**

Replace the current separate detail/lesson/progress requests with the path response after enrollment. Use existing tokens/components, layered cards, restrained gradients, CSS timeline, responsive layout, and visible focus. Preserve current route and quiz links.

- [ ] **Step 4: Verify and commit**

```bash
cd frontend
./node_modules/.bin/vitest run src/app/courses/course-path.test.tsx
./node_modules/.bin/eslint .
./node_modules/.bin/next build
git add src/lib/api.ts src/types/api.ts 'src/app/courses/[id]/page.tsx' src/app/courses/course-path.test.tsx 'src/app/session/[id]/summary/page.tsx'
git commit -m "feat: build editorial course learning path"
```

## Chunk 2: Progress and FSRS Insight

### Task 6: Add server-calculated Progress and FSRS insight

**Files:**
- Create: `app/Services/LearnerProgressSummary.php`
- Create: `app/Http/Requests/Api/V1/PreviewFsrsRequest.php`
- Create: `tests/Feature/Api/V1/FsrsInsightApiTest.php`
- Modify: `app/Http/Controllers/Api/V1/{ProgressController,FsrsController}.php`
- Modify: `app/Domain/Fsrs/FsrsScheduler.php`, `routes/spa.php`

- [ ] **Step 1: Write failing deterministic tests**

Freeze UTC time. Assert reviewed-card population, scheduler retrievability reuse, null empty retention, averages, FSRS state distribution, seven UTC due buckets, course/unit/lesson completion, recent sessions, and quiz performance. Preview accepts `{card_id, base_revision}`, rejects another learner/stale revision, does not mutate, and returns each rating as `{rating, due_at, interval_seconds}` plus `generated_at` and `base_revision`; sub-day intervals must remain nonzero.

- [ ] **Step 2: Confirm RED**

Run: `php artisan test tests/Feature/Api/V1/FsrsInsightApiTest.php tests/Unit/FsrsSchedulerTest.php`

- [ ] **Step 3: Implement summary and preview**

Expose insight through the existing progress dashboard and authenticated `POST /api/v1/fsrs/preview`. `PreviewFsrsRequest` scopes `card_id` to the current user and validates base revision. Add a public, non-mutating scheduler preview that clones card state and calls existing scheduling logic.

- [ ] **Step 4: Verify and commit**

```bash
php artisan test tests/Feature/Api/V1/FsrsInsightApiTest.php tests/Unit/FsrsSchedulerTest.php
./vendor/bin/pint --test
git add app/Services/LearnerProgressSummary.php app/Http/Requests/Api/V1/PreviewFsrsRequest.php app/Http/Controllers/Api/V1/ProgressController.php app/Http/Controllers/Api/V1/FsrsController.php app/Domain/Fsrs/FsrsScheduler.php routes/spa.php tests/Feature/Api/V1/FsrsInsightApiTest.php tests/Unit/FsrsSchedulerTest.php
git commit -m "feat: add learner fsrs insight"
```

### Task 7: Build Editorial Progress and predicted Review intervals

**Files:**
- Modify: `frontend/src/{lib/api.ts,types/api.ts,features/progress/progress-page.tsx,app/review/page.tsx}`
- Create: `frontend/src/features/progress/progress-page.test.tsx`
- Create: `frontend/src/app/review/review-page.test.tsx`

- [ ] **Step 1: Write failing UI tests**

Progress: due CTA, retention null/value, forecast, state distribution, course/unit/lesson breakdown, recent sessions, quiz performance, listening, and all states. Review: preview loading, four precise interval labels after reveal, keyboard 1–4, preview failure without blocking rating, and stale revision.

- [ ] **Step 2: Confirm RED**

Run: `cd frontend && ./node_modules/.bin/vitest run src/features/progress/progress-page.test.tsx src/app/review/review-page.test.tsx`

- [ ] **Step 3: Implement with CSS/SVG only**

Use Editorial Learning cards/hero and an accessible SVG or CSS bar forecast. Keep `/review` focused. Do not add a chart package.

- [ ] **Step 4: Run learner gates and commit**

```bash
cd frontend
./node_modules/.bin/vitest run
./node_modules/.bin/eslint .
./node_modules/.bin/next build
git add src/lib/api.ts src/types/api.ts src/features/progress/progress-page.tsx src/features/progress/progress-page.test.tsx src/app/review/page.tsx src/app/review/review-page.test.tsx
git commit -m "feat: enrich learner progress and review"
```

## Chunk 3: Safe Staged Import

### Task 8: Add staging schema and pure classification

**Files:**
- Create: `database/migrations/2026_07_29_030000_create_admin_import_staging.php`
- Create: `app/Models/AdminImportItem.php`
- Create: `app/Services/Import/StagedImportClassifier.php`
- Create: `tests/Unit/StagedImportClassifierTest.php`
- Modify: `app/Models/AdminImportRun.php`, `tests/Feature/AdminContentSchemaTest.php`

- [ ] **Step 1: Write failing schema/classifier tests**

Assert run states and items store entity/source identity, normalized fingerprint, safe candidate/snapshot, base revision/fingerprint/override, classification, selected action, parent/dependencies, validation errors, and reviewer metadata. A run records `initiator_type=admin|cli`; `actor_id` is required for admin and nullable only for CLI. Cover `new`, `exact_duplicate`, `upstream_update`, `local_conflict`, `invalid`, and natural-key ambiguity.

- [ ] **Step 2: Confirm RED; implement minimum schema and pure classifier**

Run: `php artisan test tests/Unit/StagedImportClassifierTest.php tests/Feature/AdminContentSchemaTest.php`

Keep classification free of HTTP and writes. Store only fields needed for diff/apply; do not archive credentials or transport headers.

- [ ] **Step 3: Verify the schema contract and commit**

```bash
php artisan test tests/Unit/StagedImportClassifierTest.php tests/Feature/AdminContentSchemaTest.php
git add database/migrations/2026_07_29_030000_create_admin_import_staging.php app/Models/AdminImportItem.php app/Models/AdminImportRun.php app/Services/Import/StagedImportClassifier.php tests/Unit/StagedImportClassifierTest.php tests/Feature/AdminContentSchemaTest.php
git commit -m "feat: add import staging model"
```

### Task 9: Convert import runner from direct apply to staged review

**Files:**
- Modify: `app/Jobs/RunAdminImport.php`, `app/Services/AdminImportRunner.php`
- Modify: `app/Services/Import/{CategoryImporter,CourseImporter,LessonContentImporter,TagTopicImporter}.php`
- Modify: `app/Services/LexiLingoVocabularySync.php`, `config/features.php`
- Modify: `app/Console/Commands/ImportLexiLingoDataset.php`, `app/Console/Commands/SyncLexiLingoVocabulary.php`
- Test: `tests/Feature/Api/V1/AdminContentOperationsApiTest.php`
- Test: `tests/Feature/{LexiLingoImportTest,LexiLingoVocabularySyncTest}.php`

- [ ] **Step 1: Write failing run-state tests**

Prove fetch produces `review_ready` items without catalog mutation, staging/failure never advances the checkpoint, dependency edges are recorded, and write/apply remains disabled by default. A transport/schema/batch invariant failure produces terminal `validation_failed`; item-level business validation remains an `invalid` item in `review_ready` so it can be excluded with dependants before approval.

- [ ] **Step 2: Confirm RED; split fetch from apply**

Run: `php artisan test tests/Feature/Api/V1/AdminContentOperationsApiTest.php tests/Feature/LexiLingoImportTest.php tests/Feature/LexiLingoVocabularySyncTest.php`

Reuse existing client/schema/pagination code. Change admin jobs and both CLI commands to create/stage runs only; remove direct catalog apply from command paths. CLI-created runs use `initiator_type=cli` and null `actor_id`; they remain auditable and require an authenticated admin to review/apply. `--dry-run` may validate/fetch without staging, but no CLI option bypasses approval. Add `features.lexilingo_import_apply` defaulting false, separate from staging fetch, and add Artisan command assertions to the existing LexiLingo feature tests.

- [ ] **Step 3: Run importer regression tests and commit**

```bash
php artisan test --filter='AdminContentOperations|LexiLingoImport|LexiLingoLessonSync|LexiLingoVocabularySync'
git add app/Jobs/RunAdminImport.php app/Services/AdminImportRunner.php app/Services/Import/CategoryImporter.php app/Services/Import/CourseImporter.php app/Services/Import/LessonContentImporter.php app/Services/Import/TagTopicImporter.php app/Services/LexiLingoVocabularySync.php app/Console/Commands/ImportLexiLingoDataset.php app/Console/Commands/SyncLexiLingoVocabulary.php config/features.php tests/Feature/Api/V1/AdminContentOperationsApiTest.php tests/Feature/LexiLingoImportTest.php tests/Feature/LexiLingoLessonSyncTest.php tests/Feature/LexiLingoVocabularySyncTest.php
git commit -m "feat: stage catalog imports for review"
```

### Task 10: Add approval, stale-revision protection, and Google step-up

**Files:**
- Create: `app/Services/AdminImportApproval.php`, `app/Support/RecentGoogleAdmin.php`
- Create: `tests/Feature/Api/V1/AdminImportApprovalApiTest.php`
- Modify: `app/Http/Controllers/AdminGoogleAuthController.php`
- Modify: `app/Http/Controllers/Api/V1/Admin/ContentOperationsController.php`, `routes/spa.php`
- Modify: `app/Policies/OperationsPolicy.php`
- Test: `tests/Feature/Api/V1/AdminContentOperationsApiTest.php`

- [ ] **Step 1: Write failing security/transaction tests**

Cover history/show/items, draft selection, exclusion cascade, invalid handling, and these separate actions with `features.lexilingo_import_apply=true` only inside apply tests: add-new needs no step-up; `upstream_update` replacement allows Admin but requires fresh Google auth; `local_conflict` replacement requires Super Admin plus fresh Google auth. Cover the default-disabled response, `428`, subject/session binding, replay, row lock/base revision, stale rollback, dependency order, checkpoint advancement only after accepted apply, audit/cancel/reset/retry. Add a race where staged `new` becomes existing before apply: re-resolve source identity under transaction and reclassify rather than leaking a unique-key error. Assert explicit policy capabilities for review/approve/apply/replace/cancel.

- [ ] **Step 2: Confirm RED**

Run: `php artisan test tests/Feature/Api/V1/AdminImportApprovalApiTest.php tests/Feature/Api/V1/AdminContentOperationsApiTest.php tests/Feature/AdminGoogleLoginTest.php`

- [ ] **Step 3: Implement final-action authorization**

Store re-auth freshness in the current session with user ID and Google subject. Add `/import` to the existing local-path return allowlist and preserve only a safe run/draft identifier; test the backend handoff returns there after Google auth. At apply, authorize every action, require `features.lexilingo_import_apply`, re-resolve source identity (including staged-new races), lock targets, compare base state, apply dependency order, and advance checkpoint only after commit. Keep the apply flag false until release evidence passes.

- [ ] **Step 4: Verify and commit**

```bash
php artisan test tests/Feature/Api/V1/AdminImportApprovalApiTest.php tests/Feature/Api/V1/AdminContentOperationsApiTest.php tests/Feature/AdminGoogleLoginTest.php
./vendor/bin/pint --test
git add app/Services/AdminImportApproval.php app/Support/RecentGoogleAdmin.php app/Http/Controllers/AdminGoogleAuthController.php app/Http/Controllers/Api/V1/Admin/ContentOperationsController.php app/Policies/OperationsPolicy.php routes/spa.php tests/Feature/Api/V1/AdminImportApprovalApiTest.php tests/Feature/Api/V1/AdminContentOperationsApiTest.php tests/Feature/AdminGoogleLoginTest.php
git commit -m "feat: approve imports with google step-up"
```

### Task 11: Build import history, diff, approval, and polling UI

**Files:**
- Modify: `admin-frontend/src/lib/api.ts`, `admin-frontend/src/app/import/page.tsx`
- Create: `admin-frontend/src/app/import/import-page.test.tsx`
- Create: `admin-frontend/src/components/{ImportRunList,ImportReviewTable,ImportDiffPanel}.tsx`

- [ ] **Step 1: Write failing UI tests**

Cover run history, 5-second polling while active, slower polling after one minute, cleanup on unmount, classification counts, field diff, default actions, per-item exclusion/cascade warning, invalid block, draft save, apply, stale-conflict refresh, `428` redirect with safe return, and no secret/password storage.

- [ ] **Step 2: Confirm RED and implement typed state machine**

Run: `cd admin-frontend && ./node_modules/.bin/vitest run src/app/import/import-page.test.tsx src/lib/api.test.ts`

Keep the existing `/import` route. Put run selection, review table, and one-record diff in the three listed focused components; keep polling/mutation orchestration in the page. Do not introduce global state.

- [ ] **Step 3: Run admin gates and commit**

```bash
cd admin-frontend
./node_modules/.bin/vitest run src/app/import/import-page.test.tsx src/lib/api.test.ts
./node_modules/.bin/eslint .
./node_modules/.bin/next build
git add src/lib/api.ts src/lib/api.test.ts src/app/import/page.tsx src/app/import/import-page.test.tsx src/components/ImportRunList.tsx src/components/ImportReviewTable.tsx src/components/ImportDiffPanel.tsx
git commit -m "feat: add import review workspace"
```

## Chunk 4: Contract and Release Gate

### Task 12: Align all privileged mutations with Google recent re-auth

**Files:**
- Modify: `app/Http/Controllers/Api/V1/Admin/{UserController,OperationsController}.php`
- Modify: `app/Support/RecentPassword.php`, `admin-frontend/src/{lib/api.ts,app/roles/page.tsx,app/operations/page.tsx}`
- Create: `admin-frontend/src/app/roles/roles-page.test.tsx`, `admin-frontend/src/app/operations/operations-page.test.tsx`
- Test: `tests/Feature/Api/V1/{AdminUserApiTest,OperationsApiTest,ProfileApiTest}.php`

- [ ] **Step 1: Add failing subject/session-bound tests**

Cover role, teacher scope, quota, and alert rule with fresh/stale/mismatched Google session. For every mutation, assert capability authorization runs first: lower roles receive `403`, never a revealing `428`; only an authorized actor lacking freshness receives the stable `428`. Preserve learner account deletion’s current-password behavior through `RecentPassword`; only admin Google operations use `RecentGoogleAdmin`.

- [ ] **Step 2: Implement and verify**

Return the same stable `428` response and redirect through the existing handoff. Resume only allowlisted local paths and preserve unsaved form state only in memory/session-safe draft identifiers.

Run:

```bash
php artisan test --filter='AdminUserApi|OperationsApi|ProfileApi'
cd admin-frontend && ./node_modules/.bin/vitest run src/app/roles/roles-page.test.tsx src/app/operations/operations-page.test.tsx src/lib/api.test.ts
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Api/V1/Admin/UserController.php app/Http/Controllers/Api/V1/Admin/OperationsController.php app/Support/RecentPassword.php admin-frontend/src/lib/api.ts admin-frontend/src/lib/api.test.ts admin-frontend/src/app/roles/page.tsx admin-frontend/src/app/roles/roles-page.test.tsx admin-frontend/src/app/operations/page.tsx admin-frontend/src/app/operations/operations-page.test.tsx tests/Feature/Api/V1/AdminUserApiTest.php tests/Feature/Api/V1/OperationsApiTest.php tests/Feature/Api/V1/ProfileApiTest.php
git commit -m "fix: require google step-up for privileged changes"
```

### Task 13: Make OpenAPI match concrete runtime operations

**Files:**
- Create: `tests/Feature/ApiContractParityTest.php`
- Modify: `docs/openapi/laravel-v1.yaml`, `docs/api/route-doc-gap-log.md`
- Modify: `postman/php-learning-english-web.postman_collection.json`

- [ ] **Step 1: Write a failing method/path parity test**

Normalize parameter names but compare HTTP method plus path. Parse only the OpenAPI `paths:` keys and their standard method children with a small test helper; do not add a YAML dependency. Build the runtime inventory deterministically from every registered route whose URI starts with `api/v1/`, excluding implicit `HEAD`/`OPTIONS`; compare both directions. Keep one explicit compatibility allowlist in the test for legacy `/api/admin` routes, with a removal note per entry—no selective controller/path filtering.

- [ ] **Step 2: Remove phantom generic operations and document concrete routes**

Delete `{resource}`, nonexistent `sync-runs`, retry-sync, and secret-rotation operations unless runtime now implements them. Add Course Path, Unit, FSRS preview, import staging/approval, and exact existing supported paths.

- [ ] **Step 3: Validate**

```bash
php artisan test tests/Feature/ApiContractParityTest.php
cd frontend && ./node_modules/.bin/redocly lint ../docs/openapi/laravel-v1.yaml
cd .. && php -r 'json_decode(file_get_contents("postman/php-learning-english-web.postman_collection.json"), true, flags: JSON_THROW_ON_ERROR);'
```

Expected: parity PASS; Redocly valid with only consciously accepted warnings.

- [ ] **Step 4: Commit**

```bash
git add docs/openapi/laravel-v1.yaml docs/api/route-doc-gap-log.md postman/php-learning-english-web.postman_collection.json tests/Feature/ApiContractParityTest.php
git commit -m "docs: align api contract with learning path"
```

### Task 14: Execute release evidence and rollback rehearsal

**Files:**
- Modify: `docs/PRODUCTION_ENV.md`, `docs/security/production-runbook.md`, `docs/CURRENT_STATUS.md`
- Create: `docs/release-evidence/2026-07-29-learning-path.md`
- Create: `database/seeders/ReleaseImportScenarioSeeder.php`
- Create: `tests/Feature/ReleaseImportScenarioSeederTest.php`

- [ ] **Step 1: Run the complete local gate**

```bash
./vendor/bin/pint --test
php artisan test
DB_CONNECTION=mysql DB_HOST="$MYSQL_TEST_HOST" DB_PORT="$MYSQL_TEST_PORT" DB_DATABASE="$MYSQL_TEST_DATABASE" DB_USERNAME="$MYSQL_TEST_USERNAME" DB_PASSWORD="$MYSQL_TEST_PASSWORD" php artisan test --group=mysql-concurrency
cd frontend && ./node_modules/.bin/vitest run && ./node_modules/.bin/eslint . && ./node_modules/.bin/next build
cd ../admin-frontend && ./node_modules/.bin/vitest run && ./node_modules/.bin/eslint . && ./node_modules/.bin/next build
cd ../frontend && ./node_modules/.bin/redocly lint ../docs/openapi/laravel-v1.yaml
cd .. && php artisan test tests/Feature/ApiContractParityTest.php
```

`CourseLearningPathMysqlConcurrencyTest` owns the `mysql-concurrency` group and must refuse SQLite. `ReleaseImportScenarioSeederTest` proves the release seeder refuses production, requires apply disabled at seed time, and creates only reserved `release-fixture-*` source identities plus a manifest of exact run IDs for add, upstream-update, local-conflict, and stale scenarios.

- [ ] **Step 2: Run role-based browser smoke**

Against a named non-production staging URL, use three dedicated seeded identities (`RELEASE_LEARNER_EMAIL`, `RELEASE_ADMIN_EMAIL`, `RELEASE_SUPER_ADMIN_EMAIL`; never personal accounts). Record URL, backend release, both frontend deployment IDs, test identity IDs, timestamp, request IDs, and pass/fail in the evidence file. With import apply still disabled, verify learner enroll → Course Path → eligible lesson → session → summary → Progress → Review; admin Unit authoring; import history/review; and forbidden paths for lower roles. Capture console/API failures without tokens or payload data.

- [ ] **Step 3: Run bounded real-provider verification**

First prove `APP_ENV=staging`, `features.lexilingo_import=true`, and `features.lexilingo_import_apply=false` using `php artisan tinker --execute='throw_unless(app()->environment("staging") && config("features.lexilingo_import") && ! config("features.lexilingo_import_apply"));'`. With configured secrets, run `php artisan lexilingo:import all --limit=1 --dry-run`, require zero transport/schema errors, then run `php artisan lexilingo:import all --limit=1` to create one staged run and record its run ID/counts. Inspect duplicate/conflict/invalid counts without printing payloads or credentials. Do not apply to production until backup evidence exists.

- [ ] **Step 4: Rehearse backup/restore and deployment rollback**

Use MySQL client option files so credentials never appear in shell history. Create a consistent backup with `mysqldump --defaults-extra-file="$PROD_MYSQL_CNF" --single-transaction --routines --triggers "$DB_DATABASE" > "$BACKUP_FILE"`. Before restore, run `test -n "$RESTORE_DB_DATABASE" && test "$RESTORE_DB_DATABASE" != "$DB_DATABASE"` and require the target name to match the dedicated `restore_rehearsal_*` convention; query `SELECT DATABASE()` through `RESTORE_MYSQL_CNF` and abort unless it resolves to that exact target. Restore with `mysql --defaults-extra-file="$RESTORE_MYSQL_CNF" "$RESTORE_DB_DATABASE" < "$BACKUP_FILE"`, then run row-count/checksum and application health checks against that target. Record backup checksum/identifier, restore target/evidence, backend/learner/admin deployment references, disable-write-first rollback order, and the catalog-data restoration decision. Never restore into production during rehearsal and never use migration rollback to undo an approved import.

- [ ] **Step 5: Enable apply on staging and smoke the destructive boundary**

After Steps 1–4 pass, run `php artisan db:seed --class=ReleaseImportScenarioSeeder --force` on staging and record its manifest of deterministic run IDs/source identities; never depend on the bounded real-provider page containing every classification. Install an EXIT trap in the operator shell that sets `FEATURE_LEXILINGO_IMPORT_APPLY=false`, clears Laravel config, and restarts queue workers. Enable the flag on staging only, clear cached config/restart workers, then use an application-side assertion to prove web and queue runtime both observe `true`. Using the manifest, verify Admin add-new, Admin upstream replacement after Google re-auth, Super Admin local-conflict replacement after Google re-auth, stale rejection, and lower-role `403`. Trigger the cleanup in both success and failure paths, then assert web and queue runtime observe `false` before concluding. Do not enable production apply in this task.

- [ ] **Step 6: Update current status and commit evidence**

```bash
git add database/seeders/ReleaseImportScenarioSeeder.php tests/Feature/ReleaseImportScenarioSeederTest.php docs/PRODUCTION_ENV.md docs/security/production-runbook.md docs/CURRENT_STATUS.md docs/release-evidence/2026-07-29-learning-path.md
git commit -m "docs: record learning path release evidence"
```

## Completion Criteria

- Learner follows a real local Course → Unit → Lesson → Session path with server-owned eligibility.
- Admin can author Units independently of LexiLingo and local edits survive sync.
- Progress shows server-derived course/FSRS insight; Review shows non-mutating predicted intervals.
- Import validates and stages before mutation, detects duplicates/conflicts, supports batch approval with exclusions, and rejects stale approvals.
- Privileged replacement/reset/role/scope/quota/rule actions require session- and subject-bound recent Google authentication.
- Concrete runtime/OpenAPI parity passes; all backend/frontend gates and role-based browser smoke pass.
- Production apply remains disabled until bounded provider, backup/restore, and rollback evidence is recorded.
