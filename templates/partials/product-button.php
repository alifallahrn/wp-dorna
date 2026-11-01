<?php
/**
 * @var int $product_id
 */

defined('ABSPATH') || exit;
?>
<div class="misc-pub-section wpd wpd-box" dir="rtl">
    <button type="button" class="wpd-btn is-small wpd-btn--block" data-product-sync="<?php echo esc_attr($product_id); ?>">
        <?php esc_html_e('بروزرسانی از درنا', 'wp-dorna'); ?>
    </button>
    <p class="wpd-note" data-box-message hidden></p>
</div>
