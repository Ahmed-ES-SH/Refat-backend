# AGENTS.md — Rifad (رِفاد) · Backend API

Instructions for AI coding agents working in this repository (`backend/`). Read this file fully before changing anything.

**Parent standard:** `docs/AI-Agent-Development-Standard.md` (full workflow reference).
This file is the operational summary. For backend rules, this file wins. For Small tasks this file is enough; read the full standard only for Medium/High tasks or when something here is unclear.

> **Docker first (hard rule):** every `php artisan` command (tests, migrations, seeders, `route:list`, `scramble:export`, `config:show`, `make:*`, tinker, …) runs **inside the Docker backend container**, never on the host. See §8. Start Docker first if it is not running (`make start` from the parent folder). Only fall back to a host-local artisan run if Docker cannot be started, and say so explicitly in your report.

## 1. What this project is

Rifad is a **portfolio project**: a healthcare and family-care platform for the Gulf market. Patients book appointments (for themselves or family members), buy memberships through Stripe, and chat with medical centers. Centers manage doctors, availability, and bookings. Admins approve centers and manage packages and the homepage.

- It is **not** a real SaaS. Stripe runs in **test mode**. Data is demo data. There is no real patient data.
- Target level is **junior → mid**. Prefer simple, conventional, readable code over clever or "scalable" code.
- Success = a clean, working, deployed demo with strong tests on the hard parts: booking concurrency, Stripe webhooks, authorization.

## 2. Source of truth (read in this order)

| Doc | Purpose |
|---|---|
| `rifad-database-schema.md` | **Canonical DB structure.** Never deviate. |
| `rifad-backend-plan.md` | Backend scope (§2), feature rules (§4), API conventions (§6), security (§7), testing (§10), milestones (§14), production checklist (§15) |
| `DESIGN.md` | UI/UX design system (shared with the frontend) |
| `../Refat-PRD.md` | Product requirements (parent folder, covers both apps) |
| `../frontend/rifad-frontend-plan.md` | Frontend scope; its §15 is the same Integration Contract as backend plan §13 |
| `../frontend/AGENTS.md` | Frontend rules (Next.js; that version differs from training data, so read it there). Frontend layout and conventions live **only** there |
| `tasks.md` (per milestone) | The current work list, once it exists. Do only what it asks |
| `docs/AI-Agent-Development-Standard.md` | Full workflow standard |

- The plan and schema currently live at the **repo root**, not in `docs/`. If they are moved, update this table and every path below.
- The **Integration Contract** (backend plan §13 = frontend plan §15) defines how the two apps connect. If code and contract disagree, stop and report it. Do not silently pick a side.
- Precedence when docs conflict: `rifad-database-schema.md` wins for data → the plans win for scope → this file wins for behavior → `DESIGN.md` for visual/UX detail.

## 3. Current state of the repo (do not assume features exist)

This is a **fresh Laravel skeleton**, not a built product yet. Update this section when a milestone lands, and verify with `composer show --direct` before relying on it.

- Installed: Laravel 13, PHP 8.5 runtime (`composer.json` allows `^8.3`), Pest 5, Pint, Laravel Boost, Pail, Vite + Tailwind 4.
- Present today: `app/Models/User.php`, base `Controller`, `routes/web.php`, the three default migrations, example tests.
- **Everything in §6 (layout) and §10 (tests) is target state from the plan, not existing code.** Build it milestone by milestone (plan §14: M0 → M7).
- Local `.env`: SQLite + `database` session/queue/cache drivers. Tests: SQLite `:memory:` (`phpunit.xml`). Docker Compose in the parent folder runs **MySQL 8.4** + phpMyAdmin + backend + queue worker (no frontend container; the frontend runs host-local).
- **Planned but not installed yet:** Sanctum, Socialite, Stripe SDK, Reverb/Pusher, Redis, Scramble, Larastan, Mailpit/MinIO in compose. Add each one only in the milestone that needs it, as a deliberate change with approval.

