# Learner Public Access Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let guests browse public learner content while redirecting protected user pages and gating state-changing actions behind login.

**Architecture:** Add one auth context owned by `AppShell`, backed by the existing Laravel session client. Pages and widgets consume that state to avoid user-specific requests for guests; protected route prefixes redirect with a validated same-origin return URL.

**Tech Stack:** Next.js 16, React 19, TypeScript, Vitest, Testing Library, Laravel session API

**Approved design:** `docs/superpowers/specs/2026-07-26-learner-public-access-design.md`

---

## Chunk 1: Session state and route policy

### Task 1: Shared auth state and safe return URLs

**Files:**
- Create: `frontend/src/features/auth/auth-context.tsx`
- Create: `frontend/src/features/auth/route-policy.ts`
- Test: `frontend/src/features/auth/route-policy.test.ts`

- [x] Write helper tests for `/profile`, nested `/profile/x`, lookalike `/profiled`, query preservation, `//evil`, absolute URLs, raw/decoded backslashes, and fallback `/`.
- [x] Run `cd frontend && pnpm vitest run src/features/auth/route-policy.test.ts` and require failure.
- [x] Implement `AuthProvider`, `useAuth()`, `refreshUser()`, logout state clearing, protected route policy, and safe return URL helpers.
- [x] Run `cd frontend && pnpm vitest run src/features/auth/route-policy.test.ts` and require pass.

### Task 2: Use the shared state in the shell and login

**Files:**
- Modify: `frontend/src/components/layout/app-shell.tsx`
- Modify: `frontend/src/features/auth/login-page.tsx`
- Modify: `frontend/src/features/auth/login-page.test.tsx`
- Create: `frontend/src/components/layout/app-shell.test.tsx`

- [x] Write shell integration tests for guest public rendering, protected redirect-before-child-mount, unavailable public rendering, unavailable protected retry, authenticated rendering, post-login refresh, and post-logout guest state.
- [x] Remove the unconditional guest redirect and the pathname-triggered repeated `auth.me()` call.
- [x] Redirect guests only from protected routes using encoded pathname and query.
- [x] Await `refreshUser()` after login, then navigate to a validated `next` or `/`.
- [x] Make protected retry call `refreshUser()`; keep public children mounted when auth is unavailable.
- [x] Run `cd frontend && pnpm vitest run src/components/layout/app-shell.test.tsx src/features/auth/login-page.test.tsx` and require pass for login-to-authenticated-shell and malicious `next` cases.

## Chunk 2: Public and user-owned data

### Task 3: Stop guest user-data requests

**Files:**
- Modify: `frontend/src/components/layout/notifications.tsx`
- Modify: `frontend/src/components/layout/app-shell.tsx`
- Modify: `frontend/src/features/dashboard/dashboard-page.tsx`
- Modify: `frontend/src/features/flashcards/flashcards-page.tsx`
- Modify: `frontend/src/features/vocabulary/vocabulary-page.tsx`
- Modify: `frontend/src/features/quiz/quiz-page.tsx`
- Modify: `frontend/src/lib/api.ts`
- Create: `frontend/src/components/layout/notifications.test.tsx`
- Create: `frontend/src/features/dashboard/dashboard-page.test.tsx`
- Create: `frontend/src/features/flashcards/flashcards-page.test.tsx`
- Create: `frontend/src/features/quiz/quiz-page.test.tsx`

- [x] Point guest catalog loading to the existing public `/api/v1/vocabulary` contract; do not call nonexistent legacy `/api/words`, `/api/topics`, or `/api/flashcards` routes.
- [x] Derive guest word count/categories/display cards from the returned public vocabulary page where needed.
- [x] Derive guest quiz categories/setup data from public vocabulary and remove its calls to nonexistent `/api/words/categories` and `/api/topics`.
- [x] Load progress, FSRS, streak, review queue, notifications, and due words only for authenticated users.
- [x] Render login calls to action instead of user metrics/actions for guests.
- [x] Run `cd frontend && pnpm vitest run src/components/layout/notifications.test.tsx src/features/dashboard/dashboard-page.test.tsx src/features/flashcards/flashcards-page.test.tsx src/features/quiz/quiz-page.test.tsx` and prove zero guest calls to progress, FSRS, streak, notifications, review queue, or due words.

### Task 4: Gate persistent actions on public pages

**Files:**
- Modify: `frontend/src/features/quiz/quiz-page.tsx`
- Modify: `frontend/src/features/flashcards/flashcards-page.tsx`
- Modify: `frontend/src/features/vocabulary/vocabulary-page.tsx`
- Create: `frontend/src/features/quiz/quiz-page.test.tsx`
- Create: `frontend/src/features/vocabulary/vocabulary-page.test.tsx`

- [x] Redirect quiz start, review/save/import, bookmark, mutation, and enrichment actions to safe login return URLs when unauthenticated.
- [x] Keep public catalog/filter/search requests available.
- [x] Run `cd frontend && pnpm vitest run src/features/quiz/quiz-page.test.tsx src/features/flashcards/flashcards-page.test.tsx src/features/vocabulary/vocabulary-page.test.tsx` and verify protected mutation methods are never called before redirect.

### Task 5: Preserve backend authorization boundaries

**Files:**
- Modify: `tests/Feature/Api/V1/VocabularyApiTest.php`
- Test: `tests/Feature/Api/V1/AuthApiTest.php`
- Test: `tests/Feature/Api/V1/ProfileApiTest.php`

- [x] Assert guest `GET /api/v1/vocabulary` returns `200`, pagination metadata, and no per-user progress/review/bookmark fields.
- [x] Assert guest `/api/v1/auth/me` and profile mutation endpoints return `401` using the existing Auth/Profile API tests.
- [x] Do not add or accept `404` assertions for progress, quiz, bookmark, or import APIs; those endpoints remain deferred to their assigned feature issues and the frontend must not call them for guests.
- [x] Run `php artisan test tests/Feature/Api/V1/VocabularyApiTest.php tests/Feature/Api/V1/AuthApiTest.php tests/Feature/Api/V1/ProfileApiTest.php` and require pass.

## Chunk 3: Verification

### Task 6: Full quality gate

- [x] Run `cd frontend && pnpm test && pnpm lint && pnpm build`.
- [x] Run `php artisan test` and `./vendor/bin/pint --test`.
- [x] Create a disposable DB with `SMOKE_DB=$(mktemp /tmp/learner-public-smoke.XXXXXX.sqlite)` then run `DB_CONNECTION=sqlite DB_DATABASE="$SMOKE_DB" php artisan migrate:fresh --seed --force`; start Laravel from the repo root with the same DB variables on `127.0.0.1:18080`, start Next from `frontend/` using `LARAVEL_API_ORIGIN=http://127.0.0.1:18080 pnpm dev --hostname 127.0.0.1 --port 13000`, and use `curl --fail` to verify public pages, `/api/v1/vocabulary`, and `/api/v1/health` return `200`. Rely on JS component tests for final protected-route URLs because redirect is client-side; stop both processes and delete only `$SMOKE_DB` after the check.
- [x] Review the diff for unrelated changes and record exact gate results in the PR description.
