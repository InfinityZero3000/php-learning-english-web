# Next.js Auth Suite Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a polished Next.js authentication suite with working email registration/reset and Google/Facebook OAuth while removing learner-facing Blade auth from production.

**Architecture:** Next.js owns every learner auth screen through a shared responsive split-screen layout. Laravel exposes JSON/session/OAuth endpoints behind the existing Vercel rewrite; OAuth callbacks return through Vercel to preserve host-only session state and finish on a small Next.js bootstrap route.

**Tech Stack:** Next.js 16, React 19, TypeScript, Tailwind CSS, Vitest/Testing Library, Laravel 12, Socialite, PHPUnit, Fly.io, Vercel.

---

## Chunk 1: Laravel authentication boundary

### Task 1: Add safe OAuth routing and session bootstrap redirects

**Files:**
- Create: `app/Http/Controllers/Api/V1/OAuthController.php`
- Create: `app/Support/SafeFrontendPath.php`
- Modify: `routes/spa.php`
- Test: `tests/Feature/Api/V1/OAuthApiTest.php`

- [ ] **Step 1: Write failing path-validation tests**

Cover `/`, `/profile`, and query strings as accepted values; cover absolute URLs, `//evil.test`, auth routes, backslashes, and control characters as rejected values that fall back to `/`.

- [ ] **Step 2: Run the focused test and verify failure**

Run: `php artisan test tests/Feature/Api/V1/OAuthApiTest.php`

Expected: FAIL because the OAuth API routes and `SafeFrontendPath` do not exist.

- [ ] **Step 3: Implement the shared safe-path helper**

Implement `SafeFrontendPath::normalize(?string $path): string` as the single allowlist boundary. It returns `/` unless the value starts with exactly one `/`, contains no control characters or backslashes, and does not target `/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`, or `/auth/callback` (including nested segments).

- [ ] **Step 4: Write failing OAuth entry tests**

Assert only `google` and `facebook` are accepted, `auth.oauth_next` is stored in session, and Socialite redirects with the configured callback URI.

- [ ] **Step 5: Implement and verify the OAuth entry route**

Add:

```php
Route::get('/auth/oauth/{provider}', [OAuthController::class, 'redirect']);
```

Implement only the entry behavior first: provider allowlist, normalized `next` session value, and stateful Socialite redirect. Run the focused entry tests and expect PASS.

- [ ] **Step 6: Write failing callback success-policy tests**

Cover new learner creation, existing learner login, existing non-learner conflict without account mutation/authentication, session regeneration, one-time destination consumption, and success destination encoded exactly once.

- [ ] **Step 7: Implement the callback success policy**

Add:

```php
Route::get('/auth/oauth/{provider}/callback', [OAuthController::class, 'callback']);
```

Implement only the successful callback and role-conflict path:

- use stateful Socialite,
- create only learner accounts,
- reject an existing non-learner email with `role_conflict`,
- regenerate the session after login,
- consume `auth.oauth_next` once,
- redirect success to `{FRONTEND_URL}/auth/callback?next=...`.

Run the callback success-policy tests and expect PASS.

- [ ] **Step 8: Write failing callback failure-mapping tests**

Cover cancellation, invalid state, missing email, provider failure, and role conflict. Assert every failure consumes `auth.oauth_next`, does not authenticate or mutate a user, redirects to the exact stable code on `{FRONTEND_URL}/login?oauth_error=...`, and logs no provider token/payload.

- [ ] **Step 9: Implement callback error mapping**

Map only documented failures, clear destination state, and log provider plus exception class without credentials. Run all OAuth API tests and expect PASS.

- [ ] **Step 10: Run the complete focused test**

Use driver mocks consistent with the installed Socialite version.

Run: `php artisan test tests/Feature/Api/V1/OAuthApiTest.php`

Expected: PASS.

- [ ] **Step 11: Run style checks and commit**

Run: `./vendor/bin/pint --test app/Http/Controllers/Api/V1/OAuthController.php app/Support/SafeFrontendPath.php routes/spa.php tests/Feature/Api/V1/OAuthApiTest.php`

Expected: PASS.

Commit: `feat: add learner OAuth API flow`

