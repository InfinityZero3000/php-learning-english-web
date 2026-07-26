# Foundation, Auth API, and Frontend Login Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver Phases 1–3 of the approved integration: versioned contracts, the forward-only learning schema, a session/CSRF JSON API, Auth/Mail behavior, and working login/current-user flows in both Next.js applications.

**Architecture:** Laravel remains the only identity and business backend. First-party JSON routes use Laravel's native `web` session and CSRF middleware through same-origin Vercel rewrites. New schema preserves existing numeric IDs and stores LexiLingo identifiers as opaque unique strings.

**Tech Stack:** PHP 8.3, Laravel 13, PHPUnit, MySQL/SQLite tests, Next.js 16, React 19, TypeScript, pnpm, Vercel rewrites, Fly.io

**Approved design:** `docs/superpowers/specs/2026-07-25-laravel-next-lexilingo-integration-design.md`

**Dependency gate:** Tasks 1 and 2 are strictly sequential prerequisites.
Tasks 3–13 do not start until both contract validators pass and the plan
reviewer approves the checked-in artifacts.

---

## File Map

### Contract and documentation

- Create `docs/openapi/laravel-v1.yaml`: canonical API contract used by Laravel and both Next.js apps.
- Create `docs/openapi/lexilingo-import.schema.json`: pinned validation schema for the approved public import payloads.
- Modify `frontend/package.json`, `frontend/pnpm-lock.yaml`: pin the
  OpenAPI/schema validator and frontend test runner.
- Modify `admin-frontend/package.json`, `admin-frontend/package-lock.json`:
  pin the admin frontend test runner.
- Modify `docs/PROJECT_PLAN.md`: record Phase 1–3 completion evidence only after checks pass.

### Laravel schema and models

- Create `database/migrations/2026_07_25_000000_extend_learning_schema_for_integration.php`.
- Create `app/Models/CourseCategory.php`.
- Create `app/Models/Unit.php`.
- Create `app/Models/UserVocabulary.php`.
- Create `app/Models/VocabularyReview.php`.
- Modify `app/Models/Course.php`, `Lesson.php`, `Vocabulary.php`, and `User.php`.
- Modify `database/seeders/CatalogSeeder.php`.

### Laravel API

- Create `routes/spa.php`: session-authenticated `/api/v1` routes.
- Modify `bootstrap/app.php`: load `routes/spa.php` with the `web` middleware.
- Create `app/Http/Controllers/Api/V1/AuthController.php`.
- Create `app/Http/Controllers/Api/V1/EmailVerificationController.php`.
- Create `app/Http/Controllers/Api/V1/PasswordController.php`.
- Create `app/Http/Controllers/Api/V1/ProfileController.php`.
- Create `app/Http/Requests/Api/V1/RegisterRequest.php`.
- Create `app/Http/Requests/Api/V1/LoginRequest.php`.
- Create `app/Http/Requests/Api/V1/UpdateProfileRequest.php`.
- Create `app/Http/Resources/UserResource.php`.
- Create `app/Support/ApiResponse.php`.
- Modify `app/Providers/AppServiceProvider.php` for canonical signed mail URLs.
- Modify `.env.example` and `config/app.php` for frontend URL and cookie/mail settings.
- Modify `routes/web.php` only if needed to preserve the existing Blade route
  named `verification.verify`; do not remove or rename it.

The existing Blade controllers and routes remain operational during migration.
Shared business logic should be reused directly where it is already small;
do not introduce service interfaces or repositories for Auth.

### Learner frontend

- Modify `frontend/next.config.ts`.
- Modify `frontend/package.json`, `frontend/pnpm-lock.yaml`,
  `frontend/vitest.config.ts`, and create test setup as needed.
- Replace `frontend/src/lib/api.ts` with the canonical Laravel client surface.
- Create `frontend/src/lib/csrf.ts`.
- Create `frontend/src/features/auth/login-page.tsx`.
- Create `frontend/src/app/login/page.tsx`.
- Modify `frontend/src/features/profile/profile-page.tsx`.
- Modify `frontend/src/components/layout/app-shell.tsx` only as needed for authenticated state/logout.

### Admin frontend

