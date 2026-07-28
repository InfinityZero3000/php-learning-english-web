# Supervised Learning, TraceCAG, and FSRS Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver an end-to-end learner, teacher, admin, and super-admin system in which Laravel owns identity and learning state while LexiLingo supplies content, TraceCAG, STT, pronunciation, and TTS services.

**Architecture:** Laravel remains the system of record and exposes one versioned `/api/v1` surface to both Next.js applications. Content is imported locally; FSRS-6 scheduling, supervision, authorization, and audit are deterministic Laravel services. LexiLingo receives one backward-compatible service endpoint for bounded TraceCAG requests and otherwise remains an external service provider.

**Tech Stack:** PHP 8.3, Laravel 13, MySQL/SQLite tests, Redis cache/queues, PHPUnit 12, Next.js 16, React 19, TypeScript, Tailwind CSS, LexiLingo FastAPI/Python, pytest.

**Approved design:** `docs/superpowers/specs/2026-07-28-supervised-learning-tracecag-fsrs-design.md`

---

## File Structure

### Laravel domain

- `app/Domain/Fsrs/FsrsScheduler.php`: pure deterministic FSRS-6 calculation.
- `app/Domain/Fsrs/FsrsConfig.php`: pinned v6.3.0 parameters and version.
- `app/Services/LearningSessionService.php`: plan/start/advance/complete session orchestration.
- `app/Services/VocabularyReviewService.php`: locked idempotent FSRS review transaction.
- `app/Services/SupervisionAlertService.php`: deterministic alert evaluation and lifecycle.
- `app/Services/LexiLingoTraceCag.php`: bounded TraceCAG service client.
- `app/Services/Import/*`: reuse importer branch checkpoint/failure infrastructure.
- `app/Policies/*`: capabilities for learner ownership, teacher scope, admin content, and super-admin operations.
- `app/Http/Controllers/Api/V1/*`: thin learner endpoints.
- `app/Http/Controllers/Api/V1/Teacher/*`: teacher-scoped endpoints.
- `app/Http/Controllers/Api/V1/Admin/*`: business administration.
- `app/Http/Controllers/Api/V1/Admin/Operations/*`: high-risk operations.

### Laravel data

- One new forward migration adds roles, enrollment/session/supervision tables, FSRS revision/snapshots, and required constraints.
- Existing `progress`, `attempts`, `user_vocabularies`, and `vocabulary_reviews` are extended rather than duplicated.
- Existing LexiLingo import tracking tables from `origin/feature/9-lexilingo-import` are reused.

### Frontends

- `frontend/src/features/learning/*`: Today plan, course path, study session, summary, AI/voice.
- `frontend/src/features/teacher/*`: assigned learners, alerts, evidence, assignments.
- `admin-frontend/src/app/*`: replace hard-coded content screens and add operations screens.
- Existing API clients remain the single browser transport and gain typed canonical `/api/v1` methods.

### LexiLingo

- `contracts/trace-cag/external-analyze-v1.schema.json`: shared request/response contract.
- `ai-service/api/routes/integration_trace_cag.py`: service-authenticated external endpoint.
- `ai-service/api/core/*`: current/previous token validation and request retention limits.
- Focused provider contract and security tests; no user/JWT behavior changes.

## Chunk 1: Contracts, Schema, and Authorization

### Task 1: Pin API and TraceCAG contracts

**Files:**
- Modify: `docs/openapi/laravel-v1.yaml`
- Create: `docs/openapi/trace-cag-external-v1.schema.json`
- Create in LexiLingo: `contracts/trace-cag/external-analyze-v1.schema.json`
- Test: `frontend/scripts/validate-lexilingo-schema.mjs`

- [ ] Add exact learner, teacher, admin, operations, FSRS, session, and degraded-response paths to Laravel OpenAPI.
- [ ] Define the TraceCAG request/response JSON Schema once and copy the byte-identical contract to LexiLingo.
- [ ] Extend the existing schema validator to assert both copies have the same SHA-256.
- [ ] Run `cd frontend && pnpm exec redocly lint ../docs/openapi/laravel-v1.yaml`.
- [ ] Run `cd frontend && node scripts/validate-lexilingo-schema.mjs`.
- [ ] Commit `docs: pin supervised learning service contracts`.

### Task 2: Add learning, supervision, and operations schema

