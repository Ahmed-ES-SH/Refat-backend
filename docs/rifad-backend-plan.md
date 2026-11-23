# Rifad (رِفاد) — Backend Plan v2 (Laravel API, phased)

> Project type: **portfolio** (Stripe test mode, demo data, no real patient data).
> Deploy target: **one small VPS** (2 vCPU / 4 GB RAM ideal, 2 GB workable with swap).
> Supersedes the previous backend plan. Companion files: `Refat-PRD.md` (v2) and `rifad-database-schema.md` (apply the deltas in section 6).

---

## 1. Review verdict

The previous plan was already well simplified. What was left over was a second layer of over-engineering, mostly in the data model and the deployment shape. Changes below are all "same demo, less to build, less to run".

| # | What I found | Decision | Why |
|---|---|---|---|
| 1 | `membership_cards` is a strict 1:1 table with `subscriptions`; `valid_to` just copies `current_period_end` | **Merge into `subscriptions`** (`card_no`, `card_token`, `card_issued_at`) | One table, one fewer sync bug, same QR/verify feature |
| 2 | `membership_card_members` pivot + UI to pick covered members | **Cut.** Covered people are computed: owner + the owner's first `member_limit − 1` family members (by id) | Keeps the "Family plan covers 4" demo story without a table or screen |
| 3 | `specialties` table, seeded, never edited | **PHP enum `Specialty`** + `doctors.specialty` string column | 12 fixed values do not need a table, FK, or endpoint |
| 4 | Nightly Stripe reconciliation job | **Cut** | Card status is derived from `status` + `current_period_end`, so a missed webhook cannot leave a card wrongly active for long; Stripe retries webhooks for days |
| 5 | Nightly stale-upload cleanup job | **Cut.** Delete the file in the same request if the DB insert fails | Uploads only exist as part of creating a message |
| 6 | CMS saves call a Next.js revalidate endpoint with a shared secret | **Cut.** Frontend uses time-based revalidation (60 s) | Removes a secret, an env var, and a cross-service call |
| 7 | Newsletter (double opt-in, unsubscribe, admin list) | **Cut** (moved to "Later") | Marketing feature, not part of the healthcare story, adds mail volume on a small server |
| 8 | Three hosts (`app.`, `api.`, `ws.`) + CORS + cookie-domain config | **One origin, path routing in Caddy** | Removes CORS, `SESSION_DOMAIN`, and the classic Sanctum cross-subdomain debugging |
| 9 | MinIO in dev, S3/R2 in prod | **Local disks**: `public` for logos/photos, private `local` disk + signed URLs for chat media | One less container and SDK; swap to S3 later by changing `FILESYSTEM_DISK` |
| 10 | Separate scheduler container | **Host cron** runs `schedule:run` every minute | One less always-on container |
| 11 | 100–200 virtual-user load target | **50–100 VUs**, honest numbers published | Realistic for 2 vCPU; honest results beat a big claim |
| 12 | `activity_logs` | **Stretch only**, last item of Phase 6 | Nice, not needed |
| 13 | Pest *or* PHPUnit, Cashier *or* SDK | **Pest + Stripe PHP SDK** | Decide once. Custom webhook handling is the thing this project is showing off |

**Kept on purpose** (these are what a reviewer will look for): computed slots, DB-level double-booking guard + concurrency test, Stripe Checkout + signed idempotent webhooks, Reverb chat with channel authorization, Policies + 404-not-403 isolation, rate limits, CI, health check, tested restore.

Final size: **14 own tables** (was 16 core + 2 stretch) + 3 Laravel defaults (`password_reset_tokens`, `notifications`, `failed_jobs`).

---

## 2. Scope tiers

| Tier | Features |
|---|---|
| **Must** | Auth (email + password, Google), email verification, password reset, profile · Family members · Center self-register → admin approves · Doctors, weekly availability, days off, public directory · Booking (book / cancel / reschedule / statuses) · Packages, Stripe Checkout subscription, webhooks, payment history, Customer Portal, membership card + verify · Chat (text + image) over Reverb · In-app + email notifications · Admin (users, centers, packages) · 3 roles |
| **Should** | Voice messages (≤ 60 s) · Homepage CMS (6 fixed section types) · 24 h reminders · OpenAPI docs (Scramble) · k6 script · Nightly demo reset |
| **Stretch** | Activity log for admin actions · Typing indicator · ICS download · Per-member card coverage picker |
| **Cut** | National ID · Session/device management · Super Admin and role editor · Branches · Services · Slots table + generator · Benefits table · Gateway abstraction · Refunds/dunning · CMS schema engine · Notification preferences · Settings page · Newsletter · Horizon/Octane · PITR · Staging · Legal workstream · "1,000 concurrent users" |

