<?php

namespace WPDorna\Admin;

use WC_Abstract_Order;
use WC_Order;
use WPDorna\Core\Services;
use WPDorna\Orders\InvoiceState;

/**
 * The "Dorna" column in the orders list and the box on the order screen. Both
 * the HPOS and the legacy (posts) screens are covered.
 */
class OrderIntegration
{
    private $services;

    public function __construct(Services $services)
    {
        $this->services = $services;
    }

    public function register()
    {
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_column'));
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'render_column'), 10, 2);

        add_filter('manage_edit-shop_order_columns', array($this, 'add_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'render_column_legacy'), 10, 2);

        add_action('add_meta_boxes', array($this, 'add_box'));
    }

    public function add_column($columns)
    {
        $columns['wp_dorna'] = __('درنا', 'wp-dorna');

        return $columns;
    }

    public function render_column($column, $order)
    {
        if ($column !== 'wp_dorna' || !$order instanceof WC_Order) {
            return;
        }

        View::render('partials/order-badge', array('state' => InvoiceState::describe($order)));
    }

    public function render_column_legacy($column, $post_id)
    {
        if ($column === 'wp_dorna') {
            $this->render_column($column, wc_get_order($post_id));
        }
    }

    public function add_box()
    {
        $screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';

        add_meta_box('wp-dorna-order-box', __('اتصال به درنا', 'wp-dorna'), array($this, 'render_box'), $screen, 'side', 'high');
    }

    public function render_box($post_or_order)
    {
        $order = $post_or_order instanceof WC_Abstract_Order ? $post_or_order : wc_get_order($post_or_order->ID);

        if (!$order instanceof WC_Order) {
            return;
        }

        View::render('partials/order-box', array(
            'order_id' => $order->get_id(),
            'state'    => InvoiceState::describe($order),
        ));
    }
}
