# CebuTech Hub — E-Commerce Website & Admin Dashboard

**Web Development Internship Assessment | CubeTech Innovations**

CebuTech Hub is a responsive, full-stack PHP e-commerce web application built for a premier developer and creator hardware retailer. The system features two interconnected interfaces—a **Customer Storefront** and a protected **Admin Dashboard**—operating over a shared relational SQLite 3 (PDO) data store with an automatic server-side JSON fallback.

---

## 1. Overview

CebuTech Hub provides an end-to-end e-commerce experience tailored to both desktop and mobile devices (`375px` to `1280px+`). All money values use exact integer centavo arithmetic (`price_cents`) to completely prevent floating-point rounding errors.

### Customer Storefront
- **Home Page (`index.php`)**: Features brand identity, navigation bar, hero banner, category cards with real-time active product counters, featured products grid, and a streamlined 3-column footer.
- **Product Catalog (`products.php`)**:
  - Displays authentic product photographs in a responsive grid (**2 columns on mobile devices** for optimal readability without bulky scrolling).
  - Multi-parameter search, category filter, and price sorting (`Low to High` / `High to Low`) work together simultaneously.
  - Informative empty states when no products match filter criteria.
  - Inactive products (`#12 CubeTech Legacy Wired Trackball Pro`) are hidden from public listings and blocked from direct URL access.
  - Active products with zero stock (`#4 CubeTech AeroLight 55g Gaming Mouse`) remain visible with an `Out of Stock` badge and a disabled `Add to Cart` button.
- **Product Details (`product.php`)**: Real high-resolution product photography, live stock badge, quantity stepper (`1` to max available stock), `Add to Cart`, and up to 3 related active products from the same category.
- **Persistent Shopping Cart (`cart.php`)**:
  - Backed by PHP session and HMAC-signed cookie persistence across browser refreshes and tab reloads.
  - Merges duplicate additions into incremented quantities.
  - Strict integer centavo arithmetic (`price_cents * quantity`) ensuring **Subtotal equals Total** with zero decimal discrepancies.
  - Includes a one-click **"Load PHP 325.50 Test Example"** button (`2 × PHP 120.00 + 1 × PHP 85.50 = PHP 325.50`).
- **Checkout & Verification (`checkout.php`, `confirmation.php`)**:
  - Full server-side validation for Customer Name, Email Address, Contact Number, Delivery Address, and Payment Method (`Cash on Delivery`, `E-Wallet`, `Bank Transfer`).
  - **Live Concurrency & Price Recheck**: Submitting an order queries the live database inside a transaction. If an admin altered a product's price, stock, or active status while in the cart, checkout is safely aborted, the cart is refreshed with the exact delta, and the customer is prompted to review before re-submitting.
  - Atomic stock reduction, immutable name/price snapshot recording (`order_items`), and automated cart clearance upon successful order creation.
- **Order Tracker (`track.php`)**: Direct tracking lookup allowing customers to inspect their live order status using their order reference number (e.g., `ORD-20261101-1001`).

### Admin Dashboard
- **Splash Activity & Modern Login (`admin/login.php`)**:
  - Engaging startup splash screen animation with brand pulse and loading progress before revealing the sign-in form.
  - One-click demo credentials auto-fill button, password toggle, error shake animation (`@keyframes shakeError`), and route protection for all `/admin/*` pages.
- **Mobile-Responsive Sticky Admin Header**:
  - On mobile (`<= 768px`), topbar adapts to a compact 56px app bar with a 40×40 touch hamburger button, mobile logo, section heading, and icon-first action pills without horizontal overflow.
- **Overview Dashboard (`admin/index.php`)**:
  - Displays the 5 core summary metric cards: `Total Products` (active + inactive), `Total Orders` (including cancelled), `Pending / Completed Orders`, `Total Customers` (unique normalized emails), and `Total Sales` (`Completed` orders only).
  - Quick action toolbar to seed/reset demo data or navigate directly to management modules.
- **Product Management (`admin/products.php`)**:
  - Full CRUD operations with image uploads, category assignments, and real-time active/stock status toggles.
  - **Relational Integrity Protection**: Hard deletion of products referenced in historical orders is strictly blocked by foreign key constraints. The system surfaces a distinctive `.feedback-card` explaining why deletion was prevented with a 1-click **"Deactivate Instead"** remedy.
- **Category Management (`admin/categories.php`)**:
  - CRUD operations with case-insensitively unique name constraints and URL slug generation.
  - Blocks deletion of categories with active assigned products, surfacing structured feedback.
