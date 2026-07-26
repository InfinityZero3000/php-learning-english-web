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

- [x] Write isolated tests for healthy responses plus database-only, Redis-only,
  and combined failures. Assert HTTP 503, failed-components-only JSON, and the
  `version` field on the API endpoint without using external services.
- [x] Run the focused test and confirm failure.
- [x] Add one health service that checks DB, checks Redis whenever cache,
  session, or queue is configured to use Redis, logs underlying exceptions,
  and exposes no connection details.
- [x] Route both health endpoints through the service while preserving their success contracts.
- [x] Run the focused test and all backend tests.

### Task 2: Align Fly runtime configuration

**Files:**
- Modify: `fly.toml`
- Modify: `docs/PRODUCTION_ENV.md`
- Modify: `docs/DEVELOPMENT_WORKFLOW.md`
- Modify: `README.md`

- [x] Change production queue to `sync` until a worker exists.
- [x] Add secure cookie, same-site, queue, and log-level values. Keep unknown
  public URLs operator-supplied and documented rather than committing
  placeholders.
- [x] Document readiness semantics and required Vercel/GitHub branch settings.
- [x] Correct stale README feature statements.
- [x] Validate `docker compose config --quiet`.

## Chunk 2: Frontend quality

### Task 3: Repair admin lint errors

**Files:**
- Modify only reported files under `admin-frontend/src`

- [x] Run ESLint and capture the exact current failures.
- [x] Fix loader effects with minimal rule-compliant calls.
- [x] Replace explicit `any`, escape JSX text, and remove unused declarations.
- [x] Run admin ESLint until clean.
- [x] Run the admin production build.

### Task 4: Remove targeted frontend debt

**Files:**
- Modify: `admin-frontend/src/app/layout.tsx`
- Modify: `admin-frontend/src/app/globals.css`
- Modify: `frontend/src/app/globals.css`

- [x] Remove the browser-extension MutationObserver workaround.
- [x] Use system font fallbacks for text; retain the Material Symbols stylesheet because replacing the icon set is outside scope.
- [x] Ensure neither build performs a build-time font download.
- [x] Run both frontend linters and builds.

## Chunk 3: CI/CD gates

### Task 5: Gate Fly deploy on all applications

**Files:**
- Modify: `.github/workflows/tests.yml`
- Modify: `frontend/package.json` only if a named schema-check script is needed

- [x] Split backend checks into a named backend job.
- [x] Add learner job using pinned pnpm/frozen lockfile, lint, schema fixtures, and build.
- [x] Add admin job using `npm ci`, lint, and build.
- [x] Make deploy require all three jobs while preserving push-to-main and production-environment guards.
- [x] Inspect workflow syntax and verify exact runtime/install commands,
  `needs: [backend, learner, admin]`, push-to-`main`, and `production`
  environment guards.

## Chunk 4: Final verification

### Task 6: Execute the full release gate

- [x] Run `./vendor/bin/pint --test`.
- [x] Run `php artisan test`.
- [x] Run learner schema validation, ESLint, and production build.
- [x] Run admin ESLint and production build.
- [x] Run `docker compose config --quiet`.
- [x] Review the final diff for unrelated changes and report external settings still required.
