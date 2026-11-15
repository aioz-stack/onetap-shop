# Onetap Marketplace — Development Log

**Current phase:** Phase 2 (Core Foundation) is complete. Phase 3 (Admin Panel) is next.

## Setup

```bash
composer install
cp .env.example .env        # already points at DB_DATABASE=onetap-shop, root, empty password
php artisan key:generate
php artisan migrate         # incremental only. Never migrate:fresh / reset / db:wipe on onetap-shop
php artisan db:seed         # idempotent: roles, permissions, settings, categories, demo accounts (local only)
php artisan storage:link
npm install
composer dev                # server + queue + vite
```

If you only need the server, run `npm run build` once and then `php artisan serve`.

### Demo accounts (local only)

`DemoAccountsSeeder` runs only when `APP_ENV` is `local` or `testing`. Every demo account uses the password `password`.

| Username | Panel | Notes |
| --- | --- | --- |
| admin | /admin | Marketplace administrator |
| customer | / | Has a default address in Quezon City |
| seller | /seller | Also a customer. Owns the "Santos Home Bakery" shop (active) |
| logistics | /logistics | Owner of the "Onetap Express" partner (OTX), which covers Quezon City |
| rider | /rider | Active rider under Onetap Express |

## Phase 1 — done (2026-11-15)

- Inspected the Baseplate React starter (findings in ARCHITECTURE.md §1).
- Wrote the architecture, ERD, API plan, module map and roadmap.

## Phase 2 — done (2026-11-15)

**Repository**
- Moved the starter from `frontend/` to the repo root.
- Renamed the starter's `AGENTS.md` (Laravel Boost guidelines) to `LARAVEL_GUIDELINES.md`. On Windows it would otherwise have overwritten `agents.md`.

**Configuration**
- `.env` and `.env.example` now use `onetap-shop`, root with an empty password, and `APP_NAME="Onetap Marketplace"`.
- Added `laravel/sanctum` 4.4 (approved) and `routes/api.php` under `/api/v1`.

**Database**
- 11 domain migrations covering all 60+ ERD tables, plus Sanctum's tokens table. They are applied to `onetap-shop` (batch 1, 63 tables).

**Models and enums**
- 48 generated models and an extended `User` model, all with relationships, casts and enum casts.
- 23 status enums in `app/Enums`.
- Bank account numbers are stored with an encrypted cast. File paths for proofs and verification documents are hidden from serialization.

**Roles and permissions**
- Tables `roles`, `permissions`, `user_roles`, `role_permissions`; 5 roles and 28 permissions.
- `User::assignRole/removeRole/hasRole/hasPermission/primaryRole/homeRouteName`.
- The legacy `users.role` column is kept in sync with the primary role.

**Middleware**
- `role:<names>` guards each panel.
- `active` signs out (web) or revokes the token (API) of suspended users.
- Throttles: `api` at 60/min and `auth` at 10/min. Web login has its own lockout of 5 failed attempts per username and IP.

**Auth**
- Login with email or username, plus registration (customer profile, referral code and cart created in one transaction) and logout.
- Web uses session auth with CSRF. The API uses Sanctum tokens (`register`, `login`, `logout`, `me`).
- Successful logins, logouts and registrations are written to `audit_logs`.

**Services**
- `RegistrationService`, `LoginService`, `AuditLogger`, `SettingsService` (cached `system_settings`).

**Routing**
- `/` is now the public storefront and `/login` the login page.
- `/dashboard` redirects each user to their own panel.
- 403, 404, 429, 500 and 503 errors render an Inertia error page.

**Frontend**
- Onetap branding: teal palette (`brand-*` tokens), original logo and icon, favicon.
- UI kit in `Components/ui`: Button, Card, StatCard, Badge/StatusBadge, DataTable, Pagination, Modal/ConfirmDialog, form fields, Tabs, Toast, SearchInput, PageHeader, and empty, error and loading states.
- Six layouts: Panel (shared shell), Admin, Seller, Logistics, Rider (mobile, bottom tabs), Customer (storefront).
- `AuthenticatedLayout` now points to `AdminLayout` so the starter's CRUD pages keep working.
- Pages are code-split, so each panel loads only its own page.

## Testing results (2026-11-15)

- `php artisan test`: **55 passed, 425 assertions**. Tests use in-memory SQLite and were confirmed not to touch MySQL.
  - Auth: email and username login, registration validation and referrals, a login lockout test, suspended accounts blocked at login and mid-session.
  - RBAC: every panel opens for its role and returns 403 for the other four roles; guests are redirected; multi-role users; permissions.
  - API: register, login, me, logout and token revocation; JSON validation and 401; suspended token rejected; categories.
  - Seeders: idempotent; dashboards read live counts.
- Browser check on `php artisan serve` with the real MySQL database:
  - Storefront shows the seeded categories.
  - Admin login lands on `/admin` with live counts. Admin opening `/seller` gets the 403 page. `/crud` lists the users.
  - Logout through the user menu works, and the rider can log in by email.
  - Registration shows server-side errors and creates the user, role, profile, cart and audit rows in MySQL.
  - No horizontal overflow at 375px on the rider page, the storefront or the registration page.
- API checked with curl against MySQL: login → me → logout, then 401 on reuse; categories are public.

## Dependency security update — done (2026-11-15, approved)

- `composer update --with-all-dependencies` within the unchanged `composer.json` ranges: 70 packages updated, 3 installed, 1 removed.
  - Laravel 13.32.0 → 13.42.0, inertia-laravel 3.3.0 → 3.7.0, league/commonmark 2.10.1 → 2.10.7, Symfony 8.1.x patches.
  - Transitive major versions: guzzle 7 → 8, guzzlehttp/psr7 2 → 3, symfony/yaml 7.4 → 8.1, brick/math 0.19 → 1.0, laravel/mcp 0.9 → 1.2, laravel/roster 0.5 → 1.0.
- `npm audit fix`: axios is now 1.21.1 and follow-redirects 1.16.3.
- Results:
  - `composer audit` reports no advisories (was 12) and `npm audit` reports 0 vulnerabilities (was 2 high).
  - 55 tests pass, `npm run build` works, and the pages and API respond on the running server.
- npm noted that esbuild's postinstall script is not on its `allowScripts` list. The build works without it. Run `npm install-scripts approve esbuild` if a future build complains.

## Known issues / notes

- The migrations reached MySQL before the explicit `php artisan migrate` step: that step reported "Nothing to migrate" (all 15 already in batch 1). Nothing was dropped and the database was empty beforehand. The earliest migration run was meant to target in-memory SQLite.
- The starter `CRUD` controller still writes the free-text `users.role` column and does not touch `user_roles`. Phase 3 replaces it with full user management.
- The starter demo page `Pages/Dashboard.jsx` (static TailAdmin charts) is unused. It is code-split so it never loads. Remove it in Phase 3.
- Sidebar entries marked "Soon" are disabled placeholders for modules in later phases.
- Storefront search and category links return a "coming soon" state until Phase 4.
- Demo passwords are weak by design and the seeder is limited to local and testing environments.

## Next tasks (Phase 3: Admin Panel)

User management (verification, suspend/reactivate), seller approvals, product categories and moderation, order management tabs, logistics partner and coverage management, finance views, system settings, audit log viewer.