- Modify `admin-frontend/next.config.ts`.
- Modify `admin-frontend/package.json`, `admin-frontend/package-lock.json`,
  `admin-frontend/vitest.config.ts`, and create test setup as needed.
- Replace the authentication portion of `admin-frontend/src/lib/api.ts`.
- Modify `admin-frontend/src/app/login/page.tsx`.
- Modify `admin-frontend/src/components/AdminLayout.tsx` to verify the current user and require the admin role.

### Tests

- Create `tests/Feature/IntegrationSchemaTest.php`.
- Create `tests/Feature/Api/V1/AuthApiTest.php`.
- Create `tests/Feature/Api/V1/PasswordApiTest.php`.
- Create `tests/Feature/Api/V1/ProfileApiTest.php`.
- Create `tests/Feature/Api/V1/HealthApiTest.php`.
- Preserve and run existing Blade Auth/Profile tests.
- Create frontend unit/component tests for CSRF/API error parsing, learner
  login, and the admin role guard.

---

## Chunk 1: Contracts and Schema

### Task 1: Check in the versioned Laravel API contract

**Files:**

- Create: `docs/openapi/laravel-v1.yaml`
- Modify: `frontend/package.json`, `frontend/pnpm-lock.yaml`
- Test: parse/lint command in this task

- [x] **Step 1: Write the initial OpenAPI document**

Define these Phase 1–3 operations:

```text
GET    /api/v1/health
GET    /api/v1/csrf-cookie
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
POST   /api/v1/auth/email/resend
GET    /api/v1/auth/email/verify/{id}/{hash}
POST   /api/v1/auth/password/forgot
POST   /api/v1/auth/password/reset
PUT    /api/v1/profile
PUT    /api/v1/profile/password
DELETE /api/v1/profile
```

Use cookie authentication, `X-XSRF-TOKEN` for mutations, JSON validation
errors (`message`, `errors`), and a user schema containing:

```yaml
User:
  type: object
  required: [id, name, email, email_verified_at, role]
  properties:
    id: { type: integer }
    name: { type: string }
    email: { type: string, format: email }
    email_verified_at: { type: [string, "null"], format: date-time }
    role: { type: [string, "null"] }
```

- [x] **Step 2: Pin and run the OpenAPI validator**

```bash
cd frontend
pnpm add -D @redocly/cli@1.34.0
pnpm exec redocly lint ../docs/openapi/laravel-v1.yaml
```

Commit the resulting `package.json` and `pnpm-lock.yaml`. Expected: exit code
0 with no broken references. This is a Phase 1 gate; do not start Task 3 if it
fails.

- [x] **Step 3: Confirm every frontend Auth call has a documented operation**

Run:

```bash
rg -n "/api/v1/" frontend admin-frontend
```

Expected: every Phase 1–3 path is present in `laravel-v1.yaml`.

- [x] **Step 4: Commit**

```bash
git add docs/openapi/laravel-v1.yaml frontend/package.json frontend/pnpm-lock.yaml
git commit -m "docs: define Laravel v1 auth API contract"
```

### Task 2: Pin the LexiLingo import schema

**Files:**

- Create: `docs/openapi/lexilingo-import.schema.json`
- Create: `frontend/scripts/validate-lexilingo-schema.mjs`
- Create: `frontend/test-fixtures/lexilingo/{valid,invalid}/*.json`

- [x] **Step 1: Write the JSON Schema fixture**

Cover the exact fields defined in the approved design for:

```text
CategoryListResponse
CategoryDetailResponse
PaginatedCourseResponse
CourseDetailResponse
LessonContentResponse
VocabularyItemList
```

Use `"additionalProperties": true` at response-envelope boundaries so harmless
upstream additions do not break imports, but require every field consumed by
Laravel. Treat all external IDs as non-empty strings, not UUID-specific values.

- [x] **Step 2: Validate JSON syntax**

Run:

```bash
php -r '$d=json_decode(file_get_contents("docs/openapi/lexilingo-import.schema.json"), true, 512, JSON_THROW_ON_ERROR); assert(isset($d["$defs"]));'
```

Expected: exit code 0.

Add direct pinned `ajv` (and `ajv-formats` when used) devDependencies:

