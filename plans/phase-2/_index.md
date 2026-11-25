# Phase 2 - Centers, doctors, availability, directory

## Goal

Build center self-registration (pending → admin approve), doctor management, weekly availability rules with days off, public directory with filtering, and on-the-fly slot computation. All behind Policies with center isolation.

## Source

Main plan: `docs/rifad-backend-plan.md`, section: **Phase 2 — Centers, doctors, availability, directory (weeks 4–5)**

## Session Files (execute in order)

| #   | File                    | Steps | Status | Depends on       |
| --- | ----------------------- | ----- | ------ | ---------------- |
| 1   | step-1-to-4.md          | 1-4   | DONE   | Phase 1 done     |
| 2   | step-5-to-8.md          | 5-8   | TODO   | session 1        |

## Status legend

TODO | IN PROGRESS | DONE | BLOCKED

## Global decisions (apply to ALL session files)

- **Stack**: Laravel 13, PHP 8.5, MySQL 8 (prod) / SQLite :memory: (tests), Pest, Pint, API Resources, Form Requests, Policies.
- **Auth**: Sanctum SPA cookie auth. `auth:sanctum` + `active` middleware on all protected routes.
- **API conventions**: `/api/v1` prefix. JSON responses via API Resources. Error envelope `{ message, code, errors }` with stable codes.
- **Pagination**: page-based `{ data, meta: { page, per_page, total } }`, `per_page` max 50.
- **Dates**: ISO-8601 UTC in API; `center.timezone` (IANA) for display and availability computation.
- **Enums**: PHP backed enums in `app/Enums/` cast from `string` columns. Never MySQL ENUM.
- **Money**: `unsignedInteger` minor units + `char(3)` currency (not used in Phase 2).
- **Files**: Local disks only. `public` disk for center logos. Signed URLs not needed here (chat is Phase 5).
- **404 not 403**: Foreign center/doctor access returns 404 to avoid ID enumeration.
- **OpenAPI**: After each session, run `php artisan scramble:export` and verify new endpoints at `/docs/api`.
- **Migration order** (from schema §8): `centers` (done) → `users` (done) → `password_reset_tokens` (done) → `family_members` (done) → `doctors` (done) → `availability_rules` (done) → `days_off` (done) → ...
- **Specialty**: Use PHP enum `Specialty` (12 fixed slugs from schema §9), NOT a `specialties` table. `doctors.specialty` is a `string(60)` column with index.

## Assumptions / Open questions

- **Admin user for approval**: Phase 1 should have seeded an admin. If not, we'll need a seeder or manual DB insert before testing approval flow.
- **Logo upload**: Uses `public` disk. Need `php artisan storage:link` in setup.
- **Timezone handling**: Gulf zones are DST-free (Asia/Riyadh, Asia/Dubai, etc.). Slot computation converts center-local times to UTC for storage/comparison.
- **Rate limiting**: Center registration endpoint needs throttling (e.g., `throttle:center-register`). Admin approve/suspend are low-traffic; no special limit needed.

## Progress log

<!-- the implementing agent appends one line per finished step: date - step - what changed - commit -->

- 2026-11-19 - Step 1 - `Specialty` enum (12 slugs, schema §9), `doctors` migration + `Doctor` model + `Center::doctors()` - no commit (per user)
- 2026-11-19 - Step 2 - `availability_rules` + `days_off` migrations, `AvailabilityRule`/`DayOff` models, `Weekday` enum - no commit (per user)
- 2026-11-19 - Step 3 - center registration (`RegisterCenterWithStaff`), admin approve/suspend, `CenterPolicy`, `CenterResource`, throttled public routes - no commit (per user)
- 2026-11-24 - Step 4 - center profile show/update, logo upload (`StoreCenterLogo`), `DELETE /center/profile/{center}/logo` added, `name` excluded from profile update, `throttle:sensitive` on profile PATCH, `storage:link` run, OpenAPI re-exported (106 tests passing) - no commit (per user)