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
| GET | /rider/assignments | rider | Own assignments only |
| POST | /rider/availability | rider | Available / unavailable |
| POST | /rider/deliveries/{id}/status | rider | Status transition |
| POST | /rider/deliveries/{id}/proof | rider | Photo + recipient + GPS |
| POST | /rider/remittances | rider | Submit daily remittance |

This table is updated as each endpoint is implemented and tested.