```bash
cd frontend
pnpm add -D ajv@8.17.1 ajv-formats@3.0.1
```

The
validator must import them directly rather than relying on transitive lockfile
entries. Run the pinned validator against representative valid and invalid
fixtures:

```bash
cd frontend
node scripts/validate-lexilingo-schema.mjs
```

Expected: every valid fixture passes and every invalid fixture fails. This is
the second Phase 1 gate.

- [x] **Step 3: Record the pinned source**

Add schema annotations:

```json
{
  "$comment": "Derived from InfinityZero3000/LexiLingo commit 4f74be584a6181acc90dcd72caaae3f47ab3ace1"
}
```

- [x] **Step 4: Commit**

```bash
git add docs/openapi/lexilingo-import.schema.json frontend/scripts/validate-lexilingo-schema.mjs frontend/test-fixtures/lexilingo frontend/package.json frontend/pnpm-lock.yaml
git commit -m "docs: pin LexiLingo import contract"
```

### Task 3: Add the forward-only integration schema

**Files:**

- Create: `database/migrations/2026_07_25_000000_extend_learning_schema_for_integration.php`
- Test: `tests/Feature/IntegrationSchemaTest.php`

- [x] **Step 1: Write failing schema tests**

Test every approved table, column type/default, foreign key, cascade/null
behavior, unique index and JSON field. At minimum assert:

```php
Schema::hasTable('course_categories');
Schema::hasTable('units');
Schema::hasTable('user_vocabularies');
Schema::hasTable('vocabulary_reviews');
Schema::hasColumns('courses', [
    'external_id', 'category_id', 'language', 'thumbnail_url',
    'estimated_duration', 'total_xp',
]);
Schema::hasColumns('lessons', [
    'external_id', 'unit_id', 'lesson_type', 'estimated_minutes',
    'xp_reward', 'pass_threshold',
]);
Schema::hasColumns('vocabularies', [
    'external_id', 'definition', 'translation', 'pronunciation',
    'part_of_speech', 'difficulty_level', 'tags', 'external_audio_url',
]);
```

Also create a legacy course and lesson after the base migration but before the
integration migration, then prove the lesson receives a `Legacy` unit with
signed `sort_order = -1`, its original `course_id` and a deterministic,
collision-resistant slug/content
preserved. Test `user_vocabularies` uniqueness and review cascade delete.

- [x] **Step 2: Run the tests to verify failure**

```bash
php artisan test tests/Feature/IntegrationSchemaTest.php
```

Expected: FAIL because the new tables/columns do not exist.

- [x] **Step 3: Implement the migration**

Required rules:

- `external_id` is nullable string with a unique index per table.
- `course_categories.slug` is unique.
- `units` belongs to courses and has a unique constraint on
  `(course_id, sort_order)`. The reserved Legacy unit owns `-1`; an upstream
  `-1` conflicts deterministically remap to the next available non-negative
  order and record the remap in sync diagnostics.
- `lessons.unit_id` starts nullable.
- Existing lessons are assigned to one generated `Legacy` unit per course with
  reserved signed `sort_order = -1`.
- Keep `lessons.course_id`.
- `lessons.content` remains `longText`; the model stores JSON-shaped content as
  a validated JSON string so existing text is not destructively converted.
- `courses.slug` and `lessons.slug` remain required. Imported slugs use a
  normalized title plus a deterministic hash suffix derived from the full
  opaque external ID; assert uniqueness rather than truncating arbitrary IDs.
- `vocabularies.meaning` remains required and is populated from
  `translation.vi`, falling back to `definition`.
- `user_vocabularies` is unique on `(user_id, vocabulary_id)`.
- `vocabulary_reviews` is append-only and cascades with its user vocabulary.

- [x] **Step 4: Run migration tests**

```bash
php artisan test tests/Feature/IntegrationSchemaTest.php
```

Expected: PASS.

- [x] **Step 5: Verify a forward upgrade and rollback on disposable databases**

