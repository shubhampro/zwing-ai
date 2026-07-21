---
name: scaffold-laravel-inertia-react
description: Use when the user wants to scaffold a brand-new Laravel + Inertia.js v3 + React 19 application that mirrors the zwing-ai stack (Laravel 13, Fortify 2FA, Tailwind v4, Wayfinder, MongoDB adapter, league/csv, Pest 4, Pint, ESLint 9, Prettier 3). Triggers on "new Laravel project", "scaffold a zwing-ai-style app", "create a Laravel + Inertia + React starter", "bootstrap a new Inertia v3 app", "scaffold a stock/invoice reconciliation app", or any request to spin up a fresh Laravel SPA with this exact toolchain. Do NOT use for adding features to an existing Laravel project, for non-Laravel stacks, or for backend-only Laravel apps.
license: MIT
metadata:
  project: zwing-ai
  stack: laravel-13 + inertia-v3 + react-19 + tailwind-v4
---

# Scaffold a Laravel + Inertia v3 + React 19 App (zwing-ai style)

A step-by-step checklist. Run each step in order. **Stop and ask the user** if a step requires a decision (project name, app name, MongoDB host, etc.). After every batch of changes, run the relevant lint/test commands. Do not skip verification.

> Use **PHP 8.4** (matches zwing-ai). Composer must allow `php: ^8.3` minimum.

---

## 1. Verify Prerequisites

- Check PHP version: `php -v` — must be 8.3 or 8.4
- Check Composer: `composer --version`
- Check Node: `node -v` — must be 20+
- Check pnpm or npm: `pnpm -v` or `npm -v` (zwing-ai uses npm with a `pnpm-workspace.yaml`, so npm is fine)
- Check Herd/Valet if user wants a `.test` domain: `herd list`

If anything is missing, tell the user and stop.

## 2. Create the Laravel Skeleton

- Run: `composer create-project laravel/laravel <project-name>`
- The skeleton ships with Laravel 13; verify `php artisan --version` reports 13.x
- `cd <project-name>`

## 3. Install Backend Dependencies

Run as a single batch:

- `composer require inertiajs/inertia-laravel:^3.0`
- `composer require laravel/fortify:^1.34`
- `composer require laravel/wayfinder:^0.1.14`
- `composer require mongodb/laravel-mongodb:^5.7`
- `composer require league/csv:^9.28`
- `composer require laravel/tinker:^3.0`

Dev dependencies:

- `composer require --dev laravel/boost:^2.2`
- `composer require --dev laravel/pail:^1.2.5`
- `composer require --dev laravel/pint:^1.27`
- `composer require --dev pestphp/pest:^4.4`
- `composer require --dev pestphp/pest-plugin-laravel:^4.1`
- `composer require --dev mockery/mockery:^1.6`
- `composer require --dev nunomaduro/collision:^8.9`
- `composer require --dev fakerphp/faker:^1.24`

## 4. Install Node Dependencies

Frontend:

- `npm install @inertiajs/react@^3.0.0`
- `npm install @inertiajs/vite@^3.0.0`
- `npm install react@^19.2.0 react-dom@^19.2.0`
- `npm install @types/react@^19.2.0 @types/react-dom@^19.2.0`
- `npm install @vitejs/plugin-react@^5.2.0`
- `npm install @laravel/vite-plugin-wayfinder@^0.1.3`
- `npm install laravel-vite-plugin@^3.0.0`
- `npm install typescript@^5.7.2`

Styling (Tailwind v4 + Radix primitives used in zwing-ai):

- `npm install tailwindcss@^4.0.0 @tailwindcss/vite@^4.1.11`
- `npm install @radix-ui/react-avatar @radix-ui/react-checkbox @radix-ui/react-collapsible @radix-ui/react-dialog @radix-ui/react-dropdown-menu @radix-ui/react-label @radix-ui/react-navigation-menu @radix-ui/react-select @radix-ui/react-separator @radix-ui/react-slot @radix-ui/react-toggle @radix-ui/react-toggle-group @radix-ui/react-tooltip`
- `npm install @headlessui/react`
- `npm install class-variance-authority clsx tailwind-merge lucide-react sonner input-otp tw-animate-css`

