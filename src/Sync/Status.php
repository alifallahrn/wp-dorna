<?php

namespace WPDorna\Sync;

/**
 * Outcome of the last minute-sync runs, shown on the dashboard.
 */
class Status
{
    const OPTION             = 'wp_dorna_sync_status';
    const LAST_UPDATE_OPTION = 'wp_dorna_last_product_update';

    public static function get()
    {
        $status = get_option(self::OPTION, array());

        return array_merge(array(
            'last_attempt' => null,
            'last_success' => null,
            'last_error'   => null,
            'last_stats'   => array(),
        ), is_array($status) ? $status : array());
    }

    public static function update(array $data)
    {
        update_option(self::OPTION, array_merge(self::get(), $data), false);
    }

    /**
     * Time up to which Dorna's changes are already in the store.
     */
    public static function synced_until()
    {
        return get_option(self::LAST_UPDATE_OPTION, null);
    }

    public static function set_synced_until($iso)
    {
        update_option(self::LAST_UPDATE_OPTION, $iso);
    }
}
