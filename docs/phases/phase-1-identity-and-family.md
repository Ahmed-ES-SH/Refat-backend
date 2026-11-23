# Phase 1 — Identity and family

**Status:** In progress
**Risk class:** Large / High Risk (authentication, authorization, personal data) — Section 3 of `docs/AI-Agent-Development-Standard.md`
**Workflow:** EGM-style draft → one independent review → human approval → ordered plan → controlled execution → phase verification
**Sources:** `docs/rifad-backend-plan.md` §5 (Phase 1), §7, §8, §9 · `docs/rifad-database-schema.md` §4.1–4.3, §8 · `AGENTS.md` §5–§10

---

## Objective

A patient can create an account, verify their email, sign in and out, recover a forgotten password, sign in with Google, maintain their profile, and manage their own family members — all over `/api/v1` with Sanctum SPA cookie auth, server-side authorization, a stable error envelope, and tests for the security-sensitive paths.

The repository is still the bare Laravel skeleton, so this phase also delivers the **Phase-1-critical part of Phase 0 (Foundation)**. See "Foundation slice" below for exactly what is included and what is deferred.

---

## Scope

### In scope

**Foundation slice (prerequisite, approved as part of this phase)**
- `laravel/sanctum` (SPA cookie auth), `laravel/socialite` (Google), `dedoc/scramble` (OpenAPI).
- `routes/api_v1.php` mounted at `/api/v1`; `GET /api/v1/ping`.
- Error envelope `{ message, code, errors }` for every API error, with stable machine codes.
- `AssignRequestId` middleware (`X-Request-ID` in, log context, response header) and JSON log channel.
- `app/Enums`: `UserRole`, `UserStatus`, `Locale`, `Gender`, `FamilyRelation`.
- Migrations in schema order: `centers` → `users` (renamed to sort after `centers`) → `password_reset_tokens` → `family_members` → `sessions` (temporary).
- `lang/ar` for auth, passwords and validation; `lang/en` for auth and passwords (English validation falls back to the framework defaults).
- CORS + Sanctum stateful config for `localhost:3000` → `localhost:8000`.
- `config/permissions.php` role → permissions map.

**Phase 1 features**
- Patient registration, login, logout (session-based).
- Email verification (signed link) and resend.
- Forgot / reset password (60 minutes, single use).
- Change email and change password (current password required).
- Google OAuth: sign in, and link to an existing account only when the Google email is verified and matches.
- `GET /me`, `PATCH /me` with the contract fields plus `permissions[]`.
- Family members CRUD, owner-scoped, foreign IDs return 404.
- Login throttling per IP **and** per identifier; suspended users cannot sign in.
- Unverified users are blocked from booking/purchasing by a reusable middleware (`EMAIL_NOT_VERIFIED`).

### Out of scope (explicitly)
- Center self-registration and admin approval — Phase 2 (it needs the `centers` record and the approval flow; a `center_staff` account without a center would violate the schema invariant).
- `MEMBER_HAS_FUTURE_APPOINTMENTS` enforcement — Phase 3, per the plan ("wired fully in Phase 3"); the `appointments` table does not exist yet. Phase 1 delete is a soft delete.
- Redis, Mailpit, Caddy single-origin, Reverb, Larastan, GitHub Actions CI, load tests, seeders/demo data.
- Email/password policy beyond `Password::defaults()`, 2FA, session/device management, role editor (all on the Cut list).
- Frontend work (sibling repo, separate phase).

---

## Context and Dependencies

- **Auth model:** Sanctum SPA cookie auth only. No API tokens in JavaScript, `localStorage`, or `sessionStorage` (`AGENTS.md` §5.14).
- **Schema:** `users`, `password_reset_tokens`, `family_members` exactly as in `rifad-database-schema.md` §4.1–4.3. Enums are string columns cast to PHP backed enums; never MySQL `ENUM`. Money/timestamps rules do not apply yet.
- **Dev environment:** Docker Compose (`../compose.yaml`, driven by `Makefile`): `backend` (PHP 8.5, `artisan serve`, MySQL 8.4), `mysql`, `phpmyadmin`, `frontend`. No Redis, no Mailpit, no queue worker running by default. Tests run on SQLite `:memory:` via `phpunit.xml`.
- **Laravel version:** 13.42. Model attributes use `#[Fillable]` / `#[Hidden]`; `Socialite` is resolved from `Laravel\Socialite\Socialite` and supports `Socialite::fake()`. Verified against the installed framework docs before implementation.
- **Frontend contract:** `rifad-backend-plan.md` §7 — one origin in production; `GET /sanctum/csrf-cookie` → `POST /api/v1/auth/login` → HttpOnly session cookie → `GET /api/v1/me`; `401` → login, `403` + `code` → contextual message.