Dev tooling:

- `npm install --save-dev eslint@^9.17.0 @eslint/js@^9.19.0 eslint-config-prettier@^10.0.1 prettier@^3.4.2 prettier-plugin-tailwindcss@^0.6.11`
- `npm install --save-dev eslint-import-resolver-typescript eslint-plugin-import eslint-plugin-react eslint-plugin-react-hooks`
- `npm install --save-dev typescript-eslint @stylistic/eslint-plugin`
- `npm install --save-dev @types/node@^22.13.5 babel-plugin-react-compiler@^1.0.0 globals@^15.14.0`
- `npm install --save-dev concurrently@^9.0.1`

## 5. Environment & Keys

- Copy `.env.example` to `.env`
- Generate app key: `php artisan key:generate`
- Set `APP_NAME` to the user's chosen name
- Set `DB_CONNECTION=sqlite` (default for local)
- Create the SQLite file: `touch database/database.sqlite`
- For MongoDB credentials, leave `MONGODB_SSH_*` blank for now — fill in when user provides
- For MySQL remote, leave `MYSQL_REMOTE_*` blank for now
- Verify with: `php artisan config:show app.name`

## 6. Configure Vite

- Replace the default `vite.config.ts` with the zwing-ai shape:
  - import `vue`/`react` plugin via `@vitejs/plugin-react`
  - import `laravel-vite-plugin` with the standard `input` array (resources/js/app.tsx, css/app.css)
  - import `tailwindcss/vite`
  - import `wayfinder` from `@laravel/vite-plugin-wayfinder`
  - export `defineConfig({ plugins: [...] })`
- Set `ssr: 'resources/js/ssr.tsx'` for Inertia v3 SSR (optional, but include the file)

## 7. Configure Tailwind v4

- Use the `@tailwindcss/vite` plugin (no `tailwind.config.js` required for v4)
- Create `resources/css/app.css` with the v4 `@import "tailwindcss";` directive plus the `@theme` block from zwing-ai (shadcn-style CSS variables for light/dark)
- Import it from `resources/js/app.tsx`

## 8. Configure Inertia

- Publish Inertia config: `php artisan vendor:publish --tag=inertia-config`
- Set `config/inertia.php`:
  - `testing.ensure_pages_exist` → `true`
  - `ssr.enabled` → `false` in dev (toggle later)
  - `history->encrypt` → `false`

## 9. Configure Fortify

- Publish Fortify config: `php artisan vendor:publish --tag=fortify-config`
- In `config/fortify.php` enable features the user wants:
  - `Features::registration()` — ask user
  - `Features::resetPasswords()`
  - `Features::emailVerification()`
  - `Features::twoFactorAuthentication()` — recommended
- Customize `home` redirect in `app/Providers/FortifyServiceProvider.php`
- Verify routes: `php artisan route:list --name=login`

## 10. Configure Wayfinder

- Wayfinder auto-generates types from `routes/`. No manual config needed beyond the Vite plugin (step 6).
- Confirm: `php artisan list` should show `wayfinder:generate` and the Vite plugin should be wired.
- Run: `php artisan wayfinder:generate` to seed `resources/js/actions/` and `resources/js/routes/`

## 11. Configure TypeScript

- Replace `tsconfig.json` with zwing-ai's (path alias `@/*` → `resources/js/*`, strict mode, jsx react-jsx)
- Verify: `npm run types:check`

## 12. Configure ESLint + Prettier

- Replace `eslint.config.js` with zwing-ai's flat config (typescript-eslint, react, react-hooks, import resolver with tsconfig paths, prettier compat)
- Create `.prettierrc` with the project's choices (semi, singleQuote, trailingComma, tailwind plugin)
- Create `.prettierignore` ignoring `node_modules`, `vendor`, `public/build`
- Verify: `npm run lint:check && npm run format:check`

## 13. Configure Pest

- Publish Pest: `php artisan pest:install`
- Confirm `tests/Pest.php` has `RefreshDatabase` trait
- Confirm `phpunit.xml` sets the SQLite test database env vars

## 14. Configure Pint

- Create `pint.json` matching zwing-ai's preset choice (default Laravel is fine)
- Verify: `vendor/bin/pint --test`

