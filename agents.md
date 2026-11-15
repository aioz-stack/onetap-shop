# ONETAP MARKETPLACE
## MASTER DEVELOPMENT PROMPT FOR CLAUDE CODE

### PROJECT FOUNDATION

**Project Name:** Onetap Marketplace

**Frontend Starter Repository:**
https://gitlab.com/czar.gaba/baseplate-react

**Database Configuration:**

- Database Name: `onetap-shop`
- Database Driver: MySQL
- Database Username: `root`
- Database Password: (empty / no password)
- Database Host: `127.0.0.1`
- Database Port: `3306`

**Development Priority: COMPLETE THE WEB APPLICATION FIRST.**

We will eventually develop mobile applications, but the immediate priority is to build a complete, fully functional web-based marketplace.

---

# 1. PROJECT OBJECTIVE

Develop a complete, modern, scalable, multi-vendor online marketplace for local businesses and customers.

The platform will have six major systems:

1. Admin Panel
2. Customer Panel
3. Seller Panel
4. Logistics Panel
5. Rider Panel
6. REST API Infrastructure for future mobile applications

All six systems must communicate through one centralized backend and one centralized database.

The frontend interfaces must have separate layouts and permissions depending on the authenticated user's role.

# 2. IMPORTANT: EXISTING REPOSITORY

Use the following repository as the starting point:

https://gitlab.com/czar.gaba/baseplate-react

Before making changes:

1. Inspect the entire existing project.
2. Identify the existing framework and dependencies.
3. Understand its folder structure.
4. Inspect existing layouts and reusable components.
5. Inspect its routing implementation.
6. Inspect its authentication implementation.
7. Understand its styling and design system.
8. Identify which existing components can be reused.

Do not unnecessarily replace the existing architecture.

Preserve the Baseplate React template's existing functionality wherever possible.

Do not create a completely new project unless there is a documented technical reason.

# 3. DATABASE CONFIGURATION — IMPORTANT

Use MySQL as the primary database.

Configure the application to connect to the following database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=onetap-shop
DB_USERNAME=root
DB_PASSWORD=
```

Important database instructions:

- The database name must be exactly `onetap-shop`.
- The database username must be `root`.
- The database password must remain empty.
- Do not rename the database.
- Do not create a different database name.
- Do not silently change the database credentials.
- Use the existing database configuration consistently throughout development.

If the database does not exist, provide the SQL command to create it:

```sql
CREATE DATABASE `onetap-shop`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Use Laravel migrations or the appropriate migration system for the actual backend framework.

Do not manually create duplicate tables when migrations already exist.

**Database safety:**

- Never execute destructive database operations without explicit approval.
- Never use automatic database resets or destructive migration commands.
- Do not drop existing tables.
- Do not truncate existing records.
- Use incremental migrations.
- Maintain proper foreign keys and indexes.
- Use decimal data types for financial values.
- Use database transactions for important financial and order operations.

For local development, an empty root password is acceptable because this is the explicitly configured local environment. For production deployment, use a dedicated database account with a strong password.

# 4. SYSTEM ARCHITECTURE

Build a modular marketplace architecture.

The React frontend will communicate with the centralized backend.

The backend will handle:

- Authentication
- Authorization
- Business logic
- Database operations
- Order processing
- Inventory
- Financial transactions
- Logistics
- Rider assignments
- Notifications

Create a versioned REST API using:

`/api/v1/`

The web application must be completed first, but the architecture must remain ready for future Android and iOS applications.

Avoid microservices initially. Use a modular monolithic backend with clearly separated business domains.

# 5. ADMIN PANEL

Create a complete administrator dashboard.

### Dashboard
- Total Revenue
- Total Orders
- Total Customers
- Total Sellers
- Total Products
- Pending Orders
- Completed Orders
- Cancelled Orders
- Total Logistics Partners
- Total Riders
- Sales Analytics
- Recent Transactions
- Recent Activities

