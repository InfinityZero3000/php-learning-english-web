# Self-owned Learning Path and Safe Import Design

Date: 2026-07-29

## Goal

Close the learner-facing course, progress, FSRS, and import gaps without making
the application depend on LexiLingo at learning-request time. Laravel remains
the source of truth; LexiLingo supplies candidate catalog data that an
authorized administrator reviews before it can replace local content.

## Scope

This design delivers four vertical slices:

1. A Course → Unit → Lesson Learning Path backed by independently manageable
   local units.
2. A richer Progress surface with FSRS analytics and predicted review intervals.
3. A staged import workflow with validation, duplicate detection, diff review,
   batch approval, per-record exclusion, and transactional apply.
4. Google recent re-authentication, API contract parity, browser smoke evidence,
   and rollback verification.

Voice streaming, object storage, classroom membership, streak/reward systems,
and a moderator role are separate future projects. They do not block this scope.

## Principles

- Local catalog data wins over upstream data.
- Learner requests read published local data only.
- Import never silently overwrites a local edit.
- Invalid import batches stop before catalog mutation.
- UI controls never rely on fake or placeholder server state.
- Each vertical slice includes schema, API, UI, automated checks, and a release
  checkpoint.

## Architecture

```text
LexiLingo/file
      |
      v
staged import run/items
  validate -> fingerprint -> duplicate classification -> diff
      |
      v
admin draft approval (batch selection + per-record action)
      |
      v
transactional apply + audit
      |
      v
published local Course -> Unit -> Lesson -> Session
```

The existing importer fetches candidates, but it no longer applies approved
replacements directly to catalog rows. Staging and catalog mutation are separate
operations. Learning APIs never call LexiLingo synchronously.

## Catalog Ownership

Course, Unit, Lesson, and Vocabulary records retain `external_id` where they
originated upstream. Locally authored records may have no external ID.

Imported records need minimal provenance:

- source system and source-scoped external ID;
- last accepted upstream fingerprint;
- last accepted upstream snapshot needed to compute a review diff;
- local override state and timestamp;
- catalog revision incremented by every local/import mutation;
- last synchronized timestamp.

External identity is the composite `(source_system, external_id)`, not a global
external ID. Existing globally unique constraints migrate forward to a unique
composite constraint. Locally authored records use `source_system = local` and
a null external ID.

An admin edit marks the affected record as locally overridden. A later import
classifies the candidate but defaults its action to `keep_local`. The system
must not clear local override implicitly. An explicit approved replace updates
the local record, accepted fingerprint/snapshot, and audit entry. At apply time
the service locks each target row and compares the staged base catalog revision,
accepted fingerprint, and override state. Any change since review turns that
item back into `local_conflict`; a stale approval never overwrites it.

## Unit Model and Administration

Units become first-class local content:

- Admin and Super Admin can list, create, read, update, reorder, archive, and
  delete eligible units through canonical `/api/v1/admin/catalog/units` APIs.
- A unit belongs to one course. Lessons may be assigned or moved to a unit.
- Unit order is unique within a course. Reordering is one bounded transaction.
- Unit lifecycle is `draft → published → archived`; archive removes it from new
  learner plans but does not invalidate an already active session.
- Only an empty draft unit with no lesson or learner dependency may be deleted.
  Units with lessons must be archived or have lessons explicitly moved first.
- Publishing a unit requires a published course and at least one eligible
  lesson. Archiving a unit prevents new sessions for its lessons.
- Imported and locally authored units use the same model and UI; provenance is
  metadata, not a parallel content tree.

The admin course editor exposes a Unit outline rather than adding a second
unrelated catalog application.

## Learner Course Experience

### Course catalog

`/courses` continues to list published courses and enrollment state.

### Course Learning Path

`/courses/[id]` becomes the canonical Course Learning Path:

- editorial hero with course identity, completion percentage, and one “Continue
  learning” action;
- ordered Unit sections with lesson completion counts;
- lesson status: completed, available/current, or locked;
- prerequisite explanation for locked lessons;
- lesson duration and activity summary where available;
- resilient loading, empty, unauthorized, unavailable, and retry states.

