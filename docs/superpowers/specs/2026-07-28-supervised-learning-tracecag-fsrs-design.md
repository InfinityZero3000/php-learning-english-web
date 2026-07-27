# Supervised Learning with LexiLingo TraceCAG and FSRS

## Goal

Turn `PHP_Learning-English-Web` into the system of record for a complete,
supervised English-learning workflow. LexiLingo remains a separate service
provider for importable content, TraceCAG, STT, pronunciation assessment and
TTS. PHP owns users, sensitive data, authorization, courses copied locally,
learning state, FSRS scheduling, teacher supervision and operational audit.

## Confirmed Current State

- Laravel already has local course, unit, lesson and vocabulary models plus
  `external_id` fields for imported LexiLingo content.
- `user_vocabularies` and append-only `vocabulary_reviews` contain the state
  needed by an FSRS scheduler, but the scheduling endpoints used by the
  Next.js clients are not implemented on the current branch.
- A vocabulary-only LexiLingo synchronizer exists. Complete category, course,
  unit and lesson import work exists on repository feature branches and should
  be reused rather than rebuilt.
- Translate, pronunciation, STT and TTS proxy work also exists on
  `feature/10-ai-proxy` and should be merged or selectively reused.
- Learner and admin Next.js applications contain many screens and API calls,
  but several calls target endpoints that do not exist and some admin screens
  use hard-coded data.
- LexiLingo production backend and AI health endpoints returned HTTP 200 on
  2026-07-28. A protected TraceCAG call still requires a valid external
  service contract and credential probe before production enablement.

## System Boundary

### PHP Learning English

Laravel is the only identity and business backend. It owns:

- users, roles, sessions and all sensitive data;
- enrollment, teacher assignment and authorization;
- the local content snapshot used by learners;
- lesson progress, learning sessions and evidence;
- FSRS state, review history and scheduling decisions;
- supervision alerts, assignments and intervention notes;
- integration status, quotas and audit events.

The learner and administration Next.js applications use Laravel's same-origin
session APIs. Browser code never receives a LexiLingo credential.

### LexiLingo

LexiLingo provides bounded server-to-server services:

- read-only content APIs for categories, courses, units, lessons and
  vocabulary;
- TraceCAG diagnosis and tutoring;
- speech-to-text and pronunciation assessment;
- text-to-speech.

LexiLingo does not become an identity provider for PHP users. It receives no
email, password, PHP role or unrestricted learning history. Any LexiLingo
change must be backward compatible with its current clients.

## Identity and Privacy Contract

Laravel derives a stable opaque external subject from the local user ID using
HMAC and a server-only secret. Requests may include only the current learning
goal, CEFR level, aggregate concept mastery, recent error classes and the
minimum current exercise context.

TraceCAG must expose a versioned service-to-service endpoint that accepts a
service credential, the opaque subject and the bounded learner snapshot. It
must not require Laravel to create a LexiLingo user or mint a user JWT.
Responses use a versioned JSON schema containing diagnosis, feedback, hints,
recommended next action and safe learner-facing text. Internal prompts,
chain-of-thought and raw trace data are never returned.

Audio uploads are validated for type and size, held only for processing and
removed after the request. Logs redact credentials, learner text where
appropriate and all raw audio.

## Role Model

Keep the existing `roles` table and add `teacher` and `super_admin`.
Authorization is capability-based through Gates and Policies rather than
assuming every higher role can perform every action.

### Super Admin

May manage AI and TraceCAG operations, service health, monitoring, quota
policy, alert rules, audit events, integration probes, sync retry, admin role
assignment and teacher assignment. Sensitive mutations require recent
password confirmation. Secrets can be replaced but never read through an API.

Only a super admin may grant or remove `super_admin`. A user cannot demote
their own super-admin account, and the final super admin cannot be demoted or
deleted. The first super admin is promoted using an interactive Artisan
command run directly on the server; no default account or password is seeded.

The capability transitions are explicit:

