# Google-only Admin Whitelist

## Goal

Restrict the administration application to Google-authenticated email
addresses listed only in server environment configuration.

## Configuration and Roles

Laravel reads two comma-separated, case-insensitive lists through
`config/admin_access.php`:

- `ADMIN_GOOGLE_EMAILS`;
- `SUPER_ADMIN_GOOGLE_EMAILS`.

Whitespace and empty entries are ignored, duplicates are removed and emails
are normalized to lowercase. Every non-empty value must pass email validation;
missing or malformed configuration fails closed and disables admin login. A
super-admin entry grants every admin capability even when the
address is absent from the admin list. If an address appears in both lists,
`super_admin` wins. `nhthang312@gmail.com` is configured as a super admin.
Neither list is exposed by an API, logged, stored in the database as a
whitelist, or embedded in either frontend bundle.

Production changes require rebuilding Laravel's config cache and reloading
application/queue processes. Revocation is effective on the first request
handled after that reload; deployment documentation must make this explicit.

## Authentication Flow

The admin login page contains only “Continue with Google”. It starts a
dedicated admin Google OAuth flow. Laravel stores the intended portal in the
session and relies on Socialite OAuth state validation. The callback accepts
only a verified Google email.

If the normalized email is absent from both lists, Laravel invalidates the
candidate session and redirects to the admin login page with a generic access
denied code. It does not reveal which addresses are allowed.

For an allowed address Laravel upserts or links the local user, stores the
immutable Google provider subject, marks the email
verified, synchronizes its role to `admin` or `super_admin`, rotates the
session ID and CSRF token, stores a Google-admin authentication marker bound
to the user ID, Google subject and normalized email, and redirects only
to the configured admin frontend origin/dashboard. Arbitrary callback URLs
are not accepted.

Google must report `email_verified = true`. A subject already bound to another
email, an email bound to another Google subject/provider, or a conflicting
local identity fails closed and requires manual operator resolution.

Normal learner Google login remains available and does not grant admin
access merely because the database role was previously elevated.

## Request Enforcement

All `/api/v1/admin/*` routes require one middleware that verifies on every
request:

1. an authenticated Laravel user;
2. the Google-admin session marker;
3. the current normalized email remains in one of the environment lists;
4. the marker user ID, Google subject and email match the current user;
5. the database role matches the highest current whitelist role.

The middleware synchronizes a changed allowed role downward or upward inside
a transaction before authorization. Removing an address from both lists
atomically clears the marker and recent-reauth timestamp, demotes the persisted
role to `learner`, logs the user out of the admin session and denies the
request. Existing capability
gates continue to distinguish ordinary admin from super-admin operations.

Password login, password reset, a forged database role, learner Google login,
and direct frontend navigation cannot satisfy the Google-admin marker.
Every initial login attempt, logout, denial, OAuth state failure, account
switch and revocation clears the marker and recent-reauth timestamp before
proceeding.

## Interface and Errors

The admin frontend removes password fields and API password-login usage. It
shows Google-only access, handles denied/cancelled/configuration errors, and
redirects an expired or revoked session to login.

Recent-password confirmation currently used for sensitive mutations becomes
a server-enforced Google re-authentication requirement valid for 15 minutes.
Sensitive forms prompt “Verify with Google” rather than requesting a password.
Starting reauthentication preserves the current identity-bound marker and
clears only the prior freshness timestamp. The reauth redirect uses OAuth
state and forces fresh Google interaction with
`prompt=select_account` and `max_age=0`. Its callback must match the currently
authenticated user ID, stored Google subject and normalized email, repeat the
whitelist/role check, and return only to a fixed allowlisted admin route. It
never upserts or switches accounts. Only that successful callback stores the
new reauthentication timestamp.

All admin and capability-bearing routes are inventoried in an automated route
test. Initial admin OAuth start/callback are narrowly scoped bootstrap routes:
they require OAuth state, fixed safe redirects, rate limiting and fail-closed
callback checks but cannot require a marker they establish. Reauthentication,
`/api/v1/admin/*`, operational mutations and any future capability-bearing
route require the Google-admin boundary plus their existing capability gate;
adding an uncovered route fails the test.

## Verification

Tests cover config-cache/reload expectations, malformed fail-closed config,
normalization, duplicate handling, list precedence, allowed admin, allowed
super-admin, denied address, missing/invalid Google email, OAuth state,
session rotation, safe redirects, password-session rejection, learner-Google
session rejection, immediate revocation, role synchronization, admin versus
super-admin authorization, Google subject conflicts, marker identity binding,
fresh same-identity reauth, route coverage and secret/list redaction. Admin
lint and production build must pass.
