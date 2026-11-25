# Audit — Phase 2 Steps 1–4 (`plans/phase-2/step-1-to-4.md`)

**Revision 2 — 2026-11-24 (supersedes revision 1 of the same date)**
**Scope reviewed:** Steps 1–4 (Specialty enum + doctors, availability/days_off, center registration + admin approve/suspend, center profile + logo upload)
**Classification:** Small (documentation-only change; no code touched by this revision)

**Evidence (run in Docker, from the parent folder):**

| Check | Command | Result |
|---|---|---|
| Full suite | `docker compose exec backend php artisan test --compact` | **106 passed (382 assertions)**, 35.95 s |
| Pint | `docker compose exec backend vendor/bin/pint --dirty --format agent` | **passed** |
| OpenAPI export | `public/docs/api/openapi.json` | 23 paths, all 9 center routes present |
| Storage symlink | `ls -la public/` in container | `storage -> /var/www/html/storage/app/public` ✅ |

**Verdict:** Steps 1–4 are **complete and tested**. Revision 1 of this document was a *pre-fix snapshot* that was never updated after the fixes landed — every ❌ it reported had already been resolved. Revision 2 corrects that record and adds **9 findings revision 1 did not raise**, of which **3 are still open and need a decision**.

> **Implementation note — 2026-11-24 (after revision 2):** **H2, H3, H4, H6 and H7 are resolved**; H1, H5, H8 and H9 remain open as written (H9 reaffirmed: still no commits). Work, decisions and evidence are recorded in the "Audit-fix round" handoff in `plans/phase-2/step-1-to-4.md`. Two corrections to this document: the test specified in F4 (PNG bytes under a non-image name → rejected) **cannot** prove sniffing, because `Illuminate\Http\Testing\File` derives its MIME type from the file name, so that payload is rejected either way — a real `UploadedFile` is required; and F5's `"Asia/Riyadh "` example never reaches validation, because Laravel's `TrimStrings` middleware strips the trailing space (the tests use `UTC+3`). Baseline is now **113 passed (408 assertions)**.

---

## 0. Corrections to revision 1 (read this first)

Revision 1 was written before the gap-fixes were applied and was not updated afterwards. It describes a tree that no longer exists. **Do not act on its "Work plan" section** — all five of its tasks are already done.

| Revision 1 claim | Actual state (verified) |
|---|---|
| "Step 4 missing the logo delete endpoint" ❌ | Exists: `CenterProfileController::deleteLogo()` (`app/Http/Controllers/Api/V1/Centers/CenterProfileController.php:51`), route `DELETE /center/profile/{center}/logo` (`routes/api_v1.php:132`), 5 tests (`tests/Feature/Centers/CenterProfileTest.php:124-165`) |
| "`UpdateCenterProfileRequest` excludes `name` — ❌ `name` accepted and updatable" | Already excluded, with the rationale in a comment (`app/Http/Requests/UpdateCenterProfileRequest.php:29-30`); test asserts the name is unchanged (`CenterProfileTest.php:31`) |
| Task 3 — "add `throttle:sensitive` to the profile `PATCH`" | Already applied (`routes/api_v1.php:129`); test at `CenterProfileTest.php:167` |
| "`_index.md` still `TODO`, progress log empty" | Status `DONE` + 4 progress-log lines (`plans/phase-2/_index.md:15,48-51`) |
| "Handoff in `step-1-to-4.md` not filled" | Filled (`plans/phase-2/step-1-to-4.md:184-205`) |
| "OpenAPI currently stale: zero center routes" | Stale — `public/docs/api/openapi.json` contains `/v1/centers`, `/v1/centers/{center}`, `/v1/centers/register`, `/v1/center/profile/{center}`, `/v1/center/profile/{center}/logo`, `/v1/admin/centers/{center}/approve`, `/v1/admin/centers/{center}/suspend` |
| Baseline "101 passed (366 assertions)" | **106 passed (382 assertions)** |
| "~40 uncommitted files" | **57 paths** (17 modified tracked + 40 untracked) |

