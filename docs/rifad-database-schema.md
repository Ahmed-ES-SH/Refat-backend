# Rifad — Database Schema (canonical)

**Engine:** MySQL 8 · InnoDB · `utf8mb4_unicode_ci` · Laravel migrations.
**Scope:** portfolio project (Stripe test mode, demo data). Companion docs: `rifad-backend-plan.md`, `rifad-frontend-plan.md`.
**Suggested repo location:** `docs/DATABASE.md` (reference it from `AGENTS.md`).

---

## 0. Rules for AI agents (read first)

1. This file is the **only source of truth** for database structure. Do not add tables, columns, or indexes that are not listed here. If a feature seems to need one, stop and report it instead of inventing it.
2. Create migrations in the order of **§8**, one migration per table, each with a working `down()`.
3. Every foreign key listed gets a real FK constraint with the stated `ON DELETE` behavior.
4. The **Type** column uses Laravel migration method names so it maps 1:1 to migrations.
5. **DB rule** = enforced by a constraint or index. **App rule** = enforced in code (FormRequest / Policy / Action) and covered by a test. Both kinds are listed per table.
6. Never create anything from **§10 (Do NOT create)**.

---

## 1. Global conventions

| Topic | Convention |
|---|---|
| Names | `snake_case`, plural tables, FK = `<singular>_id` |
| Primary keys | `id()` (unsigned bigint auto-increment) unless stated otherwise |
| Timestamps | `timestamps()` on every table unless stated; all datetimes stored in **UTC** |
| Enums | `string` columns cast to PHP backed enums (`app/Enums`). **Never** MySQL `ENUM` |
| Money | `unsignedInteger` minor units (`*_minor`) + `char(3)` ISO currency |
| JSON | Only for `packages.benefits`, `cms_sections.content`, `notifications.data`, `activity_logs.meta` |
| Soft deletes | Only `family_members`. Users, centers, doctors, packages are **deactivated, never deleted** |
| Stripe IDs | `string(64)`, `unique` where stated |
| Local-time columns | `availability_rules.start_time/end_time` are in the **center's timezone**. All `*_at` columns are UTC |
| Language | Stored data is single-language. UI strings are translated in the frontend (`specialties.slug` is the i18n key) |
| Laravel drivers | `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` → **no** `sessions`, `jobs`, `cache` tables |

---

## 2. Enum registry

| Enum | Values | Used by |
|---|---|---|
| `UserRole` | `patient`, `center_staff`, `admin` | `users.role` |
| `UserStatus` | `active`, `suspended` | `users.status` |
| `Locale` | `ar`, `en` | `users.locale` |
| `Gender` | `male`, `female` | `users.gender`, `family_members.gender` |
| `FamilyRelation` | `spouse`, `child`, `parent`, `sibling`, `other` | `family_members.relation` |
| `CenterStatus` | `pending`, `active`, `suspended` | `centers.status` |
| `AppointmentStatus` | `pending`, `confirmed`, `completed`, `cancelled` (**active** = `pending` or `confirmed`) | `appointments.status` |
| `BillingInterval` | `month`, `year` | `packages.interval` |
| `SubscriptionStatus` | `incomplete`, `incomplete_expired`, `trialing`, `active`, `past_due`, `canceled`, `unpaid`, `paused` (mirrors Stripe) | `subscriptions.status` |
| `PaymentStatus` | `succeeded`, `failed` | `payments.status` |
| `MessageType` | `text`, `image`, `voice` | `messages.type` |
| `SenderSide` | `patient`, `center` | `messages.sender_side` |
| `CmsSectionType` | `hero`, `features`, `centers_carousel`, `packages`, `faq`, `cta` | `cms_sections.type` |
| `NewsletterStatus` | `pending`, `subscribed`, `unsubscribed` | `newsletter_subscribers.status` |

---

## 3. Table map

