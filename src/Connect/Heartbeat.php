<?php

namespace WPDorna\Connect;

use WP_Error;
use WPDorna\Api\Client;
use WPDorna\Core\Connection;
use WPDorna\Core\Settings;
use WPDorna\Orders\OrderStats;
use WPDorna\Support\Logger;
use WPDorna\Sync\FullSync;
use WPDorna\Sync\Status;

/**
 * Every few minutes the site tells Dorna how it is doing, so the Dorna dashboard
 * can show the connection and raise an alert when something stops. Counts and
 * states only: no customer, order or product data.
 */
class Heartbeat
{
    private $client;
    private $connection;
    private $settings;
    private $logger;

    public function __construct(Client $client, Connection $connection, Settings $settings, Logger $logger)
    {
        $this->client     = $client;
        $this->connection = $connection;
        $this->settings   = $settings;
        $this->logger     = $logger;
    }

    public function payload()
    {
        $sync     = Status::get();
        $full     = FullSync::get_state();
        $orders   = OrderStats::counts();
        $schedule = $this->settings->schedule();

        return array(
            'plugin_version' => WP_DORNA_VERSION,
            'wp_version'     => get_bloginfo('version'),
            'wc_version'     => defined('WC_VERSION') ? WC_VERSION : '',
            'php_version'    => PHP_VERSION,
            'cron_disabled'  => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'product_sync'   => array(
                'last_attempt' => $sync['last_attempt'],
                'last_success' => $sync['last_success'],
                'last_error'   => $sync['last_error'],
                'error_kind'   => $sync['error_kind'],
            ),
            'full_sync'      => array(
                'status'      => $full['status'],
                'finished_at' => $full['finished_at'],
                'trigger'     => $full['trigger'],
                'checked'     => $full['checked'],
                'updated'     => $full['updated'],
            ),
            'orders'         => array(
                'sent_30d'         => $orders['sent'],
                'unsent'           => $orders['unsent'],
                'oldest_unsent_at' => $orders['oldest_unsent_at'],
                'max_attempts'     => $orders['max_attempts'],
            ),
            'schedule'       => array('mode' => $schedule['mode'], 'hour' => $schedule['hour']),
        );
    }

    /**
     * @return true|WP_Error|null null when there is nothing to send to (no usable connection)
     */
    public function send()
    {
        if (!$this->connection->is_usable()) {
            return null;
        }

        $result = $this->client->heartbeat($this->payload());

        if (is_wp_error($result)) {
            $this->logger->warning('گزارش سلامت به درنا ارسال نشد.', array('reason' => $result->get_error_message()));

            return $result;
        }

        return true;
    }
}
