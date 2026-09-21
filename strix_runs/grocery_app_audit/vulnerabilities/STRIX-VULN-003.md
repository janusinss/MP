# STRIX-VULN-003: Cross-Site Request Forgery (CSRF) on Cart State Operations

- **Severity**: High
- **CWE**: CWE-352 (Cross-Site Request Forgery)
- **OWASP**: A01:2021 - Broken Access Control
- **Status**: Fixed

## Description
Cart modification handlers allowed state changes without anti-CSRF token verification. A third-party malicious site could submit POST forms targeting the user's browser, clearing or modifying the shopping cart without user knowledge.

## Affected Locations
- `targets/grocery_app/cart/remove.php`
- `targets/grocery_app/cart/update.php`
- `targets/grocery_app/cart/add.php`
- `targets/grocery_app/cart/index.php`
- `targets/grocery_app/includes/header.php`
- `targets/grocery_app/includes/footer.php`
- `targets/grocery_app/products/view.php`

## Proof of Concept
Send an unauthenticated or cross-origin POST request to `/cart/remove.php` with `product_id=1`. The item was removed from the session cart without CSRF verification.

## Remediation
1. Implemented `verify_csrf_token()` validation in `cart/remove.php`, `cart/update.php`, and `cart/add.php`.
2. Embedded `csrf_input()` hidden fields into cart forms in `cart/index.php` and `products/view.php`.
3. Injected `csrf-token` meta tag in `includes/header.php` and automatically attached token to AJAX add-to-cart requests in `includes/footer.php`.
