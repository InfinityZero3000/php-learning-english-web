# Blade to Next.js Migration Design

**Date:** 2026-07-28  
**Status:** Draft for review  
**Decision:** Replace Blade by capability, reusing the two existing Next.js applications.

## 1. Objective

Remove Blade from the application runtime and make Laravel an API, authentication, callback, and health-check boundary only.

The migration must:

- reuse existing Next.js pages instead of recreating Blade routes one-for-one;
- keep learner and authentication UI in `frontend/`;
- keep administrator UI in `admin-frontend/`;
- preserve all still-required learner and administrator capabilities;
- remove legacy HTML form endpoints after equivalent JSON APIs are verified;
- delete every file under `resources/views/` when no runtime code references a view;
- preserve authorization, CSRF protection, signed verification links, recent-password checks, and destructive-action confirmation.

## 2. Current State

The repository contains 29 Blade files and 26 PHP call sites that return `view(...)`. Two Next.js applications already cover much of the same product surface.

### 2.1 Existing Next.js applications

`frontend/` owns learner-facing functionality:

- login and Google authentication;
- Today learning plan;
- learner course discovery and course detail;
- learning sessions and summaries;
- vocabulary catalog;
- vocabulary quiz and FSRS review;
- assignments, profile, progress, teacher workspace, and listening practice.

`admin-frontend/` owns administrative functionality:

- dashboard and user administration;
- role and teacher-scope management;
- courses, levels, topics, vocabulary, and decks;
- learning analytics, reports, imports, operations, audit logs, notifications, and settings.

### 2.2 Blade inventory

| Blade group | Files | Existing Next.js coverage | Migration decision |
|---|---:|---|---|
| Shared layouts | 2 | `AppShell`, `AdminLayout`, root layouts | Delete; do not recreate as pages |
| Home | 1 | `frontend /` Today page | Use existing Today page |
| Authentication | 4 | Login exists; other flows missing | Add missing auth pages using existing API client |
| Admin users | 1 | Admin dashboard and `/users` exist | Use existing pages |
| Learner profile | 1 | `/profile` exists | Extend existing page |
| Learner progress | 1 | `/progress` exists and is broader | Use existing page |
| Learner words | 1 | `/vocabulary` exists | Extend existing page |
| Bookmarks | 1 | No equivalent view | Integrate into vocabulary view |
| Learner quiz | 2 | `/quiz` exists for vocabulary quiz | Extend for lesson quiz without duplicate result page |
| Admin courses | 4 | `/courses` has list/create/edit | Extend existing page |
| Admin lessons | 4 | Missing | Add lesson management |
| Admin quizzes | 4 | `/quizzes` is analytics only | Add separate quiz management capability |
| Admin vocabulary | 3 | `/flashcards` has CRUD | Extend existing page |

`WordsController::show()` currently references a missing `words.show` Blade file. The migration must not reproduce this defect. A word-detail route is added only if detail data cannot be represented accessibly in the existing vocabulary cards/dialog.

## 3. Architecture

### 3.1 Application ownership

| Concern | Owner |
|---|---|
| Learner pages and learner auth UI | `frontend/` |
| Administrator pages | `admin-frontend/` |
| JSON validation and authorization | Laravel `/api/v1` |
| Session cookies and CSRF | Laravel |
| Google/Facebook OAuth callbacks | Laravel, followed by Next.js redirect |
| Signed email verification | Laravel, followed by Next.js redirect |
| Health endpoints | Laravel |

Browser code must not access the database or partner credentials directly. Both Next.js applications call same-origin `/api/*` paths through their existing Laravel rewrites.

### 3.2 Migration strategy

Migration is capability-based:

1. identify the behavior supplied by each Blade screen;
2. map it to an existing Next.js page when possible;
3. extend that page with missing behavior;
4. create a new page only when no coherent existing destination exists;
5. expose or normalize the required JSON API;
6. remove the legacy HTML route and controller path;
7. delete Blade only after route and test searches prove it is unreachable.

No compatibility Blade shell or catch-all Blade view will remain.

## 4. Learner and Authentication Design

### 4.1 Authentication

Keep `/login` and its existing `LoginPage`. Add a shared auth layout and these pages:

