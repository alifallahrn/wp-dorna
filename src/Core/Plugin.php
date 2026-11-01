<?php

namespace WPDorna\Core;

use WPDorna\Admin\Admin;

class Plugin
{
    public static function boot()
    {
        // WooCommerce is required: without it nothing that touches orders or
        // products may run (cron, hooks, screens), so only say why.
        if (!class_exists('WooCommerce')) {
            if (is_admin()) {
                add_action('admin_notices', array(__CLASS__, 'missing_woocommerce_notice'));
            }

            return;
        }

        $services = new Services();

        $scheduler = new Scheduler($services->incremental, $services->retry, $services->logger);
        $scheduler->register();

        $services->full_sync->register();
        $services->invoices->register();

        if (is_admin()) {
            (new Admin($services))->register();
        }
    }

    public static function missing_woocommerce_notice()
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__('افزونه‌ی اتصال به درنا برای کار کردن به ووکامرس نیاز دارد. ووکامرس را نصب و فعال کنید.', 'wp-dorna')
        );
    }
}
