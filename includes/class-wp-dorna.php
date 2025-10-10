<?php

class WP_Dorna
{
    public function init()
    {
        add_filter('cron_schedules', function ($schedules) {
            $schedules['every_minute'] = array(
                'interval' => 60,
                'display'  => __('Every Minute'),
            );
            return $schedules;
        });

        $this->schedule_product_updates();

        add_action('wp_dorna_update_products_event', array($this, 'update_products'));
        add_action('woocommerce_order_status_processing', array($this, 'create_invoice_in_dorna'));
        add_action('woocommerce_order_status_completed', array($this, 'create_invoice_in_dorna'));
    }

    public function schedule_product_updates()
    {
        if (!wp_next_scheduled('wp_dorna_update_products_event')) {
            wp_schedule_event(time(), 'every_minute', 'wp_dorna_update_products_event');
        }
    }

    public function update_products()
    {
        global $wpdb;

        set_time_limit(0);

        $api = new WP_Dorna_API();
        $apiEndpoint = $api::PRODUCTS_ENDPOINT;

        $lastSync = get_option('wp_dorna_last_product_update', null);
        if ($lastSync) {
            $apiEndpoint = $apiEndpoint . '?since=' . urlencode($lastSync);
        }

        $page = 1;
        $dornaProducts = [];

        do {
            $endpoint = $apiEndpoint . ($lastSync ? '&page=' . $page : '?page=' . $page);
            $response = $api->get_data($endpoint);

            if (!is_array($response) || empty($response['data'])) {
                break;
            }

            $filteredProducts = array_filter($response['data'], function ($product) {
                return !empty($product['sku']);
            });

            foreach ($filteredProducts as $p) $dornaProducts[$p['sku']] = $p;

            $hasMore = isset($response['current_page'], $response['last_page']) && $response['current_page'] < $response['last_page'];
            $page++;
        } while ($hasMore);

        if (!empty($dornaProducts)) {
            $wooProductsResults = $wpdb->get_results("SELECT post_id, meta_value as sku FROM {$wpdb->prefix}postmeta WHERE meta_key = '_sku'");
            $wooProducts = wp_list_pluck($wooProductsResults, 'post_id', 'sku');

            $products = array_intersect_key($dornaProducts, $wooProducts);

            $currency = get_woocommerce_currency();

            foreach ($products as $sku => $product) {
                $existing_product_id = $wooProducts[$sku] ?? null;
                if ($existing_product_id) {

                    if ($currency == 'IRT') {
                        $product['sale_price'] = $product['sale_price'] / 10;
                    }

                    $wc_product = wc_get_product($existing_product_id);
                    if ($wc_product) {
                        $wc_product->set_price($product['sale_price']);
                        $wc_product->set_regular_price($product['sale_price']);
                        $wc_product->set_stock_quantity($product['stock']);
                        $wc_product->save();
                    }
                }
            }
        }

        update_option('wp_dorna_last_product_update', current_time('mysql'));
    }

    public function create_invoice_in_dorna($order_id)
    {
        $order = wc_get_order($order_id);
        $api = new WP_Dorna_API();

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
                'mobile' => $order->get_billing_phone(),
                'email' => $order->get_billing_email(),
                'address' => $state_name . ' - ' . $city_name . ' - ' . $order->get_billing_address_1(),
            ],
            'items' => array(),
            'discount' => ($currency == 'IRT') ? ($order->get_total_discount() * 10) : $order->get_total_discount(),
            'total' => ($currency == 'IRT') ? ($order->get_total() * 10) : $order->get_total(),
            'order_id' => $order->get_id(),
            'order_status' => $order->get_status(),
            'payment_method' => $order->get_payment_method_title(),
            'transaction_id' => $order->get_transaction_id(),
        );

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if (! $product) {
                continue;
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
            $this->log_error('WP Dorna API Error: ' . $response->get_error_message());
            return;
        }
    }

    // save error logs to files day to day in plugin folder
    public function log_error($message)
    {
        $log_file = WP_DORNA_PLUGIN_DIR . 'logs/' . date('Y-m-d') . '.log';
        error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", 3, $log_file);
    }
}