## 4. Workflow (risk-based)

Use the lightest workflow that gives enough confidence for the risk. **Risk beats size**: a small change to auth or payments is High; a large change to a simple Resource is not.

| Level | Typical work here | Process |
|---|---|---|
| **Small** | Factories/seeders, adding a field to an existing Resource, simple read-only endpoint on non-sensitive data, docs fixes | Inspect → short brief (objective, behavior, acceptance) → implement → verify → review diff → commit |
| **Medium** | New endpoint with Form Request + Resource, new Action, queued job or notification, an additive table from schema §8, multi-model features | Inspect → phase brief in `tasks.md` (spec + ordered tasks) → approve important decisions → implement task by task, verifying each → phase checks → review diff → commit |
| **High** | Sanctum/Socialite auth, Policies and role isolation, Stripe + webhooks + card status, booking transactions/concurrency, chat channel auth, uploads, migrations that alter or delete existing data | Medium process + independent design review + human approval before coding |

**Rules**
- Classify the task first and state it in one line before coding (e.g. "High: billing webhooks, design review first"). Do not classify High just because many files change. An additive migration for a table already in schema §8 is Medium, not High.
- **Heavy design review happens once per module** (auth, billing/webhooks, booking, chat, uploads). The plan section plus the schema are the spec. Later changes to that module are Medium plus the security rules in §5 and negative tests.
- **Independent review (High only, one round):** a fresh-context agent receives only the spec and the files it references, and checks completeness, security gaps, conflicts with the schema or Integration Contract, and feasibility. Fix material findings; reject the rest and note why. EGM may be used if installed, but it is optional. A review that shares the author's context is not independent: do not call it that.
- **One phase document:** use the task's entry in `tasks.md` and the plan's milestone (§14). Do not create overlapping docs; create documentation files only when asked. Update the entry when behavior or decisions change. If a task has no acceptance criteria, add a short brief (objective, scope, behavior, acceptance, verification) before coding.
- **Human approval** is required when scope, architecture, security, data integrity, cost, or user-visible behavior would materially change.
- **Skills** (use only if installed; otherwise follow the same steps manually and say so): `writing-plans` for multi-task Medium/High work · `test-driven-development` for non-trivial logic (slot computation, cancellation rules, card status) · `systematic-debugging` on any failure · `verification-before-completion` before reporting done. Do not use `executing-plans`, subagents, or worktrees unless asked. Project skills are in §13.
- **Guard pass after every edit (mandatory):** after creating or editing **any production code file** (PHP, config, routes, migrations), load `clean-code-guard` and run its self-check on that change before presenting, committing, or moving on — fix every violation first. After creating or editing **any test file or test logic**, load `test-guard` and fix its violations the same way. Both guards stay active for the whole session: re-run the self-check after *each* later edit, never just once at the end. If a skill is not installed for the agent, apply its rules manually from its `SKILL.md` — `clean-code-guard` and `test-guard` live in the user-level `~/.agents/skills/<name>/SKILL.md` — and say so in your report.
- **Stuck rule:** if the same issue is unresolved after **3 attempts**, stop. Report the failure, the likely cause, what you tried, the latest error output, and what decision you need. Do not repeat cosmetic variations of a failed approach.
- **Git:** check `git status` first. Never touch, stage, or revert unrelated changes. After each verified task, make a focused commit (Conventional Commits, one concern per commit) when the repo workflow permits. Stage only relevant files. Never commit `.env`, secrets, generated artifacts, or failing/unverified work as complete.
- **Evidence:** the final report lists the checks run and their results, important scenarios verified, checks not run and why, and known limitations. Never claim something passed unless you ran it and read the output.

## 5. Hard rules

