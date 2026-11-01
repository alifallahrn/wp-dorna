<?php

namespace WPDorna\Core;

class Lifecycle
{
    public static function activate()
    {
        add_option(WP_DORNA_OPTION_NAME, array('api_key' => ''), '', 'no');

        // A fresh install (no token yet) gets the first-run checklist.
        $settings = new Settings();
        (new Wizard($settings, new Connection($settings)))->start_if_new();
    }

    /**
     * Stops the plugin's scheduled work; settings and data stay.
     */
    public static function deactivate()
    {
        Scheduler::clear_all();
    }
}
