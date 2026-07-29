# Full Admin System Implementation Plan

> **Trạng thái:** phạm vi ứng dụng đã triển khai; production smoke/rollback còn
> lại được theo dõi tại [`../../CURRENT_STATUS.md`](../../CURRENT_STATUS.md).
> Checkbox bên dưới là hồ sơ kế hoạch, không phải trạng thái hiện hành.

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a complete, role-aware Admin and Super Admin system in `admin-frontend` with every retained screen discoverable and backed by authorized, non-mock Laravel APIs.

**Architecture:** Keep LexiLingo as the service/data provider and PHP_Learning-English-Web as the system of record and admin application. Extend the existing `/api/v1/admin/*` controllers and policies instead of introducing another service layer; reuse the existing Google admin session, role gates, audit model, importers, and Next.js shell. Admin receives catalog and aggregate learning operations, while Super Admin inherits those capabilities and gains roles, evidence access, AI/monitoring, quotas, alerts, and audit controls.

**Tech Stack:** Laravel/PHP 8, Eloquent/MySQL 8.4 in production and SQLite in tests, Next.js 16 App Router, React 19, TypeScript, Tailwind CSS 4, PHPUnit, ESLint, native browser/fetch APIs.

**Design spec:** `docs/superpowers/specs/2026-07-28-full-admin-super-admin-navigation-design.md`

---

## File Map

**Backend contracts**

- Modify `routes/spa.php`: register canonical admin catalog, learning, import, feed, notification, preference, report, and evidence routes inside `auth` + `google.admin`.
- Modify `app/Http/Controllers/Api/V1/Admin/CatalogController.php`: levels, topics, vocabulary, deck CRUD and catalog summary using `manage-content`.
- Create `app/Http/Controllers/Api/V1/Admin/LearningController.php`: privacy-safe analytics, quiz, progress, and FSRS aggregates plus CSV report streaming.
- Create `app/Http/Controllers/Api/V1/Admin/ContentOperationsController.php`: import checkpoints/start/reset, provider content feed, operational notifications, and admin preferences.
- Create `app/Models/AdminImportRun.php`, `app/Models/AdminImportLock.php`, `app/Jobs/RunAdminImport.php`, and `app/Services/AdminImportRunner.php`: durable idempotent import runs, queued execution, and one pre-seeded lock row per entity around existing importers.
- Modify `app/Services/Import/AbstractLexiLingoImporter.php`, `app/Services/Import/CategoryImporter.php`, `app/Services/Import/CourseImporter.php`, and `app/Services/LexiLingoVocabularySync.php`: explicit starting-cursor replay plus bounded/redacted failure persistence while retaining the existing client-owned retry/rate-limit behavior.
- Modify `app/Http/Controllers/Api/V1/Admin/OperationsController.php`: paginated/filterable real audit data and Super Admin dashboard data only.
- Modify `app/Http/Controllers/Api/V1/TeacherController.php` and `app/Policies/LearningPolicy.php`: explicit Super Admin learner-evidence access with reason and audit; teacher assignment behavior remains unchanged.
- Create `app/Models/VocabularyDeck.php`, `app/Models/AdminPreference.php`, and `database/migrations/2026_07_28_130000_create_admin_content_tables.php`: persisted decks, deck-vocabulary pivot, admin preferences, and notification read state only; do not duplicate existing levels/topics/vocabulary/import/audit data.
- Modify `app/Models/Vocabulary.php` and `app/Models/User.php`: relationships/casts for the new persisted data.

**Frontend shell and contracts**

- Modify `admin-frontend/src/components/Sidebar.tsx`: one canonical role-aware navigation manifest with all approved entries.
- Create `admin-frontend/src/lib/admin-navigation.mjs`: runtime-neutral navigation/alias manifest consumed by Sidebar and the Node checker.
- Modify `admin-frontend/src/components/AdminLayout.tsx`: retain the shared session guard and expose the loaded user through a minimal context only if child pages require it.
- Create `admin-frontend/src/app/super-admin/page.tsx`: Super Admin overview backed by operations APIs.
- Modify `admin-frontend/src/lib/api.ts`: remove learner-era `/api/*` calls from admin workflows and expose typed `/api/v1/admin/*` clients.
- Modify retained pages under `admin-frontend/src/app/{analytics,content,decks,flashcards,import,levels,notifications,quizzes,reports,settings,spaced-repetition,topics,user-progress,audit-logs,operations}/page.tsx`: connect each UI to its matching contract, real loading/error/empty states, and permissions.
- Modify aliases `admin-frontend/src/app/{progress,quiz,vocabulary,vocabulary-sets}/page.tsx`: server redirects to canonical routes.
- Modify `admin-frontend/src/app/dashboard/page.tsx`: Admin operational dashboard; link Super Admin to `/super-admin`.
- Modify `admin-frontend/src/app/globals.css`: only shared sidebar scroll/responsive rules needed by the approved visual.

**Verification**