---

## Expected Behavior

### Endpoints

All paths are relative to `/api/v1`. Success responses use `{ "data": ... }`; lists use `{ "data": [...], "meta": { page, per_page, total } }`.

| Method | Path | Auth | Throttle | Success | Notes |
|---|---|---|---|---|---|
| GET | `/ping` | — | `api` | 200 `{data:{pong:true}}` | Foundation health/demo endpoint |
| POST | `/auth/register` | — | `register` | 201 `UserResource` | Patient only; logs the user in; sends verification mail |
| POST | `/auth/login` | — | `login` | 200 `UserResource` | Session regenerated. Credentials are checked before the account status, so a suspended account cannot be probed without the password |
| POST | `/auth/logout` | yes | `api` | 204 | Session invalidated + token regenerated |
| POST | `/auth/forgot-password` | — | `password-reset` | 200 `{message}` | Always 200, never reveals whether the email exists |
| POST | `/auth/reset-password` | — | `password-reset` | 200 `{message}` | Token single use, 60 min |
| GET | `/auth/email/verify/{id}/{hash}` | signed | `api` | 302 → `FRONTEND_URL/auth/verified` | Marks verified; no session required |
| POST | `/auth/email/verification-notification` | yes | `verification` | 202 `{message}` | Already verified → 409 `EMAIL_ALREADY_VERIFIED` |
| GET | `/auth/google/redirect` | — | `api` | 302 → Google | Browser navigation (web middleware) |
| GET | `/auth/google/callback` | — | `api` | 302 → `FRONTEND_URL/auth/callback` | Creates/links the account, establishes the session |
| GET | `/me` | yes | `api` | 200 `UserResource` | Includes `permissions[]` |
| PATCH | `/me` | yes | `api` | 200 `UserResource` | `name`, `phone`, `date_of_birth`, `gender`, `locale` |
| PUT | `/me/password` | yes | `sensitive` | 200 `{message}` | Requires `current_password` |
| PATCH | `/me/email` | yes | `sensitive` | 200 `UserResource` | Requires `current_password`; resets `email_verified_at`; sends verification |
| GET | `/me/family-members` | yes | `api` | 200 paginated | Owner-scoped |
| POST | `/me/family-members` | yes | `api` | 201 `FamilyMemberResource` | |
| GET | `/me/family-members/{id}` | yes | `api` | 200 | Foreign ID → 404 |
| PATCH | `/me/family-members/{id}` | yes | `api` | 200 | Foreign ID → 404 |
| DELETE | `/me/family-members/{id}` | yes | `api` | 204 | Soft delete; foreign ID → 404 |

`GET /me` payload:

```json
{
  "data": {
    "id": 1,
    "name": "Sara Al-Otaibi",
    "email": "sara@example.com",
    "role": "patient",
    "email_verified": true,
    "locale": "ar",
    "center_id": null,
    "phone": "0551234567",
    "date_of_birth": "1994-04-12",
    "gender": "female",
    "permissions": [
      "manage_family_members",
      "book_appointments",
      "cancel_own_appointments",
      "chat_with_centers",
      "buy_membership"
    ]
  }
}
```

`phone`, `date_of_birth` and `gender` are additive to the plan's field list: `PATCH /me` accepts them, so the profile screen needs them back to render. Everything the plan lists is still present and unchanged.

### Error envelope

Every API error returns the same shape; `code` is a stable machine string, `errors` only appears for validation:

```json
{ "message": "...", "code": "VALIDATION_ERROR", "errors": { "email": ["..."] } }
```

