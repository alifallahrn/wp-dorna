<?php

namespace WPDorna\Api;

use WP_Error;
use WPDorna\Core\Settings;

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

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    private function request_args()
    {
        return array(
            'timeout' => self::TIMEOUT,
            'headers' => array(
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $this->settings->api_key(),
                'Content-Type'  => 'application/json',
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

                    return is_array($decoded)
                        ? $decoded
                        : new WP_Error('api_error', __('پاسخ درنا قابل خواندن نیست.', 'wp-dorna'), array('status' => $code));
                }

                $error = new WP_Error('api_error', $this->status_message($code), array('status' => $code));

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
            return new WP_Error('api_error', $this->error_message($body, $status), array('status' => $status, 'body' => $body));
        }

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
            return array('ok' => false, 'message' => __('ابتدا توکن درنا را وارد کنید.', 'wp-dorna'));
        }

        $response = wp_remote_get(WP_DORNA_API_URL . self::PRODUCT_BY_SKU_ENDPOINT . '/' . rawurlencode('wp-dorna-ping'), $this->request_args());

        if (is_wp_error($response)) {
            return array('ok' => false, 'message' => __('ارتباط با سرور درنا برقرار نشد. اتصال اینترنت و فایروال سرور را بررسی کنید.', 'wp-dorna'));
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code === 404 || $code === 200) {
            return array('ok' => true, 'message' => __('اتصال به درنا برقرار است.', 'wp-dorna'));
        }

        if ($code === 401 || $code === 403) {
            return array('ok' => false, 'message' => __('توکن پذیرفته نشد. توکن را از پنل درنا دوباره کپی کنید.', 'wp-dorna'));
        }

        return array('ok' => false, 'message' => $this->status_message($code));
    }

    private function status_message($code)
    {
        if ($code === 401 || $code === 403) {
            return __('توکن درنا معتبر نیست.', 'wp-dorna');
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
            return $this->status_message($status);
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