- `/register`: name, email, password, password confirmation;
- `/forgot-password`: email submission and enumeration-safe success state;
- `/reset-password`: reads `token` and `email` from the query string;
- `/verify-email`: verification instructions, safe email display, resend action, and return to login.

The pages use the existing endpoints:

- `POST /api/v1/auth/register`;
- `POST /api/v1/auth/password/forgot`;
- `POST /api/v1/auth/password/reset`;
- `POST /api/v1/auth/email/resend`.

Laravel continues to validate signed verification URLs. A successful verification redirects to `FRONTEND_URL/login?verified=1`. Login reads this query parameter and shows an `aria-live` success notice.

The canonical password-reset URL remains the URL already generated by `AppServiceProvider`:

```text
FRONTEND_URL/reset-password?token={urlencoded-token}&email={urlencoded-email}
```

The Next.js page must not move the token into a path segment. Tests inspect the actual `ResetPassword` notification URL and complete a reset through the API using its query values.

### 4.1.1 Social authentication

Google and Facebook learner login remain supported.

- Google entry remains `/api/v1/auth/oauth/google`; callback remains `/api/v1/auth/oauth/google/callback`; safe `next` paths redirect to `FRONTEND_URL`.
- Facebook entry remains the named Laravel route `facebook.login` at `/auth/facebook`; callback remains `facebook.callback` at `/auth/facebook/callback`. Because the learner Next.js app only rewrites `/api/*`, add `frontend/src/app/auth/facebook/route.ts`, mirroring the existing Google route handler, to redirect the browser to `${LARAVEL_API_ORIGIN}/auth/facebook`.
- Facebook callback is changed from the relative Laravel `/profile` redirect to `FRONTEND_URL/profile`.
- The login page may expose both providers only when their client IDs are configured; hiding a button does not remove the backend callback.
- Provider failures return to `FRONTEND_URL/login?error={safe-code}` without leaking provider responses. Tests request the Next.js `/auth/facebook` route, assert the Laravel entry redirect, verify OAuth state is present, and assert the callback returns to the configured learner frontend.

Admin Google handoff remains unchanged: Laravel completes privileged Google verification and redirects to `ADMIN_FRONTEND_URL/login` with the short-lived handoff or a safe error code.

### 4.2 Profile

Extend the existing `/profile` page instead of porting `profile/index.blade.php`.

Retain its current identity, statistics, background, and reminder controls. Add:

- password change using `PUT /api/v1/profile/password`;
- account deletion using `DELETE /api/v1/profile`;
- an explicit confirmation dialog and password confirmation;
- disabled/loading/error/success states independent from the existing profile form.

Deletion remains destructive and must not be optimistic. The UI redirects to `/login` only after Laravel confirms success.

### 4.3 Vocabulary and saved words

Extend `/vocabulary` rather than creating separate `/words` and `/bookmarks` pages.

The page gains:

- a `view=all|saved` query state;
- topic filtering;
- bookmark toggle on each vocabulary card;
- saved-empty guidance that returns to the full catalog;
- URL-backed search, topic, page, and view state;
- optional accessible detail dialog for examples, lesson, topic, image, and audio.

A separate `/vocabulary/[id]` page is only required if content grows beyond a usable dialog or needs shareable deep links. It is not part of the initial migration.

Laravel must provide versioned bookmark JSON endpoints with ownership enforcement. Existing HTML bookmark endpoints are not called by Next.js.

### 4.4 Progress and Today

`frontend /` replaces `home.blade.php`. `/progress` replaces `progress/index.blade.php`. No Blade-specific cards, inline styles, or navigation are ported when the Next.js page already exposes the same or richer learning state.

### 4.5 Learner quiz

The existing `/quiz` vocabulary quiz remains intact. Lesson quizzes are added as a second source within the same learning-oriented experience:

- quiz selection or lesson-launched quiz setup;
- one question at a time;
- fixed answers after submission;
- visible progress;
- final score and pass/fail summary inline;
- retry and next-learning-step actions.

There is no separate result page unless reload-safe result URLs become a confirmed requirement. Laravel persists attempts and returns learner-owned results through JSON.

## 5. Administrator Design

### 5.1 Existing administrative pages

Do not recreate pages already available for dashboard, users, roles, levels, topics, flashcards, decks, analytics, imports, operations, audit logs, or settings.

### 5.2 Courses

