<?php

namespace WPDorna\Support;

/**
 * The same SKU can be typed with spaces, Persian digits or another case on each
 * side; both sides are compared in this normalized form.
 */
class Sku
{
    public static function normalize($sku)
    {
        $sku = trim((string) $sku);
        $sku = strtr($sku, array(
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ));

        return function_exists('mb_strtolower') ? mb_strtolower($sku) : strtolower($sku);
    }
}