---

## 3. Stack and structure

**Stack:** Laravel (latest stable, PHP 8.3+), MySQL 8, Redis (cache, queue, sessions, rate limits), Laravel Reverb, Sanctum (SPA cookie auth), Socialite (Google), Stripe PHP SDK, `laravel-lang/lang` (Arabic validation messages), Scramble (OpenAPI), Pest, Larastan, Pint.
**Mail:** Mailpit in dev; a free-tier transactional provider in production (use its API or port 587; do not self-host mail).
**Files:** local disks only (see review item 9).

```
app/
  Actions/        BookAppointment, CancelAppointment, RescheduleAppointment,
                  ProcessStripeEvent, IssueMembershipCard
  Enums/          UserRole, AppointmentStatus, SubscriptionStatus, CenterStatus,
                  MessageType, Specialty, ...
  Http/Controllers/Api/V1/{Auth,Family,Centers,Booking,Billing,Chat,Notifications,Cms,Admin}
  Http/Requests/  Http/Resources/  Policies/
  Models/  Notifications/  Jobs/  Console/Commands/
routes/api_v1.php  routes/channels.php
```

Rules: standard Laravel, no module packages, no repositories, no CQRS. An `Action` class only where a rule spans several steps. Controllers stay thin.

---

## 4. Small-VPS deployment shape

**Containers (prod):** `caddy`, `app` (php-fpm), `queue` (`queue:work --max-time=3600`), `reverb`, `mysql`, `redis`, plus the Next.js container. Scheduler = host cron: `* * * * * docker compose exec -T app php artisan schedule:run`.

**Caddy single-origin routing (TLS automatic):**

| Path | Goes to |
|---|---|
| `/api/*`, `/sanctum/*`, `/docs/api`, `/storage/*` | Laravel (php-fpm) |
| `/ws/*` (prefix stripped → Reverb `/app/{key}`) | Reverb |
| everything else | Next.js |

**Tuning that matters on 2–4 GB:**
- MySQL: `innodb_buffer_pool_size=256M`, `performance_schema=OFF`.
- Redis: `maxmemory 128mb` with **`noeviction`** (queues live there; LRU eviction would drop jobs).
- PHP-FPM: `pm=dynamic`, `pm.max_children≈8`, OPcache on, `config:cache route:cache event:cache`.
- 1–2 GB swap as a safety net; `docker stats` check after each phase.
- Needs a domain (a free subdomain is fine) so Caddy can issue TLS.

---

## 5. Phases

Each phase ends with something demo-able. Frontend work for the same phase happens in parallel (no backend feature without a screen). Estimate: solo, part-time, ≈ 13 weeks.

### Phase 0 — Foundation (week 1)
**Build**
- Laravel project, dev Docker Compose (app, Caddy, MySQL, Redis, Mailpit, Reverb), `.env.example`.
- Sanctum SPA config for a single origin, error envelope `{ message, code, errors }`, request-ID middleware, JSON logs, `/up`.
- Enums, `lang/en` + `lang/ar`, Scramble, Pint, Larastan, Pest.
- GitHub Actions: Pint → Larastan → Pest (MySQL + Redis services).
- **Export OpenAPI docs**: `php artisan scramble:export` to generate `public/docs/api/openapi.json`; serve at `/docs/api` via Caddy.

**Exit:** CI green; frontend "hello" page calls `GET /api/v1/ping` through Caddy with a real cookie session; OpenAPI docs accessible at `/docs/api`.

### Phase 1 — Identity and family (weeks 2–3)
**Build**
- Register (patient or center), login/logout, email verification, forgot/reset (60 min, single use), change email/password (needs current password).
- Google OAuth (link only when the Google email is verified and matches).
- `GET/PATCH /me` returning `{ id, name, email, role, email_verified, locale, center_id?, permissions[] }`; role → permissions static map in config.
- Login throttling per IP + identifier; suspended users cannot log in; unverified users get `403 EMAIL_NOT_VERIFIED` on book/buy.
- Family members CRUD, owner-scoped, **404 for foreign IDs**, delete blocked with `MEMBER_HAS_FUTURE_APPOINTMENTS` (wired fully in Phase 3).

