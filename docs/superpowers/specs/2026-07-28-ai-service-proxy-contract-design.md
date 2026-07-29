# AI Service Proxy Contract Design

## Goal

Finish issue #10 without parallel contracts or deferred compatibility debt. Laravel exposes one stable application API, enforces authentication and cost controls, and adapts requests to the existing LexiLingo service contract without exposing service credentials.

## Public API

The only public endpoints are:

- `POST /api/v1/services/translate`
- `POST /api/v1/services/pronunciation`
- `POST /api/v1/services/stt`
- `POST /api/v1/services/tts`

The temporary `/api/v1/ai/*` paths are removed rather than retained as aliases. All endpoints require an authenticated Laravel user, session CSRF protection, a per-minute limiter, and a shared configurable daily per-user quota.

## Upstream Mapping

Laravel maps the public API to the documented LexiLingo service:

| Laravel endpoint | Upstream request |
|---|---|
| `/services/translate` | `GET /api/v1/ai/translate` with validated query parameters |
| `/services/pronunciation` | `POST /api/v1/stt/assess-pronunciation` multipart |
| `/services/stt` | `POST /api/v1/stt/transcribe` multipart |
| `/services/tts` | `POST /api/v1/tts/synthesize` JSON, streamed audio response |

The upstream method/path inventory is pinned to
`docs/api_docs_lexilingo.md` as captured on 25 July 2026. Laravel owns the
following stable adapter contract:

| Endpoint | Public request → upstream fields | Required upstream success payload → public `data` |
|---|---|---|
| translate | JSON `text` (required string), `target_lang` (required two lowercase letters), `source_lang` (optional two lowercase letters) → same query names | JSON object with string `translated_text`; optional string `source_lang` and `target_lang` |
| pronunciation | multipart `audio`, `reference_text` (required string), `language` (optional two lowercase letters) → same multipart names | JSON object with numeric `score` from 0–100; optional string `feedback` |
| stt | multipart `audio`, `language` (optional two lowercase letters) → same multipart names | JSON object with non-empty string `text`; optional numeric `confidence` from 0–1 |
| tts | JSON `text` (required string), `voice` (optional string, maximum 50 characters; no enum exists in the pinned inventory), `language` (optional two lowercase letters) → same JSON names | approved audio response |

Unknown upstream JSON fields are discarded. Missing, malformed, or wrongly
typed required fields are an invalid upstream response rather than a partial
success. Audio forwarding preserves the validated filename and detected MIME
type. Translation follows the documented upstream GET contract even though
query transport is not ideal; Laravel never logs request payloads.

## Limits and Configuration

- Translation text: 5,000 UTF-8 characters.
- Pronunciation reference text: 500 characters.
- Audio: default 10,240 KiB maximum, clamped to 1–51,200 KiB. Accepted upload pairs are `.mp3` with
  `audio/mpeg`, `.wav` with `audio/wav` or `audio/x-wav`, `.m4a` with
  `audio/mp4` or `audio/x-m4a`, and `.ogg` with `audio/ogg`.
- TTS text: 2,000 characters.
- Existing per-minute limits remain endpoint-specific.
- `LEXILINGO_AI_DAILY_LIMIT` configures a shared daily quota per authenticated
  user across all four endpoints. It defaults to 100 and is clamped to
  1–10,000; invalid/non-positive input fails closed at one request.
- Upstream timeout defaults to 30 seconds and is clamped to 1–60 seconds.
- Translation GET total attempts default to two and are clamped to 1–3;
  `LEXILINGO_AI_RETRY_TIMES` means total attempts, not additional retries.
  Only connection failures and upstream `5xx` are retried. Delay defaults to
  200 ms and is clamped to 0–5,000 ms.
- Pronunciation, STT, and TTS POST requests are attempted once. They are not
  automatically retried because the pinned upstream contract provides no
  idempotency-key guarantee and duplicate paid work is worse than a visible
  retryable failure.

