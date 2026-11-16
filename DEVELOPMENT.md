# Onetap Marketplace — Development Log

**Current phase:** Phase 9 (Testing and Finalization) is done: automated testing, the permission and security passes, fixes, and the browser responsive sweep on MySQL. Open: the timezone decision (yours) and the production deploy settings listed under Known issues.

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

## Phase 5 — Seller Center — done (2026-11-16)

**Access**
- `/seller/*` requires the seller role and an approved seller profile with a shop (`seller.shop` middleware). The shop is resolved on the server, so controllers never trust a shop id from the request, and every record is checked against it (404 otherwise).
- Finance pages sit behind Laravel's `password.confirm` (the new `/confirm-password` page). The confirmation lasts 3 hours (`auth.password_timeout`).

**Dashboard:** total sales (completed), orders, pending to ship, products sold, products live, balance (available plus on hold), a 30-day sales chart, the next orders to ship, and low-stock variations. Sidebar badges show orders to ship and unread chats.

**Products** (`SellerProductService`)
- Status tabs and search. Create and edit with up to 8 photos (JPG/PNG/WebP, 4 MB, 200–5000 px) on the public disk, with a cover photo and removal.
- Up to 30 variations, each with a price, an optional original price (must be ≥ price) and stock.
- **Review rule:** new products go to draft or "submit for review". On a live product, edits to name, description, category, brand or photos send it back to review; price, stock and variation changes stay live.
- **Stock changes** go through `InventoryService` (`restock` and `adjust`, ledgered) and can never drop below what open orders have reserved.
- **Removing a variation** that was ever sold (or is reserved) deactivates it instead of deleting it.
- **Status actions:** submit (draft or rejected → review), unpublish, republish (back to live if it was approved before, otherwise to review), and delete (soft delete; past orders keep their snapshot). Products with an open violation are locked.
- **Inventory page:** every variation with on hand, reserved and available, a low-stock filter, an "update stock" dialog with a note, and recent stock movements.

**Orders** (`SellerOrderService`)
- Tabs and search. Detail page with items to pack, totals (voucher, delivery, commission, "you earn"), address and customer note, shipment, return requests and history.
- Accept: Pending → Processing.
- **Ready for pickup:** Processing → Ready, and `ShipmentService` creates the shipment in the same transaction. It gets a tracking number, the pickup and drop-off addresses, the COD amount (the seller-order total) and the delivery fee. It's routed to the cheapest partner covering the customer's barangay or city; without coverage it waits for assignment, and that's noted in the shipment history.
- Cancel (Unpaid, Pending or Processing; reason required). Stock is released and the customer is notified.

**Shop settings**
- Profile: description, category, pickup address and phone.
- Branding: logo (≥100 px) and banner (≥800×200) uploads.
- Decoration: template (classic, spotlight or minimal), accent color, and an announcement bar. These now drive the public shop page.
- Vacation mode with a message. It blocks add-to-cart and checkout, and turns on the away auto-reply.

**Customer service**
- Shop chat inbox: replies, unread markers, 5-second polling.
- **Automated replies:** one greeting (first message), one away message (sent while on vacation), and any number of keyword replies (case-insensitive "contains").
- **Reviews:** filter by star rating or "not replied yet", and reply; replies are public on the product page.

**Finance** (password-confirmed)
- **Balance:** available, on hold, income this month and last month, and earnings still in progress.
- **Withdrawals:** requested through `WithdrawalService`, so the minimum and balance checks apply and the amount is held.
- **Payout accounts:** up to 5 banks or e-wallets. The number is stored with an encrypted cast and only the last 4 digits are shown. Accounts with payout history can't be deleted.
- **Income statement:** monthly, with gross sales, vouchers, commission, net income and refunds, plus per-order income details showing available or on hold.
- **Transaction history.**

**Marketing**
- **Vouchers:** shop codes (unique, alphanumeric, stored uppercase).
  - Types: fixed, percent with an optional cap, or free shipping with an optional cap.
  - Rules: minimum spend, total and per-customer limits, a schedule, an on/off switch.
  - Each voucher shows how many times it was used and the discount given. A voucher that was used is switched off instead of deleted.