- Modify `tests/Feature/Api/V1/AdminCatalogApiTest.php`: catalog/deck authorization, validation, idempotency, and audit coverage.
- Create `tests/Feature/Api/V1/AdminLearningApiTest.php`: aggregate field allowlist, forbidden PII, report, and evidence authorization.
- Create `tests/Feature/Api/V1/AdminContentOperationsApiTest.php`: import role split, provider failures, notifications, and preferences.
- Modify `tests/Feature/Api/V1/OperationsApiTest.php`: Super Admin overview/audit filters and secret redaction.
- Modify `tests/Feature/RoleCapabilityTest.php`: teacher assignment versus Super Admin reason/audit evidence matrix.
- Create `admin-frontend/scripts/check-admin-navigation.mjs`: dependency-free manifest/route/alias check.
- Modify `admin-frontend/package.json`: add only `check:navigation`; do not install a test framework.

## Chunk 1: Security and Backend Foundations

### Task 1: Lock the catalog route/capability matrix with a failing test

**Files:**
- Modify: `tests/Feature/Api/V1/AdminCatalogApiTest.php`

- [ ] **Step 1: Add data providers for Admin versus Super Admin access**

Keep the existing private `user(string $role)` helper in `AdminCatalogApiTest`. Import `PHPUnit\Framework\Attributes\DataProvider`. Generate the Admin/Super Admin cross-product for summary, courses, levels, topics, vocabularies, and decks. Assert unauthenticated requests return `401`, learners return `403`, authenticated admins without the `google_admin` marker return `401`, and marker subject/email/user mismatches return `401`. An old `google_admin_reauthenticated_at` must still allow ordinary catalog reads/writes; freshness is checked only for the sensitive actions named in the spec.

```php
#[DataProvider('adminReadRoutes')]
public function test_admin_and_super_admin_can_read_catalog_contracts(string $role, string $uri): void
{
    $this->actingAs($this->user($role))->getJson($uri)->assertOk();
}

public static function adminReadRoutes(): array
{
    $routes = ['summary', 'courses', 'levels', 'topics', 'vocabularies', 'decks'];
    $cases = [];
    foreach (['admin', 'super_admin'] as $role) {
        foreach ($routes as $route) $cases[] = [$role, "/api/v1/admin/catalog/{$route}"];
    }
    return $cases;
}
```

- [ ] **Step 2: Add mutation security assertions**

Assert every catalog write requires a valid Google admin session, `manage-content`, UUID `X-Request-ID`, and creates one audit record. Replay the same request id/payload successfully; reuse it with a different action/payload and assert `409`. Because Laravel bypasses CSRF during normal feature tests, add a focused route-stack assertion that each admin mutation route contains the `web` middleware group and is not listed with `PreventRequestForgery` removed; retain one production-browser missing-token smoke check expecting `419` in Task 11.

- [ ] **Step 3: Run the focused test and confirm RED**

Run: `php artisan test tests/Feature/Api/V1/AdminCatalogApiTest.php`

Expected: FAIL only because the new routes/models/authorization behavior do not exist.

- [ ] **Step 4: Do not commit while RED**

Keep the failing catalog tests in the worktree through Tasks 2–3. Commit them only with the passing catalog implementation.

### Task 2: Add only the missing persisted admin data

**Files:**
- Create: `database/migrations/2026_07_28_130000_create_admin_content_tables.php`
- Create: `app/Models/VocabularyDeck.php`
- Create: `app/Models/AdminPreference.php`
- Modify: `app/Models/Vocabulary.php`
- Modify: `app/Models/User.php`
- Create: `tests/Feature/AdminContentSchemaTest.php`

- [ ] **Step 1: Write migration assertions**

Create `test_admin_content_schema_supports_decks_preferences_and_notification_reads` in `AdminContentSchemaTest`. Assert unique deck slug, pivot cascade, one preference row per administrator, and unique `(user_id, supervision_alert_id)` read state.

- [ ] **Step 2: Run the schema assertions and confirm RED**

Run: `php artisan test tests/Feature/AdminContentSchemaTest.php --filter=admin_content_schema`

Expected: FAIL because the tables are absent.

- [ ] **Step 3: Add the minimal schema**

Create `vocabulary_decks(id, name, slug unique, description nullable, is_public boolean default false, timestamps)`, `vocabulary_deck_vocabulary(vocabulary_deck_id, vocabulary_id, sort_order, primary key)`, `admin_preferences(user_id primary key, notifications json, ui json, timestamps)`, and `admin_notification_reads(user_id, supervision_alert_id, read_at, primary key)`. Use foreign keys and cascade deletes; ensure the migration works on both MySQL and SQLite; no generic settings framework.

- [ ] **Step 4: Add relationships and JSON casts**

`VocabularyDeck::vocabularies()` and `Vocabulary::decks()` explicitly pass `vocabulary_deck_vocabulary`, `vocabulary_deck_id`, and `vocabulary_id`; `User::readOperationalNotifications()` explicitly passes `admin_notification_reads`, `user_id`, and `supervision_alert_id`. Add `AdminPreference` JSON casts and `User::adminPreference()`. These explicit mappings preserve the readable table names without relying on Laravel inference.

- [ ] **Step 5: Run schema tests and commit**

Run: `php artisan test tests/Feature/AdminContentSchemaTest.php --filter=admin_content_schema`

Expected: PASS.

```bash
git add database/migrations/2026_07_28_130000_create_admin_content_tables.php app/Models/VocabularyDeck.php app/Models/AdminPreference.php app/Models/Vocabulary.php app/Models/User.php tests/Feature/AdminContentSchemaTest.php
git commit -m "feat: persist admin decks and preferences"
```

