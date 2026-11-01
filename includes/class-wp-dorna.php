<?php

class WP_Dorna
{
    const LAST_UPDATE_OPTION = 'wp_dorna_last_product_update';
    const SYNC_STATUS_OPTION = 'wp_dorna_sync_status';

    // `since` is sent this far in the past: Dorna stamps updated_at inside a
    // transaction, so a change can become visible after a sync already passed it.
    const SYNC_OVERLAP_SECONDS = 300;
    const MAX_SYNC_PAGES = 500;
    const PAGE_DELAY_MICROSECONDS = 500000;

    // Orders without an invoice in Dorna are retried for a week, with backoff.
    const RETRY_WINDOW = 7 * DAY_IN_SECONDS;
    const RETRY_BATCH = 15;
    const RETRY_MAX_DELAY = 6 * HOUR_IN_SECONDS;

    // Woo has already reduced the stock for these, Dorna does not know yet.
    const PENDING_ORDER_WINDOW = 3 * DAY_IN_SECONDS;

    public function init()
    {
        add_filter('cron_schedules', function ($schedules) {
            $schedules['every_minute'] = array(
                'interval' => 60,
                'display'  => __('Every Minute'),
            );
            $schedules['every_five_minutes'] = array(
                'interval' => 300,
                'display'  => __('Every 5 Minutes'),
            );
            return $schedules;
        });

        $this->schedule_product_updates();
        $this->schedule_retry_cron();

        add_action('wp_dorna_update_products_event', array($this, 'update_products'));
        add_action('wp_dorna_retry_failed_orders_event', array($this, 'retry_failed_orders'));
        add_action('woocommerce_order_status_processing', array($this, 'create_invoice_in_dorna'));
        add_action('woocommerce_order_status_completed', array($this, 'create_invoice_in_dorna'));
    }

    public function schedule_product_updates()
    {
        if (!wp_next_scheduled('wp_dorna_update_products_event')) {
            wp_schedule_event(time(), 'every_minute', 'wp_dorna_update_products_event');
        }
    }

    public function schedule_retry_cron()
    {
        if (!wp_next_scheduled('wp_dorna_retry_failed_orders_event')) {
            wp_schedule_event(time(), 'every_five_minutes', 'wp_dorna_retry_failed_orders_event');
        }
    }

    /**
     * Same SKU spelled differently (spaces, Persian digits, case) must match.
     */
    public static function normalize_sku($sku)
    {
        $sku = trim((string) $sku);
        $sku = strtr($sku, array(
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ));

        return function_exists('mb_strtolower') ? mb_strtolower($sku) : strtolower($sku);
    }

    /**
     * Atomic through add_option (same approach as WP core's upgrade lock). A lock
     * older than $ttl is considered dead.
     */
    private function acquire_lock($name, $ttl)
    {
        $key = 'wp_dorna_lock_' . $name;
        $now = time();

        if (add_option($key, $now, '', 'no')) {
            return true;
        }

        $started = (int) get_option($key);
        if ($started && ($now - $started) > $ttl) {
            update_option($key, $now, false);
            return true;
        }

        return false;
    }

    private function release_lock($name)
    {
        delete_option('wp_dorna_lock_' . $name);
    }

    private function update_sync_status(array $data)
    {
        $status = get_option(self::SYNC_STATUS_OPTION, array());
        if (!is_array($status)) {
            $status = array();
        }

        update_option(self::SYNC_STATUS_OPTION, array_merge($status, $data), false);
    }

    private function now_iso()
    {
        return (new DateTimeImmutable('now', wp_timezone()))->format(DATE_ATOM);
    }

    /**
     * The stored value is either the ISO time returned by Dorna (has an offset) or
     * a legacy site-local "Y-m-d H:i:s". Both end up as an ISO string with offset.
     */
    private function build_since($stored)
    {
        if (empty($stored)) {
            return null;
        }

        try {
            $time = new DateTimeImmutable($stored, wp_timezone());
        } catch (Exception $e) {
            return null;
        }

        return $time->modify('-' . self::SYNC_OVERLAP_SECONDS . ' seconds')->format(DATE_ATOM);
    }

