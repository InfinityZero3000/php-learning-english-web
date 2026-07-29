# API Route vs OpenAPI Documentation Gap Log

Date: 2026-07-29

## Purpose

Handoff artifact for issue #26 ("docs: hoàn thiện tài liệu API và hướng dẫn
sử dụng"). Produced while doing the regression-test pass for issue #28 —
lists every `/api/v1` and `/api/admin` route as verified against
`docs/openapi/laravel-v1.yaml` and the `tests/Feature/` suite. Issue #26
should treat this as its starting checklist, not re-derive it.

## Method

Compared every route registered in `routes/api.php` and `routes/spa.php`
against the path list in `docs/openapi/laravel-v1.yaml`, as of commit
`d070a64c4f7f1c2b8f7cae4ee5844aa778601aa0` on branch
`feature/28-api-regression-postman`.

## Documented in OpenAPI (already covered)

| Route group | Paths |
|---|---|
| Health/CSRF/root | `/api/v1/health`, `/api/v1/csrf-cookie` |
| Vocabulary | `GET /api/v1/vocabulary` |
| Auth | `register`, `login`, `logout`, `me`, `email/resend`, `email/verify/{id}/{hash}`, `password/forgot`, `password/reset` |
| Profile | `PUT /profile`, `PUT /profile/password` |
| Admin user management | `/api/admin/users*` (list/detail/history/lock/unlock/reset-password/role), `/api/admin/audit-logs` |
| AI proxy | `/api/v1/ai/translate`, `/pronunciation`, `/speech-to-text`, `/text-to-speech` |

## Missing from the OpenAPI spec entirely

These routes exist and are exercised by the automated test suite (and now
the Postman collection), but have **no** entry in
`docs/openapi/laravel-v1.yaml`:

| Route group | Paths | Test coverage |
|---|---|---|
| Root/misc | `GET /api/v1/`, `GET /api/v1/content/news`, `GET /api/v1/content/youtube`, `POST /api/v1/enrichment/words/{vocabulary}` | `tests/Feature/Api/V1/MiscApiTest.php` |
| OAuth | `GET /api/v1/auth/oauth/{provider}`, `GET /api/v1/auth/oauth/{provider}/callback` | `tests/Feature/Api/V1/OAuthApiTest.php` |
| Profile | `DELETE /api/v1/profile` | `tests/Feature/Api/V1/ProfileApiTest.php` |
| Catalog | `GET /api/v1/catalog/courses`, `/courses/{course}`, `/courses/{course}/lessons`, `/lessons`, `/lessons/{lesson}` | `tests/Feature/Api/V1/CatalogApiTest.php` |
| Bookmarks | `GET /api/v1/bookmarks`, `POST /bookmarks/vocabulary/{vocabulary}/toggle`, `POST /bookmarks/lesson/{lesson}/toggle` | `tests/Feature/Api/V1/BookmarkApiTest.php` |
| Quizzes | `GET /api/v1/quizzes/{quiz}`, `POST /quizzes/{quiz}/submit`, `GET /quizzes/{quiz}/history` | `tests/Feature/Api/V1/QuizApiTest.php` |
| Progress | `GET /api/v1/progress`, `/progress/dashboard`, `/progress/course/{course}`, `POST /progress/lesson/{lesson}/complete` | `tests/Feature/Api/V1/ProgressApiTest.php` |
| Admin taxonomy | `GET/POST/PUT/DELETE /api/v1/admin/topics*`, `/levels*`, `/categories*` | `tests/Feature/Api/V1/AdminTaxonomyTest.php` |
| Admin media | `POST /api/v1/admin/media/upload`, `DELETE /api/v1/admin/media/{path?}` | `tests/Feature/Api/V1/MediaUploadTest.php` |

## Known mechanism inconsistencies worth documenting in #26

- **Two different 403 mechanisms for "admin only".** `/api/admin/users*`
  (and `/audit-logs`) are guarded by the `role:admin` route middleware
  (`App\Http\Middleware\CheckRole`). `/api/v1/admin/topics|levels|categories|media*`
  are guarded only by `auth` at the route level, with
  `Gate::authorize('manage', X::class)` / `Gate::authorize('upload-media')`
  called inside each controller action instead. Both correctly reject
  non-admins with 403 today, but they are genuinely different code paths
  and easy to let drift — worth a single documented convention in #26
  rather than two undocumented ones.
- **`POST /api/v1/enrichment/words/{vocabulary}` enforces auth via a manual
  `abort_unless(auth()->check(), 401)` inside a closure**, in addition to
  (redundantly with) the route's own `->middleware('auth')`. Anyone reading
  `routes/spa.php` to generate docs from route definitions alone could miss
  that this endpoint requires auth if they only look at the closure body, or
  conversely assume the manual check is the *only* protection and remove
  the middleware. Worth a comment or a docs callout, not just silent
  redundancy.

## Out of scope for this log

Actually authoring the OpenAPI paths/schemas above is issue #26's
deliverable, not issue #28's. This file only inventories what's missing so
#26 doesn't have to re-audit the codebase from scratch.
