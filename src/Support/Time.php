<?php

namespace WPDorna\Support;

use DateTimeImmutable;
use Exception;

class Time
{
    public static function now_iso()
    {
        return (new DateTimeImmutable('now', wp_timezone()))->format(DATE_ATOM);
    }

    /**
     * Stored times are either ISO strings with an offset (returned by Dorna) or a
     * legacy site-local "Y-m-d H:i:s". Returns an ISO string moved back by
     * $overlap seconds, or null when the value is empty / unreadable.
     */
    public static function shifted_back($stored, $overlap)
    {
        if (empty($stored)) {
            return null;
        }

        try {
            $time = new DateTimeImmutable($stored, wp_timezone());
        } catch (Exception $e) {
            return null;
        }

        return $time->modify('-' . (int) $overlap . ' seconds')->format(DATE_ATOM);
    }
}
