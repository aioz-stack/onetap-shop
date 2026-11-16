# Changelog

## [Unreleased]

### 2026-11-16 — Phase 9 (Testing and Finalization)

**Added**
- Permission sweep, end-to-end workflow and security tests (26 tests in `tests/Feature/Phase9`).
- `SecurityHeaders` middleware (frame, content-type, referrer and permissions policies; HSTS over HTTPS).
- Rate limits on refund requests, reviews, starting a chat, identity uploads and seller applications.
- Audit entries for promotion edits and deletions.

**Fixed**
- The admin order page crashed for delivered orders with a proof photo (wrong route name).
- The customer order history skipped the delivery steps, so it didn't match the "Out for delivery" tracking status; it now includes them (web and API).

**Changed**
- Seller automated replies: a new **off-work message** below the greeting, with working days and opening/closing times (overnight hours supported, read in the marketplace timezone). Customers who message outside those hours get it instead of the greeting, at most once every 12 hours per chat; vacation mode's away message still takes priority. Migration `2026_11_16_130000_add_schedule_to_shop_auto_replies` adds a nullable `schedule` column (applied to MySQL). 6 tests in `OffWorkMessageTest.php`.
- Automated replies: **On/Off switches** in the top-right of the Greeting and Off-work message cards (new `Switch` component; `PATCH /seller/auto-replies/{reply}/active` changes only on/off). With nothing saved yet, switching the greeting on opens its form and switching the off-work message on saves it. Saving the off-work hours no longer changes its on/off state.
- Product pages show the seller's shop above the product details: logo, name, city, products, rating, completed orders and join date, with **Chat seller** (guests are sent to sign in; hidden for staff) and **View shop** buttons. A seller viewing their own product gets a Seller Center link instead of a chat button.
- The starter user screen (`/crud`) now follows Admin → Users rules: audited, strong passwords, roles replaced instead of added, no self-demotion, administrators cannot be deleted.

### 2026-11-16 — Phase 8 (System Integration)

**Added**
- `Communication\Notifier` and a generic `Alert` notification: cross-panel alerts for admins, sellers, customers, logistics partners and riders, sent after commit.
- Settings: "Shipments no logistics partner covers" and "Also email users" toggles; the admin team email now receives the enabled admin alerts.
- Notification bell (unread badge, latest alerts, mark all read) for the admin, seller and logistics panels and the rider app; `/notifications/*` web routes and `/api/v1/notifications`.
- Seller API (dashboard, products with photos, stock, orders), logistics API (dashboard, shipments, assign/unassign, riders) and customer refund/review endpoints.
- Admin Platform Vouchers screen; Onetap-funded vouchers with a `platform_voucher` ledger entry and net revenue in Finance (overview, monthly report, CSV).
- 20 tests in `tests/Feature/Phase8`.

**Changed**
- Price sort and price filters use promotion prices.
- Platform vouchers can be used once per order and no longer reduce the seller's earning.
- The rider assignment alert links to the job; the generic "order refunded" alert was replaced by the refund decision alert.
- The voucher manager is shared by the Seller Center and the admin panel.
- The checkout voucher box mentions Onetap codes.

**Fixed**
- The login-throttle test no longer fails when the countdown ticks from 60 to 59 seconds.

### 2026-11-16 — Phase 7 (Rider Panel)

**Added**
- Migration `2026_11_16_120000_create_rider_shifts_and_rider_pay`: a `rider_shifts` log and `logistics_partners.rider_fee_per_delivery`.
- **`RiderShiftService`:** sign in/out of shifts, available/unavailable, and an on-shift check before any delivery update.
- **`DeliveryService`:** rider-side delivery flow (decline, accept, step by step to out for delivery, failed attempts, try again, return to shop). `complete()` saves the private proof photo, recipient, GPS and COD collection (discrepancies flagged), then marks the parcel Delivered and the order paid when the cash covers it. Also computes daily earnings.
- Rider app (mobile-first): Home with shift controls and today's numbers, Deliveries (tabs, search, job page with next-step buttons, call/maps links, camera proof form), COD (remit, collections, 7-day earnings, history) and Profile (contact number, password, shifts). Tab badges show new jobs and cash in hand.
- Rider REST API: dashboard, availability, assignments, delivery detail, status, proof upload, proof photo, COD and remittances.
- Private proof-photo routes for the rider, the owning logistics partner (shown on the shipment page with a GPS map link) and admins (linked from the admin order page).
- Rider pay per delivery: editable by admins on the partner form and by operators on the Coverage page.
- 30 tests in `tests/Feature/Phase7`.

**Changed**
- `StatCard` has a `compact` mode for two-up phone layouts.
- The demo seeder gives Onetap Express a ₱35 rider fee.

### 2026-11-16 — Phase 6 (Logistics Panel)

**Added**
- **`ShipmentStatusService`:** the full delivery flow, with seller-order sync (picked up → Shipping, delivered → Delivered, returned → Returned), history with GPS, attempt counting and closing rider jobs.
- **`DispatchService`:** assign, reassign and unassign riders, redelivery jobs, and admin routing of uncovered shipments. Riders get a `DeliveryAssigned` notification.
- **`RemittanceService`:** rider remittance submission, and confirmation (ledger entry) or dispute on mismatch.
- **`RiderService`:** create, update, approve and suspend riders (suspension requeues uncollected pickups), plus rider performance.
- **`CoverageService`:** shared by the admin and logistics screens, with case-insensitive duplicates.
- **Logistics Panel:** dashboard, shipments (tabs, detail, assign, reschedule, return), riders (list, add, edit, approve, suspend, performance), coverage, daily COD, remittances (count and confirm), earnings with a 12-month report and CSV.
- **Admin:** "Route to partner" on shipment monitoring, with a "No partner yet" filter and banner.
- `logistics.partner` middleware and sidebar badges.
- 26 feature tests (241 total).

**Changed**
- Cancelling a packed order closes its waiting shipment and rider job.
- Admin rider suspension now uses `RiderService`, so it also requeues pickups.
- `ShipmentStatus` gained a transition map and tab groups.

**Fixed**
- A flaky admin user-search test (a Faker name could contain the search term).

### 2026-11-16 — Phase 5 (Seller Center)

**Added**
- **Seller Center:**
  - Dashboard with a 30-day sales chart, orders to ship and low stock.
  - Products with photo uploads, variations, the review workflow and an inventory page.
  - Orders: accept, ready for pickup (creates a routed COD shipment) and cancel.
  - Shop settings (profile, logo and banner, template, accent color, announcement, vacation mode), chat inbox, automated replies (greeting, away, keyword) and review replies.
  - Password-confirmed finance: balance, withdrawals, encrypted payout accounts, income statement, transactions.
  - Marketing: vouchers, promotions, referrals.
- **Services:** `SellerProductService`, `SellerOrderService`, `ShipmentService`, `PricingService`, `VoucherService`.
- **Middleware:** `seller.shop`, plus the `/confirm-password` page for `password.confirm`.
- **Storefront, cart, checkout and API now apply promotions and per-shop vouchers.** Commission is charged on the discounted items; the shop funds its own vouchers.
- **Migration:** `seller_orders.voucher_id`.
- **Shop pages** use the seller's template, accent color and announcement. `/register?ref=CODE` prefills the referral code.
- 43 feature tests (213 total).

**Changed**
- Seller role tests now require an approved shop.
- The `NewSellerOrder` notification links to the seller's order page.

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
