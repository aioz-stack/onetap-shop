# Onetap Marketplace — Modules and Feature Status

Legend: ⬜ not started · 🟨 in progress · ✅ implemented **and tested**

| Panel / Module | Features | Phase | Status |
| --- | --- | --- | --- |
| Core | Auth (login by email/username, register, logout), RBAC, account status, shared layouts, UI components | 2 | ✅ |
| Admin — Dashboard | KPIs, sales analytics, recent transactions/activities | 3 | ✅ |
| Admin — Users | Customers, sellers, admins, logistics, riders, verification, suspend/reactivate | 3 | ✅ |
| Admin — Sellers | Applications, approve/reject, info, performance, violations | 3 | ✅ |
| Admin — Products | All/live/under review/violations/unpublished, categories, approve/reject | 3 | ✅ |
| Admin — Orders | Status tabs, return/refund handling | 3 | ✅ |
| Admin — Logistics | Partners, riders, coverage, pickup/shipment/COD monitoring, delivery fees | 3 | ✅ |
| Admin — Finance | Revenue, seller earnings, commission, logistics fees, settlements, remittances, reports | 3 | ✅ |
| Admin — Settings | General, branding, payment, shipping, notifications, API, security, audit logs | 3 | ✅ |
| Customer — Storefront | Home (banners, categories, featured, recommended, shops, best sellers, new), search, listing, product detail | 4 | ✅ |
| Customer — Cart & Checkout | Seller-grouped cart, select items, shipping fee, COD checkout | 4 | ✅ |
| Customer — Account | Profile + photo, addresses, purchases, tracking, reviews, refunds, cancellations, notifications, chat, security, verification | 4 | ✅ |
| Seller — Dashboard | Sales, orders, balance, analytics | 5 | ✅ |
| Seller — Products | Status tabs, add/edit/delete, variations, inventory | 5 | ✅ |
| Seller — Orders | Status tabs, process, ready for pickup, cancel, returns | 5 | ✅ |
| Seller — Shop | Profile, logo, banner, description, decoration, templates, settings, vacation mode | 5 | ✅ |
| Seller — Customer Service | Chat, greetings, auto-replies, inquiries, review replies | 5 | ✅ |
| Seller — Finance | Balance, income overview/statement/details, bank accounts, withdrawals, history (password-confirmed) | 5 | ✅ |
| Seller — Marketing | Discounts, vouchers, promotions, referrals | 5 | ✅ |
| Logistics — Dashboard | Shipment counts by status, active riders, COD collections | 6 | ✅ |
| Logistics — Riders | Add/edit/approve/suspend, info, performance, availability | 6 | ✅ |
| Logistics — Shipments | Incoming, pickup requests, assign, track all statuses, returns | 6 | ✅ |
| Logistics — Coverage | Barangay/city service areas, fees | 6 | ✅ |
| Logistics — Finance | Daily COD, rider remittances, delivery fees, earnings, reports | 6 | ✅ |
| Rider — Dashboard | Today's deliveries, assigned, completed, pickups, COD, earnings | 7 | ✅ |
| Rider — Availability | Sign in/out, available/unavailable | 7 | ✅ |
| Rider — Deliveries | Full status flow + failure statuses | 7 | ✅ |
| Rider — Proof of Delivery | Photo capture/upload, recipient, timestamp, GPS | 7 | ✅ |
| Rider — COD | Collection, daily remittance, history, discrepancies | 7 | ✅ |
| API v1 | Auth, catalog, customer (shopping, refunds, reviews), seller, logistics, rider and notifications — see API_DOCUMENTATION.md | 2–8 | ✅ |
| Mobile API readiness | Customer, seller and rider apps: per-app sign-in, password reset by code, account deletion, push device registration, chat, every web feature of each app over the API (117 routes) | post-9 | ✅ (Firebase delivery pending credentials) |
| Notifications | Cross-panel alerts (in-app inbox + bell for every role, admin team email, optional user email) | 8 | ✅ |
| Admin — Platform vouchers | Onetap-funded codes valid in any shop, once per order; net revenue reporting | 8 | ✅ |
| End-to-end workflow | Order → seller → logistics → rider → delivered → COD → remittance → earnings release → payout, plus refund clawback (automated through every panel's routes in Phase 9) | 8–9 | ✅ |
| Permissions & security | Route-table permission sweep, security headers, private files, upload checks, rate limits, audits | 9 | ✅ |
| Responsive pass | Every page of every role at 375 / 768 / 1280 px on MySQL (76 pages, all clean) | 9 | ✅ |
