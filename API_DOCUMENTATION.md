# Onetap Marketplace — REST API (v1)

Status: **Phase 2 — auth and categories implemented and tested (marked ✅). The rest are planned.**

## Implemented

### POST /api/v1/auth/register ✅
Body: `name, username, email, phone?, password, password_confirmation, referral_code?, terms (true), device_name?`
`201` → `{ data: User, token, token_type: "Bearer" }`. `422` → validation errors. Throttled by `auth` (10/min).

### POST /api/v1/auth/login ✅
Body: `login` (email or username), `password`, `device_name?`
`200` → `{ data: User, token, token_type }`. `422` on bad credentials or an inactive account.

### POST /api/v1/auth/logout ✅ (Bearer)
Revokes the current token. `200` → `{ message }`.

### GET /api/v1/auth/me ✅ (Bearer)
`200` → `{ data: User }`. `401` without a token. `403` (and the token is revoked) if the account is suspended.

User object: `id, name, username, email, phone, profile_picture_url, status, roles[], primary_role, email_verified_at, created_at`.

### GET /api/v1/categories ✅ (public)
Active top-level categories with active `children[]`, ordered by `sort_order`.

### Catalog ✅ (public)
| Method | Path | Notes |
| --- | --- | --- |
| GET | /products | `q`, `category` (slug, includes sub-categories), `shop` (slug), `min`, `max`, `in_stock=1`, `sort` (relevance, newest, price_asc, price_desc, best_selling, top_rated), `per_page` ≤ 50. Card shape: `id, name, slug, price, max_price, compare_at_price, image_url, rating_avg, rating_count, sold_count, in_stock, shop{}` |
| GET | /products/{slug} | `variations[] {id, label, options, price, compare_at_price, available, is_default}`, `images[]`, `price_range`, `shop{}`, `category{}`. 404 unless live in an active shop |
| GET | /shops | `q`; ordered by completed orders |
| GET | /shops/{slug} | Active shops only |

### Customer ✅ (Bearer, customer role)
| Method | Path | Notes |
| --- | --- | --- |
| GET / PUT | /customer/profile | PUT: `name, email, phone` |
| GET / POST | /customer/addresses | POST: `recipient_name, phone (PH mobile), region, province, city, barangay, street, postal_code?, label?, is_default?` → 201 |
| PUT / DELETE | /customer/addresses/{id} | Owner only (404 otherwise); there is always exactly one default |
| GET | /cart | `?address_id=` for delivery quotes, `?vouchers[shop_id]=CODE` to preview a voucher (per group: `voucher{code,label,discount}` or `voucher_error`). Prices include running promotions. Returns `{groups: [{shop, items[], subtotal, shipping{fee, covered, free}, total}], summary}` |
| POST | /cart/items | `variation_id, quantity` → 201 with the cart. 422 if out of stock, unavailable, on vacation or your own shop |
| PATCH / DELETE | /cart/items/{id} | PATCH: `quantity?`, `is_selected?` |
| POST | /orders | `address_id, payment_method (cod), notes?, vouchers?{shop_id: CODE}` → 201 `{order_number, grand_total, shipping_total, seller_orders[]}`. Checks out the selected cart items; one seller order per shop. 422 on stock, COD limit or other method. Throttled 10/min |
| GET | /orders | `?tab=unpaid,to_ship,shipping,completed,cancelled,return_refund`; paginated seller orders |
| GET | /orders/{id} | Seller order with items, address, timeline, shipment, refund, `can{cancel, confirm_received, request_refund, review}` |
| POST | /orders/{id}/cancel | `reason`; only while Unpaid or Pending; releases stock |
| POST | /orders/{id}/confirm-received | Delivered → Completed; posts commission and the seller's pending earning |

### Rider ✅ (Bearer, rider role)

Riders only see and act on their own assignments; anyone else's parcel returns `404`. Status changes and proof need an active rider on an open shift (`422` otherwise).