### Scope (anti-over-engineering)
1. Build **only** what the current task and the plans list. No bonus features.
2. Do **not** add: microservices, CQRS, event sourcing, repositories-for-everything, module packages, generic "engines", feature flags, extra abstraction layers, or speculative interfaces (e.g. no `PaymentGateway` interface).
3. Do **not** build anything on the plan's **Cut** list (plan §2) or schema §10: branches, services, `slots` table, benefits table, National ID, session/device management, notification preferences, role editor, `settings`/`media`/`payment_methods`/`refunds`/`reviews`/translation tables.
4. Do **not** add a dependency unless the task requires it. Prefer what is already installed (§3). Mention any new dependency in your summary.
5. Prefer the boring, standard Laravel way. Three similar lines beat a premature abstraction.
6. Do not invent endpoints, columns, types, file paths, or test results.

### Database
7. Never add or change tables, columns, indexes, or enum values that are not in `rifad-database-schema.md`. If you think one is needed, **stop and ask**.
8. One migration per table, in schema §8 order, each with a working `down()`. Rename the default `users` migration so it sorts after `centers`.
9. Enums are `string` columns cast to PHP backed enums (`app/Enums`). **Never** MySQL `ENUM`.
10. Money is `unsignedInteger` minor units (`*_minor`) + `char(3)` currency. All `*_at` columns are UTC; `availability_rules.start_time/end_time` are in the center's timezone.
11. Canonical target is **MySQL 8** (Docker/production) but tests run on **SQLite `:memory:`**. Write portable migrations and queries — no MySQL-only DDL, no `FULLTEXT`, no raw vendor-specific SQL. If a schema rule cannot work on both, stop and ask.
12. Schema §1 requires Redis for session/queue/cache and forbids `sessions`/`jobs`/`cache` tables. That config lands with the Redis milestone; until then do not build anything that depends on those tables, and remove them when Redis is wired up.

### Security (non-negotiable)
13. Every endpoint is authorized on the backend (route middleware + Policy + scoped query). Frontend checks are cosmetic only.
14. Auth is Sanctum **SPA cookie** auth. **No tokens in JavaScript, localStorage, or sessionStorage. Ever.**
15. Other users' resources return **404**, not 403, to avoid ID enumeration (family members, appointments, conversations).
16. Validate with Form Requests (backend) and mirror with Zod (frontend). Backend validation is authoritative.
17. Stripe: verify the signature on the **raw body**, record `event.id` for idempotency, respond 2xx fast, process in a queued job. **Only a verified webhook activates a subscription or card**, never the success page.
18. Amounts, prices, and plan IDs come from the database. Never trust an amount sent by the client.
19. Uploads: validate size, sniff the real MIME type, allowlist extensions, random file names, private disk, short-lived signed URLs.
20. Throttle auth, verification, upload, and chat-send endpoints.
21. Never log secrets, tokens, or personal data. Never commit `.env` or real keys. Never store card data.
22. Mass-assignment protection and parameterized queries only.

## 6. Stack and layout

### Stack
**Installed now:** Laravel 13, PHP 8.5, Pest 5, Pint, Boost, Vite + Tailwind 4, SQLite (dev/tests).
**Per plan (add per milestone):** MySQL 8, Redis, Sanctum, Socialite (Google), Stripe PHP SDK, Laravel Reverb, Scramble (OpenAPI), Larastan, Docker Compose + Caddy, GitHub Actions.
**Frontend:** sibling repo `../frontend`. Its stack and layout are defined in `../frontend/AGENTS.md`, not here.
**Deployment:** plan §11 = Docker Compose + Caddy + single VPS with GitHub Actions. The `deploying-to-cloud` skill applies only if the project actually moves to Laravel Cloud.

Verify versions before relying on an API: `composer show --direct` or the relevant Boost tool. Do not assume a package's behavior from memory.

