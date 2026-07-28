# Google-only Admin Whitelist Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow admin-panel access only through Google OAuth for emails in environment-only admin or super-admin whitelists.

**Architecture:** Add a fail-closed whitelist service and identity-bound admin OAuth session, then enforce it on every admin API request before existing capability gates. Replace the admin password form with a Google redirect.

**Tech Stack:** Laravel 12, Socialite, PHPUnit, Next.js 16, TypeScript.

---

### Task 1: Whitelist configuration and identity storage

**Files:**
- Create: `config/admin_access.php`
- Create: migration adding Google subject/provider fields to users
- Modify: `app/Models/User.php`
- Create: `app/Support/AdminGoogleAccess.php`
- Test: `tests/Unit/AdminGoogleAccessTest.php`

- [ ] Test normalization, duplicates, precedence and malformed fail-closed values.
- [ ] Test absent config, config-cache semantics and list/secret redaction.
- [ ] Implement config-backed parsing without runtime `env()`.
- [ ] Store immutable Google subject/provider linkage; never store whitelist rows.
- [ ] Run tests and Pint; commit.

### Task 2: Dedicated admin Google OAuth

**Files:**
- Create: `app/Http/Controllers/AdminGoogleAuthController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AdminGoogleLoginTest.php`

- [ ] Test allowed admin/super-admin, denied/unverified/conflicting identity,
  session/CSRF rotation, fixed redirects and OAuth state failures.
- [ ] Add rate-limited bootstrap routes and identity-bound session marker.
- [ ] Synchronize the highest whitelist role transactionally.
- [ ] Add logout/denial marker cleanup.
- [ ] Clear marker/reauth on every initial attempt, denial, state failure,
  logout and identity switch; mark successful Google email verified.
- [ ] Run tests and Pint; commit.

### Task 3: Enforce every admin request

**Files:**
- Create: `app/Http/Middleware/RequireGoogleAdmin.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/spa.php`
- Test: `tests/Feature/GoogleAdminMiddlewareTest.php`

- [ ] Test password and learner-Google rejection, marker mismatch, revocation,
  demotion, admin/super-admin gates and route inventory.
- [ ] Wrap every `/api/v1/admin/*` route in the middleware.
- [ ] On revocation clear marker/reauth, demote role and invalidate admin session.
- [ ] Transactionally synchronize upgrades/downgrades for still-allowed users
  before capability authorization.
- [ ] Classify OAuth bootstrap routes separately and prove reauth, every admin
  API, operational mutation and capability-bearing route has both boundary and gate.
- [ ] Run focused and full auth tests; commit.

### Task 4: Recent Google reauthentication

**Files:**
- Modify: `app/Http/Controllers/AdminGoogleAuthController.php`
- Create: `app/Http/Middleware/RequireRecentGoogleAdmin.php`
- Modify: sensitive admin controllers/routes
- Test: `tests/Feature/AdminGoogleReauthTest.php`

- [ ] Test 15-minute freshness, marker preservation with freshness reset,
  OAuth state, fixed returns and rejection of a different subject/email.
- [ ] Add reauth start/callback with `prompt=select_account`, `max_age=0` and
  repeat current whitelist/role checks without upserting/switching users.
- [ ] Replace password validation on sensitive mutations with the freshness
  middleware and keep idempotency/audit behavior.
- [ ] Run focused tests and Pint; commit.

### Task 5: Google-only admin login UI

**Files:**
- Modify: `admin-frontend/src/app/login/page.tsx`
- Modify: `admin-frontend/src/lib/api.ts`
- Modify: `.env.example`
- Modify: sensitive admin forms that currently ask for a password

- [ ] Replace password form with a same-origin Google OAuth link.
- [ ] Show safe denied/cancelled/configuration messages.
- [ ] Redirect revoked/expired sessions to login.
- [ ] Replace password prompts with “Verify with Google” and resume only to a
  fixed allowlisted admin route.
- [ ] Document both whitelist variables as blank; configure the real address
  only in the uncommitted/server environment.
- [ ] Document config-cache rebuild and application/queue reload required for
  whitelist changes and revocations.
- [ ] Run admin lint/build and backend tests.
- [ ] Commit and verify `git diff --check`.
