<?php
/**
 * Plugin Name: WP Dorna
 * Plugin URI: https://github.com/alifallahrn/wp-dorna
 * Description: A WooCommerce integration plugin for the Dorna platform. It keeps product prices and stock in sync and sends orders to Dorna as invoices.
 * Version: 1.4.1
 * Author: Ali Fallah
 * Author URI: https://dornaapp.ir
 * License: GPL2
 * Text Domain: wp-dorna
 * Domain Path: /languages
 * Requires at least: 5.3
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

require 'vendor/autoload.php';

define('WP_DORNA_VERSION', '1.4.1');
define('WP_DORNA_FILE', __FILE__);
define('WP_DORNA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_DORNA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_DORNA_API_URL', 'https://my.dornaapp.ir/api/v1/');
define('WP_DORNA_OPTION_NAME', 'wp_dorna_settings');

spl_autoload_register(function ($class) {
    $prefix = 'WPDorna\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $file = WP_DORNA_PLUGIN_DIR . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_readable($file)) {
        require $file;
    }
});

register_activation_hook(__FILE__, array('WPDorna\Core\Lifecycle', 'activate'));
register_deactivation_hook(__FILE__, array('WPDorna\Core\Lifecycle', 'deactivate'));

add_action('init', function () {
    load_plugin_textdomain('wp-dorna', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

add_action('plugins_loaded', array('WPDorna\Core\Plugin', 'boot'));

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

$updateChecker = PucFactory::buildUpdateChecker('https://github.com/alifallahrn/wp-dorna', __FILE__, 'wp-dorna');
$updateChecker->getVcsApi()->enableReleaseAssets();
