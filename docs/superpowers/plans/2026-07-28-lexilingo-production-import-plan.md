# LexiLingo Production Import and Interface Completion Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Import a bounded real LexiLingo catalog safely and expose working learner/admin interfaces for courses, lessons, vocabulary, FSRS, TraceCAG, STT and TTS.

**Architecture:** Extend the existing importer/client and `/api/v1` contracts rather than preserving legacy APIs. Laravel owns durable state and credentials; both Next.js applications consume same-origin Laravel endpoints. Implement and verify one vertical slice at a time.

**Tech Stack:** Laravel 12, PHP 8.2, PHPUnit, Next.js 16, React 19, TypeScript, OpenAPI/Redocly.

---

## Chunk 1: Production-safe import

### Task 1: Shared partner request policy

**Files:**
- Modify: `app/Services/LexiLingoClient.php`
- Modify: `config/services.php`
- Test: `tests/Feature/LexiLingoClientTest.php`

- [ ] Add failing tests for bounded retry, `Retry-After`, non-retryable 4xx and redacted failures.
- [ ] Cover `Retry-After` seconds and HTTP-date, server/local delay precedence,
  capped jitter, connect/total timeouts and injectable clock/sleeper/jitter.
- [ ] Run `php artisan test tests/Feature/LexiLingoClientTest.php` and confirm failure.
- [ ] Add one shared partner request method using Laravel HTTP retry, timeouts and configurable delay/backoff.
- [ ] Use a cache lock to prevent concurrent import runs.
- [ ] Prove the distributed singleton across CLI, queued jobs and API starts.
- [ ] Re-run the focused test and Pint.
- [ ] Commit the slice.

### Task 2: Bounded resumable command

**Files:**
- Modify: `app/Console/Commands/ImportLexiLingoDataset.php`
- Modify: `app/Services/Import/AbstractLexiLingoImporter.php`
- Modify: `app/Services/Import/CourseImporter.php`
- Modify: `app/Services/LexiLingoVocabularySync.php`
- Create/modify migrations and models only if current checkpoint schema cannot represent run state.
- Test: `tests/Feature/LexiLingoImportTest.php`

- [ ] Add failing tests for run UUID/state, requested resources, source/schema
  and option fingerprints, per-resource cursor/page plus external-ID position,
  incompatible resume, dry-run immutability and idempotent upserts.
- [ ] Enforce and test exact dependency order `categories → courses → units →
  lessons/content → vocabulary`, unresolved-parent failures, multi-page stable
  traversal and resume across a page boundary.
- [ ] Add command options `--page-size`, `--max-items`, `--delay-ms`,
  `--max-retries`, `--resume` and `--production-confirm` while retaining
  compatibility with `--limit`.
- [ ] Test that production writes are rejected without
  `--production-confirm`; define the confirmed CLI command as the only import
  path allowed while the API/UI/job feature remains disabled.
- [ ] Apply the global bound only between atomic top-level records; persist each
  resource checkpoint only after its nested transaction commits.
- [ ] Add redacted/deduplicated failure records, warning outcomes, archival
  suppression after any transport, contract or validation failure regardless
  of terminal state, and soft archival only after a zero-warning complete snapshot.
- [ ] Test retry exhaustion preserves the last committed checkpoint, marks the
  run failed, exits `1`, and resumes compatibly without duplicate committed rows.
- [ ] Test that re-import preserves local publication, annotations,
  enrollment, progress, assignments, learning events and FSRS/review fields.
- [ ] Add default-off feature gating, queued state transitions and bounded
  synchronous-queue fallback.
- [ ] Require interactive confirmation for CLI reset unless `--force`, and
  guard fallback/fake-evidence seeders from production.
- [ ] Return exit `0` for completed/partial bounded work and `1` for exhausted transport/contract failures.
- [ ] Run importer tests and Pint.
- [ ] Commit the slice.

### Task 3: Real server-to-server probe

**Files:**
- Modify only configuration documentation if a discovered variable is missing.

- [ ] Inspect configured environment without printing secrets.
- [ ] Run a production-confirmed, feature-disabled dry-run with explicit
  resources, `--page-size=1`, `--max-items=3`, no reset/force, and capture its
  source/schema/options fingerprint.
- [ ] Abort on any warning, validation failure, retry, contract mismatch,
  credential failure or unexpected response shape.
- [ ] If contract mismatch occurs, capture only status/schema-safe evidence and fix the client contract with a test.
- [ ] Re-run the dry-run and record counts/status.

## Chunk 2: Administration

### Task 4: Import status/actions API

**Files:**
- Create: `app/Http/Controllers/Api/V1/Admin/ImportController.php`
- Modify: `routes/spa.php`
- Modify: `app/Providers/AuthServiceProvider.php` or existing capability provider
- Modify: `docs/openapi/laravel-v1.yaml`
- Test: `tests/Feature/Api/V1/AdminImportApiTest.php`

- [ ] Test `200/202/401/403/409/422/503`, CSRF, pagination, run/failure
  ownership, unresolved-only retry, recent password, response/log redaction,
  payload-bound audit and identical replay versus mismatched `409`.
- [ ] Implement list/start/show/resume/failure/retry/reset endpoints from the spec.
- [ ] Require recent password and super-admin capabilities for reset/retry.
- [ ] Add OpenAPI schemas and validate with Redocly.
- [ ] Run focused tests and Pint.
- [ ] Commit the slice.

### Task 5: Import administration UI