| Domain | Tables |
|---|---|
| Identity | `users`, `password_reset_tokens`, `family_members` |
| Centers | `centers`, `specialties`, `doctors`, `availability_rules`, `days_off` |
| Booking | `appointments` |
| Membership & billing | `packages`, `subscriptions`, `membership_cards`, `membership_card_members`, `payments`, `stripe_events` |
| Chat | `conversations`, `messages` |
| Platform | `notifications`, `cms_sections`, `failed_jobs` |
| Stretch | `newsletter_subscribers`, `activity_logs` |

---

## 4. Table definitions

### 4.1 `users`
Every login account (patient, center staff, admin).

| Column | Type | Null | Default | Constraints / notes |
|---|---|---|---|---|
| id | id | no | | PK |
| name | string(120) | no | | |
| email | string(190) | no | | **UNIQUE** |
| email_verified_at | timestamp | yes | | |
| password | string(255) | yes | | NULL for Google-only accounts |
| google_id | string(64) | yes | | **UNIQUE** |
| role | string(20) | no | `patient` | `UserRole` |
| status | string(20) | no | `active` | `UserStatus` |
| phone | string(30) | yes | | |
| date_of_birth | date | yes | | |
| gender | string(10) | yes | | `Gender` |
| locale | string(2) | no | `ar` | `Locale` |
| center_id | foreignId | yes | | → `centers.id`, `restrictOnDelete`. Only for `center_staff` |
| stripe_customer_id | string(64) | yes | | **UNIQUE** |
| created_at / updated_at | timestamps | | | |

- **Index:** `(role, status)`.
- **App rules:** `center_staff` ⇔ `center_id` is set; other roles have it NULL. One staff account per center in the MVP (the schema allows more). Unverified users (`email_verified_at` NULL) cannot book or buy. Suspended users cannot log in. Google linking only when the Google email is verified and matches an existing email.

### 4.2 `password_reset_tokens`
Laravel default table (email PK, token, created_at). Token lifetime 60 minutes, single use.

### 4.3 `family_members`
Dependants of a patient (not login accounts).

| Column | Type | Null | Default | Constraints / notes |
|---|---|---|---|---|
| id | id | no | | PK |
| user_id | foreignId | no | | → `users.id`, `cascadeOnDelete` |
| name | string(120) | no | | |
| relation | string(20) | no | | `FamilyRelation` |
| date_of_birth | date | no | | |
| gender | string(10) | no | | `Gender` |
| deleted_at | softDeletes | yes | | keeps appointment history readable |
| created_at / updated_at | timestamps | | | |

- **Index:** `(user_id)`.
- **App rules:** only the owner can read or change a member; any other access returns **404** (not 403). Delete is blocked while the member has future active appointments (code `MEMBER_HAS_FUTURE_APPOINTMENTS`).

### 4.4 `centers`

| Column | Type | Null | Default | Constraints / notes |
|---|---|---|---|---|
| id | id | no | | PK |
| name | string(150) | no | | |
| description | text | yes | | |
| logo_path | string(255) | yes | | private/public disk path |
| phone | string(30) | yes | | |
| email | string(190) | yes | | contact email (not a login) |
| country_code | char(2) | no | `SA` | |
| city | string(80) | no | | |
| address | string(255) | yes | | |
| latitude | decimal(10,7) | yes | | |
| longitude | decimal(10,7) | yes | | |
| license_no | string(60) | yes | | plain text, informational |
| timezone | string(40) | no | `Asia/Riyadh` | IANA timezone; used to read availability and display times |
| status | string(20) | no | `pending` | `CenterStatus` |
| approved_at | timestamp | yes | | |
| created_at / updated_at | timestamps | | | |

- **Indexes:** `(status, city)`, `(name)`.
- **App rules:** a center registers as `pending`; an admin approves (`active`) or suspends. Only `active` centers appear in the public directory or accept bookings. Suspended centers keep their existing appointments.

### 4.5 `specialties`
Global reference list (seeded). No timestamps.

