# WP Dorna — Agent Guide

## Repo structure

```
wp-dorna.php                       # Plugin entrypoint, defines constants, hooks init
includes/
├── class-wp-dorna.php             # Core: cron sync, invoice creation, pricing
├── class-wp-dorna-admin.php       # Admin UI (Persian), AJAX import/sync, order meta box
└── class-wp-dorna-api.php         # HTTP client with Bearer auth
```

## Key facts

- **No build system, no tests, no CI, no linters.** Pure PHP. Only dependency is `yahnis-elsts/plugin-update-checker` via Composer.
- **Default branch is `development`.** Do not commit to `main` unless PR merging.
- **API base URL:** `https://my.dornaapp.ir/api/v1/` (set in `wp-dorna.php` constant `WP_DORNA_API_URL`). Auth via Bearer token stored in WordPress option `wp_dorna_settings` (`api_key` key).
- **Products matched by SKU** between Dorna and WooCommerce. No external ID mapping.
- **IRT currency:** amounts are multiplied/divided by 10 when converting between Dorna (Toman) and WooCommerce (Rial).
- **Cron:** `wp_dorna_update_products_event` fires every minute. Fetches products from Dorna since last update (`wp_dorna_last_product_update` option), updates prices and stock in WooCommerce.
- **Invoice auto-send:** triggers on `woocommerce_order_status_processing` / `woocommerce_order_status_completed`. Deduplication via `_dorna_invoice_sent` order meta. Manual resend button on order edit screen.
- **Error logs** stored in `logs/YYYY-MM-DD.log` (gitignored).
- **HPOS** (High-Performance Order Storage) compatibility declared in `wp-dorna.php`.
- **Auto-update** via GitHub releases using `plugin-update-checker`. No WordPress.org readme.txt — not distributed on wp.org.
- **Code duplication:** `apply_dorna_pricing()` and `log_error()` exist in both `WP_Dorna` and `WP_Dorna_Admin` — change with care.

## Commands

```sh
# Install dependencies (after clone)
composer install --no-dev

# Composer autoload dump (if adding classes)
composer dump-autoload
```

No other tooling exists.