| Code | Status | When |
|---|---|---|
| `VALIDATION_ERROR` | 422 | Form Request failure |
| `INVALID_CREDENTIALS` | 422 | Wrong email/password (also carries `errors.email`) |
| `UNAUTHENTICATED` | 401 | Missing/expired session |
| `FORBIDDEN` | 403 | Policy / gate denial |
| `NOT_FOUND` | 404 | Missing or foreign-owned resource |
| `TOO_MANY_REQUESTS` | 429 | Throttled route |
| `EMAIL_NOT_VERIFIED` | 403 | `verified` middleware |
| `EMAIL_ALREADY_VERIFIED` | 409 | Resend when already verified |
| `INVALID_SIGNATURE` | 403 | Tampered or expired signed link |
| `INVALID_RESET_TOKEN` | 422 | Unknown, used or expired password reset token |
| `ACCOUNT_SUSPENDED` | 403 | Login or authenticated request by a suspended user |
| `GOOGLE_EMAIL_CONFLICT` | 409 | Google email matches an existing unverified account, or an account already linked to another Google identity |
| `GOOGLE_EMAIL_UNVERIFIED` | 403 | Google reports the email as unverified or missing |
| `GOOGLE_NOT_CONFIGURED` | 302 | Client id/secret missing → redirect to the login screen with `error=` |
| `GOOGLE_AUTH_FAILED` | 302 | Consent denied, expired state or provider failure → redirect to the login screen with `error=` |
| `SERVER_ERROR` | 500 | Unexpected failure (message generic in production) |

### Behavior rules

- **Registration:** `role` is not accepted from the client; every registration creates a `patient`. `locale` defaults to `ar` unless supplied (`ar|en`). Password follows `Password::defaults()` (min 8). A verification email is sent immediately.
- **Login:** email + password. Credentials are verified first; only then is the account status checked, so `ACCOUNT_SUSPENDED` cannot be used to probe which emails exist. An unverified account **may** sign in (the gate applies to booking and purchasing, not to browsing). Failures never disclose which field was wrong.
- **Throttling:** `login` = 5/min per `email|ip` **and** 20/min per IP; `register` = 5/min per IP; `password-reset` = 3/min per `email|ip`; `verification` = 3/min per user; `sensitive` = 6/min per user; `api` = 60/min per user or IP.
- **Verification link:** temporary signed URL, 60 minutes, `hash = sha1(email)`. Visiting it marks the email verified and redirects to the frontend. An invalid or expired signature returns `403` (JSON envelope, since the route is under `/api`).
- **Password reset:** Laravel password broker, `expire = 60`, `throttle = 60`. The email links to `FRONTEND_URL/reset-password?token=…&email=…`. A second use of the same token fails; an expired token fails; unknown emails still return 200.
- **Change email:** requires the current password, updates the email, clears `email_verified_at`, and sends a fresh verification link. Duplicate email → 422.
- **Change password:** requires the current password; the new password must differ from the current one.
- **Google OAuth:** the callback resolves the account in this order — (1) by `google_id`, (2) by email, (3) create. Existing `google_id` → sign in. Existing account with the same email and a verified email → link `google_id`. Existing account with the same email but unverified → `409 GOOGLE_EMAIL_CONFLICT`. No account → patient created with `email_verified_at = now()`, `password = null`, `google_id` stored. Google reports an unverified email → `403 GOOGLE_EMAIL_UNVERIFIED`. Suspended account → `403 ACCOUNT_SUSPENDED`. All failures redirect to `FRONTEND_URL/login?error=<CODE>` because the flow is browser-based.
- **Family members:** `relation` ∈ `spouse|child|parent|sibling|other`, `gender` ∈ `male|female`, `date_of_birth` required, in the past, and not in the future. Ownership is enforced by resolving every record through `$request->user()->familyMembers()` — a foreign or missing id throws `ModelNotFoundException` and renders `404`, never 403. Lists are capped at `per_page` 50. Soft-deleted members disappear from every response.
- **Suspended users** are rejected on every authenticated request, not only at login.
- **Locale:** `Accept-Language: ar|en` sets the request locale (first match, fallback `APP_LOCALE`); `users.locale` drives queued/outgoing mail through `preferredLocale()`.

