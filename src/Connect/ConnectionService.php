<?php

namespace WPDorna\Connect;

use WP_Error;
use WPDorna\Api\Client;
use WPDorna\Core\Connection;
use WPDorna\Core\Settings;
use WPDorna\Core\Wizard;
use WPDorna\Support\Flash;
use WPDorna\Support\Logger;
use WPDorna\Support\Site;

/**
 * "Connect to Dorna": the browser goes to Dorna, the owner signs in, picks the
 * business and confirms, Dorna sends the browser back with a one-time code, and the
 * code is swapped for the token server to server (PKCE: only this site, holding the
 * verifier, can complete it). The token never appears in a URL.
 */
class ConnectionService
{
    const START_ACTION      = 'wp_dorna_connect_start';
    const RETURN_ACTION     = 'wp_dorna_connect_return';
    const DISCONNECT_ACTION = 'wp_dorna_disconnect';
    const STATE_TTL         = 600;

    private $client;
    private $settings;
    private $connection;
    private $heartbeat;
    private $wizard;
    private $logger;

    public function __construct(Client $client, Settings $settings, Connection $connection, Heartbeat $heartbeat, Wizard $wizard, Logger $logger)
    {
        $this->client     = $client;
        $this->settings   = $settings;
        $this->connection = $connection;
        $this->heartbeat  = $heartbeat;
        $this->wizard     = $wizard;
        $this->logger     = $logger;
    }

    public function register()
    {
        add_action('admin_post_' . self::START_ACTION, array($this, 'handle_start'));
        add_action('admin_post_' . self::RETURN_ACTION, array($this, 'handle_return'));
        add_action('admin_post_' . self::DISCONNECT_ACTION, array($this, 'handle_disconnect'));
    }

    /** Where Dorna's own pages live (the API lives under /api/v1/ on the same host). */
    public static function app_url()
    {
        return defined('WP_DORNA_APP_URL') ? WP_DORNA_APP_URL : preg_replace('#api/v1/?$#', '', WP_DORNA_API_URL);
    }

    public static function return_url()
    {
        return admin_url('admin-post.php?action=' . self::RETURN_ACTION);
    }

    private static function base64url($binary)
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    /**
     * The Dorna page the owner confirms the connection on.
     *
     * @return string|WP_Error
     */
    public function authorize_url()
    {
        $site = Site::origin();

        if ($site === '' || Site::origin_of(self::return_url()) !== $site) {
            return new WP_Error('site', __('آدرس سایت و آدرس پنل مدیریت وردپرس باید یکی باشند تا اتصال انجام شود.', 'wp-dorna'));
        }

        $state    = self::base64url(random_bytes(24));
        $verifier = self::base64url(random_bytes(32));

        set_transient('wp_dorna_connect_' . $state, array('verifier' => $verifier, 'user' => get_current_user_id()), self::STATE_TTL);

        return trailingslashit(self::app_url()) . 'connect/woocommerce?' . http_build_query(array(
            'site'      => $site,
            'return'    => self::return_url(),
            'state'     => $state,
            'challenge' => self::base64url(hash('sha256', $verifier, true)),
            'name'      => wp_strip_all_tags(get_bloginfo('name')),
            'v'         => WP_DORNA_VERSION,
        ), '', '&', PHP_QUERY_RFC3986);
    }

    private function settings_url()
    {
        return admin_url('admin.php?page=wp-dorna-settings');
    }

    private function after_connect_url()
    {
        return admin_url('admin.php?page=' . ($this->wizard->finished() ? 'wp-dorna-settings' : 'wp-dorna-setup'));
    }

    private function guard()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('اجازه‌ی دسترسی ندارید.', 'wp-dorna'), '', array('response' => 403));
        }
    }

    public function handle_start()
    {
        $this->guard();
        check_admin_referer(self::START_ACTION);

        $url = $this->authorize_url();

        if (is_wp_error($url)) {
            Flash::add('bad', $url->get_error_message());
            wp_safe_redirect($this->settings_url());
            exit;
        }

        // The destination is Dorna's page, which is outside this site on purpose.
        wp_redirect($url); // phpcs:ignore WordPress.Security.SafeRedirect
        exit;
    }

    /**
     * Dorna sends the browser back here with ?code=…&state=… (or ?error=access_denied).
     */
    public function handle_return()
    {
        $this->guard();

        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- the state is the CSRF token of this flow
        $key   = preg_match('/^[A-Za-z0-9_-]{16,128}$/', $state) ? 'wp_dorna_connect_' . $state : '';

        $pending = $key ? get_transient($key) : false;
        if ($key) {
            delete_transient($key);
        }

        if (!is_array($pending) || (int) $pending['user'] !== get_current_user_id()) {
            Flash::add('bad', __('درخواست اتصال معتبر نبود یا منقضی شد؛ دوباره «اتصال به درنا» را بزنید.', 'wp-dorna'));
            wp_safe_redirect($this->settings_url());
            exit;
        }

        if (isset($_GET['error'])) { // phpcs:ignore WordPress.Security.NonceVerification
            Flash::add('mut', __('اتصال لغو شد. هر زمان خواستید دوباره «اتصال به درنا» را بزنید.', 'wp-dorna'));
            wp_safe_redirect($this->settings_url());
            exit;
        }

        $code = isset($_GET['code']) ? preg_replace('/[^A-Za-z0-9]/', '', wp_unslash($_GET['code'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        $result = $this->connect($code, $pending['verifier']);

        if (is_wp_error($result)) {
            Flash::add('bad', $result->get_error_message());
            wp_safe_redirect($this->settings_url());
            exit;
        }

        /* translators: %s: business name in Dorna */
        Flash::add('good', sprintf(__('به کسب‌وکار «%s» در درنا وصل شد.', 'wp-dorna'), $result['business']['name']));
        wp_safe_redirect($this->after_connect_url());
        exit;
    }

    /**
     * @return array|WP_Error the exchange answer
     */
    public function connect($code, $verifier)
    {
        if (strlen($code) !== 48) {
            return new WP_Error('code', __('کد اتصال معتبر نیست؛ دوباره «اتصال به درنا» را بزنید.', 'wp-dorna'));
        }

        $result = $this->client->exchange($code, $verifier);

        if (is_wp_error($result)) {
            $this->logger->error('اتصال به درنا انجام نشد.', array('reason' => $result->get_error_message()));

            return $result;
        }

        $this->settings->set_api_key($result['token']);
        $this->connection->save_connected($result);
        $this->logger->info('به درنا وصل شد.', array('business' => isset($result['business']['username']) ? $result['business']['username'] : ''));

        // Dorna's card shows the site from now on, without waiting for the cron.
        $this->heartbeat->send();

        return $result;
    }

    public function handle_disconnect()
    {
        $this->guard();
        check_admin_referer(self::DISCONNECT_ACTION);

        $this->disconnect();

        Flash::add('mut', __('اتصال به درنا قطع شد. بروزرسانی خودکار و ارسال فاکتورها تا اتصال دوباره متوقف است.', 'wp-dorna'));
        wp_safe_redirect($this->settings_url());
        exit;
    }

    /** Tells Dorna (best effort), then forgets the token here. */
    public function disconnect()
    {
        if ($this->connection->is_usable()) {
            $this->client->disconnect_remote();
        }

        $this->settings->clear_api_key();
        $this->connection->clear();
        $this->logger->info('اتصال به درنا قطع شد.');
    }
}