```bash
DB_CONNECTION=sqlite DB_DATABASE=/private/tmp/learning-upgrade.sqlite \
  php artisan migrate --path=database/migrations/0001_01_01_000000_create_users_table.php
DB_CONNECTION=sqlite DB_DATABASE=/private/tmp/learning-upgrade.sqlite \
  php artisan migrate --path=database/migrations/2026_07_17_000000_create_learning_schema.php
DB_CONNECTION=sqlite DB_DATABASE=/private/tmp/learning-upgrade.sqlite \
  php artisan test tests/Feature/IntegrationSchemaTest.php --filter=MigrationUpgrade
DB_CONNECTION=sqlite DB_DATABASE=/private/tmp/learning-upgrade.sqlite \
  php artisan migrate:rollback --step=1
DB_CONNECTION=sqlite DB_DATABASE=/private/tmp/learning-upgrade.sqlite \
  php artisan migrate
```

Use a dedicated migration-upgrade harness (not `RefreshDatabase`) that migrates
only the base paths, inserts legacy rows, invokes the target migration
explicitly, asserts backfill, rolls the target migration back, and re-runs it.
The harness must clear any
PHPUnit `DB_DATABASE=:memory:` override. Repeat against disposable MySQL in CI.
Never run `migrate:fresh` against an uncontrolled configured database.

- [x] **Step 6: Commit**

```bash
git add database/migrations tests/Feature/IntegrationSchemaTest.php
git commit -m "feat: extend learning schema for integrations"
```

### Task 4: Add models and relationships

**Files:**

- Create: `app/Models/CourseCategory.php`
- Create: `app/Models/Unit.php`
- Create: `app/Models/UserVocabulary.php`
- Create: `app/Models/VocabularyReview.php`
- Modify: `app/Models/Course.php`
- Modify: `app/Models/Lesson.php`
- Modify: `app/Models/Vocabulary.php`
- Modify: `app/Models/User.php`
- Modify: `database/seeders/CatalogSeeder.php`
- Test: `tests/Feature/IntegrationSchemaTest.php`

- [x] **Step 1: Add failing relationship tests**

Cover:

```text
category → courses
course → category, units
unit → course, lessons
lesson → unit
user → user vocabularies
vocabulary → user vocabularies
user vocabulary → review history
```

Prove the application rejects assigning a lesson to a unit belonging to a
different course at the request boundary when lesson APIs are added; for this
phase, keep the invariant documented in the model test.

- [x] **Step 2: Run the tests to verify failure**

```bash
php artisan test tests/Feature/IntegrationSchemaTest.php
```

- [x] **Step 3: Implement minimal Eloquent relationships and casts**

Use existing Eloquent patterns. Add JSON casts for translation/tags/content and
datetime casts for review scheduling. Do not add repositories or model service
interfaces.

- [x] **Step 4: Seed CEFR levels**

Add A1–C2 with additive upserts while retaining the existing
Beginner/Intermediate/Advanced rows:

```php
['name' => 'A1', 'slug' => 'a1', 'sort_order' => 1],
// ...
['name' => 'C2', 'slug' => 'c2', 'sort_order' => 6],
```

- [x] **Step 5: Run tests and Pint**

```bash
php artisan test tests/Feature/IntegrationSchemaTest.php
./vendor/bin/pint --test
```

- [x] **Step 6: Commit**

```bash
git add app/Models database/seeders tests/Feature/IntegrationSchemaTest.php
git commit -m "feat: model integrated learning content"
```

## Chunk 2: Session API, Auth, and Mail

### Task 5: Add API health, session, and CSRF routing

**Files:**

- Create: `routes/spa.php`
- Modify: `bootstrap/app.php`
- Modify: `.env.example`
- Test: `tests/Feature/Api/V1/HealthApiTest.php`

- [x] **Step 1: Write failing route tests**

Test:

```text
GET /health returns the existing {status: ok} health JSON
GET /api/v1/health returns {status: ok, version: v1}
GET /api/v1/csrf-cookie returns 204 and an XSRF-TOKEN cookie
POST mutation with a cross-site Origin and without a valid token is rejected
in a non-testing smoke process
```

- [x] **Step 2: Run tests and verify failure**

```bash
php artisan test tests/Feature/Api/V1/HealthApiTest.php
```

- [x] **Step 3: Load `routes/spa.php` under `web` middleware**

