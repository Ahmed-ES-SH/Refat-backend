# Clean Code Plan — Rifad Backend

**Status:** DONE — all three phases implemented and verified in Docker · **Created:** 2026-11-24 · **Updated:** 2026-11-24 · **Scope:** `backend/` (Laravel 13 API)

## Goal

Bring the backend to a shippable clean-code baseline: no endpoint that returns a 500 by construction, one
response contract, one validation boundary, no dead code, and a test suite that actually covers the
Phase 2 surface that was just written.

This plan is the output of a `clean-code-guard` **review-mode** pass over `app/`, `routes/`,
`database/`, `config/` and `tests/`. It is a *plan*, not a diff: nothing in the codebase was changed
while producing it.

## How the review was run (baseline evidence)

| Check | Command | Result |
|---|---|---|
| Full test suite | `php artisan test --compact` | **67 passed** (243 assertions) |
| Style | `php vendor/bin/pint --test` | **PASS** — 109 files |
| Route table | `php artisan route:list --path=api/v1/centers` | 4 routes — one expected route **missing** (see C1) |
| Behaviour probes | temporary Pest files (3, since deleted) | confirmed C1, C2, C4, C5, I1, C6 |

> ⚠️ **Deviation from AGENTS.md §8:** the Docker daemon was not running for this review
> (`npipe:////./pipe/dockerDesktopLinuxEngine` unreachable), so `php artisan` was executed on the host
> (PHP 8.5.13) instead of inside the `backend` container. **Every verification command listed in this
> plan must be re-run in Docker** (`make artisan ARGS="..."` from the parent folder) before the work is
> called done. Nothing was verified against MySQL — see [Verification gaps](#verification-gaps).

The suite passes while five endpoints return 500. That is the core problem this plan fixes: **the
safety net is blind exactly where the defects are.**

## Findings register

Counts: **7 critical, 11 important, 7 nits** = 25 findings. IDs are stable and referenced by the
work items below.

| ID | Severity | Location | Rule | Summary |
|---|---|---|---|---|
| C1 | Critical | `routes/api_v1.php:63` vs `:120` | AI #13 (plausible-but-wrong) | Staff profile route `GET centers/{center}` **overwrites** the public directory route → public center detail returns 401, named route `api.v1.centers.show` no longer exists |
| C2 | Critical | `app/Http/Controllers/Controller.php:5` | AI #6 (hallucinated API) | Base controller has no `authorize()` → 500 `Call to undefined method …::authorize()` on 4 endpoints |
| C3 | Critical | `app/Http/Controllers/Api/V1/Admin/CenterAdminController.php:26,40` | AI #12 | Method declares `: JsonResponse` but returns a `JsonResource` → `TypeError` |
| C4 | Critical | `app/Http/Controllers/Api/V1/Centers/CenterDirectoryController.php:22` | AI #13 | Eager-load `select()` omits `is_active`, the resource filters on it → `specialties` is **always `[]`** |
| C5 | Critical | `app/Http/Requests/UpdateCenterProfileRequest.php:26-36` vs `database/migrations/2026_11_19_000001_create_centers_table.php:15-20` | AI #13 | Validation lengths exceed the column widths → MySQL strict-mode 500 (`Data too long`) |
| C6 | Critical | `app/Models/Doctor.php:74` | AI #11 (dead code) | `appointments()` targets a non-existent `App\Models\Appointment` → fatal on first call |
| C7 | Critical | `tests/` (no Phase 2 coverage) | §10 must-haves | Zero tests for registration, admin approve/suspend, directory, profile/logo, `CenterPolicy` |
| C8 | Critical | `app/Http/Controllers/Api/V1/Centers/CenterProfileController.php:62` (old) | AI #13 | `storeAs('public', $path)` writes to the **default** disk under a `public/` folder, not to the `public` disk — every uploaded logo was unreachable at its URL and never deleted. Found by the new upload test, not by the original review |
| C9 | Critical | `backend/server.php` (missing) | AI #13 | The export path mandated by AGENTS.md (`public/docs/api/openapi.json`) makes the **directory** `public/docs/api` exist, and `php artisan serve` serves any existing path as a static file — the directory shadowed the `/docs/api` route and the API docs page answered 404. Fixed with an app-level `server.php` that only serves real files |
| I1 | Important | `app/Http/Requests/AdminCenterActionRequest.php:26` | KISS | Admin endpoints require a redundant `action` body field the controller ignores → 422 without it |
| I2 | Important | `routes/api_v1.php:73` | AI #13 | The suspend route is gated by `can:approve,center` → `CenterPolicy::suspend` is never called (dead policy method) |
| I3 | Important | `app/Http/Controllers/Api/V1/Centers/CenterProfileController.php:33-64` | DRY / §5.19 | Upload validation duplicated, hand-rolled error envelope without `errors`/i18n, client-supplied extension used for the stored name, old logo deleted before the new file is stored |
| I4 | Important | `app/Support/ApiResponse.php` vs `CenterDirectoryController.php:50` | DRY (knowledge) | Two list envelopes: `{data, meta:{page,per_page,total}}` vs Laravel's default `links`+`meta.current_page` → violates plan §7 |
| I5 | Important | `app/Http/Controllers/Api/V1/Centers/CenterDirectoryController.php:26-47` | §5.16 | Directory filters read raw request input (no Form Request); `per_page` unvalidated; LIKE wildcards unescaped |
| I6 | Important | `app/Policies/CenterPolicy.php:25` | §5.15 | A suspended center yields **403**, not 404 → confirms the center's existence (ID enumeration) |
| I7 | Important | `RegisterRequest.php:33`, `ResetPasswordRequest.php:31`, `UpdatePasswordRequest.php:27`, `CenterRegistrationRequest.php:43` | DRY (knowledge) | One password rule expressed four times with **two different rule sets** |
| I8 | Important | `app/Models/AvailabilityRule.php:33-34` + migration `:19` | AI #13 | `TIME` columns cast to `datetime`; weekday convention (DB comment `0 = Sunday`) contradicts Carbon's `dayOfWeek` (`1 = Monday`) → off-by-one waiting in slot computation |
| I9 | Important | `app/Http/Controllers/Api/V1/Auth/GoogleController.php:57` | §5 (fail closed) | A missing `email_verified` flag in the provider payload defaults to **verified** |
| I10 | Important | `app/Http/Controllers/Api/V1/Centers/CenterRegistrationController.php:22-53` | §6 (thin controllers) | Controller re-lists every validated field, uses raw `'pending'`/`'ar'` strings instead of the enums, and owns a DB transaction + business rules |
| I11 | Important | `config/scramble.php:33` | §9 / §11 | `export_path` is `api.json` → the export lands in the repo root, not `public/docs/api/openapi.json`; a stray untracked `api.json` sits in `backend/` |
| N1 | Nit | `GoogleController.php:61,65,73`; `CenterProfileController.php:33,37,46,54,59`; `CenterDirectoryController.php:25,30,38`; `CenterRegistrationRequest.php:27,40`; `CenterPolicy.php:23,28,33,38,71` | §B comment pollution | Step-number scaffolding ("1. Already linked → sign in.") and comments that restate the line below |
| N2 | Nit | `app/Policies/CenterPolicy.php:13,45`; `app/Models/Center.php:53` | AI #14 (YAGNI) | `viewAny`/`register` policy methods never called; `staff()` not scoped to `role = center_staff`. *(Corrected during implementation: the `verified` middleware alias **is** used — `tests/Feature/Auth/EmailVerificationTest.php:79` mounts a route with it — so it was kept.)* |
| N3 | Nit | `CenterProfileController.php:31`; `CenterRegistrationController.php:22`; `CenterDirectoryController.php:21,32,41` | AI #7 (generic names) | `$data`, `$q` — names do not reveal intent |
| N4 | Nit | `CenterProfileController.php:61-63` | AI #11 | `str_replace('public/', '', $path)` is a no-op — `storeAs()` already returns a disk-relative path |
| N5 | Nit | `CenterDirectoryController.php:62` | KISS | Eager-loads `availabilityRules` + `daysOff` that `CenterDirectoryResource` never exposes → 2 wasted queries |
| N6 | Nit | `FamilyMemberController.php:40`; `ProfileController.php:23-24` | AI #10 | One method returns a `Resource` while its siblings return `JsonResponse`; `fill()+save()` where the rest of the codebase uses `update()` |
| N7 | Nit | `app/Providers/AppServiceProvider.php:20-23` | AI #11 | `register()` is an empty stub carrying a `//` placeholder |

### What's good (do not regress)

- `app/Exceptions/ApiErrorRenderer.php` — one error envelope, machine codes, `Accept-Language` aware.
- `app/Providers/AppServiceProvider.php:38-71` — rate limits keyed on identifier **and** IP.
- `app/Http/Controllers/Api/V1/Family/FamilyMemberController.php:63-66` — foreign ids resolve to 404, not 403.
- `app/Models/User.php` — `#[Fillable]` / `#[Hidden]` attributes instead of stringly-typed property bags.
- Pint-clean formatting across 109 files.

---

## Work plan

Three phases, in strict order. Each phase ends with its own verification gate; do not start a phase
before the previous gate is green. Every phase is one focused commit per work item (Conventional
Commits), per AGENTS.md §4.

### Phase A — Correctness (P0)

**Objective:** every route in the table answers 200/4xx, never 500-by-construction.
**Behaviour change:** A2 moves two URLs → requires frontend sign-off (see [Coordination](#coordination-with-the-frontend)).

| # | Item | Fixes | Files |
|---|---|---|---|
| A1 | Give the base controller the authorization helper | C2 | `app/Http/Controllers/Controller.php` |
| A2 | Break the route collision: move the staff profile routes off `centers/{center}` | C1 | `routes/api_v1.php` |
| A3 | Return a response, not a resource, from the admin controller | C3 | `app/Http/Controllers/Api/V1/Admin/CenterAdminController.php` |
| A4 | Include `is_active` in the directory eager-load | C4 | `app/Http/Controllers/Api/V1/Centers/CenterDirectoryController.php` |
| A5 | Align the profile-request limits with the column widths | C5 | `app/Http/Requests/UpdateCenterProfileRequest.php` |
| A6 | Delete the `appointments()` relation until the model exists | C6 | `app/Models/Doctor.php` |
| A7 | Add the Phase 2 test suite (regression tests for A1–A6) | C7 | `tests/Feature/Centers/*` |

**A1 — base controller.** `app/Http/Controllers/Controller.php` is `abstract class Controller {}` with no
parent, so `$this->authorize()` does not exist. The Laravel 11+ skeleton dropped
`Illuminate\Routing\Controller`, but the helper still has to come from somewhere.
Fix: use the `AuthorizesRequests` trait (and `ValidatesRequests` if the codebase later needs it) — the
minimal, boring option:

```php
abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;
}
```

Do **not** introduce a `PaymentGateway`-style abstraction or a base-class hierarchy here; one trait is enough.

**A2 — route collision.** Laravel stores routes in an array keyed by method + URI, so a later
registration with the same method+URI **replaces** the earlier one. The staff profile group
(`routes/api_v1.php:120`) registers `GET api/v1/centers/{center}` after the public directory route
(`:63`) and silently replaces it. Verified: `route:list` shows no `api.v1.centers.show`, and
`route('api.v1.centers.show')` throws `RouteNotFoundException`.

Fix: give the staff surface its own prefix so no two routes share method+URI. Phase 2's own step
file (`plans/phase-2/step-1-to-4.md`) already anticipated `/center/profile*` — adopt that:

```
GET   api/v1/center/profile/{center}   → CenterProfileController@show
PATCH api/v1/center/profile/{center}   → CenterProfileController@update
```

Keep `GET api/v1/centers/{center}` for the public directory detail. Acceptance: `route:list` shows
**both** routes, `route('api.v1.centers.show')` resolves, and a guest can read an active center's
public detail.

**A3 — admin controller return type.** `approve()`/`suspend()` declare `: JsonResponse` but
`return new CenterResource(...)`. Return `->response()` (matching `CenterProfileController`) or drop
the return type — pick one and apply it to every controller in the codebase. Preferred: return the
resource and let Laravel serialise it, i.e. change the return type to `CenterResource` and keep
`(new CenterResource(...))->response()->setStatusCode(...)` only where a status code is needed.

**A4 — directory specialties.** `->select('id', 'center_id', 'specialty')` drops `is_active`; the
resource then filters the collection with `->where('is_active', true)`, and every doctor is filtered
out because the attribute is missing. Verified: `specialties => []` for an active center with an
active cardiologist.
Fix: add `is_active` to the select **and** decide where the filtering belongs — the query already
filters `is_active = true`, so the resource filter is redundant. Pick the query as the single place
and delete the resource-side `->where(...)` (knowledge duplication, not duplication).

**A5 — column widths.** `UpdateCenterProfileRequest` allows `name` 255 / `phone` 50 / `email` 255 /
`city` 100 / `address` 500; the migration declares `name(150)` / `phone(30)` / `email(190)` /
`city(80)` / `address(255)`. MySQL runs with `'strict' => true` (`config/database.php:60`), so a
long value is a 500, not a 422. SQLite does not enforce the length → the test suite cannot catch it.
Fix: make the request limits match the migration exactly, and add the column width as a test
assertion per field so the two can never drift again.

**A6 — phantom relation.** `Doctor::appointments()` resolves `App\Models\Appointment`, which does not
exist (verified: `Error: Class "App\Models\Appointment" not found`). Phase 3 owns that model. Delete
the relation now and re-add it with the model; a relation that cannot run is dead code.

**A7 — Phase 2 tests.** Add `tests/Feature/Centers/` with:

| File | Covers |
|---|---|
| `CenterRegistrationTest.php` | public registration creates center + staff, status is `pending`, staff email unique, invalid payload → 422 |
| `CenterAdminTest.php` | admin approves (status `active`, `approved_at` set), suspends, non-admin → 403, guest → 401 |
| `CenterDirectoryTest.php` | only `active` centers listed, `specialties` populated, filters (city/specialty/search), pagination bounds, suspended center detail → 404 |
| `CenterProfileTest.php` | staff of another center → 403/404, logo upload (type + size rejection), logo path written |
| `CenterPolicyTest.php` | unit test for every policy method per role |

Each of A1–A6 gets a **regression test** that fails before the fix and passes after (AGENTS.md §10).
Assert on the machine code (`code`), not on message text.

**Phase A gate:** `make artisan ARGS="test"` green · `route:list` reviewed by hand ·
`make artisan ARGS="scramble:export"` runs clean · no endpoint returns 500 in a manual sweep of
`/api/v1/centers*`.

### Phase B — Contracts and boundaries (P1)

**Objective:** one envelope, one validation boundary, one authorization story, one policy per rule.

| # | Item | Fixes | Files |
|---|---|---|---|
| B1 | Drop the redundant `action` field from the admin request | I1 | `app/Http/Requests/AdminCenterActionRequest.php` |
| B2 | Gate suspend with `can:suspend,center` | I2 | `routes/api_v1.php` |
| B3 | One list envelope for every endpoint | I4 | `app/Support/ApiResponse.php`, directory controller |
| B4 | `DirectoryFilterRequest` + escaped LIKE + validated `per_page` | I5 | new Form Request + controller |
| B5 | Suspended/foreign center → 404, not 403 | I6 | `app/Policies/CenterPolicy.php` |
| B6 | One password policy, defined once | I7 | shared rule object + the four requests |
| B7 | Extract the logo upload into an Action | I3 | new `app/Actions/StoreCenterLogo.php` + controller |
| B8 | Export OpenAPI to `public/docs/api/openapi.json` | I11 | `config/scramble.php`, delete stray `api.json` |

**B1/B2 — admin contract.** The route already encodes the action (`/approve`, `/suspend`), so
requiring `{"action":"approve"}` in the body is pure duplication — and the controller ignores the value
entirely. Remove the field (keep `reason` if it is ever displayed; otherwise drop it too — YAGNI).
Separately, the suspend route is gated by `can:approve,center`, which makes `CenterPolicy::suspend`
unreachable. Change the middleware to the ability the route actually performs.

**B3 — one envelope.** `ApiResponse::paginated()` produces `{data, meta:{page, per_page, total}}` (plan
§7) but the directory returns Laravel's default resource pagination (`links` + `meta.current_page`).
Two shapes for two list endpoints is a contract bug the frontend will hit. Decide once:
recommended = route **every** list through `ApiResponse::paginated()` and drop the default
`JsonResource::collection(...)->response()` pagination shape, or keep the default everywhere and
delete `ApiResponse`. Whichever wins, it must be one rule applied to every list endpoint
(`/me/family-members`, `/api/v1/centers`, and every list added in Phase 2 Steps 5–8).

**B4 — directory filters.** Introduce `DirectoryFilterRequest` (`city`, `specialty`, `search`,
`per_page` 1–50, `page` ≥ 1) as the phase-2 plan already prescribes, and escape `%` / `_` in the
LIKE values so a user cannot smuggle wildcards into the query.

**B5 — 404 over 403.** `CenterPolicy::view` returns `false` for a suspended center → 403, which tells
an anonymous caller "this id exists but you may not see it". Use `denyAsNotFound()` on the policy
response so the endpoint answers 404 (AGENTS.md §5.15).

**B6 — password policy.** `Password::min(8)` in three places and
`min(8)->mixedCase()->numbers()->symbols()` in a fourth. One rule, one place — e.g. a
`App\Rules\PasswordPolicy` rule object or a `config/auth.php` entry that all four requests reference.
The stricter rule (staff accounts are privileged) may be deliberate, but it must be an explicit,
documented difference, not drift.

**B7 — logo upload action.** Move the upload out of the controller into `app/Actions/StoreCenterLogo.php`
(AGENTS.md §6: an Action when the rule spans several steps). The action must:
- derive the stored extension from the **sniffed MIME type**, never from `getClientOriginalExtension()` (AGENTS.md §5.19);
- take size/MIME limits from the Form Request only — no second copy in the controller;
- store the new file **before** deleting the old one, so a failed upload never leaves a dangling `logo_path`;
- delete the no-op `str_replace('public/', '', $path)` (N4) and keep `storeAs()`'s disk-relative path;
- return the stored path, letting the controller stay thin.

The controller's hand-built 422 bodies must go through `ApiException` so they carry `{message, code, errors}`
and a translated message like every other error (AGENTS.md §7, §11).

**B8 — OpenAPI export.** `config/scramble.php` sets `'export_path' => 'api.json'`, which writes to the
repo root; AGENTS.md §9 and §11 require `public/docs/api/openapi.json` (and `public/docs/` does not
exist yet). Point the config at `public/docs/api/openapi.json`, delete the untracked `backend/api.json`,
re-export, and verify the new endpoints appear in `/docs/api`.

**Phase B gate:** every endpoint returns the same envelope · every list uses one pagination shape ·
`scramble:export` writes to `public/docs/api/openapi.json` · no hand-built error body outside
`ApiException` · full suite green.

### Phase C — Structure and polish (P2)

**Objective:** remove the residue that makes the next milestone harder to write.

| # | Item | Fixes | Files |
|---|---|---|---|
| C1 | `RegisterCenterWithStaff` Action + enum values | I10 | new `app/Actions/`, controller, `CenterRegistrationController` |
| C2 | Correct the availability-rule casts and pin the weekday convention | I8 | `app/Models/AvailabilityRule.php`, migration comment, new `Weekday` enum |
| C3 | Fail closed on a missing `email_verified` | I9 | `app/Http/Controllers/Api/V1/Auth/GoogleController.php` |
| C4 | Comment cleanup pass | N1 | 6 files listed in the register |
| C5 | Delete or wire the dead code | N2 | `bootstrap/app.php`, `CenterPolicy`, `Center::staff()` |
| C6 | Rename the generic identifiers | N3 | 3 controllers |
| C7 | Unify the controller response style | N6 | `FamilyMemberController`, `ProfileController` |
| C8 | Drop the empty `register()` stub comment | N7 | `app/Providers/AppServiceProvider.php` |
| C9 | Remove the unused eager-loads | N5 | `CenterDirectoryController` |

**C1 — registration action.** `CenterRegistrationController` re-lists all eleven fields from
`$data` (silent-drop risk the moment a field is added to the request), hard-codes `'pending'` and
`'ar'` as raw strings while the models cast to `CenterStatus` / `Locale`, and owns a DB transaction
that is a business rule. Extract `RegisterCenterWithStaff` into `app/Actions/`, use
`CenterStatus::Pending->value` and `Locale::Ar->value`, and let the controller do
authorise → validate → act → respond.

**C2 — availability semantics.** `start_time`/`end_time` are `TIME` columns cast to `datetime`; a
Carbon date written back into a `TIME` column is either truncated or rejected by MySQL. Cast them as
plain strings (`H:i`) and let the slot-computation Action parse them. The weekday convention also has
to be pinned **before** Phase 2 Step 8 writes the slot algorithm: the migration comment says
`0 = Sunday … 6 = Saturday`, Carbon's `dayOfWeek` says `1 = Monday … 7 = Sunday`. Introduce a
`Weekday` enum with named cases so the mapping is explicit in one place, and re-derive the slot code
from that enum instead of from a raw integer.

**C3 — Google fail closed.** `data_get($googleUser->getRaw(), 'email_verified', true) === false`
treats a **missing** flag as verified. Invert it: only an explicit `true` may proceed; anything else
(missing, false, non-boolean) → `GOOGLE_EMAIL_UNVERIFIED`.

**C4 — comments.** Delete the step-number scaffolding in `GoogleController` (the numbered list is
already expressed by the code order) and the "what" comments in the center controllers and requests.
Keep the comments that explain *why* — e.g. the "Credentials are checked first so the status cannot be
probed without the password" note in `AuthenticatedSessionController` and the `email_verified`
rationale — those earn their place.

**C5 — dead code.** `EnsureEmailIsVerified` is registered as the `verified` alias but no route uses it
(it is meant for Phase 3 booking/purchasing). Either wire it onto the Phase 3 routes when they land,
or remove the alias now and re-add it with them — an unused alias is a promise nobody keeps. Same for
`CenterPolicy::viewAny()` and `::register()`: neither is called. Scope `Center::staff()` to
`role = center_staff` so a mis-set `center_id` cannot leak a patient into the staff payload.

**Phase C gate:** full suite green · Pint clean · no comment restates its line · no unreferenced
class, method or relation remains.

---

## Out of scope (deliberately not in this plan)

- **Phase 3+ features** — booking, appointments, Stripe, chat. This plan only cleans what exists.
- **Larastan / static analysis** — not installed yet (AGENTS.md §3). When it lands, run it at level 5+
  and fold its findings into a new review round.
- **New dependencies** — every fix above uses what is already installed (Laravel 13, Sanctum, Socialite,
  Scramble). No package is added.
- **Schema changes** — nothing here adds a table or column. C5 fixes the *validation* to match the
  existing migration; C2/C1 change PHP only.
- **Frontend work** — the frontend consumes the API; see coordination below.

## Coordination with the frontend

Three items in this plan change observable API behaviour and must be announced in the same release as the
frontend change (backend plan §13 = frontend plan §15, the Integration Contract):

1. **A2** — the staff profile endpoints move from `api/v1/centers/{center}` to
   `api/v1/center/profile/{center}`. The frontend call sites must move with it.
2. **B3** — the public directory now answers with the shared list envelope
   `{ data, meta: { page, per_page, total } }` instead of Laravel's default `links` + `meta.current_page`.
3. **B3/B4** — `per_page` above 50 is now a **422** on every list endpoint. The family-member list used to
   clamp it to 50; both lists now share one rule, so the frontend must never send more than 50.

Everything else is additive or internal. Record these changes in the backend plan's progress log and in
the frontend repo before merging.

## Verification gaps (honest limits of this review)

| Gap | Why | Risk |
|---|---|---|
| No MySQL verification | The suite runs on SQLite `:memory:`, the dev DB is SQLite | C5 (column widths) and I8 (`TIME` columns) can only be confirmed on MySQL 8 — run the suite in CI |
| No Laravel-Cloud / CI run | CI pipeline does not exist yet | Scramble export and Pint are unverified in CI |
| Concurrency, Stripe, uploads | Not implemented (Phase 3/4) | n/a for this plan |
| `laravel/pao`, Boost, Pail | Installed dev tooling, not application code | Not reviewed beyond identity (`laravel/pao` is a genuine Laravel package) |

> The implementation was verified **inside the Docker `backend` container** (`docker compose exec backend
> php artisan test`, `vendor/bin/pint`, `scramble:export`) — not on the host. The review pass itself was
> produced while the daemon was down and therefore ran host-locally; that only affected how the findings
> were observed, not the fixes.

## Deviations from the plan (and why)

| Item | Planned | Done | Reason |
|---|---|---|---|
| B1 | Remove the `action` field from `AdminCenterActionRequest` | The whole request class was **deleted**; the controller takes no input | With `action` and `reason` both unused, an empty Form Request would have been a class with nothing to validate |
| B7 | Extract the logo upload into an Action (Phase B) | Done in **Phase A** | The new upload test failed on the old code (C8), so the fix could not wait for the Phase B gate |
| A4 | Remove the redundant resource-side `is_active` filter | The filter was **kept** and the select was fixed | The resource must stay correct whatever loaded the relation; the query filter is a performance measure, the resource filter is the contract |
| B3 | Unify the list envelope | The family list was also switched to validating `per_page` | Two lists answering differently to the same input is the inconsistency the item was about |
| N5 | Remove the unused eager-loads (Phase C) | Done in **Phase A** | Same file and same concern as A4 |

## Progress log

<!-- one line per finished work item: date - item - what changed - commit -->

- 2026-11-24 — plan created from a clean-code-guard review pass over `backend/` (7 critical, 11 important, 7 nits).
- 2026-11-24 — **A1** base `Controller` now uses `AuthorizesRequests` / `ValidatesRequests`; `authorize()` works.
- 2026-11-24 — **A2** staff profile routes moved to `api/v1/center/profile/{center}`; `api.v1.centers.show` restored.
- 2026-11-24 — **A3** admin controller returns a response, not a resource.
- 2026-11-24 — **A4** `is_active` added to the directory eager-load; unused eager-loads dropped (N5).
- 2026-11-24 — **A5** profile request limits now match the `centers` migration.
- 2026-11-24 — **A6** `Doctor::appointments()` removed until the model exists.
- 2026-11-24 — **A7** `tests/Feature/Centers/` added: registration, admin, directory, profile, policy.
- 2026-11-24 — **B1/B2** redundant `action` field and its request class removed; suspend gated by `can:suspend,center`.
- 2026-11-24 — **B3** every list answers with `{ data, meta: { page, per_page, total } }`; `per_page` validated 1–50 everywhere.
- 2026-11-24 — **B4** `DirectoryFilterRequest` added; LIKE wildcards stripped from filters.
- 2026-11-24 — **B5** `denyAsNotFound()` on the policy; the error renderer learned the 404 authorization status.
- 2026-11-24 — **B6** `PasswordPolicy` holds the one password rule (patient vs staff).
- 2026-11-24 — **B7** `StoreCenterLogo` action: sniffed MIME type, random name, new file stored before the old one is deleted.
- 2026-11-24 — **B8** OpenAPI exports to `public/docs/api/openapi.json`; the stray `api.json` is gone.
- 2026-11-24 — **C9** app-level `server.php` added: only real files are served statically, so `public/docs/api` no longer shadows the `/docs/api` route (C9). `/docs/api` answers 200 again.
- 2026-11-24 — **C1** `RegisterCenterWithStaff` action; the controller only validates and responds.
- 2026-11-24 — **C2** `Weekday` enum; availability times are plain strings again.
- 2026-11-24 — **C3** Google sign-in fails closed on a missing `email_verified` flag.
- 2026-11-24 — **C4/C5/C6/C7/C8** comment, dead-code, naming and response-style cleanup.
- 2026-11-24 — verified in Docker: 101 tests / 366 assertions pass, Pint clean, OpenAPI exported with all 21 documented paths.

## Follow-ups (not part of this plan)

- **Phase 3 must reintroduce the verification gate** on the booking and purchasing routes. The `verified`
  middleware and its alias are in place and covered by a test; no route uses them yet.
- **MySQL parity**: the suite runs on SQLite `:memory:`. The column-width regression (C5) and the `TIME`
  column behaviour (I8) deserve one CI run against MySQL 8 before the next milestone.
- **Larastan** is still not installed; when it lands, run it at level 5+ and fold the findings into a new
  review round.
- **The production web server must not resolve directories either.** `backend/server.php` fixes
  `php artisan serve`; the Caddy configuration of the deployment milestone has to use a `try_files` that
  matches files only, or `public/docs/api` will shadow `/docs/api` in production as well.
