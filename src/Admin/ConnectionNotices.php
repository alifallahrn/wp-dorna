<?php

namespace WPDorna\Admin;

use WPDorna\Core\Connection;
use WPDorna\Core\Services;

/**
 * A WordPress notice, on every admin screen, when Dorna refuses this site: a
 * revoked / replaced token, or a token that belongs to another address (this site
 * is probably a copy). Without it the owner only finds out when stock goes stale.
 */
class ConnectionNotices
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
        add_action('admin_notices', array($this, 'render'));
    }

    public function render()
    {
        if (!current_user_can('manage_woocommerce') || $this->admin->is_plugin_screen()) {
            return;
        }

        $status = $this->services->connection->status();

        if (!in_array($status, array(Connection::REJECTED, Connection::MISMATCH), true)) {
            return;
        }

        $message = $status === Connection::MISMATCH
            ? __('این سایت با توکن سایت دیگری به درنا وصل است؛ احتمالاً نسخه‌ی کپی (تستی) سایت اصلی است. درنا درخواست‌هایش را رد می‌کند و همگام‌سازی انجام نمی‌شود.', 'wp-dorna')
            : __('درنا توکن این سایت را قبول نمی‌کند (اتصال از درنا قطع شده یا سایت دیگری جایش وصل شده). همگام‌سازی و ارسال فاکتورها متوقف است.', 'wp-dorna');

        printf(
            '<div class="notice notice-error"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
            esc_html__('اتصال به درنا:', 'wp-dorna'),
            esc_html($message),
            esc_url(admin_url('admin.php?page=wp-dorna-settings')),
            esc_html__('رفتن به تنظیمات اتصال', 'wp-dorna')
        );
    }
}
