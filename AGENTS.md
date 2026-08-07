# Water Supply Management System

## Stack
- **PHP 8+** vanilla (no framework), **MySQL** via `mysqli_*`, Bootstrap 5.3.3, jQuery 3.7.1, Font Awesome 6.5.2
- DataTables jQuery plugin used on listing pages for search/sort/pagination
- Runs on **XAMPP** (localhost). DB: `water_supply_system`, user: `root`, pass: empty (`includes/db.php:3-6`)

## Setup
1. Create DB `water_supply_system` and import `database/schema.sql` (20 tables + default admin + expense categories)
2. Or hit `http://localhost/water_demo_copy/database/database.php` once
3. Login: `admin` / `admin123` at `login.php`

## Architecture
- **Entrypoints**: `index.php` (dashboard), `login.php`, `logout.php`
- **Feature pages**: 23 PHP files in `pages/`
- **Includes**: `db.php` (session + DB), `header.php` (layout + sidebar + mobile menu JS), `footer.php` (jQuery + Bootstrap JS), `txt.php` (site config strings)
- Auth guard: `$_SESSION['admin_logged_in']` at top of every page

## Site config (`includes/txt.php`)
Defines `$software_name`, `$company_name`, `$owner_name`, `$owner_address`, `$owner_phone`. Included by both `db.php` and `header.php`. **Also has its own `mysqli_connect` call** (line 2) — redundant connection.

## Quirks
- **Plain-text passwords** — auth compares `$_POST['password']` directly against DB (`login.php:14`)
- **No prepared statements** — SQL uses `mysqli_real_escape_string` string interpolation throughout
- **Paths** use auto-detected `$base_url`: `'../'` when in `pages/`, `''` otherwise (`header.php:20-24`)
- All CSS/JS is inline in PHP files — `assets/css/style.css` is loaded; `assets/js/script.js` is empty (unused)
- `error_log` files accumulate at root and in `pages/` (runtime errors)
- No build tools, tests, linters, or CI

## Testing
- No test framework — manual smoke test: log in, visit each `pages/` endpoint, verify CRUD

## Key schema notes
- **No route feature** — `routes` table and `customers.route_id` were removed (client requirement). **No block/area/bottle_rate** — `customers.block`, `customers.area`, `customers.bottle_rate` were also removed (client requirement): customers carry only `address` (free text) plus `salesman`. Bottle rate is entered manually per delivery at sale time (`pages/deliveries.php`), stored on `water_deliveries.bottle_rate` only
- **Deliveries** (`pages/deliveries.php`): when a customer is selected, shows salesman + previous balance + empties inline

## UI Conventions
- **"button ui"**: buttons that sit next to form inputs must match the input fields' size. Use `style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;"` so the button height, border-radius, and text alignment are identical to `form-control`. Apply `w-100` or `flex-fill` as needed for width.
- **Table action buttons**: use custom class `btn-xs` with `padding: 6px 10px; font-size: 12px; line-height: 1.3; border-radius: 6px;` for compact edit/delete buttons (e.g. `pages/users.php`).
