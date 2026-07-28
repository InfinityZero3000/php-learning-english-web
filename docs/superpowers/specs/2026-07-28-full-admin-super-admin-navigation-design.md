# Full Admin and Super Admin Navigation Design

## Goal

Expose every existing admin interface through a complete, role-aware navigation while preserving all current pages. Admin manages ordinary learning operations. Super Admin inherits Admin access and gains security, AI, monitoring, quota, role, and audit controls.

## Current Gap

The admin frontend contains more than twenty routes, but the sidebar exposes only Dashboard, Courses, Users, Roles, and AI & Operations. Existing pages for content, vocabulary, decks, imports, learning analytics, FSRS, quizzes, reports, notifications, settings, and audit are therefore difficult or impossible to discover.

Some older pages use shared learner-era APIs instead of dedicated `/api/v1/admin/*` contracts. They must remain available, but each visible workflow must be checked for authentication, authorization, error handling, and a working backend endpoint.

## Role Model

### Admin

Admin can access:

- Admin Dashboard and analytics.
- Courses, levels, topics, vocabulary, decks, flashcards, content feeds, and imports.
- Quizzes, spaced repetition, learner progress, and reports.
- User management, notifications, and ordinary settings.

Admin cannot see or call Super Admin-only controls.

### Super Admin

Super Admin inherits every Admin capability and additionally accesses:

- Super Admin system overview.
- Role management and teacher-to-learner scopes.
- AI services, TraceCAG contracts, STT/TTS availability, and service probes.
- Monitoring, usage, alert rules, quota policies, and audit events.

Super Admin can move between the Admin Dashboard and Super Admin Dashboard without a second login.

## Canonical Navigation Manifest

The desktop sidebar remains fixed and scrollable. Mobile uses the existing drawer. Every row below is visible to the listed role. `google.admin` and the relevant capability remain mandatory on every supporting backend contract.

| Group | Label | Canonical href | Role | Primary contract or state |
|---|---|---|---|---|
| Overview | Dashboard | `/dashboard` | Admin+ | Admin users and catalog summaries |
| Overview | Analytics | `/analytics` | Admin+ | Platform aggregate analytics; never the administrator's learner record |
| Content | Courses | `/courses` | Admin+ | `/api/v1/admin/catalog/courses` |
| Content | Levels | `/levels` | Admin+ | Admin catalog level contract; read-only unavailable until implemented |
| Content | Topics | `/topics` | Admin+ | Admin catalog topic contract; mutations disabled until implemented |
| Content | Vocabulary | `/flashcards` | Admin+ | Admin vocabulary contract; aliases `/vocabulary` redirect here |
| Content | Decks | `/decks` | Admin+ | Admin vocabulary-set contract; alias `/vocabulary-sets` redirects here |
| Content | Import Jobs | `/import` | Admin+ | Admin may start/resume normal imports; retry/reset requires Super Admin |
| Content | Content Feed | `/content` | Admin+ | Authenticated provider feed; read-only if provider unavailable |
| Learning | Quizzes | `/quizzes` | Admin+ | Platform aggregate quiz contract; alias `/quiz` redirects here |
| Learning | Spaced Repetition | `/spaced-repetition` | Admin+ | Aggregate FSRS health/statistics, never admin's personal queue |
| Learning | Learner Progress | `/user-progress` | Admin+ | Privacy-limited platform aggregates; alias `/progress` redirects here |
| Learning | Reports | `/reports` | Admin+ | Real aggregate report/export contract; export disabled until implemented |
| People | Users | `/users` | Admin+ | `/api/v1/admin/users`; role mutation is Super Admin + recent Google verification |
| Account | Notifications | `/notifications` | Admin+ | Administrator's operational notifications only |
| Account | Settings | `/settings` | Admin+ | Administrator preferences; platform settings remain Super Admin-only |
| Super Admin | Super Dashboard | `/super-admin` | Super Admin | Operations overview, usage, contracts, service state |
| Super Admin | Roles & Teacher Scope | `/roles` | Super Admin | `/api/v1/admin/roles` and teacher assignments |
| Super Admin | AI & Monitoring | `/operations` | Super Admin | Existing operations overview, probes, usage, contracts |
| Super Admin | Alerts & Quotas | `/operations#controls` | Super Admin | Existing alert-rule and quota contracts |
| Super Admin | Audit Trail | `/audit-logs` | Super Admin | Existing audit-event contract; remove mock events |

