<?php

namespace WPDorna\Sync;

use Throwable;
use WPDorna\Api\Client;
use WPDorna\Support\Lock;
use WPDorna\Support\Logger;
use WPDorna\Support\Sku;
use WPDorna\Support\Time;

/**
 * Every minute: fetch what changed in Dorna since the last successful run.
 */
class IncrementalSync
{
    // `since` is sent this far in the past: Dorna stamps updated_at inside a
    // transaction, so a change can become visible after a run already passed it.
    const OVERLAP_SECONDS  = 300;
    const MAX_PAGES        = 500;
    const PAGE_DELAY_MICRO = 500000;
    const LOCK             = 'products';

    private $client;
    private $writer;
    private $full_sync;
    private $lock;
    private $logger;

    public function __construct(Client $client, ProductWriter $writer, FullSync $full_sync, Lock $lock, Logger $logger)
    {
        $this->client    = $client;
        $this->writer    = $writer;
        $this->full_sync = $full_sync;
        $this->lock      = $lock;
        $this->logger    = $logger;
    }

    public function run()
    {
        if (FullSync::is_running() || !$this->lock->acquire(self::LOCK, 10 * MINUTE_IN_SECONDS)) {
            return;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        try {
            $this->sync();
        } catch (Throwable $e) {
            $this->fail('خطای پیش‌بینی‌نشده: ' . $e->getMessage());
        } finally {
            $this->lock->release(self::LOCK);
        }
    }

    private function fail($message)
    {
        $this->logger->error('بروزرسانی خودکار محصولات ناموفق بود.', array('reason' => $message));
        Status::update(array('last_attempt' => Time::now_iso(), 'last_error' => $message));
    }

    private function sync()
    {
        $since       = Time::shifted_back(Status::synced_until(), self::OVERLAP_SECONDS);
        $fallback_at = Time::now_iso();
        $server_time = null;

        $products = array();
        $inactive = array();
        $page     = 1;

        do {
            $query = array('page' => $page, 'include_inactive' => 1);
            if ($since) {
                $query['since'] = $since;
            }

            $response = $this->client->get_data(Client::PRODUCTS_ENDPOINT . '?' . http_build_query($query));

            // The synced-until time must NOT advance when a page failed, or the
            // changes in that window are lost for good.
            if (is_wp_error($response)) {
                $this->fail(sprintf('صفحه %d: %s', $page, $response->get_error_message()));
                return;
            }

            if (!isset($response['data']) || !is_array($response['data'])) {
                $this->fail(sprintf('صفحه %d: قالب پاسخ نامعتبر است.', $page));
                return;
            }

            if ($server_time === null && !empty($response['server_time'])) {
                $server_time = $response['server_time'];
            }

            foreach ($response['data'] as $product) {
                $key = Sku::normalize($product['sku'] ?? '');
                if ($key === '') {
                    continue;
                }

                if (isset($product['is_active']) && !$product['is_active']) {
                    $inactive[$key] = $product['sku'];
                    continue;
                }

                $products[$key] = $product;
            }

            $has_more = isset($response['current_page'], $response['last_page']) && $response['current_page'] < $response['last_page'];
            $page++;

            if ($has_more) {
                usleep(self::PAGE_DELAY_MICRO);
            }
        } while ($has_more && $page <= self::MAX_PAGES);

        $stats = array('fetched' => count($products));

        if ($products || $inactive) {
            $result = $this->writer->apply_batch($products, $inactive);
            unset($result['matched']);
            $stats = array_merge($stats, $result);
        }

        $synced_at = $server_time ? $server_time : $fallback_at;
        Status::set_synced_until($synced_at);

        Status::update(array(
            'last_attempt' => Time::now_iso(),
            'last_success' => $synced_at,
            'last_error'   => null,
            'last_stats'   => $stats,
        ));
    }
}
