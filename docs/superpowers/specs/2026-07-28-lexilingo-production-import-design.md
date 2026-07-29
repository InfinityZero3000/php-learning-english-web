# LexiLingo Production Import and Interface Completion

## Goal

Complete the approved supervised-learning product with production-safe
LexiLingo content import and working learner/admin interfaces for courses,
lessons, vocabulary flashcards, TraceCAG tutoring, STT and TTS.

## Import Boundary

Laravel remains the system of record. LexiLingo is queried only by Laravel
server-to-server using backend environment credentials. Browser applications
never receive integration secrets.

Import runs as an explicit deployment or administration job, not inside a
database migration. Schema migrations must remain deterministic and must not
depend on network availability. The production deployment sequence is:

1. run database migrations;
2. deploy application code with importer and import UI disabled;
3. probe credentials, partner health and contract compatibility;
4. run a dry-run that cannot write catalog rows or checkpoints;
5. run a bounded import and verify counts, checkpoints and failures;
6. enable the importer/UI feature flag.

Rollback disables the importer/UI flag and application jobs first. Imported
catalog rows remain locally available; rollback never deletes learning state.

## Import Workflow

Extend the existing `lexilingo:import` command rather than creating a second
import framework. It imports in dependency order:

1. categories;
2. courses;
3. units resolved to an imported course;
4. lessons and lesson content resolved to imported units/courses;
5. vocabulary resolved to its imported lesson where supplied.

The command supports bounded production execution through:

- `--page-size` for upstream page size;
- `--max-items` for the maximum top-level records processed across the whole
  invocation;
- `--delay-ms` for the minimum pause between upstream requests;
- `--max-retries` for transient retry attempts;
- `--resume` to continue the latest compatible incomplete run;
- existing dry-run and reset behavior, with reset treated as privileged.

Each run stores a UUID, state (`pending`, `running`, `partial`, `completed`,
`failed`), requested resources, source/schema version and a fingerprint of
page size, ordering and filters. Each resource checkpoint stores the upstream
cursor/page and stable `external_id` ordering position. Resume selects only
the latest incomplete run with the same fingerprint and source/schema version;
otherwise it returns a configuration conflict. Dry-run never creates or
advances a checkpoint.

The item bound is checked between top-level records. The current record and
its nested content commit atomically, so the command never stops halfway
through a course. Reaching the bound produces resumable `partial` success and
exit code `0`; exhausted retries or contract errors produce `failed` and exit
code `1`; validation failures produce a completed/partial run with warnings.

Imported rows use LexiLingo `external_id` as the stable upsert key. Re-running
an import must update the same local records without duplicates. A checkpoint
is advanced only after the corresponding local transaction commits. A
complete snapshot marks missing upstream catalog records archived only when
all requested pages completed without transport, contract or validation
failures. “Archived validation failure” means a redacted failure record, never
archiving the corresponding local catalog row.

Upstream-owned fields are category name/slug, course title/description/
language/media/duration/XP, unit title/description/order/presentation,
lesson title/order/type/XP/content/duration/pass threshold, and vocabulary
word/definition/translation/pronunciation/part of speech/difficulty/tags/audio.
Local publication state, teacher content annotations, enrollment, progress,
assignments, learning events, FSRS state and reviews are never overwritten.

## Rate Limits and Failure Handling

All partner calls share one throttled request path. A distributed cache lock
allows only one active import run across CLI and queue workers; a second start
returns the existing run and HTTP `409` for a different request. The client
enforces connect and total request timeouts plus the configured minimum delay.
HTTP `429` supports both seconds and HTTP-date `Retry-After`; the larger of
the server delay and local exponential backoff applies, capped by a configured
maximum and jittered without exceeding that cap. `429` and transient `5xx`
responses are retried; other `4xx` responses fail immediately.

The limiter clock, sleeper and jitter source are injectable for deterministic
tests. Validation failures create redacted failure records and are skipped.
Exhausted transport retries preserve the last committed checkpoint and end
the command with failure so operators can resume.

