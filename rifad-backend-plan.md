# Rifad (رِفاد) — Backend Master Plan (Laravel API)

> Companion to `rifad-frontend-plan.md`. Section 13 (Integration Contract) and Section 14 (Milestones) are identical in both files and are the source of truth for how the two projects connect.
> Project type: **portfolio** (Stripe test mode, demo data, no real patient data). Target level: junior → mid.

---

## 1. Goals and principles

**What this backend must prove to a reviewer**
1. Clean, conventional Laravel architecture (thin controllers, Form Requests, Resources, Policies, Actions for real business rules).
2. A **concurrency-safe booking** flow (no double-booking, proven by a test).
3. A **correct Stripe subscription flow** (Checkout + signed, idempotent webhooks).
4. **Real-time chat** and notifications over Reverb.
5. Security by default: authorization on every endpoint, safe uploads, rate limits.
6. Production-style delivery: Docker, CI, health checks, backups, documented API.

**Principles**
- Standard Laravel structure. No module packages, no CQRS, no repositories-for-everything.
- An `Action` class only where a business rule spans several steps (book, cancel, reschedule, handle Stripe event).
- Every feature ships with tests for its critical rule, not for every getter.
- No backend feature without a matching frontend screen (and vice versa).

---

## 2. Scope after simplification

| Tier | Features |
|---|---|
| **Must** | Auth (email + password, Google), email verification, password reset, profile · Family members · Centers (self-register → admin approves), doctors, specialties, weekly availability + days off, public directory · Booking (book / cancel / reschedule / statuses) · Packages, Stripe Checkout subscription, webhooks, payment history, membership card + verify · Chat (text + image) via Reverb · In-app + email notifications · Admin dashboard (users, centers, packages) · RBAC with 3 roles |
| **Should** | Voice messages (≤ 60 s) · Homepage CMS (fixed section types) · 24h appointment reminders · OpenAPI docs (Scramble) · Small k6 load script |
| **Stretch** | Newsletter (double opt-in) · ICS download · Activity log for admin actions · Typing/presence · Delivered-status for messages |
| **Cut** | National ID login/storage · Device/session management · Super Admin + role-editor UI · Branches · Services entity · Pre-generated slots table + generator job · Benefits table + redemption · `PaymentGateway` abstraction · Refund/dunning flows · JSON-schema CMS builder · Notification preferences · Platform-settings page · Horizon/Octane · PITR/binlog backups · Staging environment · Legal/data-residency workstream · "1,000 concurrent users" target |

---

## 3. Stack and project structure

**Stack:** latest stable Laravel (PHP 8.3+), MySQL 8, Redis (cache, queue, rate limiting), Laravel Reverb, Sanctum (SPA cookie auth), Socialite (Google), Stripe PHP SDK (Laravel Cashier is an acceptable alternative if you want less custom code), Scramble (OpenAPI), Pest or PHPUnit, Larastan, Pint.
**Storage:** local/MinIO in dev → S3-compatible bucket (R2/S3) in production, private disk + temporary signed URLs.
**Mail:** Mailpit in dev, a free-tier transactional provider in production.

**Structure (standard Laravel, grouped by feature inside each folder)**
```
app/
  Actions/            BookAppointment, CancelAppointment, RescheduleAppointment, ProcessStripeEvent, IssueMembershipCard
  Enums/              AppointmentStatus, SubscriptionStatus, CardStatus, MessageType, CenterStatus
  Http/Controllers/Api/V1/{Auth,Family,Centers,Booking,Billing,Chat,Notifications,Cms,Admin}
  Http/Requests/  Http/Resources/  Policies/
  Models/  Notifications/  Jobs/  Console/Commands/
routes/api_v1.php  routes/channels.php
```

---

## 4. Feature plans

### 4.1 Auth and account
- Register (patient or center), login/logout via Sanctum SPA cookies, email verification (signed link → frontend page), forgot/reset password (60 min, single use), Google OAuth (link to existing account only when Google email is verified and matches).
- Change email/password requires current password.
- Login throttling per IP + identifier; generic error messages.
- Unverified users cannot book or buy (`403 EMAIL_NOT_VERIFIED`).
- Center registration creates a user with role `center_staff` and a center in `pending` status.
- **Endpoints:** `auth/register|login|logout|forgot-password|reset-password`, `auth/google/redirect|callback`, `email/verify/resend`, `GET /me`, `PATCH /me`.

