# FinApp

A small business financial management system built on Laravel 13: chart of accounts, double-entry journals, cash & bank transactions, and live financial reports.

## Features

- **Accounting core** — chart of accounts, journal entries with debit/credit lines, posting workflow that enforces balanced entries and makes posted journals immutable, opening balances.
- **Cash & bank** — cash accounts, bank accounts (with account-number privacy), cash-in/cash-out transactions that post straight into the journal.
- **Reports** — General Ledger, Profit & Loss, Balance Sheet, and a live dashboard, all computed directly from posted journals (nothing cached or precomputed).
- **Admin** — role-based access control (Admin / Accountant / Viewer), multi-company support with a company switcher, user management, and an audit log of every change.

## Stack

- PHP 8.4, Laravel 13
- PostgreSQL
- Blade + Alpine.js for most of the UI; Livewire is scoped to the authentication module (login, logout, company switcher)
- Vite + Tailwind CSS 4 (CSS-first config, no `tailwind.config.*`)
- PHPUnit for tests, Pint for code style

## Getting Started

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Configure a PostgreSQL database in `.env` (`DB_CONNECTION=pgsql` plus `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), then:

```bash
php artisan migrate --seed
npm run build
```

Seeding creates a demo company, an admin user (`admin@finapp.test` / `password`), and a standard chart of accounts.

### Local development

```bash
composer run dev
```

Runs the PHP server, queue listener, and Vite dev server together. Use `npm run dev` on its own if you only need asset watching.

## Testing

```bash
php artisan test --compact
```

Or a narrower run:

```bash
php artisan test --filter=testName
vendor/bin/pint --dirty --format agent
```

## Project Structure Notes

- Domain models live in `app/Models`, controllers in `app/Http/Controllers`, business logic (e.g. journal posting) in dedicated service classes.
- `database/seeders/AccountSeeder.php` seeds the default chart of accounts for a company.
- See `docs/IMPLEMENTATION_PLAN.md` for the original build plan and architecture decisions.