Lesson prerequisites use a `lesson_prerequisites` pivot with
`(lesson_id, prerequisite_lesson_id)` uniqueness. Both lessons must belong to
the same course, self-reference is forbidden, and writes reject cycles. A lesson
is eligible after every prerequisite has completed progress. Imported
prerequisite external IDs resolve during staging; unresolved or cross-course
references are validation failures.

The API returns the ordered published course tree, learner-specific progress,
prerequisite status, and a `next_action`: either `resume_session` with the active
session ID or `start_lesson` with the next eligible lesson ID. The client must
not rebuild eligibility rules independently.

### Lesson and session

The Course page starts or resumes the existing session workflow. Session create
accepts an optional lesson ID, validates enrollment and server-side eligibility,
and returns the existing active session for an identical learner/enrollment/
lesson request. `/session/[id]`
remains focused on one activity at a time; analytics stay out of the session.
The session summary links back to the course path, Progress, or due review.

## Progress and FSRS

`/progress` is the learner analytics destination. It combines:

- course/unit/lesson completion;
- recent session activity and quiz performance;
- due count and seven-day due forecast;
- retention estimate, average stability, average difficulty, and FSRS state
  distribution;
- a direct “Review now” action.

The existing `/review` route remains the focused review mode. After reveal, its
four rating buttons show server-calculated predicted intervals for Again, Hard,
Good, and Easy. The browser does not implement the FSRS formula. The review
submission still uses the current card revision/idempotency contract.

Retention is the mean value returned by the existing FSRS-6 scheduler
retrievability method for reviewed cards with positive stability at one frozen
server timestamp; analytics must not implement a second decay formula. New cards
are excluded and an empty population returns null. Stability/difficulty averages
use the same population. The seven forecast buckets are UTC calendar dates from
the server date through the next six dates, counted from `due_at`; the client
only localizes labels. A non-mutating preview operation runs the existing
scheduler against a copy of the current card for all four ratings and returns
intervals plus `generated_at` and the base revision. Tests freeze the clock.

The visual direction is Editorial Learning: strong typography, layered cards,
restrained gradients, contextual hero content, CSS/SVG charts, responsive dense
information, and the existing accessibility baseline. No new chart or UI
dependency is required unless native CSS/SVG proves insufficient.

## Staged Import Workflow

### States

An import run moves through:

`fetching → validating → review_ready → approved → applying → completed`

Terminal states include `validation_failed`, `apply_failed`, and `cancelled`.
No run may enter `applying` until validation has completed successfully.

### Duplicate classification

Every staged item is classified as exactly one of:

- `new`: no matching local record;
- `exact_duplicate`: accepted fingerprint and normalized payload match;
- `upstream_update`: upstream changed and local has no override;
- `local_conflict`: upstream changed after a local override;
- `invalid`: schema, relationship, or identity validation failed.

Matching uses stable external ID first. A normalized natural-key collision is
reported for review; it is never automatically merged. Fingerprints cover
normalized business fields, not timestamps or volatile transport metadata.
Staged items retain parent identity and dependency edges. Apply order is Course
→ Unit → Lesson → dependent content. Excluding a parent automatically excludes
all staged descendants unless they resolve to an already accepted local parent;
the UI previews this cascade. An item cannot apply with an excluded or unresolved
required dependency.

### Review and approval

The admin import page shows run history, current state, aggregate counts, staged
items, field-level diffs, and safe error details. The reviewer approves a batch
while retaining the ability to exclude individual items or change their action.

Defaults:

- `new` → add;
- `exact_duplicate` → skip;
- `upstream_update` → replace;
- `local_conflict` → keep local;
- `invalid` → blocks approval while selected; it may be excluded only when that
  exclusion does not leave a selected dependent item orphaned.

Apply uses one transaction for the approved bounded batch. Target catalog rows
are locked and revalidated against staged base revisions immediately before
mutation. A failure or stale approval rolls back that batch and retains the
review draft. The audit records actor, run, selected
items, excluded items, action, and before/after fingerprints without secrets or
full sensitive payloads.

## Google Recent Re-authentication

Admin access is Google-only, so local password confirmation is removed from the
application contract for privileged operations. Freshness is stored in the
current Laravel session and bound to the authenticated user ID plus Google
subject; it is not a reusable timestamp on the user row. Session rotation or a
subject/user mismatch clears freshness.

The following require a recent Google authentication:

