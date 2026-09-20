# FreshCart — Product Specification & Execution Contract

## 1. Product Strategy & User Personas

### 1.1 Product Intent
FreshCart is an organic grocery e-commerce and Advanced Database Systems (ADS) platform that connects conscious consumers with locally sourced, fresh produce, dairy, bakery, and pantry staples. The platform provides a transparent shopping experience paired with an administrative back-office and RESTful API services.

### 1.2 Target Audience & Personas
1. **The Household Restocker (Elena, 34)**: Prioritizes organic certification, speed of weekly reordering, clear stock indicators, and straightforward delivery checkout.
2. **The Artisan & Farmer Supporter (Marcus, 28)**: Values provenance, farmer profiles, sustainability commitments, and ethical trade transparency.
3. **The Store Administrator (Janus, Admin)**: Requires inventory control, order fulfillment workflows, review moderation, customer accounts management, and CSV reporting.

---

## 2. Feature Catalog & Priority Matrix (P0–P3)

### P0 (Critical Baseline — Core Commerce Loop)
- **Catalog Discovery & Search**: Fast product grid with real-time category filtering and live text search.
- **Product Details & Sizing**: High-res imagery, pricing per unit, in-stock badges, and verified customer reviews.
- **Session Shopping Bag**: Dynamic subtotal computation, coupon discount application (`FRESH50`, `WELCOME20`), and stock quantity bounds.
- **Checkout & Order Placement**: Multi-step checkout with address validation, cash/online payment selection, and ACID transaction boundaries.
- **Customer Order Tracking & History**: Stored-procedure-backed order timeline with real-time order cancellation and inventory rollback.

### P1 (Essential Storefront & Admin Operations)
- **Customer Account Settings**: Profile name, delivery address defaults, and password updates.
- **Review Submission**: Authenticated customer ratings and review submission with average score calculation.
- **Admin Analytics Dashboard**: Real-time business telemetry (Revenue velocity Chart.js, 4 KPI cards without AI chip slop, unified 4-segment operational health bar, department aisle share, and reorder alerts).
- **Enlarged View Typography Standard**: Standardized `.admin-view-title` at `2.35rem` with `.admin-kicker` across all back-office operational portals.

### P2 (Administrative Operations & Fulfillment)
- **Order Management Portal**: Real-time order dispatch updates (Pending, Shipped, Delivered, Cancelled) with `.admin-status-pill` tokens and order detail inspection.
- **Inventory Management**: Product catalog with SKU tracking, stock replenishment, addition with image validation, and deletion protection with CSRF.
- **Customer Directory & Audit**: User profile list, lifetime customer spend analytics, order history, and account moderation.
- **Review Gallery Moderation**: Shopper feedback moderation, star rating calculations, and satisfaction tracking.
- **CSV Data Export**: One-click transactional report export (`export_orders.php`) for bookkeeping.

### P3 (Delight & Polish)
- **Micro-interactions**: Exponential deceleration hover transitions, subtle card lifts, and toast feedback.
- **Responsive Layout Harmony**: Fluid grid transitions and zero horizontal scroll overflow across 375px mobile, 768px tablet, and 1280px desktop viewports.

---

## 3. User Journeys & Interaction Architecture

### 3.1 The Shopper Journey
```text
Landing (index.php) → Filter Aisles / Search → Inspect (products/view.php) → Quick Add to Bag → Review Bag (cart/index.php) → Secure Checkout (orders/checkout.php) → Place Order (orders/place.php) → Order Success (orders/success.php) → View Timeline (orders/index.php)
```

### 3.2 The Order Cancellation Journey
```text
Order Timeline (orders/index.php) → Select Order → Cancel Order (orders/cancel.php) → Atomic Stock Restock Trigger → Order Status 'Cancelled'
```

---

## 4. Core State & Data Models

- **User**: `id`, `full_name`, `email`, `password`, `address`, `role`, `api_token`
- **Product**: `id`, `name`, `price`, `image`, `stock_qty`, `category`
- **Order**: `id`, `user_id` (FK), `customer_name`, `address`, `total_amount`, `status`, `created_at`
- **OrderItem**: `id`, `order_id` (FK), `product_id` (FK), `quantity`
- **Review**: `id`, `product_id` (FK), `user_id` (FK), `rating`, `comment`, `created_at`
- **Coupon**: `id`, `code`, `discount_percent`, `expiry_date`, `status`

---

## 5. Non-Functional Requirements & Performance Budgets

- **Interaction Latency**: All filter and search responses rendered within 150ms.
- **Viewport Integrity**: Zero horizontal scroll overflow across 375px, 768px, and 1280px screens.
- **Design Tokens**: 100% compliance with `DESIGN.md` CSS variables and typography tokens.
- **Anti-Slop Compliance**: Zero side-tab accents, zero bounce transitions, zero AI clichés in UI copy.

---

## 6. Master Prompt Execution Contract

```text
Build and refactor the FreshCart frontend storefront to deliver the refined organic baseline specified in DESIGN.md. Ensure all 14 detected CSS anti-patterns in assets/css/style.css are resolved. Align index.php, products/view.php, cart/index.php, and orders/checkout.php with high-craft editorial typography, tight hero clearance, and authentic photography.
```
