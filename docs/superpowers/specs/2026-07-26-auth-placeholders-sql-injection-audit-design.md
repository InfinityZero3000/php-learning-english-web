# Auth Placeholders and SQL Injection Audit

## Scope

Add explicit placeholders to every learner authentication field in the Next.js
frontend and audit backend authentication queries for SQL injection risks.

## UI design

Each `Field` call supplies its own placeholder so the copy remains contextual
and visible at the call site:

- Name: `Enter your name`
- Email: `name@example.com`
- Current password: `Enter your password`
- New password: `Enter a new password`
- Password confirmation: `Confirm your password`

The existing labels, input types, autocomplete attributes, required validation,
layout, and accessibility behavior remain unchanged. The affected flows are
login, registration, forgot password, and reset password.

## Security audit

Trace learner authentication requests from route and request validation through
controllers/services to database access. Search production backend code for raw
SQL, raw query clauses, unprepared statements, and user-controlled values
interpolated into queries.

Laravel Eloquent and query builder calls are acceptable when values are passed
through parameterized APIs. Any unsafe interpolation found in an authentication
path must be replaced at the shared query boundary and covered by the smallest
relevant regression test.

## Verification

- Run the focused frontend auth tests.
- Run frontend lint and production build.
- Run backend authentication tests and the full backend suite if production
  code changes.
- Deploy the learner frontend to Vercel production only after checks pass.
- Confirm the production deployment reaches `Ready` and owns the
  `linguist-nova.vercel.app` alias.

