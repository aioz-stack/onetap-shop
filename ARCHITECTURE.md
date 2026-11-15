# Onetap Marketplace — Architecture

Status: **Approved 2026-11-15. The Phase 2 foundation is implemented.** The app lives at the repo root (moved from `frontend/`).

## 1. Findings: Baseplate React starter

Source: https://gitlab.com/czar.gaba/baseplate-react (2 commits, cloned to `frontend/`).

"Baseplate React" is **not a standalone React SPA**. It is a full **Laravel 13 monolith** that renders
React pages through **Inertia.js**. Backend and frontend live in one codebase.

| Area | What the starter ships |
| --- | --- |
| Backend | PHP 8.3+, Laravel 13, inertiajs/inertia-laravel 3 |
| Frontend | React 19, @inertiajs/react 3, Vite 7, Tailwind CSS v4 (`@theme` tokens in `resources/css/style.css`) |
| UI kit | TailAdmin-derived dashboard styling: Sidebar, Header, Breadcrumb, MetricGroup, RecentOrdersTable, FlashMessage, Preloader, Overlay, Icons, ApexChart, WorldMap, UserForm |
| Extra libs | ApexCharts, FullCalendar, jsvectormap, flatpickr, dropzone, Alpine.js (leftover from the HTML template) |
| Routing | `routes/web.php`: guest login at `/`, auth group with `/dashboard`, `Route::resource('crud')`, `/logout`, Inertia 404 fallback |
| Auth | Session auth, login by **username** + password, session regeneration, CSRF via Laravel `web` middleware. No registration, no password reset, no API auth |
| Authorization | Only a free-text `users.role` string column (default `user`). No middleware, policies or gates |
| Layouts | `AuthenticatedLayout` (sidebar + header), `GuestLayout` |
| Theme | `ThemeContext` dark-mode toggle persisted in localStorage |
| Shared props | `HandleInertiaRequests` shares `auth.user` and `flash.success` |
| DB | Default Laravel migrations (users, sessions, cache, jobs). Session, cache and queue drivers all use the database |
| Tests | Pest 4; `tests/Feature/AuthAndCrudTest.php` covers login + user CRUD. **phpunit.xml uses in-memory SQLite**, so tests never touch the `onetap-shop` database |
| Tooling | Laravel Boost (MCP + guidelines), Pint, Pail, Sail |

Starter conventions to keep: invokable-style controller names (`Dashboard`, `CRUD`, `Auth\Login`),
`casts()` method on models, Pages resolved by `Inertia::render('Folder/Page')`, Pest feature tests,
Pint formatting.

### Local environment (verified 2026-11-15)

- PHP 8.5.12 (pdo_mysql, gd, intl, zip, fileinfo present), Composer 2.11, Node 26, npm 11
- MariaDB 10.11.16 (XAMPP) at 127.0.0.1:3306, user `root`, empty password — connection OK
- Database `onetap-shop` **already exists and is empty** (0 tables)

## 2. Decision: keep Laravel + Inertia + React

We keep the starter's architecture instead of splitting into a separate SPA + API server.

- **Web app (all 5 panels):** Inertia pages, session auth, CSRF — exactly how the starter works.
- **Mobile-ready API:** a parallel `/api/v1/*` route file using **Laravel Sanctum** tokens and
  JSON API Resources. Requires adding `laravel/sanctum` (dependency change — needs approval).
- **Single source of business logic:** both Inertia controllers and API controllers call the same
  service classes (`app/Services/...`). Controllers stay thin. No logic is duplicated between web
  and API.

```
Browser (React/Inertia)            Future mobile apps
        │  session + CSRF                 │  Bearer token (Sanctum)
        ▼                                 ▼
 routes/web.php                     routes/api.php  (/api/v1)
 Controllers/{Admin,Customer,       Controllers/Api/V1/*
  Seller,Logistics,Rider}            + JsonResource classes
        └───────────────┬─────────────────┘
                        ▼
        Form Requests (validation) + Policies (authorization)
                        ▼
           app/Services/* (domain services, DB transactions)
                        ▼
              Eloquent models → MySQL `onetap-shop`
```

