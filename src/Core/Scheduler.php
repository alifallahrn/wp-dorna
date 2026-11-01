<?php

namespace WPDorna\Core;

use WPDorna\Orders\RetryService;
use WPDorna\Support\Logger;
use WPDorna\Sync\FullSync;
use WPDorna\Sync\IncrementalSync;

/**
 * Cron schedules and events. The hook names are the ones earlier versions used, so
 * an already scheduled event keeps working after an update.
 */
class Scheduler
{
    const PRODUCTS_HOOK  = 'wp_dorna_update_products_event';
    const RETRY_HOOK     = 'wp_dorna_retry_failed_orders_event';
    const LOG_PURGE_HOOK = 'wp_dorna_purge_logs_event';

    private $incremental;
    private $retry;
    private $logger;

    public function __construct(IncrementalSync $incremental, RetryService $retry, Logger $logger)
    {
        $this->incremental = $incremental;
        $this->retry       = $retry;
        $this->logger      = $logger;
    }

    public function register()
    {
        add_filter('cron_schedules', array($this, 'add_schedules'));

        add_action(self::PRODUCTS_HOOK, array($this->incremental, 'run'));
        add_action(self::RETRY_HOOK, array($this->retry, 'run'));
        add_action(self::LOG_PURGE_HOOK, array($this->logger, 'purge_old'));

        $this->ensure(self::PRODUCTS_HOOK, 'every_minute');
        $this->ensure(self::RETRY_HOOK, 'every_five_minutes');
        $this->ensure(self::LOG_PURGE_HOOK, 'daily');
    }

    public function add_schedules($schedules)
    {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display'  => __('هر دقیقه', 'wp-dorna'),
        );
        $schedules['every_five_minutes'] = array(
            'interval' => 300,
            'display'  => __('هر ۵ دقیقه', 'wp-dorna'),
        );

        return $schedules;
    }

    private function ensure($hook, $recurrence)
    {
        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), $recurrence, $hook);
        }
    }

    public static function clear_all()
    {
        foreach (array(self::PRODUCTS_HOOK, self::RETRY_HOOK, self::LOG_PURGE_HOOK, FullSync::CRON_HOOK) as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
}
