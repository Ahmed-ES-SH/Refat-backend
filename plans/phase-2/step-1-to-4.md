# Phase 2 - Steps 1 to 4

## Read first (context capsule)

- **Phase goal**: Build center self-registration (pending → admin approve), doctor management, weekly availability rules with days off, public directory with filtering, and on-the-fly slot computation.
- **Already done before this file**: 
  - Phase 1 complete: auth (register/login/verify/reset), Google OAuth, profile, family members CRUD
  - Migrations: `centers`, `users`, `password_reset_tokens`, `family_members` exist
  - Models: `User`, `Center`, `FamilyMember` exist with relationships
  - Routes: `/api/v1/auth/*`, `/api/v1/me`, `/api/v1/me/family-members`
  - Enums: `UserRole`, `UserStatus`, `Locale`, `Gender`, `FamilyRelation`
  - Policies: Basic authorization infrastructure likely exists
- **Key decisions that apply here**:
  - Specialty = PHP enum `Specialty` (12 slugs), NOT a table. `doctors.specialty` is string(60) + index.
  - Slot computation is on-the-fly: rules for weekday (center tz → UTC) − days_off − active appointments − past times.
  - `GET /doctors/{id}/slots?date=` returns `[{ starts_at, ends_at }]` in UTC.
  - Center registration creates `center_staff` user + center in `pending` status.
  - Staff scoping by `users.center_id` + Policies; center profile + logo upload (public disk).
  - Public directory: filter by city, specialty, name; page-based (per_page ≤ 50); only `active` centers; cached with short TTL + bust on change.
- **Files to open before starting**:
  - `docs/rifad-database-schema.md` (tables: doctors, availability_rules, days_off, centers)
  - `app/Enums/` (need to add Specialty enum)
  - `database/migrations/` (need 4 new migrations)
  - `app/Models/` (need Doctor, AvailabilityRule, DayOff models)
  - `app/Http/Controllers/Api/V1/` (need Centers, Doctors, Availability, Directory controllers)
  - `routes/api_v1.php` (need to add Phase 2 routes)

## Scope

**In scope (Steps 1-4)**:
1. `Specialty` PHP enum + migration for `doctors.specialty` column
2. Migrations: `doctors`, `availability_rules`, `days_off` (centers already exists)
3. Models: `Doctor`, `AvailabilityRule`, `DayOff` with relationships and casts
4. Center registration endpoint (public), admin approve/suspend endpoints (minimal admin)

**Out of scope (Steps 5-8 / next session)**:
- Doctor CRUD, availability CRUD, days_off CRUD
- Public directory with filtering + caching
- Slot computation endpoint (`GET /doctors/{id}/slots`)
- Logo upload (public disk)
- Unit tests for slot computation
- OpenAPI export and verification

## Steps

### Step 1 - Specialty enum + doctors migration + model

- **Goal**: Create the `Specialty` PHP backed enum (12 slugs from schema §9) and the `doctors` migration + model with `specialty` string column (not FK).
- **Files**:
  - Create: `app/Enums/Specialty.php`
  - Create: `database/migrations/2026_11_19_000006_create_doctors_table.php` (sorts after family_members)
  - Create: `app/Models/Doctor.php`
  - Modify: `app/Models/Center.php` (add `doctors` relationship)
- **Tasks**:
  - [ ] Create `Specialty` enum with 12 cases: `general_medicine`, `pediatrics`, `dentistry`, `dermatology`, `gynecology`, `orthopedics`, `cardiology`, `ophthalmology`, `ent`, `psychiatry`, `nutrition`, `physiotherapy` (values = slugs from schema §9)
  - [ ] Create `doctors` migration with columns per schema §4.6: `id`, `center_id` (FK → centers, restrictOnDelete), `specialty` (string(60), index), `name`, `bio` (nullable), `photo_path` (nullable), `is_active` (boolean, default true), `timestamps`
  - [ ] Add indexes: `(center_id, is_active)`, `(specialty)`
  - [ ] Create `Doctor` model with `$fillable`, casts (`is_active` → boolean, `specialty` → `Specialty::class`), relationships: `center()` (BelongsTo), `availabilityRules()` (HasMany), `daysOff()` (HasMany), `appointments()` (HasMany)
  - [ ] Add `doctors()` HasMany to `Center` model
  - [ ] Run migration to verify
- **Backend notes**: 
  - `specialty` is a string column cast to `Specialty` enum, NOT a foreign key to a `specialties` table (per plan review item 3).
  - Migration must have working `down()`.
- **Security**: Doctor data scoped to center via Policy (later step). Model itself just defines relationships.
- **Done when**: Migration runs clean; `Doctor::factory()->create()` works; `Specialty::cases()` returns 12 values.
- **Verify**: `php artisan migrate:fresh --seed` (if seeders exist) or `php artisan migrate` + `php artisan tinker --execute 'App\Models\Doctor::factory()->create(); echo "OK";'`

