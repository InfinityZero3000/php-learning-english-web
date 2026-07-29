# Blade to Next.js Migration Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace all Blade user interfaces with the existing learner and administrator Next.js applications, remove duplicate HTML mutation routes, and delete `resources/views` without losing required behavior.

**Architecture:** Laravel remains the single authenticated JSON API and OAuth/signed-link boundary. `frontend/` owns learner and authentication UI; `admin-frontend/` owns content administration. Existing pages are extended by capability, new pages are added only for missing auth, lesson-management, and quiz-authoring workflows, and Blade is removed only after both applications pass parity checks.

**Tech Stack:** Laravel 12, PHP 8.2+, PHPUnit, Next.js 16 App Router, React 19, TypeScript, Tailwind CSS, existing Laravel session/CSRF authentication.

**Design:** `docs/superpowers/specs/2026-07-28-blade-to-nextjs-migration-design.md`

**Required skills during execution:** `@fsrspring-ui-ux`, `@frontend-design`, `@requesting-code-review`. Read `admin-frontend/AGENTS.md` and the relevant Next.js 16 local documentation before changing either Next.js application.

---

## File Responsibility Map

### Laravel

- `routes/spa.php`: canonical `/api/v1` contracts.
- `routes/web.php`: OAuth/signed callback routes and temporary Next.js redirects only.
- `app/Http/Controllers/Api/V1/VocabularyController.php`: learner catalog filters and saved-state output.
- `app/Http/Controllers/Api/V1/BookmarkController.php`: idempotent learner bookmark mutation.
- `app/Http/Controllers/Api/V1/LessonQuizController.php`: published lesson-quiz discovery and attempt lifecycle.
- `app/Http/Controllers/Api/V1/Admin/LessonController.php`: admin lesson CRUD/lifecycle.
- `app/Http/Controllers/Api/V1/Admin/QuizController.php`: admin nested quiz CRUD.
- `app/Http/Requests/Api/V1/*`: boundary validation for new writes.
- `app/Http/Resources/*`: stable JSON shapes without correctness leakage.
- `app/Services/LessonQuizService.php`: transaction and idempotency rules for attempts.
- `app/Services/AdminQuizService.php`: validated transactional nested quiz replacement.
- `app/Providers/AppServiceProvider.php`: canonical reset and signed verification URLs.
- `app/Http/Controllers/SocialController.php`: frontend-safe Google/Facebook redirects.

### Learner Next.js

- `frontend/src/features/auth/*`: shared auth shell and missing auth flows.
- `frontend/src/features/profile/profile-page.tsx`: retain current profile UI; add password/deletion sections.
- `frontend/src/features/vocabulary/vocabulary-page.tsx`: retain catalog; add URL filters and saved state.
- `frontend/src/features/quiz/quiz-page.tsx`: retain vocabulary quiz; route lesson quizzes to a focused component.
- `frontend/src/features/quiz/lesson-quiz.tsx`: lesson quiz state machine only.
- `frontend/src/app/auth/facebook/route.ts`: browser redirect to Laravel Facebook entry.
- `frontend/src/lib/api.ts`: concrete request/response types; no new `Record<string, unknown>` for these flows.

### Administrator Next.js

- `admin-frontend/src/app/courses/page.tsx`: extend existing course manager.
- `admin-frontend/src/app/lessons/page.tsx`: new lesson list/form/detail surface.
- `admin-frontend/src/app/quiz-management/page.tsx`: new authoring surface; `/quizzes` remains analytics.
- `admin-frontend/src/app/flashcards/page.tsx`: extend existing vocabulary manager only if media audit requires it.
- `admin-frontend/src/components/*Form.tsx`: focused reusable forms, not generic form factories.
- `admin-frontend/src/lib/api.ts`: typed lesson/quiz/course contracts.
- `admin-frontend/src/lib/admin-navigation.mjs`: add only missing lesson and quiz-management destinations.

### Cleanup and tests

- `tests/Feature/Api/V1/*`: API validation, ownership, capability, idempotency, and transaction tests.
- `tests/Feature/NextFrontendRedirectTest.php`: exact legacy redirect and callback behavior.
- `tests/Feature/NoBladeRuntimeTest.php`: route and tracked-file gate.
- `resources/views/**/*.blade.php`: deleted only in final chunk.

---

## Chunk 1: Learner API Parity

### Task 1: Add idempotent saved-vocabulary API

**Files:**
- Create: `app/Http/Controllers/Api/V1/BookmarkController.php`
- Modify: `app/Http/Controllers/Api/V1/VocabularyController.php`
- Modify: `app/Http/Controllers/Api/V1/CatalogController.php`
- Modify: `app/Http/Resources/VocabularyResource.php`
- Modify: `app/Http/Resources/TopicResource.php`
- Modify: `routes/spa.php`
- Test: `tests/Feature/Api/V1/VocabularyApiTest.php`
- Test: `tests/Feature/Api/V1/CatalogApiTest.php`

- [ ] **Step 1: Write failing catalog and bookmark tests**

Add tests proving `topic_id` and `saved=1` filter correctly, authenticated items expose `is_bookmarked`, anonymous catalog data does not leak another user's bookmark, and repeated PUT requests are idempotent.

Also cover `401` bookmark mutation, `404` vocabulary, and `422` invalid `bookmarked`, `topic_id`, `saved`, `page`, and `per_page`. Define anonymous `saved=1` as `401` because saved state has no anonymous owner.

Add a public typed `GET /api/v1/catalog/topics` test returning ordered `{ id, name, slug }` items through `TopicResource`; this is the learner vocabulary filter source.

```php
$this->actingAs($user)
    ->putJson("/api/v1/vocabulary/{$word->id}/bookmark", ['bookmarked' => true])
    ->assertOk()
    ->assertJsonPath('data.bookmarked', true);

$this->actingAs($user)
    ->putJson("/api/v1/vocabulary/{$word->id}/bookmark", ['bookmarked' => true])
    ->assertOk();

$this->assertDatabaseCount('bookmarks', 1);
```

- [ ] **Step 2: Run the focused test and confirm failure**

Run: `php artisan test tests/Feature/Api/V1/VocabularyApiTest.php tests/Feature/Api/V1/CatalogApiTest.php`

Expected: FAIL because the bookmark route/filter contract does not exist.

- [ ] **Step 3: Implement the minimum contract**

Add:

```php
Route::put('/vocabulary/{vocabulary}/bookmark', [BookmarkController::class, 'update']);
Route::get('/catalog/topics', [CatalogController::class, 'topics']);
```

Validate `bookmarked` as required boolean and catalog filters as `topic_id: nullable integer exists`, `saved: nullable boolean`, `page: integer min:1`, and `per_page: integer min:1 max:100`. Use `updateOrCreate` when true and owner-scoped delete when false. Extend the existing vocabulary query with grouped search conditions, `topic_id`, and an authenticated `whereHas('bookmarks')` saved filter. Compute `is_bookmarked` without N+1 queries.

Eager-load `topic` and `lesson`. `VocabularyResource` returns the existing catalog fields plus `example`, `image_url`, `audio_url`, `topic: { id, name } | null`, `lesson: { id, title } | null`, and request-user-only `is_bookmarked`. It never returns another user's bookmark relation.

- [ ] **Step 4: Run tests**

Run: `php artisan test tests/Feature/Api/V1/VocabularyApiTest.php tests/Feature/Api/V1/CatalogApiTest.php`

