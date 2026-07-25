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

- [ ] Add failing feature tests for login throttling, reset-link throttling,
  verification resend throttling, and neutral unknown-email responses.
- [ ] Run the focused tests and verify they fail.
- [ ] Add built-in route middleware and return the same reset-link response for
  known and unknown addresses.
- [ ] Run the focused tests and verify they pass.

### Task 2: Password and email verification events

**Files:**
- Modify: `app/Http/Controllers/ForgotPasswordController.php`
- Modify: `app/Http/Controllers/EmailVerificationController.php`
- Test: `tests/Feature/ForgotPasswordTest.php`
- Test: `tests/Feature/RegistrationTest.php`

- [ ] Add failing tests for remember-token rotation, `PasswordReset`, and
  `Verified`, including no duplicate event for an already verified user.
- [ ] Run the focused tests and verify they fail.
- [ ] Rotate the remember token, dispatch `PasswordReset`, and dispatch
  `Verified` only when `markEmailAsVerified()` returns `true`.
- [ ] Run the focused tests and verify they pass.

### Task 3: Required learner role

**Files:**
- Modify: `app/Http/Controllers/AuthController.php`
- Test: `tests/Feature/RegistrationTest.php`

- [ ] Add a failing test proving registration does not create a role-less user.
- [ ] Run the focused test and verify it fails.
- [ ] Resolve the learner role before creating the user; if absent, throw
  `ValidationException::withMessages(...)`, otherwise create the user with the
  resolved role.
- [ ] Run the focused test and verify it passes.

## Chunk 2: Profile safety and branding

### Task 4: Password confirmation and protected deletion

**Files:**
- Modify: `app/Http/Requests/UpdateProfileRequest.php`
- Modify: `app/Http/Controllers/ProfileController.php`
- Modify: `resources/views/profile/index.blade.php`
- Test: `tests/Feature/ProfileTest.php`

- [ ] Add failing tests for new-password confirmation and account deletion with
  missing, incorrect, and correct current passwords.
- [ ] Run the focused tests and verify they fail.
- [ ] Add the `confirmed` rule and confirmation input. Validate deletion with
  `['required', 'current_password']`; only a correct password may delete the
  account.
- [ ] Run the focused tests and verify they pass.

### Task 5: Remove unapproved branding

**Files:**
- Modify: `resources/views/layouts/auth.blade.php`
- Modify: `resources/views/auth/*.blade.php`
- Modify: `resources/views/profile/index.blade.php`

- [ ] Replace every user-visible `LexiLingo` occurrence with
  `English Learning`.
- [ ] Verify `rg -i lexilingo app config resources routes tests` returns no
  matches.

### Task 6: Full verification

- [ ] Run `php artisan test` and require all tests to pass.
- [ ] Run `./vendor/bin/pint --test` and require it to pass.
- [ ] Review the final diff for unrelated changes.