Extend `admin-frontend /courses` to cover the meaningful behavior from the four course Blade files:

- filter and pagination;
- create and edit in the existing modal pattern;
- level and topic assignment;
- detail panel with units/lessons summary;
- publish and archive actions;
- deletion only if the domain permits it safely.

The existing `/api/v1/admin/catalog/courses` endpoints remain the single mutation path. Publish/archive use their dedicated endpoints rather than overloading update.

### 5.3 Lessons

Add `/lessons` to `admin-frontend` because no corresponding page exists.

The first version uses one route with:

- searchable/filterable lesson list;
- course filter and status badge;
- create/edit modal sharing one form component;
- detail drawer for content, vocabulary count, and attached quizzes;
- publish/archive/delete actions where supported;
- links that open related quiz management pre-filtered by lesson.

Create/edit/show do not become three separate pages unless URL-addressable editing becomes necessary. This keeps parity without copying the Blade route structure.

Laravel adds admin lesson JSON endpoints under `/api/v1/admin/catalog/lessons` with capability authorization and validation equivalent to or stricter than the legacy controller.

### 5.4 Quiz management

Keep `/quizzes` as analytics. Add `/quiz-management` for authoring.

The management page provides:

- quiz list, lesson/status filters, and question count;
- create/edit form with title, lesson, passing score, and status;
- nested question and answer editing;
- at least two answers and at least one correct answer per question;
- detail preview;
- confirmed deletion.

Nested quiz updates are transactional in Laravel. The API never deletes the existing question tree until the replacement payload validates completely.

### 5.5 Vocabulary management

Extend the existing `/flashcards` page to replace the vocabulary Blade forms:

- lesson and topic fields;
- example and pronunciation fields;
- image and audio upload only if those files are still used by the current domain;
- preview and explicit replacement/removal controls;
- existing create/edit/delete interactions and pagination remain.

No second `/vocabulary` admin CRUD is created; its current redirect to `/flashcards` remains sufficient.

## 6. API and Data Contracts

All newly required APIs use `ApiResponse` envelopes and concrete frontend types. They must not return view models, redirect responses, validation HTML, stack traces, or raw exception bodies.

### 6.1 Existing-page compatibility audit

Before a Next.js page is accepted as a Blade replacement, every request it makes is classified as retained `/api/v1`, migrated legacy `/api/*`, or removed. A page is not considered complete while it depends on an undocumented legacy endpoint.

| Next.js capability | Required Laravel API |
|---|---|
| Login/register/password/verification | `/api/v1/auth/*` |
| Profile identity/password/deletion | `/api/v1/profile*` |
| Today/course/session/progress | existing `/api/v1/learning`, `/catalog`, `/enrollments`, `/progress` |
| Vocabulary catalog and saved state | `/api/v1/vocabulary`, `/api/v1/vocabulary/{id}/bookmark` |
| FSRS review | `/api/v1/fsrs/*` |
| Learner lesson quiz | new `/api/v1/lesson-quizzes/*` |
| Admin users/roles | existing `/api/v1/admin/users`, `/roles` |
| Admin courses/levels/topics/vocabulary/decks | existing `/api/v1/admin/catalog/*`, extended as specified below |
| Admin lessons/quizzes | new `/api/v1/admin/catalog/lessons*`, `/api/v1/admin/catalog/quizzes*` |

Legacy calls currently present in `frontend/src/lib/api.ts`, including `/api/words`, `/api/quiz`, `/api/progress`, `/api/fsrs`, `/api/streak`, `/api/notifications`, `/api/flashcards`, `/api/enrichment`, `/api/dictionary`, and `/api/import`, must each be either documented as a retained non-Blade API or migrated to `/api/v1`. This audit is a blocking checklist, not an automatic requirement to rename stable JSON APIs.

### 6.2 Learner vocabulary and bookmarks

`GET /api/v1/vocabulary` accepts `search`, `topic_id`, `saved`, `page`, and `per_page` (maximum 100). For authenticated callers each item includes `is_bookmarked`; public catalog behavior remains unchanged if anonymous access is retained.

`PUT /api/v1/vocabulary/{vocabulary}/bookmark` sets `{ "bookmarked": true|false }` and returns `{ vocabulary_id, bookmarked }`. Repeating the same value is idempotent. The vocabulary must exist; bookmark ownership always comes from the authenticated session, never the request body.

