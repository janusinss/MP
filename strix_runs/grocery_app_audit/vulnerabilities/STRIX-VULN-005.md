# STRIX-VULN-005: Overly Permissive CORS Policy and Missing Inventory Decrement

- **Severity**: High
- **CWE**: CWE-942 (Permissive Cross-Domain Policy with Untrusted Domains), CWE-840 (Business Logic Flaw)
- **OWASP**: A01:2021 - Broken Access Control
- **Status**: Fixed

## Description
1. `api/config/cors.php` configured `Access-Control-Allow-Origin: *` while accepting `Authorization: Bearer <token>`, allowing cross-origin exfiltration.
2. `api/v1/orders/create.php` processed orders without executing an inventory decrement on `products.stock_qty`, enabling infinite orders.

## Affected Locations
- `targets/grocery_app/api/config/cors.php:4`
- `targets/grocery_app/api/v1/orders/create.php:55-66`

## Proof of Concept
1. Send `OPTIONS` request with `Origin: https://attacker.com`; endpoint returned `Access-Control-Allow-Origin: *`.
2. Post order to `api/v1/orders/create.php`; database recorded the order, but `products.stock_qty` remained unchanged.

## Remediation
1. Configured CORS in `api/config/cors.php` to validate origins dynamically against local/matching host domains before emitting origin headers.
2. Added atomic SQL stock decrement in `api/v1/orders/create.php` with bounds verification: `UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?`.
