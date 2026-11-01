<?php

namespace WPDorna\Support;

/**
 * A short message that survives one redirect (the connect flow bounces through
 * Dorna), kept per user.
 */
class Flash
{
    private static function key()
    {
        return 'wp_dorna_flash_' . get_current_user_id();
    }

    public static function add($tone, $text)
    {
        set_transient(self::key(), array('tone' => $tone, 'text' => $text), 120);
    }

    /** @return array|null {tone, text}, shown once */
    public static function pull()
    {
        $flash = get_transient(self::key());

        if ($flash) {
            delete_transient(self::key());
        }

        return is_array($flash) ? $flash : null;
    }
}
