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
        return __('اتصال این سایت به درنا را وصل، آزمایش یا قطع کنید.', 'wp-dorna');
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
            'connection'    => $this->services->connection->data(),
            'status'        => $this->services->connection->status(),
            'start_action'  => \WPDorna\Connect\ConnectionService::START_ACTION,
            'disconnect_action' => \WPDorna\Connect\ConnectionService::DISCONNECT_ACTION,
            'wizard_open'   => !$this->services->wizard->finished(),
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

        $token = isset($_POST['api_key']) ? trim((string) wp_unslash($_POST['api_key'])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        $this->services->settings->save($token, !empty($_POST['remove_data']));

        // A hand-pasted token is not tied to this site's address in Dorna the way a connected one is.
        if ($token !== '') {
            $this->services->connection->set_manual();
        }

        wp_safe_redirect($this->url(array('saved' => 1)));
        exit;
    }
}
