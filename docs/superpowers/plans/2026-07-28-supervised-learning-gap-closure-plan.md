# Supervised Learning Gap Closure Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the remaining learner, teacher, super-admin, and catalog interface gaps against the approved supervised-learning design.

**Architecture:** Laravel remains the single business API and exposes narrowly scoped `/api/v1` endpoints. Existing learner/admin Next.js applications consume those endpoints with their current session and CSRF helpers; no new dependency or parallel data model is introduced.

**Tech Stack:** Laravel 12, PHPUnit, Next.js 16, React 19, TypeScript.

---

## Chunk 1: Assignment lifecycle

### Task 1: Learner assignment API and page

**Files:**
- Modify: `app/Http/Controllers/Api/V1/LearningSessionController.php`
- Modify: `routes/spa.php`
- Modify: `frontend/src/lib/api.ts`
- Create: `frontend/src/app/assignments/page.tsx`
- Modify: `frontend/src/components/layout/app-shell.tsx`
- Test: `tests/Feature/Api/V1/LearningSessionApiTest.php`

- [x] Add an owned assignment listing endpoint with lesson/vocabulary/teacher data.
- [x] Add an assignment page with pending/completed states and an actionable start button.
- [x] Add learner navigation and loading/error/empty states.
- [x] Test ownership and response contract.

### Task 2: Teacher assignment updates

**Files:**
- Modify: `app/Http/Controllers/Api/V1/TeacherController.php`
- Modify: `routes/spa.php`
- Modify: `frontend/src/lib/api.ts`
- Modify: `frontend/src/app/teacher/page.tsx`
- Test: `tests/Feature/Api/V1/TeacherApiTest.php`

- [x] Add teacher-scoped assignment update with bounded status transitions.
- [x] Add teacher UI actions for pending, in-progress, completed, and cancelled states.
- [x] Test cross-teacher denial and valid transitions.

## Chunk 2: Super-admin control surfaces

### Task 3: User, role, and teacher-scope APIs

**Files:**
- Create: `app/Http/Controllers/Api/V1/Admin/UserController.php`
- Modify: `routes/spa.php`
- Modify: `admin-frontend/src/lib/api.ts`
- Modify: `admin-frontend/src/app/users/page.tsx`
- Modify: `admin-frontend/src/app/roles/page.tsx`
- Test: `tests/Feature/Api/V1/AdminUserApiTest.php`

- [x] Implement paginated user listing and details using real Laravel role slugs.
- [x] Implement recent-password role mutation through the existing policy.
- [x] Implement super-admin teacher-to-learner assignment management.
- [x] Replace stale USER/MODERATOR/ADMIN UI assumptions with learner/teacher/admin/super_admin.
- [x] Test admin versus super-admin capabilities and last-super-admin protection.

### Task 4: Real course administration

**Files:**
- Create: `app/Http/Controllers/Api/V1/Admin/CatalogController.php`
- Modify: `routes/spa.php`
- Modify: `admin-frontend/src/lib/api.ts`
- Modify: `admin-frontend/src/app/courses/page.tsx`
- Test: `tests/Feature/Api/V1/AdminCatalogApiTest.php`

- [x] Implement course list/create/update using the existing Course model.
- [x] Replace course stubs and “coming soon” action with real API-backed forms.
- [x] Preserve published/draft behavior and validate unique slug.
- [x] Test admin authorization and CRUD contract.

## Chunk 3: Verification

### Task 5: Contract and end-to-end verification

**Files:**
- Modify: `docs/openapi/laravel-v1.yaml`

- [x] Align OpenAPI with implemented assignment, admin user, teacher-scope, and course endpoints.
- [x] Run Pint, full PHPUnit, both frontend lint/build commands, and OpenAPI lint.
- [x] Run browser QA for learner, teacher, admin, and super-admin paths; use HTTP integration tests if the connected browser runtime is unavailable.
- [x] Request code review and fix all critical/important findings.