| Method | Path | Body / query | Response |
| --- | --- | --- | --- |
| GET | /api/v1/rider/dashboard | — | `{ data: { availability, on_shift, partner, stats: { assigned, pendingPickups, todayDeliveries, completedToday, codToday, codUnremitted, earningsToday, ratePerDelivery, failedToday } } }` |
| POST | /api/v1/rider/availability | `action`: `sign_in` \| `sign_out` \| `available` \| `unavailable` | `{ data: { availability, on_shift } }` |
| GET | /api/v1/rider/assignments | `scope`: `open` (default) \| `history` | `{ data: [job], meta: { current_page, last_page, total } }`; job = id (shipment id), tracking_number, status, job_status, job_type, open, shop, pickup_area, recipient, dropoff_area, cod_amount, attempts |
| GET | /api/v1/rider/deliveries/{shipment} | — | job + pickup/dropoff addresses, items, history, proof, collection, `next_steps`, `can_decline` |
| POST | /api/v1/rider/deliveries/{shipment}/status | `status`: declined, accepted, arrived_at_pickup, picked_up, in_transit, out_for_delivery, failed_delivery, customer_unavailable, rescheduled, returned_to_seller; `note` (required for declined, failed, unavailable, returned); optional `lat`, `lng` | `{ data: { id, tracking_number, status } }`; 60/min |
| POST | /api/v1/rider/deliveries/{shipment}/proof | multipart: `photo` (jpg/png/webp, ≤8 MB), `recipient_name`, optional `recipient_relation`, `amount_collected` (defaults to the COD due), `lat`, `lng`, `note` | `201` with the job detail; parcel must be out for delivery; 20/min |
| GET | /api/v1/rider/deliveries/{shipment}/proof-photo | — | the image (private) |
| GET | /api/v1/rider/cod | — | `{ data: { summary, collections, earnings (7 days), remittances } }` |
| POST | /api/v1/rider/cod/remittances | `amount`, optional `notes` | `201` remittance `{ id, date, expected_amount, remitted_amount, discrepancy, status }`; 10/min |

## Planned

- Base path: `/api/v1`
- Auth: Laravel Sanctum bearer tokens (`Authorization: Bearer <token>`), issued by `/auth/login`
- Format: JSON; responses use Eloquent API Resources `{ "data": ..., "meta": {pagination} }`
- Errors: `422` validation `{ message, errors }`, `401`, `403`, `404`, `429`
- Rate limits: `auth` 5/min per IP+identifier, general `api` 60/min per user, checkout 10/min

| Method | Path | Role | Purpose |
| --- | --- | --- | --- |
| POST | /auth/register | guest | Customer registration |
| POST | /auth/login | guest | Issue token |
| POST | /auth/logout | any | Revoke current token |
| GET | /auth/me | any | Current user + roles |
| GET | /products | public | List/search/filter (q, category, shop, min/max price, sort) |
| GET | /products/{slug} | public | Product detail with variations, images, reviews |
| GET | /categories | public | Category tree |
| GET | /shops, /shops/{slug} | public | Shops and shop products |
| GET/POST/PATCH/DELETE | /cart, /cart/items/{id} | customer | Cart management |
| POST | /orders | customer | Checkout (COD) |
| GET | /orders, /orders/{id} | customer | Order history and tracking |
| POST | /orders/{id}/cancel, /orders/{id}/confirm-received, /orders/{id}/refund | customer | Order actions |
| GET/PUT | /customer/profile | customer | Profile |
| GET/POST/PUT/DELETE | /customer/addresses | customer | Addresses |
| POST | /reviews | customer | Review a delivered item |
| GET | /seller/dashboard | seller | Stats |
| GET/POST/PUT/DELETE | /seller/products | seller | Product CRUD |
| GET | /seller/orders | seller | Orders by status |
| POST | /seller/orders/{id}/status | seller | Process / ready for pickup / cancel |
| GET | /logistics/shipments | logistics | Shipments by status |
| POST | /logistics/shipments/{id}/assign | logistics | Assign rider |
| GET/POST/PUT | /logistics/riders | logistics | Rider management |

This table is updated as each endpoint is implemented and tested.