### Backend layout (target)
```
app/Actions/              one class per multi-step business rule (BookAppointment, CancelAppointment,
                          RescheduleAppointment, ProcessStripeEvent, IssueMembershipCard)
app/Enums/                backed enums (see schema §2)
app/Http/Controllers/Api/V1/{Auth,Family,Centers,Booking,Billing,Chat,Notifications,Cms,Admin}
app/Http/Requests/  app/Http/Resources/  app/Policies/
app/Models/  app/Notifications/  app/Jobs/  app/Console/Commands/
routes/api_v1.php  routes/channels.php
```
- Controllers stay **thin**: authorize → validate (Form Request) → call an Action or query → return an API Resource.
- Create an Action only when a business rule spans several steps or needs a transaction. Simple CRUD stays in the controller.
- Every response goes through an API Resource. Every list is paginated (`per_page` max 50; chat uses cursor pagination).

## 7. Conventions

**Backend:** PSR-12 via Pint · typed properties/params/returns · Form Requests · Policies · eager loading and no N+1 (`Model::preventLazyLoading()` on outside production) · queue anything slow (mail, notifications, Stripe event processing).
**API:** JSON under `/api/v1` · Laravel API Resources · error envelope `{ message, code, errors }` with stable machine codes (`EMAIL_NOT_VERIFIED`, `SLOT_TAKEN`, `CANCEL_WINDOW_PASSED`, `MEMBER_HAS_FUTURE_APPOINTMENTS`, `CARD_NOT_ACTIVE`) · ISO-8601 UTC datetimes · `Accept-Language: ar|en` · `409` for slot conflicts · Scramble-generated OpenAPI, exported in CI **and after each feature/phase via `make artisan ARGS="scramble:export"` (Docker, §8)**.
**Naming:** `snake_case` DB, `PascalCase` classes, `kebab-case` route segments.

## 8. Commands

**All `php artisan` commands run in Docker, not on the host.** Docker Compose lives in the parent folder (with `compose.yaml` + `Makefile`); the `backend` service is the PHP container.

```bash
# Docker lifecycle — run from the parent folder that holds compose.yaml
make up | start | stop | down | logs | ps
make sh-backend                          # shell inside the backend container

# Every artisan command goes through Docker
make artisan ARGS="test --compact"       # narrowest failing/changed test set
make artisan ARGS="test"                 # full suite
make artisan ARGS="route:list --except-vendor"
make artisan ARGS="config:show database.default"
make artisan ARGS="migrate:fresh --seed" # rebuild demo DB (or: make fresh && make seed)
make artisan ARGS="scramble:export"      # OpenAPI docs → public/docs/api/openapi.json
make artisan ARGS="make:test --pest Foo" # any make:* / tinker / console command

# Same thing without make (from the parent folder)
docker compose exec backend php artisan <command>

# Pint (not artisan, but still inside the container)
docker compose exec backend vendor/bin/pint --dirty --format agent   # changed files
docker compose exec backend vendor/bin/pint --format agent           # everything
```

If Docker is not running, start it first (`make start` from the parent folder) — do **not** switch to host-local artisan as a shortcut. Only if Docker genuinely cannot start, run the command on the host and state that explicitly in your report.

**Frontend / assets (host-local, no Docker required):** `npm run build`, `composer run dev`.
**Not installed yet — do not run until the milestone adds them:** `./vendor/bin/phpstan analyse` (Larastan).

## 9. How to work

1. Read the current milestone in plan §14 and any `tasks.md`, then the plan sections it references. Check `git status`.
2. Classify the task (§4) and state the workflow in one line.
3. Do the smallest change that satisfies the task and its acceptance criteria. Do not refactor unrelated code.
4. Write or update tests **with** the feature (§10).
5. After each code-file edit or new code file: run the `clean-code-guard` self-check and fix violations. After each test edit or new test: run `test-guard` and fix violations (§4, §13).
6. Verify each meaningful task: Pint and the relevant tests (both in Docker, §8). Run Larastan/Scramble only once installed.
7. **Export OpenAPI docs**: after each completed feature/phase, run `make artisan ARGS="scramble:export"` (from the parent folder) to regenerate `public/docs/api/openapi.json` and verify new endpoints appear in `/docs/api`.
8. Finish with a short summary: what changed, evidence (checks run and results, checks not run and why), new dependencies (if any), and anything you were unsure about.