### Step 2 - Availability rules + days off migrations + models

- **Goal**: Create `availability_rules` and `days_off` tables with models, following schema §4.7 and §4.8.
- **Files**:
  - Create: `database/migrations/2026_11_19_000007_create_availability_rules_table.php`
  - Create: `database/migrations/2026_11_19_000008_create_days_off_table.php`
  - Create: `app/Models/AvailabilityRule.php`
  - Create: `app/Models/DayOff.php`
  - Modify: `app/Models/Doctor.php` (add relationships)
- **Tasks**:
  - [ ] Create `availability_rules` migration: `id`, `doctor_id` (FK → doctors, cascadeOnDelete), `weekday` (unsignedTinyInteger, 0=Sunday..6=Saturday), `start_time` (time), `end_time` (time), `slot_minutes` (unsignedSmallInteger, default 30), `timestamps`. Index: `(doctor_id, weekday)`.
  - [ ] Create `days_off` migration: `id`, `doctor_id` (FK → doctors, cascadeOnDelete), `date` (date), `reason` (string(150), nullable), `timestamps`. Unique index: `(doctor_id, date)`.
  - [ ] Create `AvailabilityRule` model: `$fillable`, casts (`weekday` → int, `slot_minutes` → int, `start_time`/`end_time` → string or Carbon), `doctor()` BelongsTo.
  - [ ] Create `DayOff` model: `$fillable`, casts (`date` → date), `doctor()` BelongsTo.
  - [ ] Add `availabilityRules()` and `daysOff()` HasMany to `Doctor` model (already stubbed in Step 1).
  - [ ] Run migrations to verify.
- **Backend notes**:
  - `start_time`/`end_time` are in center's local timezone (stored as `time` without timezone).
  - `weekday` matches Carbon `dayOfWeek` (0=Sunday).
  - App rules: `end_time > start_time`; window length multiple of `slot_minutes`; no overlapping windows for same doctor+weekday (enforced in FormRequest later).
- **Security**: Scoped to doctor's center via Policy (later).
- **Done when**: Migrations run clean; factories create valid records; relationships load correctly.
- **Verify**: `php artisan migrate:fresh` + `php artisan tinker --execute '$r = App\Models\AvailabilityRule::factory()->create(); echo $r->doctor->center->name;'` (should not error)

### Step 3 - Center registration + admin approve/suspend endpoints

- **Goal**: Public center registration (creates center_staff user + center in pending), admin approve/suspend endpoints. Minimal admin here; full admin in Phase 6.
- **Files**:
  - Create: `app/Http/Controllers/Api/V1/Centers/CenterRegistrationController.php`
  - Create: `app/Http/Controllers/Api/V1/Admin/CenterAdminController.php` (approve/suspend only)
  - Create: `app/Http/Requests/CenterRegistrationRequest.php`
  - Create: `app/Http/Requests/AdminCenterActionRequest.php`
  - Create: `app/Http/Resources/CenterResource.php`
  - Create: `app/Http/Resources/CenterDirectoryResource.php` (for public directory)
  - Modify: `routes/api_v1.php` (add routes)
  - Modify: `app/Models/User.php` (ensure center_staff role works with center_id)
- **Tasks**:
  - [ ] Create `CenterRegistrationRequest`: validate name, description, phone, email, city, address, license_no, country_code, timezone, staff_name, staff_email, staff_password. All required per schema §4.4 except optional fields.
  - [ ] Create `CenterRegistrationController@store`: 
    - Create center with `status=pending`, `approved_at=null`
    - Create user with `role=center_staff`, `center_id=center.id`, `email=staff_email`, `name=staff_name`, email_verified_at=null (self-registration: unverified until the registrant proves ownership of the address — audit F1/H2), status=active; send the verification email after the transaction commits
    - Return `CenterResource` with 201
  - [ ] Create `AdminCenterActionRequest`: validate `action` in ['approve', 'suspend'] (for suspend, optional `reason`)
  - [ ] Create `CenterAdminController`:
    - `approve(Center $center)`: only admin; set `status=active`, `approved_at=now()`; return updated resource
    - `suspend(Center $center)`: only admin; set `status=suspended`; return updated resource
  - [ ] Create `CenterResource` (for admin/owner): includes all fields + staff info
  - [ ] Create `CenterDirectoryResource` (public): only public fields (name, description, logo_path, phone, email, city, address, latitude, longitude, specialty list via doctors)
  - [ ] Add routes in `routes/api_v1.php`:
    - `POST /centers/register` (public, throttled)
    - `POST /admin/centers/{center}/approve` (admin only)
    - `POST /admin/centers/{center}/suspend` (admin only)
    - `GET /centers/{center}` (public, shows directory resource)
  - [ ] Add Policy: `CenterPolicy` with `viewAny` (public), `register` (public), `approve/suspend` (admin), `update` (center_staff of that center)
