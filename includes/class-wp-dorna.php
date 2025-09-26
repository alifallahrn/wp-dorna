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
        $api = new WP_Dorna_API();
        $products = $api->get_data($api::PRODUCTS_ENDPOINT);

        if (isset($products['error'])) {
            error_log('WP Dorna API Error: ' . $products['error']);
            return;
        }

        if (!is_array($products)) {
            error_log('WP Dorna API Error: Invalid products data');
            return;
        }

        if (empty($products)) {
            error_log('WP Dorna API Notice: No products found to update');
            return;
        }

        foreach ($products as $product) {
            if (!isset($product['sku'])) {
                continue;
            }

            $existing_product_id = wc_get_product_id_by_sku($product['sku']);

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
            } else {
                $new_product = new WC_Product_Simple();
                $new_product->set_name($product['name']);
                $new_product->set_sku($product['sku']);
                $new_product->set_price($product['sale_price']);
                $new_product->set_regular_price($product['sale_price']);
                $new_product->set_manage_stock(true);
                $new_product->set_stock_quantity($product['stock']);
                $new_product->save();
            }
        }
    }

    public function create_invoice_in_dorna($order_id)
    {
        $order = wc_get_order($order_id);
        $api = new WP_Dorna_API();

        $invoice_data = array(
            'customer' => [
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'mobile' => $order->get_billing_phone(),
                'email' => $order->get_billing_email(),
                'address' => $order->get_billing_state() . ' - ' . $order->get_billing_city() . ' - ' . $order->get_billing_address_1(),
            ],
            'items'          => array(),
            'total'          => $order->get_total(),
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
                'price'    => $item->get_total() / $item->get_quantity(),
            );
        }

        $response = $api->post_data($api::INVOICES_ENDPOINT, $invoice_data);

        if (isset($response['error'])) {
            error_log('WP Dorna API Error: ' . $response['error']);
        }
    }
}
