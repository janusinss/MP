# STRIX-VULN-004: CSV Formula Injection in Order Export Feature

- **Severity**: Medium
- **CWE**: CWE-1236 (Improper Neutralization of Formula Elements in CSV File)
- **OWASP**: A03:2021 - Injection
- **Status**: Fixed

## Description
The administrator order export feature (`admin/export_orders.php`) wrote user-controlled customer names and delivery addresses straight into CSV output without formula escaping. When loaded into spreadsheet viewers (Excel, LibreOffice Calc), dynamic formulas starting with `=`, `+`, `-`, or `@` could be triggered.

## Affected Locations
- `targets/grocery_app/admin/export_orders.php:20-23`

## Proof of Concept
Submit an order with customer name `=2+5`. Export orders as CSV. Opening the spreadsheet evaluated the cell to `7` rather than rendering `=2+5`.

## Remediation
Sanitized all row values before calling `fputcsv()`. If the first character matches `/^[=+\-@\t\r]/`, prepend a single quote `'` to neutralize spreadsheet execution.