- **Backend notes**:
  - Center registration is public but throttled (`throttle:center-register`).
  - Staff user is created **unverified** and receives the standard verification email (decided 2026-11-24, audit F1/H2): this endpoint is self-registration, so the old "center admin creates them" rationale did not apply. Seeders may still create verified staff directly.
  - `CenterPolicy` enforces: patients/other centers get 404 on foreign centers; staff only see their own center.
- **Security**: 
  - Rate limit on registration.
  - Admin endpoints require `admin` role (Policy).
  - No mass assignment on sensitive fields (status, approved_at).
- **Done when**: 
  - `POST /api/v1/centers/register` creates center+staff, returns 201 with center data.
  - Admin can approve → center status=active, appears in directory.
  - Admin can suspend → center status=suspended.
  - Non-admin gets 403 on approve/suspend.
- **Verify**: 
  - `php artisan test --filter=CenterRegistrationTest` (write test)
  - Manual: `curl -X POST /api/v1/centers/register ...` → 201; admin token → approve → 200

### Step 4 - Center profile + logo upload (public disk)

- **Goal**: Authenticated center_staff can view/update their center profile and upload logo to public disk.
- **Files**:
  - Create: `app/Http/Controllers/Api/V1/Centers/CenterProfileController.php`
  - Create: `app/Http/Requests/UpdateCenterProfileRequest.php`
  - Modify: `routes/api_v1.php` (add profile routes)
  - Modify: `app/Policies/CenterPolicy.php` (add `update` policy)
- **Tasks**:
  - [ ] Create `UpdateCenterProfileRequest`: validate optional fields (description, phone, email, address, license_no, city, country_code, timezone). Exclude: name, status, approved_at, logo_path (separate upload).
  - [ ] Create `CenterProfileController`:
    - `show()`: returns `CenterResource` for authenticated user's center (via `auth()->user()->center`)
    - `update(UpdateCenterProfileRequest $request)`: updates center, returns `CenterResource`
    - `uploadLogo(Request $request)`: validate file (image, max 2MB, mime: jpeg,png,webp), store on `public` disk with random name, update `logo_path`, return `CenterResource`
    - `deleteLogo()`: delete file from public disk, set `logo_path=null`, return `CenterResource`
  - [ ] Add routes in `routes/api_v1.php` (inside auth:sanctum + active middleware):
    - `GET /center/profile` → `show`
    - `PATCH /center/profile` → `update`
    - `POST /center/profile/logo` → `uploadLogo`
    - `DELETE /center/profile/logo` → `deleteLogo`
  - [ ] Update `CenterPolicy@update`: only center_staff of that center (check `user->center_id === center->id`)
  - [ ] Ensure `public` disk is configured and `php artisan storage:link` works (dev setup).
- **Backend notes**:
  - Logo stored on `public` disk → accessible via `/storage/` URL (Caddy serves `/storage/*` to Laravel per plan §4).
  - File validation: size, MIME sniffing (use `File::getMimeType()`), extension allowlist, random filename.
  - `logo_path` stores relative path from public disk root.
- **Security**: 
  - Only center_staff of that center can update/upload.
  - File upload validation per security checklist §5.20.
- **Done when**: 
  - Staff can GET/PATCH their center profile.
  - Staff can upload logo → file appears in `storage/app/public/...`, `logo_path` updated, accessible via URL.
  - Staff can delete logo → file removed, `logo_path` null.
  - Other users get 404 on these endpoints.
- **Verify**: 
  - `php artisan test --filter=CenterProfileTest`
  - Manual: login as staff → upload logo → verify URL works.

## Session checklist

- [x] All steps above done and verified
- [x] Tests added/updated and passing (113 passed / 408 assertions, Docker — includes the audit-fix round below)
- [x] No files touched outside scope
- [x] `_index.md` status updated and progress log appended

## Handoff (fill at end of session)

- Completed:
  - Steps 1–4 all implemented and verified: `Specialty` enum, `doctors`/`availability_rules`/`days_off` migrations + models + factories, center registration (public, throttled 3/min) + admin approve/suspend, center profile show/update + logo upload/delete on the public disk.
  - Gap fixes after review (2026-11-24): added `DELETE /api/v1/center/profile/{center}/logo` (`CenterProfileController::deleteLogo`, idempotent, `can:update,center`), removed `name` from `UpdateCenterProfileRequest` (staff cannot rename an approved center), added `throttle:sensitive` (6/min) to the profile `PATCH` (upload path, security §5.20), ran `storage:link`, re-exported OpenAPI.
  - Audit report + work plan saved at `audits/phase-2-steps-1-to-4-review-and-plan.md`.
  - Evidence: full suite `106 passed (382 assertions)`; Pint clean; `public/docs/api/openapi.json` now contains `/v1/center/profile/{center}`, `/v1/center/profile/{center}/logo`, `/v1/centers/register`.