| Column | Type | Null | Constraints / notes |
|---|---|---|---|
| id | id | no | PK |
| slug | string(60) | no | **UNIQUE**; frontend i18n key |
| name | string(100) | no | default English label |

### 4.6 `doctors`

| Column | Type | Null | Default | Constraints / notes |
|---|---|---|---|---|
| id | id | no | | PK |
| center_id | foreignId | no | | → `centers.id`, `restrictOnDelete` |
| specialty_id | foreignId | no | | → `specialties.id`, `restrictOnDelete` |
| name | string(120) | no | | |
| bio | text | yes | | |
| photo_path | string(255) | yes | | |
| is_active | boolean | no | `true` | deactivate instead of deleting |
| created_at / updated_at | timestamps | | | |

- **Indexes:** `(center_id, is_active)`, `(specialty_id)`.
- **App rules:** staff manage only doctors of their own center.

### 4.7 `availability_rules`
Weekly recurring working windows per doctor. Slots are **computed** from these, never stored.

| Column | Type | Null | Default | Constraints / notes |
|---|---|---|---|---|
| id | id | no | | PK |
| doctor_id | foreignId | no | | → `doctors.id`, `cascadeOnDelete` |
| weekday | unsignedTinyInteger | no | | **0 = Sunday … 6 = Saturday** (matches Carbon `dayOfWeek`) |
| start_time | time | no | | center-local time |
| end_time | time | no | | center-local time |
| slot_minutes | unsignedSmallInteger | no | `30` | |
| created_at / updated_at | timestamps | | | |

- **Index:** `(doctor_id, weekday)`.
- **App rules:** `end_time > start_time`; the window length is a multiple of `slot_minutes`; windows for the same doctor and weekday must not overlap. Several windows per day are allowed (e.g. morning and evening).

### 4.8 `days_off`
Full-day exceptions (holidays, leave).

| Column | Type | Null | Constraints / notes |
|---|---|---|---|
| id | id | no | PK |
| doctor_id | foreignId | no | → `doctors.id`, `cascadeOnDelete` |
| date | date | no | center-local calendar date |
| reason | string(150) | yes | |
| created_at / updated_at | timestamps | | |

- **DB rule:** **UNIQUE** `(doctor_id, date)`.

### 4.9 `appointments`

| Column | Type | Null | Default | Constraints / notes |
|---|---|---|---|---|
| id | id | no | | PK |
| user_id | foreignId | no | | booker/owner → `users.id`, `restrictOnDelete` |
| family_member_id | foreignId | yes | | → `family_members.id`, `restrictOnDelete`. NULL = booked for self |
| doctor_id | foreignId | no | | → `doctors.id`, `restrictOnDelete` |
| center_id | foreignId | no | | → `centers.id`, `restrictOnDelete`. Denormalized from the doctor, for scoping |
| starts_at | dateTime | no | | UTC |
| ends_at | dateTime | no | | UTC (= `starts_at` + rule `slot_minutes`) |
| status | string(20) | no | `pending` | `AppointmentStatus` |
| notes | text | yes | | patient note (max 1000 chars, app rule) |
| cancelled_at | timestamp | yes | | |
| reminder_sent_at | timestamp | yes | | makes the 24h reminder job idempotent |
| active_flag | tinyInteger | yes | | **generated, stored:** `CASE WHEN status IN ('pending','confirmed') THEN 1 ELSE NULL END` (Laravel `storedAs`) |
| created_at / updated_at | timestamps | | | |

- **DB rule (double-booking guard):** **UNIQUE** `(doctor_id, starts_at, active_flag)`. NULL values do not collide in MySQL, so cancelled/completed rows free the slot. A duplicate-key error maps to `409 SLOT_TAKEN`.
- **Other indexes:** `(user_id, status, starts_at)`, `(center_id, status, starts_at)`. (A separate `(doctor_id, starts_at)` index is unnecessary; the unique index covers it.)
- **App rules (inside one DB transaction in the `BookAppointment` action):**
  - `center_id` = `doctor.center_id`; the center is `active`; the doctor is `is_active`.
  - `starts_at` must equal a valid **computed** slot (rules − days off), be in the future, and match the doctor's `slot_minutes`.
  - If `family_member_id` is set it must belong to `user_id`.
  - The same person (same `user_id` + same `family_member_id`, NULL = self) cannot have two overlapping active appointments.
  - The user must be email-verified.
