# Next.js Auth Suite Design

## Goal

Replace the learner-facing legacy Blade authentication UI with a complete Next.js authentication experience at `https://linguist-nova.vercel.app`. Laravel remains the API, session, email, and OAuth backend only.

## User experience

The desktop authentication layout is split into two equal-height columns:

- The left panel uses a locally stored, optimized real photograph at `frontend/public/images/auth-language-study.webp`. A restrained gradient overlay keeps text readable. Pointer movement creates a small CSS/React parallax offset, with no motion when `prefers-reduced-motion` is enabled.
- The right panel contains the active form in a focused reading column. It includes the Linguist wordmark, concise supporting copy, accessible form labels, inline errors, a primary action, Google and Facebook actions, and links between authentication flows.

On small screens the photograph becomes a short hero above the form. Forms remain usable at 320 px width, keyboard focus remains visible, and status/error messages use accessible live regions.

## Next.js routes

- `/login`: email/password login, Google, Facebook, forgot-password link, and register link.
- `/register`: name, email, password, confirmation, submit, and login link. A successful request navigates to `/verify-email`.
- `/verify-email`: shows the email-verification instruction and supports resend through the existing `POST /api/v1/auth/email/resend` endpoint.
- `/forgot-password`: email submission and a privacy-preserving success message.
- `/reset-password`: reads `token` and `email` from the query string, validates password confirmation, submits the reset, then links or redirects to login.
- `/auth/callback`: a transient OAuth completion screen that calls `refreshUser()`, which verifies the new Laravel session and writes the frontend session hint, before replacing the route with the validated destination. Failure returns to `/login?oauth_error=session_failed`.

All routes share a new, small `AuthLayout` built from the project's existing generic `Button`, `Input`, and `Card` components. Form pages remain separate components rather than introducing a form framework or schema dependency. The implementation does not add a UI or animation dependency.

The route policy exposes `isAuthPath()` for `/login`, `/register`, `/verify-email`, `/forgot-password`, `/reset-password`, and `/auth/callback`. `AppShell` renders every auth path outside the learner navigation chrome but inside the stacking context required to remain above the persistent background.

## API and session flow

Existing same-origin Next.js rewrites remain the boundary for JSON requests:

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/password/forgot`
- `POST /api/v1/auth/password/reset`
- `GET /api/v1/auth/me`

Laravel session cookies remain host-only so responses proxied by Vercel establish the session on the frontend hostname.

## OAuth flow

Add API-scoped OAuth entry and callback routes for Google and Facebook. Both the entry and callback are reached through the Vercel hostname so the host-only session cookie containing Socialite state remains available:

- Entry: `https://linguist-nova.vercel.app/api/v1/auth/oauth/{provider}?next=/`
- Callback: `https://linguist-nova.vercel.app/api/v1/auth/oauth/{provider}/callback`
- `GOOGLE_REDIRECT_URI=https://linguist-nova.vercel.app/api/v1/auth/oauth/google/callback`
- `FACEBOOK_REDIRECT_URI=https://linguist-nova.vercel.app/api/v1/auth/oauth/facebook/callback`

The `next` parameter defaults to `/`, accepts only internal paths beginning with one `/` (not `//`) and excludes auth routes. The entry route stores it as `auth.oauth_next` in the current session. The callback consumes it once with `pull`; normal Laravel session lifetime applies. The provider callback:

1. verifies provider state through Socialite,
2. creates or loads the learner account,
3. regenerates the Laravel session,
4. permits login only when a new account can be created as `learner` or an existing account already has the learner role; an email owned by an admin or any non-learner role fails without linking or logging in,
5. redirects to `{FRONTEND_URL}/auth/callback?next=<encoded-safe-destination>` so Next.js bootstraps its auth state before navigation,
6. redirects to `/login?oauth_error=<code>` on a handled provider error without exposing provider messages or credentials.

The Next.js buttons navigate to the backend through same-origin `/api/v1/auth/oauth/{provider}` paths. Only `google` and `facebook` are accepted. Provider credentials and exact callback URLs remain Fly secrets.

Stable error codes are `cancelled`, `invalid_state`, `email_missing`, `role_conflict`, `provider_failed`, and `session_failed`. Next.js maps these codes to user-facing text; raw provider errors are logged server-side without credentials and never returned in the URL.

## Password-reset email

Laravel's reset notification must generate a frontend URL:

`{FRONTEND_URL}/reset-password?token=...&email=...`

The token is never logged or persisted by Next.js. Missing query parameters produce an actionable invalid-link state rather than submitting an incomplete request.

## Legacy Blade boundary

Learner-facing production GET routes redirect as follows:

- `/` → `{FRONTEND_URL}/`
- `/login` → `{FRONTEND_URL}/login`
- `/register` → `{FRONTEND_URL}/register`
- `/forgot-password` → `{FRONTEND_URL}/forgot-password`
- `/reset-password/{token}` → `{FRONTEND_URL}/reset-password?token=...&email=...`
- `/verify-email` → `{FRONTEND_URL}/verify-email`
- learner `/profile`, `/progress`, and `/words` routes → their Next.js equivalents where one exists, otherwise `/`

The old learner-facing web POST authentication endpoints (`/login`, `/register`, `/forgot-password`, `/reset-password`) are retired after their API equivalents are covered by feature tests. API, health, OAuth callbacks, signed email-verification routes, and admin routes remain on Fly. Blade auth views and controller rendering methods are deleted only after route and test references are removed in the same change; unrelated admin CRUD Blade views remain out of scope.

## Error handling

- Validation errors render next to the relevant field when available.
- Invalid credentials and unverified email keep the current safe messages.
- OAuth cancellation/failure returns a stable error code rendered by Next.js.
- Network failures offer retry without clearing user-entered fields.
- Forgot-password always returns the privacy-preserving backend response.

## Security

- Preserve CSRF initialization for all state-changing requests.
- Regenerate sessions after password and social login.
- Validate OAuth provider allowlists and frontend redirect destinations.
- Do not expose OAuth secrets to Next.js public environment variables.
- Do not use arbitrary remote image hosts; ship the selected image with the frontend.

## Verification

- Unit tests cover each form's successful request, validation/error state, and navigation.
- Feature tests cover OAuth provider allowlisting, callback redirect behavior, and frontend reset-link generation.
- Existing frontend lint/test/build and backend PHP matrix remain green.
- Production verification checks desktop and mobile screenshots, reduced-motion behavior, keyboard navigation, login API proxy health, and that Fly learner UI routes redirect to Next.js.
- Registration verification covers the `/verify-email` state and resend action.
- OAuth verification covers session bootstrapping before destination navigation.
- Shell tests verify that every auth route renders without learner navigation and remains above the persistent background.