**Tests:** throttling, verification gate, 404-vs-403 isolation, reset token expiry and single use.
**Exit:** register → verify → login → add family member.
**OpenAPI:** Run `php artisan scramble:export` after phase completion; verify new auth/family endpoints appear in `/docs/api`.

### Phase 2 — Centers, doctors, availability, directory (weeks 4–5)
**Build**
- Center registration (creates `center_staff` user + center in `pending`); admin approve/suspend endpoints (minimal admin here, rest in Phase 6).
- Staff scoping by `users.center_id` + Policies; center profile + logo upload (public disk).
- Doctors CRUD (`specialty` enum, `is_active`), weekly `availability_rules`, `days_off`.
- Public directory: filter by city, specialty, name; page-based (`per_page` ≤ 50); only `active` centers; cached with short TTL + bust on change.
- **Slot computation (no slots table):** rules for the weekday (center timezone → UTC) − days off − active appointments − past times. `GET /doctors/{id}/slots?date=` → `[{ starts_at, ends_at }]`.

**Tests:** slot computation unit tests (multiple windows, days off, timezone/DST-free Gulf zones, past times), center isolation.
**Exit:** center registers → admin approves → appears in directory with bookable slots.
**OpenAPI:** Run `php artisan scramble:export` after phase completion; verify centers/doctors/availability/slots endpoints appear in `/docs/api`.

### Phase 3 — Booking and notifications (weeks 6–7)
**Build**
- Actions: **Book**, **Cancel** (until `CANCEL_CUTOFF_HOURS`, default 12), **Reschedule** (one transaction, updates the same row).
- Lifecycle `pending → confirmed → completed`, or `cancelled`. Center confirms; `completed` only when `now ≥ starts_at`.
- **Double-booking defense in depth:** (1) server re-validates the start is a computed slot, (2) DB transaction, (3) **UNIQUE `(doctor_id, starts_at, active_flag)`** with the generated `active_flag` column → duplicate key = `409 SLOT_TAKEN`, (4) concurrency test.
- No overlapping active appointments for the same person.
- Notifications (database + queued mail) on created/confirmed/cancelled/rescheduled; hourly reminder job using `reminder_sent_at` (Should).

**Tests (must):** happy path, cancel cutoff, reschedule atomicity, **parallel requests on one slot → exactly one 201, rest 409**.
**Exit:** patient books for a family member → center confirms → both notified.
**OpenAPI:** Run `php artisan scramble:export` after phase completion; verify booking/appointments endpoints appear in `/docs/api`.

### Phase 4 — Membership and Stripe (weeks 8–9)
**Build**
- Packages (admin CRUD; creating a package creates the Stripe Product + Price; price, currency, interval immutable once subscribed). `benefits` = JSON list of display strings.
- `POST /checkout` (subscription mode, metadata: user, package; block a second active subscription) → `{ url }`.
- `POST /webhooks/stripe`: verify signature on the raw body → insert `stripe_events` (duplicate key = already received, return 2xx) → queue `ProcessStripeEvent` → re-read the Stripe object when events arrive out of order.
- Events (5): `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`.
- `IssueMembershipCard` on first successful payment: `card_no` + opaque `card_token` stored on the subscription. Status is **derived** (never stored). Covered members computed as in review item 2.
- Payments rows from invoices; `POST /billing/portal` (Stripe Customer Portal for cancel/update card/invoices; no custom UI).
- `GET /center/cards/verify` (rate-limited): validity, holder name, package, benefits, covered names. No billing data.
- Success page never grants access; only the webhook does.

**Tests (must):** bad signature, duplicate event, out-of-order event, renewal extends `current_period_end`, failed payment makes card `suspended`, card status derivation table.
**Exit:** buy membership with a test card → card appears → center verifies it.
**OpenAPI:** Run `php artisan scramble:export` after phase completion; verify billing/packages/subscriptions/card verify endpoints appear in `/docs/api`.

### Phase 5 — Chat and live notifications (weeks 10–11)
**Build**
- Reverb behind Caddy `/ws`; `POST /api/broadcasting/auth` with the session cookie.
- Conversations: one per (user, center) (UNIQUE pair), started by the patient; all staff of that center share the inbox.
- Messages: text, then image (JPEG/PNG/WebP, ≤ 5 MB; real MIME sniffing, random names, private disk, signed URLs). **Persist first, broadcast after commit.**
- Channels `private-conversation.{id}` (membership check) and `private-user.{id}` (notification bell).
- `read_at` + unread counters, cursor pagination, `after_id` reconcile, send rate limit ≈ 30/min/user.
- **Last in phase:** voice (≤ 60 s enforced by the client recorder; server checks MIME + size cap). ffprobe duration check = stretch.

