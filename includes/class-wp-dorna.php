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
        set_time_limit(0);
        $api = new WP_Dorna_API();
        $products = $api->get_data($api::PRODUCTS_ENDPOINT);
        $this->log_error('WP Dorna: Fetched ' . (is_array($products) ? count($products) : '0') . ' products from API.');

        if (isset($products['error'])) {
            $this->log_error('WP Dorna API Error: ' . $products['error']);
            return;
        }

        if (!is_array($products)) {
            $this->log_error('WP Dorna API Error: Invalid products data');
            return;
        }

        if (empty($products)) {
            $this->log_error('WP Dorna API Notice: No products found to update');
            return;
        }

        foreach ($products as $product) {
            if (!isset($product['sku'])) {
                continue;
            }

            $existing_product_id = wc_get_product_id_by_sku($product['sku']);
            if ($existing_product_id === 0) {
                $this->log_error('WP Dorna Notice: Product with SKU ' . $product['sku'] . ' not found in WooCommerce. It will be created.');
            } else {
                $this->log_error('WP Dorna Notice: Updating product with SKU ' . $product['sku'] . ' (ID: ' . $existing_product_id . ')');
            }

            // change price unit from rials to tomans
            $product['sale_price'] = $product['sale_price'] / 10;

            if (!empty($existing_product_id)) {
                $wc_product = wc_get_product($existing_product_id);
                if ($wc_product) {
                    $wc_product->set_name($product['name']);
                    $wc_product->set_price($product['sale_price']);
                    $wc_product->set_regular_price($product['sale_price']);
                    $wc_product->set_manage_stock(true);
                    $wc_product->set_stock_quantity($product['stock']);
                    $wc_product->save();
                }
            }
        }
    }

    public function create_invoice_in_dorna($order_id)
    {
        $order = wc_get_order($order_id);
        $api = new WP_Dorna_API();

        $state_code = $order->get_billing_state();
        $country    = $order->get_billing_country();
        $states     = WC()->countries->get_states( $country );
        $state_name = isset( $states[ $state_code ] ) ? $states[ $state_code ] : $state_code;

        $city_name  = function_exists('pw_get_city_name')
            ? pw_get_city_name( $state_code, $order->get_billing_city() )
            : $order->get_billing_city();

        $invoice_data = array(
            'customer' => [
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'mobile' => $order->get_billing_phone(),
                'email' => $order->get_billing_email(),
                'address' => $state_name . ' - ' . $city_name . ' - ' . $order->get_billing_address_1(),
            ],
            'items'          => array(),
            'total'          => ($order->get_total() * 10), // convert to rials
            'order_id'       => $order->get_id(),
            'order_status'   => $order->get_status(),
            'payment_method' => $order->get_payment_method_title(),
            'transaction_id' => $order->get_transaction_id(),
        );

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if (! $product) {
                continue;
            }

            $invoice_data['items'][] = array(
                'name'     => $item->get_name(),
                'sku'      => $product->get_sku(),
                'quantity' => $item->get_quantity(),
                'price'    => ($item->get_total() / $item->get_quantity()) * 10, // convert to rials
            );
        }

        $response = $api->post_data($api::INVOICES_ENDPOINT, $invoice_data);
        $this->log_error('WP Dorna API Response: ' . print_r($response, true));

        if (isset($response['error'])) {
            $this->log_error('WP Dorna API Error: ' . $response['error']);
        }
    }

    // save error logs to files day to day in plugin folder
    public function log_error($message)
    {
        $log_file = WP_DORNA_PLUGIN_DIR . 'logs/' . date('Y-m-d') . '.log';
        error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", 3, $log_file);
    }
}
