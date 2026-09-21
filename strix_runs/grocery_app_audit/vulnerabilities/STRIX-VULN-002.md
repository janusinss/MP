# STRIX-VULN-002: Sensitive Information Disclosure via Unsanitized Exceptions and Debug Backdoor

- **Severity**: High
- **CWE**: CWE-209 (Generation of Error Message Containing Sensitive Information), CWE-497 (Exposure of Sensitive System Information)
- **OWASP**: A05:2021 - Security Misconfiguration
- **Status**: Fixed

## Description
The database connection initialization allowed a URL parameter (`?debug=1`) to output internal database hostnames, usernames, database schemas, and stack traces. Furthermore, seven API endpoints reflected raw `$e->getMessage()` to API consumers, revealing SQL syntax details, database structures, and backend file paths.

## Affected Locations
- `targets/grocery_app/config/db.php:46-48`
- `targets/grocery_app/api/v1/products/index.php:73`
- `targets/grocery_app/api/v1/orders/history.php:23`
- `targets/grocery_app/api/v1/orders/create.php:76`
- `targets/grocery_app/api/v1/cart/add.php:52`
- `targets/grocery_app/api/v1/cart/update.php:63`
- `targets/grocery_app/api/v1/cart/index.php:35`
- `targets/grocery_app/api/v1/auth/login.php:46`
- `targets/grocery_app/orders/place.php:102`
- `targets/grocery_app/admin/order_details.php:56`

## Proof of Concept
Query `GET /api/v1/products/index.php?page=-1`. The server returned HTTP 500 containing internal SQL syntax errors:
`{"success":false,"message":"Database Error: You have an error in your SQL syntax near '-24'"}`.

## Remediation
1. Removed `?debug=1` parameter handler from `config/db.php`.
2. Clamped pagination parameters to positive values.
3. Replaced raw `$e->getMessage()` exposures with server-side `error_log()` and returned generic error strings.