Expected: PASS.

- [ ] **Step 5: Commit only task files**

```bash
git add app/Http/Controllers/Api/V1/BookmarkController.php app/Http/Controllers/Api/V1/CatalogController.php app/Http/Controllers/Api/V1/VocabularyController.php app/Http/Resources/TopicResource.php app/Http/Resources/VocabularyResource.php routes/spa.php tests/Feature/Api/V1/CatalogApiTest.php tests/Feature/Api/V1/VocabularyApiTest.php
git diff --cached --name-only
git commit -m "feat: add learner saved vocabulary api"
```

### Task 2: Add learner lesson-quiz attempt API

**Files:**
- Create: `app/Http/Controllers/Api/V1/LessonQuizController.php`
- Create: `app/Http/Requests/Api/V1/SubmitLessonQuizAnswerRequest.php`
- Create: `app/Http/Resources/LessonQuizResource.php`
- Create: `app/Models/AttemptAnswer.php`
- Create: `app/Services/LessonQuizService.php`
- Create: `database/migrations/2026_07_28_150000_extend_attempts_for_lesson_quizzes.php`
- Modify: `app/Models/Attempt.php`
- Modify: `app/Models/Quiz.php`
- Modify: `app/Http/Controllers/QuizController.php`
- Modify: `routes/spa.php`
- Test: `tests/Feature/Api/V1/LessonQuizApiTest.php`

- [ ] **Step 1: Write failing eligibility and secrecy tests**

Cover unauthenticated access, authenticated non-learner access, another user's attempt, unpublished lesson/quiz, missing enrollment/assignment, empty quiz, and ensure discovery/start responses never contain `is_correct`.

- [ ] **Step 2: Write failing lifecycle tests**

Test atomic start replay, two starts with different request IDs producing one active attempt, answer ownership, question/answer relationship validation, same-answer replay, changed answer `409 ANSWER_LOCKED`, incomplete completion, completion replay, score/pass calculation, and result ownership.

Request shape:

```json
{ "answer_id": 123 }
```

Response shape:

```json
{
  "data": {
    "question_id": 1,
    "answer_id": 123,
    "is_correct": true,
    "explanation": "..."
  },
  "meta": {}
}
```

- [ ] **Step 3: Run focused tests and confirm failure**

Run: `php artisan test tests/Feature/Api/V1/LessonQuizApiTest.php`

Expected: FAIL with missing routes/classes.

- [ ] **Step 4: Implement the service transaction**

Implement owner-scoped methods `start`, `answer`, `complete`, and `show`. Keep correctness flags out of the start resource. Validate that the selected answer belongs to the route question and that the route question belongs to the attempt quiz before revealing correctness.

Add the required migration because the existing non-null `attempts.score` and lack of answer rows cannot represent active attempts:

```text
attempts.status string default 'completed' index
attempts.correct_answers unsignedInteger nullable
attempts.total_questions unsignedInteger nullable
attempts.active_quiz_id unsignedBigInteger nullable
unique attempts(user_id, active_quiz_id)

attempt_answers.id
attempt_answers.attempt_id FK cascade
attempt_answers.question_id unsignedBigInteger (snapshot identifier; no FK)
attempt_answers.answer_id unsignedBigInteger (snapshot identifier; no FK)
attempt_answers.answer_content_snapshot text
attempt_answers.is_correct boolean
attempt_answers.explanation_snapshot text nullable
timestamps
unique(attempt_id, question_id)
```

The migration backfills existing attempts as `status=completed`, leaves `active_quiz_id=null`, and preserves their non-null score so existing quiz/progress/admin analytics remain compatible. New active attempts use `score=0`, `status=active`, `total_questions` fixed to the current question count, and `active_quiz_id=quiz_id`; completion writes score/counts/status and clears `active_quiz_id` atomically. Question and answer identifiers are deliberately scalar snapshots without foreign keys so later admin authoring replacement cannot erase completed attempt replay evidence.

Quiz authoring replacement is rejected while any active attempt exists for that quiz. Add `Quiz::hasActiveAttempts()` in this task. Both lesson-quiz start and every authoring update/delete must lock the same quiz row with `lockForUpdate()` and evaluate/create/mutate inside one database transaction. Guard the temporary legacy `QuizController::update/destroy` paths with that transaction and a safe validation/redirect error so Chunk 1 is independently stable while Blade admin still exists. Task 9 must reuse the same locked transaction rule and return JSON `409 ACTIVE_ATTEMPTS`. Test a start-versus-update race plus both legacy and admin guards. This keeps the active attempt's question set stable without duplicating every unsubmitted question at start.

Use transactions for start, answer, and complete. Start locks the quiz row, checks for an existing active attempt, then inserts. The unique `(user_id, active_quiz_id)` constraint is the final concurrent-start guard; catch its duplicate-key race and return the winning active attempt. Return stored answer/result snapshots for replay.

All routes are registered inside the existing `auth` group. The controller requires the authenticated user to have learner role. Eligibility is granted by either an enrollment owned by the learner with status `active` or `completed`, or an assignment owned by the learner with status `pending`, `in_progress`, or `completed`; `cancelled` assignments never grant access.

Every domain and validation failure uses the safe `ApiResponse` JSON envelope rather than `abort()` or Laravel's default validation JSON. `SubmitLessonQuizAnswerRequest::failedValidation()` returns `ApiResponse::error('VALIDATION_ERROR', 'The given data was invalid.', 422, ['errors' => $validator->errors()])`; query validation uses the same shape.

Exact success contracts:

```json
// GET /lesson-quizzes?lesson_id=1
{ "data": [{ "id": 1, "lesson_id": 1, "title": "Checkpoint", "passing_score": 60, "questions_count": 3 }], "meta": {} }

// POST start and GET active attempt
{ "data": { "id": 10, "quiz": { "id": 1, "title": "Checkpoint", "passing_score": 60 }, "status": "active", "questions": [{ "id": 7, "content": "...", "answers": [{ "id": 20, "content": "..." }] }], "submitted_answers": [{ "question_id": 7, "answer_id": 20, "answer_content": "...", "is_correct": true, "explanation": "..." }] }, "meta": {} }

// POST complete and GET completed attempt
{ "data": { "id": 10, "status": "completed", "score": 67, "correct_answers": 2, "total_questions": 3, "passed": true, "completed_at": "..." }, "meta": {} }
```

Error mappings: invalid/missing `lesson_id` and relationship mismatch → `422 VALIDATION_ERROR`; unavailable unpublished/ineligible entity → `404 NOT_FOUND`; empty quiz → `409 QUIZ_EMPTY`; changed answer → `409 ANSWER_LOCKED`; incomplete completion → `409 ATTEMPT_INCOMPLETE`; other-user attempt → `403 FORBIDDEN`.

Add reload tests that: (1) submit at least one answer and assert active GET restores selected answer, correctness, explanation snapshot, and locked state; (2) assert authoring replacement is rejected while that attempt is active; and (3) complete the attempt, allow authoring replacement, then assert completed-result replay remains stable from stored snapshots.

- [ ] **Step 5: Register exact routes**