Errors: `401` unauthenticated mutation, `404` missing vocabulary, `422` invalid filter or body.

### 6.3 Learner lesson quiz

- `GET /api/v1/lesson-quizzes?lesson_id={id}` lists published quizzes available to the learner.
- `POST /api/v1/lesson-quizzes/{quiz}/attempts` starts or returns the learner's active attempt and exposes questions without correctness flags.
- `PUT /api/v1/lesson-quiz-attempts/{attempt}/answers/{question}` accepts `{ "answer_id": 123 }` and returns `{ "data": { "question_id": 1, "answer_id": 123, "is_correct": true, "explanation": "..." }, "meta": {} }`. The question must belong to the attempt's quiz and the answer must belong to that question; relationship mismatches return `422` without revealing correctness. Repeating the same answer is idempotent; changing a submitted answer returns `409 ANSWER_LOCKED`.
- `POST /api/v1/lesson-quiz-attempts/{attempt}/complete` completes once and returns `{ score, correct_answers, total_questions, passed, completed_at }`.
- `GET /api/v1/lesson-quiz-attempts/{attempt}` returns only an attempt owned by the current learner.

Eligibility requires an authenticated learner, a published quiz, a published lesson, and access through an enrollment or assigned lesson. Attempts are owner-scoped. Completing an already completed attempt returns the stored result. An empty quiz cannot start and returns `409 QUIZ_EMPTY`.

### 6.4 Admin lessons

- `GET /api/v1/admin/catalog/lessons` accepts `search`, `course_id`, `status`, `page`, and `per_page`.
- `POST /api/v1/admin/catalog/lessons` creates from `{ course_id, title, slug, content?, sort_order, estimated_minutes?, status }`.
- `GET /api/v1/admin/catalog/lessons/{lesson}` returns course, vocabulary count, and quiz summaries.
- `PUT /api/v1/admin/catalog/lessons/{lesson}` updates the same fields.
- `POST /api/v1/admin/catalog/lessons/{lesson}/publish` and `/archive` perform explicit lifecycle changes.
- `DELETE /api/v1/admin/catalog/lessons/{lesson}` is allowed only when no progress, active assignment, attempt, or other protected learning evidence depends on it; otherwise it returns `409 DEPENDENCY_EXISTS` with safe counts.

All endpoints require `manage-content`. Slugs are unique within a course. Pagination follows the existing admin envelope and meta format.

### 6.5 Admin quiz authoring

- `GET /api/v1/admin/catalog/quizzes` accepts `search`, `lesson_id`, `status`, `page`, and `per_page`.
- `POST /api/v1/admin/catalog/quizzes` creates a quiz and nested questions.
- `GET /api/v1/admin/catalog/quizzes/{quiz}` returns the complete authoring structure including correctness flags.
- `PUT /api/v1/admin/catalog/quizzes/{quiz}` replaces the nested authoring structure transactionally.
- `DELETE /api/v1/admin/catalog/quizzes/{quiz}` is blocked with `409 DEPENDENCY_EXISTS` once learner attempts exist; otherwise it deletes through database cascades.

Write payload:

```json
{
  "lesson_id": 1,
  "title": "Checkpoint",
  "passing_score": 60,
  "status": "draft",
  "questions": [
    {
      "content": "Question",
      "explanation": "Optional",
      "answers": [
        { "content": "Answer A", "is_correct": true },
        { "content": "Answer B", "is_correct": false }
      ]
    }
  ]
}
```

Validation requires at least one question, two answers per question, and at least one correct answer. Only users with `manage-content` can read correctness flags or mutate quizzes.

### 6.6 Courses and vocabulary media

Course hard deletion is intentionally retired. Published or referenced courses are archived through `POST /api/v1/admin/catalog/courses/{course}/archive`; drafts with no dependencies may remain in the database as archived records. This removes the legacy destructive behavior instead of introducing a second deletion contract.

The vocabulary media fields are added to the existing admin vocabulary contract only if the usage audit finds persisted `image_path` or `audio_path` data or active consumers. If retained, writes use multipart requests, replacement removes the superseded file after the database update succeeds, and deletion removes owned media. If unused, the Blade-only upload controls are formally retired and existing external audio remains available.

