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

## Additional Lessons from Session History

- Deployment flow: prepare code locally, create commits, and push to GitHub when the user has explicitly authorized those actions. Before deployment, inspect the server worktree; if it is clean, run `sudo -u www git pull --ff-only origin main` in `/www/wwwroot/mariawk.cn`, then `php artisan view:clear && php artisan view:cache` and `sudo /etc/init.d/php-fpm-83 reload`. The server repo's `.git` is root-owned, so pulls as `www` can fail on lock files — fix permissions, and stop to investigate any server-local changes.
- Pulling code is not a full deploy: run `php artisan migrate --force` for new migrations, rerun backfill commands when needed (e.g. `ads:sync-shadow-posts`), rebuild `config:cache` if `.env` toggles changed, and rebuild Vite assets — clearing Blade/view cache alone does not refresh built JS/CSS.
- Never stream binary downloads through Livewire: it Base64-encodes the payload into the JSON response and exhausts `memory_limit` (a ~50MB ZIP caused a 500 under `128M`). Use a plain admin-protected GET route that streams the file.
- Production is a 2-core/4GB box with little headroom (4GB swap at `/swapfile-nllb`, in fstab, was added for the NLLB service). Site-wide 500s after a server reboot were caused by Redis not running: the site uses the BT-panel instance at `/www/server/redis/redis.conf` managed by `coli-redis.service`, not the system `redis-server.service`. Give memory-hungry side services conservative limits.
- The user prefers real admin-UI features over one-off artisan commands run on the server (ads-as-shadow-posts was converted into a backend feature for this reason). Restored SQL uploads must reject MySQL client meta-commands (`\!`, `\.`, `source`, `system`), verify ZIP/SQL hashes, and auto-create an emergency backup before restoring.
- Post translations are cached permanently per (post, target language) with alias normalization (`en`/`en-us`/`en-gb` share one entry). Always read the post body server-side (ignore client-sent text) to protect the shared cache; invalidate on edit or non-active status, cascade-delete with the post, lock concurrent misses, and re-validate post/author active plus block status on cache hits.
- Language detection on short strings is fragile: detect CJK by character ranges first, restrict results to the target model's supported set with explicit mappings (e.g. `ga → gle_Latn`), and decode HTML entities before detection — misdetection on feed posts previously caused 503s.
