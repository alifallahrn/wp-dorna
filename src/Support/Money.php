<?php

namespace WPDorna\Support;

/**
 * Dorna and a store that uses IRT (Toman) differ by a factor of ten.
 */
class Money
{
    const IRT = 'IRT';

    public static function to_dorna($amount, $currency)
    {
        return $currency === self::IRT ? $amount * 10 : $amount;
    }

    public static function from_dorna($amount, $currency)
    {
        if ($amount === null) {
            return null;
        }

        return $currency === self::IRT ? $amount / 10 : $amount;
    }
}
