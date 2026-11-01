<?php

namespace WPDorna\Support;

/**
 * Atomic through add_option (the same approach WP core uses for its upgrade lock).
 * A lock older than its ttl is considered dead.
 */
class Lock
{
    public function acquire($name, $ttl)
    {
        $key = 'wp_dorna_lock_' . $name;
        $now = time();

        if (add_option($key, $now, '', 'no')) {
            return true;
        }

        $started = (int) get_option($key);
        if ($started && ($now - $started) > $ttl) {
            update_option($key, $now, false);
            return true;
        }

        return false;
    }

    public function release($name)
    {
        delete_option('wp_dorna_lock_' . $name);
    }
}
