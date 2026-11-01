<?php

namespace WPDorna\Admin;

class Format
{
    /**
     * Persian digits with thousands separators, for numbers shown to people.
     */
    public static function num($number)
    {
        return strtr(number_format((float) $number, 0, '.', '،'), array(
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ));
    }

    /**
     * "۳ دقیقه پیش" for an ISO time or a unix timestamp; "—" when empty.
     */
    public static function ago($time)
    {
        $timestamp = is_numeric($time) ? (int) $time : (empty($time) ? 0 : strtotime($time));

        if (!$timestamp) {
            return '—';
        }

        $diff = max(0, time() - $timestamp);

        if ($diff < 45) {
            return __('چند ثانیه پیش', 'wp-dorna');
        }

        foreach (array(
            array(DAY_IN_SECONDS, __('روز', 'wp-dorna')),
            array(HOUR_IN_SECONDS, __('ساعت', 'wp-dorna')),
            array(MINUTE_IN_SECONDS, __('دقیقه', 'wp-dorna')),
        ) as $unit) {
            if ($diff >= $unit[0]) {
                /* translators: 1: number, 2: unit (minute, hour, day) */
                return sprintf(__('%1$s %2$s پیش', 'wp-dorna'), self::num(floor($diff / $unit[0])), $unit[1]);
            }
        }

        return '—';
    }

    public static function datetime($time)
    {
        $timestamp = is_numeric($time) ? (int) $time : (empty($time) ? 0 : strtotime($time));

        return $timestamp ? wp_date('Y/m/d - H:i', $timestamp) : '—';
    }
}