---

## Technical Constraints

- PHP 8.5 runtime, `composer.json` allows `^8.3`; PSR-12 via Pint; typed properties/params/returns; constructor promotion.
- Laravel 13 conventions: `#[Fillable]`/`#[Hidden]` model attributes, `bootstrap/app.php` middleware/exception configuration, `Socialite::fake()` in tests.
- Migrations must run on **both** MySQL 8 and SQLite `:memory:` — no vendor-specific DDL.
- Enums are `string` columns cast to backed enums; no MySQL `ENUM`.
- Every endpoint authorized server-side: `auth:sanctum` + scoped binding or policy. Frontend checks are cosmetic.
- Foreign-owned resources return **404**, never 403.
- No new dependency beyond Sanctum, Socialite and Scramble (all named in plan §3).
- **No forbidden tables.** Schema §10 bans `sessions`, `jobs` and `cache`, so local dev runs `SESSION_DRIVER=file`, `CACHE_STORE=file` and `QUEUE_CONNECTION=sync`; the default Laravel migrations for those tables were deleted. `failed_jobs` is kept because schema §4.20 requires it. Redis replaces the file/sync drivers at the Redis milestone, without a code change.

---

## Acceptance Criteria

1. `POST /api/v1/auth/register` creates a `patient`, logs them in, and sends a verification notification (`Notification::fake()` + `assertSentTo`).
2. Visiting the signed verification link sets `email_verified_at`; a tampered or expired link returns `403 INVALID_SIGNATURE` and does not verify.
3. Login succeeds for a verified and an unverified user; a suspended user gets `403 ACCOUNT_SUSPENDED`.
4. The 6th login attempt within a minute returns `429 TOO_MANY_REQUESTS`.
5. Forgot/reset works end to end; the same token fails on second use; a token older than 60 minutes fails.
6. `GET /me` returns the plan's `id, name, email, role, email_verified, locale, center_id, permissions` plus the additive `phone, date_of_birth, gender`; the permissions match the role map.
7. `PATCH /me`, `PUT /me/password`, `PATCH /me/email` enforce the current password; changing the email clears verification and re-sends the mail.
8. Google callback creates a patient, links to a verified existing account, and refuses an unverified existing account.
9. Family members: owner can list/create/show/update/delete; another user's member ID returns 404 on show/update/delete; validation rejects a bad relation, gender, or future birth date.
10. A route protected by the `verified` middleware returns `403 EMAIL_NOT_VERIFIED` for an unverified user.
11. Every error response has `message`, `code`, and (for validation) `errors`; every response carries `X-Request-ID`.
12. `vendor/bin/pint --dirty` is clean and the full Pest suite passes.
13. Migrations run cleanly with `migrate:fresh` on MySQL and on SQLite `:memory:`.

---

## Implementation Plan

Ordered; each task ends with a verification step. Foundation tasks (F) unblock feature tasks (T).