Register the route file from `bootstrap/app.php` using the routing callback
supported by Laravel 13. The group prefix is `/api/v1`; do not place these
session endpoints under the stateless default `routes/api.php` group.

Add:

```php
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'version' => 'v1',
]));

Route::get('/csrf-cookie', fn () => response()->noContent());
```

The CSRF route must execute the full `web` middleware stack.

- [x] **Step 4: Add production cookie/frontend examples**

Add non-secret examples:

```dotenv
FRONTEND_URL=http://localhost:3000
ADMIN_FRONTEND_URL=http://localhost:3001
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
```

Production changes only `FRONTEND_URL`, `ADMIN_FRONTEND_URL`, and
`SESSION_SECURE_COOKIE=true`.

Add concrete config accessors:

```php
'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
'admin_frontend_url' => env('ADMIN_FRONTEND_URL', 'http://localhost:3001'),
```

- [x] **Step 5: Run focused and full tests**

```bash
php artisan test tests/Feature/Api/V1/HealthApiTest.php
php artisan test
```

- [x] **Step 6: Commit**

```bash
git add bootstrap/app.php routes/spa.php .env.example tests/Feature/Api/V1/HealthApiTest.php
git commit -m "feat: add first-party session API foundation"
```

### Task 6: Implement JSON registration, login, logout, and current user

**Files:**

- Create: `app/Http/Controllers/Api/V1/AuthController.php`
- Create: `app/Http/Requests/Api/V1/RegisterRequest.php`
- Create: `app/Http/Requests/Api/V1/LoginRequest.php`
- Create: `app/Http/Resources/UserResource.php`
- Create: `app/Support/ApiResponse.php`
- Modify: `routes/spa.php`
- Test: `tests/Feature/Api/V1/AuthApiTest.php`

- [x] **Step 1: Write failing API tests**

Cover:

- Registration assigns learner role and sends verification mail.
- Login rejects invalid credentials.
- Login rejects unverified users without authenticating them.
- Login regenerates the session for verified users.
- `/auth/me` returns `401` for guests and the documented user for sessions.
- Admin login uses the same endpoint but admin screens later require role.
- Logout invalidates the session.
- Login is limited to five attempts per minute.

- [x] **Step 2: Run tests and verify failure**

```bash
php artisan test tests/Feature/Api/V1/AuthApiTest.php
```

- [x] **Step 3: Implement the minimal JSON controller**

Reuse the current request validation rules and lifecycle behavior. All
non-204 success responses use the shared `ApiResponse::success()` helper and
include both `data` and `meta`:

```php
return ApiResponse::success(new UserResource($user));
```

Registration returns `201` with a neutral verification message. Login returns
`422` for invalid credentials and `403` with code `EMAIL_UNVERIFIED` for an
unverified account. Never return a bearer or refresh token.

The OpenAPI contract explicitly documents `204` responses without a body and
the health response as its own exception to the envelope.

- [x] **Step 4: Add routes and throttles**

```php
Route::post('/auth/register', ...)->middleware('throttle:5,1');
Route::post('/auth/login', ...)->middleware('throttle:5,1');
Route::post('/auth/logout', ...)->middleware('auth');
Route::get('/auth/me', ...)->middleware('auth');
```

- [x] **Step 5: Run focused, legacy, and full tests**

