# Auth and Profile Hardening Design

## Goal

Correct the existing authentication and profile workflows without adding new
features or dependencies.

## Design

- Apply Laravel's built-in `throttle` middleware to login, password-reset email,
  and verification-email resend routes.
- Keep forgot-password responses indistinguishable for known and unknown email
  addresses.
- Follow Laravel's password-reset lifecycle by rotating the remember token and
  dispatching `PasswordReset`.
- Dispatch `Verified` only when a signed verification link newly verifies an
  address.
- Require the learner role to exist before registration creates a user.
- Require confirmation for a new password and the current password before
  destructive account deletion.
- Replace the unapproved `LexiLingo` branding with the repository's existing
  `English Learning` name.

## Error Handling

Missing required catalog data returns a registration validation error without
creating a partially configured user. Invalid credentials and unknown password-reset
emails return safe user-facing errors or neutral success messages. Laravel
continues to handle invalid signed links, invalid reset tokens, CSRF, and rate
limit responses.

## Verification

Feature tests cover throttling, neutral reset-link responses, reset lifecycle
events and token rotation, verification events, missing learner roles, password
confirmation, and password-protected account deletion. The full test suite and
Pint must pass.