**Files:**
- Create: `admin-frontend/src/app/imports/page.tsx`
- Modify: `admin-frontend/src/lib/api.ts`
- Modify: `admin-frontend/src/components/Sidebar.tsx`

- [ ] Add typed API calls for runs, failures and allowed actions.
- [ ] Build states for unavailable, idle, running, partial, completed and failed.
- [ ] Poll every five seconds, switch to fifteen seconds after one minute,
  pause when hidden and stop immediately on a terminal run.
- [ ] Leave the UI unavailable while the default-off production feature flag
  is disabled and test polling timers/hidden-tab transitions deterministically.
- [ ] Expose reset/retry only to super admin with password input.
- [ ] Run admin lint and production build.
- [ ] Commit the slice.

### Task 6: Complete catalog CRUD

**Files:**
- Modify: `app/Http/Controllers/Api/V1/Admin/CatalogController.php`
- Modify: `routes/spa.php`
- Modify: `docs/openapi/laravel-v1.yaml`
- Modify/create: `admin-frontend/src/app/courses/**`
- Create: `admin-frontend/src/app/vocabulary/page.tsx`
- Test: `tests/Feature/Api/V1/AdminCatalogApiTest.php`

- [ ] Add failing tests for units, lessons/content, vocabulary and activity/quiz CRUD with role and idempotency checks.
- [ ] Implement only the missing resource operations using existing models and policies.
- [ ] Build course drill-down editors and vocabulary/activity management.
- [ ] Remove or redirect remaining legacy admin pages that call nonexistent endpoints.
- [ ] Run focused backend tests, OpenAPI validation, admin lint and build.
- [ ] Commit the slice.

## Chunk 3: Learner workflow

### Task 7: Vocabulary and FSRS flashcards

**Files:**
- Modify: `frontend/src/lib/api.ts`
- Modify: `frontend/src/features/vocabulary/vocabulary-page.tsx`
- Modify: `frontend/src/features/flashcards/flashcards-page.tsx`
- Modify: `frontend/src/components/layout/app-shell.tsx`
- Test: `tests/Feature/Api/V1/VocabularyApiTest.php`
- Test: `tests/Feature/Api/V1/FsrsApiTest.php`

- [ ] Replace every supported vocabulary/flashcard call with `/api/v1/vocabulary` and `/api/v1/fsrs/*`.
- [ ] Add explicit loading, empty, error, reveal and rating states.
- [ ] Add TTS playback to a card without exposing credentials.
- [ ] Restore Words/Flashcards navigation only after their route contracts pass.
- [ ] Run focused API tests, learner lint and build.
- [ ] Commit the slice.

### Task 8: Lesson voice and TraceCAG tutor

**Files:**
- Modify: `frontend/src/app/session/[id]/page.tsx`
- Modify: `frontend/src/lib/api.ts`
- Reuse: `frontend/src/components/voice-recorder.tsx` if present
- Test: `tests/Feature/Api/V1/AiProxyTest.php`
- Test: `tests/Feature/Api/V1/TraceCagApiTest.php`
- Test: `tests/Feature/Api/V1/LearningSessionApiTest.php`

- [ ] Map each lesson activity to answer, hint/chat, STT/pronunciation and TTS controls supported by its type.
- [ ] Ensure recording consent, MIME/size errors, disabled/in-flight states and cleanup.
- [ ] Persist evidence before requesting TraceCAG and render structured assistance/fallback.
- [ ] Run focused backend tests, learner lint and build.
- [ ] Commit the slice.

### Task 9: Remove dead learner routes

**Files:**
- Modify/delete only legacy pages confirmed to have no current contract.
- Modify: `frontend/src/components/layout/app-shell.tsx`

- [ ] Inventory every remaining frontend API path against `php artisan route:list`.
- [ ] Redirect unsupported quiz/import legacy routes to course/session workflows.
- [ ] Confirm no supported screen silently catches transport errors into fake values.
- [ ] Run lint and build.
- [ ] Commit the slice.

## Chunk 4: Production verification

### Task 10: Bounded real import and full regression

**Files:**
- Modify: project docs only for verified operational commands.

- [ ] Run production credential/health probe without logging the key.
- [ ] Snapshot catalog, run/checkpoint/failure and archival state before import.
- [ ] Run a zero-warning dry-run using the exact production source/schema/
  option fingerprint intended for import, with explicit resources and bounds.
- [ ] Abort unless dry-run is zero-warning; keep the feature flag disabled and
  never use reset/force.
- [ ] Run exactly `php artisan lexilingo:import all --page-size=1
  --max-items=3 --delay-ms=500 --max-retries=3 --production-confirm` with the
  feature still disabled, then snapshot state again.
- [ ] Verify counts, duplicates, run/checkpoint/failure state, field ownership
  and complete-snapshot archival behavior.
- [ ] Run the complete Laravel test suite.
- [ ] Run lint and production build for both frontends.
- [ ] Validate OpenAPI and `git diff --check`.
- [ ] Verify admin and learner client paths all map to registered routes.
- [ ] Perform authenticated, forbidden and error-state browser smoke tests
  when callable; otherwise run equivalent HTTP/feature smoke tests and record
  the limitation.
- [ ] Enable the production import/UI flag only after all verification passes;
  otherwise leave it disabled.
- [ ] Verify rollback by disabling the importer/UI flag and import jobs first,
  then confirm imported catalog rows and all learning state remain unchanged.
- [ ] Commit final fixes and report exact imported/tested results.
