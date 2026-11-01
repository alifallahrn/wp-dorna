<?php

namespace WPDorna\Api;

use WP_Error;
use WPDorna\Core\Connection;
use WPDorna\Core\Settings;
use WPDorna\Support\Site;

class Client
{
    const PRODUCTS_ENDPOINT       = 'products-list';
    const PRODUCT_BY_SKU_ENDPOINT = 'product-by-sku';
    const INVOICES_ENDPOINT       = 'invoices-create';

    // The default wp_remote_* timeout is 5s, too short for a page of 100 products.
    const TIMEOUT        = 30;
    const MAX_ATTEMPTS   = 3;
    const MAX_RETRY_WAIT = 30;

    private $settings;
    private $connection;

    public function __construct(Settings $settings, Connection $connection)
    {
        $this->settings   = $settings;
        $this->connection = $connection;
    }

    private function request_args()
    {
        return array(
            'timeout' => self::TIMEOUT,
            'headers' => array(
                'Accept'          => 'application/json',
                'Authorization'   => 'Bearer ' . $this->settings->api_key(),
                'Content-Type'    => 'application/json',
                // Dorna binds the token to this address: a copy of the site (staging)
                // that inherited the token is refused instead of writing into the real business.
                'X-Dorna-Site'    => Site::origin(),
                'X-Dorna-Plugin'  => WP_DORNA_VERSION,
            ),
        );
    }

    private function missing_key()
    {
        return new WP_Error('api_error', __('توکن درنا در تنظیمات وارد نشده است.', 'wp-dorna'), array('status' => 0));
    }

    /**
     * GET with retry on network errors, 429 and 5xx. Returns the decoded body or a
     * WP_Error whose data holds the HTTP `status` (0 for network errors).
     */
    public function get_data($endpoint)
    {
        if (!$this->settings->has_key()) {
            return $this->missing_key();
        }

        $url   = WP_DORNA_API_URL . $endpoint;
        $error = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = wp_remote_get($url, $this->request_args());
            $wait     = min(self::MAX_RETRY_WAIT, 2 * $attempt);

            if (is_wp_error($response)) {
                $error = new WP_Error('api_error', $response->get_error_message(), array('status' => 0));
            } else {
                $code = (int) wp_remote_retrieve_response_code($response);

                if ($code === 200) {
                    $decoded = json_decode(wp_remote_retrieve_body($response), true);

                    if (!is_array($decoded)) {
                        return new WP_Error('api_error', __('پاسخ درنا قابل خواندن نیست.', 'wp-dorna'), array('status' => $code));
                    }

                    $this->connection->mark_ok();

                    return $decoded;
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                $this->note_refusal($code, $body);
                $error = new WP_Error('api_error', $this->status_message($code, $body), array('status' => $code));

                if ($code !== 429 && $code < 500) {
                    return $error;
                }

                $retry_after = (int) wp_remote_retrieve_header($response, 'retry-after');
                if ($retry_after > 0) {
                    $wait = min(self::MAX_RETRY_WAIT, $retry_after);
                }
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                sleep($wait);
            }
        }

        return $error;
    }

    /**
     * POST is not retried here: a lost response still creates the invoice in Dorna
     * and the order retry re-sends it (a repeated order_id is answered with the
     * existing invoice_id).
     */
    public function post_data($endpoint, array $data)
    {
        if (!$this->settings->has_key()) {
            return $this->missing_key();
        }

        $args         = $this->request_args();
        $args['body'] = wp_json_encode($data);

        $response = wp_remote_post(WP_DORNA_API_URL . $endpoint, $args);

        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message(), array('status' => 0));
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if ($status < 200 || $status >= 300) {
            $this->note_refusal($status, $body);

            return new WP_Error('api_error', $this->error_message($body, $status), array('status' => $status, 'body' => $body));
        }

        $this->connection->mark_ok();