- replacing local data during import;
- resetting or retrying a privileged import;
- changing fixed roles or teacher scope;
- changing quota or alert rules.

If the authentication age exceeds the configured window, the API returns a
stable re-auth-required response. The frontend redirects through the existing
Google admin entry and returns to the exact safe local path/draft. Return paths
use the existing safe-path validation. The browser stores no password, provider
token, or integration secret.

Authorization is explicit:

- Admin and Super Admin may start, inspect, cancel pre-apply runs, review diffs,
  exclude items, and approve/apply new or non-conflicting upstream updates.
- Only Super Admin with recent Google re-auth may replace a `local_conflict`,
  reset/retry privileged runs, change roles/teacher scope, or change quota/alert
  rules.
- The apply endpoint re-authorizes final selected actions; approval state alone
  never grants permission.

## API and Contract Rules

- New supported operations use `/api/v1` and the existing response envelope.
- Mutation requests use request IDs and conflict-safe replay semantics.
- OpenAPI must list concrete runtime paths; generic `{resource}` paths and
  nonexistent sync/secret operations are removed unless implemented.
- Route inventory compares HTTP method plus normalized path to prevent false
  parity.
- Validation errors identify safe fields and item IDs; upstream bodies and
  credentials are never returned.

## Failure Handling

- Schema or relationship validation failure stops the run before apply.
- Exact duplicates are skipped without warning noise.
- Natural-key ambiguity becomes a review conflict.
- Provider timeout/retry exhaustion preserves the last completed checkpoint and
  leaves catalog rows unchanged.
- Apply failure rolls back the bounded batch and keeps its approval draft.
- AI/provider degradation never blocks text learning, session persistence, or
  FSRS review.
- Every new UI surface has explicit loading, empty, forbidden, conflict, error,
  and retry states as applicable.

## Delivery Slices

### Slice 1: Self-owned Learning Path

Add provenance/override fields, canonical Unit CRUD/reorder operations, ordered
course-tree response, and the Editorial Learning Course page. Before importer
commands/jobs can run against these rows, make existing direct import writes
override-aware; otherwise keep importer write entry points disabled. Exit when a
learner can enroll and follow Course → Unit → Lesson → Session using local data.

### Slice 2: Progress and FSRS insight

Add aggregate/forecast responses, rebuild Progress presentation, and expose
predicted intervals in Review. Exit when values come from server data and all
review states remain keyboard accessible.

### Slice 3: Safe staged import

Add staged runs/items, classification and diff, approval API/UI, transactional
apply, run history, polling, audit, and session-bound Google recent re-auth for
privileged import actions. The apply entry point remains feature-disabled until
re-auth enforcement and stale-revision checks pass. Exit when a local conflict cannot be
overwritten without explicit authorized approval.

### Slice 4: Release gate

Extend the same recent Google re-auth enforcement to role, teacher-scope, quota,
and alert-rule mutations; reconcile OpenAPI/runtime paths, run all automated
gates, verify role-based browser flows, perform a bounded real-provider
probe, and rehearse backup/restore and rollback. Exit only with recorded
production-safe evidence.

## Verification

- Migration up/rollback/up and relationship/constraint tests.
- Unit CRUD, ordering, lifecycle, dependency, authorization, and request replay
  feature tests.
- Course-tree eligibility, ownership, progress, and next-lesson tests.
- FSRS forecast/predicted-interval fixture and API tests; frontend keyboard and
  error-state tests.
- Import validation, duplicate classes, natural-key ambiguity, local override,
  approval, exclusion, rollback, retry, polling, and audit tests.
- Google re-auth age, safe return path, role boundary, and privileged mutation
  tests.
- Pint, full PHPUnit, both Vitest suites, ESLint, both Next.js production builds,
  OpenAPI lint/parity, and role-based browser smoke.

## Rollout and Rollback

Each slice is forward-compatible and releasable independently. Additive schema
ships before clients use it. New read responses remain backward compatible until
the learner page is deployed. Import staging ships disabled before real-provider
use. Rollback disables new write entry points first, rolls clients back second,
and retains provenance/staging/audit rows until a verified data rollback decision
is made.

Before production approval, capture database backup/restore evidence and the
exact backend/learner/admin deployment references. Never use schema rollback as
a substitute for restoring catalog data changed by an approved import.
