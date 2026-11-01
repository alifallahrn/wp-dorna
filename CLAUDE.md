# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

WordPress/WooCommerce plugin (Persian UI, RTL) that connects a store to the Dorna platform (`https://my.dornaapp.ir/api/v1/`, constant `WP_DORNA_API_URL`): Dorna → store for product price and stock, store → Dorna for order invoices. PHP 7.4+, no build step. The only Composer dependency is `yahnis-elsts/plugin-update-checker` (GitHub-release auto-update); `vendor/` is committed. Source files are CRLF — keep them that way.

```sh
composer install --no-dev                      # after clone
php tests/run.php                              # unit tests for the pure helpers, no WordPress needed
WP_DORNA_TESTS=1 php tests/integration.php     # loads the local WP site (../../../../wp-load.php), fake HTTP, temp data; disposable dev site only
```

The default branch is `development`; don't commit to `main` except when merging a PR.

## Architecture

`wp-dorna.php` only defines constants, a PSR-4 style autoloader (`WPDorna\` → `src/`) and hooks `Core\Plugin::boot()`. `boot()` does nothing but show a notice when WooCommerce is missing, so cron, hooks and screens never run without it. `Core\Services` wires every collaborator by constructor injection.

- `Api\Client` — Bearer-token HTTP client (every request also sends `X-Dorna-Site` = the site origin and `X-Dorna-Plugin`; Dorna binds the token to that address, so a staging copy that inherited the token is refused): 30s timeout, GET retried on network errors/429/5xx (honours `Retry-After`), POST never retried. Errors are `WP_Error` carrying the HTTP `status`; 401/403/429/5xx get a Persian message instead of the server's English body.
- `Sync\` — `ProductWriter` is the only place that writes Dorna data into WooCommerce (matching by normalized SKU; used by every other class here). `IncrementalSync` is the minute cron (`?since=` with a 5-minute overlap; the synced-until time only advances when **every** page succeeded and is taken from Dorna's `server_time`). `FullSync` is the paced full pass (state in an option, driven by WP-Cron plus the open admin tab, pausable/resumable). `SingleSync` is the product screen button.
- `Orders\` — `InvoiceService` sends an order once (per-order lock), `InvoiceState` owns the order meta keys (`_dorna_invoice_*`, kept from older versions), `RetryService` re-sends with `Support\Backoff` (5 min doubling to 6 h, 7-day window).
- `Connect\` — `ConnectionService` is the "connect to Dorna" button flow (OAuth-like, PKCE): the browser goes to `<Dorna>/connect/woocommerce?site&return&state&challenge`, the owner signs in and confirms, Dorna returns `?code&state`, and the code is swapped for the token **server to server** (`Api\Client::exchange`), so the token never appears in a URL. `Core\Connection` stores how the site is linked (`connected` | `manual` | `rejected` | `mismatch`; the client sets it from 401 / 403 `site_mismatch`). `Heartbeat` posts a health report (counts and states only, no customer data) every 5 minutes; Dorna shows it in its dashboard card and raises the alerts.
- `Sync\ScheduledFullSync` + `Support\SchedulePlan` — nightly/weekly full sync at an hour of the site's day; a due time is honoured late but only inside a 6 h window, and not when a pass began < 12 h ago. `Core\Wizard` is the first-run checklist (installs that predate it count as set up).
- `Import\ImportService` — scan pages into a per-user transient, import by SKU from that list (the browser never sends product data); drafts only.
- `Admin\` — `Admin` (top-level "درنا" menu, six `Pages\*`), `Ajax` (every handler: nonce `wp_dorna_admin` + capability, then a service call), `Assets`, `OrderIntegration` (list column + box, HPOS and legacy), `ProductIntegration`. Views are in `templates/` (escaped there); `assets/css/admin.css` is the scoped `.wpd` design system (Dorna panel look: flat white cards, `#E5E7EB` lines, `#2563EB` accent, bundled Vazirmatn); `assets/js/admin.js` is vanilla JS, one module per screen.
- `Support\` — `Sku`, `Money`, `Backoff`, `SchedulePlan`, `Site` are pure; `Lock` (atomic via `add_option`), `Logger` (daily files in `uploads/wp-dorna-logs/`, 14 days, token redacted, closed to the web), `Time`.

## Gotchas

- **Dorna's `since` is `products.updated_at`.** Stock lives in `inventory_transactions`, so Dorna must touch the product on every stock change (its `InventoryTransactionObserver` does). Never rely on `updated_at` alone for correctness: the full sync is the safety net.
- **Stock held for unsent orders.** Woo reduces stock when an order is placed, Dorna only after the invoice arrives; `ProductWriter` skips the stock of products in recent orders without `_dorna_invoice_sent` so a stale Dorna number does not put sold units back.
- **IRT currency:** amounts are ×10 towards Dorna and ÷10 from it — always through `Support\Money`.
- Dorna answers a repeated `order_id` with the existing `invoice_id` (older versions: HTTP 400 "already exists"); both count as sent.
- Options / meta / cron hook names are kept from earlier versions (`wp_dorna_settings`, `wp_dorna_update_products_event`, ...): changing them breaks installed sites.
- `WP_DORNA_API_URL` (wp-dorna.php) is also where the connect page is derived from (`WP_DORNA_APP_URL` overrides it); a local Dorna is used by pointing it at the dev server. Dorna only accepts a local site address (http, localhost, IP, .test) outside production.
- The token can be fixed with `define('WP_DORNA_API_KEY', '...')` in wp-config.php; the settings field is never pre-filled and an empty submit keeps the stored token. Uninstall removes data only when the "remove data" setting is on.
- Products are matched by SKU only, no external ID mapping. Weighed products (Dorna `quantity_unit`) are not supported.