| Action | Super admin | Admin | Teacher |
|---|---:|---:|---:|
| Grant/remove `super_admin` | Yes | No | No |
| Grant/remove `admin` | Yes | No | No |
| Grant/remove `teacher` | Yes | No | No |
| Assign teacher to learner | Yes | No | No |
| Manage ordinary learners | Yes | Yes | Assigned only |
| Start content sync | Yes | Yes | No |
| Retry/override a failed sync | Yes | No | No |
| Read learner evidence | Assigned or explicit support audit only | No | Assigned only |
| Manage AI, quota, monitoring and secrets | Yes | No | No |

Recent password confirmation is required for secret replacement, quota or
alert-policy mutation, retry/override of failed integration work, granting or
removing admin/super-admin, deleting an account and changing teacher scope.
Confirmation lasts 15 minutes. Existing admin pages that expose FSRS,
user-progress or learner reports move to teacher-scoped APIs or are removed;
admin does not inherit access to learning evidence.

### Admin

May manage ordinary users and the business content catalog: courses, units,
lessons, vocabulary, quizzes, publication and archival. Admin may see
non-sensitive sync status but cannot manage AI credentials, monitoring
policy, super admins or other high-risk operations.

### Teacher

May access only assigned learners. Teacher capabilities cover
learning evidence, alerts, assignments and intervention notes.

### Learner

May access only their own enrollment, sessions, FSRS reviews, tutor/voice
features and progress.

## Data Model

Reuse:

- `course_categories`, `courses`, `units`, `lessons`, `vocabularies`;
- `user_vocabularies`, `vocabulary_reviews`;
- existing quiz, attempt and progress records where their semantics match.

Add only the missing durable concepts:

- `enrollments`: learner, course, status, enrolled/completed timestamps;
- extend the existing `progress` table for one unique `(user_id, lesson_id)`
  record with status, best score and started/completed timestamps instead of
  creating a parallel `lesson_progress` table;
- `teacher_assignments`: one unique `(teacher_id, learner_id)` scope; classroom
  support is deferred until a real `classes` and membership model is approved;
- `learning_sessions`: learner, course/lesson, state, start/end and summary;
- `learning_events`: append-only normalized answer, hint, voice and timing
  evidence with a request id for idempotency;
- `learning_assistance_results`: append-only TraceCAG or local-fallback
  results linked to a learning event and request ID;
- `supervision_alerts`: deterministic rule, severity, evidence, assignee,
  state and resolution;
- `assignments`: teacher, learner scope, target lesson/vocabulary, due date and
  completion state;
- `intervention_notes`: teacher note and audit metadata;
- integration run/audit records where the existing importer work does not
  already supply them.

Avoid a second generic event platform. JSON is limited to bounded evidence or
service metadata whose shape is versioned at the boundary.

Status and event type columns use database-backed enumerated strings validated
in PHP. Foreign keys cascade only for learner-owned ephemeral/session data;
audit, reviews and resolved alerts restrict destructive deletion and are
anonymized by the account-deletion workflow. `assignments` has exactly one
target enforced by a check constraint: `lesson_id` XOR `vocabulary_id`.
Teacher scope and assignments are unique where an active duplicate would be
ambiguous.

Quiz answer submission writes the existing `attempt`/answer state and its
corresponding `learning_event` in one local transaction. A due flashcard
submission writes its learning event, locked FSRS state and review snapshot in
one transaction. The event references the attempt or review when one exists;
standalone voice exercises have neither. It does not duplicate quiz scoring.

## Learning Loop

### Planning

Laravel creates each session plan in this order:

1. explicit teacher lesson assignment due now;
2. due FSRS reviews;
3. the next eligible activity in the enrolled course;
4. remediation for a recent repeated weakness.

Course state determines what the learner is eligible to study. FSRS is the
only authority for when vocabulary is reviewed.

A teacher vocabulary assignment creates unscheduled practice. It may collect
evidence and satisfy the assignment, but it cannot mutate FSRS before the card
is due. The UI labels it practice rather than review.

### Observation

Every attempt records the exercise, answer, correctness, response time and
hint count. Voice activities additionally record the transcript and bounded
pronunciation metrics, not persistent raw audio.

### Diagnosis and Feedback

Laravel sends the minimum exercise and learner snapshot to TraceCAG.
TraceCAG returns structured diagnosis, progressive hints, explanation and a
recommended action. TraceCAG never writes PHP learning state.