- **Promotions:** a percent markdown on chosen products for a period. `PricingService` applies the best running promotion everywhere a price appears: product cards (with a "−20%" badge), the product page (struck-through price and promo name), the cart, checkout and the API.
- **Referrals:** the owner's personal code and link (`/register?ref=CODE` now prefills the sign-up form) and the list of customers who joined with it.

**Checkout changes** (`CheckoutService`, `CartService`, `VoucherService`)
- Unit prices include promotions. Customers can enter one shop voucher per shop at checkout; it's validated live and the error is shown under that shop.
- **Commission** is charged on (items − item discount); free-shipping vouchers don't reduce it.
- **Seller earning** = items − commission − voucher discount. The shop funds its own vouchers, and the logistics fee is untouched.
- **Redemption** is recorded, and `used_count` is incremented under a row lock inside the checkout transaction.
- **New migration** `seller_orders.voucher_id`, already applied to MySQL. The web and API checkout both accept `vouchers[shop_id]=CODE`.

## Phase 5 testing results (2026-11-16)

- `php artisan test`: **213 passed, 2106 assertions** (43 new, plus 1 new Phase 2 test that a seller role without an approved shop gets a 403). They cover:
  - every seller page rendering;
  - product creation with photos, variations and the stock ledger, draft vs submit, validation of photos, prices and duplicate variations, the live-edit review rule, the reserved-stock floor, deactivating sold variations, the status workflow and soft delete, the violation lock, and cross-shop 404s;
  - accept → ready creating a routed COD shipment and notifying the customer, unassigned shipments, cancel rules and stock release, order isolation, dashboard figures;
  - the finance password gate, encrypted bank numbers (the raw column is ciphertext), withdrawal limits and holds, statement and transactions, account-removal guards;
  - voucher creation and uniqueness, promotion pricing across product, search, cart and checkout, cross-shop and expired promotions, fixed, percent-with-cap and free-shipping voucher math including commission and seller earning, voucher rules (minimum spend, other shop, scheduled, exhausted, unknown, reused), and API vouchers;
  - keyword and away auto-replies, the seller inbox and its isolation, review replies, shop profile, branding, template and vacation (which blocks the cart), and referral sign-up tracking.
- Browser check on MySQL as the demo seller (Santos Home Bakery):
  - The dashboard showed the pending bakery order with badges (orders to ship 1, chat 2).
  - Accepted and then marked `OT261115I73DWN-1` ready: tracking `OTX261115E9ALWC4` was created, routed to Onetap Express, with ₱419 COD and the earning shown as ₱342 after ₱18 commission.
  - Created "Cheese Pandesal (10 pcs)" through the form with a real PNG upload (generated in the page); it went to Under Review, with the file on disk and a restock ledger row in MySQL.
  - Created the "Ube Week" 20% promotion on Ube Ensaymada; the storefront API then showed ₱52 (was ₱65) and ₱288 (was ₱360).
  - Created voucher `BAKERY30` (₱30 off, ₱200 minimum).
  - Finance redirected to `/confirm-password`, and after confirming showed ₱342 in progress with withdrawal disabled (no available balance yet).
  - Replied to the customer from the seller chat inbox; the unread badge cleared.
  - All 18 seller pages and the public shop page render and fit at 375px.
  - I did not type a bank account number in the browser (the automated tests cover payout accounts).

## Phase 6 — Logistics Panel — done (2026-11-16)

**Access:** `/logistics/*` requires the logistics role and the operator account of an **active** partner (`logistics.partner` middleware). The partner is resolved on the server, and every shipment, rider, area and remittance is checked against it (404 otherwise).

