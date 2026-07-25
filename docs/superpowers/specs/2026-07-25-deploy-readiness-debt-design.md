# Deploy Readiness and Technical Debt Design

## Goal

Make the Laravel API and both Next.js applications safe to deploy to Fly.io
and Vercel by enforcing all existing checks in CI, aligning production
configuration with runtime behavior, and removing known small pieces of debt.

This change does not implement missing product APIs or introduce new
infrastructure.

## Deployment architecture

- Laravel remains a single Fly.io web machine running Nginx and PHP-FPM.
- Learner and admin Next.js applications remain separate Vercel projects.
- GitHub Actions is the Fly quality gate. Fly deployment may run only after
  backend, learner frontend, and admin frontend checks pass.
- Vercel continues using its native Git integration. Production deploys must
  track `main`; repository branch protection must require the three GitHub
  checks before merge. Preview deployments may run before those checks finish.
  Direct pushes to `main` must be disabled in repository settings. These
  external settings are documented but cannot be enforced by repository code.

## CI checks

Split checks into independent jobs so failures are easy to identify:

1. Backend: Composer install, Pint, fresh migration/seed/rollback/migrate, and
   PHPUnit against MySQL.
2. Learner frontend: frozen pnpm install, ESLint,
   `node scripts/validate-lexilingo-schema.mjs`, and production build.
3. Admin frontend: `npm ci`, ESLint, and production build.

The Fly deploy job depends on all three and retains all existing guards: it runs
only for a successful push to `main`, uses the GitHub `production` environment,
and reads `FLY_API_TOKEN` and `FLY_APP` from repository secret/variable
configuration. Dependency caches may use setup-action integrations; no custom
cache scripts are needed.

## Production configuration

`fly.toml` contains non-secret values that are invariant for the deployment:

- secure session cookie and `lax` same-site policy;
- `QUEUE_CONNECTION=sync` until the repository contains an actual queued job
  and a separately monitored worker;
- production log level;
- public application/frontend URLs only when their real values are known.

Credentials, application key, database/Redis URLs, mail credentials, and
provider secrets remain Fly secrets. Documentation must distinguish required
operator-supplied values from values committed in `fly.toml`.

## Health checks

Keep `/health` as the Fly readiness endpoint, but make it verify the
dependencies needed for authenticated requests:

- execute a minimal database connection check;
- execute Redis `PING` only when Redis backs cache, sessions, or queues;
- return HTTP 200 with `{"status":"ok"}` when dependencies are available;
- return HTTP 503 from `/health` as
  `{"status":"error","checks":{"database":"down","redis":"down"}}`, including
  only failed components;
- return the same structure plus `"version":"v1"` from `/api/v1/health`;
- log the underlying exception server-side without exposing credentials or
  connection details.

Successful `/health` retains `{"status":"ok"}` and successful
`/api/v1/health` retains `{"status":"ok","version":"v1"}`. Both endpoints use
the same service/check rather than duplicating logic. Tests cover success and
each dependency failure without requiring real external infrastructure.
`docs/DEVELOPMENT_WORKFLOW.md` and production documentation must describe this
as readiness, not process-only liveness.

## Admin lint remediation

Fix the current ESLint errors without broad page rewrites:

- make effect-triggered loaders return promises without synchronous wrapper
  calls that violate the React rule;
- replace explicit `any` with narrow response types;
- escape JSX text where required;
- remove unused variables/imports.

Existing request behavior and UI output remain unchanged.

## Debt cleanup

- Remove the global MutationObserver that continuously removes attributes added
  by browser extensions. `suppressHydrationWarning` remains the native React
  boundary.
- Remove remote CSS font imports from application source and use the existing
  system-font fallbacks, so production builds do not fetch fonts from Google.
- Update README statements that still describe implemented authentication as
  missing.
- Do not create shared frontend packages merely to deduplicate two small API
  clients; the applications deploy independently and currently have different
  contracts.

## Verification

Required before completion:

- `./vendor/bin/pint --test`
- `php artisan test`
- learner `pnpm lint` and `pnpm build`
- learner `node scripts/validate-lexilingo-schema.mjs`
- admin `npm run lint` and `npm run build`
- `docker compose config --quiet`
- workflow syntax inspection and confirmation that Fly deploy depends on all
  check jobs

Network- or sandbox-specific failures must be distinguished from source
failures and rerun in an environment with the needed permission when required.

## Explicitly deferred

- Missing learner/admin business APIs.
- Queue worker, scheduler machine, staging environment, and object storage.
- Automated database backup provisioning, which must be configured and
  verified against the production Fly account separately.
- Cross-application shared TypeScript package.