**Files:**
- Restore before the new migration from `origin/feature/9-lexilingo-import@1d1ed74d4caa7e2ebeac7ab777386260e6f01727`:
  `database/migrations/2026_07_26_112424_create_lexilingo_import_tracking_tables.php`,
  `app/Models/{LexiLingoImportCheckpoint,LexiLingoImportFailure}.php`
- Create: `database/migrations/2026_07_28_000000_create_supervised_learning_schema.php`
- Create: `app/Models/Enrollment.php`
- Create: `app/Models/LearningSession.php`
- Create: `app/Models/LearningEvent.php`
- Create: `app/Models/LearningAssistanceResult.php`
- Create: `app/Models/TeacherAssignment.php`
- Create: `app/Models/SupervisionAlert.php`
- Create: `app/Models/Assignment.php`
- Create: `app/Models/InterventionNote.php`
- Create: `app/Models/OperationsAudit.php`
- Create: `app/Models/QuotaPolicy.php`
- Create: `app/Models/AlertRule.php`
- Modify: `app/Models/Progress.php`
- Modify: `app/Models/UserVocabulary.php`
- Modify: `app/Models/VocabularyReview.php`
- Test: `tests/Feature/SupervisedLearningSchemaTest.php`

- [ ] Review and restore the pinned import tracking migration/models first so later migrations and importer work share the existing infrastructure.
- [ ] Write a failing schema test for foreign keys, unique constraints, assignment XOR target, FSRS revision, nullable initial state, append-only relationships, audit records, quota policy, and versioned alert rules.
- [ ] Create the minimum forward migration and model relationships.
- [ ] Make only expand-safe schema changes here; do not replay FSRS history until Task 4 has shipped the scheduler.
- [ ] Keep raw audio and unrestricted prompt/trace columns out of the schema.
- [ ] Run migration up, rollback, and up against the test database.
- [ ] Run `php artisan test tests/Feature/SupervisedLearningSchemaTest.php`.
- [ ] Commit `feat: add supervised learning data model`.

### Task 3: Add roles, capability policies, and account anonymization

**Files:**
- Modify: `database/seeders/CatalogSeeder.php`
- Modify: `app/Models/User.php`
- Modify: `app/Policies/UserPolicy.php`
- Create: `app/Policies/LearningPolicy.php`
- Create: `app/Policies/OperationsPolicy.php`
- Create: `app/Console/Commands/PromoteSuperAdmin.php`
- Create: `app/Services/AnonymizeAccount.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Http/Controllers/Admin/UserController.php`
- Modify: `app/Http/Controllers/Api/V1/ProfileController.php`
- Test: `tests/Feature/RoleCapabilityTest.php`
- Test: `tests/Feature/PromoteSuperAdminTest.php`
- Test: `tests/Feature/AccountAnonymizationTest.php`

- [ ] Write failing tests for four roles, stored teacher scope, admin restrictions, super-admin transitions, final-super-admin protection, recent-password confirmation, and deletion anonymization.
- [ ] Add `teacher` and `super_admin` role records without seeding a privileged account.
- [ ] Add explicit `User::hasRole()` and capability Gates; stop using exact-role middleware for shared admin content access.
- [ ] Add the interactive email-based promotion command with confirmation and an append-only operations audit.
- [ ] Prevent admin from granting teacher/admin/super-admin and from reading learner evidence.
- [ ] Anonymize retained reviews, resolved alerts, and audit ownership while deleting learner-owned ephemeral/session data.
- [ ] Run `php artisan test --filter='RoleCapability|PromoteSuperAdmin|AccountAnonymization|AdminMiddleware'`.
- [ ] Commit `feat: add scoped roles and account anonymization`.

## Chunk 2: FSRS and Learner Vertical Slice

### Task 4: Implement deterministic FSRS-6

**Files:**
- Create: `app/Domain/Fsrs/FsrsConfig.php`
- Create: `app/Domain/Fsrs/FsrsCard.php`
- Create: `app/Domain/Fsrs/FsrsResult.php`
- Create: `app/Domain/Fsrs/FsrsScheduler.php`
- Test: `tests/Unit/FsrsSchedulerTest.php`
- Fixture: `tests/Fixtures/fsrs_v6_3_0.json`
- Create: `tests/Fixtures/generate_fsrs_v6_3_0.py`
- Create: `app/Console/Commands/BackfillFsrsState.php`
- Test: `tests/Feature/BackfillFsrsStateTest.php`