**Shipment status service** (`ShipmentStatusService`, the delivery-flow counterpart of `OrderStatusService`)
- **Transition map** in `ShipmentStatus::allowedTransitions()`:
  - Pending pickup → Assigned → Accepted → Arrived at pickup → Picked up → In transit → Out for delivery → Delivered.
  - Out for delivery → Failed delivery or Customer unavailable → Rescheduled (→ Out for delivery again) or Returned to seller.
  - Assigned, Accepted or Arrived can go back to Pending pickup (unassign or reassign).
- **On every change** it writes history (with optional GPS), counts failed attempts, stamps picked-up and delivered times, closes or cancels rider jobs, and audits.
- **Seller-order sync:**
  - Picked up → Shipping (the reservation becomes a sale).
  - Delivered → Delivered (the customer can now confirm received).
  - Returned to seller → Returned (items restocked).
- **Cancelled before pickup:** if a packed order is cancelled, `OrderStatusService` closes the waiting shipment and cancels its rider job.

**Dispatch** (`DispatchService`)
- Assign or reassign to an **active rider of the same partner**. Reassigning cancels the previous job.
- A rescheduled parcel gets a "delivery" job, with no new pickup.
- The rider is notified (`DeliveryAssigned`, in the database). The rider picker lists online riders with the fewest open jobs first.
- Unassign before pickup, with a reason.
- **Admin:** "Route to partner" for shipments no partner covers. There's a banner and a "No partner yet" filter on Admin → Shipments; rerouting is only allowed before a rider has the job.

**Logistics Panel**
- **Dashboard:** total shipments, pending pickups (and how many have no rider), in transit, out for delivery, delivered (and today), failed, active riders, pending approvals, COD collected today and still with riders, remittances to review, and a "waiting for a rider" list.
- **Shipments:**
  - Tabs: pickup requests, assigned, in transit, out for delivery, delivered, failed, returned. Search, plus a filter by rider.
  - Detail page: pick-up and drop-off, the parcel, COD and fee, proof of delivery and COD status, rider jobs, tracking history.
  - Actions: assign or reassign, remove rider, reschedule (with a note and optional date), return to seller.
- **Riders** (`RiderService`):
  - Add a rider (creates a rider login under the partner; pending unless "documents checked — activate now"), edit, approve, suspend (goes offline, and uncollected pickups go back to the queue), reactivate.
  - List: availability, open jobs, completed jobs, cash on hand.
  - Rider page: delivered count, failed attempts, success rate, average pickup-to-delivery time, cash collected and unremitted, recent jobs.
- **Service coverage:** the partner manages its own areas and fees, through the shared `CoverageService`. Admin coverage now uses it too, and duplicate detection is case-insensitive.
- **Finance:**
  - Daily COD per day (pick a date): totals, a per-rider breakdown with outstanding cash, and each collection (mismatches highlighted).
  - Rider remittances (`RemittanceService`): the rider submits all unremitted collections up to that day (`submit()`; the Rider app calls it in Phase 7). The operator counts the cash: a match confirms it, marks the collections remitted and writes a `remittance` ledger entry; a mismatch saves it as disputed (collections flagged) and it can be recounted.
  - Earnings and reports: delivery fees on delivered parcels (all time, this month, still in progress) and a 12-month report (delivered, fees, COD, failed attempts, returned) with CSV download.
- **Sidebar badges:** shipments waiting for a rider, riders waiting for approval, remittances to review.

## Phase 6 testing results (2026-11-16)

- `php artisan test`: **241 passed, 2355 assertions** (26 new, plus 1 new Phase 2 test for a logistics role without an active partner, and a fix for a flaky user-search test that matched Faker names). They cover:
  - every logistics page rendering;
  - assignment with notification, reassignment cancelling the old job, same-partner/active-only riders, partner isolation;
  - pickup turning the reservation into a sale and setting the order to Shipping, delivery setting it to Delivered, skipped steps rejected;
  - customer-unavailable → reschedule (with date) → redelivery job → failed → return to seller (order Returned, stock restored, jobs closed);
  - unassign; a packed order cancelled before pickup closing its shipment and job; admin routing an uncovered shipment;
  - adding a rider who can log in to `/rider` after approval, editing riders and isolation, suspension requeuing pickups, rider performance;
  - coverage CRUD with case-insensitive duplicates and isolation;
  - remittance submit and confirm writing the ledger, a short count saved as disputed and then a recount;
  - daily COD, earnings and CSV, dashboard figures.