**Revision 1's findings that remain accurate:** `Doctor::appointments()` is missing (deferred, see §3 H1); the `specialties` table was replaced by the `Specialty` enum per plan §6, and the schema doc was never amended (see §2 F2 — this is the real issue behind revision 1's "slug typo" note); directory caching is not implemented (see §3 H5); no git commits exist (see §3 H9).

---

## 1. Verified status of Steps 1–4

### Step 1 — Specialty enum + doctors migration + model ✅

| Item | Status |
|---|---|
| `Specialty` enum, 12 cases | ✅ `app/Enums/Specialty.php` — values match schema §9 exactly (`general-medicine`, …) |
| `doctors` migration: columns, `(center_id, is_active)` + `(specialty)` indexes, working `down()` | ✅ `database/migrations/2026_11_19_000006_create_doctors_table.php` — matches plan §6 delta (`specialty_id` FK → `specialty` string(60)) |
| `Doctor` model: fillable, casts (`is_active` bool, `specialty` enum), `center()`, `availabilityRules()`, `daysOff()` | ✅ `app/Models/Doctor.php` |
| `Center::doctors()` | ✅ `app/Models/Center.php:65` |
| `Doctor::factory()` works | ✅ exercised in `tests/Feature/Centers/CenterDirectoryTest.php` |
| `appointments()` relationship | ❌ missing — **deferred to the booking phase** (no `Appointment` model/table yet). Carried as H1. |

Slug note: schema §9 lists `general-medicine`; the plan's own prose said `general_medicine`. The code follows the schema. The plan is wrong here, not the code — but see F2 for the deeper doc problem.

### Step 2 — Availability rules + days off ✅ (models fine; no test coverage)

- Migrations match schema §4.7/§4.8 (weekday tinyint, `time` columns, `slot_minutes` default 30, `(doctor_id, weekday)` index, unique `(doctor_id, date)`, working `down()`s). ✅
- Models, casts, `doctor()` BelongsTo, factories present. ✅
- **`weekday` cast to the new `Weekday` enum** instead of `int` — correct 0=Sunday convention, type-safe. Documented deviation. ✅
- `start_time`/`end_time` kept as plain strings (allowed by plan; rationale in the model). ✅
- ⚠️ **The factories are never exercised** — `grep 'AvailabilityRule\|DayOff' tests/` returns nothing. See F7.

### Step 3 — Center registration + admin approve/suspend ✅ (2 documented deviations)

- **Registration:** ✅ `CenterRegistrationRequest` → `RegisterCenterWithStaff` creates a `pending` center + a `center_staff` user in one transaction, returns 201. Throttled `throttle:center-register` (3/min by IP, `AppServiceProvider:43`).
  - Plan typo: the plan said `name=staff_password`; the code correctly uses `staff_name`.
- **Admin endpoints:** ✅ `POST /admin/centers/{center}/approve|suspend`, admin-only, `CenterResource` response; tests cover happy path, non-admin 403, guest 401.
- **Deviation 1:** no `AdminCenterActionRequest` — two routes with `can:approve,center` / `can:suspend,center` middleware instead. Functionally equivalent.
- **Deviation 2:** `CenterPolicy` has `view/approve/suspend/update` but no `viewAny`/`register` — the public directory and registration authorize inline. Acceptable, but `register` being absent means the registration endpoint has no Policy entry at all (see F8).
- **Good:** foreign staff get **404** (`denyAsNotFound`) per §5.15.
- ⚠️ Security concern about pre-verified staff emails — see F1.

### Step 4 — Center profile + logo upload ✅ (with findings)