The retained `/vocabulary` and `/vocabulary-sets` pages may remain as compatibility redirects. `/levels`, `/topics`, `/flashcards`, `/decks`, `/import`, `/content`, `/quizzes`, `/spaced-repetition`, `/analytics`, `/user-progress`, `/reports`, `/notifications`, and `/settings` remain real pages, not deleted placeholders.

Group labels and role badges make the current zone explicit. Active routes use the existing learner-inspired bordered blue state. No current page or route is deleted.

## Dashboard Behavior

`/dashboard` remains the Admin operational dashboard for both roles. A new `/super-admin` dashboard is visible only to Super Admin and focuses on service health, AI providers, TraceCAG, STT/TTS, usage, open alerts, and environment-safe configuration status. It must not expose secrets.

Dashboard values must come from existing APIs. Unavailable metrics display a clear unavailable state rather than fabricated values.

## API, Data Scope, and Authorization Matrix

- All canonical pages use the shared Google session guard before protected child data is mounted. `AdminLayout` is only presentation; every backend contract independently enforces authentication, `google.admin`, and its capability policy.
- Admin may read and mutate ordinary catalog content. Admin may read privacy-safe aggregate learning metrics, but cannot browse learner evidence, personal FSRS queues, answers, or recordings without an explicit teacher scope or Super Admin authorization.
- Super Admin pages pass `requiredRole="super_admin"`. Backend policies, not menu visibility, enforce the same restriction.
- Existing learner-era API calls are not valid admin contracts. Each page must either be connected to a working authorized admin contract or present an explicit read-only unavailable state with every action disabled. A primary mutation may not remain visible and fail on click.
- Role changes, teacher-scope changes, quota versions, alert-rule changes, import retry/reset, and platform settings require recent Google verification. Mutations require CSRF protection; retryable/sensitive mutations require `X-Request-ID` idempotency and an audit event.
- Normal import start/resume is available to Admin. Destructive retry/reset and checkpoint replacement are Super Admin-only.
- Navigation visibility is presentation only; backend authorization remains authoritative.
- No new backend abstraction is introduced unless an existing page lacks any endpoint required for its primary workflow.

| Capability | Admin | Super Admin | Data scope / safeguards |
|---|---|---|---|
| Catalog read/write | Yes | Yes | Platform catalog; CSRF and validation on writes |
| Aggregate analytics/reports | Read | Read | Aggregated or anonymized; no learner evidence |
| User directory | Read | Read | Minimum identity fields |
| User role mutation | No | Yes | Recent Google verification, idempotency, audit |
| Teacher assignments | No | Yes | Recent Google verification, idempotency, audit |
| Import start/resume | Yes | Yes | Idempotent checkpoint semantics |
| Import retry/reset | No | Yes | Recent Google verification and audit |
| AI/service monitoring | No | Yes | Redacted configuration; no credentials |
| Quota/alert mutation | No | Yes | Recent Google verification, versioning, audit |
| Learner evidence/recordings | No general access | Explicit operational access only | Minimize PII and audit access |

## Error and Loading Behavior

- A missing session returns to `/login` without a misleading expiry message.
- A forbidden Admin request returns to `/dashboard?forbidden=1`.
- API failures render an in-page retry or unavailable state. Unsupported actions are disabled with an explanation.
- Menu links never target placeholder aliases that immediately redirect to unrelated pages.
- Existing mock audit events, static metrics presented as live data, and simulated export success are prohibited. Replace them with real contracts or honest unavailable states.

## Verification

- Unit/build checks: ESLint, TypeScript, and Next production build.
- Backend feature tests for any new or changed admin contract.
- Executable role matrix for every manifest row: Admin/Super Admin visibility, direct-route behavior, backend `200/403`, canonical destination, primary endpoint existence, and loading/error state.
- Action tests cover read versus mutation permissions, data scope, CSRF, recent Google verification, idempotency, and audit creation where required.
- Route/menu audit asserts that aliases resolve canonically and no navigation destination is a placeholder.
- Responsive browser checks at 390×844 (mobile drawer), 768×1024 (tablet), and 1440×900 (fixed scrollable desktop sidebar).

## Non-goals

- Deleting or redesigning every existing page.
- Creating fake infrastructure metrics.
- Moving shared components into a cross-application package.
- Replacing the existing Google whitelist or role model.
