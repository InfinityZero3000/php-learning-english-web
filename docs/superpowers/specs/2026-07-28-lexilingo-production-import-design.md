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
2. deploy application code;
3. run or resume the bounded LexiLingo import command;
4. verify local catalog counts and failed-item records.

## Import Workflow

Extend the existing `lexilingo:import` command rather than creating a second
import framework. It imports in dependency order:

1. categories;
2. courses with nested units, lessons and lesson content;
3. vocabulary.

The command supports bounded production execution through:

- `--page-size` for upstream page size;
- `--max-items` for the maximum records processed in one invocation;
- `--delay-ms` for the minimum pause between upstream requests;
- `--max-retries` for transient retry attempts;
- `--resume` to continue from durable checkpoints;
- existing dry-run and reset behavior, with reset treated as privileged.

Imported rows use LexiLingo `external_id` as the stable upsert key. Re-running
an import must update the same local records without duplicates. A checkpoint
is advanced only after the corresponding local transaction commits.

## Rate Limits and Failure Handling

All partner calls share one throttled request path. The client enforces the
configured minimum delay between requests. HTTP `429` respects `Retry-After`;
`429` and transient `5xx` responses use bounded exponential backoff with
jitter. Validation failures are archived and skipped. Exhausted transport
retries are recorded with safe response metadata, preserve the last committed
checkpoint and end the command with failure so operators can resume.

Logs and failure records must redact credentials and avoid storing raw
sensitive response headers.

## Administration

Add an admin import interface backed by versioned `/api/v1/admin` endpoints.
It shows local entity counts, checkpoints, the most recent failures and the
latest run result.

An admin may start or resume a normal import. Resetting a checkpoint or
retrying archived failures requires `super_admin`, recent password
confirmation and an idempotency request ID. The UI must expose loading,
running, success, partial-failure and unavailable states without polling more
often than necessary.

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

