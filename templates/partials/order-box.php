<?php
/**
 * @var int   $order_id
 * @var array $state
 */

defined('ABSPATH') || exit;
?>
<div class="wpd wpd-box" dir="rtl" data-wpd-order-box>
    <?php \WPDorna\Admin\View::render('partials/order-badge', array('state' => $state, 'detailed' => true)); ?>

    <?php if (!$state['sent']) : ?>
        <button type="button" class="wpd-btn is-primary wpd-btn--block" data-orders="send-box" data-order-id="<?php echo esc_attr($order_id); ?>">
            <?php esc_html_e('ارسال به درنا', 'wp-dorna'); ?>
        </button>
        <p class="wpd-note" data-box-message hidden></p>
    <?php endif; ?>
</div>