        return is_array($body) ? $body : array();
    }

    /**
     * Cheap authenticated call (an unknown SKU answers 404 only when the token is
     * valid) used by the "test connection" button.
     *
     * @return array{ok: bool, message: string}
     */
    public function ping()
    {
        if (!$this->settings->has_key()) {
            return array('ok' => false, 'message' => __('ابتدا به درنا وصل شوید.', 'wp-dorna'));
        }

        $response = wp_remote_get(WP_DORNA_API_URL . self::PRODUCT_BY_SKU_ENDPOINT . '/' . rawurlencode('wp-dorna-ping'), $this->request_args());

        if (is_wp_error($response)) {
            return array('ok' => false, 'message' => __('ارتباط با سرور درنا برقرار نشد. اتصال اینترنت و فایروال سرور را بررسی کنید.', 'wp-dorna'));
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code === 404 || $code === 200) {
            $this->connection->mark_ok();

            return array('ok' => true, 'message' => __('اتصال به درنا برقرار است.', 'wp-dorna'));
        }

        $this->note_refusal($code, json_decode(wp_remote_retrieve_body($response), true));

        return array('ok' => false, 'message' => $this->status_message($code, json_decode(wp_remote_retrieve_body($response), true)));
    }

    /**
     * Swaps the one-time code Dorna handed to the browser for the token (server to
     * server, so the token never travels through the browser).
     *
     * @return array|WP_Error {token, business: {name, username}, site, connected_at}
     */
    public function exchange($code, $verifier)
    {
        $response = wp_remote_post(WP_DORNA_API_URL . 'connect/exchange', array(
            'timeout' => 20,
            'headers' => array('Accept' => 'application/json', 'Content-Type' => 'application/json'),
            'body'    => wp_json_encode(array('code' => $code, 'verifier' => $verifier, 'site' => Site::origin())),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('api_error', __('ارتباط با سرور درنا برقرار نشد؛ دوباره تلاش کنید.', 'wp-dorna'), array('status' => 0));
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200 || !is_array($body) || empty($body['token'])) {
            $message = $status === 422
                ? __('کد اتصال منقضی شده یا قبلاً استفاده شده است؛ دوباره «اتصال به درنا» را بزنید.', 'wp-dorna')
                : $this->status_message($status);

            return new WP_Error('api_error', $message, array('status' => $status));
        }

        return $body;
    }

    /** Tells Dorna the site is healthy (counts and states only, no customer data). */
    public function heartbeat(array $health)
    {
        return $this->post_data('connect/heartbeat', array('health' => $health));
    }

    /** Asks Dorna to drop this site's connection and token. */
    public function disconnect_remote()
    {
        return $this->post_data('connect/disconnect', array());
    }

    /**
     * What a refusal means for the connection: a revoked / replaced token (401, or
     * "token_not_connected") or a token that belongs to another site address (403
     * "site_mismatch", e.g. a copy of the site on a staging server).
     */
    private function note_refusal($status, $body)
    {
        $error = is_array($body) && isset($body['error']) ? $body['error'] : '';

        if ($status === 401 || ($status === 403 && $error === 'token_not_connected')) {
            $this->connection->mark(Connection::REJECTED);
        } elseif ($status === 403 && $error === 'site_mismatch') {
            $this->connection->mark(Connection::MISMATCH);
        }
    }

    private function status_message($code, $body = null)
    {
        if ($code === 403 && is_array($body) && isset($body['error']) && $body['error'] === 'site_mismatch') {
            return __('این توکن برای آدرس دیگری صادر شده است. اگر این سایت یک کپی (تستی) از سایت اصلی است، به درنا وصل نمی‌شود.', 'wp-dorna');
        }

        if ($code === 401 || $code === 403) {
            return __('درنا توکن را قبول نمی‌کند؛ دوباره به درنا وصل شوید.', 'wp-dorna');
        }

        if ($code === 429) {
            return __('درنا درخواست‌ها را موقتاً محدود کرده است.', 'wp-dorna');
        }

        if ($code >= 500) {
            return __('سرور درنا موقتاً پاسخ نمی‌دهد.', 'wp-dorna');
        }

        /* translators: %d: HTTP status code */
        return sprintf(__('درنا خطا برگرداند (کد %d).', 'wp-dorna'), $code);
    }

    private function error_message($body, $status)
    {
        if (in_array($status, array(401, 403, 429), true) || $status >= 500) {
            return $this->status_message($status, $body);
        }

        if (is_array($body)) {
            if (!empty($body['errors']) && is_array($body['errors'])) {
                $messages = array();
                foreach ($body['errors'] as $field => $field_errors) {
                    $messages[] = $field . ': ' . implode(' ', (array) $field_errors);
                }
                return implode(' | ', $messages);
            }

            foreach (array('message', 'error') as $key) {
                if (!empty($body[$key]) && is_string($body[$key])) {
                    return $body[$key];
                }
            }
        }

        return 'HTTP ' . $status;
    }
}
