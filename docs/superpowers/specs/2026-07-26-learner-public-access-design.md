# Learner Public Access Design

## Goal

Allow guests to use public learner pages and public catalog APIs while keeping
profile and other user-owned data behind authentication.

## Route Policy

- Public pages: `/`, `/vocabulary`, `/flashcards`, and `/quiz`.
- Protected pages: `/profile`, `/progress`, and `/import`.
- A `401` response from `/api/v1/auth/me` means “guest”, not an application
  failure.
- Guests on public pages remain in the application shell and see a login call
  to action.
- Guests on protected pages are redirected to `/login?next=<encoded-relative-url>`.
- Server and transport failures remain visible with a retry action.

Protected-route matching is segment-aware: a route matches only when it equals
the protected prefix or starts with `<prefix>/`. A lookalike such as
`/profiled` is not protected by the `/profile` rule.

## Data Loading

Public catalog requests load independently of authentication. User-owned data
such as profile, progress, streak, notifications, bookmarks, and imports is
requested only after a user session is confirmed.

The shell provides one shared auth state (`checking`, `guest`, `authenticated`,
or `unavailable`) to learner pages and widgets. Notification, streak, progress,
review, bookmark, and import consumers do not mount or request data in guest
state. After login, the frontend queries the current user and only the
user-specific data needed by the active page.

Public pages may display quiz, flashcard, and vocabulary catalog content to a
guest. Any action that persists user state—quiz submission, review/save,
bookmark, import, create/update/delete, or enrichment—redirects to login with a
safe return URL instead of issuing a protected request.

## Implementation

Keep route policy in the learner `AppShell`; do not add Next.js middleware or
duplicate session checks across pages. Add a small auth context owned by the
shell so pages/widgets consume the result of the single `auth.me()` request.
Represent protected routes as a route-prefix list so nested protected pages
inherit the same behavior. Preserve the existing Laravel session and CSRF API
client.

The shell holds a stable auth state instead of rerunning `auth.me()` on every
pathname change. While state is `checking`, protected children do not mount.
Once guest status is known, navigation to a protected route redirects before
rendering its children.

The return URL includes pathname and query string, is URL-encoded, and is
consumed after login only when it begins with exactly one `/` and not `//`.
Invalid or external values fall back to `/`.

## Error Handling

- `401` on public pages: render guest state.
- `401` on protected pages: redirect to login with the original relative URL.
- Other failures from `auth.me()`: public catalog remains usable with auth
  state `unavailable`; protected pages show the existing retry screen.
- Public API failure: show that feature's normal error state without treating
  the user as logged out.

Client guards are UX only. Laravel continues to require authentication and
resource authorization for every user-owned endpoint. Public catalog responses
must not expose per-user fields.

## Verification

Component tests cover guest access to public pages, segment-aware protected
redirects (including nested and lookalike paths), safe `next` handling,
authenticated rendering, and auth transport failures. Tests also prove that
protected children never mount before redirect and guests make zero
user-specific requests. Existing API client tests, frontend lint/build, and
backend authorization tests remain green.