### 4.2 Family members
- CRUD for a patient's dependants (name, relation, date of birth, gender).
- Owner-scoped queries; other users' records return **404**, not 403.
- Deleting a member with future active appointments is blocked with a clear code.
- **Endpoints:** `GET|POST /family-members`, `PATCH|DELETE /family-members/{id}`.

### 4.3 Centers, doctors, availability, directory
- Center profile (name, description, logo, city, address, coordinates, phone, license number as text, timezone, status: pending/active/suspended).
- Staff users belong to one center (`users.center_id`); every center query is scoped by it and guarded by Policies.
- Specialties are a seeded global list; each doctor has one specialty.
- **Availability:** weekly rules per doctor (weekday, start, end, slot length) + days off. **Slots are computed on request** (rules − days off − active appointments). There is no slots table and no generator job.
- Public directory: filter by city, specialty, name; paginated; suspended/pending centers are hidden.
- **Endpoints:** `GET /centers`, `GET /centers/{id}`, `GET /centers/{id}/doctors`, `GET /doctors/{id}/slots?date=`; center side: `/center/profile|doctors|availability|days-off|appointments`.

### 4.4 Booking
- Actions: **Book**, **Cancel** (allowed until a configured cutoff, e.g. 12 h before), **Reschedule** (one transaction: reserve new, release old).
- Lifecycle: `pending → confirmed → completed`, or `cancelled`. Center confirms/completes.
- **Double-booking prevention (defense in depth)**
  1. Server re-validates that the requested start is a valid computed slot.
  2. Insert runs in a DB transaction.
  3. DB-level guarantee: a generated "active" column + **UNIQUE** index over `(doctor_id, starts_at, active_flag)`; duplicate-key → `409 SLOT_TAKEN`.
  4. A concurrency test fires parallel requests at one slot: exactly one `201`, the rest `409`.
- A person cannot hold two overlapping active appointments.
- Every status change dispatches a notification.
- **Endpoints:** `POST /appointments`, `GET /appointments`, `POST /appointments/{id}/cancel|reschedule`; center: `POST /center/appointments/{id}/confirm|complete`.

### 4.5 Membership and billing (Stripe, test mode)
- **Packages** (admin CRUD): name, description, price (minor units) + currency, interval (month/year), member limit, benefits as a plain text list (display only), `stripe_price_id`.
- **Flow:** `POST /checkout` creates a Stripe Checkout Session (subscription mode, user/package in metadata) → returns `{url}`. The success page **never grants access**; only a verified webhook does.
- **Webhook rules:** verify signature on the raw body · store `event.id` in `stripe_events` (idempotent) · return 2xx fast and process in a queued job · re-read the Stripe object when events arrive out of order.
- **Events handled (5):** `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`.
- **Customer Portal** session for cancel / update card / invoices (no custom UI for these).
- **Membership card:** issued on first successful payment; card number + opaque random token (no personal data in the QR); status is **derived** from subscription state; covers the owner + up to `member_limit` family members.
- **Card verify (center staff):** lookup by number/token → validity, holder name, package, benefits; no billing data; rate-limited.
- Never store card data. Keep only Stripe IDs, brand, last4. Money = integer minor units + ISO currency (pick a currency Stripe test mode accepts; verify early, USD as fallback).
- **Endpoints:** `GET /packages`, `POST /checkout`, `POST /billing/portal`, `GET /me/subscriptions|cards|payments`, `POST /webhooks/stripe`, `GET /center/cards/verify`.

### 4.6 Chat (Reverb)
- One conversation per (user, center); started by the user; all staff of that center share the inbox.
- Message types: text, image (JPEG/PNG/WebP, ≤ 5 MB), voice (≤ 60 s; the limit is enforced by the client recorder, with a size cap + MIME check on the server; ffprobe duration check is a stretch).
- **Persist first, then broadcast** (after commit). Private channel `conversation.{id}` authorized by membership (the user, or staff of that center).
- Read state: `read_at` + unread counters. Clients reconcile after reconnect with `after_id`. Cursor pagination for history.
- Upload hardening: real MIME sniffing, extension allowlist, random file names, private disk, short-lived signed URLs.
- Rate limit sending (≈ 30/min/user).
- **Endpoints:** `GET|POST /conversations`, `GET|POST /conversations/{id}/messages`, `POST /conversations/{id}/read`.

