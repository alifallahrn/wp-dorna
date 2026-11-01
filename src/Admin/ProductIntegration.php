<?php

namespace WPDorna\Admin;

/**
 * "Update from Dorna" button in the product's publish box.
 */
class ProductIntegration
{
    public function register()
    {
        add_action('post_submitbox_misc_actions', array($this, 'render_button'));
    }

    public function render_button($post)
    {
        if (!$post || $post->post_type !== 'product' || !current_user_can('manage_woocommerce')) {
            return;
        }

        View::render('partials/product-button', array('product_id' => (int) $post->ID));
    }
}