- [ ] Check in a deterministic generator and document: `python3 -m venv /tmp/php-learning-fsrs-fixture && /tmp/php-learning-fsrs-fixture/bin/pip install fsrs==6.3.0 && /tmp/php-learning-fsrs-fixture/bin/python tests/Fixtures/generate_fsrs_v6_3_0.py`.
- [ ] Generate reference fixtures for new, learning, review, lapse, same-day, and maximum-interval cases with desired retention `0.90`, approved steps, maximum interval, UTC times, and fuzzing disabled.
- [ ] Write a failing PHPUnit fixture parity test.
- [ ] Implement only the published FSRS-6 formula with the approved 21 parameters, no fuzzing, UTC clock injection, and no package dependency.
- [ ] Assert exact states, steps, intervals, and due timestamps; use tolerance only for stability and difficulty floats.
- [ ] Add a checkpointed/idempotent backfill command that converts never-reviewed rows and deterministically replays sufficient review history after fixture parity passes; do not enable review writes before this command succeeds.
- [ ] Assert exact before/after state snapshots, algorithm/version, UTC timestamps, revision increments, rerun idempotency, and checkpoint resume.
- [ ] Run `php artisan test tests/Unit/FsrsSchedulerTest.php`.
- [ ] Run `php artisan test tests/Feature/BackfillFsrsStateTest.php`.
- [ ] Commit `feat: implement deterministic FSRS-6 scheduler`.

### Task 5: Implement idempotent vocabulary reviews

**Files:**
- Create: `app/Services/VocabularyReviewService.php`
- Create: `app/Http/Requests/Api/V1/ReviewVocabularyRequest.php`
- Create: `app/Http/Controllers/Api/V1/FsrsController.php`
- Create: `app/Http/Resources/UserVocabularyResource.php`
- Modify: `routes/spa.php`
- Test: `tests/Feature/Api/V1/FsrsApiTest.php`

- [ ] Write failing API tests for due/stats/review, explicit rating, complete before/after snapshots, algorithm/version, UTC behavior, base revision, request-ID replay, request-ID conflict, stale revision, ownership, transaction rollback, and two concurrent submissions where only one revision succeeds.
- [ ] Lock the user-vocabulary row and atomically insert review, learning event, and updated FSRS state.
- [ ] Return the original response for identical idempotent replay and `409` for mismatched replay/stale revision.
- [ ] Replace legacy `/api/fsrs/*` client paths with canonical `/api/v1/fsrs/*`.
- [ ] Run `php artisan test tests/Feature/Api/V1/FsrsApiTest.php`.
- [ ] Commit `feat: add transactional FSRS review API`.

### Task 6: Implement courses, enrollment, sessions, and progress

**Files:**
- Reuse from `origin/feature/11-learning-catalog-bookmark-quiz-progress@818125dd8b90822b71f7ed64087faff5dc6bbfc7`:
  `app/Http/Controllers/Api/V1/CatalogController.php`,
  `app/Http/Resources/{CourseCategoryResource,CourseResource,LessonResource,LevelResource,ProgressResource,TopicResource,VocabularyResource}.php`,
  and `tests/Feature/Api/V1/{CatalogApiTest,ProgressApiTest}.php`
- Create: `app/Services/LearningSessionService.php`
- Create: `app/Http/Controllers/Api/V1/EnrollmentController.php`
- Create: `app/Http/Controllers/Api/V1/LearningSessionController.php`
- Modify: `app/Http/Controllers/Api/V1/ProgressController.php`
- Modify: `routes/spa.php`
- Test: `tests/Feature/Api/V1/LearningSessionApiTest.php`

- [ ] Review `git diff HEAD 818125dd8b90822b71f7ed64087faff5dc6bbfc7 -- <listed-files>` before applying; restore only listed files and never merge branch history.
- [ ] Write failing tests for enroll, Today plan priority, start/next/complete, lesson progress, ownership, teacher practice not mutating FSRS, and quiz/event atomicity.
- [ ] Implement the minimum session planner and normalized event writes.
- [ ] Keep teacher vocabulary assignments as unscheduled practice.
- [ ] Run catalog, progress, quiz, and learning-session feature tests.
- [ ] Commit `feat: add learner course and session workflow`.

## Chunk 3: LexiLingo, Supervision, and Operations

### Task 7: Complete content import with partner credentials

