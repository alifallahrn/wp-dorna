<?php

namespace WPDorna\Admin\Pages;

use WPDorna\Admin\View;
use WPDorna\Sync\Status;

class SyncPage extends Page
{
    public function slug()
    {
        return 'wp-dorna-sync';
    }

    public function label()
    {
        return __('سینک کامل', 'wp-dorna');
    }

    public function icon()
    {
        return 'sync';
    }

    public function title()
    {
        return __('سینک کامل', 'wp-dorna');
    }

    public function purpose()
    {
        return __('همه‌ی کالاهای درنا را یک‌بار، آرام و بدون فشار به سرور بررسی می‌کند.', 'wp-dorna');
    }

    public function render()
    {
        View::render('pages/sync', array(
            'status'   => Status::get(),
            'schedule' => $this->services->settings->schedule(),
        ));
    }
}
