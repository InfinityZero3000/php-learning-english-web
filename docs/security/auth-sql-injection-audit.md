# Learner Authentication SQL Injection Audit

Date: 2026-07-26

## Inspected boundaries

- `routes/api.php`: learner login, registration, password reset, email
  verification, and OAuth endpoints.
- `routes/spa.php`: SPA fallback and learner-facing route dispatch.
- `routes/web.php`: redirects for learner/admin entry points.
- `app/Http/Controllers/Auth/*`: request handling and authentication flows.
- `app/Http/Requests/*`: input validation before persistence/query access.
- `app/Services/*` auth-related services and the corresponding feature tests.

## Search evidence

Searched production application code (excluding tests and generated frontend
output) for `selectRaw`, `whereRaw`, `orderByRaw`, `havingRaw`, `unprepared`,
`statement(`, `->raw(`, SQL statement literals, and string interpolation near
auth request values.

The only raw database API usages found are schema/data migrations, import
services, health checks, and integration tests. No learner authentication
boundary interpolates request data into SQL.

## Finding

No SQL injection vulnerability found. Authentication reads and writes use
Laravel Eloquent or query builder parameter binding after request validation.
No remediation or regression test was required.
