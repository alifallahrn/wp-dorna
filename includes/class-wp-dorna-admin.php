<?php

class WP_Dorna_Admin
{
    public function init()
    {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));

        add_action('wp_ajax_wp_dorna_get_products', array($this, 'ajax_get_products'));
        add_action('wp_ajax_wp_dorna_import_product', array($this, 'ajax_import_product'));

        add_action('post_submitbox_misc_actions', array($this, 'add_sync_button'));
        add_action('wp_ajax_wp_dorna_sync_product', array($this, 'ajax_sync_product'));
    }

    public function ajax_get_products()
    {
        check_ajax_referer('wp_dorna_get_products_nonce');

        $api = new WP_Dorna_API();
        $products = $api->get_data($api::PRODUCTS_ENDPOINT);

        if (is_wp_error($products)) {
            $this->log_error('WP Dorna API Error: ' . $products->get_error_message());
            wp_send_json_error(array('message' => $products->get_error_message()), 400);
        }

        if (!is_array($products) || empty($products['data'])) {
            $this->log_error('WP Dorna: No products found or invalid response format.');
            wp_send_json_error(array('message' => 'No products found or invalid response format.'), 404);
        }

        $new_products = array();
        foreach ($products['data'] as $product) {
            if (!isset($product['sku'])) {
                continue;
            }

            $existing_product_id = wc_get_product_id_by_sku($product['sku']);
            if (empty($existing_product_id)) {
                $new_products[] = $product;
            }
        }

        wp_send_json_success([
            'total'    => count($products),
            'products' => $new_products,
        ]);
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
            wp_send_json_error(array('message' => 'کالا با این کد کالا قبلا وارد شده است.'));
        }

        $currency = get_woocommerce_currency();
        if ($currency == 'IRT') {
            $product_data['sale_price'] = $product_data['sale_price'] / 10;
        }

        $new_product = new WC_Product_Simple();
        $new_product->set_status('draft');
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

    public function add_sync_button()
    {
        global $post;

        if ($post->post_type !== 'product') return;

        $product = wc_get_product($post->ID);
        if (!$product) return;

        $btnLabel = '🔄 بروزرسانی از درنا';
?>
        <div class="misc-pub-section">
            <button
                type="button"
                class="button button-secondary"
                id="wp-dorna-sync-product-btn"
                data-product-id="<?php echo esc_attr($post->ID); ?>">
                <?php echo $btnLabel; ?>
            </button>
            <span id="wp-dorna-sync-status" style="margin-left:10px; display:none;"></span>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#wp-dorna-sync-product-btn').on('click', function() {
                    var $btn = $(this);
                    var productId = $btn.data('product-id');

                    $btn.text('در حال بروزرسانی...');
                    $btn.prop('disabled', true);

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'wp_dorna_sync_product',
                            product_id: productId,
                            _ajax_nonce: '<?php echo wp_create_nonce('wp_dorna_sync_product_nonce'); ?>'
                        },
                        success: function(response) {
                            $btn.text('<?php echo $btnLabel; ?>');
                            $btn.prop('disabled', false);
                            if (response.success) {
                                alert('✅ ' + response.data.message);
                                window.location.reload();
                            } else {
                                alert('❌ ' + response.data.message);
                            }
                        },
                        error: function() {
                            $btn.text('<?php echo $btnLabel; ?>');
                            $btn.prop('disabled', false);
                            alert('❌ خطا در ارتباط با درنا');
                        }
                    });
                });
            });
        </script>
    <?php
    }

    public function ajax_sync_product()
    {
        check_ajax_referer('wp_dorna_sync_product_nonce');

        $product_id = intval($_POST['product_id'] ?? 0);

        if (empty($product_id)) {
            wp_send_json_error(['message' => 'کالا معتبر نیست.']);
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => 'محصول یافت نشد.']);
        }

        $currency = get_woocommerce_currency();

        if ($product->is_type('simple')) {

            if (empty($product->get_sku())) {
                wp_send_json_error(['message' => 'کالا باید کد کالا (SKU) داشته باشد.']);
            }

            $api = new WP_Dorna_API();
            $endpoint = $api::PRODUCT_BY_SKU_ENDPOINT . '/' . urlencode($product->get_sku());
            $response = $api->get_data($endpoint);

            if (is_wp_error($response)) {
                wp_send_json_error(['message' => $response->get_error_message()]);
            }

            if (empty($response['sku'])) {
                wp_send_json_error(['message' => 'کالا در درنا یافت نشد.']);
            }
            if ($currency == 'IRT') {
                $response['sale_price'] = $response['sale_price'] / 10;
            }

            $price = $response['sale_price'];
            $stock = $response['stock'];
            $product->set_price($price);
            $product->set_regular_price($price);
            $product->set_manage_stock(true);
            $product->set_stock_quantity($stock);
            $product->save();
        }

        if ($product->is_type('variable')) {
            $variations = $product->get_children();
            foreach ($variations as $variation_id) {
                $variation = wc_get_product($variation_id);

                if (empty($variation->get_sku())) {
                    wp_send_json_error(['message' => 'تمامی متغیرها باید کد کالا (SKU) داشته باشند.']);
                }

                $api = new WP_Dorna_API();
                $endpoint = $api::PRODUCT_BY_SKU_ENDPOINT . '/' . urlencode($variation->get_sku());
                $response = $api->get_data($endpoint);

                if (!is_array($response) || empty($response['sku'])) continue;

                if ($currency == 'IRT') {
                    $response['sale_price'] = $response['sale_price'] / 10;
                }

                $price = $response['sale_price'];
                $stock = $response['stock'];

                $variation->set_price($price);
                $variation->set_regular_price($price);
                $variation->set_manage_stock(true);
                $variation->set_stock_quantity($stock);
                $variation->save();
            }
        }

        wp_send_json_success(['message' => 'محصول با موفقیت از درنا بروزرسانی شد.']);
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
        $lastSync = get_option('wp_dorna_last_product_update', '');
        $log_file = WP_DORNA_PLUGIN_DIR . 'logs/' . date('Y-m-d') . '.log';
    ?>

        <div class="wrap wp-dorna-settings">
            <style>
                @import url('https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/font-face.css');

                .wp-dorna-settings {
                    font-family: Vazir, Tahoma, sans-serif !important;
                    background: #F9FAFB;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
                    margin-top: 20px;
                    color: #1E293B;
                }

                /* Typography */
                .wp-dorna-settings h1,
                .wp-dorna-settings h2,
                .wp-dorna-settings h3,
                .wp-dorna-settings h4,
                .wp-dorna-settings h5,
                .wp-dorna-settings h6 {
                    font-family: Vazir, Tahoma, sans-serif !important;
                    color: #1E293B;
                }

                /* Header */
                .wp-dorna-header {
                    background: #FFFFFF;
                    border-bottom: 1px solid #E5E7EB;
                    color: #1E293B;
                    padding: 25px 30px;
                    text-align: right;
                }

                .wp-dorna-header h1 {
                    margin: 0;
                    font-size: 22px;
                    font-weight: 600;
                }

                .wp-dorna-header p {
                    margin-top: 5px;
                    font-size: 14px;
                    color: #64748B;
                }

                /* Layout */
                .wp-dorna-body {
                    display: flex;
                    background: #F9FAFB;
                }

                /* Sidebar */
                .wp-dorna-sidebar {
                    width: 230px;
                    border-left: 1px solid #E5E7EB;
                    background: #FFFFFF;
                    padding: 15px 0;
                }

                .wp-dorna-tab {
                    display: block;
                    padding: 12px 20px;
                    color: #334155;
                    cursor: pointer;
                    border-right: 4px solid transparent;
                    transition: all 0.2s ease;
                    font-weight: 500;
                    border-radius: 6px 0 0 6px;
                    margin: 4px 10px;
                }

                .wp-dorna-tab:hover {
                    background: #F1F5F9;
                }

                .wp-dorna-tab.active {
                    background: #EEF2FF;
                    border-color: #2563EB;
                    color: #2563EB;
                }

                /* Content */
                .wp-dorna-content {
                    flex: 1;
                    padding: 30px 40px;
                    background: #FFFFFF;
                    border-radius: 0 0 12px 0;
                }

                .wp-dorna-content h2 {
                    font-size: 18px;
                    border-bottom: 1px solid #E5E7EB;
                    padding-bottom: 8px;
                    margin-bottom: 20px;
                    color: #2563EB;
                }

                /* Boxes */
                .wp-dorna-box {
                    background: #FFFFFF;
                    border: 1px solid #E5E7EB;
                    border-radius: 10px;
                    padding: 18px 20px;
                    margin-bottom: 25px;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
                }

                /* Form elements */
                input[type="text"],
                input[type="password"],
                input[type="url"],
                select {
                    width: 100%;
                    padding: 10px 12px;
                    border: 1px solid #D1D5DB;
                    border-radius: 8px;
                    font-size: 14px;
                    font-family: Vazir;
                    margin-top: 5px;
                    background: #F9FAFB;
                    color: #1E293B;
                }

                input[type="text"]:focus,
                select:focus {
                    border-color: #2563EB;
                    outline: none;
                    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
                }

                /* Buttons */
                .button-primary {
                    background: #2563EB !important;
                    border: none !important;
                    border-radius: 8px !important;
                    padding: 9px 22px !important;
                    font-family: Vazir;
                    font-size: 14px;
                    transition: background 0.3s;
                }

                .button-primary:hover {
                    background: #1D4ED8 !important;
                }

                pre {
                    direction: ltr;
                    background: #F9FAFB;
                    padding: 12px;
                    border-radius: 8px;
                    border: 1px solid #E5E7EB;
                    font-size: 13px;
                    max-height: 300px;
                    overflow-y: auto;
                    color: #334155;
                }
            </style>

            <!-- Header -->
            <div class="wp-dorna-header">
                <h1>درنا - اتصال به ووکامرس</h1>
                <p>مدیریت اتصال و هماهنگ‌سازی بین سیستم درنا و فروشگاه شما</p>
            </div>

            <!-- Body -->
            <div class="wp-dorna-body">
                <!-- Sidebar -->
                <div class="wp-dorna-sidebar">
                    <div class="wp-dorna-tab active" data-tab="settings">تنظیمات</div>
                    <div class="wp-dorna-tab" data-tab="import">وارد کردن محصولات</div>
                    <div class="wp-dorna-tab" data-tab="logs">لاگ خطاها</div>
                </div>

                <!-- Content -->
                <div class="wp-dorna-content">
                    <!-- Settings -->
                    <div id="tab-settings" class="wp-dorna-tab-content active">
                        <form method="post" action="options.php">
                            <?php
                            settings_fields('wp_dorna_settings_group');
                            do_settings_sections('wp-dorna-settings');
                            submit_button('ذخیره تنظیمات');
                            ?>
                        </form>

                        <div class="wp-dorna-box">
                            <h3>آخرین بروزرسانی محصولات</h3>
                            <p><?php echo $lastSync ? esc_html($lastSync) : '-'; ?></p>
                        </div>
                    </div>

                    <!-- Import -->
                    <div id="tab-import" class="wp-dorna-tab-content" style="display:none;">
                        <h2>وارد کردن محصولات</h2>
                        <p>با کلیک بر روی دکمه زیر، محصولات شما از درنا به ووکامرس منتقل خواهند شد.</p>
                        <button id="wp-dorna-import-products" class="button button-primary">شروع وارد کردن</button>

                        <div id="wp-dorna-import-status" class="wp-dorna-box" style="display:none; margin-top:15px;"></div>
                    </div>

                    <!-- Logs -->
                    <div id="tab-logs" class="wp-dorna-tab-content" style="display:none;">
                        <h2>لاگ خطاهای امروز</h2>
                        <div class="wp-dorna-box">
                            <?php if (file_exists($log_file)) { ?>
                                <pre id="wp-dorna-error-log">
                                    <?php echo esc_html(file_get_contents($log_file)); ?>
                                </pre>
                            <?php } else { ?>
                                <p>هیچ خطایی ثبت نشده است.</p>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const tabs = document.querySelectorAll(".wp-dorna-tab");
                    const contents = document.querySelectorAll(".wp-dorna-tab-content");

                    tabs.forEach(tab => {
                        tab.addEventListener("click", () => {
                            tabs.forEach(t => t.classList.remove("active"));
                            contents.forEach(c => c.style.display = "none");
                            tab.classList.add("active");
                            document.getElementById("tab-" + tab.dataset.tab).style.display = "block";
                        });
                    });
                });
            </script>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#wp-dorna-import-products').on('click', function() {
                    var $button = $(this);
                    var $status = $('#wp-dorna-import-status');

                    $status.show();
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
                                var products = response.data.products;
                                var import_count = products.length;
                                var total = response.data.total;
                                var imported = 0;

                                if (total === 0) {
                                    $status.append('<br>هیچ کالایی در درنا وجود ندارد.');
                                    $button.removeAttr('disabled');
                                    return;
                                }

                                $status.append('<br>تعداد کل کالاها در درنا: ' + total);

                                if (import_count === 0) {
                                    $status.append('<br>کالای جدیدی برای وارد کردن وجود ندارد.');
                                    $button.removeAttr('disabled');
                                    return;
                                }

                                $status.append('<br>تعداد کالاهای جدید برای وارد کردن: ' + import_count);

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
                                                $status.append('<br>کالا با کد ' + product.sku + ' با موفقیت وارد شد. (' + imported + '/' + import_count + ')');
                                            } else {
                                                $status.append('<br>خطا در وارد کردن کالا با کد ' + product.sku + ': ' + importResponse.data.message);
                                            }
                                            importNextProduct();
                                        },
                                        error: function() {
                                            $status.append('<br>خطا در وارد کردن کالا با کد ' + product.sku);
                                            importNextProduct();
                                        }
                                    });
                                }

                                importNextProduct();
                            } else {
                                $status.append('<br>خطایی هنگام دریافت کالاها از درنا رخ داد: ' + response.data.message);
                                $button.removeAttr('disabled');
                            }
                        },
                        error: function() {
                            $status.append('<br>خطایی هنگام دریافت کالاها از درنا رخ داد.');
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