## 3. Modular monolith — domains

| Domain | Responsibility | Key services |
| --- | --- | --- |
| Identity | users, roles, permissions, profiles, addresses, verification | `AuthService`, `RoleService` |
| Catalog | shops, categories, products, variations, images, reviews, moderation | `ProductService`, `ProductModerationService` |
| Ordering | cart, checkout, orders → seller orders, status machine, cancellations, refunds | `CartService`, `CheckoutService`, `OrderStatusService` |
| Inventory | stock reservation/deduction/restock ledger | `InventoryService` |
| Logistics | partners, service areas, shipments, rider assignments, proofs | `ShipmentService`, `DispatchService` |
| Finance | commissions, ledger, seller balances, withdrawals, COD collections, remittances | `LedgerService`, `SettlementService`, `CodService` |
| Marketing | vouchers, discounts, promotions, referrals | `VoucherService` |
| Communication | conversations, messages, notifications, auto-replies | `ChatService`, Laravel Notifications |
| Administration | settings, audit logs, reports | `SettingsService`, `AuditLogger` |

Proposed folders (new base folders need approval per the starter's guidelines):

```
app/
  Enums/                 OrderStatus, ShipmentStatus, ProductStatus, RoleName, ...
  Http/Controllers/
    Admin/ Customer/ Seller/ Logistics/ Rider/ Auth/  (Inertia)
    Api/V1/                                            (JSON)
  Http/Middleware/       EnsureRole, EnsurePasswordConfirmed (built-in), ...
  Http/Requests/         Form Requests per action
  Http/Resources/        API Resources
  Models/
  Policies/
  Services/              per domain (see table)
resources/js/
  Layouts/  AdminLayout, CustomerLayout, SellerLayout, LogisticsLayout, RiderLayout, GuestLayout
  Pages/    Admin/ Customer/ Seller/ Logistics/ Rider/ Auth/ Errors/
  Components/ui/         DataTable, Pagination, Modal/ConfirmDialog, EmptyState, StatCard, Badge, Tabs, FormField, ImageUploader, Toast
```

The existing `AuthenticatedLayout`, `Sidebar` and `Header` become the base for the four back-office
layouts (Admin, Seller, Logistics, Rider), each with its own nav config. The Customer layout is a new
storefront layout (top header + search + category nav + footer). Rider layout is mobile-first
(bottom tab bar).

### Implemented services (Phase 3)

| Service | Rule it owns |
| --- | --- |
| `Ordering\OrderStatusService` | Every seller-order status change. Transition map in `SellerOrderStatus::allowedTransitions()`; writes history, timestamps, audit and stock effects |
| `Ordering\InventoryService` | Stock: `stock` = on hand, `reserved` = held by unshipped orders. Locks the row and writes `product_inventory` for every move |
| `Ordering\RefundService` | Refund approve (optional restock, earnings clawback) and reject (restore the previous status) |
| `Finance\LedgerService` | Append-only `transactions` and seller balance arithmetic (bcmath; balances never go negative) |
| `Finance\WithdrawalService` | Payout lifecycle: request holds funds, approve, paid writes the ledger, reject refunds the hold |
| `Finance\FinanceReportService` | Revenue recognised at seller-order completion |
| `Catalog\SellerApprovalService`, `Catalog\ProductModerationService` | Seller and product standing |
| `Logistics\ShipmentStatusService` | Every shipment status change: delivery-flow map in `ShipmentStatus::allowedTransitions()`, history with GPS, seller-order sync (picked up → Shipping, delivered → Delivered, returned → Returned) |
| `Logistics\DispatchService` / `RiderService` / `CoverageService` | Rider assignment and reassignment, admin routing to a partner, rider lifecycle and performance, service areas |
| `Finance\RemittanceService` | Rider COD remittances: submit, then confirm (ledger) or dispute |
| `Logistics\RiderShiftService` / `DeliveryService` | Rider shifts and availability; the rider-side delivery flow (own jobs only), proof of delivery on the private disk, COD collection and marking orders paid, daily rider earnings |
| `Communication\Notifier` | Every cross-panel alert (who hears about what), sent after commit; admin toggles, team email and optional user email |
| `Identity\UserAccountService` | Staff accounts, role sync, suspension (revokes tokens and sessions), verification review |
| `Administration\SettingsSchema` / `SettingsService` | Typed, validated, cached system settings |

## 4. Authentication and authorization

- Login by **email or username**; registration for customers; seller onboarding = customer applies
  to open a shop (seller_profile + shop in `pending` until admin approves).
- Riders are created by a logistics partner admin (and approved there). Logistics partner accounts
  are created by marketplace admins.
- RBAC tables `roles`, `permissions`, `user_roles`, `role_permissions`. Roles: `admin`, `customer`,
  `seller`, `logistics`, `rider`. A user may hold several roles (a seller is also a customer).
- `role:<name>` middleware guards each panel's route group; Policies guard record ownership
  (seller sees only own shop's orders; rider sees only own assignments).
- `password.confirm` middleware (Laravel built-in) on seller finance/bank/account settings.
- Account status (`active`, `suspended`, `pending`) checked at login and on every request.
- Rate limiting on login, register, API (`throttle`), and checkout.
- Uploads: validated mime + size; public disk for product/shop images; **private (local) disk** for
  verification documents and delivery proofs, served via authorized controller routes.

## 5. Order, shipment and money flow

```
Customer checkout (1 cart, N shops)
  └─ orders (1 per checkout: customer, address snapshot, payment method, grand total)
       └─ seller_orders (1 per shop: items, shipping fee, commission, status)
            ├─ order_items
            └─ shipments (1 per seller_order) → delivery_assignments → rider
```

Seller order statuses (what admin/seller/customer tabs filter on):
`unpaid → to_ship (pending → processing → ready_for_pickup) → shipping → delivered → completed`,
plus `cancelled`, `return_requested`, `returned`, `refunded`.
For COD, orders skip "unpaid" and start at `to_ship`.

Shipment statuses (rider flow): `pending_pickup, assigned, accepted, arrived_at_pickup, picked_up,
in_transit, out_for_delivery, delivered, failed_delivery, customer_unavailable, rescheduled,
returned_to_seller`. Shipment transitions push the seller order status
(picked_up → `shipping`, delivered → `delivered`). Customer confirms (or auto after N days) → `completed`.

Every transition is done by a service inside a DB transaction and writes a row to
`order_status_histories` / `shipment_status_histories`.

Money (all `DECIMAL(14,2)`, currency PHP):
1. Checkout: stock reserved, `orders` + `seller_orders` created, payment row `pending` (COD).
2. Delivered: the rider submits proof (photo on the private disk, recipient, GPS) and the cash collected → `delivery_proofs` + `rider_collections` (a mismatch is flagged as a discrepancy). When the cash covers the seller order it gets `paid_at`; once every active seller order is paid, the order and its payment row become `paid`.
3. Rider remits daily → `rider_remittances` confirmed by logistics; discrepancies flagged.
   Vouchers: a shop voucher is funded by the seller (commission on the discounted items, discount taken from the earning). A platform voucher is funded by Onetap (seller paid in full; a `platform_voucher` debit is booked at completion; net revenue = commission − platform vouchers).
4. Completed: ledger `transactions` posted — seller earning (subtotal − commission − voucher share),
   admin commission, logistics delivery fee. Seller `pending_balance → available_balance` after the
   hold period (setting).
5. Seller withdrawals draw from available balance → admin approves/pays.

## 6. Payments (future-ready)

`payments` stores `method` (`cod` now; `gcash`, `maya`, `card` later), `provider`, `provider_ref`,
`status`. A `PaymentGateway` interface with a `CodGateway` implementation lets online gateways be
added later without changing checkout.

## 7. Non-goals for now

No microservices, no mobile apps, no real-time websockets (chat uses polling first; Reverb can be
added later), no third-party payment integration yet.
