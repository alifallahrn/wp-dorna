<?php

class WP_Dorna_API
{

    private $api_key;

    const PRODUCTS_ENDPOINT = 'products-list';
    const INVOICES_ENDPOINT = 'invoices-create';

    public function __construct()
    {
        $this->api_key = get_option(WP_DORNA_OPTION_NAME)['api_key'];
    }

    public function get_data($endpoint)
    {
        $url = WP_DORNA_API_URL . $endpoint;

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
        ));

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('api_error', 'API request failed with response code ' . $response_code);
        }

        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return new WP_Error('api_error', 'API response body is empty');
        }

        return json_decode($body, true);
    }

    public function post_data($endpoint, $data)
    {
        $url = WP_DORNA_API_URL . $endpoint;

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => json_encode($data),
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        return json_decode($response['body'], true);
    }
}