- **Transitions:** `pending → confirmed → completed`; `pending|confirmed → cancelled`. `completed` and `cancelled` are terminal. The center confirms; it may mark `completed` only when `now ≥ starts_at`.
- **Cancel:** allowed until `starts_at − CANCEL_CUTOFF_HOURS` (config, default 12), else `CANCEL_WINDOW_PASSED`.
- **Reschedule:** a single transaction that updates `doctor_id` / `starts_at` / `ends_at` on the same row; the unique index rejects conflicts.

### 4.10 `packages`
Membership plans (admin-managed).

| Column | Type | Null | Default | Constraints / notes |
|---|---|---|---|---|
| id | id | no | | PK |
| name | string(100) | no | | |
| description | text | yes | | |
| price_minor | unsignedInteger | no | | |
| currency | char(3) | no | `USD` | use a currency Stripe test mode accepts |
| interval | string(10) | no | | `BillingInterval` |
| member_limit | unsignedTinyInteger | no | `1` | total people covered, **including the owner** |
| benefits | json | no | `[]` | array of display strings |
| stripe_product_id | string(64) | yes | | |
| stripe_price_id | string(64) | yes | | **UNIQUE** |
| is_active | boolean | no | `true` | deactivate instead of deleting |
| sort_order | unsignedSmallInteger | no | `0` | |
| created_at / updated_at | timestamps | | | |

- **App rule:** `price_minor`, `currency`, and `interval` cannot change once a subscription exists (Stripe prices are immutable). Create a new package instead.

### 4.11 `subscriptions`

| Column | Type | Null | Default | Constraints / notes |
|---|---|---|---|---|
| id | id | no | | PK |
| user_id | foreignId | no | | → `users.id`, `restrictOnDelete` |
| package_id | foreignId | no | | → `packages.id`, `restrictOnDelete` |
| stripe_subscription_id | string(64) | no | | **UNIQUE** |
| status | string(30) | no | | `SubscriptionStatus` (copied from Stripe) |
| current_period_start | timestamp | yes | | |
| current_period_end | timestamp | yes | | |
| cancel_at_period_end | boolean | no | `false` | |
| canceled_at | timestamp | yes | | |
| ended_at | timestamp | yes | | |
| created_at / updated_at | timestamps | | | |

- **Index:** `(user_id, status)`.
- **App rules:** rows are created/updated **only by verified webhooks** (never by the success page). MVP allows one non-canceled subscription per user; block a second checkout.

### 4.12 `membership_cards`
Digital card issued on the first successful payment. **No `status` column** (see §7).

| Column | Type | Null | Constraints / notes |
|---|---|---|---|
| id | id | no | PK |
| subscription_id | foreignId | no | → `subscriptions.id`, `cascadeOnDelete`, **UNIQUE** (one card per subscription) |
| card_no | string(20) | no | **UNIQUE**, human-readable (e.g. `RF-7K2M-9QX4`) |
| token | string(64) | no | **UNIQUE**, random opaque string used in the QR (no personal data) |
| valid_from | timestamp | no | |
| valid_to | timestamp | no | updated to `current_period_end` on each `invoice.paid` |
| created_at / updated_at | timestamps | | |

### 4.13 `membership_card_members`
Family members covered by a card (the owner is always implicitly covered).

| Column | Type | Null | Constraints / notes |
|---|---|---|---|
| card_id | foreignId | no | → `membership_cards.id`, `cascadeOnDelete` |
| family_member_id | foreignId | no | → `family_members.id`, `restrictOnDelete` |