- Audit-fix round (2026-11-24, implementing audit revision 2 — H2/H3/H4/H6/H7):
  - **H3** — `docs/rifad-database-schema.md` amended for the `specialties` removal: dated amendment note, `Specialty` row in the §2 enum registry, §3 table map, §4.5 tombstone ("no table, do not create"), §4.6 `specialty` `string(60)` + index `(specialty)`, §5 relationship, §8 migration order, §10 Do-NOT-create. The contradictory `specialties` entry in `_index.md`'s migration-order line was fixed too.
  - **H4a** — logo swap is now save-then-delete: `StoreCenterLogo` only writes the file, `CenterProfileController::update()` saves the row first and removes the previous file after, and a failed save removes the just-written file instead of leaving it orphaned. `deleteLogo()` nulls the column before deleting the file.
  - **H4b** — added a test that uploads real PNG bytes under `payload.txt` and asserts the stored extension is `.png`. Correction to the audit: the test it specified (PNG bytes under a non-image name → *rejected*) cannot prove sniffing, because `Illuminate\Http\Testing\File` derives its MIME type from the file name, so that payload is rejected by the filename-based `mimes` rule either way. A real `UploadedFile` (content-sniffed by Symfony) is required to make the test able to fail.
  - **H4c** — `timezone` validated with Laravel's `timezone` rule (IANA list) on registration and profile update. Second correction: the audit's `"Asia/Riyadh "` example never reaches validation — Laravel's `TrimStrings` middleware strips the trailing space — so the tests use `UTC+3`.
  - **H6** — `CenterResource.staff` is now present on all three call sites (profile show/update/delete load `staff`); the public directory still uses `CenterDirectoryResource` (no staff exposure).
  - **H2** — center registration now creates the staff account **unverified** and sends `VerifyEmail` after the transaction commits (was `email_verified_at = now()` on a public endpoint). Seeded demo staff are created directly and stay verified.
  - Decisions taken (2026-11-24): **H2** = unverified + verification email; **H7** = `verified` middleware deliberately deferred to the booking/billing phase (schema §4.1 gates booking/buying, which do not exist yet — recorded so it is not mistaken for an oversight); **H6** = `staff` always present; **H9** = still no commits (earlier decision upheld).
  - Evidence: full suite `113 passed (408 assertions)` (Docker); Pint clean; `scramble:export` re-run — 22 paths matching `route:list` exactly.
  - Still open: H1 (`Doctor::appointments()`, booking phase), H5 (Steps 5–8: doctor/availability CRUD, slot computation, directory caching + bust-on-change, schema §4.7 app rules, first `AvailabilityRule`/`DayOff` tests), H8 (standing rule: keep the schema doc in sync with plan deltas), and plan §6's other unapplied deltas (`membership_cards`, `membership_card_members`, `newsletter_subscribers` removal — not yet written back to the schema doc).
- Deviations from plan and why:
  - `availability_rules.weekday` is cast to a new `Weekday` enum (not `int`) — type-safe, same 0=Sunday convention.
  - No `AdminCenterActionRequest`: two separate admin routes with `can:approve/suspend,center` middleware instead of one `action` param route (functionally equivalent, endpoints as planned).
  - `CenterPolicy` has `view/approve/suspend/update` but no `viewAny`/`register` — public directory and registration authorize inline.
  - Profile routes use `/center/profile/{center}` + policy instead of `/center/profile` derived from `auth()->user()->center`; logo upload stays merged into `PATCH` as a `logo` file field (only `DELETE .../logo` added as a separate route).
  - Plan text says slug `general_medicine`; schema §9 (source of truth) says `general-medicine` — code follows the schema, plan text has a typo.
  - Directory (`GET /centers`, `GET /centers/{center}`, `DirectoryFilterRequest`, `CenterDirectoryResource`) was built early although listed under Steps 5–8; **caching not added yet** — step 5–8 session must add short TTL + bust on change.
  - Step-4 task checkboxes left as written where implementation differs; deviations listed above are authoritative.
- Notes the next session must know:
  - **`Doctor::appointments()` does NOT exist yet** — no `Appointment` model/table; add it in the booking phase.
  - No git commits were made (user decision, reaffirmed 2026-11-24); 57 paths remain uncommitted (17 modified tracked + 40 untracked).
  - `public/storage` symlink now exists; logo files live under `centers/{id}/logo_<random>.<ext>` on the `public` disk.
  - Profile update is rate-limited (`throttle:sensitive`, 6/min per user) — frontend should handle 429.
  - Baseline for the next session: 113 tests green.
- Next file to run: `plans/phase-2/step-5-to-8.md`