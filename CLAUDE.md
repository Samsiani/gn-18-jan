# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Custom WooCommerce Invoice Generator (CIG)** is a WordPress plugin (PHP 7.4+, WooCommerce 5.0+, WordPress 5.8+) that provides a full invoice management system for GN Industrial (gn.ge). It combines a classic PHP/AJAX backend with a Vue 3 SPA frontend.

**Version:** `4.1.1` — defined in the plugin header and as `CIG_VERSION` constant in `custom-woocommerce-invoice-generator.php`. Update both places when bumping.

**Two distinct UIs coexist:**
1. **Legacy PHP UI** — shortcodes (`[invoice_generator]`, `[products_stock_table]`, `[invoice_accountant_dashboard]`) rendered via WP templates, driven by jQuery + WP AJAX.
2. **Vue 3 SPA** — shortcode `[cig_vue_app]` mounts `<div id="app">` and loads `dist/gn-invoice.js` + `dist/gn-invoice.css` as an ES module. The SPA talks to the REST API at `cig/v1/`.

## Architecture

### Entry Point
`custom-woocommerce-invoice-generator.php` — defines all constants, bootstraps the singleton `CIG_Invoice_Generator`, loads every class, registers activation/deactivation hooks.

### Singleton Pattern
```php
CIG()           // global accessor → CIG_Invoice_Generator::instance()
CIG()->logger   // CIG_Logger
CIG()->cache    // CIG_Cache
CIG()->stock    // CIG_Stock_Manager
CIG()->invoice  // CIG_Invoice (legacy)
CIG()->invoice_manager  // CIG_Invoice_Manager (v4 custom tables)
CIG()->rest_api // CIG_Rest_API
```

### Directory Structure
```
custom-woocommerce-invoice-generator.php  ← main plugin file
includes/
  class-cig-core.php           ← asset enqueuing, Vue shortcode, cart hooks
  class-cig-invoice-manager.php ← CRUD on wp_cig_invoices custom tables (v4)
  class-cig-invoice.php        ← legacy post-meta invoice logic
  class-cig-stock-manager.php  ← stock reservation + WC stock filters
  class-cig-logger.php
  class-cig-cache.php          ← WP Object Cache + transient fallback
  class-cig-validator.php
  class-cig-security.php
  class-cig-db-installer.php
  class-cig-settings.php
  class-cig-statistics.php
  class-cig-accountant.php
  class-cig-customers.php
  class-cig-admin-columns.php
  class-cig-admin-portal.php
  class-cig-user-restrictions.php
  class-cig-updater.php
  ajax/                        ← WP AJAX handlers (legacy UI)
    class-cig-ajax-invoices.php
    class-cig-ajax-products.php
    class-cig-ajax-statistics.php
    class-cig-ajax-customers.php
    class-cig-ajax-dashboard.php
  api/                         ← REST API (Vue SPA)
    class-cig-rest-api.php     ← base class, registers namespace cig/v1
    class-cig-rest-invoices.php
    class-cig-rest-customers.php
    class-cig-rest-products.php
    class-cig-rest-dashboard.php
  database/
    class-cig-database.php
  dto/
    class-cig-invoice-item-dto.php
  migration/
    class-cig-migrator.php     ← v1 postmeta → v2 custom tables
  services/
    class-cig-invoice-service.php
assets/
  css/  js/  img/              ← legacy UI assets
dist/
  gn-invoice.js                ← compiled Vue 3 SPA (ES module)
  gn-invoice.css               ← compiled Vue 3 SPA styles
  assets/                      ← Vite code-split chunks
  index.html                   ← standalone dev reference
templates/                     ← PHP templates for legacy shortcodes
tests/
  bootstrap.php                ← PHPUnit bootstrap (WP stubs)
  InvoiceManagerTest.php
  StockManagerTest.php
```

## Database

The plugin uses **custom tables** (v4+) alongside legacy WP post meta.

| Table | Purpose |
|-------|---------|
| `{prefix}cig_invoices` | Invoice header rows |
| `{prefix}cig_invoice_items` | Line items per invoice |
| `{prefix}cig_payments` | Payment records per invoice |
| `{prefix}cig_customers` | Customer directory |

`CIG_DB_Installer::install()` runs `dbDelta()` on activation. To add a column: increment the version check in `CIG_DB_Installer` and add the `ALTER TABLE` inside `maybe_update_schema()`.

Legacy invoices are stored as `invoice` custom post type with postmeta keys prefixed `_cig_*`. `CIG_Migrator` migrates them to custom tables on activation.

## Vue SPA Integration

