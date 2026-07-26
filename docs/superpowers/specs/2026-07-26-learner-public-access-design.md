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
- Guests on protected pages are redirected to `/login?next=<path>`.
- Server and transport failures remain visible with a retry action.

## Data Loading

Public catalog requests load independently of authentication. User-owned data
such as profile, progress, streak, notifications, bookmarks, and imports is
requested only after a user session is available or from a protected page.
After login, the frontend queries the current user and the user-specific data
needed by the active page.

## Implementation

Keep the policy in the existing learner `AppShell`; do not add Next.js
middleware or duplicate guards across pages. Represent protected routes as a
small route-prefix list so nested protected pages inherit the same behavior.
Preserve the existing Laravel session and CSRF API client.

## Error Handling

- `401` on public pages: render guest state.
- `401` on protected pages: redirect to login with the original path.
- Other failures from `auth.me()`: show the existing retry screen.
- Public API failure: show that feature's normal error state without treating
  the user as logged out.

## Verification

Component tests cover guest access to public pages, guest redirects from
protected pages, authenticated rendering, and transport failures. Existing API
client tests, frontend lint/build, and backend tests remain green.
