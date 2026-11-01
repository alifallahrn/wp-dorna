<?php

namespace WPDorna\Admin;

use WPDorna\Core\Services;

/**
 * CSS / JS load only on the plugin's own screens, the product edit screen (sync
 * button) and the order screens (Dorna box / column).
 */
class Assets
{
    private $services;
    private $admin;

    public function __construct(Services $services, Admin $admin)
    {
        $this->services = $services;
        $this->admin    = $admin;
    }

    public function register()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
    }

    private function wanted()
    {
        if ($this->admin->is_plugin_screen()) {
            return true;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        $order_screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';

        return in_array($screen->id, array('product', 'edit-shop_order', 'shop_order', $order_screen), true);
    }

    public function enqueue()
    {
        if (!$this->wanted()) {
            return;
        }

        wp_enqueue_style('wp-dorna-admin', WP_DORNA_PLUGIN_URL . 'assets/css/admin.css', array(), WP_DORNA_VERSION);
        wp_enqueue_script('wp-dorna-admin', WP_DORNA_PLUGIN_URL . 'assets/js/admin.js', array(), WP_DORNA_VERSION, true);

        wp_localize_script('wp-dorna-admin', 'wpDorna', array(
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(Ajax::NONCE),
            'fullSync' => $this->admin->is_plugin_screen() ? $this->services->full_sync->payload() : null,
            'i18n'     => array(
                'networkError' => __('ارتباط با سرور وردپرس برقرار نشد. دوباره تلاش کنید.', 'wp-dorna'),
                'confirmCancel' => __('سینک کامل لغو شود؟ کالاهایی که تا الان بروزرسانی شده‌اند می‌مانند.', 'wp-dorna'),
                'confirmClear' => __('همه‌ی گزارش‌ها پاک شود؟', 'wp-dorna'),
                'sending'      => __('در حال ارسال…', 'wp-dorna'),
                'send'         => __('ارسال به درنا', 'wp-dorna'),
                'syncing'      => __('در حال بروزرسانی…', 'wp-dorna'),
                'sync'         => __('بروزرسانی از درنا', 'wp-dorna'),
                'copied'       => __('کپی شد', 'wp-dorna'),
                'confirmDisconnect' => __('اتصال به درنا قطع شود؟ تا وصل کردن دوباره، قیمت و موجودی بروزرسانی نمی‌شود و فاکتور سفارش‌ها ثبت نمی‌شود.', 'wp-dorna'),
                'states'       => array(
                    'idle'      => __('آماده‌ی شروع', 'wp-dorna'),
                    'running'   => __('در حال اجرا', 'wp-dorna'),
                    'paused'    => __('متوقف‌شده', 'wp-dorna'),
                    'completed' => __('تکمیل شد', 'wp-dorna'),
                    'cancelled' => __('لغو شد', 'wp-dorna'),
                    'failed'    => __('به‌دلیل خطاهای پیاپی متوقف شد', 'wp-dorna'),
                ),
            ),
        ));
    }
}
