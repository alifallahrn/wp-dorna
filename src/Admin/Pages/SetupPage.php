<?php

namespace WPDorna\Admin\Pages;

use WPDorna\Admin\View;
use WPDorna\Connect\ConnectionService;
use WPDorna\Sync\FullSync;

/**
 * The first-run checklist: connect, first full sync, import, nightly schedule. It
 * leaves the menu once finished; "run the setup again" brings it back.
 */
class SetupPage extends Page
{
    const REOPEN_ACTION = 'wp_dorna_wizard_reopen';

    public function slug()
    {
        return 'wp-dorna-setup';
    }

    public function label()
    {
        return __('راه‌اندازی', 'wp-dorna');
    }

    public function icon()
    {
        return 'check';
    }

    public function title()
    {
        return __('راه‌اندازی اتصال به درنا', 'wp-dorna');
    }

    public function purpose()
    {
        return __('چهار قدم کوتاه تا اینکه قیمت و موجودی سایت همیشه با درنا یکی باشد.', 'wp-dorna');
    }

    public function visible()
    {
        return !$this->services->wizard->finished();
    }

    public function register()
    {
        add_action('admin_post_' . self::REOPEN_ACTION, array($this, 'handle_reopen'));
    }

    public function render()
    {
        $wizard = $this->services->wizard;

        View::render('pages/setup', array(
            'steps'        => $wizard->steps(),
            'ready'        => $wizard->ready(),
            'connection'   => $this->services->connection->data(),
            'status'       => $this->services->connection->status(),
            'schedule'     => $this->services->settings->schedule(),
            'start_action' => ConnectionService::START_ACTION,
            'import_url'   => admin_url('admin.php?page=wp-dorna-import'),
            'settings_url' => admin_url('admin.php?page=wp-dorna-settings'),
            'full'         => FullSync::get_state(),
        ));
    }

    public function handle_reopen()
    {
        if (!current_user_can($this->capability())) {
            wp_die(esc_html__('اجازه‌ی دسترسی ندارید.', 'wp-dorna'), '', array('response' => 403));
        }

        check_admin_referer(self::REOPEN_ACTION);

        $this->services->wizard->reopen();

        wp_safe_redirect($this->url());
        exit;
    }
}
