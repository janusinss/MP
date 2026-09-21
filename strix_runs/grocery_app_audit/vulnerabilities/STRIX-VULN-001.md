# STRIX-VULN-001: Hardcoded Database and Administrative Fallback Credentials

- **Severity**: Critical
- **CWE**: CWE-798 (Use of Hard-coded Credentials)
- **OWASP**: A07:2021 - Identification and Authentication Failures
- **Status**: Fixed

## Description
Production database connection scripts and customer/admin authentication portals contained plaintext fallback credentials in repository source files. If external environment variables were unconfigured or stripped, attackers could authenticate directly as system administrators or access remote database instances using these fallback secrets.

## Affected Locations
- `targets/grocery_app/config/db.php:36`
- `targets/grocery_app/auth/login.php:23-26`

## Proof of Concept
1. Send an unauthenticated HTTP POST to `/auth/login.php` with `email=admin@freshcart.com` and `password=admin123`.
2. The endpoint authenticated the user without querying the database, generating an administrative session with `$_SESSION['admin_logged_in'] = true`.

## Remediation
1. Stripped hardcoded database fallback passwords in `config/db.php`.
2. Removed hardcoded admin user/pass strings from `auth/login.php`. Administrator authentication now verifies environment-injected credentials via `hash_equals()` or falls back exclusively to database-hashed credentials.
