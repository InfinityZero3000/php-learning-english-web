# Laravel, Next.js, and LexiLingo Integration Design

## Goal

Turn the current Laravel skeleton and the two imported Next.js applications
into one English-learning system. Laravel owns users and business data;
LexiLingo supplies importable learning content and optional real-time AI
services.

## System Boundaries

### Laravel

Laravel is the only business backend. It owns authentication, email
verification, password reset, profiles, authorization, courses, vocabulary,
quizzes, bookmarks, progress, review scheduling, admin operations, and the
local MySQL dataset.

### Next.js

`frontend/` is the learner application and `admin-frontend/` is the
administration application. Both call relative `/api/*` URLs. Vercel rewrites
those requests to Laravel on Fly.io, keeping browser requests same-origin and
service URLs out of client bundles.

### LexiLingo

LexiLingo is not an identity provider or source of user state. Laravel imports
categories, courses, units, lessons, and vocabulary through server-to-server
requests. Translate, pronunciation, STT, and TTS remain live server-to-server
integrations because their outputs are generated on demand. AI tutor is
conditional on a future external-subject contract.

## Data Model

Keep existing numeric Laravel primary keys and add unique nullable
`external_id` strings to imported entities. IDs are opaque strings at the
integration boundary even though the pinned LexiLingo source currently uses
UUIDs. Add `course_categories` and `units`; categories are not mapped to
Laravel topics because course taxonomy and vocabulary topics are different
concepts. Extend course, lesson, and vocabulary metadata only for fields
provided by the approved API contract:

- Course category: name, slug, description, icon, color and order.
- Course: language, thumbnail, duration, XP.
- Unit: course, title, description, order, icon and background color.
- Lesson: unit, type, duration, XP, pass threshold and JSON-shaped content
  stored in the existing `longText` column for backward compatibility.
- Vocabulary: definition, translation JSON, pronunciation, part of speech,
  CEFR difficulty, tags and external audio URL.

Existing migrations are not rewritten; a new forward migration changes the
schema.

Migration order:

1. Create `course_categories` and `units`.
2. Add nullable `category_id` to courses and nullable `unit_id` to lessons.
3. Add the import metadata fields and unique nullable `external_id` indexes.
4. Create one `Legacy` unit for every existing course with `sort_order = -1`
   and attach its existing lessons.
5. Keep `lessons.course_id` during this release for compatibility and validate
   that a selected unit belongs to the same course. Removing the redundant
   column is a later measured migration, not part of this integration.

FSRS requires owned user state. Add `user_vocabularies` with a unique
`(user_id, vocabulary_id)` pair, due date, state, stability, difficulty,
scheduled/elapsed days, repetitions and lapses. Add append-only
`vocabulary_reviews` for rating, response time and scheduling snapshots.

## API Contract

Laravel APIs use `/api/v1`. Success responses use:

```json
{
  "data": {},
  "meta": {}
}
```

Validation and business errors use:

```json
{
  "message": "Human-readable message",
  "errors": {}
}
```

Authentication uses Laravel's native `web` session middleware, not a second
JWT system. First-party JSON routes run under the `web` middleware so sessions
and CSRF remain active. The frontend never stores a privileged token in local
storage. Admin routes additionally require role/policy authorization.

Compatibility aliases for the old FSRSpring endpoint names are avoided.
Frontend API clients are updated to the canonical Laravel contract so the
application has one documented API surface.

Phase 1 must check in `docs/openapi/laravel-v1.yaml` with every endpoint,
method, request, response, error and authorization requirement used by either
Next.js app. CI validates the document and frontend/backend work does not begin
until this versioned Laravel OpenAPI contract passes review.

## Pinned LexiLingo Import Contract

The initial contract is pinned to
`https://github.com/InfinityZero3000/LexiLingo/tree/4f74be584a6181acc90dcd72caaae3f47ab3ace1`.
Phase 1 checks in `docs/openapi/lexilingo-import.schema.json`, derived from that
revision, and records its SHA-256 in the importer. Every response is validated
against the schema; contract drift fails rather than silently changing data.

