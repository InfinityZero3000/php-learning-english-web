# Auth Placeholders and SQL Injection Audit Implementation Plan

> **For agentic workers:** Execute this plan in the current worktree with focused checks after each task.

**Goal:** Add contextual placeholders to all learner auth fields and produce evidence that auth database access is parameterized.

**Architecture:** Keep the existing shared `Field` component and pass explicit placeholder props at each auth call site. Audit existing Laravel auth routes, request validation, controllers, and services without adding abstractions; only patch a shared unsafe query boundary if one exists.

**Tech Stack:** Next.js, React, TypeScript, Laravel, Eloquent/query builder, Vitest, PHPUnit, Vercel.

---

## Chunk 1: Audit and placeholders

### Task 1: Audit authentication query boundaries

**Files:**
- Inspect `routes/api.php`, `routes/spa.php`, `routes/web.php`, `app/Http/Controllers`, `app/Http/Requests`, `app/Services`, and auth tests.
- Record findings in `docs/security/auth-sql-injection-audit.md`.

- [ ] Search for raw SQL and interpolated user input with `rg`.
- [ ] Trace login, register, forgot-password, reset-password, verify-email, and OAuth callbacks to DB calls.
- [ ] Add an audit record listing inspected boundaries, search patterns/results, and whether remediation is required.
- [ ] If unsafe interpolation is found, patch the shared query boundary and add its smallest regression test before proceeding.

### Task 2: Add explicit auth placeholders

**Files:**
- Modify `frontend/src/features/auth/form-support.tsx` only if needed to preserve placeholder props.
- Modify `frontend/src/features/auth/login-page.tsx`.
- Modify `frontend/src/features/auth/register-page.tsx`.
- Modify `frontend/src/features/auth/forgot-password-page.tsx`.
- Modify `frontend/src/features/auth/reset-password-page.tsx`.

- [ ] Add `name@example.com` to email fields.
- [ ] Add contextual name/password/confirmation placeholders.
- [ ] Preserve labels, types, autocomplete, required validation, and existing layout.

### Task 3: Verify focused behavior

- [ ] Add assertions covering every affected email, name, password, and confirmation field, then run the frontend auth tests.
- [ ] Run `pnpm lint` and `pnpm build` from `frontend`.
- [ ] If backend code changed, run Pint and the full PHPUnit suite.
- [ ] Commit the audit and UI changes.

## Chunk 2: Production deployment

### Task 4: Deploy learner frontend

- [ ] Deploy `frontend` with `vercel deploy frontend --prod -y`.
- [ ] Inspect deployment until status is `Ready`.
- [ ] Confirm aliases include `https://linguist-nova.vercel.app`.
- [ ] If backend code changed, push/merge to `main` to trigger the existing `Deploy to Fly.io` workflow, inspect its run to `success`, verify `https://linguist.fly.dev/api/v1/health` returns `200`, and smoke-test affected auth endpoints with no real credentials and no credential logging.
