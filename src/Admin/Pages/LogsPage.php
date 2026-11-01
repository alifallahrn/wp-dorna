<?php

namespace WPDorna\Admin\Pages;

use WPDorna\Admin\View;

class LogsPage extends Page
{
    public function slug()
    {
        return 'wp-dorna-logs';
    }

    public function label()
    {
        return __('گزارش‌ها', 'wp-dorna');
    }

    public function icon()
    {
        return 'file';
    }

    public function title()
    {
        return __('گزارش رویدادها', 'wp-dorna');
    }

    public function purpose()
    {
        return __('خطاها و رویدادهای مهم ۱۴ روز اخیر؛ برای پیگیری مشکل یا ارسال به پشتیبانی.', 'wp-dorna');
    }

    public function capability()
    {
        return 'manage_options';
    }

    public function render()
    {
        $logger = $this->services->logger;
        $dates  = $logger->dates();

        $requested = isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $date      = in_array($requested, $dates, true) ? $requested : ($dates ? $dates[0] : '');

        View::render('pages/logs', array(
            'dates' => $dates,
            'date'  => $date,
            'lines' => $date ? $logger->tail($date) : array(),
            'url'   => $this->url(),
        ));
    }
}
