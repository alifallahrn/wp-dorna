<?php

namespace WPDorna\Admin\Pages;

use WPDorna\Admin\View;
use WPDorna\Core\Scheduler;
use WPDorna\Orders\OrderStats;
use WPDorna\Sync\FullSync;
use WPDorna\Sync\Status;

class DashboardPage extends Page
{
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
        $counts = OrderStats::counts();

        return array('sent' => $counts['sent'], 'unsent' => $counts['unsent']);
    }

    private function callouts($has_key, array $status, array $full, array $orders)
    {
        $callouts = array();

        $connection = $this->services->connection;
        $wizard     = $this->services->wizard;

        if (!$wizard->finished() && !$wizard->ready()) {
            $callouts[] = array(
                'tone'   => 'info',
                'icon'   => 'check',
                /* translators: %s: number of finished steps out of four */
                'title'  => sprintf(__('راه‌اندازی را کامل کنید (%s از ۴ قدم)', 'wp-dorna'), \WPDorna\Admin\Format::num($wizard->done_count())),
                'text'   => __('اتصال، سینک کامل و سینک شبانه را در چند دقیقه انجام دهید تا دیگر لازم نباشد به سایت سر بزنید.', 'wp-dorna'),
                'action' => array(__('ادامه‌ی راه‌اندازی', 'wp-dorna'), $this->url_for('wp-dorna-setup')),
            );

            return $callouts;
        }

        if (!$has_key) {
            $callouts[] = array(
                'tone'   => 'bad',
                'icon'   => 'alert',
                'title'  => __('هنوز به درنا وصل نشده‌اید', 'wp-dorna'),
                'text'   => __('«اتصال به درنا» را بزنید، وارد حساب درنا شوید و کسب‌وکار را تأیید کنید.', 'wp-dorna'),
                'action' => array(__('رفتن به تنظیمات', 'wp-dorna'), $this->url_for('wp-dorna-settings')),
            );

            return $callouts;
        }

        if ($connection->needs_attention()) {
            $callouts[] = array(
                'tone'   => 'bad',
                'icon'   => 'alert',
                'title'  => $connection->status() === \WPDorna\Core\Connection::MISMATCH
                    ? __('این سایت با توکن سایت دیگری وصل است', 'wp-dorna')
                    : __('درنا توکن این سایت را قبول نمی‌کند', 'wp-dorna'),
                'text'   => __('همگام‌سازی و ارسال فاکتورها متوقف است. اگر این سایت اصلی است، دوباره به درنا وصل شوید.', 'wp-dorna'),
                'action' => array(__('اتصال دوباره', 'wp-dorna'), $this->url_for('wp-dorna-settings')),
            );
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