### 4.7 Notifications
- Laravel Notifications with three channels: database, queued mail, broadcast on `private-user.{id}`.
- Triggers: appointment created / confirmed / cancelled / rescheduled, 24h reminder, payment succeeded / failed, membership issued, account/security emails.
- No per-category preferences UI.
- **Endpoints:** `GET /notifications`, `POST /notifications/{id}/read`, `POST /notifications/read-all`.

### 4.8 CMS (homepage)
- `cms_sections`: key, type, content (JSON), is_active, sort_order.
- Fixed section types: hero, features, centers carousel, packages, FAQ, CTA. Each type validated by its own Form Request rules (no schema engine).
- Public `GET /cms/home` is cached; cache busted on save; on save the API calls the frontend's revalidate endpoint (secret + tag).
- **Endpoints:** `GET /cms/home`, `/admin/cms/sections` (CRUD + reorder).

### 4.9 Admin and RBAC
- Roles: `patient`, `center_staff`, `admin` (a `users.role` column + a static role→permissions map in config; **no role editor**, no Super Admin, no permissions package).
- Admin features: users (list, suspend), centers (approve/suspend), packages CRUD, CMS.
- Authorization = route middleware + Policies + scoped queries. `/me` returns role + permissions for UI convenience only.
- Stretch: `activity_log` for suspend/approve/package changes (actor, target, IP, time).

### 4.10 Newsletter (stretch)
- Public subscribe with double opt-in, one-click unsubscribe, admin list. No campaigns, no CSV export.

---

## 5. Data model (≈ 16 own tables)

> **Canonical, column-level definition: `rifad-database-schema.md`.** The table below is only a summary; if the two differ, the schema file wins.

| Table | Key columns / constraints |
|---|---|
| `users` | email (unique), password, role, locale, google_id, email_verified_at, status, center_id (nullable) |
| `family_members` | user_id, name, relation, dob, gender |
| `centers` | name, description, city, address, lat/lng, phone, license_no, timezone, status, logo |
| `specialties` | name |
| `doctors` | center_id, specialty_id, name, bio, photo, is_active |
| `availability_rules` | doctor_id, weekday, start, end, slot_minutes |
| `days_off` | doctor_id, date, reason |
| `appointments` | user_id, family_member_id (nullable), doctor_id, starts_at, ends_at, status, notes, cancelled_at, **UNIQUE(doctor_id, starts_at, active_flag)** |
| `packages` | name, price_minor, currency, interval, member_limit, benefits (JSON), stripe_price_id, is_active |
| `subscriptions` | user_id, package_id, stripe_subscription_id (unique), status, current_period_end, cancel_at_period_end |
| `membership_cards` | subscription_id, card_no (unique), token (unique), valid_from, valid_to + pivot of covered members |
| `payments` | user_id, subscription_id, stripe_invoice_id (unique), amount_minor, currency, status, invoice_url |
| `stripe_events` | id = Stripe event id (PK), type, processed_at |
| `conversations` | user_id, center_id (**UNIQUE pair**), last_message_at |
| `messages` | conversation_id, sender_id, type, body, media_path, duration_s, read_at |
| `cms_sections` · `newsletter_subscribers` (stretch) · `activity_log` (stretch) · Laravel `notifications` | — |

**Indexes to add:** `appointments(doctor_id, starts_at)`, `appointments(user_id, status)`, `messages(conversation_id, id)`, `payments(user_id, created_at)`, `subscriptions(user_id, status)`, `centers(city, status)`.
All timestamps stored in UTC.

---

## 6. API conventions
- JSON under `/api/v1`; Laravel API Resources for every response; Form Requests for validation.
- Error envelope `{ message, code, errors }` with stable machine codes (e.g. `EMAIL_NOT_VERIFIED`, `SLOT_TAKEN`, `CANCEL_WINDOW_PASSED`, `MEMBER_HAS_FUTURE_APPOINTMENTS`, `CARD_NOT_ACTIVE`).
- Pagination: page-based for lists (`per_page` max 50), cursor-based for chat.
- `Accept-Language: ar|en` for validation messages and emails.
- OpenAPI generated by Scramble and exported in CI (consumed by the frontend for types).

---

