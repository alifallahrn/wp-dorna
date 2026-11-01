<?php

namespace WPDorna\Support;

/**
 * Daily log files in uploads/wp-dorna-logs: kept across plugin updates, closed to
 * the web, bounded in size and age, and without secrets.
 */
class Logger
{
    const RETENTION_DAYS = 14;
    const MAX_FILE_BYTES = 2097152;
    const TAIL_LINES     = 400;

    private $secrets;

    /**
     * @param callable|null $secrets returns the strings that must never be written
     */
    public function __construct($secrets = null)
    {
        $this->secrets = $secrets;
    }

    public function directory()
    {
        $uploads = wp_upload_dir(null, false);

        return trailingslashit($uploads['basedir']) . 'wp-dorna-logs/';
    }

    public function error($message, array $context = array())
    {
        $this->write('ERROR', $message, $context);
    }

    public function warning($message, array $context = array())
    {
        $this->write('WARN', $message, $context);
    }

    public function info($message, array $context = array())
    {
        $this->write('INFO', $message, $context);
    }

    private function write($level, $message, array $context)
    {
        $dir = $this->directory();

        if (!$this->prepare($dir)) {
            return;
        }

        $file = $dir . wp_date('Y-m-d') . '.log';
        if (file_exists($file) && filesize($file) > self::MAX_FILE_BYTES) {
            return;
        }

        $line = sprintf('[%s] %s %s', wp_date('H:i:s'), $level, $message);
        if ($context) {
            $line .= ' ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        @file_put_contents($file, $this->redact($line) . "\n", FILE_APPEND | LOCK_EX);
    }

    private function redact($text)
    {
        $secrets = is_callable($this->secrets) ? (array) call_user_func($this->secrets) : array();

        foreach ($secrets as $secret) {
            if (is_string($secret) && strlen($secret) >= 6) {
                $text = str_replace($secret, '***', $text);
            }
        }

        return $text;
    }

    private function prepare($dir)
    {
        if (!file_exists($dir . '.htaccess')) {
            if (!wp_mkdir_p($dir)) {
                return false;
            }
            @file_put_contents($dir . '.htaccess', "Require all denied\nDeny from all\n");
            @file_put_contents($dir . 'index.php', "<?php\n// Silence is golden.\n");
        }

        return is_dir($dir) && is_writable($dir);
    }

    /**
     * @return string[] dates (Y-m-d) that have a log file, newest first
     */
    public function dates()
    {
        $dates = array();

        foreach ((array) glob($this->directory() . '*.log') as $path) {
            $date = basename($path, '.log');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $dates[] = $date;
            }
        }

        rsort($dates);

        return $dates;
    }

    /**
     * @return string[] the last lines of one day's log (date is validated against existing files)
     */
    public function tail($date)
    {
        if (!in_array($date, $this->dates(), true)) {
            return array();
        }

        $lines = file($this->directory() . $date . '.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return $lines ? array_slice($lines, -self::TAIL_LINES) : array();
    }

    public function clear()
    {
        foreach ($this->dates() as $date) {
            @unlink($this->directory() . $date . '.log');
        }
    }

    public function purge_old()
    {
        $limit = wp_date('Y-m-d', time() - self::RETENTION_DAYS * DAY_IN_SECONDS);

        foreach ($this->dates() as $date) {
            if ($date < $limit) {
                @unlink($this->directory() . $date . '.log');
            }
        }
    }
}