### Scheduling

Laravel maps a completed vocabulary review to Again, Hard, Good or Easy,
applies FSRS-6 semantics pinned against `py-fsrs` v6.3.0 reference fixtures,
updates `user_vocabularies` in a transaction and appends the scheduling
snapshot to `vocabulary_reviews`. The PHP implementation follows the published
21-parameter FSRS-6 formula; it does not invoke Python in production.

Initial configuration is versioned in code: parameters
`[0.212, 1.2931, 2.3065, 8.2956, 6.4133, 0.8334, 3.0194, 0.001, 1.8722, 0.1666, 0.796, 1.4835, 0.0614, 0.2629, 1.6483, 0.6014, 1.8729, 0.5425, 0.0912, 0.0658, 0.1542]`,
desired retention `0.90`, learning steps `1 minute` then `10 minutes`,
relearning step `10 minutes`, maximum interval `36,500` days and fuzzing
disabled so schedules are deterministic and auditable. These values match
`py-fsrs` v6.3.0 defaults except for explicitly disabled fuzzing. Optimized
per-user parameters are out of scope until enough review history exists and a
separate offline validation is approved.

All scheduling timestamps are UTC. The learner timezone is used only to group
the due queue by local day. The server clock is authoritative. New cards begin
in `learning`, with no stability/difficulty until the first rating. Rating is
an explicit learner choice after revealing the answer; objective exercises
may recommend a rating but cannot silently choose it.

`vocabulary_reviews` adds a globally unique `request_id`, algorithm/version,
rating, `reviewed_at`, and before/after snapshots of state, due time,
stability, difficulty, scheduled days and last review. `user_vocabularies`
adds `last_reviewed_at`, algorithm version and unsigned `revision` defaulting
to zero. The mutation locks the user-vocabulary row, requires the submitted
base revision to equal the stored revision, increments it by one and commits
all rows atomically. Repeating the same request ID and identical normalized
payload returns the original result. Reusing the ID with a different payload
or submitting against a stale revision returns `409`.

The expand/backfill migration converts never-reviewed rows from `state = new`
and numeric zero stability/difficulty into FSRS-6 Learning state with nullable
stability/difficulty, no last review, due now and revision zero. Rows with
review history are replayed from their append-only reviews into FSRS-6 state
before the new scheduler is enabled.

### Supervision

Deterministic rules produce alerts for repeated concept errors, falling
retention, excessive overdue work, inactivity, weak pronunciation or
excessive hint use. TraceCAG may summarize evidence and recommend an
intervention, but it does not decide whether an alert exists.

Initial versioned rules are:

| Rule | Threshold and window |
|---|---|
| `repeated_concept_error` | At least 3 incorrect results for one concept among its last 5 graded events, all within 7 days |
| `retention_drop` | At least 20 reviews in the last 14 days, estimated retention below 70% and at least 10 percentage points below the preceding 14 days |
| `overdue_work` | At least 20 due cards, or any assigned lesson more than 7 days overdue |
| `inactivity` | Enrolled learner has no completed learning event for 7 full local days |
| `weak_pronunciation` | Mean pronunciation score below 60/100 across at least 3 assessed attempts in 7 days |
| `excessive_hint_use` | Hints used on at least 50% of at least 10 graded exercises in 7 days |

Event-dependent rules run after the local learning transaction commits.
Overdue, inactivity and retention rules also run nightly in the learner's
timezone. Each alert has a unique active fingerprint of
`(learner_id, rule_version, rule_code, subject_key)`, where `subject_key` is
the concept, assignment or `global`. Reevaluation updates the alert's
append-only evidence snapshots and severity rather than creating duplicates.

A teacher may resolve an alert with a required resolution code and optional
note. The evaluator auto-resolves only after the condition is false on two
consecutive nightly evaluations; event-only rules remain open until teacher
resolution or their rolling window expires on two nightly evaluations. If the
same fingerprint breaches again within 30 days, the existing alert reopens
and appends a lifecycle event; after 30 days a new alert is created. Every
open, severity change, resolve and reopen action is audited.