### Task 2: Generate frontend password-reset and verification URLs

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Http/Controllers/Api/V1/EmailVerificationController.php`
- Test: `tests/Feature/Api/V1/PasswordApiTest.php`
- Test: `tests/Feature/Api/V1/EmailVerificationApiTest.php`

- [ ] **Step 1: Add regression tests for the existing reset-link hook**

The project already configures `ResetPassword::createUrlUsing()` in `AppServiceProvider`. Assert the notification URL host/path is `{FRONTEND_URL}/reset-password` and its query contains the generated token plus URL-encoded email. The test should pass and lock this behavior against regression; do not add a second notification mechanism.

- [ ] **Step 2: Harden the existing URL builder only if the regression test exposes an edge case**

Keep `ResetPassword::createUrlUsing()` as the single integration point. Prefer `http_build_query` if current encoding fails the test. Do not override the User notification method and do not log or persist the token.

- [ ] **Step 3: Verify email confirmation redirects to Next.js**

Test the existing signed verification endpoint and resend endpoint. Success must redirect to `{FRONTEND_URL}/login?verified=1`; invalid/expired signatures remain rejected.

- [ ] **Step 4: Run focused tests and commit**

Run: `php artisan test tests/Feature/Api/V1/PasswordApiTest.php tests/Feature/Api/V1/EmailVerificationApiTest.php`

Expected: PASS.

Run: `./vendor/bin/pint --test app/Providers/AppServiceProvider.php app/Http/Controllers/Api/V1/EmailVerificationController.php tests/Feature/Api/V1/PasswordApiTest.php tests/Feature/Api/V1/EmailVerificationApiTest.php`

Expected: PASS.

Commit: `feat: route account emails to Next.js`

### Task 3: Retire learner Blade authentication routes

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/AuthController.php`
- Modify: `app/Http/Controllers/ForgotPasswordController.php`
- Modify: `app/Http/Controllers/EmailVerificationController.php`
- Delete: `app/Http/Controllers/SocialController.php`
- Delete when unreferenced: `resources/views/auth/login.blade.php`
- Delete when unreferenced: `resources/views/auth/register.blade.php`
- Delete when unreferenced: `resources/views/auth/forgot-password.blade.php`
- Delete when unreferenced: `resources/views/auth/reset-password.blade.php`
- Delete when unreferenced: `resources/views/auth/verify-notice.blade.php`
- Modify: `tests/Feature/SocialLoginTest.php`
- Test: `tests/Feature/ProductionFrontendRedirectTest.php`

- [ ] **Step 1: Write production redirect tests**

Cover `/`, `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/verify-email`, `/profile`, `/progress`, and `/words`. Assert `/api/v1/health` returns 200; a valid signed `/api/v1/auth/email/verify/{id}/{hash}` request reaches the API controller and redirects to `{FRONTEND_URL}/login?verified=1`; mocked `/api/v1/auth/oauth/google/callback` reaches the new OAuth controller; and `/admin` retains its existing authentication redirect rather than a learner frontend redirect.

- [ ] **Step 2: Remove obsolete learner web POST routes**

Delete legacy POST handlers for login, registration, forgot/reset password after confirming their API feature tests pass. Remove `/auth/google`, `/auth/facebook`, their legacy callback routes, and `SocialController` once the new API OAuth tests pass. Replace `SocialLoginTest` with assertions that all four legacy OAuth URLs return 404 while the API-scoped routes remain covered by `OAuthApiTest`. Preserve the named GET `login` redirect required by Laravel's browser-oriented auth middleware and keep admin routes intact.

- [ ] **Step 3: Remove only unreferenced Blade auth rendering code**

Run `rg "view\('auth\.|auth\.(login|register|forgot|reset|verify)" app routes tests` before deletion. Delete each view and controller rendering method only if the search proves it has no remaining caller; update tests in the same commit. Do not delete admin CRUD views.

- [ ] **Step 4: Run backend regression and commit**

Run: `php artisan test tests/Feature/ProductionFrontendRedirectTest.php tests/Feature/Api/V1/OAuthApiTest.php tests/Feature/LoginTest.php tests/Feature/RegistrationTest.php tests/Feature/ForgotPasswordTest.php tests/Feature/SocialLoginTest.php`

Expected: PASS, with obsolete web-flow assertions removed only where the route was intentionally retired.

Run: `./vendor/bin/pint --test routes/web.php app/Http/Controllers tests/Feature/ProductionFrontendRedirectTest.php`

Expected: PASS.

Commit: `refactor: retire learner Blade auth UI`

## Chunk 2: Next.js experience and production rollout

### Task 4: Build the shared responsive auth layout

