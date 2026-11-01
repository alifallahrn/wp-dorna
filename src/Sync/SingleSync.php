<?php

namespace WPDorna\Sync;

use WC_Product;
use WP_Error;
use WPDorna\Api\Client;

/**
 * "Update from Dorna" for one product (and its variations).
 */
class SingleSync
{
    private $client;
    private $writer;

    public function __construct(Client $client, ProductWriter $writer)
    {
        $this->client = $client;
        $this->writer = $writer;
    }

    /**
     * @return true|WP_Error
     */
    public function sync($product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('not_found', __('محصول پیدا نشد.', 'wp-dorna'));
        }

        $targets = $product->is_type('variable')
            ? array_filter(array_map('wc_get_product', $product->get_children()))
            : array($product);

        if (!$targets) {
            return new WP_Error('no_sku', __('این محصول متغیری، زیرمجموعه ندارد.', 'wp-dorna'));
        }

        foreach ($targets as $target) {
            if (trim((string) $target->get_sku()) === '') {
                return new WP_Error('no_sku', __('کالا باید کد کالا (SKU) داشته باشد؛ در متغیرها، همه‌ی متغیرها.', 'wp-dorna'));
            }
        }

        $currency = get_woocommerce_currency();

        foreach ($targets as $target) {
            $data = $this->client->get_data(Client::PRODUCT_BY_SKU_ENDPOINT . '/' . rawurlencode($target->get_sku()));

            if (is_wp_error($data)) {
                $status = $data->get_error_data();
                if (is_array($status) && isset($status['status']) && $status['status'] === 404) {
                    return new WP_Error('not_in_dorna', sprintf(
                        /* translators: %s: product SKU */
                        __('کالایی با کد %s در درنا پیدا نشد.', 'wp-dorna'),
                        $target->get_sku()
                    ));
                }

                return $data;
            }

            if (empty($data['sku'])) {
                return new WP_Error('not_in_dorna', __('کالا در درنا پیدا نشد.', 'wp-dorna'));
            }

            $this->writer->fill($target, $data, $currency);
            $target->save();
        }

        return true;
    }
}