    public function update_products()
    {
        if (!$this->acquire_lock('products', 10 * MINUTE_IN_SECONDS)) {
            return;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        try {
            $this->run_product_sync();
        } catch (Throwable $e) {
            $this->fail_product_sync('Unexpected error: ' . $e->getMessage());
        } finally {
            $this->release_lock('products');
        }
    }

    private function fail_product_sync($message)
    {
        $this->log_error('WP Dorna product sync FAILED: ' . $message);
        $this->update_sync_status(array(
            'last_attempt' => $this->now_iso(),
            'last_error'   => $message,
        ));
    }

    private function run_product_sync()
    {
        $api         = new WP_Dorna_API();
        $since       = $this->build_since(get_option(self::LAST_UPDATE_OPTION, null));
        $fallback_at = $this->now_iso();
        $server_time = null;

        $dorna_products = array();
        $inactive       = array();
        $page           = 1;

        do {
            $query = array('page' => $page, 'include_inactive' => 1);
            if ($since) {
                $query['since'] = $since;
            }

            $response = $api->get_data($api::PRODUCTS_ENDPOINT . '?' . http_build_query($query));

            // The last-sync time must NOT advance when a page failed, or the
            // changes in that window are lost for good.
            if (is_wp_error($response)) {
                $this->fail_product_sync(sprintf('page %d: %s', $page, $response->get_error_message()));
                return;
            }

            if (!isset($response['data']) || !is_array($response['data'])) {
                $this->fail_product_sync(sprintf('page %d: invalid response format', $page));
                return;
            }

            if ($server_time === null && !empty($response['server_time'])) {
                $server_time = $response['server_time'];
            }

            foreach ($response['data'] as $product) {
                $key = self::normalize_sku($product['sku'] ?? '');
                if ($key === '') {
                    continue;
                }

                if (isset($product['is_active']) && !$product['is_active']) {
                    $inactive[$key] = $product['sku'];
                    continue;
                }

                $dorna_products[$key] = $product;
            }

            $has_more = isset($response['current_page'], $response['last_page']) && $response['current_page'] < $response['last_page'];
            $page++;

            if ($has_more) {
                usleep(self::PAGE_DELAY_MICROSECONDS);
            }
        } while ($has_more && $page <= self::MAX_SYNC_PAGES);

        $stats = array('fetched' => count($dorna_products), 'updated' => 0, 'failed' => 0, 'stock_held' => 0, 'inactive' => 0);

        if (!empty($dorna_products) || !empty($inactive)) {
            $woo_ids = $this->get_woo_ids_by_sku();

            foreach ($inactive as $key => $sku) {
                if (isset($woo_ids[$key])) {
                    $stats['inactive']++;
                    $this->log_error(sprintf('WP Dorna: product SKU %s is inactive in Dorna but still exists in the store (not changed).', $sku));
                }
            }

            $pending_product_ids = !empty($dorna_products) ? $this->get_pending_invoice_product_ids() : array();
            $currency            = get_woocommerce_currency();

            foreach ($dorna_products as $key => $product) {
                if (!isset($woo_ids[$key])) {
                    continue;
                }

                foreach ($woo_ids[$key] as $woo_id) {
                    // Woo already reduced the stock for an order Dorna has not received
                    // yet: Dorna's older number would put the sold units back.
                    $hold_stock = isset($pending_product_ids[$woo_id]);

                    try {
                        $wc_product = wc_get_product($woo_id);
                        if (!$wc_product) {
                            continue;
                        }

                        $this->apply_dorna_pricing($wc_product, $product, $currency);

                        if ($hold_stock) {
                            $stats['stock_held']++;
                        } elseif (isset($product['stock']) && is_numeric($product['stock'])) {
                            $wc_product->set_manage_stock(true);
                            $wc_product->set_stock_quantity(wc_stock_amount($product['stock']));
                        }

                        $wc_product->save();
                        $stats['updated']++;
                    } catch (Throwable $e) {
                        $stats['failed']++;
                        $this->log_error(sprintf('WP Dorna: updating SKU %s (product #%d) failed: %s', $product['sku'], $woo_id, $e->getMessage()));
                    }
                }
            }
        }

        $synced_at = $server_time ? $server_time : $fallback_at;
        update_option(self::LAST_UPDATE_OPTION, $synced_at);

        $this->update_sync_status(array(
            'last_attempt' => $this->now_iso(),
            'last_success' => $synced_at,
            'last_error'   => null,
            'last_stats'   => $stats,
        ));
    }

    /**
     * Normalized SKU => [post ids] for every live product / variation.
     */
    private function get_woo_ids_by_sku()
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value AS sku
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_sku'
              AND pm.meta_value <> ''
              AND p.post_type IN ('product', 'product_variation')
              AND p.post_status <> 'trash'"
        );

        $map = array();
        foreach ((array) $rows as $row) {
            $key = self::normalize_sku($row->sku);
            if ($key === '') {
                continue;
            }
            $map[$key][] = (int) $row->post_id;
        }

