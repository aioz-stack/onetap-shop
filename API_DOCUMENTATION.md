# Onetap Marketplace — REST API (v1) for the mobile apps

One API, three apps: **customer**, **seller** and **rider**. Every endpoint below is implemented and covered
by automated tests (`tests/Feature/MobileApi`, plus the earlier phase tests). Admin stays web-only.

- [1. Getting started](#1-getting-started)
- [2. Shared: sign-in, account, inbox, chat, push](#2-shared-every-app)
- [3. Customer app](#3-customer-app)
- [4. Seller app](#4-seller-app)
- [5. Rider app](#5-rider-app)
- [6. Logistics partner (web panel API)](#6-logistics-partner)
- [7. What's not in the API yet](#7-not-in-the-api-yet)

---

## 1. Getting started

**Base URL:** `{APP_URL}/api/v1` (local: `http://localhost:8000/api/v1`). For a phone on the same Wi-Fi, run
`php artisan serve --host=0.0.0.0 --port=8000`, set `APP_URL=http://<your PC's LAN IP>:8000` in `.env` (image
URLs are built from it), and point the app at that address. Use HTTPS in production.

**Headers:** `Accept: application/json` on every request, `Authorization: Bearer <token>` once signed in.
Send files as `multipart/form-data`; everything else as JSON.

### Sign-in flow (every app)

1. `POST /auth/login` with `login` (email or username), `password`, **`app`** (`customer` | `seller` | `rider`)
   and `device_name` (e.g. "Juan's Pixel 8").
   - `200` → `{ data: Profile, token, token_type: "Bearer" }`. Store the token in secure storage (Keychain / Keystore).
   - `403` with `code: "app_not_allowed"` and `apps: [...]` → this account can't use this app (show `message`).
   - `422` → wrong credentials or inactive account (`errors.login`).
2. On launch, `GET /auth/me` to restore the session (`401` → token expired or revoked → back to sign-in).
3. After sign-in, register the phone for push: `POST /devices`.
4. Sign out: `DELETE /devices` (this phone's push token), then `POST /auth/logout`.

Tokens expire after **Admin → Settings → API → token lifetime** (30 days by default). There's no refresh token:
when a request returns `401`, send the user to sign in again.

**Who can use which app** (`Profile.apps` lists them):

| App | Requirement |
| --- | --- |
| customer | customer role (sellers have it too — they can shop) |
| seller | seller role **and** an approved seller profile with a shop |
| rider | rider role and a rider profile (added by a logistics partner) |

### Responses, errors and paging

| Status | Meaning | Body |
| --- | --- | --- |
| 200 / 201 | OK / created | `{ data: … }` (lists add `meta`) |
| 204 | Deleted | empty |
| 401 | Not signed in / token expired or revoked | `{ message }` |
| 403 | Signed in but not allowed (wrong role, inactive shop/partner, suspended account) | `{ message }` |
| 404 | Not found **or not yours** (other customers' orders, other shops' products, other riders' parcels…) | `{ message }` |
| 422 | Validation or business rule | `{ message, errors: { field: [messages] } }` — show `errors` next to the fields |
| 423 | Seller finance needs a fresh password confirmation | `{ message, code: "password_confirmation_required" }` |
| 429 | Too many requests | `{ message }` — back off and retry later |

- **Lists:** `{ data: [...], meta: { current_page, last_page, total, … } }`; ask for more with `?page=2`.
- **Money** is a string with 2 decimals (`"1059.00"`), currency PHP. Don't parse it with floats for arithmetic.
- **Dates** are ISO 8601 with offset (`2026-11-16T07:53:37+00:00`, UTC). Show them in the phone's local time.
- **Images:** every `*_url` is absolute. Private files (proof photos, identity documents) are only served by
  authenticated endpoints — send the Bearer header when loading them.
- **Rate limits:** sign-in and password reset are limited per IP + account; every user has an overall limit
  (Admin → Settings → API, 60/min default); write endpoints have their own limits (noted below).

### Push notifications

`POST /devices` registers the phone (`token` from FCM/APNs, `platform: android|ios`, `app`). Every in-app alert
is also pushed to the matching app: seller links go to the seller app, rider links to the rider app, the rest to
the customer app. The push payload has `title`, `body`, `url` and `data.type`, the same as the notification list.
**Today the server writes pushes to its log** (`PUSH_DRIVER=log`); sending through Firebase needs the Firebase
service-account credentials and one driver class (see DEVELOPMENT.md).

### Opening links (`url`)

Notifications and push payloads carry a web-style path. Map them to screens:

| `url` pattern | Screen |
| --- | --- |
| `/account/orders/{id}` | customer order detail (`GET /orders/{id}`) |
| `/account/orders`, `/account/refunds` | customer orders / refunds |
| `/seller/orders/{id}` | seller order detail |
| `/seller/products/{id}/edit` | seller product |
| `/seller/finance` | seller finance (asks for the password) |
| `/seller` | seller dashboard |
| `/rider/deliveries/{shipmentId}` | rider job (`GET /rider/deliveries/{id}`) |
| `/rider/deliveries`, `/rider/cod` | rider jobs / cash |

### Chat without websockets

There are no websockets yet. While a chat is open, poll `GET /chats/{id}?after_id=<last message id>` every 3–5
seconds; refresh the chat list (`GET /chats`) when the app comes to the foreground or a chat push arrives.

---

## 2. Shared (every app)

### Public

| Method | Path | Notes |
| --- | --- | --- |
| GET | /config | `name, currency, support_email, support_phone, tagline, primary_color, cod_max_amount, default_delivery_fee, free_shipping_threshold, payment_methods[{code,label,available}], apps[], password_reset_code_minutes` |

### Authentication

| Method | Path | Auth | Notes |
| --- | --- | --- | --- |
| POST | /auth/register | — | Customer sign-up: `name, username, email, phone?, password, password_confirmation, referral_code?, terms: true, device_name?` → `201 { data: Profile, token }` |
| POST | /auth/login | — | `login, password, app?, device_name?` (see flow above) |
| GET | /auth/me | Bearer | `{ data: Profile }` |
| POST | /auth/logout | Bearer | Revokes this token |
| POST | /auth/logout-all | Bearer | Revokes every token, web session and push registration |
| POST | /auth/password/forgot | — | `email` → always `200` (doesn't reveal whether the email exists). Emails a **6-digit code**, valid 15 minutes |
| POST | /auth/password/reset | — | `email, code (6 digits), password, password_confirmation` → `200`. Wrong/expired code → `422 errors.code`; 5 wrong tries lock the code. Signs out every device |
| POST | /auth/confirm-password | Bearer | `password` → unlocks seller finance **on this token** for 3 hours (`expires_in` seconds). 6/min |

**Profile** (`/auth/me`, `/account`): `id, name, username, email, phone, profile_picture_url, status, roles[],
primary_role, email_verified_at, created_at, apps[], seller{status, shop{id,name,slug,status,logo_url}}|null,
rider{id,status,availability,partner}|null`.

### Account

| Method | Path | Notes |
| --- | --- | --- |
| GET | /account | Profile |
| POST | /account | `name, email, phone?, profile_picture? (image ≤2 MB), remove_picture?` — multipart when sending a photo. Returns Profile |
| PUT | /account/password | `current_password, password, password_confirmation` (8+ chars, letters and numbers). This phone stays signed in; other devices are signed out |
| GET | /account/deletion | `{ can_delete, blockers[] }` — show the blockers to the user |
| DELETE | /account | `password, reason?` → deletes the account (required by the App Store / Play Store). Refused (`422 errors.account[]`) while orders, shop money/payouts, rider jobs or cash are open; staff accounts can't be deleted here. Removes name, email, phone, photo and addresses; order and payment records keep their own copies |

### Push devices

| Method | Path | Notes |
| --- | --- | --- |
| POST | /devices | `token, platform (android|ios), app (customer|seller|rider)` → `201` new / `200` refreshed. Re-registering the same token moves it to the signed-in account |
| DELETE | /devices | `token` — call on sign-out |

### Notifications (inbox)

| Method | Path | Notes |
| --- | --- | --- |
| GET | /notifications | `?unread=1`; `{ data: [{ id, type, title, body, url, read, created_at }], meta: { …paging, unread } }` |
| POST | /notifications/{id}/read | Mark one read (404 for anyone else's) |
| POST | /notifications/read-all | `{ unread: 0 }` |

`type` values: `seller.applied|approved|rejected`, `product.approved|rejected|violation|unpublished`,
`refund.requested|approved|rejected`, `withdrawal.requested|approved|paid|rejected`, `order.cancelled`,
`order.completed`, `delivery.out`, `delivery.failed`, `shipment.new`, `shipment.unrouted`, `job.declined`,
`job.removed`, `remittance.submitted|confirmed|disputed`. Order and assignment alerts from before Phase 8 have no `type`.

### Chat (customer ↔ shop)

| Method | Path | Notes |
| --- | --- | --- |
| GET | /chats | Customer app: default (`scope=customer`) — chats with shops. Seller app: `scope=shop` — the shop's inbox (403 without an active shop). Items: `id, role (customer|shop), shop{id,name,slug,logo_url}, customer{…}` (seller side only), `last_message{id,body,mine,created_at}, last_message_at, unread, unread_count`. `meta.unread_messages` = total unread |
| POST | /chats | Customer app: `shop_id` → `201` new / `200` existing conversation. You can't message your own shop (`422 errors.shop`). 20/min |
| GET | /chats/{id} | Conversation + `messages[{id, body, mine, sender, auto_reply, created_at}]` oldest first (latest 50). `after_id` = only newer (polling), `before_id` = older page. Opening marks it read |
| POST | /chats/{id}/messages | `body` (≤2000) → `201 { message, auto_replies[] }` — automated shop replies (greeting, off-work, away, keyword) come back immediately. 30/min |
| POST | /chats/{id}/read | Mark read |

---

## 3. Customer app

Sign in with `app: customer`. Browsing works signed out; cart, checkout and account need the token.

### Home and catalog (public)

| Method | Path | Notes |
| --- | --- | --- |
| GET | /home | `banners[], categories[{id,name,slug,icon,children[]}], featured[], recommended[], best_sellers[], new_arrivals[], popular_shops[]`. Send the token if you have one: recommendations follow what the customer bought |
| GET | /categories | Category tree |
| GET | /products | `q`, `category` (slug, includes sub-categories), `shop` (slug), `min`, `max`, `in_stock=1`, `sort` (relevance, newest, price_asc, price_desc, best_selling, top_rated), `per_page` ≤ 50. Prices and price filters/sorting include running promotions. Card: `id, name, slug, price, max_price, compare_at_price, discount_percent, image_url, rating_avg, rating_count, sold_count, in_stock, shop{}` |
| GET | /products/{slug} | `variations[{id, label, options, price, compare_at_price, available, is_default}]`, `images[]`, `price_range`, `shop{}`, `category{}`. 404 unless live in an active shop |
| GET | /shops, /shops/{slug} | Shops (active only), and a shop with its products |

### Account, addresses, verification, selling

| Method | Path | Notes |
| --- | --- | --- |
| GET / PUT | /customer/profile | PUT: `name, email, phone` (photo: `POST /account`) |
| GET / POST | /customer/addresses | POST: `recipient_name, phone (PH mobile), region, province, city, barangay, street, postal_code?, label?, is_default?` → 201 |
| PUT / DELETE | /customer/addresses/{id} | Yours only; there's always exactly one default |
| GET | /customer/verification | `documents[{id, type, type_label, status, notes, created_at}]`, `types[]` |
| POST | /customer/verification | multipart `type, file (jpg/png/pdf ≤5 MB)` → 201. Stored privately. 10/min |
| GET | /customer/seller-application | Current application (or null), `business_types[]`, `shop_categories[]` |
| POST | /customer/seller-application | "Sell on Onetap": `business_name, business_type, tin?, shop_name, shop_category_id?, description?, pickup_street, pickup_barangay, pickup_city, pickup_province, pickup_region, pickup_phone, agree: true` → 201 pending. Once an admin approves it, the account can sign in to the seller app. 5/min |

### Cart, checkout, orders

| Method | Path | Notes |
| --- | --- | --- |
| GET | /cart | `?address_id=` for delivery quotes, `?vouchers[shop_id]=CODE` to preview vouchers (shop or Onetap codes). `{ groups: [{shop, items[], subtotal, shipping{fee, covered, free}, voucher|voucher_error, total}], summary }` |
| POST | /cart/items | `variation_id, quantity` → 201 with the cart. 422 if out of stock, unavailable, on vacation or your own shop |
| PATCH / DELETE | /cart/items/{id} | PATCH: `quantity?`, `is_selected?` |
| POST | /orders | `address_id, payment_method (cod), notes?, vouchers?{shop_id: CODE}` → 201 `{ order_number, grand_total, shipping_total, seller_orders[] }`. Checks out the selected items, one order per shop. An Onetap voucher works once per order. 10/min |
| GET | /orders | `?tab=unpaid|to_ship|shipping|completed|cancelled|return_refund` |
| GET | /orders/{id} | Items, address, `timeline[]` (order events merged with delivery steps), `shipment{tracking_number, status, status_label}`, refund, `can{cancel, confirm_received, request_refund, review}` |
| POST | /orders/{id}/cancel | `reason`; only while Unpaid or Pending |
| POST | /orders/{id}/confirm-received | Delivered → Completed |
| POST | /orders/{id}/refund | `reason, details?, amount? (≤ items total)` → 201. 10/min |
| GET | /refunds | Your refund requests |

### Reviews

| Method | Path | Notes |
| --- | --- | --- |
| GET | /reviews/pending | Items from completed orders waiting for a review: `order_item_id, product_name, variation_label, image_url, order_reference, completed_at` |
| POST | /order-items/{id}/review | `rating 1–5, comment?` → 201 (once per item). 20/min |
| GET | /reviews | Your reviews with the seller's replies |

---

## 4. Seller app

Sign in with `app: seller` (approved shop required). Everything is scoped to the signed-in seller's shop:
other shops' records return `404`. Validation matches the web Seller Center.

### Dashboard, products, inventory

| Method | Path | Notes |
| --- | --- | --- |
| GET | /seller/dashboard | `{ shop, stats: { total_sales, orders, pending_orders, products_sold, products, pending_balance, available_balance } }` |
| GET | /seller/products | `?status=draft|under_review|live|rejected|violation|unpublished`, `?search=` |
| GET | /seller/products/{id} | Detail with images and variations |
| POST | /seller/products | multipart: `name, product_category_id, description?, brand?, weight_grams?, variations[][label, price, compare_at_price?, stock, is_active?], images[] (1–8, jpg/png/webp ≤4 MB, 200–5000 px), submit?` → 201 (`draft`, or `under_review` with `submit=1`). Categories: `GET /categories` |
| POST | /seller/products/{id} | Update (multipart, same fields; images optional; `variations[][id]` keeps existing ones; `remove_images[]`, `primary_image_id`) |
| POST | /seller/products/{id}/submit · /unpublish · /republish | Status actions |
| DELETE | /seller/products/{id} | 204; past orders keep their details |
| GET | /seller/inventory | Every variation: `id, product{id,name,status}, label, sku, price, stock, reserved, available, is_active`. `?low=1` (≤5 available), `?search=` |
| PATCH | /seller/variations/{id}/stock | `stock, note?` → `{ id, stock, reserved, available }` |

### Orders

| Method | Path | Notes |
| --- | --- | --- |
| GET | /seller/orders | `?tab=unpaid|to_ship|shipping|completed|cancelled|return_refund` |
| GET | /seller/orders/{id} | With delivery address and history |
| POST | /seller/orders/{id}/status | `action: accept|ready|cancel`, `reason` to cancel. `ready` creates the shipment and routes it to the covering logistics partner. Packed orders can't be cancelled by the shop |

### Shop settings

| Method | Path | Notes |
| --- | --- | --- |
| GET | /seller/shop | Profile, pickup address, `logo_url, banner_url, template, decoration{accent_color, announcement}, vacation_mode, vacation_message`, `categories[]`, `templates[]` |
| PUT | /seller/shop | `description?, shop_category_id?, pickup_street, pickup_barangay, pickup_city, pickup_province, pickup_region, pickup_phone` |
| POST | /seller/shop/branding | multipart `logo? (≥100 px), banner? (≥800×200), remove_logo?, remove_banner?, template (classic|spotlight|minimal), accent_color (#RRGGBB), announcement?` |
| POST | /seller/shop/vacation | `vacation_mode (bool), vacation_message?` — customers can't order while on vacation |

### Customer service

| Method | Path | Notes |
| --- | --- | --- |
| GET | /chats?scope=shop | Shop inbox (see Chat above); reply with `POST /chats/{id}/messages` |
| GET | /seller/reviews | `?filter=unanswered|1..5`; `meta.average`, `meta.unanswered` |
| POST | /seller/reviews/{id}/reply | `reply` (≤1000) |
| GET | /seller/auto-replies | `replies[{id, type, keyword, message, schedule, is_active}]`, `types`, `timezone` |
| POST | /seller/auto-replies | `type: greeting|off_work|offline|keyword, message, is_active?`; `keyword` for keyword replies; for `off_work`: `days[] (1=Mon…7=Sun), start, end (HH:MM, may pass midnight)`. One greeting, off-work and away message per shop |
| PUT | /seller/auto-replies/{id} | Same fields (type can't change). Leaves on/off as it is unless `is_active` is sent |
| PATCH | /seller/auto-replies/{id}/active | `is_active` — the on/off switch |
| DELETE | /seller/auto-replies/{id} | 204 |

### Finance (password-confirmed)

Call `POST /auth/confirm-password` first; these endpoints answer `423` until then and again after 3 hours.

| Method | Path | Notes |
| --- | --- | --- |
| GET | /seller/finance | `balance{available, pending, total_earned, total_withdrawn}, income{thisMonth, lastMonth, toRelease, inProgress}, holdDays, minWithdrawal, bankAccounts[], recentWithdrawals[], banks[]` |
| GET | /seller/finance/statement | `?month=YYYY-MM` → `statement{gross_sales, vouchers, commission, net_income, orders, refunds}`, `orders[]` (paged) |
| GET | /seller/finance/transactions | Ledger entries (paged) |
| GET | /seller/finance/bank-accounts | `[{id, bank_name, account_name, last4, is_default}]` — full numbers are never returned |
| POST | /seller/finance/bank-accounts | `bank_name (one of banks[]), account_name, account_number (8–20 digits)` → 201. Max 5. 10/min |
| POST | /seller/finance/bank-accounts/{id}/default | Make default |
| DELETE | /seller/finance/bank-accounts/{id} | 204; refused (`422 errors.account`) while a payout is in progress or if it has past payouts |
| GET | /seller/finance/withdrawals | Payout history |
| POST | /seller/finance/withdrawals | `amount, bank_account_id` → 201 pending. At least `minWithdrawal`, at most the available balance. 10/min |

### Marketing

| Method | Path | Notes |
| --- | --- | --- |
| GET / POST | /seller/vouchers | POST: `code (letters/numbers, unique), name, type (fixed|percent|free_shipping), value, min_spend?, max_discount?, usage_limit?, per_user_limit, starts_at?, ends_at?, is_active?`. The shop funds its vouchers |
| PUT / DELETE | /seller/vouchers/{id} | A used voucher is switched off instead of deleted (200 with a message) |
| GET / POST | /seller/promotions | POST: `name, discount_percent (1–90), starts_at?, ends_at?, is_active?, product_ids[]` (your products only) |
| PUT / DELETE | /seller/promotions/{id} | |
| GET | /seller/referrals | `code, link, referred[]` |

---

## 5. Rider app

Sign in with `app: rider`. Riders only see and act on their own jobs (`404` otherwise). Status changes and proof
need an active rider on an open shift (`422` otherwise).

| Method | Path | Notes |
| --- | --- | --- |
| GET | /rider/dashboard | `{ availability, on_shift, partner, stats{ assigned, pendingPickups, todayDeliveries, completedToday, codToday, codUnremitted, earningsToday, ratePerDelivery, failedToday } }` |
| POST | /rider/availability | `action: sign_in|sign_out|available|unavailable` → `{ availability, on_shift }` |
| GET | /rider/profile | `name, email, phone, profile_picture_url, partner, vehicle_type, plate_number, license_number, status, availability, rate_per_delivery, performance{delivered, declined}` |
| PUT | /rider/profile | `phone` (vehicle and status are managed by the partner; photo: `POST /account`) |
| GET | /rider/shifts | Shift log: `started_at, ended_at, minutes` |
| GET | /rider/assignments | `?tab=new|pickup|delivery|issues|history` (same tabs as the web rider app) and `?search=` tracking number; `meta.counts` per tab. The older `?scope=open|history` still works. Job: `id (shipment id), tracking_number, status, job_status, job_type, open, shop, pickup_area, recipient, dropoff_area, cod_amount, attempts, assigned_at` |
| GET | /rider/deliveries/{shipmentId} | Job + pickup/drop-off addresses, items, history, proof, collection, `next_steps[]`, `can_decline` |
| POST | /rider/deliveries/{shipmentId}/status | `status: declined|accepted|arrived_at_pickup|picked_up|in_transit|out_for_delivery|failed_delivery|customer_unavailable|rescheduled|returned_to_seller`, `note` (required for declined, failed, unavailable, returned), `lat?, lng?`. 60/min |
| POST | /rider/deliveries/{shipmentId}/proof | multipart `photo (jpg/png/webp ≤8 MB), recipient_name, recipient_relation?, amount_collected? (defaults to the COD due), lat?, lng?, note?` → 201. Parcel must be out for delivery. 20/min |
| GET | /rider/deliveries/{shipmentId}/proof-photo | The photo (private; send the token) |
| GET | /rider/cod | `{ summary, collections[], earnings[] (7 days), remittances[] }` |
| POST | /rider/cod/remittances | `amount, notes?` → 201. 10/min |

Use the shared endpoints for the inbox, password change and account deletion.

---

## 6. Logistics partner

Not one of the three apps, but available for a future dispatcher app. Logistics role with an active partner;
scoped to that partner.

| Method | Path | Notes |
| --- | --- | --- |
| GET | /logistics/dashboard | Shipment counts per tab, active riders, COD collected today and unremitted |
| GET | /logistics/shipments | `?tab=incoming|assigned|in_transit|out_for_delivery|delivered|failed|returned`, `?search=` |
| GET | /logistics/shipments/{id} | Addresses, history, proof, collection |
| POST | /logistics/shipments/{id}/assign · /unassign | `rider_id` · `reason` |
| GET / POST | /logistics/riders | List (`?status=`), add (`name, username, email, phone, password, vehicle_type, plate_number?, license_number?, activate?`) |
| GET / PUT | /logistics/riders/{id} | Detail with performance; update |
| POST | /logistics/riders/{id}/status | `action: approve|suspend` |

---

## 7. Not in the API yet

- **Online payments** (GCash, Maya, cards): checkout is Cash on Delivery only (`/config.payment_methods`).
- **Firebase push delivery:** devices register and pushes are generated; sending needs Firebase credentials and one driver.
- **Live chat (websockets):** poll as described above.
- **Admin:** web panel only.
- **Email verification** after sign-up, and social sign-in: not built.
