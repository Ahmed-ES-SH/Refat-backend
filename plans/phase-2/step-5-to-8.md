# Phase 2 - Steps 5 to 8

## Read first (context capsule)

- **Phase goal**: Build center self-registration (pending → admin approve), doctor management, weekly availability rules with days off, public directory with filtering, and on-the-fly slot computation.
- **Already done before this file (Steps 1-4 complete)**:
  - `Specialty` enum created (12 slugs)
  - Migrations: `doctors`, `availability_rules`, `days_off` created and run
  - Models: `Doctor`, `AvailabilityRule`, `DayOff` with relationships
  - Center registration endpoint (public), admin approve/suspend endpoints
  - Center profile + logo upload (public disk)
  - Policies: `CenterPolicy` with viewAny, register, approve, suspend, update
  - Routes: `/centers/register`, `/admin/centers/{center}/approve|suspend`, `/center/profile*`, `/centers/{center}`
- **Key decisions that apply here**:
  - Doctor CRUD scoped to center_staff's center via Policy
  - Availability rules: multiple windows per weekday allowed, no overlaps, slot_minutes default 30
  - Days off: unique per doctor+date
  - Public directory: filter by city, specialty, name; page-based (per_page ≤ 50); only `active` centers; cached with short TTL (e.g., 60s) + bust on change
  - Slot computation: `GET /doctors/{id}/slots?date=` → expand rules for weekday (center tz → UTC) − days_off − active appointments − past times
  - OpenAPI: run `php artisan scramble:export` after this session
- **Files to open before starting**:
  - `app/Http/Controllers/Api/V1/` (need Doctors, Availability, Directory controllers)
  - `app/Http/Requests/` (need Doctor, AvailabilityRule, DayOff FormRequests)
  - `app/Http/Resources/` (need DoctorResource, AvailabilityRuleResource, DayOffResource, Directory list resource)
  - `app/Policies/` (need DoctorPolicy, AvailabilityRulePolicy, DayOffPolicy)
  - `routes/api_v1.php` (add remaining Phase 2 routes)
  - `database/seeders/` (may need Specialty seeder for directory filtering)

## Scope

**In scope (Steps 5-8)**:
5. Doctor CRUD (center_staff only, scoped to their center)
6. Availability rules CRUD + validation (no overlaps, valid windows)
7. Days off CRUD
8. Public directory (filter, paginate, cache) + Slot computation endpoint (`GET /doctors/{id}/slots?date=`)
9. Unit tests for slot computation (multiple windows, days off, timezone, past times)
10. OpenAPI export and verification

**Out of scope**: 
- Phase 3 (booking, appointments)
- Phase 4 (billing, Stripe)
- Phase 5 (chat)

## Steps

### Step 5 - Doctor CRUD (center_staff scoped)

- **Goal**: Center staff can CRUD doctors for their own center only. Admin can view all.
- **Files**:
  - Create: `app/Http/Controllers/Api/V1/Centers/DoctorController.php`
  - Create: `app/Http/Requests/StoreDoctorRequest.php`
  - Create: `app/Http/Requests/UpdateDoctorRequest.php`
  - Create: `app/Http/Resources/DoctorResource.php`
  - Create: `app/Http/Resources/DoctorListResource.php` (lighter for index)
  - Create: `app/Policies/DoctorPolicy.php`
  - Modify: `routes/api_v1.php`
  - Modify: `app/Providers/AuthServiceProvider.php` (register policy)