### How it loads
`CIG_Core::enqueue_front_assets()` detects the `[cig_vue_app]` shortcode on the current page and:
1. Enqueues `dist/gn-invoice.css` as `cig-vue-app`
2. Enqueues `dist/gn-invoice.js` as `cig-vue-app` (footer, `true`)
3. Patches the script tag to `type="module"` via `script_loader_tag` filter
4. Strips all other theme/plugin styles and scripts (`wp_enqueue_scripts` at `PHP_INT_MAX`)
5. Localises `cigGlobal` with `restUrl`, `nonce`, `isLoggedIn`, `currentUser`

### Building the SPA
The Vue source lives in a **separate repository**: `Documents/Migration/gn-invoice-vue/`.

```bash
cd Documents/Migration/gn-invoice-vue
npm run build          # outputs to dist/
# then copy dist/ into this plugin repo:
cp -R dist/ ../GIT/gn-industrial-custom-invoice-generator/dist/
```

**Never hand-edit files in `dist/`** — they are compiled output.

### REST API
All endpoints are under `cig/v1/`. Authentication uses WP nonces (`wp_rest`).

| Class | Routes |
|-------|--------|
| `CIG_Rest_Invoices` | `GET/POST /invoices`, `GET/PUT/DELETE /invoices/{id}` |
| `CIG_Rest_Customers` | `GET/POST /customers`, `GET/PUT/DELETE /customers/{id}` |
| `CIG_Rest_Products` | `GET /products` |
| `CIG_Rest_Dashboard` | `GET /dashboard` |

## Stock Reservation System

`CIG_Stock_Manager` uses `_cig_reserved_stock` post meta on WC products to track reservations without deducting WC stock:

```php
// Structure of _cig_reserved_stock postmeta (array keyed by invoice_id):
[
  42 => ['qty' => 3, 'expires' => '2025-03-01 00:00:00', 'invoice_date' => '...'],
  55 => ['qty' => 1, 'expires' => '',  'invoice_date' => '...'],
]
```

- **Reserved** items: appear in `_cig_reserved_stock` meta, WC filters subtract them from displayed stock
- **Sold** items: deducted from actual WC stock via `wc_update_product_stock()`
- **Fictive** items (status `none`): ignored entirely — no reservation, no stock deduction

Expired entries are cleaned up by the `cig_check_expired_reservations` hourly cron.

## Key Constants

| Constant | Value | Purpose |
|----------|-------|---------|
| `CIG_VERSION` | `'4.1.1'` | Plugin version (update in header + constant) |
| `CIG_PLUGIN_DIR` | `plugin_dir_path(__FILE__)` | Absolute path to plugin root |
| `CIG_INCLUDES_DIR` | `CIG_PLUGIN_DIR . 'includes/'` | |
| `CIG_ASSETS_URL` | `CIG_PLUGIN_URL . 'assets/'` | Legacy assets URL |
| `CIG_CACHE_GROUP` | `'cig_cache'` | WP Object Cache group |
| `CIG_CACHE_EXPIRY` | `900` | 15 minutes |
| `CIG_INVOICE_NUMBER_PREFIX` | `'N'` | e.g. `N25000001` |
| `CIG_INVOICE_NUMBER_BASE` | `25000000` | Starting invoice number base |
| `CIG_DEFAULT_RESERVATION_DAYS` | `30` | |

## Testing

### Backend — PHPUnit
```bash
composer install
./vendor/bin/phpunit --testdox
# 82 tests, all green
```

Tests use **Brain Monkey** to mock WP functions and **Mockery** to mock `$wpdb`. No WordPress installation or database needed. `tests/bootstrap.php` defines all required WP constants and stubs `WP_Error`, `WP_User`, `WP_REST_Request`, `WP_REST_Response`, `WP_REST_Server`.

| Test file | Tests | Coverage |
|-----------|-------|----------|
| `InvoiceManagerTest.php` | 7 | `CIG_Invoice_Manager` CRUD |
| `StockManagerTest.php` | 6 | `CIG_Stock_Manager` reservation logic |
| `RestApiTest.php` | 12 | `CIG_Rest_API` — auth endpoints, `format_user`, `get_cig_role` |
| `RestInvoicesLogicTest.php` | 34 | `CIG_Rest_Invoices` private helpers via Reflection |
| `RestDashboardTest.php` | 13 | `CIG_Rest_Dashboard` — settings, accountant endpoints, permission callbacks |
| `DbSchemaTest.php` | 10 | Static analysis: all columns in `CIG_DB_Installer` SQL |