        foreach ($map as $key => $ids) {
            if (count($ids) > 1) {
                $this->log_error(sprintf('WP Dorna: SKU "%s" is used by more than one product (%s).', $key, implode(', ', $ids)));
            }
        }

        return $map;
    }

    /**
     * Product / variation ids (as array keys) of recent orders whose stock Woo
     * already reduced but whose invoice Dorna has not received.
     */
    private function get_pending_invoice_product_ids()
    {
        $orders = wc_get_orders(array(
            'status'       => array('on-hold', 'processing', 'completed'),
            'limit'        => 200,
            'date_created' => '>' . (time() - self::PENDING_ORDER_WINDOW),
            'meta_key'     => '_dorna_invoice_sent',
            'meta_compare' => 'NOT EXISTS',
        ));

        $ids = array();
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $ids[(int) $item->get_product_id()] = true;
                if ($item->get_variation_id()) {
                    $ids[(int) $item->get_variation_id()] = true;
                }
            }
        }

        return $ids;
    }

    public function retry_failed_orders()
    {
        if (!$this->acquire_lock('retry', 10 * MINUTE_IN_SECONDS)) {
            return;
        }

        try {
            $orders = wc_get_orders(array(
                'status'       => array('processing', 'completed'),
                'limit'        => 100,
                'orderby'      => 'date',
                'order'        => 'ASC',
                'date_created' => '>' . (time() - self::RETRY_WINDOW),
                'meta_key'     => '_dorna_invoice_sent',
                'meta_compare' => 'NOT EXISTS',
            ));

            $sent = 0;
            foreach ($orders as $order) {
                if ($sent >= self::RETRY_BATCH) {
                    break;
                }

                if (!$this->is_retry_due($order)) {
                    continue;
                }

                $this->create_invoice_in_dorna($order->get_id());
                $sent++;

                sleep(1);
            }
        } finally {
            $this->release_lock('retry');
        }
    }

    // 5 min after the first failure, then doubling, capped at 6 hours.
    private function is_retry_due($order)
    {
        $attempts = (int) $order->get_meta('_dorna_invoice_attempts');
        if ($attempts < 1) {
            return true;
        }

        $delay = min(self::RETRY_MAX_DELAY, 5 * MINUTE_IN_SECONDS * pow(2, $attempts - 1));

        return (time() - (int) $order->get_meta('_dorna_invoice_last_attempt')) >= $delay;
    }

    private function record_invoice_failure($order, $message)
    {
        $order->update_meta_data('_dorna_invoice_error', $message);
        $order->update_meta_data('_dorna_invoice_attempts', (int) $order->get_meta('_dorna_invoice_attempts') + 1);
        $order->update_meta_data('_dorna_invoice_last_attempt', time());
        $order->save();
    }

    public function create_invoice_in_dorna($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order || $order->get_meta('_dorna_invoice_sent')) {
            return;
        }

        // A timed out request can still be running on the Dorna side while the
        // retry cron fires; one in-flight request per order.
        $lock = 'invoice_' . $order_id;
        if (!$this->acquire_lock($lock, 2 * MINUTE_IN_SECONDS)) {
            return;
        }

        try {
            $this->send_invoice($order);
        } finally {
            $this->release_lock($lock);
        }
    }

    private function send_invoice($order)
    {
        $order_id = $order->get_id();
        $api      = new WP_Dorna_API();

        $currency = get_woocommerce_currency();

        $state_code = $order->get_billing_state();
        $country    = $order->get_billing_country();
        $states     = WC()->countries->get_states($country);
        $state_name = isset($states[$state_code]) ? $states[$state_code] : $state_code;

        $city_name  = function_exists('pw_get_city_name')
            ? pw_get_city_name($state_code, $order->get_billing_city())
            : $order->get_billing_city();

        $invoice_data = array(
            'customer' => [
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'mobile' => $order->get_billing_phone() ?: null,
                'email' => $order->get_billing_email(),
                'address' => implode(' - ', array_filter([
                    $state_name,
                    $city_name,
                    $order->get_billing_address_1(),
                    $order->get_billing_postcode() ?: null,
                ])),
            ],
            'items' => array(),
            'discount' => ($currency == 'IRT') ? ($order->get_total_discount() * 10) : $order->get_total_discount(),
            'shipping_cost' => ($currency == 'IRT') ? ($order->get_shipping_total() * 10) : $order->get_shipping_total(),
            'tax' => ($currency == 'IRT') ? ($order->get_total_tax() * 10) : (float) $order->get_total_tax(),
            'sub_total' => ($currency == 'IRT') ? ($order->get_subtotal() * 10) : $order->get_subtotal(),
            'total' => ($currency == 'IRT') ? ($order->get_total() * 10) : $order->get_total(),
            'order_id' => $order->get_id(),
            'order_status' => $order->get_status(),
            'order_note' => $order->get_customer_note(),
            'payment_method' => $order->get_payment_method_title(),
            'transaction_id' => $order->get_transaction_id(),
        );

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if (! $product) {
                $this->log_error(sprintf('WP Dorna: order #%d item "%s" has no product anymore and was left out of the invoice.', $order_id, $item->get_name()));
                continue;
            }

            // Dorna rejects the WHOLE invoice when one item has no SKU.
            if (trim((string) $product->get_sku()) === '') {
                $message = sprintf('کالای «%s» کد کالا (SKU) ندارد.', $item->get_name());
                $this->log_error(sprintf('WP Dorna invoice NOT sent (order #%d): %s', $order_id, $message));
                $this->record_invoice_failure($order, $message);
                return;
            }

            if ($currency == 'IRT') {
                $item_total = ($item->get_total() / $item->get_quantity()) * 10;
            } else {
                $item_total = ($item->get_total() / $item->get_quantity());
            }

            $invoice_data['items'][] = array(
                'name'     => $item->get_name(),
                'sku'      => $product->get_sku(),
                'quantity' => $item->get_quantity(),
                'price'    => $item_total,
            );
        }

        $response = $api->post_data($api::INVOICES_ENDPOINT, $invoice_data);

        if (is_wp_error($response)) {
            $error_data = $response->get_error_data();
            $status     = is_array($error_data) && isset($error_data['status']) ? (int) $error_data['status'] : 0;

            // Older Dorna versions answer a repeated order_id with 400: the invoice
            // exists, so this order is done.
            if ($status === 400 && stripos($response->get_error_message(), 'already exists') !== false) {
                $response = array();
            } else {
                $this->log_error(sprintf(
                    'WP Dorna invoice send FAILED (order #%d, HTTP %d): %s | Payload: %s',
                    $order_id,
                    $status,
                    $response->get_error_message(),
                    wp_json_encode($invoice_data)
                ));
                $this->record_invoice_failure($order, $response->get_error_message());
                return;
            }
        }

        $order->update_meta_data('_dorna_invoice_sent', true);
        $order->update_meta_data('_dorna_invoice_id', $response['invoice_id'] ?? '');
        $order->delete_meta_data('_dorna_invoice_error');
        $order->delete_meta_data('_dorna_invoice_attempts');
        $order->delete_meta_data('_dorna_invoice_last_attempt');
        $order->save();
    }

    private function apply_dorna_pricing($wc_product, $product, $currency)
    {
        $original   = $product['original_price'] ?? $product['sale_price'];
        $discounted = $product['discounted_price'] ?? null;
        $active     = !empty($product['discount_active']);

        if ($currency === 'IRT') {
            $original = $original / 10;
            if ($discounted !== null) {
                $discounted = $discounted / 10;
            }
        }

        $wc_product->set_regular_price($original);

        if ($active && $discounted !== null) {
            $wc_product->set_sale_price($discounted);
            $wc_product->set_price($discounted);
            if (!empty($product['discount_start_date'])) {
                $wc_product->set_date_on_sale_from(strtotime($product['discount_start_date']));
            }
            if (!empty($product['discount_end_date'])) {
                $wc_product->set_date_on_sale_to(strtotime($product['discount_end_date']));
            }
        } else {
            $wc_product->set_sale_price('');
            $wc_product->set_price($original);
            $wc_product->set_date_on_sale_from('');
            $wc_product->set_date_on_sale_to('');
        }
    }

    // save error logs to files day to day in plugin folder
    public function log_error($message)
    {
        self::write_log($message);
    }

    public static function write_log($message)
    {
        $dir = WP_DORNA_PLUGIN_DIR . 'logs/';

        // The logs hold customer data (invoice payloads): never web-readable.
        if (!file_exists($dir . '.htaccess')) {
            wp_mkdir_p($dir);
            @file_put_contents($dir . '.htaccess', "Require all denied\nDeny from all\n");
            @file_put_contents($dir . 'index.php', "<?php\n// Silence is golden.\n");
        }

        error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", 3, $dir . date('Y-m-d') . '.log');
    }
}
