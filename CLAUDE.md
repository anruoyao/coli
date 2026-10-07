# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working in this repository.

## Project

ColibriPlus is a Laravel application with a Vue 3 frontend. PHP dependencies and test configuration are in `composer.json` and `phpunit.xml`; frontend dependencies and build entry points are in `package.json` and `vite.config.js`.

## Common Commands

```bash
# Install dependencies
composer install
npm install

# Run the app and Vite dev server in separate terminals
php artisan serve
npm run dev

# Build all frontend bundles, including separately compiled dark themes
npm run build

# Run all PHPUnit tests, or select a suite/path/method
vendor/bin/phpunit
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/Feature/Guest
vendor/bin/phpunit tests/Feature/Guest/GuestProfileTest.php --filter test_name
```

Use `npm run build:vite` for only the Vite manifest bundles; `npm run build:dark` rebuilds the separate dark-theme CSS outputs. There are no lint scripts defined in `package.json`.

## Architecture

- Laravel owns HTTP handling, persistence, and server-rendered pages. Route files in `routes/` are split by surface (web, social, business, admin, API, callbacks, webhooks); `bootstrap/app.php` wires the application. Follow the existing route grouping and middleware for the surface being changed.
- Backend responsibilities are separated across `app/Http` (controllers, middleware, resources), `app/Actions` (domain operations), `app/Services` (integrations and reusable domain services), `app/Models`, and `app/Jobs` / `app/Events` / `app/Listeners` (asynchronous and event-driven work). Schema and seed data live in `database/`.
- The frontend is a set of Vue applications, not one single SPA: desktop and mobile entry points are `resources/js/spa/apps/{desktop,mobile}/bootstrap/application.js`, each with its own router and app modules. Shared SPA code is under `resources/js/spa/kernel`; separate MPA, admin, business, and document entry points are registered in `vite.config.js`. Blade layouts/views under `resources/views/` mount these surfaces.
- Frontend state uses Pinia. Vite aliases `@` to the SPA root, `@D` to desktop, and `@M` to mobile. Keep changes in the matching app entry and route hierarchy, and update the corresponding Vite input when adding a new independently built entry.

## Testing Constraints

See `TESTING.md` for the detailed test workflow and fixture conventions. Important safeguards:

- PHPUnit is configured with `DB_DATABASE=testing`, but cached Laravel configuration can override it. Before running database-backed tests, verify that `bootstrap/cache/config.php` is absent or otherwise ensure the effective test connection targets the isolated `testing` database, never production.
- The documented test environment requires MySQL and Redis services that may not be available locally; don't assume tests can run with the default local setup.
- Use `DatabaseTransactions` rather than `RefreshDatabase` in database tests: this project's schema has many tables and per-test fresh migrations are prohibitively slow. The testing database must already have its schema migrated.
- API feature tests may need `config(['security.app_key.enabled' => false])` because the API route group can hide unauthorized requests with a 404. Fixtures that are queried through `User::activeById()` must use `UserStatus::ACTIVE`.