**Tests:** channel authorization (outsider rejected), upload rejection (type/size), unread counting, message ordering.
**Exit:** two browsers chat live; the bell updates live; if Reverb is down the UI still works by refetching.
**OpenAPI:** Run `php artisan scramble:export` after phase completion; verify chat/conversations/messages/broadcasting auth endpoints appear in `/docs/api`.

### Phase 6 — Admin and CMS (week 12)
**Build**
- Admin: users list + suspend, centers list + approve/suspend, packages CRUD (finish what Phases 2 and 4 started).
- CMS: `cms_sections` with six fixed types (hero, features, centers carousel, packages, FAQ, CTA); one Form Request that switches rules by `type`; public `GET /cms/home` cached, cache busted on save; `/admin/cms/sections` CRUD + reorder.
- Stretch: `activity_logs` for approve/suspend/package changes.

**Tests:** admin-only access (negative tests for patient and staff), CMS validation per type.
**Exit:** admin edits the homepage → public site updates within a minute.
**OpenAPI:** Run `php artisan scramble:export` after phase completion; verify admin/CMS endpoints appear in `/docs/api`.

### Phase 7 — Hardening, deploy, docs (week 13)
**Build**
- Production Compose + Caddy file, tuned settings from section 4, `deploy.sh` (pull tagged image → `up -d` → poll `/up` → roll back to the previous tag on failure).
- CI/CD: tag → build image → push to GHCR → SSH deploy.
- Backups: host cron daily `mysqldump | gzip` → free object storage (R2/B2) + weekly copy of `storage/app`; **one documented, tested restore**.
- Security pass against section 8; k6 script (browse, login, booking contention, chat send) at **50–100 VUs**; publish the numbers.
- Demo seeder (section 10) + nightly demo reset (skip or re-sync Stripe-linked rows); demo banner.
- README: architecture diagram, setup in ≤ 5 commands, Stripe test cards, load results, known limitations.

**Exit:** public demo URL with demo accounts; all checklist items in section 11 ticked.
**OpenAPI:** Run `php artisan scramble:export` in CI/CD pipeline; final `openapi.json` published at `/docs/api` and used by frontend for type generation.

**Cut order if late:** CMS editor (keep seeded homepage) → voice → reminders → image uploads → nightly reset. **Never cut:** booking concurrency, webhooks, authorization tests.

---

## 6. Data model — 14 own tables

> Canonical column definitions stay in `rifad-database-schema.md`. **Apply these deltas to it:**

| Change | Detail |
|---|---|
| Remove `membership_cards` | Add to `subscriptions`: `card_no` string(20) UNIQUE nullable, `card_token` string(64) UNIQUE nullable, `card_issued_at` timestamp nullable. `valid_to` = `current_period_end` |
| Remove `membership_card_members` | Covered members computed (owner + first `member_limit − 1` family members by id) |
| Remove `specialties` | Add enum `Specialty` (the 12 existing slugs); `doctors.specialty_id` → `doctors.specialty` string(60), index `(specialty)` |
| Remove `newsletter_subscribers` and `NewsletterStatus` | Cut |
| `activity_logs` | Keep as stretch |
| Migration order | `centers, users, password_reset_tokens, family_members, doctors, availability_rules, days_off, appointments, packages, subscriptions, payments, stripe_events, conversations, messages, notifications, cms_sections, failed_jobs` |
| Update "Do NOT create" | Add `membership_cards`, `membership_card_members`, `specialties`, `newsletter_subscribers` |
| Card status rule | Unchanged, now read from `subscriptions`: `cancelled` → `suspended` → `expired` → `active` |

Tables: `users, family_members, centers, doctors, availability_rules, days_off, appointments, packages, subscriptions, payments, stripe_events, conversations, messages, cms_sections` (+ Laravel defaults). All timestamps UTC; money = integer minor units + ISO currency.

---

## 7. API conventions and integration contract

