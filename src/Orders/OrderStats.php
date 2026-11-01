<?php

namespace WPDorna\Orders;

/**
 * How the last 30 days of orders stand with Dorna (cached for a minute): used by the
 * overview and by the heartbeat.
 */
class OrderStats
{
    const WINDOW    = 30 * DAY_IN_SECONDS;
    const CACHE_KEY = 'wp_dorna_order_stats';

    /**
     * @return array{sent: int, unsent: int, oldest_unsent_at: string|null, max_attempts: int}
     */
    public static function counts()
    {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $base = array(
            'status'       => array('processing', 'completed'),
            'date_created' => '>' . (time() - self::WINDOW),
            'meta_key'     => InvoiceState::SENT,
        );

        $sent = wc_get_orders(array_merge($base, array('limit' => 1, 'paginate' => true, 'return' => 'ids', 'meta_compare' => 'EXISTS')));

        $unsent = wc_get_orders(array_merge($base, array('limit' => 100, 'orderby' => 'date', 'order' => 'ASC', 'paginate' => true, 'meta_compare' => 'NOT EXISTS')));

        $oldest   = null;
        $attempts = 0;
        foreach ($unsent->orders as $order) {
            if ($oldest === null && $order->get_date_created()) {
                $oldest = $order->get_date_created()->date('c');
            }
            $attempts = max($attempts, (int) $order->get_meta(InvoiceState::ATTEMPTS));
        }

        $counts = array(
            'sent'             => (int) $sent->total,
            'unsent'           => (int) $unsent->total,
            'oldest_unsent_at' => $oldest,
            'max_attempts'     => $attempts,
        );

        set_transient(self::CACHE_KEY, $counts, MINUTE_IN_SECONDS);

        return $counts;
    }
}