- **Tasks**:
  - [ ] Create `StoreDoctorRequest`: validate `name` (required, max 120), `specialty` (required, in: Specialty::values()), `bio` (nullable, max 5000), `photo_path` (nullable, image, max 2MB), `is_active` (boolean, default true). No `center_id` (set from auth user).
  - [ ] Create `UpdateDoctorRequest`: same fields, all optional, `specialty` still validated if present.
  - [ ] Create `DoctorResource`: includes all fields + `center` (minimal), `availability_rules` (optional, loaded when needed), `days_off` (optional).
  - [ ] Create `DoctorListResource`: minimal fields for index (id, name, specialty, is_active, photo_path).
  - [ ] Create `DoctorController`:
    - `index()`: paginated list of doctors for auth user's center (center_staff) or all (admin). Use `DoctorListResource::collection`.
    - `store(StoreDoctorRequest $request)`: create doctor with `center_id = auth()->user()->center_id`, return `DoctorResource` 201.
    - `show(Doctor $doctor)`: authorize → return `DoctorResource`.
    - `update(UpdateDoctorRequest $request, Doctor $doctor)`: authorize → update → return `DoctorResource`.
    - `destroy(Doctor $doctor)`: authorize → soft delete? No, `is_active = false` (deactivate per schema). Return 204.
  - [ ] Create `DoctorPolicy`:
    - `viewAny`: center_staff (own center) or admin
    - `view`: center_staff (own center) or admin → else 404
    - `create`: center_staff or admin
    - `update`: center_staff (own center) or admin → else 404
    - `delete`: center_staff (own center) or admin → else 404
  - [ ] Register policy in `AuthServiceProvider`.
  - [ ] Add routes in `routes/api_v1.php` (inside auth:sanctum + active):
    - `GET /center/doctors` → index
    - `POST /center/doctors` → store
    - `GET /center/doctors/{doctor}` → show
    - `PATCH /center/doctors/{doctor}` → update
    - `DELETE /center/doctors/{doctor}` → destroy
    - Also public: `GET /doctors/{doctor}` (for directory slot computation) → but this needs to check center is active
- **Backend notes**:
  - `specialty` stored as string (enum value), cast to `Specialty` in model.
  - `center_id` auto-set from authenticated staff user.
  - Photo upload to `public` disk (like center logo) - optional for now, can skip if complex.
  - Deactivate = `is_active = false`, not delete (FK restrictOnDelete on appointments).
- **Security**: 
  - Policy enforces center isolation (404 for foreign centers).
  - Only center_staff of that center or admin can modify.
  - Validation on specialty enum values.
- **Done when**: 
  - Staff can CRUD doctors for their center.
  - Admin can see all doctors.
  - Other staff get 404 on foreign doctors.
  - Invalid specialty rejected.
- **Verify**: 
  - `php artisan test --filter=DoctorCrudTest`
  - Manual: login as staff → create doctor → list → update → deactivate.

### Step 6 - Availability rules CRUD

- **Goal**: Center staff manage weekly recurring availability windows for their doctors.
- **Files**:
  - Create: `app/Http/Controllers/Api/V1/Centers/AvailabilityRuleController.php`
  - Create: `app/Http/Requests/StoreAvailabilityRuleRequest.php`
  - Create: `app/Http/Requests/UpdateAvailabilityRuleRequest.php`
  - Create: `app/Http/Resources/AvailabilityRuleResource.php`
  - Create: `app/Policies/AvailabilityRulePolicy.php`
  - Modify: `routes/api_v1.php`
  - Modify: `AuthServiceProvider.php`