### Task 3: Implement authorized catalog APIs

**Files:**
- Modify: `routes/spa.php`
- Modify: `app/Http/Controllers/Api/V1/Admin/CatalogController.php`
- Modify: `tests/Feature/Api/V1/AdminCatalogApiTest.php`

- [ ] **Step 1: Register canonical routes**

Under the existing `google.admin` group add summary, level, topic, vocabulary, and deck list/create/update/delete routes. Every controller method begins with `abort_unless($request->user()->can('manage-content'), 403)`.

- [ ] **Step 2: Implement lists with bounded pagination/search**

Use `min(100, max(1, $request->integer('per_page', 20)))`; return `ApiResponse::success(data, meta)` and Eloquent counts. Never return per-learner relations.

- [ ] **Step 3: Implement mutations with validation, request id, and audit**

Refactor the existing private helpers in the same controller to accept an Eloquent `Model` and model class rather than hard-coding `Course`; do not add another service/trait. Validate actual fields and uniqueness. Deleting a deck removes only its membership pivot rows and the deck, never vocabulary. Its audit stores the deleted snapshot in `before_state`, `after_state = null`; an identical request-id/action/fingerprint replay returns the original `204`, while a mismatched reuse returns `409`. Return dependency `409` only when deleting a level/topic would orphan protected course/vocabulary content.

- [ ] **Step 4: Run catalog tests**

Run: `php artisan test tests/Feature/Api/V1/AdminCatalogApiTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/spa.php app/Http/Controllers/Api/V1/Admin/CatalogController.php tests/Feature/Api/V1/AdminCatalogApiTest.php
git commit -m "feat: add complete admin catalog contracts"
```

## Chunk 2: Learning, Imports, Operations, and Privacy

### Task 4: Add privacy-safe aggregate learning and report APIs

**Files:**
- Create: `app/Http/Controllers/Api/V1/Admin/LearningController.php`
- Modify: `routes/spa.php`
- Create: `tests/Feature/Api/V1/AdminLearningApiTest.php`

- [ ] **Step 1: Write failing aggregate/privacy/report tests**

Create `AdminLearningApiTest` first. Test Admin and Super Admin success, learner/unauthenticated/Google-marker failures, forbidden response keys, default 30-day range, inclusive 366-day boundary, rejection beyond 366 days, empty data, and CSV formula protection after leading whitespace/control characters.

- [ ] **Step 2: Run focused tests and confirm RED**

Run: `php artisan test tests/Feature/Api/V1/AdminLearningApiTest.php`

Expected: FAIL because routes/controller are absent.

- [ ] **Step 3: Add aggregate routes**

Register `GET /admin/learning/overview`, `/quizzes`, `/fsrs`, `/progress`, and `/reports.csv`, all guarded by `manage-content`.

- [ ] **Step 4: Implement aggregate queries from existing tables**

Return only the spec allowlist: counts, rates, average durations/scores/stability, mastery/state bands, course/content ids and titles, and date buckets. Default `to` to today UTC and `from` to 29 days earlier; parse both in UTC and query from `from.startOfDay()` through `to.endOfDay()`, with an inclusive maximum of 366 calendar days. Use portable `DATE(column)` grouping supported by both MySQL and SQLite plus `whereBetween`; never load a learner collection and redact afterward.

- [ ] **Step 5: Stream a real CSV report**

Use Laravel `response()->streamDownload()` and `fputcsv()` with columns `period,course_id,course_title,enrollments,started,completed,completion_rate,average_score`. Before testing the first character for `=`, `+`, `-`, or `@`, remove leading whitespace/control characters; prefix unsafe cell text with `'`.

- [ ] **Step 6: Run learning tests**

Run: `php artisan test tests/Feature/Api/V1/AdminLearningApiTest.php`