## 7. Security checklist
- [ ] Policy + scoped query on every endpoint; negative authorization tests for each role boundary
- [ ] Sanctum SPA cookies (HttpOnly, Secure, SameSite=Lax) + CSRF; strict CORS allowlist; no tokens in JS
- [ ] Rate limiting by route class: login, password reset, booking, chat send, uploads, card verify
- [ ] Mass-assignment protection, parameterized queries only, no sensitive data in logs
- [ ] Upload validation (size, MIME sniffing, extension allowlist, random names, private disk, signed URLs)
- [ ] Stripe signature verification, idempotent events, no card data stored
- [ ] Secrets only in environment variables; `.env` never committed
- [ ] Security headers on API responses; HTTPS only (HSTS)
- [ ] Demo-data banner/disclaimer: no real patient data

---

## 8. Performance (right-sized)
- Eager load everywhere; `Model::preventLazyLoading()` outside production.
- Cache directory filters, packages, and CMS (short TTL + explicit bust).
- Queues for mail, notifications, Stripe event processing.
- Production: `config:cache`, `route:cache`, `event:cache`, OPcache.
- **Target:** a small k6 script (browse + login + booking contention + chat send) at ~100–200 virtual users on the demo VPS, p95 < 500 ms on read endpoints. Publish the numbers in the README.

---

## 9. Background work
| Type | Item |
|---|---|
| Queue | Mail/notification sending · `ProcessStripeEvent` |
| Scheduler | Appointment reminders (hourly) · Subscription reconciliation (nightly) · Stale upload cleanup (nightly) · Demo-data reset (nightly, demo environment only) |

---

## 10. Testing
- **Must-have feature tests:** auth throttling and verification gate · role/ownership isolation (404 vs 403) · booking happy path, cancel cutoff, reschedule atomicity · **concurrent booking (1×201, rest 409)** · Stripe webhook: bad signature, duplicate event, out-of-order event, renewal extends card, failed payment suspends card · chat channel authorization · upload rejection (type/size).
- **Unit tests:** slot computation, cancellation rules, card status derivation.
- **Static:** Pint, Larastan (level 5+), run in CI with MySQL + Redis services.
- E2E (Playwright) lives in the frontend repo and exercises this API end to end.

---

## 11. DevOps
- **Docker Compose:** app (php-fpm), web server/Caddy, MySQL, Redis, Reverb, queue worker, scheduler; dev extras: Mailpit, MinIO, Stripe CLI (webhook forwarding).
- **Environments:** local and production (staging optional).
- **CI (GitHub Actions):** Pint → Larastan → tests → OpenAPI export → build image. **CD:** tagged release → SSH deploy on the VPS with health-check gate (`/up`) and rollback to the previous image tag.
- **Observability:** request-ID middleware + structured logs; Sentry (free tier) optional; uptime ping optional.
- **Backups:** daily `mysqldump` to object storage + one documented, tested restore.

---

## 12. Seeders and demo data
- Demo accounts: patient (with 2 family members), center staff, admin (credentials shown on the demo login page).
- 6–8 centers, ~20 doctors with availability, 3 packages, CMS sections, a sample chat thread.
- README lists Stripe test cards and the webhook setup.

---

## 13. Integration contract (identical in both plans)

