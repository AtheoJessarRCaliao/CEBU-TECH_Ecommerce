# CubeTech Hub — Technical Explanation & Architecture Document

**Web Development Internship Assessment | CubeTech Innovations**

> [!NOTE]
> This document is also available in the organized documentation directory at [`docs/EXPLANATION.md`](file:///c:/Users/THEO/Documents/Cebu-Tech-Ecommmerce/docs/EXPLANATION.md).

---

## 1. System Flow Summary

CubeTech Hub coordinates two primary user contexts over a unified relational data layer: a **Customer Storefront** and an **Admin Dashboard**.

```mermaid
flowchart TD
    subgraph CustomerFlow["Customer Storefront Flow"]
        A["Catalog Discovery<br/>(Search, Filter, Sort)"] --> B["Product Details<br/>(Live Stock & Related Items)"]
        B --> C["Shopping Cart<br/>(Session + HMAC Persistence)"]
        C --> D["Checkout Submission<br/>(Customer & Delivery Info)"]
        D --> E{"Live Concurrency &<br/>Price Recheck"}
        E -- "Price / Stock Delta" --> C
        E -- "Validation Pass" --> F["Order Placement<br/>(Atomic Stock Reduction)"]
        F --> G["Receipt Confirmation &<br/>Self-Service Tracker"]
        G --> H{"Customer Self-Service<br/>Cancel Eligible?"}
        H -- "Yes (Pending/Confirmed)" --> I["Restore Stock (1x)<br/>Status = Cancelled"]
        H -- "No (Preparing/Shipped/Completed)" --> J["Cancellation Locked"]
    end

    subgraph AdminFlow["Admin Dashboard Flow"]
        K["Splash Activity &<br/>Demo Sign-In"] --> L["Overview Dashboard<br/>(5 Metric Cards & Real-Time Clock)"]
        L --> M["Product Management<br/>(CRUD + Deactivate Guard)"]
        L --> N["Category Management<br/>(CRUD + Assignment Guard)"]
        L --> O["Order Management<br/>(Strict Status Progression)"]
        L --> P["Customer Summary<br/>(Normalized Email Deduplication)"]
        O --> Q{"Eligible<br/>Cancellation?"}
        Q -- "Yes (Pending/Confirmed)" --> R["Restore Stock (1x)<br/>Set stock_restored = 1"]
        Q -- "No" --> S["Block Invalid Transition"]
    end

    CustomerFlow <-->|Shared SQLite / PostgreSQL DAL| AdminFlow
```

### 1.1 Customer Storefront Lifecycle
1. **Catalog Discovery (`public/products.php`)**:
   - Queries `Repository::getProducts()` with `customer_only = true`.
   - Inactive products (`is_active = 0`, e.g. `#12 CubeTech Legacy Trackball Pro`) are excluded from customer views and direct URL requests (`product.php?id=12`).
   - Active products with zero inventory (`stock = 0`, e.g. `#4 AeroLight 55g Gaming Mouse`) display an `Out of Stock` badge and a disabled `Add to Cart` button.
   - Multi-parameter filtering simultaneously combines search strings (`LIKE %query%`), category IDs, and price sorting (`price_asc` / `price_desc`).
2. **Product Details & Related Items (`public/product.php`)**:
   - Renders authentic product photography, live stock badge, quantity selector capped at current available stock, and up to 3 active related items in the same category.
3. **Cart Persistence & Integer Math (`public/cart.php`)**:
   - State is stored in PHP `$_SESSION['cart']` and mirrored to an HMAC-signed cookie (`cubetech_cart_sig`) to persist across tab reloads and browser sessions without tampering risk.
   - Repeated additions of the same product ID merge into an incremented quantity rather than creating duplicate lines.
   - All arithmetic uses integer centavos (`price_cents * quantity`), guaranteeing `Subtotal == Total` with zero floating-point imprecision (`PHP 325.50` test case).
4. **Checkout Concurrency Verification (`public/checkout.php`)**:
   - Submissions validate name, email, contact number, delivery address, and payment method (`COD`, `E-Wallet`, `Bank Transfer`).
   - Before inserting the order, a transactional recheck verifies that every cart item is still active, has sufficient stock, and that its `unit_price_cents` matches the price snapshot recorded when added.
   - If an admin altered a price or inventory while the customer was browsing, order creation aborts, cart snapshots are updated to live values, and an alert displays the exact delta.
5. **Order Persistence & Atomic Inventory Update (`public/confirmation.php`, `public/track.php`)**:
   - Inside an ACID transaction (`BEGIN TRANSACTION`):
     - An order header is created with `status = 'Pending'` and a unique identifier (`ORD-YYYYMMDD-XXXX`).
     - Line items are written to `order_items` with immutable snapshots (`product_name_snapshot`, `unit_price_cents_snapshot`).
     - Product stock is decremented atomically (`stock = stock - quantity`).
     - Transaction commits and cart is emptied.
   - The customer receives an itemized receipt and can monitor live status and remaining stock in real-time via `public/track.php`.
6. **Customer Self-Service Cancellation (`public/track.php`)**:
   - Customers can cancel their order directly from the order tracking view as long as the status is `Pending` or `Confirmed`.
   - Upon cancellation, all ordered quantities are atomically returned to inventory, `stock_restored` is set to `1`, and the status transitions to `Cancelled`.
   - Repeated cancellations or cancelling completed/shipped orders is rejected.

### 1.2 Admin Dashboard Lifecycle
1. **Splash Activity & Authentication Guard (`public/admin/login.php`, `app/Core/Auth.php`)**:
   - Engaging splash activity with brand logo pulse and progress tracker before displaying the login form.
   - Features 1-click credential auto-fill (`admin@cubetech.demo` / `AdminDemo123!`), error shake animation (`@keyframes shakeError`), and route guarding for all `/admin/*` endpoints.
2. **Overview Metrics & Live Clock (`public/admin/index.php`)**:
   - Aggregates the 5 required assessment cards:
     - `Total Products`: Count of all active and inactive inventory.
     - `Total Orders`: Count of all orders across every status (including cancelled).
     - `Pending / Completed Orders`: Active pending queues vs finalized shipments.
     - `Total Customers`: Count of distinct customers grouped by normalized email (`LOWER(TRIM(email))`).
     - `Total Sales`: Exact sum of `total_cents` for `Completed` orders only (cancelled and pending orders are excluded).
   - Features a real-time live clock ticking in `Asia/Manila` time with an active status pulse indicator.
3. **Relational Deletion Protection (`public/admin/products.php`, `public/admin/categories.php`)**:
   - Products referenced in existing order history cannot be deleted (enforced by database `ON DELETE RESTRICT`).
   - Attempted deletion surfaces a `.feedback-card` explaining that historical orders depend on the record and provides a 1-click **"Mark as Inactive Instead"** action.
   - Categories containing assigned products cannot be deleted until products are reassigned or deleted.
4. **Strict Order Fulfillment State Machine (`public/admin/order.php`)**:
   - Strict progressive sequence:
     $$\text{Pending} \longrightarrow \text{Confirmed} \longrightarrow \text{Preparing} \longrightarrow \text{Shipped} \longrightarrow \text{Completed}$$
   - Status regressions (e.g. `Confirmed → Pending`) and step-skipping (e.g. `Pending → Shipped`) are rejected by server validation.
   - Cancellations are permitted **only from `Pending` or `Confirmed`**.
   - Upon cancellation, ordered quantities are restored to `products.stock` **exactly once**, and `stock_restored = 1` is recorded to prevent duplicate restocks.

---

## 2. Data Model Summary

### 2.1 Entity-Relationship Diagram

```mermaid
erDiagram
    categories ||--o{ products : "contains"
    orders ||--|{ order_items : "contains"
    products ||--o{ order_items : "referenced_by"

    categories {
        INTEGER id PK
        TEXT name UK "Case-insensitive unique"
        TEXT slug
        TEXT description
        TEXT created_at
    }

    products {
        INTEGER id PK
        INTEGER category_id FK "RESTRICT on delete"
        TEXT name
        TEXT slug
        TEXT description
        INTEGER price_cents "Integer centavos > 0"
        INTEGER stock "Integer >= 0"
        INTEGER is_active "0 or 1"
        INTEGER is_featured "0 or 1"
        TEXT image_url
        TEXT created_at
        TEXT updated_at
    }

    orders {
        INTEGER id PK
        TEXT order_number UK "e.g. ORD-20261101-1001"
        TEXT customer_name
        TEXT customer_email
        TEXT normalized_email "LOWER(TRIM(email))"
        TEXT contact_number
        TEXT delivery_address
        TEXT payment_method "COD, E-Wallet, Bank Transfer"
        TEXT order_notes
        INTEGER subtotal_cents
        INTEGER total_cents
        TEXT status "Pending, Confirmed, Preparing, Shipped, Completed, Cancelled"
        INTEGER stock_restored "0 or 1 (restock guard)"
        TEXT created_at
        TEXT updated_at
    }

    order_items {
        INTEGER id PK
        INTEGER order_id FK "CASCADE on delete"
        INTEGER product_id FK "RESTRICT on delete"
        TEXT product_name_snapshot "Immutable historical name"
        INTEGER unit_price_cents_snapshot "Immutable historical price"
        INTEGER quantity "Integer > 0"
        INTEGER line_total_cents
    }
```

### 2.2 Currency & Exact Precision Architecture
- All monetary representations across the database, session, and application code are stored as positive whole-number integers representing Philippine centavos (`₱1.00 = 100 centavos`).
- Standard decimal formatting is applied strictly at the presentation layer via:
  ```php
  function format_php_cents(int $cents): string {
      return 'PHP ' . number_format($cents / 100, 2);
  }
  ```
- This architecture eliminates IEEE 754 floating-point inaccuracies, ensuring arithmetic consistency across line-item subtotals, order summaries, and total sales aggregations.

### 2.3 Derived Customer Entity
- The `customers` directory is dynamically computed from the `orders` table grouped by `normalized_email` (`LOWER(TRIM(email))`):
  - `name`: Most recent submitted customer name for that email.
  - `contact_number`: Most recent phone number provided.
  - `order_count`: Total historical orders placed.
  - `total_spent_cents`: Sum of `total_cents` for orders with `status = 'Completed'`.

### 2.4 Cloud & Supabase PostgreSQL Compatibility
- In addition to native SQLite 3, the data layer includes `database/supabase_schema.sql` tailored for PostgreSQL on **Supabase**:
  - Uses `GENERATED ALWAYS AS IDENTITY` primary keys.
  - Case-insensitive category uniqueness via `UNIQUE (LOWER(name))`.
  - Check constraints for non-negative values (`price_cents > 0`, `stock >= 0`, `quantity > 0`).
  - Pre-populated seed catalog matching assessment fixtures.

---

## 3. Codebase File & Folder Organization Architecture

The project employs an industry-standard layered architecture enforcing clear separation of concerns, defensive security boundaries, and modular reusability:

```text
Cebu-Tech-Ecommmerce/
├── app/                                  # APPLICATION CORE TIER (Private, outside web root)
│   ├── Config/                           # Bootstrap, configuration, constants, session & CSRF helpers
│   ├── Core/                             # Domain logic: Auth, Cart, Repository (SQLite PDO & JSON)
│   └── Views/                            # Shared presentation layouts and partial components
│       ├── admin_footer.php              # Standardized admin footer with real-time clock support
│       ├── admin_header.php              # Secure admin navigation bar with live indicator
│       ├── footer.php                    # 4-column balanced customer footer with mobile padding
│       └── header.php                    # Responsive customer navbar with cart count badge
├── database/                             # RELATIONAL SCHEMA & SEED TIER (DDL & DML source files)
│   ├── schema.sql                        # SQLite 3 DDL schema definitions
│   ├── seed.sql                          # SQLite 3 DML initial seed data
│   └── supabase_schema.sql               # PostgreSQL / Supabase cloud-ready schema and seeds
├── docs/                                 # SYSTEM DOCUMENTATION TIER
│   └── EXPLANATION.md                    # In-depth architectural explanation and technical analysis
├── public/                               # WEB PRESENTATION TIER (Public web document root)
│   ├── admin/                            # Protected administrative management endpoints
│   │   ├── categories.php                # Category CRUD & integrity guards
│   │   ├── customers.php                 # Derived unique customer aggregation
│   │   ├── index.php                     # Executive overview & 5 KPI cards
│   │   ├── login.php                     # Splash activity & demo sign-in
│   │   ├── logout.php                    # Admin session termination
│   │   ├── order.php                     # Single order fulfillment & state machine
│   │   ├── orders.php                    # Order ledger & status filters
│   │   └── products.php                  # Product inventory CRUD & stock management
│   ├── assets/                           # Static client resources
│   │   ├── css/style.css                 # Comprehensive vanilla CSS design system
│   │   ├── images/*.jpg                  # Curated product photography assets
│   │   └── js/app.js                     # Native JavaScript & window.CubeToast notification engine
│   ├── cart.php                          # Shopping cart review & integer quantity updates
│   ├── checkout.php                      # Order submission & price concurrency verification
│   ├── confirmation.php                  # Order receipt, tracking redirect & live stock feedback
│   ├── index.php                         # Featured storefront homepage & hero banner
│   ├── product.php                       # Single item details & related product carousel
│   ├── products.php                      # Full catalog search, category filter & sorting
│   └── track.php                         # Order status tracker, recent orders feed & self-cancel
├── scripts/                              # AUTOMATION & CLI TIER
│   └── seed.php                          # CLI database migration and re-seeding tool
├── storage/                              # PERSISTENT LOCAL DATA STORES (Git-ignored)
│   ├── .gitkeep                          # Git retention placeholder
│   └── cubetech.sqlite                   # Active SQLite 3 database file
├── tests/                                # AUTOMATED ACCEPTANCE TEST SUITE
│   └── acceptance_checks.php             # Comprehensive verification runner (Checks 01 through 10)
├── .dockerignore                         # Container build exclusion rules
├── .gitignore                            # Excludes local databases, runtime sessions, and caches
├── Dockerfile                            # Production cloud container configuration (Render.com)
├── EXPLANATION.md                        # Root architecture overview & references
├── README.md                             # Comprehensive project documentation & user manual
└── start-server.bat                      # Windows one-click local server launcher
```

### 3.1 Architectural Principles
1. **Public Web Root Isolation (`public/`)**:
   - The web server is pointed strictly to `public/` (`php -S localhost:8000 -t public`).
   - Sensitive application code in `app/`, database definitions in `database/`, storage files in `storage/`, and test scripts in `tests/` cannot be directly accessed by web browsers via URL manipulation.
2. **Single Responsibility in `app/Core/`**:
   - `Auth.php`: Exclusively handles demo session state and route protection.
   - `Cart.php`: Manages session and HMAC cookie persistence, duplicate line merging, and centavo subtotal sums.
   - `Repository.php`: Acts as the single point of truth for all data queries, transactions, concurrency re-checks, and fallback engine switching.
3. **DRY View Templating in `app/Views/`**:
   - Header and footer layouts are defined once and reused across all customer and admin pages, ensuring consistent branding, responsive navigation, and unified feedback styling.

---

## 4. Known Issues & Boundaries

Across comprehensive automated and manual verification, **zero blocking defects or functional anomalies exist**. All edge cases are handled predictably:

1. **Simulated Authentication Scope**:
   - The admin authentication mechanism relies on a demo session check (`app/Core/Auth.php`) using pre-configured assessment credentials.
   - *Design Note*: This is implemented intentionally to fulfill the requirements of the offline assessment evaluation and is clearly labeled as unsuitable for production deployment without enterprise OAuth/RBAC.
2. **Single-Host Write Locking**:
   - SQLite operates with file-level concurrency locking on writes (`storage/cubetech.sqlite`).
   - *Design Note*: Ideal for single-instance applications, local evaluations, and containerized deployments; high-volume multi-region clustering would migrate the existing schema to PostgreSQL on Supabase.
3. **Local Filesystem Media Storage**:
   - Product imagery resides in `public/assets/images/`. A cloud production build would route uploads to an S3-compatible bucket or CDN.

---

## 5. Unfinished Requirements

**None.** Every requirement outlined in the assessment specification has been fully implemented, styled, and verified.

### Acceptance Verification Matrix (`tests/acceptance_checks.php`)
| Section / Check | Status | Verification Detail |
|---|---|---|
| **Check 01: Catalog Search, Filter & Sort** | **Complete** | Search + category filter + price sorting operate simultaneously. Inactive products (`#12`) are hidden from listings and direct links. Zero-stock items (`#4`) display `Out of Stock` with disabled actions. |
| **Check 02: Cart Merging & Centavo Math** | **Complete** | Adding duplicate items merges quantities. Cart persists across refreshes. The `PHP 325.50` test case (`2 × PHP 120.00 + 1 × PHP 85.50`) computes with zero decimal variance. |
| **Check 03: Form & Quantity Validation** | **Complete** | Blank names, malformed emails, invalid contact numbers, empty carts, and non-positive or excessive quantities are blocked on the server. |
| **Check 04: Checkout Concurrency Recheck** | **Complete** | Out-of-stock and inactive items cannot be ordered. Price or stock changes made during an active cart session are intercepted before committing. |
| **Check 05: Order Placement & Stock Sync** | **Complete** | Orders save with `Pending` status, product inventory decrements atomically, cart clears upon success, and the order immediately surfaces in Admin. |
| **Check 06: Admin CRUD & Integrity Guards** | **Complete** | Changes in Admin reflect on the storefront. Deletion of categories with products or products with order history is strictly blocked with clear UI feedback. |
| **Check 07: Strict Order State Machine** | **Complete** | Enforces `Pending → Confirmed → Preparing → Shipped → Completed`. Cancellations are permitted only from `Pending` or `Confirmed`, and restore stock once. |
| **Check 08: Metric Cards & Customer Roster** | **Complete** | Sales card calculates `Completed` orders only. Unique customer counts normalize emails to prevent duplicates. |
| **Check 09: Snapshot Immutability** | **Complete** | Admin price/name modifications do not mutate historical order item snapshots (`product_name_snapshot`, `unit_price_cents_snapshot`). |
| **Check 10: Responsive Layouts (375px / 1280px)** | **Complete** | Admin topbar collapses to a 56px sticky mobile app bar; product cards format in a 2-column mobile grid; tables convert to stacked cards; zero horizontal overflow. |

---

## 6. Disclosure of AI Tools, Templates, and Tutorials

In accordance with academic and corporate integrity guidelines, the tools and resources used during development are disclosed below:

### 6.1 AI Tools
- **Google Antigravity (Powered by Google DeepMind)**:
  - Used as an AI pair-programming assistant for:
    - Designing the clean relational schema (`database/schema.sql`, `database/supabase_schema.sql`) and sample seed datasets (`database/seed.sql`).
    - Architecting the dual storage repository pattern (`app/Core/Repository.php`) supporting SQLite PDO with automatic JSON fallback.
    - Implementing the HMAC-signed persistent cart session logic (`app/Core/Cart.php`).
    - Authoring the automated CLI acceptance test suite (`tests/acceptance_checks.php`).
    - Developing modern responsive CSS components (splash activity animation, sticky 56px mobile app bar, 2-column mobile product cards, and `.feedback-card`).
    - Rebranding to CubeTech Hub and integrating real-time local timestamps in `Asia/Manila`.

### 6.2 Templates & Starter Kits
- **No Third-Party CSS/JS Frameworks or Boilerplates Used**:
  - The application was built from scratch without Bootstrap, Tailwind CSS, jQuery, or frontend meta-frameworks.
  - All styling is authored in custom Vanilla CSS3 (`public/assets/css/style.css`), leveraging modern CSS Grid, Flexbox, custom design tokens, and media queries.
  - All interactivity is authored in Vanilla JavaScript (`public/assets/js/app.js`), featuring native HTML5 `<dialog>` modals and the `window.CubeToast` notification manager.

### 6.3 Tutorials & Reference Documentation
- **PHP Official Manual (`php.net`)**:
  - PDO SQLite connection handling, prepared statements, and transaction demarcation (`beginTransaction`, `commit`, `rollBack`).
  - Session cookie parameters and cryptographic hashing (`hash_hmac`).
- **SQLite Documentation (`sqlite.org`)**:
  - Foreign key constraint enforcement (`PRAGMA foreign_keys = ON`) and `ON DELETE RESTRICT` semantics.
  - Case-insensitive indexing via `COLLATE NOCASE`.
- **Supabase Documentation (`supabase.com`)**:
  - PostgreSQL table definitions, identity columns, and cloud connectivity.
- **MDN Web Docs (`developer.mozilla.org`)**:
  - Semantic HTML5 elements (`<dialog>`, `<main>`, `<nav>`, `<article>`).
  - CSS Grid auto-fit and minmax sizing patterns for mobile-friendly cards.
- **Photography Assets**:
  - Authentic product photography sourced from Unsplash under the Unsplash open commercial license, optimized and stored locally at `public/assets/images/*.jpg`.
