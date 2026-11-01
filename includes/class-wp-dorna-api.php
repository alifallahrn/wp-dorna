<?php

class WP_Dorna_API
{

    private $api_key;

    const PRODUCTS_ENDPOINT = 'products-list';
    const PRODUCT_BY_SKU_ENDPOINT = 'product-by-sku';
    const INVOICES_ENDPOINT = 'invoices-create';

    // The default wp_remote_* timeout is 5s, too short for a page of 100 products.
    const TIMEOUT = 30;
    const MAX_ATTEMPTS = 3;
    const MAX_RETRY_WAIT = 30;

    public function __construct()
    {
        $options       = get_option(WP_DORNA_OPTION_NAME, array());
        $this->api_key = is_array($options) && isset($options['api_key']) ? trim($options['api_key']) : '';
    }

    private function request_args()
    {
        return array(
            'timeout' => self::TIMEOUT,
            'headers' => array(
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
        );
    }

    /**
     * GET with retry on network errors, 429 and 5xx. Always returns the decoded
     * body or a WP_Error whose data holds the HTTP `status` (0 for network errors).
     */
    public function get_data($endpoint)
    {
        if ($this->api_key === '') {
            return new WP_Error('api_error', 'توکن درنا در تنظیمات وارد نشده است.', array('status' => 0));
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
                    $body = wp_remote_retrieve_body($response);
                    if (empty($body)) {
                        return new WP_Error('api_error', 'API response body is empty', array('status' => $code));
                    }

                    $decoded = json_decode($body, true);
                    if (!is_array($decoded)) {
                        return new WP_Error('api_error', 'API response is not valid JSON', array('status' => $code));
                    }

                    return $decoded;
                }

                $error = new WP_Error('api_error', 'API request failed with response code ' . $code, array('status' => $code));

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
     * POST is not retried here: a lost response still creates the invoice on the
     * Dorna side, and the order retry cron re-sends it (duplicates are answered
     * with the existing invoice_id).
     */
    public function post_data($endpoint, $data)
    {
        if ($this->api_key === '') {
            return new WP_Error('api_error', 'توکن درنا در تنظیمات وارد نشده است.', array('status' => 0));
        }

        $url = WP_DORNA_API_URL . $endpoint;

        $args         = $this->request_args();
        $args['body'] = wp_json_encode($data);

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message(), array('status' => 0));
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body        = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = $this->error_message($body, $status_code);
            return new WP_Error('api_error', $message, array('status' => $status_code, 'body' => $body));
        }

        return is_array($body) ? $body : array();
    }

    private function error_message($body, $status_code)
    {
        if (is_array($body)) {
            if (!empty($body['errors']) && is_array($body['errors'])) {
                $messages = array();
                foreach ($body['errors'] as $field => $field_errors) {
                    $messages[] = $field . ': ' . implode(' ', (array) $field_errors);
                }
                return implode(' | ', $messages);
            }

            if (!empty($body['message']) && is_string($body['message'])) {
                return $body['message'];
            }

            if (!empty($body['error']) && is_string($body['error'])) {
                return $body['error'];
            }
        }

        return 'HTTP ' . $status_code;
    }
}
