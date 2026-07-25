# Deploy Readiness and Technical Debt Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make backend and both Next.js applications pass deploy quality gates and align Fly production behavior with documented configuration.

**Architecture:** Keep the existing Fly web machine and two Vercel projects. Add a shared Laravel readiness service for both health routes, make GitHub Actions gate Fly deployment on three independent check jobs, and fix only debt that currently blocks or weakens deployment.

**Tech Stack:** Laravel 13/PHPUnit, Next.js 16/React 19/ESLint, GitHub Actions, Fly.io, Vercel.

---

## Chunk 1: Runtime readiness

### Task 1: Shared dependency health check

**Files:**
- Create: `app/Support/HealthCheck.php`
- Modify: `routes/web.php`
- Modify: `routes/spa.php`
- Modify: `tests/Feature/Api/V1/HealthApiTest.php`

- [ ] Write isolated tests for healthy responses plus database-only, Redis-only,
  and combined failures. Assert HTTP 503, failed-components-only JSON, and the
  `version` field on the API endpoint without using external services.
- [ ] Run the focused test and confirm failure.
- [ ] Add one health service that checks DB, checks Redis whenever cache,
  session, or queue is configured to use Redis, logs underlying exceptions,
  and exposes no connection details.
- [ ] Route both health endpoints through the service while preserving their success contracts.
- [ ] Run the focused test and all backend tests.

### Task 2: Align Fly runtime configuration

**Files:**
- Modify: `fly.toml`
- Modify: `docs/PRODUCTION_ENV.md`
- Modify: `docs/DEVELOPMENT_WORKFLOW.md`
- Modify: `README.md`

- [ ] Change production queue to `sync` until a worker exists.
- [ ] Add secure cookie, same-site, queue, and log-level values. Keep unknown
  public URLs operator-supplied and documented rather than committing
  placeholders.
- [ ] Document readiness semantics and required Vercel/GitHub branch settings.
- [ ] Correct stale README feature statements.
- [ ] Validate `docker compose config --quiet`.

## Chunk 2: Frontend quality

### Task 3: Repair admin lint errors

**Files:**
- Modify only reported files under `admin-frontend/src`

- [ ] Run ESLint and capture the exact current failures.
- [ ] Fix loader effects with minimal rule-compliant calls.
- [ ] Replace explicit `any`, escape JSX text, and remove unused declarations.
- [ ] Run admin ESLint until clean.
- [ ] Run the admin production build.

### Task 4: Remove targeted frontend debt

**Files:**
- Modify: `admin-frontend/src/app/layout.tsx`
- Modify: `admin-frontend/src/app/globals.css`
- Modify: `frontend/src/app/globals.css`

- [ ] Remove the browser-extension MutationObserver workaround.
- [ ] Use system font fallbacks for text; retain the Material Symbols stylesheet because replacing the icon set is outside scope.
- [ ] Ensure neither build performs a build-time font download.
- [ ] Run both frontend linters and builds.

## Chunk 3: CI/CD gates

### Task 5: Gate Fly deploy on all applications

**Files:**
- Modify: `.github/workflows/tests.yml`
- Modify: `frontend/package.json` only if a named schema-check script is needed

- [ ] Split backend checks into a named backend job.
- [ ] Add learner job using pinned pnpm/frozen lockfile, lint, schema fixtures, and build.
- [ ] Add admin job using `npm ci`, lint, and build.
- [ ] Make deploy require all three jobs while preserving push-to-main and production-environment guards.
- [ ] Inspect workflow syntax and verify exact runtime/install commands,
  `needs: [backend, learner, admin]`, push-to-`main`, and `production`
  environment guards.

## Chunk 4: Final verification

### Task 6: Execute the full release gate

- [ ] Run `./vendor/bin/pint --test`.
- [ ] Run `php artisan test`.
- [ ] Run learner schema validation, ESLint, and production build.
- [ ] Run admin ESLint and production build.
- [ ] Run `docker compose config --quiet`.
- [ ] Review the final diff for unrelated changes and report external settings still required.