**Rules:**
- Never stub `get_post_meta` globally in `setUp()` — it blocks per-test `Functions\expect()`. Use `Functions\when()` per test instead.
- `$wpdb->get_row(..., ARRAY_A)` mocks must return plain PHP arrays, not `stdClass` objects.
- `ARRAY_A`, `ARRAY_N`, `OBJECT` constants are defined in `tests/bootstrap.php`.
- Never mix `Functions\stubs()` + `Functions\expect()` for the same function in one test. Use `Functions\when()->alias(fn)` to capture call arguments instead.
- `Functions\when()` overrides a `Functions\stubs()` stub registered in `setUp()` — use this to vary per-test return values.
- `WP_Error` stub in `bootstrap.php` exposes `get_error_code()`, `get_error_message()`, and `get_error_data()`. If a new test needs `get_error_data()`, it is already available.
- Private methods are tested via `ReflectionMethod::setAccessible(true)` — no need to change visibility in production code.
- When adding DB transactions to a method under test, add `$this->wpdb->shouldReceive('query')->andReturn(true)` to cover `START TRANSACTION` / `COMMIT` / `ROLLBACK` calls.

### Frontend — Vitest (in the Vue SPA repo)
```bash
cd Documents/Migration/gn-invoice-vue
npm test
# 23 tests, all green
```

## Invoice Status Model

| Field | Values | Notes |
|-------|--------|-------|
| `status` | `standard` \| `fictive` | Invoice type |
| `lifecycle_status` | `draft` \| `active` \| `completed` \| `sold` | Workflow stage |
| `item_status` | `none` \| `reserved` \| `canceled` \| `sold` | Per line-item |

- `fictive` invoices: `item_status` locked to `none`, payments not allowed, no stock interaction
- `completed`/`sold` lifecycle: treated identically as "Sold"
- `sale_date` = activation date (set when `standard`); `sold_date` = formal sale date (warranty certificate)

## DB Schema Notes

**Adding columns to `wp_cig_invoices`:** Edit `CIG_DB_Installer::create_invoices_table()` and add the column. `dbDelta()` will add missing columns on the next plugin activation. Always add the matching column to `format_invoice()` in `CIG_Rest_Invoices` so the API returns it.

**Known bug fixed (v4.1.1 → v4.1.2):** The following 6 columns were referenced by the REST API but were missing from the CREATE TABLE statement, causing silent MySQL errors on every Accountant-page PATCH and NULL reads on every GET:
- `is_credit_checked`, `is_receipt_checked`, `is_corrected`
- `accountant_note`, `rs_uploaded_by`, `rs_uploaded_date`

**Security fix (v4.1.2):** `PATCH /invoices/{id}/accountant-status` and `PATCH /invoices/{id}/accountant-note` were guarded by `require_login()` (any authenticated user). Now guarded by `require_accountant_or_woocommerce()` which requires `administrator`, `manage_woocommerce`, or `cig_accountant_access` capability.

**Atomicity fix (v4.1.2):** `CIG_Invoice_Manager::create_invoice()` and `update_invoice()` now wrap all write operations in a MySQL transaction (`START TRANSACTION` / `COMMIT` / `ROLLBACK`). A failed item or payment insert no longer leaves an orphaned invoice row.

**Localisation fix (v4.1.2):** `get_accountant_invoices()` payment method labels were hardcoded in Georgian. Replaced with English (`Company Transfer`, `Cash`, `Consignment`, `Credit`, `Other`).

**Known limitation:** `cig_customers.tax_id` is indexed with a plain `KEY`, not `UNIQUE KEY`. Duplicate tax IDs can be inserted if `sync_customer()` encounters a race condition.

## Common Operations

**Add a new REST endpoint:**
1. Create `includes/api/class-cig-rest-{name}.php` extending `WP_REST_Controller`
2. Register routes in `register_routes()` hooked on `rest_api_init`
3. `require_once` it in `load_dependencies()` in `custom-woocommerce-invoice-generator.php`
4. Instantiate in `init_components()`: `$this->rest_{name} = new CIG_Rest_{Name}();`

**Add a new AJAX handler:**
1. Create `includes/ajax/class-cig-ajax-{name}.php`
2. Register `wp_ajax_cig_{action}` hooks in its constructor
3. `require_once` + instantiate in the main plugin file (same pattern as above)

**Change the invoice number format:**
Edit `CIG_INVOICE_NUMBER_PREFIX` and `CIG_INVOICE_NUMBER_BASE` constants in `custom-woocommerce-invoice-generator.php`.

## CRITICAL: Do Not Break Vue Integration

`custom-woocommerce-invoice-generator.php` and `includes/class-cig-core.php` contain the complete Vue SPA setup. When modifying `class-cig-core.php`:
- Keep the `[cig_vue_app]` shortcode registration in the constructor
- Keep the `dist/gn-invoice.js` + `dist/gn-invoice.css` enqueue block intact
- Keep the `script_loader_tag` filter that adds `type="module"` to the script tag
- Keep the `PHP_INT_MAX` style/script stripping block — the Vue SPA is fully self-contained and must not inherit theme CSS