### User Management
- Customers
- Sellers
- Administrators
- Logistics Partners
- Riders
- User Verification
- Account Status
- Suspend and Reactivate Accounts

### Seller Management
- Seller Registration
- Pending Approval
- Approved Sellers
- Rejected Sellers
- Seller Information
- Seller Performance
- Seller Violations

### Product Management
- All Products
- Live Products
- Under Review
- Violations
- Unpublished Products
- Product Categories
- Product Approval
- Product Rejection

### Order Management
- All Orders
- Unpaid
- To Ship
- Shipping
- Completed
- Cancelled
- Return and Refund

### Logistics Management
- Logistics Partners
- Rider Management
- Service Coverage
- Pickup Monitoring
- Shipment Monitoring
- COD Monitoring
- Delivery Fees

### Finance Management
- Marketplace Revenue
- Seller Earnings
- Admin Commission
- Logistics Fees
- Transactions
- Seller Settlements
- Rider Remittances
- Financial Reports

### System Settings
- General Settings
- Marketplace Branding
- Payment Settings
- Shipping Settings
- Notification Settings
- API Configuration
- Security Settings
- Audit Logs

# 6. CUSTOMER PANEL

Create a modern marketplace shopping experience.

### Homepage
- Marketplace Header
- Search Bar
- Product Categories
- Promotional Banners
- Featured Products
- Recommended Products
- Popular Shops
- Best Sellers
- Recently Added Products

### Product Management
- Product Listing
- Product Details
- Product Images
- Product Variations
- Product Reviews
- Product Ratings
- Seller Information
- Stock Availability

### Shopping Cart
- Group products by seller
- Product Quantity
- Remove Product
- Select Products
- Subtotal
- Shipping Fee
- Order Total

### Checkout
- Delivery Address
- Order Summary
- Seller Grouping
- Shipping Method
- Payment Method
- Place Order

Initially, support Cash on Delivery (COD).

Prepare the payment architecture for future e-wallet and online payment integrations.

### My Account
- My Profile
- Upload Profile Picture
- My Addresses
- My Purchases
- Order Tracking
- My Reviews
- My Refunds
- My Cancellations
- Notifications
- Chat
- Account Security
- Verification

# 7. SELLER PANEL

Create a dedicated Seller Center.

### Dashboard
- Total Sales
- Total Orders
- Pending Orders
- Products Sold
- Total Products
- Seller Balance
- Available Balance
- Sales Analytics

### Product Management
- All Products
- Live
- Under Review
- Violations
- Unpublished
- Add New Product
- Edit Product
- Delete Product
- Product Variations
- Inventory Management

### Order Management
- All
- Unpaid
- To Ship
- Shipping
- Completed
- Cancelled
- Return and Refund

### Shop Management
- Shop Profile
- Shop Logo
- Shop Banner
- Shop Description
- Shop Decoration
- Shop Templates
- Shop Settings
- Vacation Mode

### Customer Service
- Chat Management
- Agent Greetings
- Automated Responses
- Customer Inquiries
- Review Management

### Finance
- My Balance
- Income Overview
- Income Statement
- Income Details
- Bank Accounts
- Withdrawal Requests
- Transaction History

Require password confirmation before accessing sensitive financial and account settings.

### Marketing
- Discounts
- Vouchers
- Promotions
- Affiliate and Referral System

# 8. LOGISTICS PANEL

Create a dedicated logistics management system.

### Dashboard
- Total Shipments
- Pending Pickups
- In Transit
- Out for Delivery
- Delivered
- Failed Deliveries
- Active Riders
- COD Collections

### Rider Management
- Add Rider
- Edit Rider
- Approve Rider
- Suspend Rider
- Rider Information
- Rider Performance
- Rider Availability

### Shipment Management
- Incoming Shipments
- Pickup Requests
- Assigned Shipments
- In Transit
- Out for Delivery
- Delivered
- Failed Deliveries
- Returned Shipments

