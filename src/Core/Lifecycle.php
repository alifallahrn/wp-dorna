<?php

namespace WPDorna\Core;

class Lifecycle
{
    public static function activate()
    {
        add_option(WP_DORNA_OPTION_NAME, array('api_key' => ''), '', 'no');
    }

    /**
     * Stops the plugin's scheduled work; settings and data stay.
     */
    public static function deactivate()
    {
        Scheduler::clear_all();
    }
}