**Stop and ask (do not guess) when:**
- the task needs a table, column, endpoint, or dependency not in the docs;
- docs conflict with each other or with existing code;
- a requirement is ambiguous in a way that changes behavior (money, permissions, data deletion);
- you would need to weaken a security rule from §5 to finish;
- you would have to add something from the Cut list (plan §2 / schema §10).

Do not ask for information already present in the project files.

## 10. Testing

Test behavior and risk, not every line. No coverage target. Do not add tests just to raise the count.

- **Must-haves:** auth throttling and verification gate · role/ownership isolation (404 vs 403) · booking happy path, cancel cutoff, reschedule atomicity · **concurrent booking on one slot → exactly one `201`, the rest `409`** · Stripe webhook (bad signature, duplicate event, out-of-order event, renewal extends card, failed payment suspends card) · chat channel authorization · upload rejection (type and size).
- **Unit tests:** slot computation, cancellation rules, card status derivation. Skip trivial getters and plain CRUD with no logic.
- **High-risk features** need negative tests: forbidden access, invalid input, duplicate requests, unauthorized state changes.
- **Concurrency caveat:** tests run on SQLite `:memory:`, so a truly parallel-request test needs MySQL (Docker/CI). On SQLite only the sequential duplicate-booking check is meaningful. Do not claim concurrency is proven from a SQLite run.
- **Static:** Pint; Larastan (level 5+) once added. CI runs tests with MySQL + Redis services.
- Every bug fix gets a regression test. Do not delete or weaken a failing test to make it pass; fix the cause or ask.
- Report any skipped or unavailable check honestly, with the reason and the remaining risk.

## 11. Definition of done (per feature)

- [ ] Matches the task, `rifad-database-schema.md`, and the Integration Contract
- [ ] Authorization enforced and negatively tested
- [ ] Tests added and passing; Pint clean (Larastan once installed)
- [ ] `clean-code-guard` self-check run and violations fixed on every production code file touched
- [ ] `test-guard` run and violations fixed on every test file touched
- [ ] User-facing API messages respect `Accept-Language` (ar|en)
- [ ] No new tables, columns, or dependencies beyond the docs (or they are flagged)
- [ ] API change reflected in the exported OpenAPI and coordinated with the frontend
- [ ] **OpenAPI docs updated**: run `make artisan ARGS="scramble:export"` (Docker, §8) after each feature/phase; verify new endpoints appear in `/docs/api`
- [ ] Diff contains no unrelated changes; focused commit made where permitted
- [ ] Evidence summary written (checks run, not run and why, limitations)

## 12. Priorities if time is short

Cut in this order: newsletter → CMS editor (keep the seeded homepage) → voice messages → reminders → chat image uploads.
**Never cut:** booking concurrency protection, Stripe webhook handling, authorization tests.

## 13. Project skills

Activate the matching skill before working in that domain. Load it with the `skill` tool when it is registered; otherwise read `.agents/skills/<name>/SKILL.md` (mirrored in `.claude/skills/`).

- `laravel-best-practices` — any Laravel PHP work.
- `testing-best-practices` — choosing/writing/reviewing tests.
- `clean-code-guard` — **run after every production code file edit or creation** (guard-pass mode), and re-run after each later edit in the same session. Rules in §4.
- `test-guard` — **run after every test file edit or creation, and on all test logic**. Rules in §4.
- `tailwindcss-development` — Blade/Tailwind markup in this repo (frontend repo has its own copy).
- `infer-conventions` — before adding files to a new area, to match existing structure.
- `deploying-to-cloud` — only if the project moves to Laravel Cloud (plan currently targets a VPS).

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
