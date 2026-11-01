<?php

namespace WPDorna\Admin\Pages;

use WPDorna\Admin\View;
use WPDorna\Core\Scheduler;
use WPDorna\Orders\InvoiceState;
use WPDorna\Sync\FullSync;
use WPDorna\Sync\Status;

class DashboardPage extends Page
{
    const WINDOW = 30 * DAY_IN_SECONDS;

    public function slug()
    {
        return 'wp-dorna';
    }

    public function label()
    {
        return __('نمای کلی', 'wp-dorna');
    }

    public function icon()
    {
        return 'home';
    }

    public function title()
    {
        return __('نمای کلی', 'wp-dorna');
    }

    public function purpose()
    {
        return __('وضعیت اتصال سایت به درنا در یک نگاه.', 'wp-dorna');
    }

    public function render()
    {
        $settings = $this->services->settings;
        $status   = Status::get();
        $full     = FullSync::get_state();
        $orders   = $this->order_counts();

        View::render('pages/dashboard', array(
            'has_key'   => $settings->has_key(),
            'status'    => $status,
            'full'      => $full,
            'orders'    => $orders,
            'next_run'  => wp_next_scheduled(Scheduler::PRODUCTS_HOOK),
            'callouts'  => $this->callouts($settings->has_key(), $status, $full, $orders),
            'sync_url'  => $this->url_for('wp-dorna-sync'),
            'order_url' => $this->url_for('wp-dorna-orders'),
        ));
    }

    private function url_for($slug, array $args = array())
    {
        return add_query_arg(array_merge(array('page' => $slug), $args), admin_url('admin.php'));
    }

    /**
     * Orders of the last 30 days that reached Dorna / did not (cached a minute).
     */
    private function order_counts()
    {
        $cached = get_transient('wp_dorna_order_counts');
        if (is_array($cached)) {
            return $cached;
        }

        $count = function ($compare) {
            $result = wc_get_orders(array(
                'status'       => array('processing', 'completed'),
                'limit'        => 1,
                'paginate'     => true,
                'return'       => 'ids',
                'date_created' => '>' . (time() - self::WINDOW),
                'meta_key'     => InvoiceState::SENT,
                'meta_compare' => $compare,
            ));

            return (int) $result->total;
        };

        $counts = array('sent' => $count('EXISTS'), 'unsent' => $count('NOT EXISTS'));
        set_transient('wp_dorna_order_counts', $counts, MINUTE_IN_SECONDS);

        return $counts;
    }

    private function callouts($has_key, array $status, array $full, array $orders)
    {
        $callouts = array();

        if (!$has_key) {
            $callouts[] = array(
                'tone'   => 'bad',
                'icon'   => 'alert',
                'title'  => __('هنوز به درنا وصل نشده‌اید', 'wp-dorna'),
                'text'   => __('توکن را از پنل درنا کپی کنید و در تنظیمات وارد کنید تا همگام‌سازی شروع شود.', 'wp-dorna'),
                'action' => array(__('رفتن به تنظیمات', 'wp-dorna'), $this->url_for('wp-dorna-settings')),
            );

            return $callouts;
        }

        if (!empty($status['last_error'])) {
            $callouts[] = array(
                'tone'   => 'warn',
                'icon'   => 'alert',
                'title'  => __('آخرین بروزرسانی خودکار موفق نبود', 'wp-dorna'),
                'text'   => $status['last_error'],
                'action' => array(__('مشاهده گزارش‌ها', 'wp-dorna'), $this->url_for('wp-dorna-logs')),
            );
        }

        if ($orders['unsent'] > 0) {
            $callouts[] = array(
                'tone'   => 'warn',
                'icon'   => 'receipt',
                /* translators: %s: number of orders */
                'title'  => sprintf(__('%s سفارش هنوز به درنا نرسیده', 'wp-dorna'), \WPDorna\Admin\Format::num($orders['unsent'])),
                'text'   => __('سیستم خودکار دوباره تلاش می‌کند؛ علت هر سفارش را در صفحه‌ی سفارش‌ها ببینید.', 'wp-dorna'),
                'action' => array(__('بررسی سفارش‌ها', 'wp-dorna'), $this->url_for('wp-dorna-orders', array('tab' => 'attention'))),
            );
        }

        if ($full['status'] === 'idle') {
            $callouts[] = array(
                'tone'   => 'info',
                'icon'   => 'sync',
                'title'  => __('یک‌بار سینک کامل را اجرا کنید', 'wp-dorna'),
                'text'   => __('همه‌ی کالاها را یک‌بار با درنا یکی می‌کند تا از آن به بعد مطمئن باشید موجودی و قیمت‌ها درست است.', 'wp-dorna'),
                'action' => array(__('رفتن به سینک کامل', 'wp-dorna'), $this->url_for('wp-dorna-sync')),
            );
        }

        return $callouts;
    }
}
