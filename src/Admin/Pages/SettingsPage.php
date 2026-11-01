<?php

namespace WPDorna\Admin\Pages;

use WPDorna\Admin\View;

class SettingsPage extends Page
{
    const SAVE_ACTION = 'wp_dorna_save_settings';

    public function slug()
    {
        return 'wp-dorna-settings';
    }

    public function label()
    {
        return __('تنظیمات', 'wp-dorna');
    }

    public function icon()
    {
        return 'settings';
    }

    public function title()
    {
        return __('تنظیمات اتصال', 'wp-dorna');
    }

    public function purpose()
    {
        return __('توکن درنا را وارد کنید و اتصال را بیازمایید.', 'wp-dorna');
    }

    public function capability()
    {
        return 'manage_options';
    }

    public function register()
    {
        add_action('admin_post_' . self::SAVE_ACTION, array($this, 'handle_save'));
    }

    public function render()
    {
        $settings = $this->services->settings;

        View::render('pages/settings', array(
            'masked'        => $settings->masked_key(),
            'from_constant' => $settings->key_from_constant(),
            'remove_data'   => $settings->remove_data_on_uninstall(),
            'saved'         => isset($_GET['saved']), // phpcs:ignore WordPress.Security.NonceVerification
            'action'        => self::SAVE_ACTION,
            'post_url'      => admin_url('admin-post.php'),
        ));
    }

    public function handle_save()
    {
        if (!current_user_can($this->capability())) {
            wp_die(esc_html__('اجازه‌ی دسترسی ندارید.', 'wp-dorna'), '', array('response' => 403));
        }

        check_admin_referer(self::SAVE_ACTION);

        $this->services->settings->save(
            isset($_POST['api_key']) ? wp_unslash($_POST['api_key']) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            !empty($_POST['remove_data'])
        );

        wp_safe_redirect($this->url(array('saved' => 1)));
        exit;
    }
}