| Topic | Agreement |
|---|---|
| **Hosts** | `app.<domain>` = Next.js · `api.<domain>` = Laravel · `ws.<domain>` = Reverb. Shared parent domain so Sanctum cookies work. *Fallback if no custom domain:* Next.js rewrites proxy `/api/*` to Laravel so the browser sees one origin. Decide on day 1. |
| **Auth flow** | `GET /sanctum/csrf-cookie` → `POST /api/v1/auth/login` (credentials included, `X-XSRF-TOKEN`) → HttpOnly session cookie. `GET /api/v1/me` returns `{ id, name, email, role, email_verified, locale, center_id?, permissions[] }`. `401` → login page; `403` + `code` → contextual message. |
| **Google login** | Frontend navigates to `api/v1/auth/google/redirect`; API redirects back to `app/auth/callback`; frontend then calls `/me`. |
| **Env names** | Laravel: `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `STRIPE_*`, `REVERB_*`, `FRONTEND_REVALIDATE_SECRET`. Next.js: `NEXT_PUBLIC_API_URL`, `NEXT_PUBLIC_REVERB_HOST/PORT/KEY`, `REVALIDATE_SECRET`. |
| **Errors** | `{ message, code, errors }`; `422` → `errors` keyed by field; `429` → `Retry-After`; frontend maps `code` to localized text. |
| **Pagination** | Page lists: `{ data, meta: { page, per_page, total } }`. Chat: `{ data, meta: { next_cursor } }` and `?after_id=` for reconcile. |
| **Locale** | `Accept-Language: ar|en` on every request; `PATCH /me { locale }` persists the choice. |
| **Dates & money** | ISO-8601 UTC strings; `center.timezone` for display; money as integer minor units + currency (frontend formats with `Intl`). |
| **Booking** | `GET /doctors/{id}/slots?date=YYYY-MM-DD` → `[{ starts_at, ends_at }]`; `POST /appointments` → `201` or `409 SLOT_TAKEN` or `403 EMAIL_NOT_VERIFIED`. |
| **Payments** | `POST /checkout {package_id}` → `{ url }` → browser redirects to Stripe. Return URLs: `app/billing/success` and `app/billing/cancel`. The success page **polls** `GET /me/subscriptions` (≈ 30 s max) because only the webhook activates. `POST /billing/portal` → `{ url }`. |
| **Real-time** | Echo connects to `ws.<domain>`; auth via `/broadcasting/auth` with the session cookie. Channels: `private-user.{id}` (event `NotificationCreated`), `private-conversation.{id}` (events `MessageSent`, `MessagesRead`). On reconnect: refetch with `after_id`. |
| **Uploads** | `multipart POST /conversations/{id}/messages` (`type=image|voice`, `file`) → returns the message; media URLs are temporary signed URLs (refetch to refresh). |
| **CMS** | Public `GET /cms/home`. On save the API calls `POST app/api/revalidate` with a secret and tag `cms-home`. |
| **Types** | Scramble exports `openapi.json` in CI; the frontend generates TypeScript types from it; CI fails on drift. |

---

## 14. Milestones (solo, part-time estimate ≈ 13 weeks; trim from the bottom of the list if late)

| # | Weeks | Backend deliverables | Frontend deliverables | Integrated, demo-able outcome |
|---|---|---|---|---|
| M0 | 1 | Repo, Docker Compose, CI, Sanctum + CORS, base error envelope, Scramble | Repo, Docker, CI, i18n/RTL shell, UI kit, API client | "Hello" page calls API through the real auth/CORS setup |
| M1 | 2–3 | Auth (email + Google), verification, reset, profile, family, roles | Auth pages, `useMe`, guards, profile, family screens | Register → verify → login → add family member |
| M2 | 4–5 | Centers, doctors, availability, days off, directory, center approval | Directory, center page, center dashboard (profile, doctors, availability), admin approval | Center registers → admin approves → appears in directory |
| M3 | 6–7 | Slots, booking/cancel/reschedule, statuses, concurrency test, notifications (db + mail) | Booking flow, my appointments, center appointments table, bell | Patient books for a family member → center confirms → both notified |
| M4 | 8–9 | Packages, Checkout, webhooks, cards, payments, portal, verify | Packages, checkout redirect, success polling, card + QR, payment history, verify screen | Buy membership (test card) → card appears → center verifies it |
| M5 | 10–11 | Reverb, chat (text/image/voice), read state, broadcast notifications | Chat UI, recorder, upload, real-time cache updates | Two browsers chatting live; notification bell updates live |
| M6 | 12 | CMS, admin polish, newsletter (stretch) | CMS renderer + editor, admin screens | Admin edits homepage → public site updates |
| M7 | 13 | Load test, security pass, backups, deploy, docs | Lighthouse/a11y pass, Playwright suite, deploy, README | Public demo URL with demo accounts |

**Cut order if time runs short:** newsletter → CMS editor (keep seeded homepage) → voice messages → reminders → chat image uploads. Never cut: booking concurrency, webhooks, authorization tests.

---

## 15. Production-ready checklist (portfolio definition of done)
- [ ] All Must features working on the deployed demo
- [ ] Concurrency and webhook tests green in CI
- [ ] OpenAPI docs published (`/docs/api`)
- [ ] HTTPS, health check, error tracking (optional), backup + tested restore
- [ ] Seeded demo accounts + nightly reset
- [ ] README: architecture diagram, setup in ≤ 5 commands, Stripe test cards, load-test results, known limitations
