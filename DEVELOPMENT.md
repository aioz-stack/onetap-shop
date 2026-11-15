# Onetap Marketplace — Development Log

**Current phase:** Phase 4 (Customer Panel) is complete. Phase 5 (Seller Center) is next.

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

## Phase 3 — Admin Panel — done (2026-11-15)

**Shared domain services.** Later panels must reuse these rather than reimplement them.
- `Ordering\OrderStatusService` is the only place a seller order's status changes. It enforces the transition map in `SellerOrderStatus::allowedTransitions()`, stamps timestamps, writes `order_status_histories`, audits the change, and applies stock effects:
  - cancel → release the reservation
  - ship → convert the reservation into a sale and update `sold_count`
  - return → restock
- `Ordering\InventoryService`: reserve, release, sale, return, restock and adjust. Every change locks the row (`lockForUpdate`) and writes a `product_inventory` ledger row.
- `Ordering\RefundService`:
  - Approve, optionally restocking returned items. A refund transaction is recorded, and the seller's earning is clawed back if it was already credited.
  - Reject, which restores the order's status from before the return.
- `Finance\LedgerService`: append-only `transactions`, plus seller balance moves (`creditPending`, `creditAvailable`, `debitAvailable`, `clawBack`). All math uses bcmath; balances never go negative.
- `Finance\WithdrawalService`: request (holds the amount out of available) → approve → mark paid (ledger entry, `total_withdrawn`). Reject returns the held amount.
- `Finance\FinanceReportService`: GMV, commission, seller earnings, logistics fees, refunds and payouts, per range and per month. Revenue is recognised at order completion.
- `Catalog\SellerApprovalService`:
  - Approve grants the seller role and opens the shop and its balance.
  - Reject records the reason.
  - Suspend hides the shop, unpublishes live products and removes the seller role.
  - Reinstate restores the shop and role; products stay unpublished until the seller republishes them.
- `Catalog\ProductModerationService`: approve (needs an active variation), reject, unpublish, flag violation, resolve violations (back to review).
- `Identity\UserAccountService`:
  - Create and update staff accounts with role sync.
  - Suspend: revokes API tokens and deletes sessions. Admins cannot suspend themselves or drop their own admin role.
  - Reactivate, and review verification documents.
- `Logistics\LogisticsPartnerService`: create a partner together with its operator account, update it, and change rider status.
- `Administration\SettingsSchema`: the single definition of every editable setting (type, rules, default, public). The settings page and its seeder are generated from it.
- `App\Support\Money::format()` normalizes money totals, because SUM() returns different types on MySQL and SQLite.

**Admin panel.** 61 routes under `/admin`, all `role:admin`, every write audited.
- **Dashboard:** 10 KPIs, a 30-day sales and orders chart, recent transactions and recent activity.
- **People:**
  - Users: role tabs, status filter, search, create staff, edit roles, suspend (with a reason) and reactivate.
  - Verification: queue of documents streamed from the private disk; approve or reject.
  - Sellers: pending, approved, rejected and suspended tabs; detail page with performance (GMV, cancellation rate, rating) and violations.
- **Catalog:**
  - Products: status tabs, category-tree filter, search; detail page with images, variations and stock (on hand, reserved, available), and violation history.
  - Categories: two-level tree with add, edit, show/hide and sort. Deleting is only allowed when a category is empty.
- **Orders:**
  - Orders: tabs (Unpaid, To Ship, Shipping, Completed, Cancelled, Return/Refund), search, date range; detail page with items, totals, commission, customer, address, payment, shipment and a status timeline; cancel with a reason.
  - Returns & refunds: approve (optional restock) or reject.
- **Logistics:**
  - Partners: create with an operator account, edit; coverage areas by city or barangay with fee overrides, deduplicated.
  - Riders across all partners: filters, performance numbers, approve, suspend and activate.
  - Shipment monitoring tabs, and COD monitoring (collections, remittances, discrepancies).