| Item | Status |
|---|---|
| `GET/PATCH` center profile, staff-only, 404 for foreign staff | ✅ tested |
| Logo upload: `mimes` + 2 MB validation, MIME sniffing, random name, `public` disk, old file cleaned | ✅ `app/Actions/StoreCenterLogo.php` — **but see F3 (docblock overstates the guarantee) and F4 (the test does not prove content sniffing)** |
| `CenterPolicy@update` scoped by `center_id` | ✅ `app/Policies/CenterPolicy.php:49` |
| `deleteLogo()` / `DELETE .../logo` | ✅ implemented, idempotent, 4 tests |
| `name` excluded from profile update | ✅ |
| Mass-assignment safety | ✅ `status`, `approved_at`, `name` are in `Center`'s `#[Fillable]` but are **not** in `UpdateCenterProfileRequest::rules()`, so `validated()` can never carry them into `$center->update()`. Worth keeping that property. |
| Route shape | ⚠️ `/center/profile/{center}` + policy instead of the plan's `/center/profile` derived from `auth()->user()->center` — documented deviation, no functional impact |

### Session checklist ✅ (complete)

- ✅ Steps 1–4 implemented and verified
- ✅ Tests added and passing — **106 passed (382 assertions)**, Docker
- ✅ `_index.md` status `DONE` + progress log appended
- ✅ Handoff filled in `plans/phase-2/step-1-to-4.md:184-205`
- ⚠️ No git commits (user decision) — 57 paths remain uncommitted. See H9.

---

## 2. New findings revision 1 did not raise

Severity: **High** = security or data-integrity · **Medium** = contract/plan deviation · **Low** = hygiene.

### F1 — Registration creates a *pre-verified* account for an email the registrant never proved they own · **High**

`RegisterCenterWithStaff` sets `email_verified_at = now()` on every new staff user (`app/Actions/RegisterCenterWithStaff.php:39`), on the public, unauthenticated `POST /centers/register` endpoint.

The plan's rationale for pre-verifying is "staff accounts are pre-verified, since center admin creates them" (`plans/phase-2/step-1-to-4.md:108,124`). **That rationale does not hold here**: this is *self*-registration, not an admin creating staff for a known employer. A registrant can therefore create a login for **any email address they choose** — including someone else's — and that account is marked verified without ever proving ownership of the address.

Combined with `throttle:center-register` being **IP-based only (3/min)** with no per-email limit, the endpoint can mint pre-verified identities for third-party email addresses at a steady rate. Phase 1's password-reset flow then lets the real owner of that address "recover" the account, while the registrant retains access until then.

Suggested direction (needs a decision — see §3 H2): either send a verification email to `staff_email` and create the staff account unverified, or require the staff email to match a verified owner. Keep the account `status=active` either way; only `email_verified_at` is in question.

### F2 — `rifad-database-schema.md` was never amended for the `specialties` removal, and now contradicts the code · **Medium**

Plan §6 (line 201) and §1 (line 17) explicitly replace the `specialties` table with a PHP enum: *"`doctors.specialty_id` → `doctors.specialty` string(60), index `(specialty)`"*. The code follows the plan correctly. **The canonical schema doc still describes the old design** in five places:

| Location | Stale content |
|---|---|
| `docs/rifad-database-schema.md:143-150` | §4.5 `specialties` table (slug, name) |
| `docs/rifad-database-schema.md:158` | §4.6 `specialty_id` foreignId → `specialties.id` |
| `docs/rifad-database-schema.md:64` | §3 table map still lists `specialties` under Centers |
| `docs/rifad-database-schema.md:499` | §8 migration order still lists `specialties` at #5 |
| `docs/rifad-database-schema.md:443` | §11 ER diagram still shows `specialties 1─N doctors` |

AGENTS.md §2 names this file **canonical** and §5 rule 7 says never deviate from it. The deviation itself is legitimate (the plan amends the schema), but the amendment was never written back, so the next session reading §8 for migration order will try to create `specialties`. Revision 1 noticed only the `general_medicine` slug nit and attributed it to "the plan has a typo" — that mislocated the problem.

Resolution: amend the schema doc to the plan §6 design and add a dated note recording the plan §6/§1 delta. See H3.