```php
Route::get('/lesson-quizzes', [LessonQuizController::class, 'index']);
Route::post('/lesson-quizzes/{quiz}/attempts', [LessonQuizController::class, 'start']);
Route::put('/lesson-quiz-attempts/{attempt}/answers/{question}', [LessonQuizController::class, 'answer']);
Route::post('/lesson-quiz-attempts/{attempt}/complete', [LessonQuizController::class, 'complete']);
Route::get('/lesson-quiz-attempts/{attempt}', [LessonQuizController::class, 'show']);
```

- [ ] **Step 6: Run learner API and full auth tests**

Run: `php artisan test tests/Feature/Api/V1/LessonQuizApiTest.php tests/Feature/Api/V1/AuthApiTest.php tests/Feature/Api/V1/ProgressApiTest.php tests/Feature/Api/V1/AdminLearningApiTest.php`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/V1/LessonQuizController.php app/Http/Controllers/QuizController.php app/Http/Requests/Api/V1/SubmitLessonQuizAnswerRequest.php app/Http/Resources/LessonQuizResource.php app/Models/Attempt.php app/Models/AttemptAnswer.php app/Models/Quiz.php app/Services/LessonQuizService.php database/migrations/2026_07_28_150000_extend_attempts_for_lesson_quizzes.php routes/spa.php tests/Feature/Api/V1/LessonQuizApiTest.php
git diff --cached --name-only
git commit -m "feat: add learner lesson quiz api"
```

### Task 3: Normalize authentication destinations

**Files:**
- Modify: `app/Http/Controllers/SocialController.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `tests/Feature/SocialLoginTest.php`
- Modify: `tests/Feature/Api/V1/PasswordApiTest.php`
- Modify: `tests/Feature/Api/V1/AuthApiTest.php`

- [ ] **Step 1: Write failing URL tests**

Capture the actual `ResetPassword` notification, parse its URL, extract `token` and `email`, and complete a password reset through `POST /api/v1/auth/password/reset`. Assert Facebook success redirects to `FRONTEND_URL/profile`, provider failures redirect exactly to `FRONTEND_URL/login?error={safe-code}` using one of `oauth_cancelled`, `oauth_email_missing`, or `oauth_failed` with no provider text, and Google safe-return validation rejects `//host` and overlong paths.

Add signed verification tests for valid, expired, and tampered `/api/v1/auth/email/verify/{id}/{hash}` URLs. Valid links mark the user verified and redirect to `FRONTEND_URL/login?verified=1`; invalid signatures never verify the user.

- [ ] **Step 2: Run focused tests**

Run: `php artisan test tests/Feature/SocialLoginTest.php tests/Feature/Api/V1/PasswordApiTest.php`

Expected: the Facebook destination test fails before implementation.

- [ ] **Step 3: Implement only destination normalization**

Reuse `safeReturnPath` for provider returns. Catch provider exceptions before redirecting with a finite safe error code; never include provider messages. Do not change Laravel's signed verification callback or move reset tokens into route segments.

- [ ] **Step 4: Run tests and commit**

Run: `php artisan test tests/Feature/SocialLoginTest.php tests/Feature/Api/V1/PasswordApiTest.php tests/Feature/Api/V1/AuthApiTest.php`

Expected: PASS.

Commit: `fix: normalize frontend authentication redirects`

Before committing:

```bash
git add app/Http/Controllers/SocialController.php app/Providers/AppServiceProvider.php tests/Feature/SocialLoginTest.php tests/Feature/Api/V1/PasswordApiTest.php tests/Feature/Api/V1/AuthApiTest.php
git diff --cached --name-only
git commit -m "fix: normalize frontend authentication redirects"
```

### Task 3.1: Verify Chunk 1 as working software

**Files:** None.

- [ ] **Step 1: Run the full backend suite**

Run: `php artisan test`

Expected: PASS, including legacy analytics that read `attempts.score`.

- [ ] **Step 2: Inspect schema and routes**

Run: `php artisan route:list --path=api/v1` and confirm all new routes are authenticated JSON routes. Run the migration against the test database through the suite and confirm rollback succeeds.

---

## Chunk 2: Learner Next.js Parity

### Task 4: Add missing authentication pages without duplicating login

**Files:**
- Create: `frontend/src/features/auth/auth-shell.tsx`
- Create: `frontend/src/features/auth/register-page.tsx`
- Create: `frontend/src/features/auth/forgot-password-page.tsx`
- Create: `frontend/src/features/auth/reset-password-page.tsx`
- Create: `frontend/src/features/auth/verify-email-page.tsx`
- Create: `frontend/src/app/register/page.tsx`
- Create: `frontend/src/app/forgot-password/page.tsx`
- Create: `frontend/src/app/reset-password/page.tsx`
- Create: `frontend/src/app/verify-email/page.tsx`
- Create: `frontend/src/app/auth/facebook/route.ts`
- Modify: `frontend/src/app/login/page.tsx`
- Modify: `frontend/src/features/auth/login-page.tsx`
- Modify: `frontend/src/components/layout/app-shell.tsx`
- Modify: `frontend/src/lib/api.ts`
- Modify: `frontend/.env.example`

- [ ] **Step 1: Read relevant Next.js 16 docs**

Read completely:

- `frontend/node_modules/next/dist/docs/01-app/01-getting-started/15-route-handlers.md`
- `frontend/node_modules/next/dist/docs/01-app/02-guides/forms.md`
- `frontend/node_modules/next/dist/docs/01-app/02-guides/redirecting.md`

- [ ] **Step 2: Add typed auth helpers**

Keep the existing `auth.login/register/forgotPassword/resetPassword`. Add `auth.resendVerification()` returning `{ message: string }`. Do not introduce another fetch wrapper.

- [ ] **Step 3: Build one shared auth shell**

Extract only the repeated centered learning-brand frame, error summary, and navigation footer. Keep each form's state local. Maintain labels, autocomplete tokens, visible focus, `role="alert"`, and `aria-live` success.

Update `AppShell` with an exact public-route allowlist for `/login`, `/register`, `/forgot-password`, `/reset-password`, and `/verify-email`. These routes render children without calling `api.me()`, without learner navigation, and without redirecting a legitimate guest. Do not use a broad prefix that could expose protected pages.

- [ ] **Step 4: Add route pages**

`/reset-password` reads `token` and `email` with `useSearchParams`; wrap the client feature in `Suspense` as required by the current Next build. `frontend/src/app/login/page.tsx` also wraps `LoginPage` in `Suspense` because login reads `verified`/`error`. Missing reset token/email produces a safe invalid-link state without sending a request.

The Facebook route handler mirrors Google:

```ts
const laravelOrigin = process.env.LARAVEL_API_ORIGIN ?? "http://localhost:8080";

export function GET() {
  return Response.redirect(`${laravelOrigin}/auth/facebook`);
}
```

- [ ] **Step 5: Extend login, do not replace it**

Add register/forgot links, optional Facebook button, `verified=1` success, and safe `error` query handling. Preserve existing email/password and Google behavior.

Declare `NEXT_PUBLIC_GOOGLE_AUTH_ENABLED=false` and `NEXT_PUBLIC_FACEBOOK_AUTH_ENABLED=false` in `frontend/.env.example`. Render each provider button only when its exact flag is `"true"`; backend entry/callback routes remain registered regardless. Production enables a flag only when the corresponding Laravel client ID/secret is configured.

- [ ] **Step 6: Verify frontend**

Run:

```bash
cd frontend
pnpm exec tsc --noEmit
pnpm exec eslint .
pnpm build
```