- **Finance:** overview with a 6-month chart; transactions ledger with filters and CSV export; seller settlements (approve → record payment with a bank reference, or reject); 12-month report with CSV download.
- **Settings:** 9 groups (general, branding, payment, shipping, finance, orders, notifications, API, security).
  - Wired into the app: login lockout attempts and duration, API rate limit, API token lifetime, and public settings shared with the frontend (e.g. the marketplace name).
- **Audit logs:** filter by area, user or IP, and date; view before/after changes.
- **Sidebar:** badge counts for pending sellers, products under review, refund requests, withdrawals and documents to verify.

**Cleanup**
- The starter `CRUD` now validates the role against `RoleName` and attaches it through `assignRole()`, so `user_roles` stays consistent. It is no longer in the nav, because `/admin/users` replaces it.
- Removed the unused starter demo page `Pages/Dashboard.jsx`.
- Fixed `SettingsService`: saving a setting now also clears the public-settings cache.
- `Card` now has `min-w-0`, so wide tables scroll inside their card on phones instead of stretching the page.

## Phase 3 testing results (2026-11-15)

- `php artisan test`: **126 passed, 1084 assertions** (71 new). They cover:
  - every admin page and detail page rendering, and 403 for all four other roles;
  - user filters, staff creation, role sync, self-protection, suspension revoking tokens, verification review and private file access;
  - seller approve, reject, suspend and reinstate with their side effects, and seller performance figures;
  - product moderation transitions and filters, and category nesting and delete guards;
  - order tabs and search, cancel releasing stock, shipping converting reservations into sales, reservation limits, refund restock and clawback, refund reject restoring status, invalid transitions;
  - the withdrawal lifecycle and balances, finance figures, CSV exports;
  - partner creation with operator login, coverage CRUD and dedup, rider suspension;
  - settings validation, persistence and audit; lockout and token expiry following settings; audit log filters.
- Browser check on MySQL as admin:
  - All 20 admin pages return 200 with the correct page and render without client errors.
  - Created and then deleted a category through the modal. Deleting "Groceries" was blocked with an error toast.
  - Suspended the QA customer: an empty reason showed the required-field error, a real reason saved with the toast, and reactivating restored the account.
  - The audit log shows both actions. The transactions CSV downloads with the right headers, and the report CSV has 12 months plus a header row.
  - No horizontal overflow at 375px on 8 admin pages after the `min-w-0` fix.

## Phase 4 — Customer Panel — done (2026-11-16)

**Storefront** (public)
- **Home:** rotating banners (from the `banners` table), categories, featured (top-rated), recommended (from categories you have bought from, otherwise random), popular shops (most completed orders), best sellers and recently added.
- **`/search`:**
  - Matches text in product name, brand, description and shop name.
  - Filters: category including its sub-categories, price range, in stock only.
  - Sorts: best match, newest, price, best selling, top rated. Results are paginated, and matching shops are shown first.
- **Product page:**
  - Image gallery, variation picker with live price and available stock, quantity stepper, Add to cart and Buy now, and the vacation notice.
  - Seller card with Chat, a ratings summary with star distribution, paginated reviews with masked names, and related products.
- **Shop page:** banner, logo, rating, location, description, vacation notice, product search and sort, and a Chat button.
- **Visibility rule (`Product::scopeVisible`):** live products in an active shop. Everything else returns 404.

**Cart and checkout** (customer role)
- **`CartService`:**
  - Groups items by shop with live prices.
  - Flags problems per line: unavailable, shop inactive, vacation, out of stock, only N left.
  - Selection per item, per shop or all; quantities are capped at the available stock.
  - You can't buy from your own shop. Buy now checks out just that item.
- **`ShippingFeeService`:**
  - Uses the cheapest active partner covering the address's barangay or whole city: the area's fee override, otherwise the partner's base fee.
  - Without coverage, the default fee applies. The free-shipping threshold is per shop.
- **`CheckoutService`** runs as one DB transaction. It:
  - locks the variations in id order and re-checks every item;
  - snapshots the delivery address;
  - creates one order plus one seller order per shop, with commission at the current rate (bcmath);
  - reserves stock through `InventoryService`, writes status history and a pending COD payment, and clears the purchased cart lines;
  - checks COD is enabled and under the maximum amount.
  - Afterwards it notifies the customer (`OrderPlaced`) and each seller (`NewSellerOrder`).
