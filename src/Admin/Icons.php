<?php

namespace WPDorna\Admin;

/**
 * Small line icons (24px grid, 1.8 stroke) like the ones in the Dorna panel. The
 * markup is made of constants only, so it is safe to print as is.
 */
class Icons
{
    private static $paths = array(
        'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/><path d="M10 20v-6h4v6"/>',
        'sync'     => '<path d="M20 11a8 8 0 0 0-14.9-3"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.9 3"/><path d="M20 20v-4h-4"/>',
        'download' => '<path d="M12 4v11"/><path d="m7 11 5 5 5-5"/><path d="M5 20h14"/>',
        'receipt'  => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
        'file'     => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h6M10 17h6"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'alert'    => '<path d="M12 4 2.5 20h19z"/><path d="M12 10v4.5"/><path d="M12 17.5v.01"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'box'      => '<path d="m3 7.5 9-4.5 9 4.5-9 4.5z"/><path d="M3 7.5V17l9 4.5 9-4.5V7.5"/><path d="M12 12v9.5"/>',
        'link'     => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3A4 4 0 0 0 11 18.7l1-1"/>',
        'play'     => '<path d="M7 4.5v15l12-7.5z"/>',
        'pause'    => '<path d="M8 5v14M16 5v14"/>',
        'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
        'send'     => '<path d="M21 3 3 10.5l7 3 3 7z"/><path d="m10 13.5 5-5"/>',
        'external' => '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M18 14v5H5V6h5"/>',
    );

    public static function svg($name, $class = '')
    {
        $paths = isset(self::$paths[$name]) ? self::$paths[$name] : '';

        return '<svg class="wpd-i ' . esc_attr($class) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths . '</svg>';
    }
}
