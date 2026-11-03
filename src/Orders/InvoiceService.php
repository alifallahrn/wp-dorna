<?php

namespace WPDorna\Orders;

use Throwable;
use WC_Order;
use WP_Error;
use WPDorna\Api\Client;
use WPDorna\Support\Lock;
use WPDorna\Support\Logger;
use WPDorna\Support\Money;

/**
 * Sends a WooCommerce order to Dorna as an invoice (once; Dorna also refuses a
 * repeated order_id).
 */
class InvoiceService
{
    private $client;
    private $lock;
    private $logger;

    public function __construct(Client $client, Lock $lock, Logger $logger)
    {
        $this->client = $client;
        $this->lock   = $lock;
        $this->logger = $logger;
    }

    public function register()
    {
        add_action('woocommerce_order_status_processing', array($this, 'handle_status_change'));
        add_action('woocommerce_order_status_completed', array($this, 'handle_status_change'));
    }

    public function handle_status_change($order_id)
    {
        $this->send($order_id);
    }

    /**
     * @return true|WP_Error true when the order is (now) in Dorna
     */
    public function send($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('not_found', __('سفارش پیدا نشد.', 'wp-dorna'));
        }

        if ($order->get_meta(InvoiceState::SENT)) {
            return true;
        }

        // A timed out request can still be running in Dorna while the retry fires:
        // one in-flight request per order.
        $lock = 'invoice_' . $order_id;
        if (!$this->lock->acquire($lock, 2 * MINUTE_IN_SECONDS)) {
            return new WP_Error('busy', __('ارسال این سفارش در حال انجام است.', 'wp-dorna'));
        }

        try {
            return $this->deliver($order);
        } catch (Throwable $e) {
            $this->logger->error('ارسال فاکتور با خطای پیش‌بینی‌نشده متوقف شد.', array('order' => $order_id, 'reason' => $e->getMessage()));
            InvoiceState::mark_failed($order, __('خطای داخلی در آماده‌سازی فاکتور.', 'wp-dorna'));

            return new WP_Error('internal', __('خطای داخلی در آماده‌سازی فاکتور؛ جزئیات در گزارش‌ها ثبت شد.', 'wp-dorna'));
        } finally {
            $this->lock->release($lock);
        }
    }

    private function fail(WC_Order $order, $message, array $context = array())
    {
        $this->logger->error('فاکتور به درنا ارسال نشد.', array_merge(array('order' => $order->get_id(), 'reason' => $message), $context));
        InvoiceState::mark_failed($order, $message);

        return new WP_Error('invoice_failed', $message);
    }

    private function deliver(WC_Order $order)
    {
        $order_id = $order->get_id();
        $currency = get_woocommerce_currency();

        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if (!$product || $item->get_quantity() <= 0) {
                $this->logger->warning('کالای این ردیف سفارش دیگر وجود ندارد و در فاکتور نیامد.', array('order' => $order_id, 'item' => $item->get_name()));
                continue;
            }

            // Dorna rejects the WHOLE invoice when one item has no SKU.
            if (trim((string) $product->get_sku()) === '') {
                /* translators: %s: product name */
                return $this->fail($order, sprintf(__('کالای «%s» کد کالا (SKU) ندارد.', 'wp-dorna'), $item->get_name()));
            }

            $items[] = array(
                'name'     => $item->get_name(),
                'sku'      => $product->get_sku(),
                'quantity' => $item->get_quantity(),
                'price'    => Money::to_dorna($item->get_total() / $item->get_quantity(), $currency),
            );
        }

        $response = $this->client->post_data(Client::INVOICES_ENDPOINT, $this->payload($order, $items, $currency));

        if (is_wp_error($response)) {
            $data   = $response->get_error_data();
            $status = is_array($data) && isset($data['status']) ? (int) $data['status'] : 0;

            // Older Dorna versions answer a repeated order_id with 400: the invoice
            // exists, so this order is done.
            if ($status === 400 && stripos($response->get_error_message(), 'already exists') !== false) {
                $response = array();
            } else {
                return $this->fail($order, $response->get_error_message(), array(
                    'http'  => $status,
                    'items' => wp_list_pluck($items, 'quantity', 'sku'),
                ));
            }
        }

        InvoiceState::mark_sent($order, $response['invoice_id'] ?? '');

        return true;
    }

    private function payload(WC_Order $order, array $items, $currency)
    {
        $state_code = $order->get_billing_state();
        $states     = WC()->countries->get_states($order->get_billing_country());
        $state_name = isset($states[$state_code]) ? $states[$state_code] : $state_code;

        $city = function_exists('pw_get_city_name')
            ? pw_get_city_name($state_code, $order->get_billing_city())
            : $order->get_billing_city();

        return array(
            'customer' => array(
                'name'    => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                'mobile'  => $order->get_billing_phone() ?: null,
                'email'   => $order->get_billing_email(),
                'address' => implode(' - ', array_filter(array(
                    $state_name,
                    $city,
                    $order->get_billing_address_1(),
                    $order->get_billing_postcode() ?: null,
                ))),
            ),
            'items'          => $items,
            'discount'       => Money::to_dorna($order->get_total_discount(), $currency),
            'shipping_cost'  => Money::to_dorna($order->get_shipping_total(), $currency),
            'tax'            => Money::to_dorna((float) $order->get_total_tax(), $currency),
            'sub_total'      => Money::to_dorna($order->get_subtotal(), $currency),
            'total'          => Money::to_dorna($order->get_total(), $currency),
            'order_id'       => $order->get_id(),
            'order_status'   => $order->get_status(),
            'order_note'     => $order->get_customer_note(),
            'payment_method' => $order->get_payment_method_title(),
            'payment_method_id' => $order->get_payment_method(),
            'transaction_id' => $order->get_transaction_id(),
        );
    }
}
