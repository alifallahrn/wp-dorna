<?php

namespace WPDorna\Admin;

class View
{
    /**
     * Renders templates/{$template}.php with $vars as local variables. Templates
     * escape their own output.
     */
    public static function render($template, array $vars = array())
    {
        $file = WP_DORNA_PLUGIN_DIR . 'templates/' . $template . '.php';

        if (!is_readable($file)) {
            return;
        }

        extract($vars, EXTR_SKIP);
        include $file;
    }

    public static function capture($template, array $vars = array())
    {
        ob_start();
        self::render($template, $vars);

        return ob_get_clean();
    }
}
