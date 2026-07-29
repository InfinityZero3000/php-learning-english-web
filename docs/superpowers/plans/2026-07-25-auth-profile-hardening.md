# Auth and Profile Hardening Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Correct security and business-rule gaps in the existing Auth and Profile workflows.

**Architecture:** Reuse Laravel validation, events, password broker, and route throttling. Keep controllers and requests in their current structure and add only focused feature-test coverage.

**Tech Stack:** PHP 8.3, Laravel 13, PHPUnit, Laravel Pint

---

## Chunk 1: Authentication lifecycle

### Task 1: Rate limits and neutral password-reset response

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/ForgotPasswordController.php`
- Test: `tests/Feature/LoginTest.php`
- Test: `tests/Feature/ForgotPasswordTest.php`
- Test: `tests/Feature/RegistrationTest.php`

- [x] Add failing feature tests for login throttling, reset-link throttling,
  verification resend throttling, and neutral unknown-email responses.
- [x] Run the focused tests and verify they fail.
- [x] Add built-in route middleware and return the same reset-link response for
  known and unknown addresses.
- [x] Run the focused tests and verify they pass.

### Task 2: Password and email verification events

**Files:**
- Modify: `app/Http/Controllers/ForgotPasswordController.php`
- Modify: `app/Http/Controllers/EmailVerificationController.php`
- Test: `tests/Feature/ForgotPasswordTest.php`
- Test: `tests/Feature/RegistrationTest.php`

- [x] Add failing tests for remember-token rotation, `PasswordReset`, and
  `Verified`, including no duplicate event for an already verified user.
- [x] Run the focused tests and verify they fail.
- [x] Rotate the remember token, dispatch `PasswordReset`, and dispatch
  `Verified` only when `markEmailAsVerified()` returns `true`.
- [x] Run the focused tests and verify they pass.

### Task 3: Required learner role

**Files:**
- Modify: `app/Http/Controllers/AuthController.php`
- Test: `tests/Feature/RegistrationTest.php`

- [x] Add a failing test proving registration does not create a role-less user.
- [x] Run the focused test and verify it fails.
- [x] Resolve the learner role before creating the user; if absent, throw
  `ValidationException::withMessages(...)`, otherwise create the user with the
  resolved role.
- [x] Run the focused test and verify it passes.

## Chunk 2: Profile safety and branding

### Task 4: Password confirmation and protected deletion

**Files:**
- Modify: `app/Http/Requests/UpdateProfileRequest.php`
- Modify: `app/Http/Controllers/ProfileController.php`
- Modify: `resources/views/profile/index.blade.php`
- Test: `tests/Feature/ProfileTest.php`

- [x] Add failing tests for new-password confirmation and account deletion with
  missing, incorrect, and correct current passwords.
- [x] Run the focused tests and verify they fail.
- [x] Add the `confirmed` rule and confirmation input. Validate deletion with
  `['required', 'current_password']`; only a correct password may delete the
  account.
- [x] Run the focused tests and verify they pass.

### Task 5: Remove unapproved branding

**Files:**
- Modify: `resources/views/layouts/auth.blade.php`
- Modify: `resources/views/auth/*.blade.php`
- Modify: `resources/views/profile/index.blade.php`

- [x] Replace every user-visible `LexiLingo` occurrence with
  `English Learning`.
- [x] Verify `rg -i lexilingo resources/views` returns no user-visible branding
  matches. Keep technical integration names such as `LexiLingoClient` unchanged.

### Task 6: Full verification

- [x] Run `php artisan test` and require all tests to pass.
- [x] Run `./vendor/bin/pint --test` and require it to pass.
- [x] Review the final diff for unrelated changes.
