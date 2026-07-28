# Supervised Learning Completion Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete every approved learner, teacher, admin and super-admin workflow with correct session progression, stable APIs and resilient interfaces.

**Architecture:** Keep PHP as the owner of identity, learning state, authorization and audit while LexiLingo remains a provider. Extend existing controllers/services and React pages directly; reuse current UI components and API clients without introducing another state or component framework.

**Tech Stack:** Laravel 12, PHPUnit, Next.js 16, React 19, TypeScript, Tailwind CSS, existing LexiLingo HTTP integrations.

---

## Chunk 1: Learning correctness

### Task 1: Session activity progression

**Files:**
- Modify: `app/Services/LearningSessionService.php`
- Modify: `app/Http/Controllers/Api/V1/LearningSessionController.php`
- Modify: `frontend/src/app/session/[id]/page.tsx`
- Modify: `frontend/src/app/session/[id]/summary/page.tsx`
- Test: `tests/Feature/Api/V1/LearningSessionApiTest.php`

- [ ] Add a failing test proving a lesson with two vocabulary items cannot complete after one answer.
- [ ] Select the next unanswered vocabulary from answer events in the current session.
- [ ] Require all planned lesson vocabulary activities before completion.
- [ ] Return session progress and persisted summary fields.
- [ ] Add explicit loading, error, retry, next-activity and real summary UI.
- [ ] Run focused tests and commit.

### Task 2: Supervision lifecycle

**Files:**
- Modify: `app/Services/LearningSessionService.php`
- Modify: `app/Services/SupervisionAlertService.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Api/V1/LearningSessionApiTest.php`
- Test: `tests/Feature/Api/V1/TeacherApiTest.php`

- [ ] Add tests for prerequisite eligibility, remediation target and scheduled nightly evaluation.
- [ ] Enforce lesson prerequisites when planning/starting course work.
- [ ] Return actionable remediation targets instead of redirecting generically to due review.
- [ ] Schedule `supervision:evaluate` nightly with overlap protection.
- [ ] Run focused tests and commit.

## Chunk 2: Teacher supervision

### Task 3: Teacher API validation

**Files:**
- Modify: `app/Http/Controllers/Api/V1/TeacherController.php`
- Modify: `frontend/src/lib/api.ts`
- Test: `tests/Feature/Api/V1/TeacherApiTest.php`

- [ ] Test and enforce exactly one assignment target.
- [ ] Add typed client methods for detail, progress, evidence, assignment creation and intervention notes.
- [ ] Ensure mutation errors return actionable validation messages.
- [ ] Run focused tests and commit.

### Task 4: Teacher workspace

**Files:**
- Modify: `frontend/src/app/teacher/page.tsx`
- Create: `frontend/src/app/teacher/learners/[id]/page.tsx`
- Create: `frontend/src/components/teacher/assignment-form.tsx`
- Test: production frontend build

- [ ] Add loading/error/retry and assignment list states.
- [ ] Add learner detail with progress, evidence and alert history.
- [ ] Add lesson/vocabulary assignment form and intervention note form.
- [ ] Require evidence review before resolving an alert.
- [ ] Verify keyboard, labels, mobile layout and build.

## Chunk 3: Operations

### Task 5: Complete operations API

**Files:**
- Modify: `app/Http/Controllers/Api/V1/Admin/OperationsController.php`
- Modify: `routes/spa.php`
- Modify: `docs/openapi/laravel-v1.yaml`
- Test: `tests/Feature/Api/V1/OperationsApiTest.php`

- [ ] Test super-admin allow/admin deny for every operations endpoint.
- [ ] Implement contract hashes, aggregate usage, active quota replacement, alert-rule version updates and paginated audits.
- [ ] Align runtime paths/methods with OpenAPI.
- [ ] Require recent password and request IDs for sensitive mutations.
- [ ] Run tests and contract lint.

### Task 6: Super-admin control room

**Files:**
- Modify: `admin-frontend/src/components/AdminLayout.tsx`
- Modify: `admin-frontend/src/components/Sidebar.tsx`
- Modify: `admin-frontend/src/lib/api.ts`
- Modify: `admin-frontend/src/app/operations/page.tsx`
- Test: production admin build

- [ ] Permit both admin roles in the shared shell while hiding Operations from ordinary admins.
- [ ] Add role-specific authorization for the Operations page.
- [ ] Add service, usage, contract, quota, rule and audit panels.
- [ ] Add password-confirmed quota/rule forms with validation and request IDs.
- [ ] Add loading, empty, error and retry states; verify build.

## Chunk 4: Learner completion

### Task 7: Course and progress surfaces

**Files:**
- Modify: `frontend/src/lib/api.ts`
- Modify: `frontend/src/app/courses/page.tsx`
- Create: `frontend/src/app/courses/[id]/page.tsx`
- Modify: `frontend/src/features/progress/progress-page.tsx`

- [ ] Add catalog detail/lesson and supervised progress client methods.
- [ ] Show units/lessons, prerequisites, enrollment status and progress.
- [ ] Replace the legacy-only progress view with supervised course/session/FSRS metrics while retaining useful legacy vocabulary metrics.
- [ ] Add resilient loading/error/empty states.

### Task 8: AI learning controls

**Files:**
- Modify: `frontend/src/lib/api.ts`
- Modify: `frontend/src/app/session/[id]/page.tsx`
- Test: production frontend build

- [ ] Add typed translate, STT, pronunciation and TTS methods.
- [ ] Wire listen, text fallback, recording/upload and pronunciation feedback into the session.
- [ ] Treat provider failure as a non-blocking degraded state.
- [ ] Ensure raw audio is not persisted by PHP.

### Task 9: Today, review and summary hardening

**Files:**
- Modify: `frontend/src/features/learning/today-page.tsx`
- Modify: `frontend/src/app/review/page.tsx`
- Modify: `frontend/src/app/session/[id]/summary/page.tsx`

- [ ] Make every Today item actionable, including course and remediation items.
- [ ] Add pronunciation tasks when evidence warrants them.
- [ ] Separate loading, empty and failed states in FSRS review.
- [ ] Show predicted intervals and keyboard-accessible FSRS controls.

## Chunk 5: Verification

### Task 10: Contract and regression verification

**Files:**
- Modify as required by failures only.

- [ ] Run `php artisan test`.
- [ ] Run `./vendor/bin/pint --test`.
- [ ] Run Redocly OpenAPI lint.
- [ ] Run learner lint, contract validation and production build.
- [ ] Run admin lint and production build.
- [ ] Run role-based HTTP smoke for learner, teacher, admin and super-admin.
- [ ] Review accessibility and console/API errors.
- [ ] Request final code review and fix all Critical/Important findings.
- [ ] Preserve `docs/api_docs_lexilingo.md` and commit only implementation-owned files.