- **Order Management & Strict State Machine (`admin/orders.php`, `admin/order.php`)**:
  - Newest-first order listing with status badges, customer information, and immutable line item snapshots.
  - Enforces the strict sequential status workflow:
    `Pending → Confirmed → Preparing → Shipped → Completed`
  - Cancellations (`Cancelled`) are permitted only from `Pending` or `Confirmed` status.
  - Stock is restored to inventory **exactly once** (`stock_restored = 1`) upon cancellation and guarded against duplicate restocks.
- **Customer Summary (`admin/customers.php`)**:
  - Read-only customer roster derived from orders grouped by normalized email (`LOWER(TRIM(email))`), displaying the latest contact information, total order count, and lifetime completed spend.
- **Activity & Inventory Notifications (`admin/notifications.php`)**:
  - Real-time event log tracking inventory adjustments, zero-stock warnings, and order status transitions with unread counter.
- **Unified Feedback System**:
  - Visual feedback cards (`.feedback-card`) for contextual warnings and blocked operations.
  - Animated progress bars (`.alert-timer-bar`) on flash notifications.
  - Native accessible HTML5 modal dialogs (`<dialog>`).
  - Toast notification engine (`window.CebuToast`) for interactive client-side events.

---

## 2. Project File & Folder Structure

The project strictly follows professional separation-of-concerns principles. Sensitive application logic, database schemas, and persistent storage are isolated outside the public web root (`public/`), ensuring secure and organized modularity:

```text
Cebu-Tech-Ecommmerce/
│
├── app/                                  # CORE BACKEND LOGIC (Isolated outside web document root)
│   ├── Config/
│   │   └── config.php                    # Application constants, paths, session, centavo & CSRF helpers
│   ├── Core/
│   │   ├── Auth.php                      # Admin authentication guard & demo session manager
│   │   ├── Cart.php                      # Persistent shopping cart (Session + HMAC-signed cookie)
│   │   └── Repository.php                # Data Access Layer: SQLite 3 (PDO) & JSON fallback
│   └── Views/                            # Shared reusable UI presentation templates
│       ├── layouts/                      # Global structural layout headers and footers
│       │   ├── admin_header.php          # Responsive admin topbar & navigation drawer
│       │   ├── admin_footer.php          # Admin closing scripts and toast container
│       │   ├── customer_header.php       # Storefront navigation bar & live cart counter
│       │   └── customer_footer.php       # Streamlined 3-column minimal customer footer
│       └── partials/                     # Reusable modular view components
│           └── product_card.php          # Shared responsive product card (2-col mobile ready)
│
├── database/                             # RELATIONAL SCHEMA & SEED SPECIFICATIONS
│   ├── schema.sql                        # SQLite 3 DDL schema (foreign keys, check constraints, indexes)
│   └── seed.sql                          # Initial seed dataset (3 categories, 12 products, 3 sample orders)
│
├── public/                               # WEB DOCUMENT ROOT (Target of php -S server)
│   ├── index.php                         # Customer: Storefront home page with hero & categories
│   ├── products.php                      # Customer: Product catalog (Search / Filter / Sort)
│   ├── product.php                       # Customer: Product details & related recommendations
│   ├── cart.php                          # Customer: Persistent shopping cart with PHP 325.50 test action
│   ├── checkout.php                      # Customer: Checkout form & live concurrency re-validator
│   ├── confirmation.php                  # Customer: Order confirmation receipt & summary
│   ├── track.php                         # Customer: Self-service order lookup by reference number
│   ├── favicon.ico                       # Website browser icon
│   │
│   ├── admin/                            # ADMIN DASHBOARD ENDPOINTS (Guarded by Auth::requireAdmin)
│   │   ├── index.php                     # Admin: Metrics overview & 5 summary statistics cards
│   │   ├── login.php                     # Admin: Startup splash activity & demo sign-in
│   │   ├── logout.php                    # Admin: Session termination
│   │   ├── products.php                  # Admin: Product CRUD with relational deletion protection
│   │   ├── categories.php                # Admin: Category CRUD & assignment verification
│   │   ├── orders.php                    # Admin: Order roster (newest first)
│   │   ├── order.php                     # Admin: Order detail view & strict state transition machine
│   │   ├── customers.php                 # Admin: Derived customer directory (normalized emails)
│   │   └── notifications.php             # Admin: Live activity feed & inventory alerts
│   │
│   └── assets/                           # STATIC FRONTEND ASSETS
│       ├── css/
│       │   └── style.css                 # Master stylesheet (375px mobile to 1280px+ desktop)
│       ├── js/
│       │   └── app.js                    # Vanilla JS: drawer navigation, CebuToast, modal dialogs
│       └── images/                       # Local authentic JPG photography & SVG vector fallbacks
│           ├── Cebu-Logo.png             # Official CebuTech brand emblem
│           └── prod-*.jpg                # 12 authentic hardware product photographs
│
├── scripts/                              # AUTOMATION & MAINTENANCE UTILITIES
│   └── seed_and_assets.php               # CLI database reset, re-seeder & asset generator
│
├── storage/                              # PERSISTENT LOCAL DATA STORES (Git-ignored)
│   ├── .gitkeep                          # Git retention placeholder
│   └── cebutech.sqlite                   # Active SQLite 3 database file
│
├── tests/                                # AUTOMATED ACCEPTANCE TEST SUITE
│   └── acceptance_checks.php             # Section 17 verification runner (Checks 01 through 10)
│
├── .gitignore                            # Excludes runtime sqlite, json, and upload artifacts
├── EXPLANATION.md                        # Architecture, data model diagrams, known issues & AI disclosure
├── README.md                             # Comprehensive project documentation & user manual
└── start-server.bat                      # Windows one-click local server launcher
```

