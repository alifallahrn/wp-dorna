<?php

namespace WPDorna\Import;

use Throwable;
use WC_Product_Simple;
use WP_Error;
use WPDorna\Api\Client;
use WPDorna\Support\Logger;
use WPDorna\Support\Sku;
use WPDorna\Sync\ProductWriter;

/**
 * Brings Dorna products that the store does not have yet in as drafts.
 *
 * The browser walks Dorna's pages one request at a time (scan), the products
 * found are kept in a per-user transient, and the import step works from that
 * list by SKU: the browser never supplies product data.
 */
class ImportService
{
    const QUEUE_TTL = HOUR_IN_SECONDS;

    private $client;
    private $writer;
    private $logger;

    public function __construct(Client $client, ProductWriter $writer, Logger $logger)
    {
        $this->client = $client;
        $this->writer = $writer;
        $this->logger = $logger;
    }

    private function queue_key()
    {
        return 'wp_dorna_import_' . get_current_user_id();
    }

    /**
     * Reads one Dorna page and queues the products that are not in the store.
     *
     * @return array|WP_Error page, last_page, total, found (this page), queued (so far), sample
     */
    public function scan_page($page)
    {
        $page     = max(1, (int) $page);
        $response = $this->client->get_data(Client::PRODUCTS_ENDPOINT . '?' . http_build_query(array('page' => $page)));

        if (is_wp_error($response)) {
            return $response;
        }

        if (!isset($response['data']) || !is_array($response['data'])) {
            return new WP_Error('api_error', __('قالب پاسخ درنا نامعتبر است.', 'wp-dorna'));
        }

        $queue = $page === 1 ? array() : get_transient($this->queue_key());
        $queue = is_array($queue) ? $queue : array();
        $map   = $this->writer->sku_map();
        $found = 0;

        foreach ($response['data'] as $product) {
            $key = Sku::normalize($product['sku'] ?? '');

            if ($key === '' || isset($map[$key]) || isset($queue[$key]) || empty($product['name'])) {
                continue;
            }

            $queue[$key] = array(
                'sku'   => (string) $product['sku'],
                'name'  => (string) $product['name'],
                'stock' => $product['stock'] ?? null,
                'price' => $product['original_price'] ?? $product['sale_price'] ?? null,
                'data'  => $product,
            );
            $found++;
        }

        set_transient($this->queue_key(), $queue, self::QUEUE_TTL);

        return array(
            'page'      => $page,
            'last_page' => (int) ($response['last_page'] ?? $page),
            'total'     => (int) ($response['total'] ?? 0),
            'found'     => $found,
            'queued'    => count($queue),
        );
    }

    /**
     * @return array list of ['sku', 'name', 'stock', 'price'] queued by the scan
     */
    public function queued()
    {
        $queue = get_transient($this->queue_key());

        return is_array($queue) ? array_values(array_map(function ($row) {
            return array_intersect_key($row, array_flip(array('sku', 'name', 'stock', 'price')));
        }, $queue)) : array();
    }

    /**
     * @return string|WP_Error the imported product's name
     */
    public function import($sku)
    {
        $key   = Sku::normalize($sku);
        $queue = get_transient($this->queue_key());

        if (!is_array($queue) || !isset($queue[$key])) {
            return new WP_Error('not_queued', __('این کالا در فهرست بررسی‌شده نیست؛ دوباره بررسی را شروع کنید.', 'wp-dorna'));
        }

        $row = $queue[$key];

        if (wc_get_product_id_by_sku($row['sku'])) {
            unset($queue[$key]);
            set_transient($this->queue_key(), $queue, self::QUEUE_TTL);

            return new WP_Error('exists', __('کالایی با این کد از قبل در سایت هست.', 'wp-dorna'));
        }

        try {
            $product = new WC_Product_Simple();
            $product->set_status('draft');
            $product->set_name($row['name']);
            $product->set_sku($row['sku']);
            $this->writer->fill($product, $row['data'], get_woocommerce_currency());
            $product->save();
        } catch (Throwable $e) {
            $this->logger->error('ورود کالا انجام نشد.', array('sku' => $row['sku'], 'reason' => $e->getMessage()));

            return new WP_Error('import_failed', $e->getMessage());
        }

        unset($queue[$key]);
        set_transient($this->queue_key(), $queue, self::QUEUE_TTL);

        return $row['name'];
    }
}