| # | Task | Key files | Verify |
|---|---|---|---|
| F1 | Install Sanctum, Socialite, Scramble; publish configs | `composer.json`, `config/{sanctum,cors,scramble}.php` | `composer show --direct`, `route:list` |
| F2 | Enums + permission map | `app/Enums/*`, `config/permissions.php` | `php -l`, unit test |
| F3 | Migrations: `centers`, `users` (renamed), `password_reset_tokens`, `family_members`, `failed_jobs`; factories | `database/migrations/*`, `database/factories/*` | `migrate:fresh` on MySQL + SQLite |
| F3b | Models: `User` (`MustVerifyEmail`, enum casts, `permissions()`, `preferredLocale()`, `isSuspended()`), `Center`, `FamilyMember` | `app/Models/*` | model unit test |
| F4 | `bootstrap/app.php`: api route file, middleware aliases, stateful API, exception envelope, JSON log channel, request id | `bootstrap/app.php`, `app/Http/Middleware/*`, `config/logging.php` | feature test on envelope + ping |
| F5 | Locale files `en`/`ar`, `SetLocale` middleware | `lang/**`, middleware | locale test |
| F6 | Base classes: `ApiException`, `ApiController` helpers, `UserResource` | `app/Exceptions`, `app/Http/Resources` | unit test |
| T1 | Registration + verification email | `Auth/RegisteredUserController`, `RegisterRequest` | registration test |
| T2 | Login / logout / suspended + throttling | `Auth/AuthenticatedSessionController`, `LoginRequest`, rate limiters | login + throttle tests |
| T3 | Email verification + resend + `verified` middleware | `Auth/VerifyEmailController`, `EmailVerificationNotificationController` | verification tests |
| T4 | Forgot / reset password + notification | `Auth/PasswordResetLinkController`, `NewPasswordController`, `ResetPassword` notification | reset tests |
| T5 | Google OAuth (web middleware routes) | `Auth/GoogleController`, `routes/auth_google.php`, `config/services.php` | Socialite fake tests |
| T6 | `/me` show/update, password, email | `ProfileController`, requests, resource | profile tests |
| T7 | Family members CRUD + scoped binding | `Family/FamilyMemberController`, requests, resource | family tests |
| T8 | Scramble export + route docs sanity | `config/scramble.php` | `scramble:export` |
| V | Phase verification: Pint, full Pest, migrate:fresh, manual smoke through Docker | — | see Verification |

---

## Verification

```bash
make artisan ARGS="migrate:fresh --seed"          # MySQL, schema order
make artisan ARGS="route:list --except-vendor"    # /api/v1 surface
docker compose exec backend php artisan test --compact
docker compose exec backend vendor/bin/pint --dirty --format agent
docker compose exec backend php artisan scramble:export --path=public/api/openapi.json
```

Manual smoke (curl through `http://localhost:8000`): `GET /up`, `GET /api/v1/ping`, `GET /sanctum/csrf-cookie`, register → verification mail in `storage/logs/laravel.log` (log mailer), login, `GET /api/v1/me`, add a family member, attempt another user's member (404).

Not available in this environment: real Google OAuth (no credentials) — covered by `Socialite::fake()`; MySQL parallel-request tests (Phase 3 concern).

---

## Risks and Open Questions

| Risk | Mitigation |
|---|---|
| Sanctum/Scramble may not support Laravel 13 | Verified at install; Scramble export is the only deferrable item |
| Cross-origin cookies in dev (`:3000` → `:8000`) | Same-site `localhost`, `SESSION_DOMAIN=null`, CORS with credentials; production is single-origin behind Caddy |
| Google flow needs a session but lives under `/api` | Routes registered in the `web` group via `bootstrap/app.php` `then:` so the OAuth `state` survives |
| Session driver is `file` until Redis lands | No forbidden tables; one env change moves to Redis |
| Permission strings are invented (plan only says "static map") | Fixed in `config/permissions.php`, documented here and in the final report so the frontend can adopt them |
| Google-only accounts (`password = null`) cannot satisfy `current_password` | Documented limitation: they set a password through "forgot password" first |

No blocking open questions remain after the approval gate.

### Independent review — findings resolved

One zero-context review was run against this specification before implementation.

| Finding | Resolution |
|---|---|
| `remember_token` is not in schema §4.1 | Column, factory value and "remember" behaviour removed |
| `/me/family-members/{id}` cannot use scoped implicit binding (no parent parameter) | Resolved through `$request->user()->familyMembers()->findOrFail($id)`; covered by 404 tests |
| The User model contract was missing from the plan | Added as task F3b (MustVerifyEmail, enum casts, `isSuspended()`, `permissions()`) |
| CORS was `*` without credentials | `supports_credentials: true` with an explicit `FRONTEND_URL` origin |
| `cache`/`jobs` tables are forbidden by schema §10 | Drivers moved to `file`/`sync`, default migrations deleted, `failed_jobs` kept |
| Google lookup order unspecified | Resolve by `google_id` → email → create |
| `INVALID_SIGNATURE` missing from the code table | Added, together with `INVALID_RESET_TOKEN` |
| Suspended-account enumeration | Credentials verified before status is checked |
| Tests should use `Notification::fake()` | Acceptance criterion 1 updated |

---

## Decisions and Assumptions