- **DB rule:** composite **PK** `(card_id, family_member_id)`. No timestamps.
- **App rules:** the member must belong to the card owner; count ≤ `package.member_limit − 1`.

### 4.14 `payments`
One row per Stripe invoice outcome (created from webhooks).

| Column | Type | Null | Default | Constraints / notes |
|---|---|---|---|---|
| id | id | no | | PK |
| user_id | foreignId | no | | → `users.id`, `restrictOnDelete` |
| subscription_id | foreignId | yes | | → `subscriptions.id`, `nullOnDelete` |
| stripe_invoice_id | string(64) | no | | **UNIQUE** |
| stripe_payment_intent_id | string(64) | yes | | |
| amount_minor | unsignedInteger | no | | |
| currency | char(3) | no | | |
| status | string(20) | no | | `PaymentStatus` |
| invoice_url | string(500) | yes | | Stripe hosted invoice URL |
| paid_at | timestamp | yes | | |
| created_at / updated_at | timestamps | | | |

- **Index:** `(user_id, created_at)`.
- **App rule:** never store card data; Stripe holds it.

### 4.15 `stripe_events`
Webhook idempotency log.

| Column | Type | Null | Constraints / notes |
|---|---|---|---|
| id | string(64) | no | **PK = Stripe event id** (`evt_…`) |
| type | string(80) | no | |
| processed_at | timestamp | yes | set after the queued job succeeds |
| created_at | timestamp | no | when received |

- **Flow:** insert on receipt; a duplicate-key error means "already received" → return 2xx and do nothing. Handled types: `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`.

### 4.16 `conversations`
One thread per (patient, center). All staff of that center share it.

| Column | Type | Null | Constraints / notes |
|---|---|---|---|
| id | id | no | PK |
| user_id | foreignId | no | → `users.id`, `restrictOnDelete` (the patient) |
| center_id | foreignId | no | → `centers.id`, `restrictOnDelete` |
| last_message_at | timestamp | yes | |
| created_at / updated_at | timestamps | | |

- **DB rule:** **UNIQUE** `(user_id, center_id)`.
- **Indexes:** `(center_id, last_message_at)`, `(user_id, last_message_at)`.

### 4.17 `messages`

| Column | Type | Null | Constraints / notes |
|---|---|---|---|
| id | id | no | PK; monotonic, used for cursor pagination and `after_id` |
| conversation_id | foreignId | no | → `conversations.id`, `cascadeOnDelete` |
| sender_id | foreignId | no | → `users.id`, `restrictOnDelete` |
| sender_side | string(10) | no | `SenderSide`: `patient` or `center` |
| type | string(10) | no | `MessageType` |
| body | text | yes | text messages, max 2000 chars |
| media_path | string(255) | yes | private disk path |
| media_mime | string(60) | yes | server-sniffed MIME |
| media_size | unsignedInteger | yes | bytes |
| duration_s | unsignedTinyInteger | yes | voice only, ≤ 60 |
| read_at | timestamp | yes | when the **other side** read it |
| created_at | timestamp | no | no `updated_at` (messages are immutable) |

- **Indexes:** `(conversation_id, id)`, `(conversation_id, sender_side, read_at)`.
- **App rules:**
  - `text` ⇒ `body` required. `image` or `voice` ⇒ `media_path`, `media_mime`, `media_size` required.
  - Image: JPEG/PNG/WebP, ≤ 5 MB. Voice: allowed audio MIME types, `duration_s` ≤ 60, size cap.
  - The sender must be the conversation's patient (`sender_side = patient`) or a `center_staff` user whose `center_id` equals the conversation's `center_id` (`sender_side = center`).
  - **Read:** `POST /conversations/{id}/read` sets `read_at = now()` on messages where `sender_side` ≠ the reader's side and `read_at` IS NULL.
  - **Unread count** = messages in my conversations where `sender_side` ≠ my side and `read_at` IS NULL.
  - Persist first, then broadcast after commit.

### 4.18 `notifications`
Laravel default database-notifications table.

