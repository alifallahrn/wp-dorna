<?php

namespace WPDorna\Core;

use WPDorna\Connect\Heartbeat;
use WPDorna\Orders\RetryService;
use WPDorna\Support\Logger;
use WPDorna\Sync\FullSync;
use WPDorna\Sync\IncrementalSync;
use WPDorna\Sync\ScheduledFullSync;

/**
 * Cron schedules and events. The hook names are the ones earlier versions used, so
 * an already scheduled event keeps working after an update.
 */
class Scheduler
{
    const PRODUCTS_HOOK  = 'wp_dorna_update_products_event';
    const RETRY_HOOK     = 'wp_dorna_retry_failed_orders_event';
    const LOG_PURGE_HOOK = 'wp_dorna_purge_logs_event';
    const HEARTBEAT_HOOK = 'wp_dorna_heartbeat_event';
    const SCHEDULED_FULL_SYNC_HOOK = 'wp_dorna_scheduled_full_sync_event';

    private $incremental;
    private $retry;
    private $heartbeat;
    private $scheduled;
    private $logger;

    public function __construct(IncrementalSync $incremental, RetryService $retry, Heartbeat $heartbeat, ScheduledFullSync $scheduled, Logger $logger)
    {
        $this->incremental = $incremental;
        $this->retry       = $retry;
        $this->heartbeat   = $heartbeat;
        $this->scheduled   = $scheduled;
        $this->logger      = $logger;
    }

    public function register()
    {
        add_filter('cron_schedules', array($this, 'add_schedules'));

        add_action(self::PRODUCTS_HOOK, array($this->incremental, 'run'));
        add_action(self::RETRY_HOOK, array($this->retry, 'run'));
        add_action(self::LOG_PURGE_HOOK, array($this->logger, 'purge_old'));
        add_action(self::HEARTBEAT_HOOK, array($this->heartbeat, 'send'));
        add_action('wp_dorna_state_changed', array($this->heartbeat, 'send'));
        add_action(self::SCHEDULED_FULL_SYNC_HOOK, array($this->scheduled, 'run'));

        $this->ensure(self::PRODUCTS_HOOK, 'every_minute');
        $this->ensure(self::RETRY_HOOK, 'every_five_minutes');
        $this->ensure(self::LOG_PURGE_HOOK, 'daily');
        $this->ensure(self::HEARTBEAT_HOOK, 'every_five_minutes');
        $this->ensure(self::SCHEDULED_FULL_SYNC_HOOK, 'every_five_minutes');
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
        foreach (array(self::PRODUCTS_HOOK, self::RETRY_HOOK, self::LOG_PURGE_HOOK, self::HEARTBEAT_HOOK, self::SCHEDULED_FULL_SYNC_HOOK, FullSync::CRON_HOOK) as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
}
