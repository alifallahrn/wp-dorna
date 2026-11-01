<?php

namespace WPDorna\Orders;

use WC_Order;
use WPDorna\Support\Backoff;
use WPDorna\Support\Lock;

/**
 * Re-sends orders that never reached Dorna, with growing pauses between attempts.
 */
class RetryService
{
    const WINDOW = 7 * DAY_IN_SECONDS;
    const BATCH  = 15;
    const LOCK   = 'retry';

    private $invoices;
    private $lock;

    public function __construct(InvoiceService $invoices, Lock $lock)
    {
        $this->invoices = $invoices;
        $this->lock     = $lock;
    }

    public function run()
    {
        if (!$this->lock->acquire(self::LOCK, 10 * MINUTE_IN_SECONDS)) {
            return;
        }

        try {
            $orders = wc_get_orders(array(
                'status'       => array('processing', 'completed'),
                'limit'        => 100,
                'orderby'      => 'date',
                'order'        => 'ASC',
                'date_created' => '>' . (time() - self::WINDOW),
                'meta_key'     => InvoiceState::SENT,
                'meta_compare' => 'NOT EXISTS',
            ));

            $sent = 0;
            foreach ($orders as $order) {
                if ($sent >= self::BATCH) {
                    break;
                }

                if (!self::is_due($order)) {
                    continue;
                }

                $this->invoices->send($order->get_id());
                $sent++;

                sleep(1);
            }
        } finally {
            $this->lock->release(self::LOCK);
        }
    }

    public static function is_due(WC_Order $order)
    {
        $state = InvoiceState::describe($order);

        return (time() - $state['last_attempt']) >= Backoff::invoice_delay($state['attempts']);
    }
}
