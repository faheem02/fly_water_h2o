# Fly Water H2O Supply Management System

## Stack
- **PHP 8+** vanilla (no framework), **MySQL** via `mysqli_*`, Bootstrap 5.3.3, jQuery 3.7.1, Font Awesome 6.5.2
- DataTables jQuery plugin used on listing pages for search/sort/pagination
- Runs on **XAMPP** (localhost). DB: `fly_water_h2o`, user: `root`, pass: empty (`includes/db.php:3-6`)

## Setup
1. Create DB `fly_water_h2o` and import `database/schema.sql` (21 tables + default admin + expense categories)
2. Or hit `http://localhost/fly_water_h2o/database/database.php` once (idempotent; also runs migrations)
3. Login: `admin` / `admin123` at `login.php`

## Architecture
- **Entrypoints**: `index.php` (dashboard), `login.php`, `logout.php`. Salesman login → `pages/deliveries.php`; dashboard redirects salesman → `pages/customer_view.php`
- **Feature pages**: 31 PHP files in `pages/`
- **Includes**: `db.php` (session + DB + auth helpers), `header.php` (layout + **sidebar inline here** + mobile menu JS), `footer.php` (jQuery + Bootstrap JS), `txt.php` (site config strings)
- Auth guard: `$_SESSION['admin_logged_in']` isset-check at top of every page, then role checks (`is_admin()` / `is_salesman()`)
- `includes/sidebar.php` is dead (sidebar moved into `header.php`); `includes/supplier_ledger.php` has `rebuildSupplierLedger()` used after supplier data changes

## Roles (`users.role`)
- Two roles: `admin` and `salesman`. Helpers live in `db.php`: `current_role()`, `is_admin()`, `is_salesman()`, `salesman_match_condition()`, `salesman_owns_customer()`
- `db.php` auto-redirects salesman users away from all pages except `deliveries.php` / `delivery_view.php`
- Salesmen are scoped to their own customers by `salesman` name matching, and **cannot create new customers** (`pages/deliveries.php`) or access `pages/users.php` (admin-only)

## Site config (`includes/txt.php`)
Defines `$software_name`, `$company_name`, `$owner_name`, `$owner_address`, `$owner_phone`. Included by both `db.php` and `header.php`. **Also has its own `mysqli_connect` call** (line 2) — redundant connection.

## Quirks
- **Plain-text passwords** — auth compares `$_POST['password']` directly against DB (`login.php:14`); `users.php` writes password in plain text
- **No prepared statements** — SQL uses `mysqli_real_escape_string` string interpolation throughout
- **Paths** use auto-detected `$base_url`: `'../'` when in `pages/`, `''` otherwise (`header.php`)
- All CSS/JS is inline in PHP files — `assets/css/style.css` is loaded; `assets/js/script.js` is empty (unused)
- `error_log` files accumulate at root and in `pages/` (runtime errors)
- No build tools, tests, linters, or CI
- Utility scripts: `diagnose.php` (root, DB/schema health check), `database/voucher_migration.php` (idempotent, back-fills voucher numbers)

## Code generation helpers (`includes/db.php`)
- `generate_5digit_code()` — sequential 5-digit codes (10001, 10002…) for `customer_code`, `supplier_code`, `product_code`, `material_code`, `category_code`
- `generate_voucher_no()` — prefix + 5-digit sequence. **Voucher prefixes**: `SLS-` (water_deliveries), `PUR-` (raw_material_purchases), `RCP-` (customer_payments), `PAY-` (supplier_payments), `EXP-` (expenses)

## Testing
- No test framework — manual smoke test: log in, visit each `pages/` endpoint, verify CRUD. Verify as both `admin` and a `salesman` account

## Key schema notes
- **No route feature** — `routes` table and `customers.route_id` were removed (client requirement). **No block/area/bottle_rate** — `customers.block`, `customers.area`, `customers.bottle_rate` were also removed (client requirement): customers carry only `address` (free text) plus `salesman`. Bottle rate is entered manually per delivery at sale time (`pages/deliveries.php`), stored on `water_deliveries.bottle_rate` only
- **Deliveries** (`pages/deliveries.php`): when a customer is selected, shows salesman + previous balance + empties inline

## UI Conventions
- **"button ui"**: buttons that sit next to form inputs must match the input fields' size. Use `style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;"` so the button height, border-radius, and text alignment are identical to `form-control`. Apply `w-100` or `flex-fill` as needed for width.
- **Table action buttons**: use custom class `btn-xs` with `padding: 6px 10px; font-size: 12px; line-height: 1.3; border-radius: 6px;` for compact edit/delete buttons (e.g. `pages/users.php`).
- Brand/accent color is `#A04657` (burgundy) used across header, sidebar, login.