| Resource | Endpoint | Auth | Pagination | Exact JSON mapping |
|---|---|---|---|---|
| Category list | `GET /api/v1/categories?active_only=true` | Public | One `data[]`, pinned maximum 100 | `data[].id:string → external_id`; `name:string`; `slug:string`; nullable `description/icon/color`; `course_count:int` |
| Category detail | `GET /api/v1/categories/{id}` | Public | None | `data.order_index:int → sort_order`; `data.is_active:bool → status`; remaining fields match the list |
| Category courses | `GET /api/v1/categories/{id}/courses?page={n}&page_size=100` | Public | `data[]`, `pagination.total_pages:int` | category request `{id} → courses.category_id`; course `id:string → external_id`; `title`; nullable `description`; `language`; `level`; `tags:array`; nullable `thumbnail_url`; `total_lessons:int`; `total_xp:int`; `estimated_duration:int` |
| All courses | `GET /api/v1/courses?page={n}&page_size=100` | Public | `data[]`, `pagination.total_pages:int` | same course fields; records absent from category pages retain null category |
| Unit/Lesson outline | `GET /api/v1/courses/{course_id}` | Public | None | `data.units[].id:string → unit.external_id`; unit `title`, nullable `description`, `order_index:int`, nullable `background_color/icon_url`; `units[].lessons[].id:string → lesson.external_id`; lesson `title`, `order_index:int`, `lesson_type`, `xp_reward:int` |
| Lesson content | `GET /api/v1/admin/lessons/{lesson_id}` | scoped import key | None | `data.description`; `prerequisites:array<string>`; `estimated_minutes:int`; `pass_threshold:int`; nullable `content:any` |
| Vocabulary | `GET /api/v1/vocabulary/items?limit=100&offset={n}` | Public | Bare array; advance offset by count, stop when count `<100` | `[].id:string → external_id`; nullable `course_id/lesson_id:string → related external_id`; `word`; `definition`; nullable `translation:object`, `pronunciation`, `audio_url`; `part_of_speech`; `difficulty_level`; nullable `tags:array`; `usage_frequency:int` |

Category responses use `{data, meta}`; course lists use
`{data, pagination, meta}`; course detail and admin lesson use `{data, meta}`;
vocabulary returns a bare list in the pinned source.

Protected lesson export must use a non-expiring, rotatable, read-only
`content:read` service credential sent as `X-Import-Key`. It is stored as
`LEXILINGO_IMPORT_KEY` only on Fly and must not grant admin mutation access.
This scoped credential/export contract is a prerequisite owned by LexiLingo.
Until it exists, the importer runs public-only, imports lesson outlines with
null content only for newly discovered lessons, and reports the protected phase
as skipped. Public-only updates omit all protected content columns so a later
run cannot erase previously synchronized content. A 30-minute user/admin access
token is explicitly not supported for recurring sync.

## Dataset Synchronization

An idempotent Artisan command imports in dependency order:

```text
categories → courses → units → lessons → vocabulary
```

Each resource is validated before `upsert()`. Invalid records are logged and do
not prevent valid records from importing. Secrets and base URLs come from
server environment configuration. The importer handles pagination, timeouts,
and retryable transport failures. It never imports users, tokens, progress,
notifications, or other personal data.

For records with `external_id`, upstream owns imported fields; local admins may
publish/unpublish them but may not overwrite synchronized source fields.
Locally created records have no `external_id` and are never touched. Each page
imports in one database transaction and records a checkpoint. Only after every
page of a full run succeeds are upstream records missing from the completed
snapshot marked `archived`; records are never hard-deleted by sync.

## Real-Time LexiLingo Services

Laravel exposes narrow application-specific endpoints instead of passing
arbitrary upstream paths. Each endpoint validates input, authorizes the user,
applies a rate limit, sends a server-side authenticated request, and returns a
stable response. Upstream timeouts or failures return `503` without exposing
credentials or internal responses.

Initial stateless integrations:

| Laravel endpoint | Upstream | Upstream auth | Limits | Timeout/error |
|---|---|---|---|---|
| `POST /api/v1/services/translate` | `GET /api/v1/ai/translate` | service secret | 5,000 UTF-8 chars | 10s; map failure to 503 |
| `POST /api/v1/services/pronunciation` | `POST /api/v1/stt/assess-pronunciation` | service secret | audio ≤10 MB, approved audio MIME | 30s; map failure to 503 |
| `POST /api/v1/services/stt` | `POST /api/v1/stt/transcribe` | service secret | audio ≤10 MB, approved audio MIME | 30s; map failure to 503 |
| `POST /api/v1/services/tts` | `POST /api/v1/tts/synthesize` | service secret | text ≤2,000 chars | 30s; stream approved audio MIME |

All require an authenticated Laravel user, use per-user rate limits, redact
payloads and credentials from logs, and enforce a daily configurable quota.
Binary responses are streamed and never parsed as JSON. AI tutor chat is
deferred until LexiLingo supports a service-owned external subject mapping;
Laravel user IDs are never sent as if they were LexiLingo user IDs.

## Auth and Mail

The existing web authentication logic remains the behavioral base. JSON API
controllers cover registration, login, logout, current user, email
verification, resend verification, forgot/reset password, profile update,
password change, and account deletion. Existing rate limits and password
lifecycle protections remain enforced.

Mail is provided through Laravel notifications and the configured mailer.
Production credentials remain Fly secrets. Tests fake notifications and do not
send real email.

Both Vercel apps call relative URLs through rewrites. Sessions use secure,
host-only cookies (`SESSION_DOMAIN=null`, `SESSION_SECURE_COOKIE=true`,
`SameSite=Lax`). `GET /api/v1/csrf-cookie` initializes the CSRF cookie and
clients send its value as `X-XSRF-TOKEN` on mutations. The admin client removes
`NEXT_PUBLIC_API_URL`, `admin_token`, and all `localStorage` bearer behavior.

Verification links may terminate on the Fly signed endpoint and then redirect
to `FRONTEND_URL`. Password-reset notifications link to
`FRONTEND_URL/reset-password?token=...&email=...`; the frontend submits the
token to Laravel. Fly secrets define mail credentials and `FRONTEND_URL`.

## Frontend Migration

Migration is screen-by-screen:

1. Shared API client and auth state.
2. Login/current-user/profile as the first vertical slice.
3. Dashboard and catalog.
4. Vocabulary and review.
5. Quiz and progress.
6. Admin login, dashboard and CRUD screens.
7. Optional AI/speech surfaces.

Unimplemented controls must be disabled or hidden rather than calling missing
endpoints. Each migrated screen receives loading, empty, validation, forbidden,
and transport-error states.

Blade remains a transitional fallback until the equivalent Next.js critical
path passes browser smoke tests. It is then removed in a separate cleanup.

## Deployment

Vercel hosts two projects from the same repository with root directories
`frontend` and `admin-frontend`. Both receive a server-only Laravel origin used
by rewrites. Fly builds Laravel, runs forward-only migrations, and serves
the existing `/health` plus a JSON `/api/v1/health` alias. Before broader
feature work, both Vercel projects must successfully reach the API health alias
through their rewrites. Secrets are configured outside Git.

Imported thumbnails and audio remain external media dependencies in the first
release. URLs must be HTTPS and match an explicit host allowlist before being
stored. MIME and size are checked when proxied. Mirroring media requires owned
object storage and is deferred; the system only promises local availability
for database content in this release.

## Verification

- Migration and model relationship tests.
- API feature tests for authentication, authorization, catalog, learning and
  admin behavior.
- Importer tests using Laravel HTTP fakes, including pagination and reruns.
- LexiLingo proxy tests for success, timeout, upstream error and authorization.
- `php artisan test` and Pint.
- `pnpm lint` and `pnpm build` in both Next.js applications.
- Browser smoke tests for learner and admin critical paths.