Expected: all commands exit 0 and routes `/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`, `/auth/google`, and `/auth/facebook` appear in build output.

Request `/auth/facebook` in a browser or with `curl -I`, follow only to the Laravel entry, and assert Laravel's provider redirect includes OAuth state. Complete the configured test callback and assert it returns to the learner frontend; never log the state/token.

- [ ] **Step 7: Commit**

```bash
git add frontend/src/features/auth frontend/src/app/login/page.tsx frontend/src/app/register frontend/src/app/forgot-password frontend/src/app/reset-password frontend/src/app/verify-email frontend/src/app/auth/facebook frontend/src/features/auth/login-page.tsx frontend/src/components/layout/app-shell.tsx frontend/src/lib/api.ts frontend/.env.example
git diff --cached --name-only
git commit -m "feat: add complete nextjs authentication flow"
```

### Task 5: Extend the existing profile page

**Files:**
- Modify: `frontend/src/features/profile/profile-page.tsx`
- Modify: `frontend/src/components/ui/dialog.tsx`
- Modify: `frontend/src/lib/api.ts`

- [ ] **Step 1: Confirm backend contract tests pass**

Run: `php artisan test tests/Feature/Api/V1/ProfileApiTest.php`

- [ ] **Step 2: Add independent password state**

Use the existing `profile.changePassword`. Add current/new/confirmation inputs and independent `changingPassword`/message state. Clear password values only after success.

- [ ] **Step 3: Add confirmed account deletion**

Upgrade and reuse `frontend/src/components/ui/dialog.tsx`: add `role="dialog"`, `aria-modal="true"`, labelled title/description IDs, initial focus, Tab focus containment, Escape close, and focus restoration to the trigger. Require password, show irreversible consequences, disable while submitting, call `profile.destroy`, then redirect to `/login`. Do not optimistically hide the account UI.

- [ ] **Step 4: Verify keyboard and build**