**Files:**
- Add: `frontend/public/images/auth-language-study.webp`
- Add: `frontend/public/images/README.md`
- Create: `frontend/src/features/auth/auth-layout.tsx`
- Create: `frontend/src/features/auth/auth-layout.test.tsx`
- Modify: `frontend/src/features/auth/route-policy.ts`
- Modify: `frontend/src/features/auth/route-policy.test.ts`
- Modify: `frontend/src/components/layout/app-shell.tsx`
- Modify: `frontend/src/components/layout/app-shell.test.tsx`

- [ ] **Step 1: Add a licensed local photograph**

Download Unsplash photo `photo-1523240795612-9a054b0db644` (students studying together) from `https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=1920&q=82`, convert it to WebP at a maximum 1920 px width, and record the Unsplash source/photographer attribution in `frontend/public/images/README.md`. Do not use a runtime remote image URL.

Run:

```bash
file frontend/public/images/auth-language-study.webp
sips -g pixelWidth frontend/public/images/auth-language-study.webp
stat -f '%z bytes' frontend/public/images/auth-language-study.webp
```

Expected: WebP image data, pixel width at most 1920, and byte count below 500000.

- [ ] **Step 2: Write failing layout and route-policy tests**

Assert every auth path is recognized, learner navigation is absent, login content remains in `.app-shell`, form content is labelled, and the decorative image is hidden from assistive technology.

- [ ] **Step 3: Implement `AuthLayout`**

Create a desktop two-column viewport layout and stacked mobile layout. Use `next/image` with `fill`, an overlay gradient, concise Linguist learning copy, and decorative vocabulary chips. Use pointer coordinates only to update CSS custom properties with a capped transform; disable listeners and transitions for `prefers-reduced-motion`. No new dependency.

- [ ] **Step 4: Extend shell-free auth routing**

Add `isAuthPath()` covering login, register, verify, forgot, reset, and OAuth callback. Render these routes without learner navigation but inside `.app-shell` so the persistent background cannot cover them.

- [ ] **Step 5: Run focused tests and commit**

Run: `cd frontend && ./node_modules/.bin/vitest run src/features/auth/auth-layout.test.tsx src/features/auth/route-policy.test.ts src/components/layout/app-shell.test.tsx`

Expected: PASS.

Commit: `feat: add split-screen auth layout`

### Task 5: Implement email authentication pages

**Files:**
- Modify: `frontend/src/features/auth/login-page.tsx`
- Modify: `frontend/src/features/auth/login-page.test.tsx`
- Create: `frontend/src/features/auth/register-page.tsx`
- Create: `frontend/src/features/auth/register-page.test.tsx`
- Create: `frontend/src/features/auth/forgot-password-page.tsx`
- Create: `frontend/src/features/auth/forgot-password-page.test.tsx`
- Create: `frontend/src/features/auth/reset-password-page.tsx`
- Create: `frontend/src/features/auth/reset-password-page.test.tsx`
- Create: `frontend/src/features/auth/verify-email-page.tsx`
- Create: `frontend/src/features/auth/verify-email-page.test.tsx`
- Create: `frontend/src/app/register/page.tsx`
- Create: `frontend/src/app/forgot-password/page.tsx`
- Create: `frontend/src/app/reset-password/page.tsx`
- Create: `frontend/src/app/verify-email/page.tsx`
- Modify: `frontend/src/lib/api.ts`
- Modify: `frontend/src/lib/api.test.ts`

- [ ] **Step 1: Write and run the failing verification-resend API client test**

Assert `auth.resendVerification()` initializes CSRF and sends `POST /api/v1/auth/email/resend`. Run `cd frontend && ./node_modules/.bin/vitest run src/lib/api.test.ts`; expect FAIL because the method is absent.

- [ ] **Step 2: Implement and verify verification-resend API client**

Add the method through the existing request wrapper. Re-run `src/lib/api.test.ts`; expect PASS.

- [ ] **Step 3: Write and run failing login experience tests**

Cover successful login/navigation, Laravel field error, verified/reset banners, Google/Facebook URLs, accessible alert/status regions, and retryable network failure that preserves email/password. Run `cd frontend && ./node_modules/.bin/vitest run src/features/auth/login-page.test.tsx`; expect FAIL because the links, provider actions, and banners are absent.

- [ ] **Step 4: Implement and verify the redesigned login**

Wrap the form in `AuthLayout`, add forgot/register links, password visibility control, verified/error banners, separator, and Google/Facebook buttons. OAuth buttons navigate to `/api/v1/auth/oauth/{provider}?next=<safeNext>`. Preserve typed values after recoverable errors.