- **Payment methods:** only COD is selectable; GCash, Maya and card are shown as "Coming soon". Checkout is throttled to 10 per minute.

**My Account** (`/account/*`)
- **Purchases:**
  - Tabs matching the admin's, plus search.
  - Order detail with a progress bar, items, totals, address, history and the shipment tracking number.
  - Customers can cancel while the order is Unpaid or Pending; stock is released through `OrderStatusService`.
- **Order received:** a delivered order moves to Completed, and `EarningsService::postCompletion` records the commission, credits the seller's pending balance and writes the ledger entries. It's idempotent.
- **Returns and refunds:** one open request per order, the amount capped at the items total, and the order moves to `return_requested` (it then shows up in the admin queue).
- **Reviews:** verified purchases only, once per item after completion. Product and shop rating averages are recalculated each time.
- **Notifications:** database notifications, listed, opened (internal links only) or marked all read. `OrderStatusService` notifies the customer after commit whenever someone else changes their order.
- **Chat:** start a chat from a product or shop page. The shop's greeting auto-reply goes out on the first message, there's an unread count, it polls every 5 seconds, and only participants can open a thread.
- **Profile:** name, email (changing it resets verification), phone, birthday, gender, and a photo (JPG/PNG/WebP up to 2 MB) on the public disk.
- **Addresses:** create, edit, delete and set default. There is always exactly one default.
- **Security:** change password (needs the current one; signs out other sessions and revokes API tokens), list active sessions, and sign out other devices (needs your password).
- **Verification upload:** JPG/PNG/PDF up to 5 MB, stored on the private disk, and appears in the admin queue.
- **Sell on Onetap:** apply with business, shop and pickup details. This creates a pending seller profile and shop; admin approval opens it. A rejected application can be resubmitted.

**Background job**
- `php artisan onetap:release-earnings` (scheduled hourly) moves earnings from pending to available after `finance.earnings_hold_days`. It uses `seller_orders.earnings_released_at`, a new incremental migration that is already applied to MySQL.

**API** (Sanctum, `role:customer`)
- Public: products (search, filter, sort, `per_page`), product detail, shops, shop detail.
- Customer: profile get/update, addresses CRUD, cart (summary, add, update, remove), place an order, order list (by tab), order detail, cancel, confirm received.
- The API uses the same services and resources as the web pages.

**Demo data (local only)**
- `DemoCatalogSeeder` adds 3 shops (Santos Home Bakery, Aling Nena Sari-sari, Bayani Hardware), 18 products and 22 variations (one deliberately out of stock), generated SVG product images, greeting auto-replies and 2 banners.
- New demo sellers: `nena` and `bayani`, password `password`.

**Fixes found while testing**
- Login used `redirect()->intended()` blindly, so a customer who had opened `/admin` while signed out landed on a 403. Login now returns to the requested page only when the user's role can open it. A regression test covers this.
- Checkout lazy-loaded each locked variation's product (an N+1 query that throws locally); it now uses the eager-loaded product.
- `applyToSell` trusted a cached relation and could hit the unique index; it now queries fresh.
- The chat page crashed in current Chromium: an expression-bodied `useEffect` returned the Promise from `scrollIntoView()` as its cleanup. Effects now use block bodies.
- The refund clawback docstring wrongly said balances never go negative; the available balance can go negative when the seller owes the marketplace.

## Phase 4 testing results (2026-11-16)

- `php artisan test`: **169 passed** (43 new). They cover:
  - storefront sections and visibility, search filters and sorts, product and shop pages, the public API;
  - cart merging, stock caps, vacation and own-shop blocks, partner coverage fees, the default fee and free shipping;
  - the checkout split, commission, reservations, cart clearing, notifications and owner-only success page; stock running out mid-checkout; COD limit and disabled methods; address ownership; empty cart; Buy now; the full API checkout and cancel;
  - order tabs and ownership, cancel rules, confirm received posting commission and pending earnings (idempotent), earnings release after the hold, refund requests reaching the admin queue, the review gate and rating aggregates, status notifications;
  - every account page rendering, profile photo validation, the single default address, password change revoking sessions and tokens, private verification upload, the apply-to-sell → admin-approval flow, chat with auto-greeting and outsider 404, the API profile and addresses, and the intended-redirect fix.