| Column | Type | Null | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| type | string | no | notification class |
| notifiable_type / notifiable_id | morphs | no | always `User` here |
| data | text (JSON) | no | payload used by the frontend |
| read_at | timestamp | yes | |
| created_at / updated_at | timestamps | | |

- **Index:** `(notifiable_type, notifiable_id, read_at)`.

### 4.19 `cms_sections`
Ordered homepage blocks (admin-editable).

| Column | Type | Null | Default | Constraints / notes |
|---|---|---|---|---|
| id | id | no | | PK |
| key | string(60) | no | | **UNIQUE** slug |
| type | string(30) | no | | `CmsSectionType` |
| content | json | no | | shape depends on `type` (below) |
| is_active | boolean | no | `true` | |
| sort_order | unsignedSmallInteger | no | `0` | |
| created_at / updated_at | timestamps | | | |

- **Index:** `(is_active, sort_order)`.
- **Content shape per type** (validated by a Form Request per type; no schema engine):

| Type | `content` keys |
|---|---|
| `hero` | `title`, `subtitle`, `cta_label`, `cta_url`, `image_path` |
| `features` | `title`, `items[]` = `{ icon, title, text }` |
| `centers_carousel` | `title`, `limit` |
| `packages` | `title`, `subtitle` |
| `faq` | `title`, `items[]` = `{ question, answer }` |
| `cta` | `title`, `text`, `button_label`, `button_url` |

### 4.20 `failed_jobs`
Laravel default (`database-uuids` driver). Needed for the "queue failure" check.

### 4.21 `newsletter_subscribers` (stretch)

| Column | Type | Null | Default | Constraints / notes |
|---|---|---|---|---|
| id | id | no | | PK |
| email | string(190) | no | | **UNIQUE** |
| status | string(20) | no | `pending` | `NewsletterStatus` |
| token | string(64) | no | | **UNIQUE**; used for confirm and one-click unsubscribe |
| confirmed_at | timestamp | yes | | |
| unsubscribed_at | timestamp | yes | | |
| created_at / updated_at | timestamps | | | |

### 4.22 `activity_logs` (stretch)
Audit trail for admin actions only: center approve/suspend, user suspend, package changes.

| Column | Type | Null | Constraints / notes |
|---|---|---|---|
| id | id | no | PK |
| actor_id | foreignId | yes | → `users.id`, `nullOnDelete` |
| action | string(60) | no | e.g. `center.suspended` |
| subject_type | string(60) | yes | |
| subject_id | unsignedBigInteger | yes | |
| ip | string(45) | yes | |
| meta | json | yes | |
| created_at | timestamp | no | |

- **Indexes:** `(actor_id, created_at)`, `(subject_type, subject_id)`.

---

## 5. Relationships

```
centers 1─N users            (users.center_id, staff only)
users 1─N family_members
users 1─N appointments       (booker)
family_members 1─N appointments   (optional)
centers 1─N doctors
specialties 1─N doctors
doctors 1─N availability_rules
doctors 1─N days_off
doctors 1─N appointments
centers 1─N appointments     (denormalized from doctor)
packages 1─N subscriptions
users 1─N subscriptions
subscriptions 1─1 membership_cards
membership_cards N─N family_members   (membership_card_members)
users 1─N payments
subscriptions 1─N payments
users 1─N conversations      (patient side)
centers 1─N conversations
conversations 1─N messages
users 1─N messages           (sender)
users 1─N notifications      (morph: notifiable)
users 1─N activity_logs      (actor)
```

---

## 6. Invariants and where they are enforced

