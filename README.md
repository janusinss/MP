# FreshCart — Organic Grocery E-Commerce & ADS Platform

> **Year 3 Mini Project: Advanced Database Systems (ADS)**  
> Full-stack PHP/MySQL web application featuring an e-commerce storefront, administrative back-office, RESTful API, and advanced database routines (Views, Stored Procedures, Stored Functions, Triggers, and Transactions).

---

## 📁 Labeled Directory Architecture

```text
grocery_app/
│
├── ⚙️ config/                      # Application Configuration
│   └── db.php                      # Central PDO connection, environment overrides, error handling
│
├── 🛡️ admin/                       # Administrative Management Portal
│   ├── actions/                    # Dedicated Moderation & Deletion Handlers
│   │   ├── product_delete.php      # Removes product from catalog
│   │   ├── user_delete.php         # Deletes user and associated records
│   │   └── review_delete.php       # Moderates/deletes customer reviews
│   ├── index.php                   # Unified Admin Dashboard container & navigation
│   ├── router.php                  # Dynamic AJAX view loader (Overview, Inventory, Orders, Users, Reviews)
│   ├── login.php                   # Admin authentication portal
│   ├── logout.php                  # Admin session termination
│   ├── product_add.php             # New product publishing form with image upload
│   ├── product_edit.php            # Product updater (price, category, stock, image)
│   ├── order_details.php           # Admin order inspection & status management
│   └── export_orders.php           # CSV report generator for orders
│
├── 🔑 auth/                        # Customer Authentication
│   ├── login.php                   # Customer login with password_verify
│   ├── register.php                # Customer registration with password hashing
│   └── logout.php                  # Customer session destroyer
│
├── 📄 pages/                       # Storefront Informational & Policy Pages
│   ├── about.php                   # Mission statement & company background
│   ├── contact.php                 # Customer support form & contact details
│   ├── farmers.php                 # Partner farmer profiles & sourcing transparency
│   ├── sustainability.php          # Carbon footprint and packaging commitments
│   ├── privacy_policy.php          # Customer data & privacy terms
│   └── terms_of_service.php        # Terms and service conditions
│
├── 🔌 api/                         # RESTful JSON Web API (v1)
│   ├── config/                     # CORS headers and API database connection
│   │   ├── cors.php                # Cross-Origin Resource Sharing middleware
│   │   └── database.php            # Standalone API PDO connection
│   ├── utils/                      # Helper Utilities
│   │   ├── AuthMiddleware.php      # Bearer token verification
│   │   └── Response.php            # Standard JSON response formatters
│   └── v1/                         # API Version 1 Endpoints
│       ├── auth/login.php          # API authentication & token generator
│       ├── cart/                   # Cart management endpoints (index, add, update)
│       ├── orders/                 # Order creation & customer history
│       └── products/index.php      # Product catalog listing endpoint
│
├── 🎨 assets/                      # Static Frontend Assets
│   ├── css/
│   │   └── style.css               # Unified stylesheet (custom styles, animations, variables)
│   └── images/                     # Media assets, product images, and thumbnails
│
├── 🗄️ database/                    # SQL Database Definitions
│   └── grocery_db.sql              # Complete database schema, tables, and dump
│
├── 🛠️ scripts/                     # Database Setup, Seeders & Diagnostic Tools
│   ├── setup_advanced_routines.php # Installs Views, Procedures, Functions & Triggers
│   ├── setup_api_db.php            # Migrates API token and API cart schemas
│   ├── force_setup.php             # Manual database column & constraint helper
│   ├── seeder.php                  # Realistic mock user, product, and order seeder
│   ├── verify_api.php              # Automated test suite for REST API endpoints
│   └── check_credentials.php       # Diagnostic connection tester for MySQL credentials
│
├── 🧩 includes/                    # Reusable Global Template Layouts
│   ├── header.php                  # Sticky navbar, cart badge counter, user session info
│   └── footer.php                  # Footer links, newsletter form, global cart AJAX toast
│
├── 🛒 cart/                       # Customer Shopping Cart Workflow
│   ├── index.php                   # Shopping bag view with coupon discount computation
│   ├── add.php                     # Session cart item append action (stock checked)
│   ├── update.php                  # Cart item quantity modifier
│   └── remove.php                  # Cart item removal endpoint
│
├── 📦 orders/                     # Order Processing & Customer History
│   ├── index.php                   # Customer order history list (sp_get_user_order_history)
│   ├── checkout.php                # Shipping address & order confirmation view
│   ├── place.php                   # Transaction-wrapped order placement processor
│   ├── success.php                 # Order confirmation celebration & receipt screen
│   ├── details.php                 # Customer detailed order receipt
│   └── cancel.php                  # Transaction-wrapped cancellation & inventory restock
│
├── 🍏 products/                   # Product Catalog & Discovery
│   ├── view.php                    # Product showcase & customer review submission
│   └── fetch.php                   # Live AJAX search, filter & pagination helper
│
├── 👤 account/                    # Customer Account Management
│   └── profile.php                 # Customer account profile, address & password updater
│
└── 🌐 Root Entrypoint
    └── index.php                   # Main catalog storefront, category filter, search & pagination
```

---

## 🏛️ Architecture Breakdown

### 1. Database Layer (ADS Focus)
* **SQL View (`view_daily_sales`)**: Aggregates daily order revenue and volume excluding cancelled orders.
* **Stored Function (`fn_get_total_spent`)**: Computes customer lifetime expenditure using deterministic SQL reads.
* **Stored Procedure (`sp_get_user_order_history`)**: Fetches descending order timeline for a specified user ID.
* **Trigger (`trg_reduce_stock_after_order`)**: Automatically decrements product inventory on `order_items` creation.
* **Transactions (`beginTransaction` / `commit` / `rollBack`)**: Applied during checkout and order cancellation to ensure ACID consistency.

### 2. Administrative Module (`/admin/`)
* Access controlled via `$_SESSION['admin_logged_in']`.
* Single-page AJAX architecture via [admin/router.php](file:///c:/xampp/htdocs/YEAR%203/Mini%20Project%20ADS/grocery_app/admin/router.php).
* Real-time metrics, low-stock warnings, inventory CRUD, customer audit, review moderation, and CSV exports.

### 3. Customer Storefront
* Responsive layout with organic/modern aesthetic.
* Dynamic category filtering, full-text product search, and page navigation.
* Session-backed shopping bag with promo code discounts (`FRESH50`, `WELCOME20`).

---

## 🚀 Running the Project

1. Start **Apache** and **MySQL** via XAMPP Control Panel.
2. Ensure database `grocery_db` is created in phpMyAdmin:
   ```sql
   CREATE DATABASE IF NOT EXISTS grocery_db;
   ```
3. Import schema:
   * Import [database/grocery_db.sql](file:///c:/xampp/htdocs/YEAR%203/Mini%20Project%20ADS/grocery_app/database/grocery_db.sql).
4. Run ADS Routine Setup:
   * Open `http://localhost/YEAR 3/Mini Project ADS/grocery_app/scripts/setup_advanced_routines.php` in your browser.
5. Access the applications:
   * **Storefront**: `http://localhost/YEAR 3/Mini Project ADS/grocery_app/`
   * **Admin Portal**: `http://localhost/YEAR 3/Mini Project ADS/grocery_app/admin/` (Default credentials: `admin` / `admin123`)