---

## 3. Technologies

- **Backend**:
  - **PHP 8.0+** (`strict_types=1`, modern object-oriented architecture, native PDO)
  - Built-in session management with HMAC-signed cookie verification
  - CSRF token verification on all POST submissions
  - Integer centavo arithmetic helper functions (`price_cents`, `format_php_cents()`)
- **Database / Data Layer**:
  - **SQLite 3 via PDO** (`storage/cebutech.sqlite`) with foreign key constraints (`PRAGMA foreign_keys = ON`)
  - Relational schema defined in `database/schema.sql` and seeded from `database/seed.sql`
  - Zero-configuration automatic server-side JSON fallback (`storage/cebutech_data.json`) with file-level locking (`LOCK_EX`) if `pdo_sqlite` is unavailable
- **Frontend & Styling**:
  - Semantic **HTML5** with native `<dialog>` modals and SVG micro-icons
  - Custom responsive **CSS3** (`public/assets/css/style.css`) using CSS Grid, Flexbox, custom design tokens, and media queries tested at `375px` and `1280px+`
  - Mobile-first 2-column product grid (`@media (max-width: 600px)`)
  - Vanilla JavaScript (`public/assets/js/app.js`) with zero external libraries or heavy frameworks
- **Media & Assets**:
  - Real authentic product photography (`public/assets/images/*.jpg`) stored locally with graceful `onerror` fallback handlers

---

## 4. Chosen Storage Approach and Limitations

### Chosen Approach
- **Primary Engine**: **SQLite 3 via PDO** stored at `storage/cebutech.sqlite`.
- **Portability Fallback**: File-locked, atomic server-side JSON store at `storage/cebutech_data.json` automatically activated if the PHP environment does not have `pdo_sqlite` loaded.

### Why This Approach Was Chosen
1. **Server-Side ACID Transactions**: SQLite provides atomic transactions (`BEGIN`, `COMMIT`, `ROLLBACK`) required for checkout concurrency checks and order status transitions with inventory increments/decrements.
2. **Relational Data Integrity**: Enforces strict database-level foreign keys (`ON DELETE RESTRICT` on `categories` and `products`, `ON DELETE CASCADE` on `order_items`), preventing orphaned records or corrupt order histories.
3. **True Cross-Client Persistence**: Changes made in the Admin Dashboard immediately reflect for all customer sessions, incognito windows, and different browsers.
4. **Zero-Configuration Deployment**: Requires no external database servers (like MySQL/MariaDB or PostgreSQL daemon setup, user permissions, or port configurations), making it instantaneous to run on any machine with PHP.

### Limitations
- **Single-Host Concurrency**: SQLite utilizes file-level locking for writes. While excellent for local development, assessment evaluation, and moderate single-server traffic, high-concurrency distributed web clusters would require migrating `database/schema.sql` to MySQL or PostgreSQL.
- **Simulated Authentication**: The admin authentication layer uses hardcoded/environment demo credentials stored in PHP session state. It is designed specifically for assessment and evaluation purposes and is **not intended for production deployment**.
- **Local File Uploads**: Product images uploaded through the admin panel are stored in the local file system (`public/assets/images/`). Production systems would integrate object storage (e.g., AWS S3 or Cloudflare R2).