Every mutation uses CSRF protection. Sensitive admin mutations preserve capability checks and recent-password requirements where currently required.

## 7. Legacy Route Cutover

### 7.1 Redirect rules

During cutover, browser GET routes may temporarily redirect:

| Legacy route | Destination |
|---|---|
| `/` | `FRONTEND_URL/` |
| `/login` | `FRONTEND_URL/login` |
| `/register` | `FRONTEND_URL/register` |
| `/forgot-password` | `FRONTEND_URL/forgot-password` |
| `/reset-password/{token}` | `FRONTEND_URL/reset-password?token={token}&email={preserved-email}` |
| `/verify-email` | `FRONTEND_URL/verify-email` |
| `/profile` | `FRONTEND_URL/profile` |
| `/words` | `FRONTEND_URL/vocabulary`, preserving supported `search`, `topic_id`, and `page` |
| `/words/{id}` | `FRONTEND_URL/vocabulary?word={id}` when the item exists |
| `/bookmarks` | `FRONTEND_URL/vocabulary?view=saved` |
| `/progress` | `FRONTEND_URL/progress` |
| `/quizzes/{id}/attempt` | `FRONTEND_URL/quiz?lesson_quiz={id}` |
| `/quizzes/{id}/result?attempt_id={attempt}` | `FRONTEND_URL/quiz?attempt={attempt}` |
| `/admin`, `/admin/dashboard` | `ADMIN_FRONTEND_URL/dashboard` |
| `/admin/users*` | `ADMIN_FRONTEND_URL/users`, preserving user ID for detail routes |
| `/admin/courses*` | `ADMIN_FRONTEND_URL/courses`, preserving `search` and entity ID as `course` query state |
| `/admin/lessons*` | `ADMIN_FRONTEND_URL/lessons`, preserving `course_id`, `search`, and entity ID as `lesson` query state |
| `/admin/quizzes*` | `ADMIN_FRONTEND_URL/quiz-management`, preserving `lesson_id`, `search`, and entity ID as `quiz` query state |
| `/admin/vocabularies*` | `ADMIN_FRONTEND_URL/flashcards`, preserving `search`, `page`, and entity ID as `word` query state |

Named Laravel routes required by authentication middleware or framework notifications remain redirects, not views.

Exact preserved named routes:

| Route name | Type | Decision |
|---|---|---|
| `login` | redirect | Keep for Laravel auth middleware; redirect to learner `/login` |
| `password.request` | redirect | Keep; learner `/forgot-password` |
| `password.reset` | redirect | Keep legacy emailed-link compatibility; canonical new mail uses query URL |
| `verification.notice` | redirect | Keep; learner `/verify-email` |
| `api.verification.verify` | signed callback | Keep exact API callback and signature middleware |
| `google.callback` | OAuth callback | Keep, then redirect to learner frontend |
| `facebook.login`, `facebook.callback` | OAuth entry/callback | Keep, then redirect to learner frontend |
| `admin.google.login` and admin API OAuth routes | admin OAuth/handoff | Keep, then redirect to admin frontend |

Signed verification tests cover valid, expired, and tampered links and assert that all original signature query parameters reach Laravel unchanged.

### 7.2 Removed routes

Legacy POST/PUT/PATCH/DELETE form routes are removed after API parity tests pass. They are not retained as duplicate mutation surfaces.

OAuth callbacks, signed verification, health endpoints, and API routes remain in Laravel.

### 7.3 Controller cleanup

Delete or reduce controllers whose remaining purpose is returning Blade. A controller shared by callback or API behavior is retained only for that behavior. No production PHP code may call `view()` after cutover.

## 8. UI/UX Rules

The migration follows the existing visual language rather than reproducing Bootstrap Blade markup.

- Vietnamese is the primary guidance language; short product/skill labels may remain English.
- Learner UI prioritizes the current task and next action.
- Admin UI uses the existing tactile control-center visual system.
- Reuse existing Button, Input, Card, dialog, loading, and error components.
- Every async action has local loading and error state.
- Icon-only controls have accessible names.
- All forms support keyboard submission and visible focus.
- Validation summaries use `role="alert"`; asynchronous success uses `aria-live`.
- Destructive actions require explicit confirmation and are never optimistic.
- Mobile layouts avoid horizontally clipped forms; data tables provide responsive alternatives or controlled scrolling.
- Do not copy Blade inline styles, Bootstrap dependencies, or DOM scripts.