- Browser check on MySQL as the demo operator (Onetap Express):
  - The dashboard showed the bakery shipment `OTX261115E9ALWC4` waiting, with a sidebar badge of 1.
  - Assigned it to Pedro Reyes from the shipment page: the status became Assigned, a pickup job was created, and the tracking history was written.
  - MySQL has the rider's notification ("Pick up OTX261115E9ALWC4 at Santos Home Bakery. Collect ₱419.00 on delivery."). The seller order correctly stays Ready for pickup until the rider collects it.
  - All 12 logistics pages render and fit at 375px with no client errors.
  - I didn't add a rider in the browser, to avoid more test logins in your database; the automated tests cover it.

## Phase 7 — Rider Panel — done (2026-11-16)

- **Migration** `2026_11_16_120000_create_rider_shifts_and_rider_pay` (incremental, nothing dropped):
  - new `rider_shifts` table (shift log);
  - `logistics_partners.rider_fee_per_delivery`, the partner's pay per delivered parcel. Admins set it on the partner form and operators set it on the Coverage page.
- **`RiderShiftService`:**
  - Sign in starts a shift and makes the rider available; signing in twice keeps one open shift.
  - Sign out ends the shift and sets the rider offline.
  - Available / unavailable can only be toggled while on shift.
  - Riders must be active and on shift to update deliveries.
