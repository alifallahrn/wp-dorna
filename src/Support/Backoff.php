<?php

namespace WPDorna\Support;

class Backoff
{
    const INVOICE_BASE = 300;
    const INVOICE_CAP  = 21600;
    const ERROR_BASE   = 30;
    const ERROR_CAP    = 300;

    /**
     * Seconds to wait before an order's invoice is sent again: 5 minutes after
     * the first failure, doubling, capped at 6 hours.
     */
    public static function invoice_delay($attempts)
    {
        if ($attempts < 1) {
            return 0;
        }

        return (int) min(self::INVOICE_CAP, self::INVOICE_BASE * pow(2, $attempts - 1));
    }

    /**
     * Seconds to pause the full sync after consecutive API errors: 30s, 60s ... 5 min.
     */
    public static function error_delay($consecutive)
    {
        return (int) min(self::ERROR_CAP, self::ERROR_BASE * pow(2, max(0, $consecutive - 1)));
    }
}