**Files:**
- Reuse from `origin/feature/9-lexilingo-import@1d1ed74d4caa7e2ebeac7ab777386260e6f01727`:
  `app/Console/Commands/ImportLexiLingoDataset.php`,
  `app/Console/Commands/SyncLexiLingoVocabulary.php`,
  `app/Services/Import/{AbstractLexiLingoImporter,CategoryImporter,CourseImporter,ImportResult}.php`,
  `app/Services/LexiLingoVocabularySync.php`,
  `app/Support/{LexiLingoSchemaValidator,LexiLingoClient}.php`,
  `docs/openapi/lexilingo-import.schema.json`,
  and `tests/Feature/{LexiLingoImportTest,LexiLingoVocabularySyncTest,LexiLingoClientTest}.php`
- Create: `app/Services/Import/CourseOutlineImporter.php`
- Create: `app/Services/Import/LessonContentImporter.php`
- Create: `config/features.php`
- Modify: `app/Support/LexiLingoClient.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Test: `tests/Feature/LexiLingoImportTest.php`

- [ ] Review the pinned branch diff for every listed file, then restore only those files; never merge branch history or its unrelated auth/frontend changes.
- [ ] Extract the pinned `CourseImporter` nested unit/lesson behavior into focused `CourseOutlineImporter` and add `LessonContentImporter` for protected content; keep the branch's DTO/checkpoint conventions.
- [ ] Add the default-off importer feature flag before registering commands/admin actions or performing any production probe.
- [ ] Add `LEXILINGO_PARTNER_API_KEY` to config/example and send `X-LexiLingo-API-Key` only to `/integrations/*`.
- [ ] Restore importer checkpoints, failures, category/course/unit/lesson/vocabulary mapping, pagination, and schema validation.
- [ ] Update the importer to the documented production integration namespace and partial-page visibility semantics.
- [ ] Test public fallback only where explicitly allowed; protected lesson content fails clearly without the partner key.
- [ ] Add tests for every resource's pagination, schema drift/invalid-record handling, rerun idempotency, checkpoint resume, partial-page visibility, complete-snapshot archival only after full success, and no hard-delete.
- [ ] Run importer tests with HTTP fakes, explicitly enable the importer flag only for the command process, then run a read-only one-page production probe and return the flag to off.
- [ ] Commit `feat: import LexiLingo learning content`.

### Task 8: Add LexiLingo TraceCAG service endpoint and PHP AI gateway

**Files in LexiLingo:**
- Create: `ai-service/api/routes/integration_trace_cag.py`
- Modify: `ai-service/api/main.py`
- Modify: `ai-service/api/core/config.py`
- Create: `ai-service/api/core/integration_service_auth.py`
- Create: `ai-service/api/services/trace_cag/external_request_cache.py`
- Create: `ai-service/api/services/audio_cleanup.py`
- Test: `ai-service/tests/test_integration_trace_cag.py`

**Files in PHP:**
- Reuse from `feature/10-ai-proxy@9ff9dbd933c7d25e7a22131de23f7c6070cc56ec`:
  `app/Http/Controllers/Api/V1/AiProxyController.php`,
  `app/Support/ApiResponse.php`,
  `config/services.php`,
  `routes/spa.php`,
  `tests/Feature/Api/V1/AiProxyTest.php`
- Create: `app/Services/LexiLingoTraceCag.php`
- Create: `app/Support/ExternalSubject.php`
- Modify: `app/Support/LexiLingoClient.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Modify: `routes/spa.php`
- Test: `tests/Feature/Api/V1/AiProxyTest.php`
- Test: `tests/Feature/Api/V1/TraceCagApiTest.php`

- [ ] Review the pinned AI-proxy diff before applying and restore only the listed behavior, manually reconciling shared route/config/support files.
- [ ] Write LexiLingo tests for current/previous service token, expired previous-token rejection, constant-time comparison, 8 KiB body/2,000 UTF-8 text/50-concept/20-error limits, subject privacy, request-ID replay/conflict, TTL, redacted logging, quota, and error mapping.
- [ ] Add default-off importer, AI, voice, supervision, and operations feature flags plus tests proving disabled routes return stable `503 feature_disabled`.
- [ ] Add required HMAC subject secret configuration and fail closed when absent.
- [ ] Add the 10-minute request-dedup cache, prove subjects/snapshots/text/transcripts/audio are not retained, validate voice MIME/size, delete audio immediately, and schedule/test the one-hour orphan cleanup.
- [ ] Add the backward-compatible `/api/v1/integrations/trace-cag/v1/analyze` endpoint without changing user JWT routes.
- [ ] Restore PHP translate/STT/pronunciation/TTS proxy behavior from `feature/10-ai-proxy`.
- [ ] Add HMAC subject generation, payload allowlist, TraceCAG timeout, degraded local feedback, and append-only assistance result.
- [ ] Test TraceCAG/STT/pronunciation/TTS success, timeout, `401/403/409/422/429/503/504`, upstream-body redaction, approved audio MIME/size, and deterministic fallback; then run LexiLingo focused pytest and PHP AI/TraceCAG feature tests.
- [ ] Deploy disabled LexiLingo endpoint first, set/rotate current and previous token, probe it, deploy disabled PHP consumer second, then enable LexiLingo followed by PHP. Roll back by disabling PHP first, then LexiLingo.
- [ ] Commit in each repository with focused messages.

### Task 9: Add supervision, teacher, admin, and operations APIs

**Files:**
- Create: `app/Services/SupervisionAlertService.php`
- Create: `app/Listeners/EvaluateLearningEventAlerts.php`
- Create: `app/Events/LearningEventCommitted.php`
- Create: `app/Console/Commands/EvaluateSupervisionAlerts.php`
- Create: `app/Http/Controllers/Api/V1/Teacher/*`
- Create: `app/Http/Controllers/Api/V1/Admin/CatalogController.php`
- Create: `app/Http/Controllers/Api/V1/Admin/OperationsController.php`
- Modify: `routes/spa.php`
- Modify: `routes/console.php`
- Test: `tests/Unit/SupervisionAlertServiceTest.php`
- Test: `tests/Feature/Api/V1/TeacherApiTest.php`
- Test: `tests/Feature/Api/V1/AdminOperationsApiTest.php`

- [ ] Write deterministic tests for all six alert thresholds, active fingerprint, evidence append, severity update, two-pass auto-resolve, manual resolve, and 30-day reopen.
- [ ] Implement event-triggered listener and nightly evaluation without an LLM dependency.
- [ ] Add teacher APIs scoped to assigned learners only.
- [ ] Persist and expose versioned quota policies, alert rules, append-only operations audit, service probes, and sync retry; add admin content/user APIs and super-admin-only operations.
- [ ] Require recent password confirmation for every approved high-risk mutation and never return secret values.
- [ ] Run the three focused test suites.
- [ ] Commit `feat: add supervised learning operations`.

## Chunk 4: Production Interfaces and End-to-End Verification

### Task 10: Build the learner and teacher interface

**Files:**
- Modify: `frontend/src/lib/api.ts`
- Modify: `frontend/src/types/api.ts`
- Modify: `frontend/src/components/layout/app-shell.tsx`
- Create: `frontend/src/app/learn/page.tsx`
- Create: `frontend/src/app/courses/page.tsx`
- Create: `frontend/src/app/courses/[id]/page.tsx`
- Create: `frontend/src/app/session/[id]/page.tsx`
- Create: `frontend/src/app/session/[id]/summary/page.tsx`
- Create: `frontend/src/app/tutor/page.tsx`
- Create: `frontend/src/app/teacher/page.tsx`
- Create: `frontend/src/app/teacher/alerts/page.tsx`
- Create: `frontend/src/app/teacher/learners/[id]/page.tsx`
- Create: `frontend/src/features/learning/*`
- Create: `frontend/src/features/teacher/*`
- Test: `frontend/src/features/learning/learning-flow.test.tsx`
- Test: `frontend/src/features/teacher/teacher-flow.test.tsx`
- Browser test: `frontend/e2e/supervised-learning.spec.ts`

- [ ] Build one restrained, learning-first visual system using existing tokens/components; preserve the current persistent background only where it does not reduce readability.
- [ ] Add Today plan, course path, focused session, accessible flashcard reveal, four FSRS ratings with predicted intervals, AI/voice states, and session summary.
- [ ] Add teacher exception dashboard, alert evidence, assignments, notes, and resolution.
- [ ] Add learner personal progress trends and teacher learner reports.
- [ ] Implement keyboard navigation, visible focus, reduced motion, responsive layouts, and loading/empty/forbidden/degraded/error states.
- [ ] Remove or redirect obsolete frontend calls to missing `/api/*` endpoints.
- [ ] Test four FSRS ratings, degraded assistance, role navigation, teacher scope, loading/empty/forbidden states, and the learner→teacher browser flow.
- [ ] Run `cd frontend && pnpm lint && pnpm build`.
- [ ] Commit `feat: build learner and teacher experience`.

### Task 11: Build admin and super-admin operations interface

**Files:**
- Modify: `admin-frontend/src/lib/api.ts`
- Modify: `admin-frontend/src/components/Sidebar.tsx`
- Replace: `admin-frontend/src/app/courses/page.tsx`
- Modify: `admin-frontend/src/app/users/page.tsx`
- Create: `admin-frontend/src/app/units/page.tsx`
- Create: `admin-frontend/src/app/lessons/page.tsx`
- Modify: `admin-frontend/src/app/vocabulary/page.tsx`
- Modify: `admin-frontend/src/app/quizzes/page.tsx`
- Create: `admin-frontend/src/app/imports/page.tsx`
- Create: `admin-frontend/src/app/service-status/page.tsx`
- Create: `admin-frontend/src/app/integrations/page.tsx`
- Create: `admin-frontend/src/app/monitoring/page.tsx`
- Create: `admin-frontend/src/app/alert-rules/page.tsx`
- Create: `admin-frontend/src/app/audit/page.tsx`
- Create: `admin-frontend/src/app/access/page.tsx`
- Test: `admin-frontend/src/app/admin-role-navigation.test.tsx`
- Test: `admin-frontend/src/app/operations/operations-flow.test.tsx`
- Browser test: `admin-frontend/e2e/admin-operations.spec.ts`

- [ ] Replace user, course, unit, lesson, vocabulary, quiz, analytics, reports, user-progress, FSRS, and review-history stubs with authorized canonical Laravel APIs; move learner evidence to teacher scope and remove it from admin.
- [ ] Implement admin CRUD for user/basic roles and course/unit/lesson/vocabulary/quiz plus publish/archive, import runs/status, and non-sensitive service/sync status.
- [ ] Hide operations navigation from admins and enforce server authorization independently.
- [ ] Add service health, sync runs, aggregate usage, quotas, alert rules, audit, and high-risk role/scope flows for super admins.
- [ ] Add recent-password reauthentication dialogs without storing passwords.
- [ ] Provide loading/empty/forbidden/error states and accessible dense tables.
- [ ] Test separate importer/AI/voice/supervision/operations feature flags, disabled controls, admin versus super-admin navigation, and both browser critical paths.
- [ ] Run `cd admin-frontend && npm run lint && npm run build`.
- [ ] Commit `feat: build admin operations console`.

### Task 12: End-to-end verification and cleanup

**Files:**
- Modify: `.gitignore`
- Modify: `README.md`
- Modify: `docs/PRODUCTION_ENV.md`
- Modify: `docs/PROJECT_PLAN.md`
- Add/modify focused browser smoke tests if the existing harness supports them.

- [ ] Add `.superpowers/` to `.gitignore`; preserve the user's modified `docs/api_docs_lexilingo.md`.
- [ ] Run `php artisan test` and `./vendor/bin/pint --test`.
- [ ] Run frontend and admin lint/build.
- [ ] Run LexiLingo focused pytest, formatting, and existing contract tests.
- [ ] Start the local stack, migrate/seed, import one production page with the partner key, and verify learner, teacher, admin, and super-admin smoke flows.
- [ ] Run mandatory browser suites for learner session/fallback, teacher intervention, admin CRUD/import status, and super-admin operations/feature flags.
- [ ] Verify fallback by disabling TraceCAG/voice and confirming text learning plus FSRS still works.
- [ ] Review the final diff for secrets, raw audio, arbitrary proxy paths, hard-coded stubs, and unrelated changes.
- [ ] Request code and security review; fix all critical/important findings.
- [ ] Commit `docs: document supervised learning operations`.

## Completion Criteria

- All four roles see only approved data and actions.
- A learner can enroll, start a planned session, complete a quiz/flashcard/voice activity, receive TraceCAG or deterministic fallback feedback, rate recall, and see the persisted next FSRS due time.
- A deterministic rule creates one deduplicated alert; an assigned teacher can inspect evidence, intervene, and resolve it.
- Admin manages users/basic content; super admin alone manages AI, monitoring, quota, alert rules, audit, sync retry, and elevated roles/scopes.
- LexiLingo content imports locally with the partner key; AI/voice credentials remain server-only.
- Both Next.js builds, Laravel tests, LexiLingo focused tests, and browser smoke flows pass.
