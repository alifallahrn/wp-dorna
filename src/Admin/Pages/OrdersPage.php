<?php

namespace WPDorna\Admin\Pages;

use WPDorna\Admin\View;
use WPDorna\Orders\InvoiceState;

class OrdersPage extends Page
{
    const PER_PAGE = 20;
    const WINDOW   = 30 * DAY_IN_SECONDS;

    public function slug()
    {
        return 'wp-dorna-orders';
    }

    public function label()
    {
        return __('سفارش‌ها', 'wp-dorna');
    }

    public function icon()
    {
        return 'receipt';
    }

    public function title()
    {
        return __('فاکتور سفارش‌ها', 'wp-dorna');
    }

    public function purpose()
    {
        return __('سفارش‌های ۳۰ روز اخیر و اینکه فاکتورشان در درنا ثبت شده یا نه.', 'wp-dorna');
    }

    public function render()
    {
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'attention'; // phpcs:ignore WordPress.Security.NonceVerification
        $tab = in_array($tab, array('attention', 'sent', 'all'), true) ? $tab : 'attention';

        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1; // phpcs:ignore WordPress.Security.NonceVerification

        $args = array(
            'status'       => array('processing', 'completed'),
            'limit'        => self::PER_PAGE,
            'paged'        => $paged,
            'paginate'     => true,
            'orderby'      => 'date',
            'order'        => 'DESC',
            'date_created' => '>' . (time() - self::WINDOW),
        );

        if ($tab !== 'all') {
            $args['meta_key']     = InvoiceState::SENT;
            $args['meta_compare'] = $tab === 'sent' ? 'EXISTS' : 'NOT EXISTS';
        }

        $result = wc_get_orders($args);
        $rows   = array();

        foreach ($result->orders as $order) {
            $rows[] = array(
                'id'       => $order->get_id(),
                'edit_url' => $order->get_edit_order_url(),
                'date'     => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
                'total'    => wp_strip_all_tags(wc_price($order->get_total(), array('currency' => $order->get_currency()))),
                'state'    => InvoiceState::describe($order),
            );
        }

        View::render('pages/orders', array(
            'tab'     => $tab,
            'rows'    => $rows,
            'total'   => (int) $result->total,
            'pages'   => (int) $result->max_num_pages,
            'paged'   => $paged,
            'tab_url' => function ($tab_key) {
                return $this->url(array('tab' => $tab_key));
            },
            'page_url' => function ($number) use ($tab) {
                return $this->url(array('tab' => $tab, 'paged' => $number));
            },
        ));
    }
}
