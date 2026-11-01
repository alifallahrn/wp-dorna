<?php

namespace WPDorna\Sync;

use Throwable;
use WC_Product;
use WPDorna\Support\Logger;
use WPDorna\Support\Money;
use WPDorna\Support\Sku;

/**
 * Puts Dorna product data (price, discount, stock) into WooCommerce products.
 * Shared by the minute sync, the full sync, the import and the single-product button.
 */
class ProductWriter
{
    // Woo has already reduced the stock for orders in this window that Dorna has not received.
    const PENDING_ORDER_WINDOW = 3 * DAY_IN_SECONDS;

    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Normalized SKU => [post ids] for every live product / variation.
     */
    public function sku_map()
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value AS sku
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_sku'
              AND pm.meta_value <> ''
              AND p.post_type IN ('product', 'product_variation')
              AND p.post_status <> 'trash'"
        );

        $map = array();
        foreach ((array) $rows as $row) {
            $key = Sku::normalize($row->sku);
            if ($key !== '') {
                $map[$key][] = (int) $row->post_id;
            }
        }

        return $map;
    }

    /**
     * Product / variation ids (as array keys) of recent orders whose stock Woo
     * already reduced but whose invoice Dorna has not received.
     */
    public function pending_invoice_product_ids()
    {
        $orders = wc_get_orders(array(
            'status'       => array('on-hold', 'processing', 'completed'),
            'limit'        => 200,
            'date_created' => '>' . (time() - self::PENDING_ORDER_WINDOW),
            'meta_key'     => '_dorna_invoice_sent',
            'meta_compare' => 'NOT EXISTS',
        ));

        $ids = array();
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $ids[(int) $item->get_product_id()] = true;
                if ($item->get_variation_id()) {
                    $ids[(int) $item->get_variation_id()] = true;
                }
            }
        }

        return $ids;
    }

    /**
     * Writes a batch of Dorna products (keyed by normalized SKU) to the store.
     *
     * @param array $products active Dorna products
     * @param array $inactive normalized SKU => SKU of products Dorna deactivated (reported, not changed)
     * @return array counters plus `matched`: normalized SKUs that exist in the store
     */
    public function apply_batch(array $products, array $inactive = array())
    {
        $stats = array('updated' => 0, 'unchanged' => 0, 'failed' => 0, 'stock_held' => 0, 'inactive' => 0, 'missing' => 0, 'matched' => array());

        $map = $this->sku_map();

        foreach ($inactive as $key => $sku) {
            if (isset($map[$key])) {
                $stats['inactive']++;
                $stats['matched'][] = $key;
                $this->logger->warning('کالا در درنا غیرفعال است ولی در سایت هست (تغییری نکرد).', array('sku' => $sku));
            }
        }

        $pending  = $products ? $this->pending_invoice_product_ids() : array();
        $currency = get_woocommerce_currency();

        foreach ($products as $key => $data) {
            if (!isset($map[$key])) {
                $stats['missing']++;
                continue;
            }

            $stats['matched'][] = $key;

            if (count($map[$key]) > 1) {
                $this->logger->warning('یک کد کالا (SKU) برای چند محصول ثبت شده است.', array('sku' => $data['sku'], 'products' => $map[$key]));
            }

            foreach ($map[$key] as $woo_id) {
                try {
                    $product = wc_get_product($woo_id);
                    if (!$product) {
                        continue;
                    }

                    $held = isset($pending[$woo_id]);
                    $held && $stats['stock_held']++;

                    $this->fill($product, $data, $currency, !$held);

                    if ($product->get_changes()) {
                        $product->save();
                        $stats['updated']++;
                    } else {
                        $stats['unchanged']++;
                    }
                } catch (Throwable $e) {
                    $stats['failed']++;
                    $this->logger->error('بروزرسانی کالا انجام نشد.', array('sku' => $data['sku'], 'product' => $woo_id, 'reason' => $e->getMessage()));
                }
            }
        }

        return $stats;
    }

    /**
     * Sets price / discount (and stock unless it must be held) on the product
     * object. The caller saves.
     */
    public function fill(WC_Product $product, array $data, $currency, $with_stock = true)
    {
        $original   = $data['original_price'] ?? $data['sale_price'] ?? 0;
        $discounted = $data['discounted_price'] ?? null;
        $active     = !empty($data['discount_active']);

        $original   = Money::from_dorna($original, $currency);
        $discounted = Money::from_dorna($discounted, $currency);

        $product->set_regular_price($original);

        if ($active && $discounted !== null) {
            $product->set_sale_price($discounted);
            $product->set_price($discounted);
            if (!empty($data['discount_start_date'])) {
                $product->set_date_on_sale_from(strtotime($data['discount_start_date']));
            }
            if (!empty($data['discount_end_date'])) {
                $product->set_date_on_sale_to(strtotime($data['discount_end_date']));
            }
        } else {
            $product->set_sale_price('');
            $product->set_price($original);
            $product->set_date_on_sale_from('');
            $product->set_date_on_sale_to('');
        }

        if ($with_stock && isset($data['stock']) && is_numeric($data['stock'])) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity(wc_stock_amount($data['stock']));
        }
    }
}
