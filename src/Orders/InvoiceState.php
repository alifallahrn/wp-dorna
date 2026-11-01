<?php

namespace WPDorna\Orders;

use WC_Order;

/**
 * Where an order stands with Dorna. The meta keys are kept from earlier versions.
 */
class InvoiceState
{
    const SENT         = '_dorna_invoice_sent';
    const INVOICE_ID   = '_dorna_invoice_id';
    const ERROR        = '_dorna_invoice_error';
    const ATTEMPTS     = '_dorna_invoice_attempts';
    const LAST_ATTEMPT = '_dorna_invoice_last_attempt';

    public static function describe(WC_Order $order)
    {
        $sent = (bool) $order->get_meta(self::SENT);

        return array(
            'sent'         => $sent,
            'invoice_id'   => (string) $order->get_meta(self::INVOICE_ID),
            'error'        => $sent ? '' : (string) $order->get_meta(self::ERROR),
            'attempts'     => (int) $order->get_meta(self::ATTEMPTS),
            'last_attempt' => (int) $order->get_meta(self::LAST_ATTEMPT),
        );
    }

    public static function mark_sent(WC_Order $order, $invoice_id)
    {
        $order->update_meta_data(self::SENT, true);
        $order->update_meta_data(self::INVOICE_ID, $invoice_id);
        $order->delete_meta_data(self::ERROR);
        $order->delete_meta_data(self::ATTEMPTS);
        $order->delete_meta_data(self::LAST_ATTEMPT);
        $order->save();
    }

    public static function mark_failed(WC_Order $order, $message)
    {
        $order->update_meta_data(self::ERROR, $message);
        $order->update_meta_data(self::ATTEMPTS, (int) $order->get_meta(self::ATTEMPTS) + 1);
        $order->update_meta_data(self::LAST_ATTEMPT, time());
        $order->save();
    }
}