| Invariant | DB | App / test |
|---|---|---|
| A slot has at most one active appointment | UNIQUE `(doctor_id, starts_at, active_flag)` | Concurrency test: parallel requests → one `201`, rest `409` |
| Requested time is a real slot | — | Slot computation in `BookAppointment` |
| One chat thread per patient–center pair | UNIQUE `(user_id, center_id)` | — |
| One card per subscription | UNIQUE `subscription_id` | — |
| Webhook events are processed once | PK `stripe_events.id`; UNIQUE `payments.stripe_invoice_id` | Duplicate-event test |
| Only a verified webhook activates a subscription | — | Success page never writes |
| Staff see only their own center's data | `center_id` columns | Policy + scoped queries, negative tests |
| Family data is private to the owner | `user_id` FK | 404 on foreign IDs |
| Unverified users cannot book or pay | — | `EMAIL_NOT_VERIFIED` (403) |
| Cancel cutoff | — | `CANCEL_CUTOFF_HOURS` config |
| Card never exposes billing or personal data | — | Verify endpoint returns only validity, holder name, package, benefits |

---

## 7. Derived values (never stored)

| Value | Rule |
|---|---|
| **Available slots** for a doctor on a date | Expand `availability_rules` for that weekday (center timezone → UTC), remove `days_off`, remove starts that have an **active** appointment, remove past times |
| **Card status** (evaluate in this order) | `cancelled` if subscription status ∈ {`canceled`, `incomplete_expired`} → `suspended` if ∈ {`past_due`, `unpaid`, `paused`} → `expired` if `valid_to < now` → `active` if status ∈ {`active`, `trialing`} |
| **Unread counts** | See `messages` rules |
| **Patient data shown to a center** | Name, age (from `date_of_birth`), phone only |

---

## 8. Migration order

1. `centers`
2. `users` (FK to `centers`)
3. `password_reset_tokens`
4. `family_members`
5. `specialties`
6. `doctors`
7. `availability_rules`
8. `days_off`
9. `appointments`
10. `packages`
11. `subscriptions`
12. `membership_cards`
13. `membership_card_members`
14. `payments`
15. `stripe_events`
16. `conversations`
17. `messages`
18. `notifications`
19. `cms_sections`
20. `failed_jobs`
21. *(stretch)* `newsletter_subscribers`, `activity_logs`

Rename the default Laravel `users` migration file so it sorts after `centers`.

---

## 9. Reference and demo seed data

- **Specialties (slugs):** `general-medicine`, `pediatrics`, `dentistry`, `dermatology`, `gynecology`, `orthopedics`, `cardiology`, `ophthalmology`, `ent`, `psychiatry`, `nutrition`, `physiotherapy`.
- **Packages:** Individual Monthly (`member_limit` 1), Family Monthly (`member_limit` 4), Family Yearly (`member_limit` 4).
- **Demo accounts:** 1 admin, 1 patient (2 family members, 1 active membership), 2 center staff.
- **Demo content:** 6–8 active centers, ~20 doctors with weekly availability, a few appointments in each status, one sample conversation, default CMS sections.

---

## 10. Do NOT create

`branches`, `services`, `slots`, `benefits`, `roles` / `permissions` / any permissions package tables, `sessions` / `jobs` / `cache` tables, `user_devices`, `notification_preferences`, `settings`, `media`, `payment_methods`, `refunds`, `reviews`, `*_translations` tables, `national_id` columns.

Roles are the single `users.role` column. Permissions returned by `GET /me` come from a static role→permissions map in config.

---

## 11. Hot queries → indexes (sanity check)

| Query | Index used |
|---|---|
| Active appointments for a doctor on a date (slot computation) | UNIQUE `appointments(doctor_id, starts_at, active_flag)` |
| "My appointments" | `appointments(user_id, status, starts_at)` |
| Center appointments table | `appointments(center_id, status, starts_at)` |
| Public directory by city | `centers(status, city)` |
| Doctors of a center | `doctors(center_id, is_active)` |
| Chat history and `after_id` | `messages(conversation_id, id)` |
| Unread counter | `messages(conversation_id, sender_side, read_at)` |
| Payment history | `payments(user_id, created_at)` |
| Card verify by number or token | UNIQUE `membership_cards(card_no)` / `(token)` |
| Webhook idempotency | PK `stripe_events(id)` |