Run: `cd frontend && ./node_modules/.bin/vitest run src/features/auth/login-page.test.tsx`

Expected: PASS.

- [ ] **Step 5: Write and run failing registration tests**

Cover successful request and navigation to `/verify-email`, client confirmation mismatch, Laravel per-field validation errors, accessible error/status regions, duplicate submission prevention, and network retry without clearing name/email/password. Run `cd frontend && ./node_modules/.bin/vitest run src/features/auth/register-page.test.tsx`; expect FAIL because the test import cannot resolve the absent component.

- [ ] **Step 6: Implement and verify registration**

Build the route/component through `auth.register`; run `cd frontend && ./node_modules/.bin/vitest run src/features/auth/register-page.test.tsx`; expect PASS.

- [ ] **Step 7: Write and run failing verification-page tests**

Cover resend success, backend validation/session error, accessible live status, disabled pending action, login navigation, and retry without losing the displayed email context. Run `cd frontend && ./node_modules/.bin/vitest run src/features/auth/verify-email-page.test.tsx`; expect FAIL because the test import cannot resolve the absent component.

- [ ] **Step 8: Implement and verify email verification page**

Build the route/component through `auth.resendVerification`; run `cd frontend && ./node_modules/.bin/vitest run src/features/auth/verify-email-page.test.tsx`; expect PASS.

- [ ] **Step 9: Write and run failing forgot-password tests**

Cover privacy-safe success, Laravel validation, accessible status/error regions, pending submission, login navigation, and retry without clearing email. Run `cd frontend && ./node_modules/.bin/vitest run src/features/auth/forgot-password-page.test.tsx`; expect FAIL because the test import cannot resolve the absent component.

- [ ] **Step 10: Implement and verify forgot password**

Build the route/component through `auth.forgotPassword`; run `cd frontend && ./node_modules/.bin/vitest run src/features/auth/forgot-password-page.test.tsx`; expect PASS.

- [ ] **Step 11: Write and run failing reset-password tests**

Cover missing-token/email invalid state, confirmation mismatch, Laravel validation, successful navigation to `/login?reset=1`, accessible live regions, pending submission, and retry without clearing password fields. Run `cd frontend && ./node_modules/.bin/vitest run src/features/auth/reset-password-page.test.tsx`; expect FAIL because the test import cannot resolve the absent component.

- [ ] **Step 12: Implement and verify password reset**

Build the route/component through `auth.resetPassword`; run `cd frontend && ./node_modules/.bin/vitest run src/features/auth/reset-password-page.test.tsx`; expect PASS.

- [ ] **Step 13: Run all auth-page tests and commit**

Run: `cd frontend && ./node_modules/.bin/vitest run src/features/auth src/lib/api.test.ts`

Expected: PASS.

Commit: `feat: add complete Next.js email auth flows`

### Task 6: Bootstrap OAuth sessions on Next.js

**Files:**
- Create: `frontend/src/features/auth/oauth-callback-page.tsx`
- Create: `frontend/src/features/auth/oauth-callback-page.test.tsx`
- Create: `frontend/src/app/auth/callback/page.tsx`
- Modify: `frontend/src/features/auth/login-page.tsx`
- Modify: `frontend/src/features/auth/login-page.test.tsx`

- [ ] **Step 1: Write failing callback tests**

Assert `refreshUser()` runs once, a successful user navigates through existing `safeNext()`, a failed refresh routes to `/login?oauth_error=session_failed`, raw query strings never become external navigation, and login maps every documented OAuth error code to safe text.

Run: `cd frontend && ./node_modules/.bin/vitest run src/features/auth/oauth-callback-page.test.tsx src/features/auth/login-page.test.tsx`

Expected: FAIL because the callback component and OAuth error mapping are absent.

- [ ] **Step 2: Implement callback and stable OAuth messages**

Render a compact loading state within `AuthLayout`. Map only documented error codes to friendly messages on login; unknown codes use a generic message.

- [ ] **Step 3: Run tests and commit**

Run: `cd frontend && ./node_modules/.bin/vitest run src/features/auth/oauth-callback-page.test.tsx src/features/auth/login-page.test.tsx`

Expected: PASS.

Commit: `feat: complete frontend OAuth session flow`

### Task 7: Full verification, secrets, and deployment

**Files:**
- Modify: `.env.example`
- Modify: `docs/PRODUCTION_ENV.md`
- Modify if required: `.github/workflows/tests.yml`

- [ ] **Step 1: Run all local checks**

Run:

```bash
./vendor/bin/pint --test
php artisan test
cd frontend && ./node_modules/.bin/vitest run && ./node_modules/.bin/eslint . && ./node_modules/.bin/next build
```

Expected: all commands PASS.

- [ ] **Step 2: Document and configure production OAuth values**

Google and Facebook credentials are deployment prerequisites because both buttons are explicitly required and always enabled. Document the six variables, then load credentials into shell variables without echoing them and run:

```bash
flyctl secrets set --app linguist \
  GOOGLE_CLIENT_ID="$LINGUIST_GOOGLE_CLIENT_ID" \
  GOOGLE_CLIENT_SECRET="$LINGUIST_GOOGLE_CLIENT_SECRET" \
  GOOGLE_REDIRECT_URI="https://linguist-nova.vercel.app/api/v1/auth/oauth/google/callback" \
  FACEBOOK_CLIENT_ID="$LINGUIST_FACEBOOK_CLIENT_ID" \
  FACEBOOK_CLIENT_SECRET="$LINGUIST_FACEBOOK_CLIENT_SECRET" \
  FACEBOOK_REDIRECT_URI="https://linguist-nova.vercel.app/api/v1/auth/oauth/facebook/callback"
flyctl secrets list --app linguist
```

Expected: all six names show `Deployed`; values are never printed. If either provider credential pair is unavailable, production rollout is blocked rather than shipping a broken button.

- [ ] **Step 3: Open PR and wait for CI**

Push the implementation branch and open a PR to `main`, then resolve and watch its number:

```bash
LINGUIST_PR_NUMBER=$(gh pr view --json number --jq .number)
gh pr checks "$LINGUIST_PR_NUMBER" --watch
```

Expected: backend PHP matrix, learner frontend, and admin frontend pass.

- [ ] **Step 4: Merge and verify Fly deployment**

Merge only after CI passes, then run:

```bash
LINGUIST_MAIN_RUN_ID=$(gh run list --branch main --workflow Tests --limit 1 --json databaseId --jq '.[0].databaseId')
gh run watch "$LINGUIST_MAIN_RUN_ID" --exit-status
flyctl status --app linguist
curl -fsS https://linguist.fly.dev/api/v1/health
```

Expected: main workflow success, Fly machines started/healthy, and health JSON reports `status: ok`.

- [ ] **Step 5: Deploy Vercel production**

Run `cd frontend && vercel deploy --prod --yes`, then `vercel inspect linguist-nova.vercel.app`. Expected: deployment `Ready`, target `production`, and alias assigned.

- [ ] **Step 6: Verify production end to end**

Use a clean browser profile at desktop and exactly 320 px viewport width to verify screenshots, keyboard focus order, reduced-motion fallback, register/verify/resend, forgot/reset email link shape, email login/logout, both OAuth callbacks, and protected-route redirects.

Verify exact Fly boundaries without following redirects:

```text
GET https://linguist.fly.dev/ -> 302 https://linguist-nova.vercel.app/
GET https://linguist.fly.dev/login -> 302 https://linguist-nova.vercel.app/login
GET https://linguist.fly.dev/register -> 302 https://linguist-nova.vercel.app/register
GET https://linguist.fly.dev/forgot-password -> 302 https://linguist-nova.vercel.app/forgot-password
GET https://linguist.fly.dev/reset-password/test-token?email=test@example.com -> 302 to the matching frontend reset URL
GET https://linguist.fly.dev/verify-email -> 302 https://linguist-nova.vercel.app/verify-email
GET https://linguist.fly.dev/profile -> 302 https://linguist-nova.vercel.app/profile
GET https://linguist.fly.dev/progress -> 302 https://linguist-nova.vercel.app/progress
GET https://linguist.fly.dev/words -> 302 https://linguist-nova.vercel.app/vocabulary
GET https://linguist.fly.dev/api/v1/health -> 200 JSON
GET https://linguist-nova.vercel.app/api/v1/health -> 200 JSON
GET https://linguist.fly.dev/admin -> existing admin auth behavior, not learner redirect
GET https://linguist.fly.dev/api/v1/auth/email/verify/0/invalid -> 403, proving the API route remains on Fly; the valid signed success path is covered by `EmailVerificationApiTest`
```

Expected: visible auth UI, working flows, API health 200, and no learner Blade UI exposed from Fly.

- [ ] **Step 7: Record completion**

Update this plan's checkboxes as tasks complete and commit: `docs: complete Next.js auth suite plan`.