Tab through both forms and dialog; Escape closes the dialog; focus returns to the trigger. Run TypeScript, ESLint, and build.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/profile/profile-page.tsx frontend/src/components/ui/dialog.tsx frontend/src/lib/api.ts
git diff --cached --name-only
git commit -m "feat: complete nextjs profile security controls"
```

### Task 6: Extend vocabulary with URL filters and saved state

**Files:**
- Modify: `frontend/src/features/vocabulary/vocabulary-page.tsx`
- Modify: `frontend/src/app/vocabulary/page.tsx`
- Modify: `frontend/src/lib/api.ts`

- [ ] **Step 1: Add concrete API types**

Extend `VocabularyItem` with `topic?: { id: number; name: string }` and `is_bookmarked: boolean`. Extend `api.vocabulary` input with `topicId?: number` and `saved?: boolean`, serialized as `topic_id` and `saved`. Add typed `bookmarkVocabulary(id, bookmarked)`.

Add `api.catalogTopics()` for the Task 1 `GET /api/v1/catalog/topics` contract and use it as the topic-option source. Do not use the currently nonexistent `/api/topics` call or hardcode topics.

- [ ] **Step 2: Make filters URL-backed**

Parse browser `search`, `topic_id`, `page`, and `view=all|saved`. Map `view=saved` to API `saved=1`; serialize saved state back as `view=saved`, never `saved=1`, so legacy `/bookmarks → /vocabulary?view=saved` works. Invalid values fall back to `view=all` and page 1. Keep the current page rather than creating `/bookmarks`. Update the URL with Next router without full reload.

- [ ] **Step 3: Add accessible bookmark controls**

Each icon button has `aria-label` that changes between save/remove. Update only the affected item after API success; restore it and announce failure if mutation fails.

- [ ] **Step 4: Add optional detail dialog, not a new route**

Show example, lesson, topic, definition, pronunciation, and audio only when present. Support `?word={id}` for legacy word redirects.

- [ ] **Step 5: Verify and commit**

Run Laravel vocabulary tests plus learner TypeScript, ESLint, and build.

```bash
git add frontend/src/features/vocabulary/vocabulary-page.tsx frontend/src/app/vocabulary/page.tsx frontend/src/lib/api.ts
git diff --cached --name-only
git commit -m "feat: merge saved words into vocabulary catalog"
```

### Task 7: Integrate lesson quizzes into the existing quiz route

**Files:**
- Create: `frontend/src/features/quiz/lesson-quiz.tsx`
- Modify: `frontend/src/features/quiz/quiz-page.tsx`
- Modify: `frontend/src/app/quiz/page.tsx`
- Modify: `frontend/src/app/courses/[id]/page.tsx`
- Modify: `frontend/src/lib/api.ts`

- [ ] **Step 1: Add exact lesson-quiz types and API methods**

Model discovery, question without correctness, answer feedback, attempt summary, and `ApiError` codes. Do not reuse vocabulary `Word` types.

- [ ] **Step 2: Route by query, preserving current quiz**

Wrap the query-reading client feature with `Suspense` in `frontend/src/app/quiz/page.tsx` for Next.js 16 production builds. When `lesson_quiz` or `attempt` exists, render `LessonQuiz`; otherwise render the existing vocabulary quiz unchanged.

Use the discovery endpoint from the existing course-detail lesson path: for each accessible lesson fetch/list its published lesson quizzes and render “Làm quiz” links to `/quiz?lesson_quiz={id}`. Do not add another learner quiz directory page.

- [ ] **Step 3: Implement the focused state machine**

States: loading → question → feedback → next → summary. Lock answers after submit, expose progress, preserve manual retry, and reload both active and completed attempts through GET. For active attempts, restore `submitted_answers`, selected choices, correctness, explanation, and locked state before choosing the next unanswered question. Summary renders “Làm lại” (POST a new attempt after completion) and “Bước học tiếp theo” links back to the originating course/lesson or Today when origin metadata is absent.

- [ ] **Step 4: Verify degraded and authorization states**

Manually verify `401`, `403`, `409 QUIZ_EMPTY`, `409 ANSWER_LOCKED`, `409 ATTEMPT_INCOMPLETE`, `422`, and network retry. Ensure hidden correctness is never inferred before submit.

- [ ] **Step 5: Run checks and commit**

Run learner TypeScript, ESLint, build, and lesson-quiz Laravel tests.

```bash
git add frontend/src/features/quiz/lesson-quiz.tsx frontend/src/features/quiz/quiz-page.tsx frontend/src/app/quiz/page.tsx frontend/src/app/courses/'[id]'/page.tsx frontend/src/lib/api.ts
git diff --cached --name-only
git commit -m "feat: add lesson quizzes to learner journey"
```

### Task 7.1: Complete learner compatibility audit

**Files:**
- Modify only if needed: `frontend/src/lib/api.ts`
- Test: relevant Laravel feature tests for every retained endpoint used by Tasks 4–7.

- [ ] **Step 1: Classify every request used by replacement pages**

Record each call as canonical `/api/v1`, retained non-Blade JSON, migrated, or removed. At minimum inspect `/api/topics`, `/api/words*`, `/api/quiz*`, `/api/progress*`, `/api/streak`, `/api/notifications*`, `/api/flashcards*`, `/api/enrichment*`, `/api/dictionary*`, and `/api/import*`.

- [ ] **Step 2: Prove retained endpoints are JSON-only**

Run their feature tests and inspect route actions. No replacement page may call a route that returns view/redirect HTML.

- [ ] **Step 3: Normalize only obsolete calls**

Prefer existing `/api/v1` methods already in `api.ts`; remove dead duplicates. Do not rename stable JSON endpoints solely for aesthetics.

- [ ] **Step 4: Run the full Laravel and learner gates**

Run `php artisan test`, then learner TypeScript, ESLint, and build.

- [ ] **Step 5: Commit only if audit changed code**

```bash
git add frontend/src/lib/api.ts tests
git diff --cached --name-only
git commit -m "refactor: normalize learner api calls"
```

---

## Chunk 3: Administrator API and Next.js Parity

### Task 8: Add admin lesson JSON API

**Files:**
- Create: `app/Http/Controllers/Api/V1/Admin/LessonController.php`
- Create: `app/Http/Requests/Api/V1/Admin/WriteLessonRequest.php`
- Modify: `app/Http/Resources/LessonResource.php`
- Modify: `routes/spa.php`
- Test: `tests/Feature/Api/V1/AdminLessonApiTest.php`

- [ ] **Step 1: Write failing CRUD, lifecycle, and capability tests**

Cover filters/pagination, course-scoped slug uniqueness, create/show/update, publish/archive, `manage-content`, deletion success for unused draft, and `409 DEPENDENCY_EXISTS` for learning evidence.

All routes live inside the existing `auth` + `google.admin` group and call `$request->user()->can('manage-content')`. Exact routes:

```php
Route::get('/admin/catalog/lessons', [AdminLessonController::class, 'index']);
Route::post('/admin/catalog/lessons', [AdminLessonController::class, 'store']);
Route::get('/admin/catalog/lessons/{lesson}', [AdminLessonController::class, 'show']);
Route::put('/admin/catalog/lessons/{lesson}', [AdminLessonController::class, 'update']);
Route::post('/admin/catalog/lessons/{lesson}/publish', [AdminLessonController::class, 'publish']);
Route::post('/admin/catalog/lessons/{lesson}/archive', [AdminLessonController::class, 'archive']);
Route::delete('/admin/catalog/lessons/{lesson}', [AdminLessonController::class, 'destroy']);
```

Write body:

```json
{ "course_id": 1, "title": "Greetings", "slug": "greetings", "content": "...", "sort_order": 1, "estimated_minutes": 10, "status": "draft" }
```

List returns `data[]` plus standard pagination `meta`. Show/write returns `{ data: { id, course, title, slug, content, sort_order, estimated_minutes, status, vocabularies_count, quizzes }, meta: {} }`. Validation errors use `422 VALIDATION_ERROR`; missing entity `404`; capability failure `403`; invalid lifecycle `409 INVALID_STATE`.

- [ ] **Step 2: Run focused tests and confirm failure**

Run: `php artisan test tests/Feature/Api/V1/AdminLessonApiTest.php`

- [ ] **Step 3: Implement routes and controller using existing models/resources**

Do not create a repository or interface. Reuse `ApiResponse`, `LessonResource`, policies/gates, and current catalog pagination conventions.

For deletion, begin a transaction, lock the lesson row, and count dependencies: `progress`, assignments whose status is `pending|in_progress`, quiz attempts through lesson quizzes, and learning sessions/events/evidence tied to the lesson. Any count blocks with `409 DEPENDENCY_EXISTS` and safe named counts. Only an unused draft may be deleted. Publish/archive also lock the lesson row and validate transitions.

- [ ] **Step 4: Run tests and commit**

Expected: focused tests PASS.

```bash
git add app/Http/Controllers/Api/V1/Admin/LessonController.php app/Http/Requests/Api/V1/Admin/WriteLessonRequest.php app/Http/Resources/LessonResource.php routes/spa.php tests/Feature/Api/V1/AdminLessonApiTest.php
git diff --cached --name-only
git commit -m "feat: add admin lesson api"
```

### Task 9: Add transactional admin quiz-authoring API

**Files:**
- Create: `app/Http/Controllers/Api/V1/Admin/QuizController.php`
- Create: `app/Http/Requests/Api/V1/Admin/WriteQuizRequest.php`
- Create: `app/Services/AdminQuizService.php`
- Modify: `routes/spa.php`
- Test: `tests/Feature/Api/V1/AdminQuizApiTest.php`

- [ ] **Step 1: Write failing contract tests**

Cover filters, complete authoring payload, minimum question/answers/correct answer validation, read correctness authorization, create/update, and attempt-protected deletion.

Register inside the same admin group:

```php
Route::get('/admin/catalog/quizzes', [AdminQuizController::class, 'index']);
Route::post('/admin/catalog/quizzes', [AdminQuizController::class, 'store']);
Route::get('/admin/catalog/quizzes/{quiz}', [AdminQuizController::class, 'show']);
Route::put('/admin/catalog/quizzes/{quiz}', [AdminQuizController::class, 'update']);
Route::delete('/admin/catalog/quizzes/{quiz}', [AdminQuizController::class, 'destroy']);
```

Every quiz list/show/write explicitly requires `$request->user()->can('manage-content')`; `google.admin` alone is not sufficient. Tests cover `403` for an authenticated Google admin without that capability, including correctness-bearing show.

List accepts `search`, `lesson_id`, `status`, `page`, `per_page` and returns paginated summaries. Show/create/update return the approved nested payload from the design inside `{ data, meta }`, including correctness flags only for `manage-content` users. Validation uses `422 VALIDATION_ERROR`; active-attempt update uses `409 ACTIVE_ATTEMPTS`; any-attempt delete uses `409 DEPENDENCY_EXISTS`; forbidden uses `403`.

- [ ] **Step 2: Write rollback test**

Force a nested answer insert failure and assert the previous quiz/question/answer tree remains unchanged.

- [ ] **Step 3: Run tests and confirm failure**

Run: `php artisan test tests/Feature/Api/V1/AdminQuizApiTest.php`

- [ ] **Step 4: Implement minimal transaction service**

Validate the complete payload before entering the transaction. Within one transaction lock the same quiz row used by Chunk 1 start, check `hasActiveAttempts()`, update quiz fields, replace nested questions/answers, and return a refreshed authoring resource. Destroy locks the row and rejects when any attempt exists. Avoid generic nested-form abstractions.

- [ ] **Step 5: Run tests and commit**

```bash
git add app/Http/Controllers/Api/V1/Admin/QuizController.php app/Http/Requests/Api/V1/Admin/WriteQuizRequest.php app/Services/AdminQuizService.php routes/spa.php tests/Feature/Api/V1/AdminQuizApiTest.php
git diff --cached --name-only
git commit -m "feat: add transactional admin quiz api"
```

### Task 10: Extend admin courses and add lesson management UI

**Files:**
- Create: `admin-frontend/src/app/lessons/page.tsx`
- Create: `admin-frontend/src/components/LessonForm.tsx`
- Modify: `app/Http/Controllers/Api/V1/Admin/CatalogController.php`
- Modify: `app/Http/Resources/CourseResource.php`
- Modify: `admin-frontend/src/app/courses/page.tsx`
- Modify: `admin-frontend/src/lib/api.ts`
- Modify: `admin-frontend/src/lib/admin-navigation.mjs`
- Test: `admin-frontend/scripts/check-admin-navigation.mjs`
- Test: `tests/Feature/Api/V1/AdminCatalogApiTest.php`

- [ ] **Step 1: Read `admin-frontend/AGENTS.md` and Next.js local docs**

Read relevant route, form, and redirect docs from `admin-frontend/node_modules/next/dist/docs/` before editing.

- [ ] **Step 2: Add typed admin lesson/course methods**

Add list/show/create/update/publish/archive/delete methods and page metadata. Extend course types with level/topic relations needed by the existing form.

Extend `GET /api/v1/admin/catalog/courses` and `adminCourses.list` with typed `search`, `status`, `level_id`, `page`, and `per_page` parameters. Validate enum/existence/page bounds, apply the filters in the controller, and return the standard pagination metadata. First add failing tests proving each filter, combined filters, invalid values, and page navigation; then implement the controller and client contract. A query-state change in `/courses` must trigger a filtered reload rather than only changing the URL.

First write failing `AdminCatalogApiTest` cases for `level_id` and `topic_ids`. Extend course create/update validation with `level_id: nullable exists:levels,id`, `topic_ids: sometimes array distinct`, `topic_ids.*: exists:topics,id`. New creates default omitted `topic_ids` to `[]`; updates call `$course->topics()->sync(...)` only when the key is present, preserving existing callers and relations otherwise. Wrap course write and any requested sync in one transaction. `CourseResource` returns `level` and `topics`; course list/show/create/update share this shape. Run the focused test red then green before editing Next.js controls.

- [ ] **Step 3: Extend `/courses` in place**

Keep the existing list/modal. Add level/topic assignment, publish/archive actions, and a detail panel. Do not add course hard-delete UI.
Parse `course={id}&mode=view|edit` in a component rendered below `Suspense`; fetch and open the requested entity after the list loads, and clear invalid IDs with an accessible error. `mode=create` opens the empty modal.
Initialize and update the list from `search`, `status`, `level_id`, and `page` query keys so legacy redirects preserve filter state.

- [ ] **Step 4: Build `/lessons` as one management surface**

Use list + shared create/edit modal + detail drawer. Add course/status/search filters and related quiz link. Confirmation is required before archive/delete.
Parse `lesson={id}&mode=view|edit` below `Suspense`; fetch and open the requested entity after the list loads. `mode=create` opens the empty form and preserves optional `course_id` as its initial selection.
Initialize and update the list from `search`, `course_id`, `status`, and `page` query keys.

- [ ] **Step 5: Update and run navigation check**

Run: `cd admin-frontend && pnpm check:navigation`

Expected: learner/admin/super-admin navigation assertions pass and Lessons is visible only to content managers.

- [ ] **Step 6: Run admin checks and commit**

Run TypeScript, ESLint, production build, then relevant Laravel tests.

```bash
git add app/Http/Controllers/Api/V1/Admin/CatalogController.php app/Http/Resources/CourseResource.php tests/Feature/Api/V1/AdminCatalogApiTest.php admin-frontend/src/app/courses/page.tsx admin-frontend/src/app/lessons/page.tsx admin-frontend/src/components/LessonForm.tsx admin-frontend/src/lib/api.ts admin-frontend/src/lib/admin-navigation.mjs admin-frontend/scripts/check-admin-navigation.mjs
git diff --cached --name-only
git commit -m "feat: add nextjs lesson administration"
```

### Task 11: Add quiz management without replacing analytics

**Files:**
- Create: `admin-frontend/src/app/quiz-management/page.tsx`
- Create: `admin-frontend/src/components/QuizAuthoringForm.tsx`
- Modify: `admin-frontend/src/app/quizzes/page.tsx`
- Modify: `admin-frontend/src/lib/api.ts`
- Modify: `admin-frontend/src/lib/admin-navigation.mjs`

- [ ] **Step 1: Add exact authoring types and client calls**

Keep analytics methods under `adminLearning`. Put CRUD under `adminCatalog` or a focused `adminQuizzes` export; do not overload analytics response types.

- [ ] **Step 2: Build list/filter/detail states**

Support `lesson_id`/status/search/page, empty/error/loading, and legacy `?quiz={id}` deep state. Parse query state below `Suspense`: initialize and update `search`, `lesson_id`, `status`, and `page`; `quiz={id}&mode=view|edit` fetches and opens the requested entity; `mode=create` opens a form prefilled from optional `lesson_id`.

- [ ] **Step 3: Build the nested authoring form**

Use stable local IDs for unsaved questions/answers, native arrays, and focused helper functions. Enforce two answers and one correct answer before request while retaining Laravel as the trust boundary. Deletion opens an explicit named confirmation dialog, disables during the request, and retains the row on failure.

- [ ] **Step 4: Add explicit analytics link**

Keep `/quizzes` unchanged as performance analytics and link between analytics and `/quiz-management`; do not rename either route.

- [ ] **Step 5: Verify and commit**

Run admin navigation check, TypeScript, ESLint, build, and admin quiz API tests.

```bash
git add admin-frontend/src/app/quiz-management/page.tsx admin-frontend/src/components/QuizAuthoringForm.tsx admin-frontend/src/app/quizzes/page.tsx admin-frontend/src/lib/api.ts admin-frontend/src/lib/admin-navigation.mjs
git diff --cached --name-only
git commit -m "feat: add nextjs quiz authoring"
```

### Task 12: Preserve vocabulary media in the existing admin UI

**Files:**
- Modify: `app/Http/Controllers/Api/V1/Admin/CatalogController.php`
- Modify: `app/Http/Resources/VocabularyResource.php`
- Modify: `routes/spa.php`
- Modify: `admin-frontend/src/app/flashcards/page.tsx`
- Modify: `admin-frontend/src/lib/api.ts`
- Test: `tests/Feature/Api/V1/AdminCatalogApiTest.php`

- [ ] **Step 1: Record the usage audit**

Search tracked code and a development database for `image_path`, `audio_path`, and active consumers. Record counts in implementation notes. The plan treats media as used because learner resources expose it and the schema persists it.

- [ ] **Step 2: Write failing multipart and cleanup tests**

Using `Storage::fake('public')`, cover valid image/audio upload, MIME/size rejection, replacing one file, explicit removal, database failure cleanup of newly stored files, and vocabulary deletion cleanup without deleting unrelated paths.

- [ ] **Step 3: Implement the multipart contract**

Keep existing JSON create/update endpoints for metadata. Register `POST /api/v1/admin/catalog/vocabularies/{vocabulary}/media` inside the existing `auth` + `google.admin` admin group and explicitly enforce `manage-content`. It accepts optional `image`, `audio`, `remove_image`, and `remove_audio`; require at least one operation. Add a route test proving an authenticated admin without the capability receives `403`. Store new files first, update the database transactionally, delete newly stored files if DB update fails, and delete superseded files only after DB commit. On vocabulary deletion, delete the DB row first and then its owned files so a storage failure can create only an orphan, never data loss.

Return the standard typed vocabulary resource with `image_url` and `audio_url`. Validation errors use `422 VALIDATION_ERROR`; capability failures use `403`.

- [ ] **Step 4: Extend the existing flashcard modal and client**

Add a dedicated `uploadVocabularyMedia(id, formData)` request that does not set JSON `Content-Type`. Add preview, replace, and remove controls to the existing modal. Never add a second vocabulary CRUD page; preserve `/vocabulary → /flashcards`.
Parse `word={id}&mode=view|edit` below `Suspense`; fetch and open the requested vocabulary. `mode=create` opens the empty modal.
Initialize and update the list from `search` and `page` query keys.

- [ ] **Step 5: Verify and commit**

Run `php artisan test tests/Feature/Api/V1/AdminCatalogApiTest.php`, then admin TypeScript, ESLint, and build.

```bash
git add app/Http/Controllers/Api/V1/Admin/CatalogController.php app/Http/Resources/VocabularyResource.php routes/spa.php tests/Feature/Api/V1/AdminCatalogApiTest.php admin-frontend/src/app/flashcards/page.tsx admin-frontend/src/lib/api.ts
git diff --cached --name-only
git commit -m "feat: preserve vocabulary media in nextjs admin"
```

### Task 12.1: Verify Chunk 3 as working software

**Files:** None.

- [ ] **Step 1: Run backend gates**

Run: `php artisan test`

Expected: PASS, including learner attempt stability and admin analytics.

- [ ] **Step 2: Run all admin gates**

```bash
cd admin-frontend
npx tsc --noEmit
npm run lint
npm run check:navigation
npm run build
```

Expected: all commands exit 0.

---

## Chunk 4: Route Cutover and Blade Deletion

### Task 13: Add exact Next.js redirect routes and tests

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/SocialController.php`
- Modify: `admin-frontend/src/app/users/page.tsx`
- Create: `tests/Feature/NextFrontendRedirectTest.php`
- Modify: `tests/Feature/SkeletonRoutesTest.php`
- Modify: `tests/Feature/LoginTest.php`
- Modify: `tests/Feature/ForgotPasswordTest.php`
- Modify: `tests/Feature/ProfileTest.php`
- Modify: `tests/Feature/SocialLoginTest.php`

