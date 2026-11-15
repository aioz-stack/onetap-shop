# Changelog

## [Unreleased]

### 2026-11-15 — Security dependency update
- Updated Composer packages within the existing ranges (Laravel 13.42.0, inertia-laravel 3.7.0, league/commonmark 2.10.7, Symfony patches; transitive guzzle 8 and symfony/yaml 8). This cleared all 12 `composer audit` advisories.
- `npm audit fix` updated axios to 1.21.1 and follow-redirects to 1.16.3, clearing 2 high-severity advisories.
- The test suite (55) and the production build pass on the new versions.

### 2026-11-15 — Phase 2 (core foundation)

**Added**
- Laravel Sanctum, with a versioned `/api/v1` API: `auth/register`, `auth/login`, `auth/logout`, `auth/me`, `categories`.
- 11 domain migrations creating every ERD table (identity, RBAC, logistics, catalog, marketing, ordering, shipments, finance, communication, administration). They are applied to `onetap-shop`.
- 48 Eloquent models and 23 status enums.
- Roles and permissions: `role` and `active` middleware, plus seeders for roles, permissions, settings, categories and demo accounts.
- Registration with a customer profile, referral code and cart; login by email or username with throttling; audit logging.
- Onetap branding, a UI component kit, and Admin, Seller, Logistics, Rider (mobile) and Customer (storefront) layouts with dashboards that read live data.
- Inertia error page for 403, 404, 429, 500 and 503.
- 47 new feature tests (55 total, all passing).

**Changed**
- Moved the starter from `frontend/` to the repo root. Its `AGENTS.md` is now `LARAVEL_GUIDELINES.md`.
- `/` is the public storefront, the login page moved to `/login`, and `/dashboard` redirects to the user's own panel.
- Users are soft-deleted.
- The starter CRUD now requires the admin role.
- The starter Header and Sidebar now take per-panel navigation.
- Pages are code-split.
- Starter tests were updated for the new routes, the `login` field and admin-only CRUD.

**Fixed**
- Starter Header: `IconUsers` was used without being imported, and the avatar pointed at a personal photo.

### 2026-11-15 — Phase 1 (planning)
- Cloned the Baseplate React starter (Laravel 13 + Inertia + React 19).
- Added ARCHITECTURE.md, DATABASE.md (ERD), API_DOCUMENTATION.md, MODULES.md, DEVELOPMENT.md.