```bash
php artisan test tests/Feature/Api/V1/AuthApiTest.php
php artisan test tests/Feature/RegistrationTest.php tests/Feature/LoginTest.php
php artisan test
```

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api app/Http/Requests/Api app/Http/Resources routes/spa.php tests/Feature/Api/V1/AuthApiTest.php
git commit -m "feat: expose session authentication API"
```

### Task 7: Implement JSON verification and password mail flows

**Files:**

- Create: `app/Http/Controllers/Api/V1/EmailVerificationController.php`
- Create: `app/Http/Controllers/Api/V1/PasswordController.php`
- Modify: `app/Providers/AppServiceProvider.php` for
  `VerifyEmail::createUrlUsing` and `ResetPassword::createUrlUsing`
- Modify: `app/Models/User.php`
- Modify: `routes/spa.php`
- Test: `tests/Feature/Api/V1/PasswordApiTest.php`

- [x] **Step 1: Write failing tests**

Cover:

- Verification resend returns the same response for eligible states and is
  throttled. It uses the pending `verify_email` session value created at
  registration or an unverified login; it never accepts an arbitrary email
  from the client.
- Signed verification marks the user once and dispatches `Verified` once.
- Forgot-password responses do not reveal whether an email exists, including
  broker-throttled requests.
- Reset changes the password, rotates remember token, and dispatches
  `PasswordReset`.
- Reset links target `FRONTEND_URL/reset-password` and contain token/email.
- No test sends real mail (`Notification::fake()`).

- [x] **Step 2: Run tests and verify failure**

```bash
php artisan test tests/Feature/Api/V1/PasswordApiTest.php
```

- [x] **Step 3: Implement JSON controllers**

Reuse the already hardened logic from existing controllers. Keep neutral
forgot-password behavior for:

```php
Password::RESET_LINK_SENT
Password::RESET_THROTTLED
Password::INVALID_USER
```

Preserve the existing `throttle:3,1` resend and forgot-password route limits.
Keep the existing Blade route named `verification.verify` for legacy tests and
behavior. Name the API route `api.verification.verify` at
`/api/v1/auth/email/verify/{id}/{hash}`, and explicitly configure
`VerifyEmail::createUrlUsing` to target the API route so Laravel never relies
on duplicate route-name selection.

Do not duplicate password-reset token manipulation in multiple controllers;
extract one private callback only if both controllers genuinely require it.

Register `POST /api/v1/auth/email/resend` without accepting an email in the
request body. It reads the pending user ID from the server-side
`verify_email` session key, returns the same neutral response when the key is
absent, and applies `throttle:3,1`.

- [x] **Step 4: Configure canonical verification and reset URLs**

In `AppServiceProvider`, customize the built-in Laravel notification URL
builders to use the canonical signed route and
`config('app.frontend_url').'/reset-password'`. The signed verification
endpoint marks the user, then redirects to `FRONTEND_URL/login`. Never put mail
credentials or reset tokens in logs.

- [x] **Step 5: Run all Auth/Mail tests**

```bash
php artisan test tests/Feature/Api/V1 tests/Feature/ForgotPasswordTest.php tests/Feature/RegistrationTest.php
./vendor/bin/pint --test
```

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api app/Models app/Providers/AppServiceProvider.php routes/spa.php tests/Feature/Api/V1
git commit -m "feat: expose verified mail and password APIs"
```

### Task 8: Implement JSON profile and password management

**Files:**

- Create: `app/Http/Controllers/Api/V1/ProfileController.php`
- Create: `app/Http/Requests/Api/V1/UpdateProfileRequest.php`
- Modify: `routes/spa.php`
- Test: `tests/Feature/Api/V1/ProfileApiTest.php`

- [x] **Step 1: Write failing tests**

Cover:

- Authenticated profile update changes name but not email.
- Password change requires current password and confirmation.
- Account deletion requires current password, logs out, invalidates session,
  and deletes the user.
- Guests receive `401`.

- [x] **Step 2: Run tests and verify failure**

```bash
php artisan test tests/Feature/Api/V1/ProfileApiTest.php
```

- [x] **Step 3: Implement controller and routes**

Keep profile, password, and deletion as separate actions. Use existing Laravel
validation rules and return the canonical `UserResource` or `204`.

- [x] **Step 4: Run focused and legacy tests**

```bash
php artisan test tests/Feature/Api/V1/ProfileApiTest.php tests/Feature/ProfileTest.php
```

- [x] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api app/Http/Requests/Api routes/spa.php tests/Feature/Api/V1/ProfileApiTest.php
git commit -m "feat: expose protected profile API"
```

## Chunk 3: Next.js Login Vertical Slice

### Task 9: Configure same-origin rewrites in both Next.js apps

**Files:**

- Modify: `frontend/next.config.ts`
- Modify: `admin-frontend/next.config.ts`
- Modify: `frontend/.env.example` if present, otherwise create it
- Create: `admin-frontend/.env.example`
- Create: `frontend/vitest.config.ts` and auth/client test files as needed
- Create: `admin-frontend/vitest.config.ts` and auth/guard test files as needed

- [x] **Step 1: Add the server-only origin**

Both configs use:

```ts
const laravelOrigin =
  process.env.LARAVEL_API_ORIGIN ?? "http://localhost:8080";
