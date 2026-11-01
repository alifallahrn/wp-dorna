# WP Dorna — Agent Guide

## Repo structure

```
wp-dorna.php                       # Plugin entrypoint: constants, autoloader, hooks Core\Plugin::boot()
src/                               # namespace WPDorna\ (see CLAUDE.md for what each folder does)
├── Core/    Plugin, Services (wiring), Settings, Scheduler, Lifecycle
├── Api/     Client (Bearer HTTP client with retry)
├── Sync/    ProductWriter, IncrementalSync, FullSync, SingleSync, Status
├── Orders/  InvoiceService, InvoiceState, RetryService
├── Import/  ImportService
├── Admin/   Admin, Ajax, Assets, Order/Product integration, Pages/*
└── Support/ Sku, Money, Backoff, Time, Lock, Logger
templates/                         # admin views (layout, pages/, partials/)
assets/                            # css/admin.css, js/admin.js, fonts/Vazirmatn.woff2
tests/                             # run.php (pure unit), integration.php (needs a dev WP site)
uninstall.php                      # removes data only if the setting says so
```

## Key facts

- **No build system, no CI, no linters.** Pure PHP 7.4+. Only dependency is `yahnis-elsts/plugin-update-checker` via Composer (`vendor/` is committed). Files use CRLF line endings.
- **Default branch is `development`.** Do not commit to `main` unless PR merging.
- **API base URL:** `https://my.dornaapp.ir/api/v1/` (constant `WP_DORNA_API_URL`). Auth via Bearer token stored in option `wp_dorna_settings` (`api_key`) or the `WP_DORNA_API_KEY` constant.
- **Products matched by SKU** between Dorna and WooCommerce (normalized: trim, Persian/Arabic digits, case). No external ID mapping.
- **IRT currency:** amounts are multiplied/divided by 10 when converting between Dorna and WooCommerce (`Support\Money`).
- **Cron:** `wp_dorna_update_products_event` every minute fetches what changed since the last *successful* run (5-minute overlap); `wp_dorna_retry_failed_orders_event` every 5 minutes; a daily log purge. The **full sync** (admin tab "سینک کامل") is a paced pass over all Dorna products and pauses the minute sync while it runs.
- **Invoice auto-send:** `woocommerce_order_status_processing` / `_completed`. Deduplication via `_dorna_invoice_sent` order meta; failures keep `_dorna_invoice_error` / `_attempts` and are retried with backoff for 7 days. Manual resend: order box and the "سفارش‌ها" screen.
- **Logs:** `wp-content/uploads/wp-dorna-logs/YYYY-MM-DD.log` (14 days, token redacted), viewable under "گزارش‌ها".
- **HPOS** compatibility is declared in `wp-dorna.php`. WooCommerce is required (the plugin shows a notice and stays idle without it).
- **Auto-update** via GitHub releases using `plugin-update-checker`. No WordPress.org readme.txt.

## Commands

```sh
# Install dependencies (after clone)
composer install --no-dev

# Tests (classes are autoloaded by wp-dorna.php, no composer dump needed)
php tests/run.php
WP_DORNA_TESTS=1 php tests/integration.php   # disposable dev WordPress site only
```

No other tooling exists.
