<?php

namespace WPDorna\Admin;

use WPDorna\Core\Services;
use WPDorna\Orders\InvoiceState;

/**
 * Every request: nonce, capability, then a thin call into a service.
 */
class Ajax
{
    const NONCE = 'wp_dorna_admin';

    private $services;

    public function __construct(Services $services)
    {
        $this->services = $services;
    }

    public function register()
    {
        foreach (array('test_connection', 'full_sync', 'import_scan', 'import_list', 'import_product', 'sync_product', 'send_order', 'unsent_orders', 'clear_logs') as $action) {
            add_action('wp_ajax_wp_dorna_' . $action, array($this, $action));
        }
    }

    private function guard($capability = 'manage_woocommerce')
    {
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(array('message' => __('نشست شما منقضی شده است؛ صفحه را دوباره باز کنید.', 'wp-dorna')), 403);
        }

        if (!current_user_can($capability)) {
            wp_send_json_error(array('message' => __('اجازه‌ی انجام این کار را ندارید.', 'wp-dorna')), 403);
        }
    }

    private function input($key)
    {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- guard() runs first
    }

    private function respond($result, array $success = array())
    {
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($success);
    }

    public function test_connection()
    {
        $this->guard('manage_options');

        $result = $this->services->client->ping();

        $result['ok'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    public function full_sync()
    {
        $this->guard();

        $sync = $this->services->full_sync;

        switch ($this->input('command')) {
            case 'start':
                $result = $sync->start();
                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()));
                }
                break;
            case 'pause':
                $sync->pause();
                break;
            case 'resume':
                $sync->resume();
                break;
            case 'cancel':
                $sync->cancel();
                break;
            case 'tick':
                $sync->tick(1, false);
                break;
        }

        wp_send_json_success($sync->payload());
    }

    public function import_scan()
    {
        $this->guard();

        $result = $this->services->importer->scan_page((int) $this->input('page'));

        $this->respond($result, is_wp_error($result) ? array() : $result);
    }

    public function import_list()
    {
        $this->guard();

        wp_send_json_success(array('products' => $this->services->importer->queued()));
    }

    public function import_product()
    {
        $this->guard();

        $result = $this->services->importer->import($this->input('sku'));

        $this->respond($result, array('name' => is_wp_error($result) ? '' : $result));
    }

    public function sync_product()
    {
        $this->guard();

        $result = $this->services->single->sync((int) $this->input('product_id'));

        $this->respond($result, array('message' => __('محصول از درنا بروزرسانی شد.', 'wp-dorna')));
    }

    public function send_order()
    {
        $this->guard();

        $result = $this->services->invoices->send((int) $this->input('order_id'));

        $this->respond($result, array('message' => __('فاکتور سفارش در درنا ثبت شد.', 'wp-dorna')));
    }

    /**
     * Orders of the last 30 days without an invoice in Dorna, for "send all again".
     */
    public function unsent_orders()
    {
        $this->guard();

        $ids = wc_get_orders(array(
            'status'       => array('processing', 'completed'),
            'limit'        => 100,
            'return'       => 'ids',
            'orderby'      => 'date',
            'order'        => 'ASC',
            'date_created' => '>' . (time() - 30 * DAY_IN_SECONDS),
            'meta_key'     => InvoiceState::SENT,
            'meta_compare' => 'NOT EXISTS',
        ));

        wp_send_json_success(array('ids' => array_map('intval', $ids)));
    }

    public function clear_logs()
    {
        $this->guard('manage_options');

        $this->services->logger->clear();

        wp_send_json_success();
    }
}