Expected: PASS with forbidden-field assertions.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/V1/Admin/LearningController.php routes/spa.php tests/Feature/Api/V1/AdminLearningApiTest.php
git commit -m "feat: expose privacy-safe admin learning analytics"
```

### Task 5: Add import, provider feed, notification, and preference APIs

**Files:**
- Create: `app/Http/Controllers/Api/V1/Admin/ContentOperationsController.php`
- Create: `app/Models/AdminImportRun.php`
- Create: `app/Models/AdminImportLock.php`
- Create: `app/Jobs/RunAdminImport.php`
- Create: `app/Services/AdminImportRunner.php`
- Modify: `app/Services/Import/AbstractLexiLingoImporter.php`
- Modify: `app/Services/Import/CategoryImporter.php`
- Modify: `app/Services/Import/CourseImporter.php`
- Modify: `app/Services/LexiLingoVocabularySync.php`
- Create: `database/migrations/2026_07_28_140000_create_admin_import_runs.php`
- Modify: `routes/spa.php`
- Modify: `tests/Feature/AdminContentSchemaTest.php`
- Create: `tests/Feature/Api/V1/AdminContentOperationsApiTest.php`
- Modify: `tests/Feature/LexiLingoClientTest.php`
- Modify: `tests/Feature/LexiLingoImportTest.php`

- [ ] **Step 1: Write failing content-operations tests**

Test checkpoint status, durable run start/status, identical replay, payload-mismatch `409`, concurrent same-entity `409`, Admin versus Super Admin reset, retry exhaustion, `429`/`Retry-After`, checkpoint preservation on failure, redacted/bounded error persistence, stable redacted `503`, notification field allowlist, notification read state, and preference validation.

- [ ] **Step 2: Run focused tests and confirm RED**

Run: `php artisan test tests/Feature/Api/V1/AdminContentOperationsApiTest.php tests/Feature/LexiLingoClientTest.php tests/Feature/LexiLingoImportTest.php`

Expected: FAIL only on the new admin workflow/redaction expectations.

- [ ] **Step 3: Add durable import-run and lock schema**

Create a new migration (never modify Task 2's committed migration) with `admin_import_runs`: UUID request id unique, entity, payload fingerprint, actor id, status (`pending|running|succeeded|failed`), requested limit/reset, starting cursor, processed/skipped/result cursor nullable, redacted error code/message nullable, timestamps, and an index on `(entity,status)`. Add `admin_import_locks(entity primary key, current_run_id nullable, locked_at nullable)` and seed exactly `categories`, `courses`, and `vocabulary` rows in the migration. Update `AdminContentSchemaTest`. The API exposes checkpoint status and individual run status; it does not pretend checkpoints are historical runs.

- [ ] **Step 4: Register ordinary and privileged routes**

Add checkpoint status, run status, start/resume import, reset import, feed search, notifications/read, and preferences read/update. Check `start-content-sync` for start/resume; check `retry-content-sync` plus recent Google verification for reset/checkpoint replacement.

- [ ] **Step 5: Reserve idempotency before dispatch**

Inside a transaction, select the pre-seeded entity lock row with `lockForUpdate()`, reject fingerprint mismatch, return the existing run on identical replay, and reject another pending/running run for that entity. Store the current checkpoint as `starting_cursor`, create the durable `pending` run, and point the lock row at it before dispatch. On MySQL the row lock serializes check-and-create; SQLite tests run the same transaction path and verify sequential conflict behavior under its database-wide writer serialization. If a lock references a pending/running run older than 15 minutes, atomically mark that run failed with `stale_run`, clear the lock, then permit a new reservation.

- [ ] **Step 6: Queue the shared locked runner**

`RunAdminImport` implements `ShouldQueue`, sets `$tries = 1`, and invokes `AdminImportRunner`; the existing `LexiLingoClient` is the sole HTTP retry/backoff owner. The runner verifies/locks the entity row, resolves only `CategoryImporter`, `CourseImporter`, or `LexiLingoVocabularySync` through an explicit `match`, and executes the run's fixed `starting_cursor`. Refactor all three importers to accept an optional explicit cursor; replaying a crashed run therefore upserts the same page and advances the checkpoint with `max(current_cursor, result_cursor)` rather than importing the next page. The job stores result/audit and clears the entity lock on success. Its `failed(Throwable)` marks the run terminally failed and clears only its own lock. Production requires a queue worker. When `QUEUE_CONNECTION=sync` in local/test, cap at 10 items and still use the same runner/lock path. Never shell out to Artisan.

- [ ] **Step 7: Implement rate-limit, failure, and notification privacy semantics**

Keep provider `429`/`Retry-After` handling solely in the existing `LexiLingoClient`; after its bounded retries are exhausted, the job fails once and never advances the checkpoint. Update `AbstractLexiLingoImporter` to `updateOrCreate` a failure by entity/external id and persist only a bounded payload allowlist/size, error code, and bounded message—never credentials, headers, tokens, learner evidence, or raw unbounded payloads. Return a stable redacted `503` to clients. Feed responses are read-only and bounded. Notification DTOs allow only alert id, rule key/type, severity, state, created/resolved timestamps, and a generic summary; exclude `learner_id`, learner identity, and `evidence` JSON. Reads and preferences use the new narrow tables.

- [ ] **Step 8: Run tests and commit**

Run: `php artisan test tests/Feature/Api/V1/AdminContentOperationsApiTest.php tests/Feature/LexiLingoClientTest.php tests/Feature/LexiLingoImportTest.php`

Expected: PASS including Admin/Super Admin split, rate-safe limits, replay, and provider failure.

```bash
git add app/Http/Controllers/Api/V1/Admin/ContentOperationsController.php app/Models/AdminImportRun.php app/Models/AdminImportLock.php app/Jobs/RunAdminImport.php app/Services/AdminImportRunner.php app/Services/Import/AbstractLexiLingoImporter.php app/Services/Import/CategoryImporter.php app/Services/Import/CourseImporter.php app/Services/LexiLingoVocabularySync.php database/migrations/2026_07_28_140000_create_admin_import_runs.php routes/spa.php tests/Feature/AdminContentSchemaTest.php tests/Feature/Api/V1/AdminContentOperationsApiTest.php tests/Feature/LexiLingoClientTest.php tests/Feature/LexiLingoImportTest.php
git commit -m "feat: add admin content operations contracts"
```

### Task 6: Complete Super Admin evidence, audit, and dashboard contracts

**Files:**
- Modify: `app/Policies/LearningPolicy.php`
- Modify: `app/Http/Controllers/Api/V1/TeacherController.php`
- Modify: `app/Http/Controllers/Api/V1/Admin/OperationsController.php`
- Modify: `routes/spa.php`
- Modify: `tests/Feature/RoleCapabilityTest.php`
- Modify: `tests/Feature/Api/V1/OperationsApiTest.php`
- Modify: `tests/Feature/Api/V1/TeacherApiTest.php`

- [ ] **Step 1: Write failing operations and evidence tests**

In `TeacherApiTest`, cover teacher assignment/no-assignment on the retained GET route, ordinary Admin forbidden, and the new Super Admin POST access route: missing reason `422`, valid reason/request id success, allowed returned fields, and one audit. A reused request id returns `409`; every fresh access requires a fresh UUID and creates a fresh audit, so no changed evidence can be returned under an old audit. In `OperationsApiTest`, add overview/audit filter/redaction cases.

- [ ] **Step 2: Run focused tests and confirm RED**

Run: `php artisan test tests/Feature/Api/V1/TeacherApiTest.php tests/Feature/RoleCapabilityTest.php tests/Feature/Api/V1/OperationsApiTest.php`

Expected: FAIL on the new Super Admin reason/audit and response cases.

- [ ] **Step 3: Make evidence authorization explicit**

Keep the teacher `GET /teacher/learners/{learner}/evidence` route and its assignment behavior unchanged. Add `POST /admin/users/{learner}/evidence` to `TeacherController::operationalEvidence()` inside `google.admin`; ordinary Admin is forbidden, while Super Admin may bypass assignment only for the named route learner after validating a JSON-body `reason` as `required|string|min:3|max:500` and UUID `X-Request-ID`. Store actor, learner target, reason, returned field names, timestamp, and request id. Any reused request id returns `409`; the client generates a fresh id for each deliberately repeated access, producing a fresh audit before returning the current response.

- [ ] **Step 4: Make audit list real and filterable**

Extend `audits()` with validated `action`, `search`, `from`, `to`, `page`, and bounded `per_page`; return actor display data but never IP, secrets, tokens, raw prompts, transcripts, or recordings.

- [ ] **Step 5: Extend the Super Dashboard response**

Reuse `overview()`, `usage()`, `contracts()`, quota, and alert queries. Return users/courses totals, service booleans, open alert count, usage totals, environment name, and safe configuration booleans only.

- [ ] **Step 6: Run security tests**

Run: `php artisan test tests/Feature/Api/V1/TeacherApiTest.php tests/Feature/RoleCapabilityTest.php tests/Feature/Api/V1/OperationsApiTest.php`

Expected: PASS; Admin receives `403`, Super Admin evidence without reason receives `422`, secrets never appear.

- [ ] **Step 7: Run all backend admin tests and commit**

Run: `php artisan test tests/Feature/AdminContentSchemaTest.php tests/Feature/Api/V1/AdminCatalogApiTest.php tests/Feature/Api/V1/AdminLearningApiTest.php tests/Feature/Api/V1/AdminContentOperationsApiTest.php tests/Feature/Api/V1/AdminUserApiTest.php tests/Feature/Api/V1/TeacherApiTest.php tests/Feature/Api/V1/OperationsApiTest.php tests/Feature/LexiLingoClientTest.php tests/Feature/LexiLingoImportTest.php tests/Feature/RoleCapabilityTest.php tests/Feature/GoogleAdminMiddlewareTest.php tests/Feature/AdminGoogleLoginTest.php`

Expected: PASS.

```bash
git add app/Policies/LearningPolicy.php app/Http/Controllers/Api/V1/TeacherController.php app/Http/Controllers/Api/V1/Admin/OperationsController.php routes/spa.php tests/Feature/Api/V1/TeacherApiTest.php tests/Feature/RoleCapabilityTest.php tests/Feature/Api/V1/OperationsApiTest.php
git commit -m "feat: complete super admin security contracts"
```

## Chunk 3: Full Admin Frontend

### Task 7: Build the complete role-aware shell and canonical routes

**Files:**
- Modify: `admin-frontend/src/components/Sidebar.tsx`
- Modify: `admin-frontend/src/components/AdminLayout.tsx`
- Create: `admin-frontend/src/lib/admin-navigation.mjs`
- Modify: `admin-frontend/src/app/globals.css`
- Create: `admin-frontend/src/app/super-admin/page.tsx`
- Modify: `admin-frontend/src/app/roles/page.tsx`
- Modify: `admin-frontend/src/app/progress/page.tsx`
- Modify: `admin-frontend/src/app/quiz/page.tsx`
- Modify: `admin-frontend/src/app/vocabulary/page.tsx`
- Modify: `admin-frontend/src/app/vocabulary-sets/page.tsx`
- Create: `admin-frontend/scripts/check-admin-navigation.mjs`
- Modify: `admin-frontend/package.json`

- [ ] **Step 1: Read the installed Next.js App Router references**

Read `admin-frontend/node_modules/next/dist/docs/01-app/03-api-reference/04-functions/redirect.md` and the relevant routing/layout guide before editing, per `admin-frontend/AGENTS.md`.

- [ ] **Step 2: Write the dependency-free navigation check**

Put plain-data groups and aliases in `src/lib/admin-navigation.mjs`, which both `Sidebar.tsx` and the Node script import. The checker asserts every canonical page exists, Admin cannot see Super entries, Super Admin sees both zones, and reads each alias page source to prove it calls the expected server `redirect()` for `/user-progress`, `/quizzes`, `/flashcards`, and `/decks`.

- [ ] **Step 3: Run check and confirm RED**

Run: `cd admin-frontend && npm run check:navigation`

Expected: FAIL because the complete manifest and `/super-admin` do not exist.

- [ ] **Step 4: Implement one static navigation manifest**

Use the exact groups/routes in the approved spec. Filter only the Super Admin group by role. Keep pathname-based active state because the duplicate hash entry was removed. Use native `<nav>`, accessible labels, existing Material Symbols, existing mobile drawer, and scrollable sidebar.

- [ ] **Step 5: Gate child data mounting behind the shared session check**

Keep `AdminLayout` as the single shell/session gate, but move each page's hooks into a nested `PageContent` component passed as its child. React does not mount `PageContent` until `AdminLayout` reaches `ready` and returns `children`; thus no protected request fires before `adminMe()` and role validation. Apply this structure to every page touched in Tasks 9–10 and explicitly to new `/super-admin` and existing `/roles`; both Super Admin pages complete `requiredRole="super_admin"` before content mounts.

- [ ] **Step 6: Implement aliases with server redirects**

Each alias page contains only `redirect('/canonical-route')` from `next/navigation`; retain files and URLs, but no duplicate UI or API calls.

- [ ] **Step 7: Create `/super-admin` from existing operation clients**

Compose existing stat cards and panels; no chart dependency. Show unavailable per metric when an individual request fails; require `requiredRole="super_admin"`; never show secrets.

- [ ] **Step 8: Run navigation check, type-check, build, and commit**

Run: `cd admin-frontend && npm run check:navigation && npm run lint && npx tsc --noEmit && npm run build`

Expected: PASS.

```bash
git add admin-frontend/src/components/Sidebar.tsx admin-frontend/src/components/AdminLayout.tsx admin-frontend/src/lib/admin-navigation.mjs admin-frontend/src/app/globals.css admin-frontend/src/app/super-admin/page.tsx admin-frontend/src/app/roles/page.tsx admin-frontend/src/app/progress/page.tsx admin-frontend/src/app/quiz/page.tsx admin-frontend/src/app/vocabulary/page.tsx admin-frontend/src/app/vocabulary-sets/page.tsx admin-frontend/scripts/check-admin-navigation.mjs admin-frontend/package.json
git commit -m "feat: expose complete role-aware admin navigation"
```

### Task 8: Replace learner-era API clients with typed admin contracts

**Files:**
- Modify: `admin-frontend/src/lib/api.ts`

- [ ] **Step 1: Add exact response types**

Define `AdminSummary`, `AdminLevel`, `AdminTopic`, `AdminVocabulary`, `VocabularyDeck`, `AdminLearningOverview`, `AdminImportCheckpoint`, `AdminImportRun`, `AdminNotification`, `AdminPreferences`, and paginated audit types matching backend JSON. Keep checkpoint state (current entity cursor/last sync/failures) distinct from durable run state (request id/status/start/result/error).

- [ ] **Step 2: Add grouped `/api/v1/admin/*` clients**

Expose `adminCatalog`, `adminLearning`, `adminImports`, `adminFeed`, `adminNotifications`, and `adminPreferences`. `adminImports` maps `checkpoints()`, `run(id)`, `start(entity,limit)`, `resume(entity,limit)`, and Super Admin `reset(entity)` to their exact backend routes. Reuse `request()`, XSRF handling, `ApiError`, and `crypto.randomUUID()` for writes.

Add `adminUsers.evidence(id, reason)`: typed `POST /api/v1/admin/users/{id}/evidence`, JSON `{ reason }`, and a fresh UUID `X-Request-ID` for each deliberate access. Treat `409` as a consumed access id and require a deliberate retry, generating a new UUID and new audit.

- [ ] **Step 3: Remove admin references to fictitious learner-era paths**

Delete `words`, `sets`, `fsrs`, `progress`, `quiz`, `streak`, `notifications`, `importJobs`, `content`, `topics`, `wordOfDay`, and `flashcards` only after all callers are migrated in Tasks 9–10. Do not change learner frontend clients. Finish with `rg -n "/api/(words|sets|fsrs|progress|quiz|streak|notifications|import|content|topics|word-of-the-day|flashcards)" admin-frontend/src` and require no matches; this catches single quotes, double quotes, template literals, `request`, and direct `fetch`.

- [ ] **Step 4: Type-check and commit with the first migrated callers**

Run after Task 9: `cd admin-frontend && npx tsc --noEmit`.

Expected: PASS.

### Task 9: Connect catalog and content pages end to end

**Files:**
- Modify: `admin-frontend/src/app/dashboard/page.tsx`
- Modify: `admin-frontend/src/app/courses/page.tsx`
- Modify: `admin-frontend/src/app/levels/page.tsx`
- Modify: `admin-frontend/src/app/topics/page.tsx`
- Modify: `admin-frontend/src/app/flashcards/page.tsx`
- Modify: `admin-frontend/src/app/decks/page.tsx`
- Modify: `admin-frontend/src/app/import/page.tsx`
- Modify: `admin-frontend/src/app/content/page.tsx`
- Modify: `admin-frontend/src/lib/api.ts`

- [ ] **Step 1: Migrate reads to canonical clients**

Preserve existing layouts and forms. Normalize pagination to one-based API pages, abort stale effects, and render real loading/error/empty states.

- [ ] **Step 2: Migrate writes with explicit success/error behavior**

Disable submit during requests, surface Laravel validation messages, update/reload only after success, confirm destructive deletes, and never display a successful state before the API resolves.

- [ ] **Step 3: Enforce import role behavior in the UI**

Admin sees start/resume. Super Admin additionally sees reset/retry. Backend `403` remains authoritative. Cap limit input at 100 and display processed/skipped/checkpoint values.

- [ ] **Step 4: Run frontend checks**

Run: `cd admin-frontend && npm run lint && npx tsc --noEmit && npm run build`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add admin-frontend/src/lib/api.ts admin-frontend/src/app/dashboard/page.tsx admin-frontend/src/app/courses/page.tsx admin-frontend/src/app/levels/page.tsx admin-frontend/src/app/topics/page.tsx admin-frontend/src/app/flashcards/page.tsx admin-frontend/src/app/decks/page.tsx admin-frontend/src/app/import/page.tsx admin-frontend/src/app/content/page.tsx
git commit -m "feat: connect admin catalog and import interfaces"
```

### Task 10: Connect learning, account, and Super Admin pages

**Files:**
- Modify: `admin-frontend/src/app/analytics/page.tsx`
- Modify: `admin-frontend/src/app/quizzes/page.tsx`
- Modify: `admin-frontend/src/app/spaced-repetition/page.tsx`
- Modify: `admin-frontend/src/app/user-progress/page.tsx`
- Modify: `admin-frontend/src/app/reports/page.tsx`
- Modify: `admin-frontend/src/app/notifications/page.tsx`
- Modify: `admin-frontend/src/app/settings/page.tsx`
- Modify: `admin-frontend/src/app/audit-logs/page.tsx`
- Modify: `admin-frontend/src/app/operations/page.tsx`
- Modify: `admin-frontend/src/app/users/page.tsx`
- Modify: `admin-frontend/src/app/users/[id]/page.tsx`
- Modify: `admin-frontend/src/lib/api.ts`

- [ ] **Step 1: Replace every mock/static live value**

Remove `MOCK_LOGS`, simulated report export, administrator-personal FSRS/progress calls, and static metrics presented as live. Use the aggregate/admin contracts. Every migrated page must independently render loading, retryable error, empty, and ready states; one panel failure must not blank unrelated panels.

- [ ] **Step 2: Wire native CSV download**

Build query with `URLSearchParams`, fetch with credentials, reject non-OK responses, create an object URL from the blob, click a temporary anchor, and revoke the URL. No export library.

- [ ] **Step 3: Wire notifications/preferences**

Render operational notifications only. Save administrator preferences through the typed contract; do not place platform secrets or model keys in browser-editable settings.

- [ ] **Step 4: Add Super Admin evidence action to user detail**

Require a reason field before request; never preload evidence. Display only returned allowed fields and show that access was audited. Ordinary Admin must not see the action.

- [ ] **Step 5: Wire real audit and operations controls**

Set `/audit-logs` to `requiredRole="super_admin"`; its filters query the server. Operations keeps the existing sections and adds stable ids for in-page access. Replace its all-or-nothing `Promise.all` with per-panel `Promise.allSettled` state so healthy panels survive one failed request. Quota/alert and user-role mutations handle API status `428` by directing the user through the existing admin OAuth reauthentication flow. On `/users`, ordinary Admin sees read-only rows and no role mutation trigger; Super Admin retains the role dialog.

- [ ] **Step 6: Run all frontend checks and commit**

Run: `cd admin-frontend && npm run check:navigation && npm run lint && npx tsc --noEmit && npm run build`

Expected: PASS with no references to removed learner-era admin endpoints. Also run: `rg -n "/api/(words|sets|fsrs|progress|quiz|streak|notifications|import|content|topics|word-of-the-day|flashcards)" admin-frontend/src` and expect no matches.

```bash
git add admin-frontend/src/lib/api.ts admin-frontend/src/app/analytics/page.tsx admin-frontend/src/app/quizzes/page.tsx admin-frontend/src/app/spaced-repetition/page.tsx admin-frontend/src/app/user-progress/page.tsx admin-frontend/src/app/reports/page.tsx admin-frontend/src/app/notifications/page.tsx admin-frontend/src/app/settings/page.tsx admin-frontend/src/app/audit-logs/page.tsx admin-frontend/src/app/operations/page.tsx admin-frontend/src/app/users/page.tsx admin-frontend/src/app/users/[id]/page.tsx
git commit -m "feat: complete admin learning and operations interfaces"
```

## Chunk 4: System Verification and Production Rollout

### Task 11: Execute route, role, responsive, and regression verification

**Files:**
- Modify only if a verified defect is found.

- [ ] **Step 1: Run the complete backend suite**

Run: `php artisan test`

Expected: PASS.

- [ ] **Step 2: Run complete Admin frontend checks**

Run: `cd admin-frontend && npm run check:navigation && npm run lint && npx tsc --noEmit && npm run build`

Expected: PASS.

- [ ] **Step 3: Run route inventory**

Run: `php artisan route:list --path=api/v1/admin -vv`

Expected: every spec manifest primary contract is present; verbose output shows `web`, `auth`, and `google.admin`. Feature tests prove the per-action capability gates, which are controller checks rather than route middleware.

- [ ] **Step 4: Run local browser verification with @vercel:verification**

Against local Laravel and Admin frontend builds, check Admin and Super Admin at 390×844, 768×1024, and 1440×900. Verify drawer/sidebar scrolling, every navigation link, alias redirect, Admin `403` behavior, Super Admin dual-dashboard access, loading/error/empty states, and mocked provider failure presentation.

- [ ] **Step 5: Review the final diff with @requesting-code-review**

Review specifically for fake data, client-only authorization, secret leakage, unbounded queries, missing request ids, destructive import behavior, dead legacy clients, and unrelated changes. Preserve user-owned `.gitignore` and `docs/api_docs_lexilingo.md` changes.

- [ ] **Step 6: Commit only verified fixes**

If verification changed files, stage their explicit paths, rerun the affected check plus full build/tests, and commit `fix: close admin end-to-end verification gaps`. If no defect was found, do not create an empty verification commit.

### Task 12: Deploy safely and smoke-test production

**Files:**
- Modify: `docker/fly/supervisord.conf`
- Modify: `fly.toml`
- Modify: `docs/PRODUCTION_ENV.md`

- [ ] **Step 1: Provision the durable production queue worker**

Add a supervised `php artisan queue:work database --sleep=2 --tries=1 --timeout=300 --max-time=3600` process beside PHP-FPM/nginx and change production `QUEUE_CONNECTION` from `sync` to `database`. Set `DB_QUEUE_RETRY_AFTER=360`, safely above the worker timeout. Configure the Supervisor worker with `stopasgroup=true`, `killasgroup=true`, and `stopwaitsecs=330` so rolling deploys allow active imports to finish before termination. Supervisor restarts the worker; the existing database jobs table survives app restarts. Keep import-run entity locks as the concurrency authority; verify worker logs and durable run-state freshness rather than adding a heartbeat subsystem. Update `docs/PRODUCTION_ENV.md` to replace its temporary sync-queue rule with the database-worker settings and operational check. Run the focused import tests and local container smoke before commit.

```bash
git add docker/fly/supervisord.conf fly.toml docs/PRODUCTION_ENV.md
git commit -m "ops: run durable production import worker"
```

- [ ] **Step 2: Capture rollback references and production configuration**

Run `git status --short`, record the current Fly release/image, current Vercel production deployment, and a fresh database backup identifier before rollout. Verify no secrets are tracked. Confirm Admin/Super Admin whitelist variables, Google redirect URLs, LexiLingo server-to-server credentials, frontend API proxy, and database queue configuration.

- [ ] **Step 3: Deploy backend through Fly's release command**

Run `fly deploy`; do not manually rerun migrations because `fly.toml` already owns `php artisan migrate --force` as its `release_command`. Verify `php artisan migrate:status` through `fly ssh console`, application `/health` and `/api/v1/health`, authenticated `/api/v1/admin/session`, worker process/log freshness, and one queued bounded import reaching a terminal run state. Verify upstream LexiLingo through the authenticated Super Admin service-probe contract, not `/backend-health`. Do not reset or reseed production data.

- [ ] **Step 4: Create and verify a Vercel preview**

Deploy `admin-frontend` as a preview linked to the existing Vercel project; do not create another project. Because the single strict `ADMIN_FRONTEND_URL` intentionally sends OAuth handoff only to production, verify the preview's login shell, assets, routing/build output, and unauthenticated redirects without weakening the callback allowlist. Perform authenticated Google/role checks only after promotion.

- [ ] **Step 5: Promote the verified preview**

Promote/alias only the verified deployment to `admin-linguist-nova.vercel.app`. Verify `nhthang312@gmail.com` Google login, Admin/Super Admin role behavior, navigation, real dashboard totals, one read from every menu group, CSV report, service monitoring, evidence audit, and logout. For the catalog mutation, create uniquely named disposable data and delete it immediately. Before quota/alert checks, capture the active values; after testing, create the required new version restoring the exact prior values and verify it is active.

- [ ] **Step 6: Verify CSRF without leaving production changes**

Read and retain the administrator's original preference JSON. Send the same reversible preference update without `X-XSRF-TOKEN`, expect `419`, then read again and assert no change. Perform a normal-client update to a harmless UI preference using any required fresh request id, verify success, and immediately restore/verify the original JSON through the normal client. Never use catalog deletion.

- [ ] **Step 7: Define and retain rollback order**

If rollback is needed, first repoint the Admin alias to the recorded previous Vercel deployment, then redeploy the recorded previous Fly image/release. Do not run `migrate:rollback` in production. Prefer a forward fix for additive schema; use the recorded database backup only for confirmed data corruption after stopping writes/import workers.

- [ ] **Step 8: Record outcome**

Report deployed commit, Fly release/image, migration and worker status, Vercel deployment, database backup id, tested URLs, role used, any intentionally unavailable provider state, and rollback references. Never print credentials.
