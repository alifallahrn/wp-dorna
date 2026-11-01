<?php
/**
 * @var array $state    result of InvoiceState::describe()
 * @var bool  $detailed show the reason and attempts (orders screen)
 */

use WPDorna\Admin\Format;

defined('ABSPATH') || exit;

$detailed = !empty($detailed);
?>
<?php if ($state['sent']) : ?>
    <span class="wpd-badge is-good"><i></i><?php esc_html_e('ثبت شد', 'wp-dorna'); ?></span>
    <?php if ($detailed && $state['invoice_id'] !== '') : ?>
        <span class="wpd-sub"><?php esc_html_e('شناسه', 'wp-dorna'); ?> <bdi dir="ltr"><?php echo esc_html($state['invoice_id']); ?></bdi></span>
    <?php endif; ?>
<?php else : ?>
    <span class="wpd-badge is-<?php echo $state['attempts'] ? 'warn' : 'mut'; ?>"><i></i><?php echo $state['attempts'] ? esc_html__('ناموفق، در صف تلاش', 'wp-dorna') : esc_html__('ارسال نشده', 'wp-dorna'); ?></span>
    <?php if ($detailed && $state['error'] !== '') : ?>
        <span class="wpd-sub"><?php echo esc_html($state['error']); ?></span>
    <?php endif; ?>
    <?php if ($detailed && $state['attempts']) : ?>
        <span class="wpd-sub">
            <?php
            /* translators: 1: number of attempts, 2: relative time of the last attempt */
            printf(esc_html__('%1$s بار تلاش؛ آخرین: %2$s', 'wp-dorna'), esc_html(Format::num($state['attempts'])), esc_html(Format::ago($state['last_attempt'])));
            ?>
        </span>
    <?php endif; ?>
<?php endif; ?>