Logs and failure records must redact credentials and avoid storing raw
sensitive response headers.

## Administration

Add an admin import interface backed by versioned `/api/v1/admin` endpoints.
It shows local entity counts, checkpoints, the most recent failures and the
latest run result.

The OpenAPI contract adds:

- `GET /api/v1/admin/imports` paginated run history;
- `POST /api/v1/admin/imports` start a bounded run;
- `GET /api/v1/admin/imports/{run}` status and aggregate counts;
- `POST /api/v1/admin/imports/{run}/resume`;
- `GET /api/v1/admin/imports/{run}/failures` paginated redacted failures;
- `POST /api/v1/admin/imports/{run}/failures/{failure}/retry`;
- `POST /api/v1/admin/imports/{run}/reset`.

Mutations require CSRF and `X-Request-ID`. Identical request ID/payload replay
returns the original result; a changed payload returns `409`. Start/resume
returns `202`, status/list returns `200`, validation returns `422`,
unauthenticated `401`, unauthorized `403`, incompatible resume or active-run
conflict `409`, and unavailable integration `503`. Responses never contain
credentials, raw headers or unbounded upstream bodies.

An admin may start or resume a normal import. Resetting a checkpoint or
retrying archived failures requires `super_admin`, recent password
confirmation and an idempotency request ID. Only the actor who started a run
or a super admin may mutate it. The UI must expose loading,
running, success, partial-failure and unavailable states without polling more
often than every five seconds; after one minute it backs off to fifteen
seconds and stops on a terminal state or hidden browser tab.

Reset requires a typed CLI confirmation unless `--force` is supplied in a
non-interactive deployment; API reset requires recent password confirmation.
Retry targets one unresolved failure belonging to the run. Failures are
deduplicated by run/resource/external ID/error fingerprint and transition to
resolved only after a successful retry. Starts, resumes, resets and retries
write payload-bound audit events.

Long imports execute through the configured queue. If production currently
uses the synchronous queue, the command remains the supported deployment
path and the UI limits itself to bounded batches until a worker is configured.

## Learner Interfaces

The supported learner navigation must provide:

- course catalog, course detail and lesson progression;
- assignment-driven learning sessions;
- lesson activity UI with TraceCAG feedback and progressive hints;
- microphone capture for STT and pronunciation;
- TTS playback for supported text and vocabulary;
- FSRS due-card review and learner-selected ratings;
- local vocabulary browsing and saved flashcard state;
- supervised progress and session summary.

Legacy pages may remain only when wired to current `/api/v1` contracts.
Otherwise they are removed from supported navigation and redirected to the
closest supported workflow. API errors are shown explicitly; pages must not
convert transport failures into fake zero-valued data.

## Content Administration

Admin and super-admin interfaces provide CRUD for the locally owned catalog:
courses, units, lessons, lesson content, vocabulary and quiz/activity data.
Imported records remain editable locally. A later import updates only fields
owned by the upstream contract and preserves local learning state.

Super-admin operations continue to own AI health, TraceCAG contract status,
quotas, alert rules and audit events. Credentials are replace-only and are
never returned by an API.

## Production-like Data

LexiLingo import is the primary source of production catalog data. Local
fallback seed data is retained only for automated tests and development; it
must not create fake users, reviews, assignments or learning evidence in
production.

## Verification

The implementation must include:

- importer tests for pagination, idempotent upsert, checkpoints, throttling,
  `Retry-After`, retry exhaustion and resume;
- authorization and idempotency tests for admin import actions;
- API tests for newly added catalog CRUD;
- frontend lint and production builds for both applications;
- route-to-client contract checks;
- a real server-to-server dry-run against LexiLingo using configured
  production credentials, followed by a bounded import only after the dry-run
  succeeds;
- browser smoke tests for learner voice/tutor/review flows and admin catalog,
  import and operations pages when browser automation is available.