- **Tasks**:
  - [ ] Create `StoreAvailabilityRuleRequest`: validate `doctor_id` (required, exists:doctors, belongs to auth user's center), `weekday` (required, integer 0-6), `start_time` (required, format H:i), `end_time` (required, format H:i, after start_time), `slot_minutes` (integer, min 10, max 120, default 30). Custom validation: `end_time > start_time`, `(end_time - start_time) % slot_minutes === 0`, no overlapping windows for same doctor+weekday.
  - [ ] Create `UpdateAvailabilityRuleRequest`: same, all optional, `doctor_id` not changeable.
  - [ ] Create `AvailabilityRuleResource`: all fields + computed `slots_count` (for info).
  - [ ] Create `AvailabilityRuleController`:
    - `index(Doctor $doctor)`: list rules for doctor (auth: staff of doctor's center or admin)
    - `store(StoreAvailabilityRuleRequest $request)`: create rule, return resource 201
    - `show(AvailabilityRule $rule)`: authorize → return resource
    - `update(UpdateAvailabilityRuleRequest $request, AvailabilityRule $rule)`: authorize → update → return resource
    - `destroy(AvailabilityRule $rule)`: authorize → delete → 204
  - [ ] Create `AvailabilityRulePolicy`: 
    - All actions: user must be center_staff of the rule's doctor's center, or admin. Else 404.
  - [ ] Register policy.
  - [ ] Add routes (inside auth:sanctum + active):
    - `GET /center/doctors/{doctor}/availability-rules` → index
    - `POST /center/doctors/{doctor}/availability-rules` → store
    - `GET /center/availability-rules/{rule}` → show
    - `PATCH /center/availability-rules/{rule}` → update
    - `DELETE /center/availability-rules/{rule}` → destroy
- **Backend notes**:
  - Overlap check: for same `doctor_id` and `weekday`, no two windows where `start_time < existing.end_time AND end_time > existing.start_time`.
  - `start_time`/`end_time` stored as `time` (center local time). API accepts/sends `H:i` format.
  - Slot computation uses these rules (Step 8).
- **Security**: Policy enforces center isolation. Validation prevents invalid windows.
- **Done when**: 
  - Staff can CRUD availability rules for their doctors.
  - Overlapping windows rejected.
  - Invalid times (end ≤ start, not multiple of slot_minutes) rejected.
  - Other centers get 404.
- **Verify**: 
  - `php artisan test --filter=AvailabilityRuleTest`
  - Manual: create overlapping rules → 422; valid rule → 201.

### Step 7 - Days off CRUD

- **Goal**: Center staff manage full-day exceptions (holidays, leave) for their doctors.
- **Files**:
  - Create: `app/Http/Controllers/Api/V1/Centers/DayOffController.php`
  - Create: `app/Http/Requests/StoreDayOffRequest.php`
  - Create: `app/Http/Requests/UpdateDayOffRequest.php`
  - Create: `app/Http/Resources/DayOffResource.php`
  - Create: `app/Policies/DayOffPolicy.php`
  - Modify: `routes/api_v1.php`
  - Modify: `AuthServiceProvider.php`
- **Tasks**:
  - [ ] Create `StoreDayOffRequest`: validate `doctor_id` (required, exists:doctors, belongs to auth user's center), `date` (required, date, after_or_equal:today), `reason` (nullable, max 150). Unique check: `doctor_id+date` (DB unique index also enforces).
  - [ ] Create `UpdateDayOffRequest`: same, `doctor_id` not changeable, `date` optional.
  - [ ] Create `DayOffResource`: all fields.
  - [ ] Create `DayOffController`:
    - `index(Doctor $doctor)`: list days off for doctor
    - `store(StoreDayOffRequest $request)`: create, return resource 201
    - `show(DayOff $dayOff)`: authorize → return resource
    - `update(UpdateDayOffRequest $request, DayOff $dayOff)`: authorize → update → return resource
    - `destroy(DayOff $dayOff)`: authorize → delete → 204
  - [ ] Create `DayOffPolicy`: same as AvailabilityRulePolicy (center_staff of doctor's center or admin).
  - [ ] Register policy.
  - [ ] Add routes (inside auth:sanctum + active):
    - `GET /center/doctors/{doctor}/days-off` → index
    - `POST /center/doctors/{doctor}/days-off` → store
    - `GET /center/days-off/{dayOff}` → show
    - `PATCH /center/days-off/{dayOff}` → update
    - `DELETE /center/days-off/{dayOff}` → destroy
- **Backend notes**:
  - `date` is center-local calendar date (no timezone).
  - DB unique index on `(doctor_id, date)` prevents duplicates.
  - Past dates rejected (only future days off).
- **Security**: Policy enforces center isolation.
- **Done when**: 
  - Staff can CRUD days off for their doctors.
  - Duplicate date for same doctor rejected.
  - Past dates rejected.
  - Other centers get 404.
- **Verify**: 
  - `php artisan test --filter=DayOffTest`
  - Manual: create day off → list → update → delete.

### Step 8 - Public directory + slot computation + tests + OpenAPI

- **Goal**: Public directory endpoint with filtering/pagination/caching, and slot computation endpoint. Unit tests for slot logic. Export OpenAPI.
- **Files**:
  - Create: `app/Http/Controllers/Api/V1/DirectoryController.php`
  - Create: `app/Http/Requests/DirectoryFilterRequest.php`
  - Create: `app/Http/Resources/CenterDirectoryResource.php` (may exist from Step 3, extend)
  - Create: `app/Http/Controllers/Api/V1/Centers/SlotController.php` (or add to DoctorController)
  - Create: `app/Actions/ComputeSlots.php` (Action class for slot computation logic)
  - Create: `tests/Unit/ComputeSlotsTest.php` (unit tests)
  - Create: `tests/Feature/DirectoryTest.php` (feature tests)
  - Modify: `routes/api_v1.php`
  - Modify: `app/Providers/AppServiceProvider.php` (cache config if needed)
- **Tasks**:
  - [ ] Create `DirectoryFilterRequest`: validate `city` (nullable, string), `specialty` (nullable, in: Specialty::values()), `name` (nullable, string), `per_page` (integer, 1-50, default 15), `page` (integer, min 1).
  - [ ] Create `DirectoryController@index(DirectoryFilterRequest $request)`:
    - Query: `Center::where('status', 'active')`
    - Filter by `city` (exact), `specialty` (join doctors → where specialty), `name` (LIKE)
    - Select public fields only (use `CenterDirectoryResource`)
    - Paginate with `per_page` (max 50)
    - Cache: `Cache::tags(['directory'])->remember('key', 60, fn() => ...)` (60s TTL)
    - Cache bust: on center status change, doctor specialty change, center create/update → `Cache::tags(['directory'])->flush()`
  - [ ] Create `ComputeSlots` Action:
    - Input: `Doctor $doctor`, `Carbon $date` (in center's timezone)
    - Logic per schema §7:
      1. Get center timezone → convert date to center local
      2. Get `availability_rules` for that `weekday` (0-6)
      3. For each rule: generate slots from `start_time` to `end_time` in `slot_minutes` increments (center local time)
      4. Convert each slot start/end to UTC (using center timezone)
      5. Remove slots where `starts_at < now()` (past)
      6. Remove slots where `days_off` exists for that doctor on that date (center local date)
      7. Remove slots where active appointment exists (UNIQUE index check: `appointments` with `active_flag=1` for that `doctor_id` and `starts_at`)
      8. Return `[{ starts_at (UTC ISO), ends_at (UTC ISO) }]`
  - [ ] Create `SlotController@show(Doctor $doctor, Request $request)`:
    - Validate `date` query param (required, date_format:Y-m-d)
    - Authorize: doctor's center must be `active` (else 404)
    - Call `ComputeSlots` action
    - Return JSON array
  - [ ] Add routes:
    - `GET /directory` (public, no auth) → `DirectoryController@index`
    - `GET /doctors/{doctor}/slots` (public, requires `date` query) → `SlotController@show`
  - [ ] Write **unit tests** for `ComputeSlots`:
    - Multiple windows per day (morning + evening)
    - Day off removes all slots for that day
    - Timezone conversion (center tz → UTC) - test with Asia/Riyadh
    - Past times removed
    - Active appointments remove specific slots
    - No availability rules → empty array
    - Slot alignment (start_time multiples of slot_minutes)
  - [ ] Write **feature test** for directory:
    - Filter by city, specialty, name
    - Pagination works
    - Only active centers shown
    - Inactive centers return 404 on `/doctors/{id}/slots`
  - [ ] Run `php artisan scramble:export` to generate OpenAPI
  - [ ] Verify new endpoints appear in `/docs/api` (or `public/docs/api/openapi.json`)
- **Backend notes**:
  - Slot computation is the core algorithm - keep it in an `Action` class for testability.
  - Use `Carbon` with timezone for conversions. Gulf zones are DST-free.
  - Cache key for directory: hash of filter params + page.
  - Directory is public (no auth). Slot endpoint is public but only for active centers.
- **Security**: 
  - Slot endpoint checks center is active (404 if not).
  - No rate limit needed for directory (public read), but consider light throttle.
  - Slot computation doesn't expose sensitive data.
- **Done when**: 
  - `GET /api/v1/directory?city=Riyadh&specialty=cardiology` returns paginated active centers
  - `GET /api/v1/doctors/1/slots?date=2026-12-01` returns UTC slot arrays
  - All unit tests pass (slot computation edge cases)
  - Feature test passes (directory filtering)
  - OpenAPI exported and verified
- **Verify**: 
  - `php artisan test --filter=ComputeSlotsTest`
  - `php artisan test --filter=DirectoryTest`
  - `php artisan scramble:export` → check `public/docs/api/openapi.json` has new paths
  - Manual: curl directory endpoint, curl slots endpoint

## Session checklist

- [ ] All steps above done and verified
- [ ] Tests added/updated and passing
- [ ] No files touched outside scope
- [ ] `_index.md` status updated and progress log appended

## Handoff (fill at end of session)

- Completed:
- Deviations from plan and why:
- Notes the next session must know:
- Next file to run: Phase 2 complete → Phase 3