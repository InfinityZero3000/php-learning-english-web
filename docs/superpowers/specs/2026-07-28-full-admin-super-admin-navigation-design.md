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

## Navigation

The desktop sidebar remains fixed and scrollable. Mobile uses the existing drawer. All existing pages are retained and grouped as follows:

1. Overview: Dashboard, Analytics.
2. Content: Courses, Levels, Topics, Vocabulary, Decks, Flashcards, Import Jobs, Content Feed.
3. Learning: Quizzes, Spaced Repetition, Learner Progress, Reports.
4. People: Users, Notifications.
5. Account: Settings.
6. Super Admin: Super Dashboard, Roles and Teacher Scope, AI Services and Monitoring, Alerts and Quotas, Audit Trail.

Group labels and role badges make the current zone explicit. Active routes use the existing learner-inspired bordered blue state. No current page or route is deleted.

## Dashboard Behavior

`/dashboard` remains the Admin operational dashboard for both roles. A new `/super-admin` dashboard is visible only to Super Admin and focuses on service health, AI providers, TraceCAG, STT/TTS, usage, open alerts, and environment-safe configuration status. It must not expose secrets.

Dashboard values must come from existing APIs. Unavailable metrics display a clear unavailable state rather than fabricated values.

## API and Authorization Rules

- All pages stay behind the Google admin session check.
- Super Admin pages pass `requiredRole="super_admin"` and backend routes retain policy/middleware enforcement.
- Existing non-admin API calls are audited before their menu entry is considered complete. A page may remain visible while showing a clear unsupported or unavailable state, but it must not silently fail.
- Navigation visibility is presentation only; backend authorization remains authoritative.
- No new backend abstraction is introduced unless an existing page lacks any endpoint required for its primary workflow.

## Error and Loading Behavior

- A missing session returns to `/login` without a misleading expiry message.
- A forbidden Admin request returns to `/dashboard?forbidden=1`.
- API failures render an in-page retry or unavailable state.
- Menu links never target placeholder aliases that immediately redirect to unrelated pages.

## Verification

- Unit/build checks: ESLint, TypeScript, and Next production build.
- Backend feature tests for any new or changed admin contract.
- Role matrix check: Admin cannot access Super Admin routes; Super Admin can access both zones.
- Route/menu audit: every sidebar link resolves to a retained page and its primary data request has a real endpoint.
- Responsive check for desktop sidebar and mobile drawer.

## Non-goals

- Deleting or redesigning every existing page.
- Creating fake infrastructure metrics.
- Moving shared components into a cross-application package.
- Replacing the existing Google whitelist or role model.