### F3 — `StoreCenterLogo`'s docblock overstates its durability guarantee · **Medium**

`app/Actions/StoreCenterLogo.php:35` deletes the previous logo **before** the controller saves the new path (`CenterProfileController.php:40`). The class docblock claims: *"the new file is written before the previous one is removed so a failed upload can never leave the center pointing at a file that no longer exists."*

True for a failed **upload**. **False for a failed database write**: if `$center->update()` throws after `deletePreviousLogo()` ran, the row still points at the old path, the old file is already deleted, and the newly written file is orphaned on disk. The centre is left with a `logo_path` that 404s.

Fix options (small): delete the previous file *after* the successful save, or wrap the write-and-swap in a transaction. See H4.

### F4 — The upload tests do not prove MIME sniffing; they prove filename matching · **Medium**

`StoreCenterLogo::extensionFor()` derives the stored extension from `$file->getMimeType()` — content-based in production, which is the correct behaviour per §5.19.

But `Illuminate\Http\Testing\File::getMimeType()` returns `MimeType::from($this->name)` — i.e. **derived from the filename**, not the content (`vendor/laravel/framework/src/Illuminate/Http/Testing/File.php:132-135`). The same applies to `guessExtension()`, which the `mimes` rule uses (`vendor/laravel/framework/src/Illuminate/Validation/Concerns/ValidatesAttributes.php:1772`).

So `CenterProfileTest.php:79-122` ("stores a logo", "rejects a logo that is not an image", "rejects a logo larger than two megabytes") all pass purely on the **file name**. Rename the payload to `logo.png` while keeping the bytes as plain text and the fake still reports `image/png`. Production code is correct; the test simply cannot distinguish "sniffed the content" from "trusted the client name" — so a future regression that trusts `getClientOriginalName()` would pass the suite.

Fix: add one test that uploads valid PNG bytes under a non-image name (e.g. `payload.txt`) and asserts rejection — that only holds if the content is actually sniffed. See H4.

### F5 — `timezone` is never validated against the IANA list · **Medium**

Schema §4.4 defines `timezone` as *"IANA timezone; used to read availability and display times"*. Both `CenterRegistrationRequest` (`timezone` → `required|string|max:40`) and `UpdateCenterProfileRequest` (`timezone` → `sometimes|string|max:40`) accept any 40-character string.

Slot computation (Phase 2 Steps 5–8) converts center-local times to UTC **using this column**. A bad value (`"Asia/Riyadh "` with a trailing space, `"Riyadh"`, `"UTC+3"`) will not fail at write time — it will fail later, inside date maths, far from the request that caused it.

Fix: validate with `Rule::in(timezone_identifiers_list())` or `in_array(..., DateTimeZone::listIdentifiers())`. Small, self-contained. See H4.

### F6 — The same `CenterResource` yields three different response shapes · **Medium**

`CenterResource` includes `staff` only `whenLoaded('staff')` (`app/Http/Resources/CenterResource.php:40`). The three call sites load it differently:

| Endpoint | `staff` in response? |
|---|---|
| `POST /admin/centers/{center}/approve|suspend` | ✅ present (`CenterAdminController.php:25`) |
| `POST /centers/register` | ✅ present (`RegisterCenterWithStaff.php:43`) |
| `GET/PATCH/DELETE /center/profile/{center}` | ❌ **absent** (`CenterProfileController.php:42`) |

Same resource, three contracts, no documented reason. The frontend has to handle `staff` being optionally present on a field it may assume exists. Either always load it for staff-facing endpoints or document the difference in the OpenAPI. See H6.

### F7 — `AvailabilityRule` / `DayOff` have zero test coverage · **Low**

`grep 'AvailabilityRule\|DayOff' tests/` returns no matches, so the two factories are never exercised. Revision 1 marked Step 2 "✅ factories all present" on *presence*, without noticing they are never run.