## 15. Add Composer Scripts

Add the following under `scripts` in `composer.json` (copy from zwing-ai):

- `setup` — install, env copy, key:generate, migrate, npm install, npm run build
- `dev` — `concurrently` running `artisan serve`, `queue:listen`, `pail`, `vite`
- `lint` — `pint --parallel`
- `lint:check` — `pint --parallel --test`
- `test` — `config:clear`, `lint:check`, `artisan test`
- `ci:check` — npm lint/format/types + composer test
- `post-autoload-dump` — `package:discover`
- `post-update-cmd` — publish laravel-assets + `boost:update`

Verify: `composer install` (idempotent) and `composer test` (should pass on a fresh app).

## 16. Add npm Scripts

In `package.json`:

- `dev` → `vite`
- `build` → `vite build`
- `build:ssr` → `vite build && vite build --ssr`
- `lint` → `eslint . --fix`
- `lint:check` → `eslint .`
- `format` → `prettier --write resources/`
- `format:check` → `prettier --check resources/`
- `types:check` → `tsc --noEmit`

Verify: `npm run types:check && npm run lint:check && npm run format:check`.

## 17. Create the Base Inertia Frontend

Create the following skeleton pages and layouts (match zwing-ai's structure):

- `resources/js/app.tsx` — Inertia root, hydrates `<App />`
- `resources/js/ssr.tsx` — SSR entry (mirror of `app.tsx` with `createInertiaApp` + `hydrateRoot`)
- `resources/js/layouts/` — `AuthenticatedLayout`, `GuestLayout`
- `resources/js/components/` — `ui/Button`, `ui/Input`, `ui/Card` etc. (Radix-based, cva variants)
- `resources/js/pages/welcome.tsx`
- `resources/js/pages/dashboard.tsx`
- `resources/js/pages/auth/login.tsx`, `register.tsx`, `forgot-password.tsx`, `reset-password.tsx`, `verify-email.tsx`, `two-factor-challenge.tsx`, `confirm-password.tsx`
- `resources/js/pages/settings/profile.tsx`, `password.tsx`, `security.tsx`, `appearance.tsx`, `delete-user.tsx`

Inertia middleware:

- `app/Http/Middleware/HandleAppearance.php` — reads session for `appearance`
- `app/Http/Middleware/HandleInertiaRequests.php` — shares auth user, flash, ziggy routes

## 18. Run First Migration

- `php artisan migrate`
- Confirm the default `users`, `password_reset_tokens`, `sessions`, `cache`, `jobs` tables exist
- For 2FA: `php artisan vendor:publish --tag=fortify-migrations` then `php artisan migrate` to add `two_factor_*` columns

## 19. Verify the Full Stack

Run all of these and confirm green:

- `php artisan test` — Pest passes
- `vendor/bin/pint --test` — formatting clean
- `npm run lint:check` — ESLint clean
- `npm run format:check` — Prettier clean
- `npm run types:check` — TypeScript clean
- `npm run build` — Vite production build succeeds
- `composer dev` (or `php artisan serve` + `npm run dev` in two shells) — app loads at the URL Herd/Valet/serve prints
- `php artisan route:list` shows the expected Fortify + app routes

## 20. Document the Project

- Replace the default README with a short one covering:
  - Stack versions
  - Setup command (`composer setup`)
  - Dev command (`composer dev`)
  - Test/lint/format/typecheck commands
  - MongoDB + remote MySQL env vars (link to `.env.example`)
- Add a `LICENSE` file if the user has a preference (zwing-ai uses MIT)

---

## Optional Follow-ups (ask the user)

- Enable Herd/Valet for a `.test` domain
- Wire the MongoDB SSH tunnel feature (needs `SshTunnelManager` service from zwing-ai)
- Add the dynamic-database connections feature (`database_connections` table + `ResolvesRemoteWriteConnection`)
- Set up CI (GitHub Actions matrix: PHP 8.3/8.4 × Node 20)
- Add the `ReconciliationSummaryService` pattern for stock/invoice diffing

---

## Verification Gate

**Do not mark the scaffold complete until step 19 is fully green.** If any command fails, fix it before moving on. If the user wants to skip a step, get explicit confirmation.