- [ ] **Step 1: Write the redirect matrix as failing tests**

Use configured `https://learner.test` and `https://admin.test`. Implement and test this exact contract:

| Legacy route | Destination | Forwarded query keys |
|---|---|---|
| `/` | `https://learner.test/` | none |
| `/login` | `/login` | none |
| `/register` | `/register` | none |
| `/forgot-password` | `/forgot-password` | none |
| `/reset-password/{token}?email=x` | `/reset-password?token={rawurlencoded-token}&email={rawurlencoded-email}` | `email` only; token comes from path |
| `/verify-email` | `/verify-email` | none |
| `/profile` | `/profile` | none |
| `/words` | `/vocabulary` | `search`, `topic_id`, `page` only |
| `/words/{vocabulary}` | `/vocabulary?word={id}` | none; route-model binding must prove existence first |
| `/bookmarks` | `/vocabulary?view=saved` | none |
| `/progress` | `/progress` | none |
| `/quizzes/{quiz}/attempt` | `/quiz?lesson_quiz={quiz}` | none |
| `/quizzes/{quiz}/result?attempt_id={id}` | `/quiz?attempt={id}` | `attempt_id` only, validated integer and learner-owned before redirect |
| `/admin`, `/admin/dashboard` | `https://admin.test/dashboard` | none |
| `/admin/users` | `/users` | `search`, `role`, `page` |
| `/admin/users/{user}` | `/users/{user}` | none |
| `/admin/courses` | `/courses` | `search`, `status`, `level_id`, `page` |
| `/admin/courses/create` | `/courses?mode=create` | none |
| `/admin/courses/{course}` | `/courses?course={id}&mode=view` | none |
| `/admin/courses/{course}/edit` | `/courses?course={id}&mode=edit` | none |
| `/admin/lessons` | `/lessons` | `search`, `course_id`, `status`, `page` |
| `/admin/lessons/create` | `/lessons?mode=create` | `course_id` only |
| `/admin/lessons/{lesson}` | `/lessons?lesson={id}&mode=view` | none |
| `/admin/lessons/{lesson}/edit` | `/lessons?lesson={id}&mode=edit` | none |
| `/admin/quizzes` | `/quiz-management` | `search`, `lesson_id`, `status`, `page` |
| `/admin/quizzes/create` | `/quiz-management?mode=create` | `lesson_id` only |
| `/admin/quizzes/{quiz}` | `/quiz-management?quiz={id}&mode=view` | none |
| `/admin/quizzes/{quiz}/edit` | `/quiz-management?quiz={id}&mode=edit` | none |
| `/admin/vocabularies` | `/flashcards` | `search`, `page` |
| `/admin/vocabularies/create` | `/flashcards?mode=create` | none |
| `/admin/vocabularies/{vocabulary}` | `/flashcards?word={id}&mode=view` | none |
| `/admin/vocabularies/{vocabulary}/edit` | `/flashcards?word={id}&mode=edit` | none |