Schema §4.7's app rules (`end_time > start_time`, window length a multiple of `slot_minutes`, no overlapping windows for the same doctor+weekday) are also enforced **nowhere** today — the Form Request that should enforce them belongs to Steps 5–8. Neither fact is currently recorded as an obligation anywhere. See H5.

### F8 — The `verified` middleware is registered but applied to no route · **Low**

`EnsureEmailIsVerified` is aliased as `verified` (`bootstrap/app.php:33`) but no route in `routes/api_v1.php` uses it — `grep 'verified' routes/` returns nothing. Plan §9 lists *"auth throttling and verification gate"* as a must-have test.

This may be deliberate (a Phase 1 decision to gate verification later), but with F1 in play — staff accounts are created already-verified — the absence of a verification gate on other flows deserves a deliberate decision rather than silence. See H7.

### F9 — The uncommitted working tree mixes two phases · **Low**

57 uncommitted paths: 17 modified tracked files + 40 untracked.

**Most of the 17 modified files are Phase 2 work, not unrelated churn** — they are Phase 1 code that Phase 2 forced into change:

| Modified file | Why Phase 2 changed it |
|---|---|
| `app/Enums/UserRole.php` | `isAdmin()/isCenterStaff()/isPatient()` — required by `CenterPolicy` |
| `app/Exceptions/ApiErrorRenderer.php` | `AuthorizationException` → 404 handling, required by `denyAsNotFound()` (§5.15) |
| `app/Http/Controllers/Controller.php` | `AuthorizesRequests` trait, required by `$this->authorize()` in the new controllers |
| `app/Http/Requests/Auth/RegisterRequest.php`, `ResetPasswordRequest.php`, `Profile/UpdatePasswordRequest.php` | extracted `App\Support\PasswordPolicy` so `forStaff()` could be added for center registration |
| `app/Models/User.php` | `role`, `status`, `center_id` added to `#[Fillable]` — `center_staff` needs `center_id` |
| `app/Models/Center.php` | `doctors()`, `status` cast, `staff()` scoped by role |
| `app/Providers/AppServiceProvider.php` | `throttle:center-register`, `Gate::policy(Center::class, …)` |
| `routes/api_v1.php`, `config/scramble.php`, `.gitignore` | the 9 new center routes and the OpenAPI export path |

**Only 6 modified files are genuinely Phase 1 leftovers** with no Phase 2 dependency — `GoogleController` (stricter `email_verified` parsing), `FamilyMemberController` (pagination moved to `FamilyMemberIndexRequest`), `ProfileController` (`fill()+save()` → `update()`), plus `GoogleAuthTest` and `FamilyMemberTest` which cover them.

The practical consequence is that revision 1's checklist assertion "no files touched outside scope ✅" is **not verifiable**: with no commit between Phase 1 and Phase 2 there is no baseline to diff Phase 2 against, and 6 Phase 1 fixes are entangled with the Phase 2 diff. Committing Phase 1 leftovers separately (or accepting them in the Phase 2 commit) is a decision, not an oversight. See H9.

---

## 3. Remaining handoff items (still open)

Ordered by what blocks the next session soonest.