- Browser check on MySQL as the demo customer:
  - Searched "ensaymada", picked the "Box of 6" variation (price updated to ₱360), added it to the cart, then added 2 LED bulbs from the Bayani Hardware shop page (its out-of-stock item shows "Sold out").
  - The cart showed 2 shop groups: items ₱530 + delivery ₱118 = ₱648.
  - Checkout preselected the default address, with COD selected and the other methods disabled. Placing the order created `OT261115I73DWN` with 2 seller orders (₱419 and ₱229).
  - In MySQL: commission ₱18.00 and ₱8.50, seller earnings ₱342.00 and ₱161.50, stock reserved with ledger rows, a ₱648 pending COD payment, 3 notifications, and an empty cart.
  - Cancelled the hardware order from My Purchases: its reservation went back to 0, with a release ledger row and a status history entry.
  - Chat with Santos Home Bakery: messages sent, the greeting auto-replied, and the thread rendered after the `useEffect` fix.
  - All 19 storefront and account pages render and fit at 375px.
- API checked with curl against MySQL: search, product detail (available stock reflects the open reservation), and the cart returns 401 without a token.

## Known issues / notes

- The migrations reached MySQL before the explicit `php artisan migrate` step: that step reported "Nothing to migrate" (all 15 already in batch 1). Nothing was dropped and the database was empty beforehand.
- **Staff accounts (admin, logistics, rider) cannot shop.** The cart, checkout and My Account require the customer role; sellers have it. Staff see an explanation on product pages.
- Vouchers and promotions are not applied at checkout yet (`discount` is always 0). Sellers create them in Phase 5.
- Refund requests are text-only; photo evidence is not collected yet.
- Chat uses 5-second polling, not websockets. The seller's side of chat is Phase 5.
- Orders can only reach "Delivered", and so be confirmed as received, once the logistics and rider flows exist (Phases 6–7). Until then, those paths are covered by tests with factory data.
- Product images on real shops come from seller uploads in Phase 5; the demo uses generated SVGs.
- Storefront product images use `loading="lazy"`, so a browser tab that isn't on screen doesn't draw them (that's why the hidden test pane showed 0 loaded). This is expected behavior.
- **The `general.timezone` setting is stored but not applied.** The app clock is still `config('app.timezone')` = UTC (for example, CSV file names use UTC). Switching the app timezone changes how existing timestamps are read, so it needs a deliberate decision.
- Notification settings (admin email and event toggles) are stored, but no emails are sent until notifications are built in Phase 8.
- The branding color setting is for emails and the future mobile apps; the web theme uses the built-in palette.
- Shipments, COD collections, remittances, refund requests, withdrawal requests and verification uploads are created by later phases (4–7). Their admin screens are built and tested with factory data, but they are empty on the real database until then.
- Sidebar entries marked "Soon" in the seller and logistics panels are placeholders for Phases 5–6.
- Demo passwords are weak by design and the seeder is limited to local and testing environments.
- Dev database leftovers from the browser checks:
  - `qa_customer` (Phase 2).
  - Order `OT261115I73DWN`: the bakery seller order is pending, which is useful for the Phase 5 seller flow; the hardware order is cancelled.
  - A chat thread with Santos Home Bakery that has my test message twice (the first send went through before the crash fix).

## Next tasks (Phase 5: Seller Center)

- Dashboard with sales analytics.
- Products: create and edit with image uploads, variations and inventory (through `InventoryService`), the status tabs, and submitting for review.
- Orders: tabs, plus process → ready for pickup (creates the shipment) and cancel, all through `OrderStatusService`.
- Shop profile: logo, banner, decoration and templates, vacation mode.
- Customer service: chat inbox, greetings and auto-replies, review replies.
- Finance (password-confirmed): balance, income statement, bank accounts, withdrawal requests through `WithdrawalService`.
- Marketing: vouchers, discounts and promotions. Applying vouchers at checkout comes with it.