All relative destinations above are joined to the applicable configured frontend origin. Use `rawurlencode` for path-derived token/IDs and `http_build_query` on a hardcoded allowlist for query data. Tests append `next=https://evil.test`, `secret=x`, and unrelated keys to every representative route and assert none are forwarded.

- [ ] **Step 2: Test preserved named callbacks**

Assert `login`, `password.request`, `password.reset`, `verification.notice`, `api.verification.verify`, `google.callback`, `facebook.login`, `facebook.callback`, and admin Google entry remain resolvable. Cover valid, expired, and tampered signed verification URLs.

- [ ] **Step 3: Run redirect tests and confirm failure**

Run: `php artisan test tests/Feature/NextFrontendRedirectTest.php tests/Feature/SkeletonRoutesTest.php`

- [ ] **Step 4: Replace view GET routes with explicit redirects**

Use small URL helpers in `routes/web.php` or one existing support helper; do not introduce a redirect service hierarchy. Preserve only allowlisted query keys. Keep health and callbacks in Laravel.
Before enabling the admin user redirects, make `/users` parse and update `search`, `role`, and `page` below `Suspense`, matching the same URL-state contract used by the other admin lists.

- [ ] **Step 5: Run all route/auth tests**

Expected: PASS with no response rendering Blade.

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/SocialController.php admin-frontend/src/app/users/page.tsx tests/Feature/NextFrontendRedirectTest.php tests/Feature/SkeletonRoutesTest.php tests/Feature/LoginTest.php tests/Feature/ForgotPasswordTest.php tests/Feature/ProfileTest.php tests/Feature/SocialLoginTest.php
git diff --cached --name-only
git commit -m "refactor: route legacy pages to nextjs"
```

### Task 14: Remove duplicate HTML mutation surfaces and Blade-only controllers

**Files:**
- Modify: `routes/web.php`
- Delete when no callback behavior remains:
  - `app/Http/Controllers/AuthController.php`
  - `app/Http/Controllers/ForgotPasswordController.php`
  - `app/Http/Controllers/EmailVerificationController.php`
  - `app/Http/Controllers/ProfileController.php`
  - `app/Http/Controllers/Admin/UserController.php`
  - `app/Http/Controllers/BookmarkController.php`
  - `app/Http/Controllers/CourseController.php`
  - `app/Http/Controllers/LessonController.php`
  - `app/Http/Controllers/ProgressController.php`
  - `app/Http/Controllers/QuizAttemptController.php`
  - `app/Http/Controllers/QuizController.php`
  - `app/Http/Controllers/VocabularyController.php`
  - `app/Http/Controllers/WordsController.php`
- Test: `tests/Feature/NoBladeRuntimeTest.php`
- Modify: `tests/Feature/RegistrationTest.php`
- Modify: `tests/Feature/ForgotPasswordTest.php`
- Modify: `tests/Feature/ProfileTest.php`
- Modify: `tests/Feature/AdminMiddlewareTest.php`
- Modify: `tests/Feature/RoleCapabilityTest.php`
- Modify: `tests/Feature/Api/V1/AdminUserApiTest.php`

- [ ] **Step 1: Write failing route-action audit**

Iterate Laravel's route collection and fail if a production route action points at a Blade-only controller. Assert old form mutation URLs return `404` or `405`.

- [ ] **Step 2: Remove legacy form routes**

Remove resource and learner form mutations only after their API parity tests pass. Do not remove OAuth callbacks, signed verification, logout API, health, or `/api/v1` routes.

Final legacy-controller disposition:

- delete root `AuthController`; `/login`, `/register`, and named `login` are redirect closures, while register/login/logout mutations live only in `Api\V1\AuthController`;
- delete root `ForgotPasswordController`; named password GET routes are redirect closures and mutations live only in `Api\V1\PasswordController`;
- delete root `EmailVerificationController`; `verification.notice` is a redirect closure, resend and signed verify live only in `Api\V1\EmailVerificationController` at `/api/v1/auth/email/*`;
- delete root `ProfileController`; profile GET is a redirect closure and update/password/delete live only in `Api\V1\ProfileController`;
- retain `SocialController` and `AdminGoogleAuthController` because they own OAuth callbacks, but assert neither contains `view()`;
- delete every other controller listed in this task after its web routes are removed.

Rewrite the remaining legacy feature tests before deleting controllers:

- `RegistrationTest`: assert the GET redirects to `/register`; keep registration behavior only in `AuthApiTest`;
- `ForgotPasswordTest`: assert GET redirects; keep reset behavior only in `PasswordApiTest`;
- `ProfileTest`: assert GET redirects; keep mutations only in `ProfileApiTest`;
- `AdminMiddlewareTest`: assert protected admin redirects and API authorization without Blade text;
- `RoleCapabilityTest`: move web mutation coverage into `AdminUserApiTest` and remove duplicate web assertions;
- `LoginTest`, `SocialLoginTest`, and `SkeletonRoutesTest`: retain only named redirects, callbacks, and health behavior.

Run this inventory and classify every result as redirect/callback coverage, API coverage, or deletion before proceeding:

```bash
rg -n "assertView|assertSee|route\('(admin|profile|password|register|quizzes|words|bookmarks)" tests
```

- [ ] **Step 3: Delete or reduce unused controllers**

Run `rg` for every controller class before deletion. Preserve shared services/models and API controllers.

- [ ] **Step 4: Run backend suite**

Run: `php artisan test`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php app/Http/Controllers/AuthController.php app/Http/Controllers/ForgotPasswordController.php app/Http/Controllers/EmailVerificationController.php app/Http/Controllers/ProfileController.php app/Http/Controllers/Admin/UserController.php app/Http/Controllers/BookmarkController.php app/Http/Controllers/CourseController.php app/Http/Controllers/LessonController.php app/Http/Controllers/ProgressController.php app/Http/Controllers/QuizAttemptController.php app/Http/Controllers/QuizController.php app/Http/Controllers/VocabularyController.php app/Http/Controllers/WordsController.php tests/Feature/NoBladeRuntimeTest.php tests/Feature/RegistrationTest.php tests/Feature/ForgotPasswordTest.php tests/Feature/ProfileTest.php tests/Feature/AdminMiddlewareTest.php tests/Feature/RoleCapabilityTest.php tests/Feature/Api/V1/AdminUserApiTest.php
git diff --cached --name-only
git commit -m "refactor: remove legacy blade controllers"
```

### Task 15: Delete Blade and prove zero runtime references

**Files:**
- Delete: `resources/views/**/*.blade.php`
- Delete: `resources/views/` when empty
- Modify: tests/docs only when they explicitly refer to Blade runtime

- [ ] **Step 1: Resolve exact deletion set**

Run:

```bash
find resources/views -type f -name '*.blade.php' -print | sort
git grep -n -E '\bview\(|View::make|@extends|@section' -- '*.php' '*.blade.php'
```

Expected before deletion: exactly the known legacy views/references; no newly introduced view.

- [ ] **Step 2: Delete the verified Blade files**

Use `apply_patch` deletions. Do not delete unrelated `resources/` assets.

- [ ] **Step 3: Run the zero-Blade gate**

```bash
find resources/views -type f -name '*.blade.php'
git grep -n -E '\bview\(|View::make|@extends|@section' -- '*.php' '*.blade.php'
php artisan route:list
```

Expected: the first two commands return no matches; no route action points to a removed controller.

- [ ] **Step 4: Run all automated verification**

```bash
php artisan test
cd frontend && pnpm exec tsc --noEmit && pnpm exec eslint . && pnpm build
cd ../admin-frontend && npx tsc --noEmit && npm run lint && npm run check:navigation && npm run build
cd .. && git diff --check
```

Expected: all commands exit 0.

- [ ] **Step 5: Run browser smoke checklist**

Verify desktop and mobile:

1. register/resend/verify/login;
2. forgot/reset/login;
3. Google and Facebook learner callbacks;
4. profile update/password/delete confirmation;
5. vocabulary search/topic/saved/detail;
6. learner lesson quiz and reload-safe result;
7. admin Google handoff;
8. course/lesson/quiz/vocabulary management;
9. legacy redirect matrix and authorization failures;
10. keyboard focus, `aria-live`, loading/error/empty states.

Record in the implementation handoff whether the available browser automation or the manual checklist was used, the tested desktop/mobile viewport sizes, and any provider flow skipped because test credentials were unavailable.

- [ ] **Step 6: Request final code review**

Use `@requesting-code-review` against the complete diff. Fix blocking findings and rerun the relevant checks.

- [ ] **Step 7: Commit the deletion**

Before committing, verify `git diff --cached --name-only` contains only the reviewed cutover/deletion files.

```bash
git add -u resources/views
# Add only a named test/doc file here if Task 15 actually changed it.
git diff --cached --name-only
git commit -m "refactor: remove blade ui runtime"
```

### Task 16: Rehearse cutover rollback

**Files:** None in the primary worktree.

- [ ] **Step 1: Create a disposable checkout at the final cutover commit**

Use a temporary Git worktree outside the active shared worktree. Do not reset or rewrite the primary branch.

- [ ] **Step 2: Revert only the Task 13–15 cutover commits in the disposable checkout**

Do not revert Chunk 1–3 additive APIs/schema. Do not run a database rollback.

- [ ] **Step 3: Run route and backend checks**

Run `php artisan route:list` and the redirect/auth test groups in the disposable checkout. Confirm the rollback restores the previous UI routing without requiring a schema rollback.

- [ ] **Step 4: Remove the disposable checkout**

Record the rehearsal result in the implementation handoff; do not merge or commit the temporary revert.

---

## Rollout Order

1. Deploy Chunk 1 APIs with Blade still available.
2. Deploy Chunk 2 learner frontend and smoke auth/learner flows.
3. Deploy Chunk 3 admin APIs and frontend and smoke internal admin accounts.
4. Deploy Chunk 4 redirects/controller cleanup/Blade deletion.
5. Monitor `401`, `403`, `419`, `422`, `429`, and `5xx` rates plus redirect loops.

Rollback Chunk 4 by reverting its cutover commits. No database rollback is required for the UI cutover; additive API/schema changes from earlier chunks may remain deployed.