1. **Patient-only registration in Phase 1.** Center registration belongs to Phase 2 because `center_staff` requires `center_id` and the approval flow.
2. **Registration signs the user in** and sends the verification mail; unverified users can still browse and manage their profile.
3. **Verification links point at the API**, not the frontend: the signed URL is verified server-side, then redirects to `FRONTEND_URL/auth/verified`. No session is needed, so the link works from any browser.
4. **Google routes use the `web` middleware group** so OAuth `state` is preserved; they still live under `/api/v1/auth/google`.
5. **Mail is sent synchronously in Phase 1.** `QUEUE_CONNECTION=sync` and no worker runs in compose, so queued notifications (verification, reset) are delivered inline. Notifications stay `ShouldQueue`; switching to Redis + a worker in Phase 3 requires no code change.
6. **`file` session/cache drivers, no `sessions`/`cache`/`jobs` tables** (schema §10). `failed_jobs` is kept per schema §4.20.
7. **Soft delete on family members**; `MEMBER_HAS_FUTURE_APPOINTMENTS` lands in Phase 3 with the appointments table.
8. **Family routes live under `/me/family-members`** to match the contract's `/me/subscriptions` style.
9. **English validation messages come from the framework defaults**; Arabic is provided explicitly. Auth/password copy exists in both languages.
10. **`center_id` is always `null` for patients** and only populated for `center_staff` (enforced by the registration path and a model-level guard used in Phase 2).
11. **`phpunit.xml` forces `APP_ENV=testing` and the test database through both `<env force>` and `<server>`.** The Docker container exports `APP_ENV=local` and `DB_CONNECTION=mysql` into `$_SERVER`, and Laravel's `Env` repository reads `$_SERVER` before `$_ENV` — without the `<server>` entries the suite silently ran against the dev MySQL database as `APP_ENV=local`, which also disabled the CSRF bypass and made every stateful POST return 419.
12. **`public/api/openapi.json` is a generated artifact and is git-ignored.** CI will regenerate it (`scramble:export`) once the pipeline exists.

---

## Status

Phase 1 is complete. Tasks F1–F6, F3b and T1–T8 are implemented; V (phase verification) was run.

### Evidence (executed in the Docker stack)

| Check | Result |
|---|---|
| `php artisan test --compact` | 67 passed, 243 assertions |
| `vendor/bin/pint --dirty --format agent` | clean after auto-fix |
| `php artisan migrate:fresh --force` (MySQL 8.4) | 5 migrations applied in schema order |
| `php artisan route:list --except-vendor` | 20 routes; 19 API operations |
| `php artisan scramble:export --path=public/api/openapi.json` | 15 paths / 19 operations exported |
| `php artisan scramble:analyze` | "Matched 19 routes for API [default], producing 19 OpenAPI operations" |
| Curl smoke: `GET /up`, `GET /api/v1/ping`, `GET /sanctum/csrf-cookie` | 200 / 200 / 204 with `X-Request-ID`, CORS + credentials headers |
| Curl smoke: register → `GET /me` → create/list family member | 201 / 200 / 201 / 200 with session cookie auth |
| Curl smoke: second user reads another user's member | `404 NOT_FOUND` |
| Curl smoke: unauthenticated `GET /me` | `401 UNAUTHENTICATED` |
| Curl smoke: Arabic validation | `422 VALIDATION_ERROR` with Arabic messages |
| Curl smoke: 6th bad login in a minute | `429 TOO_MANY_REQUESTS` |
| Curl smoke: verification link from the mail log | `302 → FRONTEND_URL/auth/verified?status=verified`; `email_verified_at` set |
| JSON log channel | one JSON object per line in `storage/logs/laravel.log` |

### Checks not run

- **Real Google OAuth**: no credentials in this environment; covered by 11 `Socialite::fake()` tests.
- **MySQL concurrency test** for booking: not a Phase 1 concern (Phase 3).
- **Larastan**: not installed yet (deliberately deferred).
- **GitHub Actions**: deferred with the rest of the CI work.
- **Laravel Boost MCP tools**: the MCP server runs on the host, whose `vendor/` is stale (the container uses a named volume), so Boost tools fail to boot the app. Verification used `docker compose exec` instead.

