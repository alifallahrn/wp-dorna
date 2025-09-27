<?php
/*
Plugin Name: WP Dorna
Plugin URI: https://github.com/alifallahrn/wp-dorna
Description: افزونه اتصال ووکامرس به پلتفرم درنا
Version: 1.0.2
Author: Ali Fallah
Author URI: https://dornaapp.ir
License: GPL2
*/

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

define('WP_DORNA_VERSION', '1.0.2');
define('WP_DORNA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_DORNA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_DORNA_API_URL', 'http://my.dornaapp.ir/api/v1/');
define('WP_DORNA_OPTION_NAME', 'wp_dorna_settings');

require_once WP_DORNA_PLUGIN_DIR . 'includes/class-wp-dorna.php';
require_once WP_DORNA_PLUGIN_DIR . 'includes/class-wp-dorna-admin.php';
require_once WP_DORNA_PLUGIN_DIR . 'includes/class-wp-dorna-api.php';

function wp_dorna_activate()
{
    // Set default options
    $default_options = array(
        'api_key' => '',
    );
    add_option(WP_DORNA_OPTION_NAME, $default_options);
}
register_activation_hook(__FILE__, 'wp_dorna_activate');

function wp_dorna_deactivate()
{
    $timestamp = wp_next_scheduled('wp_dorna_update_products_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'wp_dorna_update_products_event');
    }
}
register_deactivation_hook(__FILE__, 'wp_dorna_deactivate');

function wp_dorna_init()
{
    $wp_dorna = new WP_Dorna();
    $wp_dorna->init();

    if (is_admin()) {
        $wp_dorna_admin = new WP_Dorna_Admin();
        $wp_dorna_admin->init();
    }
}
add_action('plugins_loaded', 'wp_dorna_init');
