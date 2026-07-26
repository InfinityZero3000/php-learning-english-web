# Next.js Auth Suite Design

## Goal

Replace the learner-facing legacy Blade authentication UI with a complete Next.js authentication experience at `https://linguist-nova.vercel.app`. Laravel remains the API, session, email, and OAuth backend only.

## User experience

The desktop authentication layout is split into two equal-height columns:

- The left panel uses a locally stored, optimized real photograph related to language learning. A restrained gradient overlay keeps text readable. Pointer movement creates a small CSS/React parallax offset, with no motion when `prefers-reduced-motion` is enabled.
- The right panel contains the active form in a focused reading column. It includes the Linguist wordmark, concise supporting copy, accessible form labels, inline errors, a primary action, Google and Facebook actions, and links between authentication flows.

On small screens the photograph becomes a short hero above the form. Forms remain usable at 320 px width, keyboard focus remains visible, and status/error messages use accessible live regions.

## Next.js routes

- `/login`: email/password login, Google, Facebook, forgot-password link, and register link.
- `/register`: name, email, password, confirmation, submit, and login link. A successful request shows the email-verification instruction returned by the API.
- `/forgot-password`: email submission and a privacy-preserving success message.
- `/reset-password`: reads `token` and `email` from the query string, validates password confirmation, submits the reset, then links or redirects to login.

All routes share a small `AuthLayout` and auth-specific form primitives already available in the project. The implementation does not add a UI or animation dependency.

## API and session flow

Existing same-origin Next.js rewrites remain the boundary for JSON requests:

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/password/forgot`
- `POST /api/v1/auth/password/reset`
- `GET /api/v1/auth/me`

Laravel session cookies remain host-only so responses proxied by Vercel establish the session on the frontend hostname.

## OAuth flow

Add API-scoped OAuth entry and callback routes for Google and Facebook. The entry route stores a validated frontend return target in the session and redirects to the provider. The provider callback:

1. verifies provider state through Socialite,
2. creates or loads the learner account,
3. regenerates the Laravel session,
4. redirects to the configured `FRONTEND_URL` with a safe success destination,
5. redirects to `/login?oauth_error=<code>` on a handled provider error without exposing provider messages or credentials.

The Next.js buttons navigate to the backend through same-origin `/api/v1/auth/oauth/{provider}` paths. Only `google` and `facebook` are accepted. Provider credentials and exact callback URLs remain Fly secrets.

## Password-reset email

Laravel's reset notification must generate a frontend URL:

`{FRONTEND_URL}/reset-password?token=...&email=...`

The token is never logged or persisted by Next.js. Missing query parameters produce an actionable invalid-link state rather than submitting an incomplete request.

## Legacy Blade boundary

Learner-facing production entry routes must redirect to their Next.js equivalents. API and health routes remain on Fly. Existing Blade files may be deleted only when no named routes, admin flows, feature tests, or controllers still depend on them; production exposure is removed regardless. This avoids breaking the current admin/legacy surface as an unrelated side effect.

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