| # | Item | Finding | Status |
|---|---|---|---|
| **H1** | Add `Doctor::appointments()` (HasMany) — needs the `Appointment` model, so it belongs to the booking phase | Step 1 | Deferred; record in the next handoff |
| **H2** | Decide the `staff_email` verification model for self-registration (see F1) | F1 | **Needs a human decision** |
| **H3** | Amend `rifad-database-schema.md` to the plan §6 design: drop §4.5 `specialties`, change §4.6 `specialty_id` → `specialty` string(60), update the §3 map, §8 migration order, §11 ER diagram; add a dated note citing plan §6/§1 | F2 | Open — doc-only, no code |
| **H4** | Small hardening batch, all in Phase 2 files: (a) delete the old logo *after* the successful save, or make the swap transactional; (b) add one upload test that proves content-based sniffing (valid PNG bytes under a non-image name → rejected); (c) validate `timezone` against the IANA list | F3, F4, F5 | Open — one focused change set |
| **H5** | Steps 5–8 must add: doctor/availability CRUD, slot computation, **directory caching with a short TTL + bust on change** (plan §5 — still missing), enforcement of the schema §4.7 app rules, and the first tests that touch `AvailabilityRule`/`DayOff` | F7 | Open — next session |
| **H6** | Decide the `staff` shape for the profile endpoints and align the OpenAPI (F6) | F6 | Open — needs frontend coordination |
| **H7** | Decide whether the `verified` middleware should gate any Phase 2 route (F8) | F8 | Open — needs a human decision |
| **H8** | Keep `docs/rifad-database-schema.md` in sync with any future plan delta — this is the second time the schema and code have drifted apart | F2 | Standing rule |
| **H9** | 57 uncommitted paths (17 modified tracked + 40 untracked). 11 of the 17 modified are Phase 2-driven changes to Phase 1 files; **6 are Phase 1 leftovers** (`GoogleController`, `FamilyMemberController`, `ProfileController` + their 2 tests) with no Phase 2 dependency (F9). No commits exist since `f848816` | F9 | Open — user decision pending on committing, and on whether to split the Phase 1 leftovers into their own commit |

### Explicitly NOT doing

- Steps 5–8 feature content (doctor CRUD, availability CRUD, slot computation, caching) — out of scope for this audit.
- `Doctor::appointments()` before the `Appointment` model exists.
- Any git commit, unless the user changes the earlier decision.
- Touching `frontend/` — the Integration Contract has not changed; `CenterResource` shape (H6) is the only coordination point and it is still open.

---

## 4. Deviations from the plan (authoritative list)

- `availability_rules.weekday` is cast to the new `Weekday` enum (not `int`) — type-safe, same 0=Sunday convention.
- No `AdminCenterActionRequest`: two admin routes with `can:approve/suspend,center` middleware instead of one `action` param route (functionally equivalent).
- `CenterPolicy` has `view/approve/suspend/update` but no `viewAny`/`register` — the public directory and registration authorize inline.
- Profile routes use `/center/profile/{center}` + policy instead of `/center/profile` derived from `auth()->user()->center`; logo upload stays merged into `PATCH` as a `logo` file field (only `DELETE .../logo` was added as a separate route).
- The `specialties` table was replaced by the `Specialty` enum with `doctors.specialty` string(60), per plan §6/§1 — **the schema doc has not been amended** (F2 / H3).
- The directory (`GET /centers`, `GET /centers/{center}`, `DirectoryFilterRequest`, `CenterDirectoryResource`) was built early although listed under Steps 5–8; **caching not added yet** — Steps 5–8 must add short TTL + bust on change (H5).
- `license_no` is **not** in `UpdateCenterProfileRequest`, although the plan listed it among the updatable fields — see H4/H6; decide whether staff may edit it (it is admin/licensing data per schema §4.4).
- Step-4 task checkboxes in `plans/phase-2/step-1-to-4.md` are left as written where the implementation differs; this list is authoritative.

---

## 5. Notes for the next session

- **`Doctor::appointments()` does not exist yet** — no `Appointment` model/table; add it in the booking phase (H1).
- **Registration currently creates pre-verified staff accounts** for an email the registrant never proved they own — F1/H2 is the highest-severity open item.
- **Directory caching is still missing** (plan §5) — Steps 5–8 must add it, along with bust-on-change on center approve/suspend/update.
- **Profile update is rate-limited** (`throttle:sensitive`, 6/min per user) — the frontend must handle `429`.
- **`public/storage` symlink exists**; logo files live under `centers/{id}/logo_<random>.<ext>` on the `public` disk.
- **The OpenAPI export is current** — `public/docs/api/openapi.json` includes all 9 center routes. Note it is gitignored (`.gitignore:28`); the export is a build artifact, not a committed file.
- **Baseline for the next session: 106 tests green.**
- **Next file to run: `plans/phase-2/step-5-to-8.md`**