Teachers work from an exception-focused queue. They can inspect filtered
evidence, assign content, set a target, add a note and resolve the alert.

## PHP APIs

Application APIs use `/api/v1`, stable response envelopes and explicit
authorization. Login, registration, password recovery, email verification,
health and documented public catalog reads are exempt from session
authentication. Authenticated browser mutations use Laravel sessions and CSRF.
Every endpoint defines ownership, pagination, idempotency and error behavior
in `docs/openapi/laravel-v1.yaml` before frontend implementation.

### Learner

- catalog, course detail and enrollment;
- current plan and lesson progress;
- start, advance and complete learning sessions;
- submit attempts and hints;
- STT, pronunciation, TTS and TraceCAG tutor actions;
- FSRS due queue, review mutation and statistics;
- assignments and personal progress.

### Teacher

Under `/api/v1/teacher/*`:

- assigned learners;
- supervision alert queue and filtered evidence;
- learner progress summaries;
- create/update assignments;
- intervention notes and alert resolution.

### Admin

Under `/api/v1/admin/*`:

- users and business-role changes allowed by policy;
- course, unit, lesson, vocabulary and quiz management;
- publish/archive actions;
- content sync start/status and non-sensitive failures.

### Super Admin Operations

Under `/api/v1/admin/operations/*`:

- backend, AI, TraceCAG, STT and TTS probes;
- sync run retry and contract status;
- aggregate usage and monitoring;
- quota policy and deterministic alert rules;
- audit events;
- admin/super-admin and teacher assignment operations.

These endpoints use explicit allowlists. They never accept an arbitrary
upstream URL/path and never return secret values.

## LexiLingo Integration

Use the partner read-only content namespace documented by LexiLingo and a
server-held partner key. Import in dependency order:

```text
categories -> courses -> units -> lessons -> vocabulary
```

Each page is schema-validated and imported transactionally. The importer is
idempotent, resumes from checkpoints and archives missing upstream records
only after a complete successful snapshot. It never hard-deletes synchronized
content.

Reuse the existing PHP AI proxy branch for translate, STT, pronunciation and
TTS after aligning it with the actual production contract. Add one narrow,
backward-compatible endpoint:

```text
POST /api/v1/integrations/trace-cag/v1/analyze
X-LexiLingo-Service-Token: <rotatable service token>
X-Request-ID: <UUID>
```

The token is compared in constant time against current and time-bounded
previous token hashes; the endpoint is separate from AI admin credentials and
user JWT routes. Request limits are 8 KiB JSON, input text 2,000 UTF-8
characters, at most 50 concept summaries and at most 20 recent error summaries.
The request contains `subject`, `session_id`, `input_type`, bounded
`learner_snapshot`, `exercise_context` and `text`. The response contains
`schema_version`, `request_id`, `diagnosis` codes, progressive `hints`,
`feedback`, `recommended_action`, learner-safe `message`, `degraded` and
bounded model metadata. JSON Schemas are checked into both repositories and
consumer/provider contract tests compare them.

Timeout is 15 seconds for TraceCAG, 30 seconds for STT/pronunciation and 30
seconds for TTS. LexiLingo returns `400/422` for invalid input, `401/403` for
service auth, `409` for request-ID conflict, `429` for quota, `503` when the
pipeline is unavailable and `504` for its deadline. PHP maps these to its
stable error/degraded envelope.

LexiLingo may use the opaque subject and snapshot only in memory for the
request. It must not build a durable learner profile or retain the subject,
snapshot, learner text, transcript or audio. Request-ID deduplication retains
only a keyed hash and response for at most 10 minutes; operational logs retain
redacted metadata. Audio temporary files are deleted immediately after
processing and by a one-hour orphan cleanup safety job.

## User Interfaces

### Learner

- Today: time estimate, due FSRS work, next lesson, pronunciation task and
  teacher assignments;
- Course path: enrollment, units, prerequisites and progress;
- Study session: focused exercise, listen/speak controls, tiered hints,
  TraceCAG feedback and FSRS rating;
- AI Tutor: text/voice assistance bounded to current learning context;
- Session summary: strengths, repeated errors, next review and assignments;
- Personal progress.