## 9. Failure Handling

- `401`: redirect learner UI to `/login`; admin UI follows its existing admin authentication entry.
- `403`: show authorization guidance without exposing protected data.
- `419`: refresh CSRF once through the existing CSRF helper, then let the user retry; do not loop.
- `422`: map Laravel field errors to form controls and an accessible summary.
- `429`: show retry guidance and honor `Retry-After`; do not continuously retry.
- `5xx` or network failure: retain entered form data and offer manual retry.
- Partial list failure: keep already-loaded content visible where safe.
- Missing legacy entity: return a typed 404 and route back to the appropriate list.

## 10. Testing and Acceptance

### 10.1 Backend

- feature tests for each new API success and validation path;
- authorization and ownership tests;
- admin capability and recent-password tests;
- nested quiz transaction rollback tests;
- bookmark idempotency tests;
- legacy redirect target tests;
- tests proving removed mutation routes return 404/405;
- full `php artisan test`.

### 10.2 Frontend

For both Next.js applications:

- TypeScript `--noEmit`;
- ESLint;
- production build;
- browser smoke tests on desktop and mobile using the repository's available browser automation; if no runner is installed, execute and record the manual checklist below rather than adding a framework;
- keyboard and visible-focus checks;
- validation, empty, loading, authorization, and network-error states.

No new React test framework is added solely for this migration.

Browser verification uses local Laravel, learner Next.js, and admin Next.js servers with seeded learner, admin, and super-admin accounts plus configured test Google/Facebook providers where available. Required flows:

1. register → resend → signed verify → login;
2. forgot-password email URL → reset → login;
3. Google and Facebook learner callback destinations and safe failure handling;
4. admin Google handoff and recent-verification challenge;
5. vocabulary search/filter/save/unsave;
6. learner lesson quiz start/answer/complete/reload;
7. admin course, lesson, quiz, and vocabulary create/edit/lifecycle actions;
8. authorization denial and destructive confirmation;
9. every legacy GET redirect, including query preservation;
10. rollback rehearsal by reverting only the cutover commit and confirming no database rollback is needed.

### 10.3 Blade removal gate

Blade deletion happens only when all checks below pass:

```text
find resources/views -type f -name '*.blade.php'   # no results
git grep -n -E "\bview\(|View::make|@extends|@section" -- '*.php' '*.blade.php'
php artisan route:list                             # no UI route points at a Blade controller
php artisan test
frontend: tsc + eslint + build
admin-frontend: tsc + eslint + build
```

`resources/views/` may then be deleted if empty. The deletion is intentional and non-recoverable except through Git history.

Feature tests enumerate every preserved named redirect/callback route. A route-action audit asserts no production route resolves to `CourseController`, `LessonController`, `QuizController`, `QuizAttemptController`, `VocabularyController`, `WordsController`, `BookmarkController`, or another controller after its Blade-only behavior is removed.

## 11. Delivery Sequence

1. Add missing API contracts and tests.
2. Add missing authentication pages and extend profile.
3. Extend learner vocabulary/bookmarks and lesson quiz.
4. Extend admin courses and vocabulary.
5. Add admin lessons and quiz management.
6. Cut legacy GET routes over to Next.js redirects.
7. Remove legacy mutation routes and Blade-only controller code.
8. Delete Blade files.
9. Run full automated verification and browser smoke tests.

Each sequence item must leave existing Next.js pages usable. Learner and admin deployments can be verified independently, but Blade deletion occurs only after both applications are ready.

## 12. Out of Scope

- redesigning the established learner or admin visual identity;
- merging `frontend/` and `admin-frontend/`;
- replacing Laravel session authentication;
- adding a new component library or React test framework;
- retaining historical Blade URLs as permanent duplicate interfaces;
- adding speculative word-detail pages or separate create/edit pages without a demonstrated navigation need.

## 13. Rollout and Rollback

Deploy API additions first. Deploy both Next.js applications next. Verify internal accounts against production-like data. Then deploy legacy redirects and Blade removal.

Rollback is performed by reverting the cutover commit and restoring Blade through Git; no database rollback should be required for UI-only cutover. New API endpoints remain backward-compatible and may stay deployed during rollback.