---

## 5. Install / Run Steps

### Prerequisites
- **PHP 8.0 or newer** (CLI executable available in system PATH).
- Recommended: `pdo_sqlite` PHP extension enabled (automatically falls back to JSON if missing).

### Step-by-Step Installation & Launch

1. **Clone or Open the Repository**:
   ```bash
   cd Cebu-Tech-Ecommmerce
   ```

2. **Verify PHP Installation**:
   ```bash
   php -v
   ```

3. **(Optional) Initialize or Reset the Database**:
   ```bash
   php scripts/seed_and_assets.php --reset-db
   ```
   *(Note: The database auto-initializes and seeds automatically on the first web request if no database file exists).*

4. **Launch the Local Development Web Server**:
   - **Option A (Terminal / Command Prompt / PowerShell)**:
     ```bash
     php -S localhost:8000 -t public
     ```
   - **Option B (Windows 1-Click Launcher)**:
     Double-click `start-server.bat` in the project root directory.

5. **Access the Application in Your Browser**:
   - **Customer Storefront**: [http://localhost:8000/index.php](http://localhost:8000/index.php)
   - **Admin Dashboard**: [http://localhost:8000/admin/index.php](http://localhost:8000/admin/index.php)
   - **Order Tracking**: [http://localhost:8000/track.php](http://localhost:8000/track.php)

6. **Run Automated Acceptance Tests**:
   ```bash
   php tests/acceptance_checks.php
   ```

---

## 6. Environment Placeholders

No external `.env` file or complex setup is required to run CebuTech Hub. All defaults are configured in `app/Config/config.php`.

If you wish to customize admin demo credentials via environment variables, set the following placeholders before launching the server:

```env
# Optional: Override Demo Admin Login Credentials
ADMIN_USER=admin@cubetech.demo
ADMIN_PASS=AdminDemo123!
```

### Windows (PowerShell):
```powershell
$env:ADMIN_USER="admin@cubetech.demo"
$env:ADMIN_PASS="AdminDemo123!"
php -S localhost:8000 -t public
```

### Windows (CMD):
```cmd
set ADMIN_USER=admin@cubetech.demo
set ADMIN_PASS=AdminDemo123!
php -S localhost:8000 -t public
```

### Linux / macOS:
```bash
export ADMIN_USER="admin@cubetech.demo"
export ADMIN_PASS="AdminDemo123!"
php -S localhost:8000 -t public
```

---

## 7. Seed / Reset Instructions

The database comes pre-seeded with **3 categories**, **12 distinct products** (including real photography, active items, an out-of-stock item `#4`, and an inactive legacy item `#12`), and **3 sample historical orders** demonstrating various fulfillment stages.

To restore the system back to its clean initial state at any time:

### Method A: Admin Dashboard (1-Click UI Reset)
1. Log into the Admin Dashboard at [http://localhost:8000/admin/login.php](http://localhost:8000/admin/login.php).
2. On the Overview page ([`admin/index.php`](http://localhost:8000/admin/index.php)), click the **"Reset / Re-seed Demo Data"** button in the header toolbar.
3. Confirm the dialog prompt. The SQLite database will be wiped, re-created from `database/schema.sql`, and re-populated from `database/seed.sql`.

### Method B: Command Line (CLI)
Run the automated seed script in your terminal:
```bash
php scripts/seed_and_assets.php --reset-db
```

---

## 8. Customer / Admin Routes

### Customer Routes
| Route | Method | Description |
|---|---|---|
| `/index.php` | `GET` | Home storefront with hero banner, category cards, and featured products |
| `/products.php` | `GET` | Product catalog with combined search, category filter, and price sort |
| `/product.php?id={id}` | `GET` | Product detail view with stock stepper and up to 3 related category products |
| `/cart.php` | `GET`, `POST` | Persistent shopping cart with integer centavo math and one-click test load |
| `/checkout.php` | `GET`, `POST` | Checkout form with live price/stock concurrency verification |
| `/confirmation.php?order={order_no}` | `GET` | Order receipt and confirmation details |
| `/track.php` | `GET` | Self-service order status tracker |

### Admin Routes
| Route | Method | Description |
|---|---|---|
| `/admin/login.php` | `GET`, `POST` | Animated splash activity & demo sign-in with 1-click credential auto-fill |
| `/admin/logout.php` | `GET`, `POST` | Clears demo session and redirects to sign-in |
| `/admin/index.php` | `GET`, `POST` | Overview dashboard with 5 summary metric cards and reset toolbar |
| `/admin/products.php` | `GET`, `POST` | Product CRUD with image upload and relational deletion protection |
| `/admin/categories.php` | `GET`, `POST` | Category CRUD with unique name validation and assignment protection |
| `/admin/orders.php` | `GET` | Filterable list of all orders displayed newest-first |
| `/admin/order.php?id={id}` | `GET`, `POST` | Order detail view with immutable item snapshots and strict status machine |
| `/admin/customers.php` | `GET` | Derived customer directory grouped by normalized email (`LOWER(TRIM)`) |
| `/admin/notifications.php` | `GET`, `POST` | Activity monitoring log and inventory notifications feed |

---

## 9. Demo Credentials

The Admin Dashboard is protected by a simulated authentication guard (`app/Core/Auth.php`).

> [!NOTE]
> **Simulated Authentication Disclosure**: This login is intended strictly for local assessment and demonstration purposes and is not secure for production deployments.

| Field | Value |
|---|---|
| **Email / Username** | `admin@cubetech.demo` |
| **Password** | `AdminDemo123!` |
| **Quick Fill** | Available via the **"Fill Demo Credentials"** button directly on the `/admin/login.php` screen |

---

## 10. Key Testing & Evaluation Scenarios

| Test Case | How to Test | Expected Behavior |
|---|---|---|
| **Exact Centavo Subtotal = Total** | In `/cart.php`, click **"Load PHP 325.50 Test Example"** | Loads 2 × PHP 120.00 (`#9`) + 1 × PHP 85.50 (`#10`). Subtotal is exactly `PHP 325.50`, Total is `PHP 325.50`. |
| **Out-of-Stock Product** | Navigate to `/product.php?id=4` (`AeroLight 55g Gaming Mouse`) | Shows `Out of Stock` badge; `Add to Cart` button is disabled; ordering is blocked. |
| **Inactive Product Isolation** | Navigate directly to `/product.php?id=12` | Product `#12` is hidden from `/products.php` and direct navigation redirects safely with an alert. |
| **Order Deletion Protection** | Go to `/admin/products.php` and try deleting Product `#1` (`ApexPro 75% Mechanical Keyboard`) | Deletion is blocked because it is referenced in an existing order. Displays a `.feedback-card` with an option to **Mark as Inactive Instead**. |
| **Category Deletion Protection** | Go to `/admin/categories.php` and try deleting `Keyboards` | Deletion is blocked because products are assigned to this category. |
| **Strict Order Transitions** | Open an order in `/admin/order.php` | Status updates must follow `Pending → Confirmed → Preparing → Shipped → Completed`. Skipping steps is blocked. |
| **Stock Restoration on Cancel** | In `/admin/order.php`, cancel a `Pending` or `Confirmed` order | Stock is restored to inventory once. The order displays a `Stock Restored` badge and cannot be restored twice. |
| **Mobile Responsiveness** | Open browser DevTools and switch to mobile view (`375px` width) | Product grid renders in **2 columns**; admin header collapses into a sticky compact 56px app bar with touch drawer; no horizontal scrolling. |

---

## 11. Automated Acceptance Test Suite (Section 17)

Run the automated verification suite from the project root:
```bash
php tests/acceptance_checks.php
```

All 10 checks run against the live data layer:
- **Check 01**: Search, category filter, and both price sorts work together. Inactive/out-of-stock states handled. (PASS)
- **Check 02**: Cart merges duplicate products, persists after refresh, and calculates the PHP 325.50 example correctly. (PASS)
- **Check 03**: Empty checkout, invalid customer fields, and invalid/excessive quantities are blocked. (PASS)
- **Check 04**: Inactive/out-of-stock items cannot be ordered. Checkout detects price/stock changes before saving. (PASS)
- **Check 05**: Successful order shows confirmation, appears in admin, reduces stock, and clears cart. (PASS)
- **Check 06**: Admin product/category changes appear on customer site. Referenced records cannot be deleted. (PASS)
- **Check 07**: Valid statuses save; invalid transitions fail. Cancellation restores stock once. (PASS)
- **Check 08**: Completed-order totals and unique-email customer counts match underlying orders. (PASS)
- **Check 09**: Existing order prices/names remain unchanged after product edits. Data persists. (PASS)
- **Check 10**: Customer/admin screens work at 375 px and 1280 px widths with no horizontal page scroll. (PASS)