- **`DeliveryService`** (all status changes go through `ShipmentStatusService`):
  - One `update()` entry point for: decline (assigned jobs only; needs a reason; the parcel goes back to the dispatcher's queue and the job is marked declined), accept, arrived at pickup, picked up, in transit, out for delivery, failed delivery / customer unavailable (need a note; attempts counted), try again later (rescheduled → out for delivery) and return to shop (needs a reason).
  - Riders cannot skip steps or mark a parcel delivered without proof.
  - `complete()`: in one transaction it stores the proof photo on the private `local` disk, writes `delivery_proofs` (recipient, relation, GPS, timestamp) and `rider_collections`, then marks the parcel Delivered. Collected ≠ due is saved as a `discrepancy`.
  - When the cash covers a seller order it gets `paid_at`. Once every active seller order of the order is paid, the order's `payment_status` and its pending `payments` rows become paid. Short cash leaves the order unpaid.
  - Daily earnings = parcels delivered that day × the partner's rate.
- **Isolation:** riders only see and act on their own assignments. Anyone else's parcel returns 404; a reassigned rider can still view their old job but no longer act on it.
- **Rider app** (mobile-first; `RiderLayout` bottom tabs with badges for new jobs and cash in hand):
  - **Home:** shift controls; assigned, pending pickups, to deliver, delivered today, COD today (plus amount still to remit), earned today; failed-attempt alert; up-next jobs.
  - **Deliveries:**
    - Tabs New / To pick up / To deliver / Issues / History, with tracking-number search.
    - The job page has a progress bar, cash to collect, pickup and drop-off cards (call links, map directions), items, and tracking history.
    - One primary next-step button, with secondary actions in a modal that asks for a note.
    - The proof form has a camera capture (`capture="environment"`) with preview, recipient and relation, cash collected (warns on a mismatch), a note, and optional GPS. A hard timeout keeps the browser's location prompt from blocking updates.
  - **COD:** collected today, cash in hand, awaiting confirmation, discrepancies; remit form (`RemittanceService::submit`), collection list, 7-day earnings, remittance history.
  - **Profile:** partner, vehicle and status, pay rate, performance, contact number, password change, recent shifts.
- **Proof photos** are streamed from private storage through authorized routes for the rider, the owning partner (`/logistics/shipments/{id}/proof-photo`, also shown on the shipment page with a GPS map link) and admins (`/admin/logistics/shipments/{id}/proof-photo`, linked from the admin order page).
- **Rider API** (Sanctum, rider role): dashboard, availability, assignments (open / history), delivery detail, status, proof (multipart), proof photo, COD summary, remittances. See API_DOCUMENTATION.md.
- `StatCard` gained a `compact` mode so money values fit two-up on phones.

## Phase 7 testing results (2026-11-16)

- `php artisan test`: **271 passed, 2713 assertions** (30 new in `tests/Feature/Phase7`). They cover:
  - every rider page rendering, and other roles blocked;
  - shift sign in/out/availability, and inactive riders blocked;
  - updates refused off shift;
  - the full flow writing proof (file on the private disk), collection, seller order Delivered and paid, order and payment paid, job completed, dashboard earnings ₱35;
  - proof validation (photo required and must be an image, recipient required, out-for-delivery only);
  - short cash flagged with the order left unpaid;
  - skipped steps rejected;
  - decline (reason required, back to queue; not after accepting);
  - failed → try again later → delivered; failed → returned to shop (order Returned, no earnings);
  - rider isolation and reassigned riders;
  - proof photo access for rider/partner/admin, other partners and customers blocked;
  - remit → partner confirm;
  - rider pay set by the partner and the admin;
  - profile phone and password;
  - the API: the full delivery over tokens, isolation, role checks, validation.
- Browser check on MySQL, end to end with the demo accounts:
  1. As rider Pedro Reyes at 375px: signed in to a shift, then took `OTX261115E9ALWC4` through accept → arrived → picked up → in transit → out for delivery.
  2. Delivered it with a generated test photo, recipient Juan Dela Cruz (Self) and ₱419.00 collected. The browser pane shares no location, so it saved with "No GPS", as designed.
  3. MySQL then showed: shipment delivered; collection ₱419.00/₱419.00 collected; seller order delivered with `paid_at`; order and payment paid; job completed; photo in `storage/app/private/delivery-proofs/1/`. The photo loads on the rider job page and the logistics shipment page.
  4. The rider remitted ₱419.00 from the COD tab; Onetap Express counted and confirmed it (remittance confirmed, collection remitted, ledger `remittance` ₱419.00).
  5. As customer Juan: "Order received" completed the order, posting a seller earning of ₱342.00 (pending balance) and commission ₱18.00.
  - All six rider pages fit at 375px with no client errors.
- The full Customer → Seller → Logistics → Rider → COD → Finance workflow now runs on real MySQL records.

## Phase 8 — System Integration — done (2026-11-16)

- **Notifications across every panel** (`Communication\Notifier` + one generic `Alert` notification with a `type`, title, body and in-app link):
  - Services only say what happened; the notifier sends after the transaction commits, and a mail failure never breaks the business action.
  - **Admin team** (each switchable in Settings → Notifications; also emailed to the team address when one is set): new seller applications, refund requests, withdrawal requests, and shipments no partner covers (new toggle).
  - **Sellers:** application approved/rejected, product approved/rejected/violation/unpublished, refund requested, order cancelled by the customer, order completed (with the earning), withdrawal approved/paid/rejected.
  - **Customers:** rider on the way (with the cash to prepare), "we missed you" after a failed attempt, refund approved/declined. The old generic "order refunded" message was removed so customers get one refund alert with the amount.
  - **Logistics partners:** new pickup request (covered address or admin routing), rider declined a job, failed delivery attempt, remittance to count.
  - **Riders:** assignment link now opens the job, job removed (unassign or reassignment), remittance confirmed/disputed.
  - Suspended accounts get nothing. New setting **"Also email users"** (off by default) mails users their alerts too; mail uses the `.env` mailer (`log` locally).
- **Inbox for every role:**
  - A header bell with an unread badge and the latest 10 alerts for the admin, seller and logistics panels and the rider app.
  - Opening an alert marks it read and only follows links inside the app; "Mark all read" clears the rest.
  - Routes: `/notifications/feed`, `/notifications/{id}/open`, `/notifications/read-all`. API: `/api/v1/notifications`.
- **API completed for the other panels** (same services and validation as the web panels; isolation by shop/partner):
  - Seller: dashboard, products (list, show, create and update with photos, submit/unpublish/republish, delete), stock, orders (list by tab, detail, accept/ready/cancel).
  - Logistics: dashboard, shipments (list, detail, assign, unassign), riders (list, detail, add, edit, approve/suspend).
  - Customer: refund requests, refund list, item reviews.
  - The API now has 60 routes; see API_DOCUMENTATION.md.
- **Platform vouchers** (admin → Products → Platform Vouchers, sharing one voucher manager with the Seller Center):
  - Valid in any shop's voucher box, once per order (typing the same code for two shops is refused).
  - **Onetap funds them:** the seller is paid as if there were no voucher and commission is charged on the full item total. On completion a `platform_voucher` debit is written to the ledger, and Finance shows **net revenue = commission − platform vouchers** (overview card, monthly report and CSV).
  - Shop vouchers keep their rule: the shop funds them.
- **Promotion-aware price sorting and filters:** price sort and the min/max price filter use the "from" price after the best running promotion, in SQL (works on MySQL and SQLite), so they match the prices on the cards.
- Small fixes: the checkout voucher box now says "Voucher code (shop or Onetap)"; a timing-sensitive login-throttle test (60 vs 59 seconds) no longer flakes.

## Phase 8 testing results (2026-11-16)

- `php artisan test`: **291 passed, 2940 assertions** (20 new in `tests/Feature/Phase8`). They cover:
  - every notification route above, with the admin toggles, the team email and "email users";
  - suspended users skipped;
  - the bell feed, open (read + safe redirect, other users' alerts 404), read all, and the shared `counts.notifications`;
  - the notifications API;
  - seller product CRUD with photo upload, stock, and order processing through to a shipment;
  - logistics dispatch and rider management with partner isolation;
  - customer refunds and reviews;
  - a parcel driven by partner and rider tokens keeping seller-order status in step;
  - platform voucher admin CRUD and scoping, funding math (₱400 order, ₱50 voucher: commission ₱20, seller ₱380), once-per-order, the ledger entry and net revenue;
  - promotion-aware sort and filters, including an expired promotion.
- Browser check on MySQL:
  1. As admin, created platform voucher `ONETAP20` (₱20 off) from the new screen.
  2. As customer Juan, bought a Buko Pie (₱350) with it: paid ₱389.00. MySQL shows commission ₱17.50 (5% of the full ₱350) and seller earning ₱332.50, so the seller wasn't charged for the voucher.
  3. As seller Maria, the bell showed "2 unread"; opening the new-order alert went to the order and marked it read. Accepted it and marked it ready for pickup.
  4. As Onetap Express, the bell showed "New pickup request" for `OTX261115770UXWB`. The dropdown fits at 375px with no overflow.
## Phase 9 — Testing and Finalization — done (2026-11-16)

**Done**
- **Permission sweep** (`tests/Feature/Phase9/PermissionSweepTest.php`). It reads the live route table, so new routes are covered automatically:
  - Every non-public route requires sign-in and an active account.
  - Every panel and API area carries its role guard (plus the shop/partner check for sellers and logistics).
  - Every web route runs the CSRF-protected `web` group and every API route is rate limited.
  - Every parameter-free page (66) opens for its own role and returns 403 for the other four roles; guests go to login.
  - Every API list endpoint returns 200 / 403 / 401 correctly.
  - Suspended accounts, suspended partners and pending shops are locked out.
  - Result: no permission holes found.
- **End-to-end workflow** (`EndToEndWorkflowTest.php`, agents.md §16), driven through the real web routes of every panel:
  - A customer orders 4 items (₱1,059 COD), the seller accepts and packs, Onetap Express assigns the rider, and the rider delivers with proof and cash.
  - The customer confirms receipt; the rider remits and the partner confirms.
  - After the 3-day hold the ₱950 earning becomes available. A ₱400 withdrawal is refused (₱500 minimum); ₱500 is approved and paid, leaving ₱450.
  - Checked at every step: stock (10 → 6, nothing left reserved), each panel's view of the order, and ledger totals (earnings ₱950, commission ₱50, remittance ₱1,059, withdrawal ₱500).
  - Also covered: a refund after completion (restock + earning clawback + refund ledger entry), and an admin cancelling a packed, assigned order (stock released, rider job closed; the shop itself is correctly refused).
- **Security pass** (`SecurityTest.php`):
  - Dependency audits: `composer audit` and `npm audit` report no known vulnerabilities.
  - New `SecurityHeaders` middleware on every response: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, a `Permissions-Policy` allowing camera and location for this site only, and HSTS over HTTPS.
  - Private identity documents and proof photos are not served by `/storage/{path}` without a signed link.
  - Uploads reject PHP files disguised as documents or images.
  - Registration cannot grant itself a role.
  - Search and price filters survive SQL-injection style input; HTML in product names is escaped.
  - Bank account numbers are encrypted at rest and never sent to the browser (last 4 digits only); password hashes never appear in pages or the API.
  - Customers cannot see or act on other customers' orders.
  - Code scan: no raw SQL built from user input, no `dangerouslySetInnerHTML`, no models open to mass assignment, and every upload validates type and size.
- **Fixes from this phase:**
  - **Bug:** the admin order page crashed (500) for any delivered order with a proof photo, because it used the wrong route name (`admin.shipments.proof-photo` instead of `admin.logistics.shipments.proof-photo`). Introduced in Phase 7; the end-to-end test caught it.
  - **Starter user screen (`/crud`) hardened:** it now uses the same `UserAccountService` as Admin → Users. Changes are audited, the 8-character letters-and-numbers password rule applies, roles are replaced instead of piling up, an admin cannot demote themself, and administrators (including yourself) cannot be deleted there. The screen itself is kept, as agents.md asks to preserve the template's features.
  - Rate limits added to refund requests (10/min), reviews (20/min), starting a chat (20/min), identity uploads (10/min) and seller applications (5/min).
  - Promotion edits and deletions are now audited (creation already was).
  - **Customer order history now matches the tracking status** (reported on `/account/orders/5`): the tracking line showed the shipment's live status ("Out for delivery") while History listed only order events, so nothing appeared between "Shipping" and "Delivered". `App\Support\OrderTimeline` now merges the delivery steps into the history (rider assigned, rider at the shop, in transit, out for delivery, failed attempts with the rider's note, redelivery), without repeating events already listed and without internal logistics steps. The mobile API returns the same history. 3 tests in `OrderTimelineTest.php`.
  - Test reliability: the end-to-end test stamps password confirmation with the test clock.

- **Browser responsive sweep on MySQL** (done after MySQL was restarted; it had been stopped, which made every page fail with a refused database connection — an environment issue, not an app bug):
  - Every page of every role — 76 pages: guest 6, customer 15, seller 16 (finance pages after the password confirmation), logistics 10, rider 5, admin 24 including the starter `/crud` screen — loaded at **375, 768 and 1280 px** (228 loads), signed in as the demo account for each role.
  - Each load checked: the Inertia page component is a real page (not an error page), the URL wasn't redirected (for example to login or the confirm-password screen), and nothing is wider than the viewport.
  - Result: **all 228 loads clean.**
  - The checker was verified first with known failures: a 404 page and a 403 page were flagged, and a planted 900 px element was detected. (A first run read Inertia v3's page data wrongly, so error pages could have slipped through; the overflow and redirect checks were valid. Every role was re-run with the corrected checker.)

**Open**
- The timezone decision (see Known issues). It needs your call.

## Phase 9 testing results (2026-11-16)

- `php artisan test`: **320 passed, 3489 assertions** (29 new in `tests/Feature/Phase9`: 8 permission sweep, 3 end-to-end, 15 security, 3 order history).
- `composer audit`: no advisories. `npm audit`: 0 vulnerabilities.
- `vendor/bin/pint`: clean. `npm run build`: succeeds.
- Browser responsive sweep on MySQL: 76 pages × 3 widths, all clean.

## Known issues / notes

- The migrations reached MySQL before the explicit `php artisan migrate` step: that step reported "Nothing to migrate" (all 15 already in batch 1). Nothing was dropped and the database was empty beforehand.
- **Staff accounts (admin, logistics, rider) cannot shop.** The cart, checkout and My Account require the customer role; sellers have it. Staff see an explanation on product pages.
- Seller shop names can't be changed by the seller (contact support); admin editing of shop names isn't built yet.
- Refund requests are text-only; photo evidence is not collected yet.
- Chat uses 5-second polling, not websockets.
- Suspending a rider does not take back parcels they have already picked up; the operator handles those manually.
- Each logistics partner has a single operator account; there are no sub-users or dispatcher roles yet.
- Storefront product images use `loading="lazy"`, so a browser tab that isn't on screen doesn't draw them (that's why the hidden test pane showed 0 loaded). This is expected behavior.
- **The `general.timezone` setting is stored but not applied.** The app clock is still `config('app.timezone')` = UTC (for example, CSV file names use UTC). Switching the app timezone changes how existing timestamps are read, so it needs a deliberate decision.
- **No Content-Security-Policy header yet.** Inertia's inline page data and the Vite dev server need a nonce-based policy; set one up for production. The other security headers are in place.
- **Production settings to change at deploy:** `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true` (HTTPS), optionally `SESSION_ENCRYPT=true`, a dedicated database user with a strong password (agents.md §3), real mail settings, and running the scheduler (`php artisan schedule:work` or cron) for earnings release.
- Emails are written to the log locally (`MAIL_MAILER=log`). Real delivery needs SMTP or a mail service in `.env`.
- Alerts are delivered when the page loads or the bell opens; there is no live push (websockets) yet, same as chat polling.
- A platform voucher can make net revenue negative on an order (the voucher is larger than the commission). This is intended and shown in Finance.
- The branding color setting is for emails and the future mobile apps; the web theme uses the built-in palette.
- Refund requests, withdrawal requests and verification uploads have admin screens tested with factory data; the dev database only has the records created during the browser checks.
- Demo passwords are weak by design and the seeder is limited to local and testing environments.
- Dev database leftovers from the browser checks:
  - `qa_customer` (Phase 2).
  - Order `OT261115I73DWN`: the bakery seller order went through the whole Phase 7 flow and is Completed (delivered with a generated test photo, ₱419.00 collected, remitted and confirmed, earnings posted); the hardware order is cancelled.
  - Rider Pedro Reyes has an open shift from the browser check (sign out from the rider Home tab).
  - Phase 8: platform voucher `ONETAP20` and order `OT261115DU7JXW` (Buko Pie, ready for pickup as `OTX261115770UXWB`, not yet assigned to a rider).
  - A chat thread with Santos Home Bakery (my test message twice, the greeting, and the seller's reply).
  - "Cheese Pandesal (10 pcs)", under review, from the upload test.
  - The "Ube Week" promotion (20% off Ube Ensaymada, no end date) and the `BAKERY30` voucher.

- **Rider days follow the app clock (UTC).** "Today" for earnings and COD totals rolls over at 8:00 AM Philippine time until the timezone decision above is made.
- Rider GPS is optional and best-effort; when the rider declines location access, deliveries are saved with "No GPS".
- Rider pay is a flat amount per delivered parcel. Pickups, returns and failed attempts are not paid, and there is no payout screen for rider pay yet (it is shown as earnings only).
- A rider can return a parcel to the shop after a failed attempt from the app; the logistics operator can also do it from the shipment page.

## Next tasks

- Decide the timezone question (apply `general.timezone` to the app clock) so "today" totals match Philippine time. This needs your decision.
- Optional, with your approval: clean up the dev test data listed above for a tidy demo database (nothing is deleted automatically).
- Before going live: the production settings above and a Content-Security-Policy.
