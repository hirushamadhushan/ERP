# CodezaERP (Enterprise v4.2) — System Documentation

A robust, enterprise-grade Enterprise Resource Planning (ERP) platform built with **Laravel 11**, **PHP 8.2**, **MySQL / MariaDB**, **Livewire**, **Alpine.js / jQuery**, and a modern, high-density **Tailwind CSS & Odoo-inspired UI**.

---

## Table of Contents

1. [System Architecture & Tech Stack](#1-system-architecture--tech-stack)
2. [Critical System Constraints & Guardrails](#2-critical-system-constraints--guardrails)
3. [Authentication, Security & RBAC](#3-authentication-security--rbac)
4. [Core Business Modules](#4-core-business-modules)
   - [4.1 User Management & Roles](#41-user-management--roles)
   - [4.2 Contacts & CRM](#42-contacts--crm)
   - [4.3 Products, Variations & Services](#43-products-variations--services)
   - [4.4 Inventory, Lots & Serial Numbers](#44-inventory-lots--serial-numbers)
   - [4.5 Delivery & Fleet Logistics](#45-delivery--fleet-logistics)
   - [4.6 Delivery Consignments & Proof of Delivery (POD)](#46-delivery-consignments--proof-of-delivery-pod)
   - [4.7 Sales Orders & Invoicing Suite](#47-sales-orders--invoicing-suite)
   - [4.8 Payment Accounts & Financial Transfers](#48-payment-accounts--financial-transfers)
5. [Database Architecture & Normalization (4NF Audit)](#5-database-architecture--normalization-4nf-audit)
   - [5.1 Cardinalities & Key Relationships](#51-cardinalities--key-relationships)
   - [5.2 Live Database Tables Catalog](#52-live-database-tables-catalog)
   - [5.3 Normalization Verdict & Modeling Decisions](#53-normalization-verdict--modeling-decisions)
6. [Error Handling & Resilience Architecture](#6-error-handling--resilience-architecture)
7. [Testing & Quality Assurance](#7-testing--quality-assurance)
8. [Installation, Environment & Deployment](#8-installation-environment--deployment)

---

## 1. System Architecture & Tech Stack

- **Backend Framework:** Laravel 11.x on PHP 8.2+
- **Database Engine:** MySQL 8.0+ / MariaDB 10.4+ (InnoDB engine, utf8mb4 charset, strict foreign key constraints)
- **Frontend / UI:** Blade templates, Livewire, Alpine.js, jQuery, Bootstrap Icons (`bi`), DataTables, custom enterprise design system with responsive sidebar and header navigation.
- **Testing Suites:** 
  - PHPUnit 11 (guarded unit/feature tests using SQLite `:memory:`)
  - Playwright / Chromium automated end-to-end browser tests
- **Storage & Documents:** Laravel private local filesystem for signatures, delivery proof photos, and uploaded documents.

---

## 2. Critical System Constraints & Guardrails

> [!CAUTION]
> **Application Database Protection**
> - **NEVER** run `migrate:fresh`, `migrate:refresh`, or database resets against the production/application database. An earlier test reset deleted live data; preserving existing data is strictly mandatory.
> - **Additive Migrations Only:** All future database changes must be strictly additive migrations that preserve existing rows and relationships.
> - **Guarded Testing:** Unit and Feature tests **must** run on an isolated SQLite `:memory:` connection with explicit connection guards. Never run tests against MySQL without database connection overrides.
> - **Preserve Credentials:** The `user1` user account credentials and default administration accounts must never be reset or erased during development.

---

## 3. Authentication, Security & RBAC

### Authentication & Sessions
- **Credential Verification:** Robust authentication verifying username/email against bcrypt password hashes (no hardcoded bypasses).
- **Throttling & Rate Limiting:** Livewire-powered login throttles consecutive failed attempts.
- **Passkeys & WebAuthn:** Built-in hardware key / biometric passkey support backed by the `passkeys` table (`user_id`, `credential_id`).
- **Two-Factor Authentication (2FA):** User-level two-factor authentication with recovery secrets.
- **Session Security:** Cookie-based session tracking with CSRF token verification across all forms and AJAX requests.

### Role-Based Access Control (RBAC)
- Roles (`roles` table) define permissions (`role_permissions` table) with cascade deletion on permission sets.
- Granular permissions enforced across the system:
  - `system.settings`: Required for global settings, invoice schemes/layouts, and payment administration.
  - `delivery.view`, `delivery.manage`, `delivery.transfer`: Required for fleet logistics and warehouse vehicle transfers.
  - `delivery.create`, `delivery.dispatch`, `delivery.arrive`, `delivery.pod`, `delivery.correct`, `delivery.proof.manage`, `delivery.route.manage`: Fine-grained consignment and POD execution permissions.
  - Multi-location warehouse permissions: Users are bound to specific locations or granted `all_locations`.

---

## 4. Core Business Modules

### 4.1 User Management & Roles
- Centralized user administration with roles, location assignments, active/inactive statuses, and audit timestamps.
- Navigation provided via the main sidebar and top header sub-navigation bar with mobile responsive toggle support.

### 4.2 Contacts & CRM
- **Customer & Supplier Management:** Unified contact architecture (`contacts` table) supporting `1 = Customer`, `2 = Supplier`, and `3 = Both`.
  - Shared forms for adding, editing, viewing, and deleting contacts with DataTables export support.
  - Balance tracking: `Total Sale Due`, `Total Sell Return Due`, `Total Purchase Due`, and `Total Purchase Return Due`.
  - Extensible Custom Fields 1–10.
- **Customer Groups (`customer-groups`):**
  - Modes: Percentage discount/markup or Selling Price Group mapping.
  - UI tooltip explaining calculation rules on hover/focus (dismissible via Escape).
  - Rename propagation across assigned contacts and deletion protection for active groups.
- **Commission Agents:** Dedicated management for sales commission agents with percentage tracking.
- **Bulk Contact CSV Import (`/contacts/import`):**
  - Upload UTF-8 encoded CSV files (up to 2 MB / 1,000 rows).
  - 27-column reference specification with downloadable header template.
  - Atomic database transactions with line-by-line error reporting and BOM (Byte Order Mark) handling.

### 4.3 Products, Variations & Services
- **Product Types:**
  - **Stock Items:** Standard physical inventory with unit costs, retail prices, alert quantities, and tax rates.
  - **Combo / Kit Products:** Parent products composed of component items (`combo_product_items` table).
  - **Variations:** Single or multi-attribute variations (`product_variants`, `variation_templates`, `variation_template_values`).
- **Units of Measure (`units`):**
  - Base units and sub-units with conversion multipliers (e.g., Box to Pieces).
  - Support for decimal quantities where allowed.
- **Brands & Hierarchical Categories:**
  - Brands (`brands` table) and multi-level Categories (`categories` table with self-referencing `parent_id`).
- **Services Module (`services`):**
  - Standalone service items with unique service code, name, description, unit rate, and active status toggle.
  - Directly selectable in Sales Orders and Invoices without requiring physical inventory tracking.

### 4.4 Inventory, Lots & Serial Numbers
- **Multi-Location Inventory:** Stock tracking across multiple physical branches and warehouses via `locations` and `location_product`.
- **Batch / Lot Tracking (`product_lots`):**
  - Batch number, manufacturing date, expiration date, purchase price, and selling price.
  - Expired lot guards during loading and sales transactions.
- **Serial Number Tracking (`product_serial_numbers`):**
  - Individual serial keys mapped to products or variants.
  - Status lifecycle (`available`, `allocated`, `sold`, `transferred`).

### 4.5 Delivery & Fleet Logistics
- **Fleet Management:**
  - Vehicles (`delivery_vehicles`): Vehicle number, model, payload capacity, and status.
  - Drivers (`delivery_drivers`): Driver profiles, contact info, license number, and license expiration validation.
  - Vehicle Assignments (`delivery_vehicle_assignments`): 1:1 active vehicle-driver pairing; requires explicit release before re-assignment.
- **Mobile Vehicle Stores (`delivery_vehicle_stores`):**
  - Each delivery vehicle acts as a dedicated mobile store location mapped to `locations`.
  - Loading / Unloading operations transfer stock custody between warehouses and vehicles atomically.
  - Idempotency guaranteed via unique request keys (`request_key`).
  - Completed transfers generate printable transfer receipts with immutable audit trails.

### 4.6 Delivery Consignments & Proof of Delivery (POD)
- **Consignment Workflow:**
  $$\text{Loaded} \longrightarrow \text{In Transit} \longrightarrow \text{Arrived} \longrightarrow (\text{Delivered} \mid \text{Partial} \mid \text{Failed})$$
  - Optional `Cancelled` terminal state before departure.
- **Consignment Lines & Partitioning:**
  $$\text{Loaded Qty} = \text{Delivered Qty} + \text{Damaged Qty} + \text{Missing Qty}$$
  - For serial-tracked products, every individual serial must have an exact outcome assigned.
- **Damage Quarantine:** Damaged goods automatically transfer from the vehicle store to the warehouse's quarantine location under a tracked inventory transaction.
- **Proof of Delivery (POD):**
  - Digital customer signature capture and private photo uploads.
  - SHA-256 checksum verification, GPS location coordinates, recipient details, and 7-year audit retention.
- **Supervisor POD Corrections:**
  - Requires supervisor justification and dual-approval workflow.
  - Reverses inventory movements (`vehicle_delivery_reversal`) and resets delivery status to `arrived` for re-inspection.

### 4.7 Sales Orders & Invoicing Suite
- **Sales Orders (`sales_orders`):**
  - Order numbers, order dates, customer selection, salesperson assignment, order items, and remarks.
- **Enterprise Invoicing (`/invoices/create` & `/invoices/{id}/edit`):**
  - **Orders Integration Modal (`#reference-orders`):**
    - Opens a searchable, paginated modal of pending sales orders.
    - Selecting an order automatically populates customer details, dates, salesperson, reference, and line items.
    - Items imported from an order are visually locked to prevent unintended alteration.
  - **10-Column Reference Table Layout:**
    | # | Column Header | Description |
    |---|---|---|
    | 1 | **Type** | Stock, Service, or Text line indicator |
    | 2 | **Code** | Product SKU/Code or Service Code |
    | 3 | **Details** | Item title, batch/lot description, or text note |
    | 4 | **Sales Price** | Unit selling price (LKR / Rs) |
    | 5 | **Discount (%)** | Percentage discount applied per row |
    | 6 | **Discount Amt.** | Currency amount discounted per row |
    | 7 | **Available Qty** | Live available stock in the selected lot/location |
    | 8 | **Qty** | Billed quantity |
    | 9 | **Total** | Net row total |
    | 10 | **Action** | Serial numbers selector & row deletion |
  - **Duplicate Line Handling Modal:**
    - Adding an existing item/lot combination triggers the *"Already on this invoice"* dialog.
    - User choices:
      1. **Add to paid line:** Automatically increments the existing row's quantity.
      2. **Add as Free Issue:** Creates a dedicated 0.00 Rs line marked as *Free Issue*.
      3. **Cancel:** Aborts the duplicate addition.
  - **Customer Details Card:**
    - Live-updating sidebar showing Customer Name, Business Name, Billing/Shipping Address, Phone, Email, Debit Limit, Balance Due, and Last 5 Invoices history.
  - **Payment Terms & Countdown Engine:**
    - Dynamic term selection (Net 15, Net 30, Due Upon Receipt, etc.).
    - Interactive in-place days editor with automatic due date computation and countdown sync.
  - **Ledger Preview Modal (`#reference-ledger`):**
    - Instant double-entry preview displaying Accounts Receivable debit alongside Revenue, VAT Payable, and COGS credit entries.

### 4.8 Payment Accounts & Financial Transfers
- **Payment Accounts (`payment_accounts`):** Cash drawers, bank accounts, and digital payment channels.
- **Hierarchical Account Types (`payment_account_types`):** Parent account classifications (Assets, Equity, Liabilities) with subtypes.
- **Transfers (`payment_account_transfers`):**
  - Atomic source account debits and destination credits.
  - Unique request IDs preventing duplicate execution.
  - Reconciliation between opening balances, transfers, and current balances.

---

## 5. Database Architecture & Normalization (4NF Audit)

The live database contains **36+ normalized tables** and **36+ declared foreign-key constraints** with **0 orphan rows**.

### 5.1 Cardinalities & Key Relationships

#### One-to-One (1:1)
- `business_settings` $\longleftrightarrow$ `business_product_settings` (FK `business_setting_id`, Unique constraint)
- `delivery_vehicles` $\longleftrightarrow$ `delivery_vehicle_stores` (FK `vehicle_id`, Unique constraint)

#### One-to-Many (1:M)
- `roles` $\longrightarrow$ `users` (`users.role_id`, RESTRICT)
- `roles` $\longrightarrow$ `role_permissions` (`role_permissions.role_id`, CASCADE)
- `users` $\longrightarrow$ `contacts` (`contacts.assigned_to`, SET NULL)
- `customer_groups` $\longrightarrow$ `contacts` (`contacts.customer_group_id`, SET NULL)
- `units` $\longrightarrow$ `products` (`products.unit_id`, RESTRICT)
- `units` $\longrightarrow$ `units` (sub-units via `units.base_unit_id`, RESTRICT)
- `brands` $\longrightarrow$ `products` (`products.brand_id`, SET NULL)
- `categories` $\longrightarrow$ `products` (`products.category_id`, SET NULL)
- `categories` $\longrightarrow$ `categories` (subcategories via `categories.parent_id`, SET NULL)
- `products` $\longrightarrow$ `product_variants` (`product_variants.product_id`, CASCADE)
- `variation_templates` $\longrightarrow$ `variation_template_values` (`variation_template_id`, CASCADE)
- `products` $\longrightarrow$ `product_serial_numbers` (`product_serial_numbers.product_id`, RESTRICT)
- `locations` $\longrightarrow$ `product_serial_numbers` (`product_serial_numbers.location_id`, RESTRICT)
- `invoice_schemes` $\longrightarrow$ `locations` (`locations.invoice_scheme_id`, RESTRICT)
- `invoice_layouts` $\longrightarrow$ `locations` (`locations.invoice_layout_pos_id`, `invoice_layout_sale_id`, RESTRICT)
- `invoice_layouts` $\longrightarrow$ `invoice_layout_labels` (`invoice_layout_labels.invoice_layout_id`, CASCADE)
- `invoice_layouts` $\longrightarrow$ `invoice_layout_options` (`invoice_layout_options.invoice_layout_id`, CASCADE)
- `sales_orders` $\longrightarrow$ `sales_order_lines` (`sales_order_lines.sales_order_id`, CASCADE)
- `sales_invoices` $\longrightarrow$ `sales_invoice_lines` (`sales_invoice_lines.sales_invoice_id`, CASCADE)

#### Many-to-Many (N:M)
- `products` $\longleftrightarrow$ `locations` via `location_product` (Composite PK `[product_id, location_id]`, tracks `opening_quantity`)
- `combo_products` $\longleftrightarrow$ `component_products` via `combo_product_items` (Unique pair `[combo_product_id, item_product_id]`)

### 5.2 Live Database Tables Catalog

| Table Name | Primary Key | Core Purpose & Relationships |
|---|---|---|
| `brands` | `id` | Product brand entities |
| `business_settings` | `id` | Global company identity, currency, precision, timezone |
| `business_product_settings` | `id` | SKU numbering, default units, expiry configurations |
| `categories` | `id` | Product categories & subcategories (`parent_id`) |
| `combo_product_items` | `id` | Kit/combo product ingredient definitions |
| `contacts` | `id` | Customers, suppliers, and commission agents |
| `customer_groups` | `id` | Customer pricing categories and discount rules |
| `delivery_vehicles` | `id` | Fleet vehicle profiles and capacity |
| `delivery_drivers` | `id` | Driver profiles and license verification |
| `delivery_vehicle_stores` | `vehicle_id` | Mobile store locations mapped to vehicles |
| `delivery_vehicle_assignments`| `vehicle_id` | Active 1:1 driver-vehicle pairings |
| `delivery_transfers` | `id` | Custody transfer headers between warehouse and fleet |
| `delivery_transfer_lines` | `id` | Stock line items per vehicle transfer |
| `delivery_consignments` | `id` | Customer dispatch packages linked to loading transfers |
| `delivery_consignment_lines` | `id` | Delivered / damaged / missing outcome quantities |
| `invoice_layouts` | `id` | Print and display layout styles |
| `invoice_schemes` | `id` | Invoice numbering formats, prefixes, and counter |
| `locations` | `id` | Warehouses, storefronts, and vehicle stores |
| `location_product` | Composite | Multi-warehouse stock inventory tracking |
| `passkeys` | `id` | WebAuthn hardware credentials |
| `payment_accounts` | `id` | Cash, bank, and ledger financial accounts |
| `payment_account_types` | `id` | Hierarchical financial account categories |
| `payment_account_transfers` | `id` | Double-entry financial transfers between accounts |
| `products` | `id` | Inventory catalog records |
| `product_lots` | `id` | Batch lots with manufacturing and expiry dates |
| `product_serial_numbers` | `id` | Unique serial numbers per item |
| `product_variants` | `id` | SKU variations linked to template values |
| `roles` | `id` | System user roles |
| `role_permissions` | `id` | Granular permission flags per role |
| `sales_orders` | `id` | Sales order documents |
| `sales_order_lines` | `id` | Items ordered with pricing and discounts |
| `sales_invoices` | `id` | Billed sales invoices |
| `sales_invoice_lines` | `id` | Invoiced product, service, or text line items |
| `services` | `id` | Billable non-inventory service items |
| `units` | `id` | Units of measure and base/sub-unit ratios |
| `users` | `id` | Application users and credentials |
| `variation_templates` | `id` | Variation groups (e.g. Size, Color) |
| `variation_template_values` | `id` | Individual variation options (e.g. Small, Red) |
| `warranties` | `id` | Warranty duration and period templates |

### 5.3 Normalization Verdict & Modeling Decisions

1. **Category & Account Subtypes:** Parent categories/types determine hierarchy via self-referencing foreign keys (`categories.parent_id`, `payment_account_types.parent_id`). Models derive parent references dynamically rather than storing duplicated facts.
2. **Variant Serial Disambiguation:** Variant-backed serials store `product_variant_id` and leave `product_id` null, while standalone serials store `product_id`. Database constraints enforce exactly one parent relation.
3. **Calculated Prices:** Margin percentages and inclusive tax amounts are dynamically derived by models rather than stored as redundant mutable columns.
4. **Current Balance Aggregates:** Account balances (`payment_accounts.current_balance`) represent a maintained cached balance calculated from opening balance plus credits minus debits, backed by atomic transfer records.

---

## 6. Error Handling & Resilience Architecture

### Backend Exception Architecture
- **Central Exception Handler:** Registered in `bootstrap/app.php` and handled by `app/Support/ErrorResponse.php`.
- **Status Code Mapping:**
  - `409 Conflict`: Returned for database integrity conflicts and unique constraint collisions.
  - `503 Service Unavailable`: Returned for database connection outages.
  - `422 Unprocessable Content`: Standard validation failures with field errors.
  - `500 Server Error`: Unexpected errors return a secure reference UUID linked to `storage/logs/laravel.log`.
- **Zero Information Leakage:** Raw SQL queries, trace dumps, and database credentials are never exposed to browser clients, even when debug mode is enabled.
- **Atomic Controller Transactions:** Write operations utilize `Controller::databaseTransaction()` with automatic deadlock retries and atomic rollbacks.

### Frontend Client-Side Handling (`public/js/error-handling.js`)
- Intercepts AJAX failures, network drops, and session expirations.
- Form states and selected file uploads remain preserved on the page when an AJAX submission fails.
- Re-enables submission buttons with safe toast notifications to prevent form locking.

---

## 7. Testing & Quality Assurance

### PHPUnit Feature & Unit Tests
All backend feature tests are executed against an isolated SQLite in-memory database.

```powershell
# Run the complete test suite
& "F:\xampp\php\php.exe" vendor/phpunit/phpunit/phpunit

# Run specific feature tests
& "F:\xampp\php\php.exe" vendor/phpunit/phpunit/phpunit tests/Feature/SalesInvoicesTest.php
& "F:\xampp\php\php.exe" vendor/phpunit/phpunit/phpunit --filter='ContactImportTest|ContactsTest|CustomerGroupsTest'
```

### Playwright Automated Browser Tests
Frontend interactions, UI alignment, dynamic calculations, and locking behaviors are tested via Playwright with Google Chrome:

```powershell
# Run invoice reference tests
node tests/Browser/invoice-reference.mjs

# Run duplicate item modal & sales order locking tests
node scratch/test-duplicate-and-order-locking.mjs
```

---

## 8. Installation, Environment & Deployment

### System Prerequisites
- PHP 8.2 or higher (with `pdo_mysql`, `mbstring`, `openssl`, `xml`, `ctype`, `curl`, `gd` extensions enabled)
- MySQL 8.0+ or MariaDB 10.4+
- Composer 2.x
- Node.js 20+ & npm

### Starting the Local Environment
Ensure MySQL daemon and PHP artisan serve are active:

```powershell
# 1. Start MySQL daemon (XAMPP example)
& "F:\xampp\mysql\bin\mysqld.exe" --defaults-file="F:\xampp\mysql\bin\my.ini" --standalone --console

# 2. Start PHP development server
& "F:\xampp\php\php.exe" artisan serve --port=8000
```

### Production Deployment Procedure
1. Pull latest code and apply additive migrations:
   ```bash
   php artisan migrate --force
   ```
2. Build frontend assets:
   ```bash
   npm run build
   ```
3. Compile configuration and route caches:
   ```bash
   php scripts/deploy-cache.php
   ```
4. Restart PHP-FPM / queue workers to load updated bytecode into memory.

---
*Documentation maintained for CodezaERP / Enterprise v4.2.*
