# Changelog

## [Unreleased]

### 2026-11-16 — Phase 4 (Customer Panel)

**Added**
- **Storefront:** home sections from live data, search with filters and sorts, product pages (gallery, variations, stock, reviews, related), shop pages.
- **Cart and checkout:** cart grouped by seller with per-shop delivery quotes from partner coverage, and a COD checkout that splits orders per shop, reserves stock and fixes commission in one transaction.
- **My Account:** purchases with tracking, cancel, order received, returns, reviews, notifications, chat with shops (auto-greeting), profile and photo, addresses, security (password, sessions), verification upload, and the seller application.
- **Services:** `CartService`, `ShippingFeeService`, `CheckoutService`, `CustomerOrderService`, `EarningsService` (completion postings and hold release), `ReviewService`, `ChatService`, `AccountService`, `Storefront`.
- **Notifications:** `OrderPlaced`, `NewSellerOrder`, and `OrderStatusUpdated` (sent after commit).
- **`onetap:release-earnings`** command, scheduled hourly.
- **Migration:** `seller_orders.earnings_released_at`.
- **API:** catalog and customer endpoints (profile, addresses, cart, orders).
- **`DemoCatalogSeeder`** (local only): 3 shops, 18 products, images, banners.
- 43 feature tests (169 total).

**Fixed**
- Login no longer returns users to a page their role can't open.
- An N+1 lazy load in checkout.
- A stale relation in the seller application.
- A chat page crash caused by the Promise from `scrollIntoView()`.

### 2026-11-15 — Phase 3 (Admin Panel)

**Added**
- The complete admin panel: 61 routes and 25 pages.
  - Dashboard with KPIs and a sales chart.
  - Users, verification, sellers, products, categories, orders, returns and refunds.
  - Logistics partners and coverage, riders, shipment and COD monitoring.
  - Finance overview, transactions with CSV export, seller settlements, and monthly reports with CSV export.
  - Settings (9 groups) and the audit log viewer.
- Shared domain services: order status machine, inventory ledger, refunds, money ledger and seller balances, withdrawals, finance reports, seller approval, product moderation, account management, logistics partners, and a settings schema.
- Settings are wired into the login lockout, the API rate limit, API token expiry and the frontend's public settings.
- Sidebar work-queue badges.
- Admin components: `ActionButton` (confirm + reason + submit), filter selects, date range, status tabs, detail list.
- 14 model factories and 71 feature tests (126 total, all passing).

**Changed**
- The starter CRUD validates roles and syncs them through `user_roles`. It is no longer in the nav.
- Money totals are normalized to two-decimal strings (`App\Support\Money`).
- `Card` has `min-w-0` so tables scroll inside it on phones.

**Removed**
- The unused starter demo page `Pages/Dashboard.jsx`.

**Fixed**
- Saving a setting now also clears the cached public settings.

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
