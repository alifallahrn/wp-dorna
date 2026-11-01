<?php

namespace WPDorna\Support;

/**
 * How this site introduces itself to Dorna: its origin (scheme + host [+ port]), never a path.
 */
class Site
{
    /** The origin of any URL, or '' when it is not a http(s) URL. */
    public static function origin_of($url)
    {
        $parts = parse_url((string) $url);

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
            return '';
        }

        $origin = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);

        return isset($parts['port']) ? $origin . ':' . (int) $parts['port'] : $origin;
    }

    public static function origin()
    {
        return self::origin_of(home_url());
    }
}