```

Both rewrite:

```ts
{
  source: "/api/:path*",
  destination: `${laravelOrigin}/api/:path*`,
}
```

Remove `SPRING_API_BASE_URL` and do not introduce
`NEXT_PUBLIC_LARAVEL_API_ORIGIN`.

Add `test` scripts and Vitest dependencies to both apps. The learner app uses
pnpm; the admin app uses npm/package-lock. Keep `LARAVEL_API_ORIGIN`
server-only in both Vercel projects.

 - [x] **Step 2: Verify configuration loads**

```bash
cd frontend && pnpm install --frozen-lockfile && pnpm build
cd ../admin-frontend && npm ci && npm run build
```

Expected: both builds pass before API client changes.
Set server-only `LARAVEL_API_ORIGIN` in each Vercel project. Laravel must also
set `APP_URL`, `FRONTEND_URL`, `ADMIN_FRONTEND_URL`, secure/same-site session
settings, and the configured mail transport before production smoke tests.

- [x] **Step 3: Commit**

```bash
git add frontend/next.config.ts frontend/.env.example admin-frontend/next.config.ts admin-frontend/.env.example
git commit -m "chore: proxy Next.js APIs to Laravel"
```

### Task 10: Replace frontend authentication clients

**Files:**

- Create: `frontend/src/lib/csrf.ts`
- Modify: `frontend/src/lib/api.ts`
- Modify: `admin-frontend/src/lib/api.ts`

- [x] **Step 1: Implement one small request primitive per app**

Behavior:

```text
credentials: include
Accept: application/json
Content-Type: application/json only for JSON bodies
initialize /api/v1/csrf-cookie before POST/PUT/PATCH/DELETE
read XSRF-TOKEN cookie and send decoded X-XSRF-TOKEN
parse Laravel message/errors on failure
```

Do not add Axios: native `fetch`, `URLSearchParams`, and current TypeScript are
enough.

Before implementation, add Vitest and the existing app test utilities, then
write failing tests for CSRF initialization, credentials, the decoded
`X-XSRF-TOKEN`, and Laravel `{message, errors}` parsing. Run the focused tests
before and after the client change.

- [x] **Step 2: Expose only implemented Auth methods**

```ts
auth.register(...)
auth.login(email, password)
auth.logout()
auth.me()
auth.forgotPassword(email)
auth.resetPassword(...)
profile.update(...)
profile.changePassword(...)
profile.destroy(password)
```

Temporarily retain unrelated old API exports only if existing pages require
them to compile; mark their controls disabled in later feature plans. Remove
all `localStorage.getItem("admin_token")` and bearer-token headers.

- [x] **Step 3: Run lint/build**

```bash
cd frontend && pnpm lint && pnpm test && pnpm build
cd ../admin-frontend && npm run lint && npm test && npm run build
```

- [x] **Step 4: Commit**

```bash
git add frontend/src/lib admin-frontend/src/lib
git commit -m "refactor: use Laravel session API clients"
```

### Task 11: Build learner login/current-user flow

**Files:**

- Create: `frontend/src/features/auth/login-page.tsx`
- Create: `frontend/src/app/login/page.tsx`
- Modify: `frontend/src/components/layout/app-shell.tsx`
- Modify: `frontend/src/features/profile/profile-page.tsx`

- [x] **Step 1: Add the login screen**

Add a focused component test first (mock `auth.login`) covering submitting,
invalid credentials, and successful navigation.

Reuse existing UI tokens/components. Required states:

```text
idle
submitting
invalid credentials
email unverified
validation errors
network/server error
```

On success, navigate to `/`; do not store tokens.

- [x] **Step 2: Add current-user and logout behavior**

The app shell calls `auth.me()`, renders the authenticated name, and exposes a
logout action. A `401` redirects protected screens to `/login`; transport
failures display an error instead of pretending the user is logged out.

- [x] **Step 3: Connect profile basics**

For this vertical slice, connect name update and current-user display. Hide or
disable legacy profile controls not covered by Phase 1–3 APIs.

- [x] **Step 4: Verify**

```bash
cd frontend && pnpm lint && pnpm test && pnpm build
```

- [x] **Step 5: Commit**

```bash
git add frontend/src/app frontend/src/features frontend/src/components/layout/app-shell.tsx
git commit -m "feat: connect learner login to Laravel"
```

### Task 12: Build admin login/current-user guard

**Files:**

- Modify: `admin-frontend/src/app/login/page.tsx`
- Modify: `admin-frontend/src/components/AdminLayout.tsx`

- [x] **Step 1: Replace token login**

Add a focused `AdminLayout` test first covering `401`, non-admin, transport
failure, and authorized admin states. Hide or disable dashboard/navigation
controls whose backend APIs are not implemented in Phase 1–3.

Use email/password fields and `auth.login()`. Remove:

```ts
localStorage.setItem("admin_token", ...)
```

After login, call `auth.me()`. Only role slug `admin` enters `/dashboard`;
otherwise log out and show a forbidden message.

- [x] **Step 2: Guard admin layouts**

On initial load:

- `401` redirects to `/login`.
- Non-admin user is logged out and redirected with a forbidden message.
- Transport failure shows retry UI.
- Authorized admin renders children.

- [x] **Step 3: Verify**

```bash
cd admin-frontend && npm run lint && npm test && npm run build
```

- [x] **Step 4: Commit**

```bash
git add admin-frontend/src/app/login/page.tsx admin-frontend/src/components/AdminLayout.tsx
git commit -m "feat: protect admin frontend with Laravel session"
```

### Task 13: End-to-end Phase 1–3 verification

**Files:**

- Modify: `docs/PROJECT_PLAN.md`
- Modify: `docs/openapi/laravel-v1.yaml` only if implementation revealed an
  reviewed contract correction

- [x] **Step 1: Run backend gates**

```bash
DB_CONNECTION=sqlite DB_DATABASE=/private/tmp/learning-phase3-smoke.sqlite php artisan migrate --seed
php artisan test
./vendor/bin/pint --test
```

Use only a disposable SQLite/MySQL test database; never run
`migrate:fresh` against a shared or production database. Expected: all pass.

- [x] **Step 2: Run frontend gates**

```bash
cd frontend && pnpm lint && pnpm test && pnpm build
cd ../admin-frontend && npm run lint && npm test && npm run build
```

Expected: all pass.

- [x] **Step 3: Run local smoke test**

Start Laravel and both Next.js apps. Verify:

```text
learner: csrf → login → me → update profile → logout
admin: csrf → login → me/admin check → protected shell → logout
negative: learner cannot enter admin dashboard
mail: register/forgot flows queue or send through configured test mailer
health: both Next.js origins reach /api/v1/health via rewrite
```

The protected shell is the only admin Phase 1–3 surface; unsupported CRUD
screens remain hidden or disabled until their APIs are implemented.

- [x] **Step 4: Audit the contract**

Compare every implemented Phase 1–3 route and response against
`docs/openapi/laravel-v1.yaml`. Contract mismatch is a failure even if tests
pass.

- [x] **Step 5: Update plan evidence**

Record exact test counts, build results, and remaining deferred phases in
`docs/PROJECT_PLAN.md`. Do not mark dataset/learning/admin/AI phases complete.

- [x] **Step 6: Request final code review**

Review security, session fixation, CSRF, rate limits, role authorization,
password lifecycle, migration rollback, and frontend token removal.

- [x] **Step 7: Commit**

```bash
git add docs/PROJECT_PLAN.md docs/openapi
git commit -m "docs: record foundation and auth delivery"
```

---

## Deferred Plans

After this plan is green, create separate reviewed plans for:

1. LexiLingo dataset importer and protected content export.
2. Catalog, vocabulary, quiz, progress, bookmark and FSRS APIs.
3. Admin CRUD/RBAC frontend migration.
4. Stateless LexiLingo translate/pronunciation/STT/TTS proxy.
5. Vercel/Fly production deployment and browser verification.

Do not implement AI tutor until LexiLingo provides the approved
external-subject contract.