### Service Coverage
Allow logistics administrators to configure serviceable barangays, cities, and delivery areas.

### Financial Management
- Daily COD Collection
- Rider Remittance
- Delivery Fees
- Logistics Earnings
- Financial Reports

# 9. RIDER PANEL

Create a responsive rider interface optimized for mobile browsers.

### Dashboard
- Today's Deliveries
- Assigned Orders
- Completed Deliveries
- Pending Pickups
- COD Collection
- Daily Earnings

### Availability
- Sign In
- Sign Out
- Available
- Unavailable

### Delivery Management

Implement these delivery statuses:

1. Assigned
2. Accepted
3. Arrived at Pickup
4. Picked Up
5. In Transit
6. Out for Delivery
7. Delivered

Additional statuses:

- Failed Delivery
- Customer Unavailable
- Delivery Rescheduling
- Return to Seller

### Proof of Delivery
- Capture Delivery Photo
- Upload Photo Evidence
- Delivery Confirmation
- Recipient Information
- Delivery Timestamp
- GPS Coordinates (with permission)

### COD Management
- COD Collection
- Daily Collection
- Daily Remittance
- Remittance History
- Collection Discrepancies

Riders must only access their own authorized delivery assignments.

# 10. DATABASE DESIGN

Create a properly normalized relational database using MySQL.

Consider these initial tables:

### Authentication and Users
- users
- roles
- permissions
- user_roles
- role_permissions
- customer_profiles
- seller_profiles
- rider_profiles
- user_addresses

### Marketplace
- shops
- shop_categories
- products
- product_categories
- product_images
- product_variations
- product_inventory
- product_reviews
- vouchers

### Orders
- shopping_carts
- cart_items
- orders
- order_items
- order_addresses
- order_status_histories
- payments
- refunds

### Logistics
- logistics_partners
- service_areas
- shipments
- shipment_items
- shipment_status_histories
- delivery_assignments
- delivery_proofs

### Financial
- commissions
- transactions
- seller_balances
- bank_accounts
- withdrawals
- rider_collections
- rider_remittances

### Communication
- conversations
- conversation_participants
- messages
- notifications

### Administration
- audit_logs
- system_settings

Add other tables and relationships when required.

Create a complete ERD before implementing the entire database.

Ensure the database supports multiple sellers per customer order through properly structured seller-specific order records or suborders.

Maintain accurate financial records and order histories.

# 11. REST API PREPARATION

Prepare versioned API endpoints.

Examples:

Authentication:
- POST /api/v1/auth/login
- POST /api/v1/auth/register
- POST /api/v1/auth/logout

Customer:
- GET /api/v1/customer/profile
- GET /api/v1/customer/orders
- GET /api/v1/customer/addresses

Products:
- GET /api/v1/products
- GET /api/v1/categories
- GET /api/v1/shops

Orders:
- POST /api/v1/orders
- GET /api/v1/orders
- GET /api/v1/orders/{id}

Seller:
- GET /api/v1/seller/dashboard
- GET /api/v1/seller/orders
- POST /api/v1/seller/products

Logistics:
- GET /api/v1/logistics/shipments
- GET /api/v1/logistics/riders

Rider:
- GET /api/v1/rider/assignments
- POST /api/v1/rider/deliveries/{id}/status
- POST /api/v1/rider/deliveries/{id}/proof

Use appropriate authentication, authorization, validation, and rate limiting.

Do not develop mobile applications at this stage.

# 12. SECURITY

Implement:

- Secure Authentication
- Role-Based Access Control
- Server-Side Validation
- CSRF Protection
- XSS Protection
- SQL Injection Protection
- Rate Limiting
- Secure Password Hashing
- Session Security
- File Upload Validation
- Private Document Storage
- Audit Logging
- Financial Transaction Protection