- JSON under `/api/v1`; API Resources for every response; Form Requests for validation.
- Error envelope `{ message, code, errors }`; stable codes: `EMAIL_NOT_VERIFIED`, `SLOT_TAKEN`, `CANCEL_WINDOW_PASSED`, `MEMBER_HAS_FUTURE_APPOINTMENTS`, `CARD_NOT_ACTIVE`.
- Page lists: `{ data, meta: { page, per_page, total } }` (`per_page` ≤ 50). Chat: `{ data, meta: { next_cursor } }` + `?after_id=`.
- `Accept-Language: ar|en` for emails and validation; `PATCH /me { locale }` persists it.
- Dates ISO-8601 UTC; `center.timezone` for display.
- **OpenAPI documentation**: After every completed feature/phase, run `php artisan scramble:export` to regenerate `public/docs/api/openapi.json`. The docs are served at `/docs/api` and used by the frontend for type generation. CI pipeline must include this export step.

**Revised integration contract (mirror this in the frontend plan):**

| Topic | Agreement |
|---|---|
| Origin | **One origin** `https://<domain>`; Caddy routes by path (section 4). No CORS, no `SESSION_DOMAIN` |
| Auth | `GET /sanctum/csrf-cookie` → `POST /api/v1/auth/login` → HttpOnly session cookie → `GET /api/v1/me`. `401` → login; `403` + `code` → contextual message |
| Google | Frontend navigates to `/api/v1/auth/google/redirect`; API redirects to `/auth/callback`; frontend calls `/me` |
| Server-side fetch (Next.js) | Uses `API_INTERNAL_URL=http://caddy` on the Docker network |
| Real-time | Echo with `wsPath: '/ws'`, `authEndpoint: '/api/broadcasting/auth'`; channels `private-user.{id}` (`NotificationCreated`), `private-conversation.{id}` (`MessageSent`, `MessagesRead`); on reconnect refetch with `after_id` |
| Payments | `POST /checkout {package_id}` → `{ url }`; return to `/billing/success` (polls `GET /me/subscriptions` ≤ 30 s) or `/billing/cancel`; `POST /billing/portal` → `{ url }` |
| Uploads | `multipart POST /conversations/{id}/messages` → message; media URLs are temporary signed URLs |
| CMS | `GET /cms/home`; frontend revalidates every 60 s (no callback) |
| Types | Scramble exports `openapi.json` in CI; frontend generates types from it |
| Env (Laravel) | `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, `STRIPE_*`, `REVERB_*` |

---

## 8. Security checklist

- [ ] Policy + scoped query on every endpoint; negative authorization test per role boundary
- [ ] Sanctum SPA cookies (HttpOnly, Secure, SameSite=Lax) + CSRF; no tokens in JS
- [ ] Rate limits by route class: login, password reset, booking, chat send, uploads, card verify
- [ ] Mass-assignment protection, parameterized queries only, no sensitive data in logs
- [ ] Upload validation: size, MIME sniffing, extension allowlist, random names, private disk, signed URLs
- [ ] Stripe signature verification, idempotent events, no card data stored
- [ ] Secrets only in environment variables; `.env` never committed
- [ ] Security headers on responses (Caddy); HTTPS only (HSTS)
- [ ] Demo banner: no real patient data
- [ ] Only ports 22, 80, 443 open on the VPS; MySQL and Redis not published outside the Docker network

---

## 9. Testing

- **Must-have feature tests:** auth throttling and verification gate · role/ownership isolation (404 vs 403) · booking happy path, cancel cutoff, reschedule atomicity · **concurrent booking (1 × 201, rest 409)** · Stripe webhook: bad signature, duplicate, out-of-order, renewal, failed payment · chat channel authorization · upload rejection.
- **Unit tests:** slot computation, cancellation rules, card status derivation.
- **Static:** Pint, Larastan (level 5+) in CI. E2E (Playwright) lives in the frontend repo.
- Test the thing that can go wrong, not every getter.

---

## 10. Seeders and demo data

- Accounts: 1 admin, 1 patient (2 family members, 1 active family membership), 2 center staff (credentials on the demo login page).
- 6–8 active centers, ~20 doctors with weekly availability, 3 packages (Individual Monthly, Family Monthly, Family Yearly), appointments in each status, one sample conversation, default CMS sections.
- README lists Stripe test cards and the webhook setup (Stripe CLI forwarding in dev).

---

## 11. Definition of done (portfolio)

- [ ] All Must features working on the deployed demo
- [ ] Concurrency and webhook tests green in CI
- [ ] OpenAPI docs at `/docs/api`
- [ ] HTTPS, `/up` health check, backup + one tested restore
- [ ] Seeded demo accounts (+ nightly reset if kept)
- [ ] `docker stats` shows the stack fits the VPS with headroom
- [ ] README: architecture diagram, ≤ 5-command setup, Stripe test cards, load-test results, known limitations