### Teacher

- overview of learners needing attention;
- alert queue ordered by severity and age;
- learner detail with filtered evidence and trend summaries;
- assignment and intervention workflow;
- learner reports.

### Admin

- real catalog/user CRUD backed by Laravel APIs;
- content import runs and publication state;
- non-sensitive service and sync status.

### Super Admin

- AI/service health and contract probes;
- monitoring and aggregate usage;
- quotas and alert-rule configuration;
- audit explorer and high-risk role management.

Every screen implements loading, empty, validation, forbidden and transport
failure states. Controls for unavailable endpoints remain hidden or disabled.

## Failure Handling

- Attempt/answer/event writes commit in one short local transaction before any
  optional TraceCAG call. TraceCAG, STT and TTS calls never run inside a
  database transaction.
- TraceCAG failure falls back to deterministic local feedback; the learner's
  attempt remains committed. The API returns successful learning data with
  `assistance.status = degraded` and a stable reason code.
- STT/pronunciation/TTS failure falls back to text or existing imported audio.
- Content sync failure continues serving local content. Validated pages
  committed before a later page fails remain visible; archival is withheld
  until the entire snapshot succeeds.
- Retry applies only to idempotent imports, service probes and observation
  delivery. Observation delivery means an optional, redacted TraceCAG
  diagnostic request associated with an already committed event; it cannot
  change the event or FSRS. FSRS review mutations are never blindly replayed.
- Retried service requests with the same request ID return the retained
  original result; after the deduplication TTL PHP may retry only if the local
  event has no linked `learning_assistance_result`, and stores a new request
  ID. Results are inserted separately and never mutate the original event; a
  unique local-event/request constraint prevents duplicate attachment.
- Upstream `401`, `403`, `429`, timeout and `5xx` errors are mapped to stable
  PHP responses without exposing upstream bodies or credentials.

## Verification

- role/policy matrix, escalation prevention, final-super-admin protection and
  recent-password enforcement;
- ownership and teacher-assignment isolation;
- HMAC subject stability and privacy payload allowlist;
- deterministic FSRS fixtures, duplicate request protection and concurrent
  review locking;
- importer pagination, schema drift, idempotency, checkpoint resume and
  archival;
- proxy success, authorization, quota, timeout, upstream failures, MIME and
  size validation;
- deterministic supervision-rule tests;
- TraceCAG contract tests in both repositories;
- Laravel feature tests and LexiLingo focused test suites;
- frontend lint/build plus browser smoke for learner, teacher, admin and
  super-admin critical paths.

## Rollout

1. Update `docs/openapi/laravel-v1.yaml` and the cross-repository TraceCAG JSON
   Schemas before application code.
2. Merge/reuse the existing importer checkpoint/failure tables and AI proxy
   work; do not create parallel integration-run infrastructure. Reconcile
   per-record validation with complete-snapshot archival: invalid records make
   the run incomplete, so no archival occurs. Validated pages committed before
   a later failure are visible immediately.
3. Apply expand-only migrations, backfill progress/FSRS revisions, deploy code
   that reads old and new shapes, then enable writes. Contract cleanup occurs
   in a later release.
4. Add roles, Policies,
   enrollment/progress and the real FSRS vertical slice.
5. Deploy LexiLingo's backward-compatible external TraceCAG endpoint disabled,
   configure/verify its credential, deploy the PHP consumer disabled, then
   enable LexiLingo before PHP. Roll back by disabling PHP first; old
   LexiLingo clients are unaffected.
6. Ship the learner path from catalog through session completion.
7. Ship teacher supervision and intervention.
8. Replace admin stubs and add super-admin operations.
9. Keep importer, AI assistance, voice, supervision and operations UI behind
   separate default-off feature flags. Enable production only after
   authenticated probes, a
   synthetic learning session and rollback smoke tests pass.

## Explicit Non-Goals

- no PHP user replication into LexiLingo;
- no second JWT/login flow in the PHP applications;
- no browser-held LexiLingo secret;
- no arbitrary upstream proxy;
- no LLM-controlled FSRS mutation or alert creation;
- no persistent raw voice recording;
- no hard deletion of synchronized content.
