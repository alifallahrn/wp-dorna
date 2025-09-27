<?php

class WP_Dorna_Admin
{
    public function init()
    {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));

        add_action('wp_ajax_wp_dorna_get_products', array($this, 'ajax_get_products'));
        add_action('wp_ajax_wp_dorna_import_product', array($this, 'ajax_import_product'));
    }

    public function ajax_get_products()
    {
        check_ajax_referer('wp_dorna_get_products_nonce');

        $api = new WP_Dorna_API();
        $products = $api->get_data($api::PRODUCTS_ENDPOINT);
        $this->log_error('WP Dorna: Fetched ' . (is_array($products) ? count($products) : '0') . ' products from API.');

        if (is_wp_error($products)) {
            wp_send_json_error(array('message' => $products->get_error_message()), 400);
        } else {
            wp_send_json_success($products);
        }
    }

    public function ajax_import_product()
    {
        check_ajax_referer('wp_dorna_import_product_nonce');

        if (!isset($_POST['product']) || empty($_POST['product'])) {
            $this->log_error('WP Dorna: No product data provided.');
            wp_send_json_error(array('message' => 'No product data provided.'));
        }

        $product_data = json_decode(stripslashes($_POST['product']), true);

        if (empty($product_data) || !is_array($product_data)) {
            $this->log_error('WP Dorna: Invalid product data.');
            wp_send_json_error(array('message' => 'Invalid product data.'));
        }

        if (!isset($product_data['sku']) || empty($product_data['sku'])) {
            $this->log_error('WP Dorna: Product SKU is required.');
            wp_send_json_error(array('message' => 'Product SKU is required.'));
        }

        $existing_products = wc_get_products(array(
            'sku' => $product_data['sku'],
            'limit' => 1,
        ));

        if (!empty($existing_products)) {
            wp_send_json_error(array('message' => 'کالا با این کد کالا قبلا وارد شده است: ' . $product_data['sku']));
        }

        // change price unit from rials to tomans
        $product_data['sale_price'] = $product_data['sale_price'] / 10;

        $new_product = new WC_Product_Simple();
        $new_product->set_name($product_data['name']);
        $new_product->set_sku($product_data['sku']);
        $new_product->set_price($product_data['sale_price']);
        $new_product->set_regular_price($product_data['sale_price']);
        $new_product->set_manage_stock(true);
        $new_product->set_stock_quantity($product_data['stock']);
        $new_product->save();

        $this->log_error('WP Dorna: Product ' . $product_data['name'] . ' imported successfully.');

        wp_send_json_success(array('message' => 'کالا ' . $product_data['name'] . ' با موفقیت وارد شد.'));
    }

    public function register_settings()
    {
        register_setting('wp_dorna_settings_group', WP_DORNA_OPTION_NAME);

        add_settings_section(
            'wp_dorna_main_section',
            'تنظیمات اصلی',
            null,
            'wp-dorna-settings'
        );

        add_settings_field(
            'api_key',
            'توکن',
            array($this, 'api_key_callback'),
            'wp-dorna-settings',
            'wp_dorna_main_section'
        );
    }

    public function api_key_callback()
    {
        $options = get_option(WP_DORNA_OPTION_NAME);
?>
        <input type="text" name="<?php echo WP_DORNA_OPTION_NAME; ?>[api_key]" value="<?php echo isset($options['api_key']) ? esc_attr($options['api_key']) : ''; ?>" style="width: 300px;">
    <?php
    }

    public function add_settings_page()
    {
        add_options_page(
            'اتصال به درنا',
            'اتصال به درنا',
            'manage_options',
            'wp-dorna-settings',
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page()
    {
    ?>
        <div class="wrap">
            <h1>تنظیمات اتصال به درنا</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_dorna_settings_group');
                do_settings_sections('wp-dorna-settings');
                submit_button();
                ?>
            </form>
            <h2>وارد کردن محصولات</h2>
            <button id="wp-dorna-import-products" class="button button-primary">وارد کردن محصولات از درنا</button>
            <div id="wp-dorna-import-status"></div>

            <h2>لاگ خطاهای امروز</h2>
            <pre id="wp-dorna-error-log" dir="ltr" style="background: #f1f1f1; border: 1px solid #ccc; padding: 10px; max-height: 300px; overflow: auto;">
                <?php
                $log_file = WP_DORNA_PLUGIN_DIR . 'logs/' . date('Y-m-d') . '.log';
                if (file_exists($log_file)) {
                    echo esc_html(file_get_contents($log_file));
                } else {
                    echo 'لاگ خطایی وجود ندارد.';
                }
                ?>
            </pre>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#wp-dorna-import-products').on('click', function() {
                    var $button = $(this);
                    var $status = $('#wp-dorna-import-status');
                    $button.attr('disabled', 'disabled');
                    $status.html('در حال دریافت کالاها از درنا ...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'wp_dorna_get_products',
                            _ajax_nonce: '<?php echo wp_create_nonce('wp_dorna_get_products_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var products = response.data;
                                var total = products.length;
                                var imported = 0;

                                if (total === 0) {
                                    $status.html('کالایی در درنا پیدا نشد.');
                                    $button.removeAttr('disabled');
                                    return;
                                }

                                $status.html('در حال وارد کردن ' + total + ' کالا ...');

                                function importNextProduct() {
                                    if (products.length === 0) {
                                        $status.append('<br>تمامی کالاها وارد شدند.');
                                        $button.removeAttr('disabled');
                                        return;
                                    }

                                    var product = products.shift();

                                    $.ajax({
                                        url: ajaxurl,
                                        method: 'POST',
                                        data: {
                                            action: 'wp_dorna_import_product',
                                            product: JSON.stringify(product),
                                            _ajax_nonce: '<?php echo wp_create_nonce('wp_dorna_import_product_nonce'); ?>'
                                        },
                                        success: function(importResponse) {
                                            if (importResponse.success) {
                                                imported++;
                                                $status.html('وارد شده ' + imported + ' از ' + total + ' کالا ...');
                                            } else {
                                                $status.append('<br>خطا در وارد کردن کالا با SKU ' + product.sku + ': ' + importResponse.data.message);
                                            }
                                            importNextProduct();
                                        },
                                        error: function() {
                                            $status.append('<br>خطا در وارد کردن کالا با SKU ' + product.sku);
                                            importNextProduct();
                                        }
                                    });
                                }

                                importNextProduct();
                            } else {
                                $status.html('خطا در دریافت کالاها از درنا: ' + response.data.message);
                                $button.removeAttr('disabled');
                            }
                        },
                        error: function() {
                            $status.html('خطایی هنگام دریافت کالاها از درنا رخ داد.');
                            $button.removeAttr('disabled');
                        }
                    });
                });
            });
        </script>
<?php
    }

    // save error logs to files day to day in plugin folder
    public function log_error($message)
    {
        $log_file = WP_DORNA_PLUGIN_DIR . 'logs/' . date('Y-m-d') . '.log';
        error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", 3, $log_file);
    }
}