The named daily limiter is registered once in `AppServiceProvider`. Its key is
the authenticated user ID; these routes are never available to guests.
Authentication and CSRF middleware run before rate limiting. One request that
reaches the service route consumes one minute-limit unit and one shared daily
unit, including validation or upstream failures; internal retries do not
consume additional Laravel quota. CSRF failures consume no quota. Laravel's
`perDay` limiter uses a 24-hour window beginning with the first counted request;
different users have isolated buckets, while all four endpoints share a user's
daily bucket.

## Responses and Errors

- JSON upstream success responses are wrapped in the existing `{data, meta}` envelope.
- TTS accepts only `audio/mpeg`, `audio/wav`, `audio/x-wav`, `audio/mp4`,
  `audio/x-m4a`, or `audio/ogg`; optional MIME parameters are stripped before
  comparison. A missing/disallowed type is invalid. The upstream request uses
  Guzzle `stream => true`; Laravel never calls `body()`. A `StreamedResponse`
  reads the upstream PSR-7 stream in 8 KiB chunks and closes it after delivery,
  avoiding application-level full-response buffering.
- Validation failures remain `422`.
- Local or upstream rate limits remain `429`. Upstream rate limits return
  `429 UPSTREAM_RATE_LIMITED`, preserve `Retry-After`, and never relay the
  upstream body.
- Connection failures/timeouts return `503 UPSTREAM_UNAVAILABLE`.
- Upstream `5xx` returns `503 UPSTREAM_UNAVAILABLE`.
- Missing proxy URL/credential or other local proxy configuration failure
  returns `503 PROXY_UNAVAILABLE`.
- Non-JSON, malformed JSON, or a JSON object missing/wrongly typing the required
  endpoint fields returns `503 UPSTREAM_INVALID_RESPONSE`.
- Missing/disallowed TTS MIME returns `503 UPSTREAM_INVALID_RESPONSE`.
- Other upstream `4xx` responses return `422 UPSTREAM_REJECTED` without
  relaying the upstream body.
- Logs contain only action, user ID, exception class, and status; never text, audio, credentials, or upstream response bodies.

This design intentionally amends the earlier generic `{message, errors}` error
shape for service-proxy failures. Proxy errors are exactly
`{message: string, code: string}`; validation keeps Laravel's existing
`{message, errors}` shape. `ApiResponse::error()` may accept extra fields, but
they cannot replace its fixed `message` or `code`.

## Code Shape

Keep the existing controller and client boundary. Extract only the repeated audio validation and multipart attachment logic into private controller helpers. Reuse `ApiResponse`; fixed `message` and `code` fields cannot be overridden by extra response data. No new package, service class, alias route, compatibility layer, or speculative streaming subsystem is introduced.

## Verification

Feature tests must cover:

- exact public and upstream methods/paths;
- authentication and both minute/daily rate limits;
- CSRF rejection without quota consumption, shared cross-endpoint quota for one
  user, and bucket isolation between users;
- input validation and TTS 2,000-character boundary;
- multipart filename/MIME preservation;
- success, retry, upstream `4xx`, `429`, `5xx`, timeout, missing configuration,
  malformed/non-JSON/wrong-shape JSON, and invalid audio response MIME;
- streamed TTS bytes and content type;
- removal of temporary `/api/v1/ai/*` routes.
- protection of `ApiResponse::error()` fixed fields from extra-data override.
- boundary/config tests for voice length, audio-size clamp, timeout clamp,
  retry-attempt clamp and retry delay; tests also prove that only translation
  retries connection/`5xx` and POST-based services make one upstream attempt.

OpenAPI, environment examples, production documentation, and project status
must describe the same contract. Full backend tests, Pint, both frontend
checks, and OpenAPI lint are required before merge. Because the repository does
not contain the upstream OpenAPI document or a configured AI service URL, merge
also requires attaching a real-service smoke-test result or the exact upstream
OpenAPI revision used to confirm the adapter fields above; this is a release
gate, not deferred technical debt.
