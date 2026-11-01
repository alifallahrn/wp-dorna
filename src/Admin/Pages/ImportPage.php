<?php

namespace WPDorna\Admin\Pages;

use WPDorna\Admin\View;

class ImportPage extends Page
{
    public function slug()
    {
        return 'wp-dorna-import';
    }

    public function label()
    {
        return __('ورود کالا', 'wp-dorna');
    }

    public function icon()
    {
        return 'download';
    }

    public function title()
    {
        return __('ورود کالا از درنا', 'wp-dorna');
    }

    public function purpose()
    {
        return __('کالاهایی که در درنا هست و در سایت نیست را به‌صورت پیش‌نویس وارد کنید.', 'wp-dorna');
    }

    public function render()
    {
        View::render('pages/import');
    }
}
