# Security checklist

| Item | Status | Evidence |
|---|---|---|
| Blade CSRF | Pass | Auth/profile forms use `@csrf`; web routes retain CSRF middleware. |
| API CSRF | Pass (session) | API is authenticated; token API awaits Sanctum installation. |
| SQL injection | Pass | Reviewed `app/Http`, `routes`; query builder bindings used, no user-concatenated raw SQL. |
| XSS | Pass | Reviewed Blade: user output uses escaped `{{ }}`; no `!!` usage found. |
| File uploads | Not applicable | No upload endpoint currently exists; add MIME/finfo validation before adding one. |
| Rate limit | Fixed | API group uses `throttle:api`; attempt endpoints use `throttle:60,1`. |
| IDOR attempts | Pass | AttemptPolicy authorizes attempt owner. Add equivalent policy checks when admin CRUD is enabled. |
| Test evidence | Pass | `BookmarkToggleTest`, `StreakCalculatorTest`, and full PHPUnit suite. |

## Follow-up

Install `laravel/sanctum` once the local Composer CA certificate is fixed, configure a restrictive `config/cors.php` frontend allow-list, and use `auth:sanctum` on the API group.
