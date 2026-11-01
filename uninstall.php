<?php
/**
 * Runs when the plugin is deleted. Data is only removed when the owner turned on
 * "remove data on uninstall" in the settings; order invoice meta is never touched.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function wp_dorna_uninstall_site()
{
    $settings = get_option('wp_dorna_settings', array());

    if (empty($settings['remove_data'])) {
        return;
    }

    foreach (array(
        'wp_dorna_settings',
        'wp_dorna_last_product_update',
        'wp_dorna_sync_status',
        'wp_dorna_full_sync',
        'wp_dorna_full_sync_seen',
        'wp_dorna_full_sync_report',
        'wp_dorna_lock_products',
        'wp_dorna_lock_retry',
        'wp_dorna_lock_full_sync',
    ) as $option) {
        delete_option($option);
    }

    delete_transient('wp_dorna_order_counts');

    $uploads = wp_upload_dir(null, false);
    $dir     = trailingslashit($uploads['basedir']) . 'wp-dorna-logs/';

    foreach ((array) glob($dir . '{*.log,.htaccess,index.php}', GLOB_BRACE) as $file) {
        @unlink($file);
    }
    @rmdir($dir);
}

if (is_multisite()) {
    foreach (get_sites(array('fields' => 'ids')) as $site_id) {
        switch_to_blog($site_id);
        wp_dorna_uninstall_site();
        restore_current_blog();
    }
} else {
    wp_dorna_uninstall_site();
}