Never expose private API keys or sensitive credentials in frontend code.

# 13. USER INTERFACE AND DESIGN

Use the existing Baseplate React design system as the starting point.

Create a professional, modern, clean, responsive marketplace.

Requirements:

- Consistent typography
- Consistent spacing
- Reusable UI components
- Responsive navigation
- Professional dashboard cards
- Clean data tables
- Search and filtering
- Pagination
- Confirmation dialogs
- Loading states
- Empty states
- Error states
- Success notifications

Create separate layouts for:

- Admin
- Customer
- Seller
- Logistics
- Rider

Support desktop, laptop, tablet, and mobile browsers.

Do not copy another marketplace's entire design. Create an original Onetap identity.

# 14. DEVELOPMENT PHASES

Follow this implementation sequence.

### PHASE 1: INSPECTION AND PLANNING
- Inspect Baseplate React.
- Identify the technology stack.
- Review existing components.
- Establish backend integration.
- Configure MySQL database.
- Create the ERD.
- Prepare development documentation.

### PHASE 2: CORE FOUNDATION
- Authentication
- Registration
- Login and Logout
- Role-Based Access Control
- Database Models
- Database Migrations
- Shared Layouts
- Global Components

### PHASE 3: ADMIN PANEL
Implement all administrator features.

### PHASE 4: CUSTOMER PANEL
Implement the complete shopping experience.

### PHASE 5: SELLER PANEL
Implement the complete Seller Center.

### PHASE 6: LOGISTICS PANEL
Implement logistics operations.

### PHASE 7: RIDER PANEL
Implement delivery and remittance operations.

### PHASE 8: SYSTEM INTEGRATION
Connect all modules through the centralized backend.

### PHASE 9: TESTING AND FINALIZATION
Test all workflows, permissions, database operations, security, and responsive interfaces.

# 15. DEVELOPMENT DOCUMENTATION

Maintain these project documents:

- DEVELOPMENT.md
- ARCHITECTURE.md
- DATABASE.md
- API_DOCUMENTATION.md
- MODULES.md
- CHANGELOG.md

Update the documentation whenever development progresses.

Track:

- Completed Features
- Incomplete Features
- Current Development Phase
- Database Changes
- API Endpoints
- Known Issues
- Testing Results
- Next Tasks

# 16. ACCEPTANCE CRITERIA

The marketplace is not considered complete just because the interfaces look good.

Every feature must be functional.

Verify that:

- All buttons work.
- All forms work.
- All data persists in MySQL.
- All CRUD operations work.
- All dashboards display actual database data.
- All search functions work.
- All filters work.
- All permissions work.
- All order statuses synchronize correctly.
- Seller inventory updates correctly.
- Logistics receives the correct shipments.
- Riders receive authorized assignments.
- Proof of delivery is saved.
- COD transactions are recorded.
- Financial records are accurate.
- All interfaces are responsive.

Test the complete workflow:

Customer places order → Seller receives order → Seller prepares order → Logistics receives shipment → Rider picks up order → Rider delivers order → Customer receives order → COD is recorded → Financial records are updated.

All these steps must work using actual database records.

# 17. FINAL INSTRUCTIONS

Act as a senior full-stack architect and developer.

First inspect the existing GitLab repository and report your technical findings.

Do not immediately rewrite the starter project.

Present the proposed architecture, database structure, ERD, and implementation roadmap before making major changes.

Once the foundation is approved, proceed phase by phase.

Never claim a feature is complete unless it has been implemented and tested.

**MOST IMPORTANT:**

The priority is to finish the complete Onetap Marketplace WEB APPLICATION FIRST.

Use the exact MySQL database configuration:

Database: `onetap-shop`

Username: `root`

Password: EMPTY

Do not develop Android or iOS applications yet.

Keep the backend API-ready for future mobile integration.

Build a secure, maintainable, scalable, and genuinely functional marketplace.