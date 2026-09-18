# Implementation Plan — Financial Management System MVP

**Produced by:** STEP 0 — Project Discovery
**Date:** 18 September 2026
**Spec sources:** `Financial Management System.md` (v1.1.0) and `Financial Management System-biz.md`

This document is discovery + planning only. No business feature (accounting, cash, reports, auth) has been implemented yet, per STEP 0's Definition of Done.

---

## Current Architecture

A stock Laravel 13 application, one commit away from `laravel new`. No modular structure, no domain code, no admin UI.

```text
app/
├── Http/Controllers/Controller.php   (empty base controller)
├── Models/User.php                    (default Laravel user)
└── Providers/AppServiceProvider.php   (empty)
```

| Aspect | Detected value |
|---|---|
| Laravel | 13.32.0 (composer.json pins `^13.17`) |
| PHP | 8.4.25 CLI, matches `^8.3` constraint |
| Frontend build | Vite 8 + `@tailwindcss/vite` (Tailwind 4, CSS-first — no `tailwind.config.*` file, theme lives in `resources/css/app.css`) |
| Alpine.js | Not installed (not in `package.json`) |
| Livewire | Not installed |
| DB driver configured | `DB_CONNECTION=sqlite` in `.env` and in `phpunit.xml` |
| DB driver actually available on this machine | `pdo_pgsql` ✅ present · `pdo_sqlite` ❌ **not installed** |
| Postgres reachability | A server is listening on `127.0.0.1:5432`, but neither `postgres/postgres` nor the current OS user connects — credentials unknown, no `docker ps` container visible |
| `bcmath` extension | Not installed |
| Auth | None built. `config/auth.php` is Laravel's default (`web` guard, session driver, `App\Models\User` eloquent provider). `SESSION_DRIVER=database` in `.env.example`. |
| Middleware | `app/Http/Middleware/` does not exist; `bootstrap/app.php` has an empty `withMiddleware()` hook |
| Routes | `routes/web.php` has one route: `GET /` → `welcome` view. `routes/console.php` untouched. |
| Views | Only `resources/views/welcome.blade.php` (Laravel's default splash page) |
| Migrations | Only the three defaults: `users`, `cache`, `jobs` |
| Seeders / Factories | `DatabaseSeeder.php` (empty), `UserFactory.php` (default) |
| Tests | `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php` — both the framework's placeholder tests. PHPUnit 12.5, `laravel/pao` (agent-optimized test output) installed. |
| Agent tooling | `laravel/boost` 2.9.1 installed this session (`composer require laravel/boost --dev` + `php artisan boost:install --guidelines --mcp`). `CLAUDE.md` now carries the generated Boost guidelines (Pint on dirty files, `make:` artisan commands, `--no-interaction`, search-docs before ecosystem-specific code, etc.) — these apply on top of this spec from STEP 1 onward. No `.ai/rules/` directory exists. |
| Git | Not a git repository yet |

**Spec amendment already applied** (before this discovery pass): `Financial Management System.md` v1.1.0 now permits Livewire, scoped to the Authentication module only (login, logout, company switcher). Every other module stays Blade + Alpine.js. See §3, §7, STEP 2 of the spec.

---

## Existing Components

Nothing reusable for the business domain. What exists and *will* be reused rather than replaced:

- `App\Models\User` — base for the auth user, will gain `CompanyMembership` relations.
- `config/auth.php` — session guard stays as-is; Livewire auth components sit on top of it, per spec §7.
- `config/database.php` — already has a complete `pgsql` connection block; just needs env values.
- `resources/css/app.css` — Tailwind 4 CSS-first setup stays; admin theme tokens get added here, not a new config file.
- `laravel/boost` MCP tools (`database-query`, `database-schema`, `search-docs`, etc.) — prefer these over raw shell/tinker from STEP 1 onward per the now-generated `CLAUDE.md`.

---

## Required Changes

Ordered by what blocks what — not by spec step number.

1. **Resolve the database driver mismatch (blocks everything else).** `pdo_sqlite` isn't installed, so the current `.env`/`phpunit.xml` sqlite config cannot even run `migrate` on this machine today (`could not find driver`). Spec already mandates PostgreSQL as source of truth. A Postgres server is reachable on `:5432` but I don't have working credentials — **needs the user** to supply `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD` (or confirm a fresh local Postgres role should be created). Same question applies to the **test** database (`phpunit.xml` currently hardcodes sqlite `:memory:`) — recommend a second Postgres database (e.g. `finapp_testing`) so `FOR UPDATE` locking and `NUMERIC(20,2)` behavior are exercised on the real engine, not emulated.
2. **Install Alpine.js and Livewire.** Alpine via `npm install alpinejs` + bootstrap in `resources/js/app.js`. Livewire via `composer require livewire/livewire`, scoped to auth views only (spec amendment).
3. **Decide PHP-side money arithmetic without `bcmath`.** `bcmath` isn't installed. Recommendation: never do debit/credit arithmetic in PHP — validate balance with a DB aggregate (`sum('debit')`, `sum('credit')` inside the same `DB::transaction`, compared as decimal strings) so Postgres does the arithmetic, matching §6's "PostgreSQL is the source of truth." Line-level single-sided checks (`debit > 0 XOR credit > 0`) are plain comparisons, not arithmetic, so they don't need `bcmath` either. If per-line running-balance math is ever needed in PHP (e.g. General Ledger display), flag it then — don't install `bcmath` speculatively.
4. **Turn on Git.** Repo is not initialized. Needed before any meaningful diff review or the `/code-review` workflow becomes useful.
5. **Build the module tree** under `app/Services/{Accounting,Cash,Reporting,Audit}` per spec §4 — nothing exists yet.

---

## New Components

Per spec §14, minimum Eloquent models (none exist beyond `User`):

```text
Company, CompanyMembership, Role, Permission, RolePermission
Account
JournalEntry, JournalLine
CashAccount, BankAccount, CashTransaction
AuditLog
```

Services (spec §4, §22 — one canonical posting path):

```text
Services/Accounting/JournalPostingService   ← the one gate (spec §22)
Services/Cash/CashTransactionService        ← builds journals, calls JournalPostingService
Services/Reporting/GeneralLedgerService
Services/Reporting/ProfitLossService
Services/Reporting/BalanceSheetService
Services/Audit/AuditLogger
```

Authorization: Policies for `Account`, `JournalEntry`, `CashTransaction`; Gates or policy-backed abilities for `report.view`, `audit_log.view`; `EnsureCompanyMembership` and `SetActiveCompany` middleware.

Livewire components (auth module only): `Login`, `Logout` (or a plain POST action), `CompanySwitcher`.

Blade layout + components (spec STEP 1): `layouts/admin.blade.php`, `components/{button,input,select,modal,alert,table,badge,pagination}.blade.php`.

---

## Database Changes

All new tables. Every financial table gets `company_id` (spec §16) with a foreign key and a composite index alongside its natural lookup column.

| Table | Key columns | Notes |
|---|---|---|
| `companies` | `id, name, status` | |
| `company_memberships` | `company_id, user_id, role_id` | unique on `(company_id, user_id)` |
| `roles` | `id, name` | seed `ADMIN`, `ACCOUNTANT`, `VIEWER` |
| `permissions` | `id, key` | seed the permission list from spec §3 (STEP 3) |
| `role_permission` | `role_id, permission_id` | pivot |
| `accounts` | `company_id, code, name, type, parent_id, status, is_system` | unique `(company_id, code)`; `type` enum ASSET/LIABILITY/EQUITY/REVENUE/EXPENSE |
| `journal_entries` | `company_id, journal_number, transaction_date, description, status, journal_type, created_by, posted_by, posted_at` | `status` DRAFT/POSTED; `journal_type` NORMAL/OPENING_BALANCE (spec STEP 7) |
| `journal_lines` | `journal_entry_id, account_id, description, debit NUMERIC(20,2), credit NUMERIC(20,2)` | app-level + DB check constraint: not both > 0 |
| `cash_accounts` | `company_id, name, account_id, status` | `account_id` must reference an ASSET account |
| `bank_accounts` | `company_id, bank_name, account_name, account_number, account_id, status` | mask `account_number` in any logging |
| `cash_transactions` | `company_id, transaction_number, transaction_type, transaction_date, cash_account_id, counter_account_id, amount NUMERIC(20,2), description, status, journal_entry_id, created_by, posted_by, posted_at` | `transaction_type` CASH_IN/CASH_OUT |
| `audit_logs` | `company_id, user_id, action, resource_type, resource_id, before_data, after_data, created_at` | append-only, no `updated_at`; JSON columns for before/after |

All money columns: `NUMERIC(20,2)`, never `float`/`double` (spec §6).

---

## Routes

```text
GET  /login                        (Livewire)
POST /logout

GET  /dashboard

GET  /accounts | GET /accounts/create | POST /accounts
GET  /accounts/{id}/edit | PUT /accounts/{id}

GET  /journals | GET /journals/create | POST /journals
GET  /journals/{id} | POST /journals/{id}/post

GET  /cash-accounts | GET /cash-accounts/create | POST /cash-accounts
GET  /bank-accounts | GET /bank-accounts/create | POST /bank-accounts
GET  /cash-transactions/create | POST /cash-transactions   (cash in/out, `transaction_type` param)

GET  /reports/general-ledger
GET  /reports/profit-loss
GET  /reports/balance-sheet

GET  /users | POST /users
GET  /roles | POST /roles
GET  /audit-logs

POST /company/switch
```

All behind `auth` + `EnsureCompanyMembership` middleware; write routes additionally behind the relevant policy/permission.

---

## Views

```text
resources/views/layouts/admin.blade.php
resources/views/components/{button,input,select,modal,alert,table,badge,pagination}.blade.php
resources/views/livewire/{login,company-switcher}.blade.php
resources/views/dashboard.blade.php
resources/views/accounts/{index,create,edit}.blade.php
resources/views/journals/{index,create,show}.blade.php
resources/views/cash-accounts/{index,create}.blade.php
resources/views/bank-accounts/{index,create}.blade.php
resources/views/cash-transactions/create.blade.php
resources/views/reports/{general-ledger,profit-loss,balance-sheet}.blade.php
resources/views/users/index.blade.php
resources/views/roles/index.blade.php
resources/views/audit-logs/index.blade.php
```

---

## Tests

Per spec STEP 18, grouped the same way:

- **Accounting** — balanced/unbalanced journal, negative/zero amount, multiple lines, inactive account, cross-company account, duplicate posting, posted-journal edit/delete rejection.
- **Cash** — cash in, cash out, duplicate post idempotency, inactive cash account, bank account CRUD.
- **Reports** — GL, P&L, Balance Sheet correctness, draft excluded, posted included, date filtering.
- **Security** — cross-company access, viewer attempting to post, unauthenticated access, inactive company transaction.

Blocked until Required Change #1 (database driver) is resolved — `php artisan test` cannot run at all right now (`pdo_sqlite` missing, and `pdo_pgsql` has no working credentials).

---

## Potential Risks

1. **Test suite cannot currently run.** Neither configured driver (sqlite) nor the spec's mandated driver (pgsql) is fully usable on this machine yet. This blocks all of STEP 1 onward until resolved — **needs user input** on Postgres credentials.
2. **Balance Sheet equity presentation is undefined.** Spec STEP 14 explicitly requires `docs/accounting/balance-sheet.md` documenting the Current Year Earnings vs. Retained Earnings treatment *before* that step is coded. Not yet written — flagged again for STEP 14, do not silently invent a balancing value.
3. **Spec's test dataset (§18) is ambiguous.** "Cash Purchase Rp10.000.000" with expected Total Expense 15.000.000 only holds if that purchase is booked as an expense; if it's inventory/fixed-asset (both out of scope per biz §41), the expected P&L numbers in the spec are wrong. Needs the dataset line renamed/clarified before STEP 18 tests are written from it.
4. **No `bcmath`.** Fine as long as balance validation stays at the DB layer (Required Change #3); revisit if PHP-side decimal arithmetic becomes unavoidable.
5. **Not a git repository.** No diff history, no branch to isolate STEP-by-STEP work. Recommend `git init` before STEP 1 so each step can land as a reviewable commit, matching the spec's "work sequentially, report after each step" execution rule (§24).
6. **Livewire scope creep.** Spec amendment restricts Livewire to the Authentication module. Worth a lint/review checkpoint at the end of STEP 2 to confirm no `wire:` directives leaked into other modules.

---

## Next Step

STEP 1 — Laravel Foundation & Admin Layout, blocked on resolving Risk #1 (database driver/credentials) first since even a layout-only step benefits from a working `migrate` for session storage (`SESSION_DRIVER=database`).
