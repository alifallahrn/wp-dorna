<?php
/**
 * @var string   $tab
 * @var array    $rows
 * @var int      $total
 * @var int      $pages
 * @var int      $paged
 * @var callable $tab_url
 * @var callable $page_url
 */

use WPDorna\Admin\Format;
use WPDorna\Admin\Icons;

defined('ABSPATH') || exit;

$tabs = array(
    'attention' => __('ارسال‌نشده', 'wp-dorna'),
    'sent'      => __('ثبت‌شده در درنا', 'wp-dorna'),
    'all'       => __('همه', 'wp-dorna'),
);
?>
<section class="wpd-card wpd-card--flush" id="wpd-orders">
    <div class="wpd-toolbar">
        <div class="wpd-segment" role="tablist">
            <?php foreach ($tabs as $key => $label) : ?>
                <a role="tab" class="wpd-segment__item<?php echo $tab === $key ? ' is-active' : ''; ?>" aria-selected="<?php echo $tab === $key ? 'true' : 'false'; ?>" href="<?php echo esc_url($tab_url($key)); ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($tab === 'attention' && $rows) : ?>
            <button type="button" class="wpd-btn is-primary" data-orders="retry-all"><?php echo Icons::svg('send'); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e('ارسال دوباره‌ی همه', 'wp-dorna'); ?></span></button>
        <?php endif; ?>
    </div>

    <p class="wpd-note" id="wpd-orders-note" hidden></p>

    <?php if (!$rows) : ?>
        <div class="wpd-empty">
            <?php echo Icons::svg($tab === 'attention' ? 'check' : 'receipt'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <p><?php echo $tab === 'attention' ? esc_html__('همه‌ی سفارش‌ها در درنا ثبت شده‌اند.', 'wp-dorna') : esc_html__('سفارشی برای نمایش نیست.', 'wp-dorna'); ?></p>
        </div>
    <?php else : ?>
        <div class="wpd-table-wrap">
            <table class="wpd-table">
                <thead><tr>
                    <th><?php esc_html_e('سفارش', 'wp-dorna'); ?></th>
                    <th><?php esc_html_e('زمان', 'wp-dorna'); ?></th>
                    <th><?php esc_html_e('مبلغ', 'wp-dorna'); ?></th>
                    <th><?php esc_html_e('وضعیت در درنا', 'wp-dorna'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr data-order="<?php echo esc_attr($row['id']); ?>">
                        <td><a class="wpd-link" href="<?php echo esc_url($row['edit_url']); ?>">#<?php echo esc_html($row['id']); ?></a></td>
                        <td><?php echo esc_html(Format::ago($row['date'])); ?></td>
                        <td><?php echo esc_html($row['total']); ?></td>
                        <td data-state><?php \WPDorna\Admin\View::render('partials/order-badge', array('state' => $row['state'], 'detailed' => true)); ?></td>
                        <td class="wpd-table__end">
                            <?php if (!$row['state']['sent']) : ?>
                                <button type="button" class="wpd-btn is-small" data-orders="retry" data-order-id="<?php echo esc_attr($row['id']); ?>"><?php esc_html_e('ارسال دوباره', 'wp-dorna'); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1) : ?>
            <nav class="wpd-pager" aria-label="<?php esc_attr_e('صفحه‌بندی', 'wp-dorna'); ?>">
                <?php if ($paged > 1) : ?>
                    <a class="wpd-btn is-small" href="<?php echo esc_url($page_url($paged - 1)); ?>"><?php esc_html_e('قبلی', 'wp-dorna'); ?></a>
                <?php endif; ?>
                <span class="wpd-muted">
                    <?php
                    /* translators: 1: current page, 2: number of pages */
                    printf(esc_html__('صفحه %1$s از %2$s', 'wp-dorna'), esc_html(Format::num($paged)), esc_html(Format::num($pages)));
                    ?>
                </span>
                <?php if ($paged < $pages) : ?>
                    <a class="wpd-btn is-small" href="<?php echo esc_url($page_url($paged + 1)); ?>"><?php esc_html_e('بعدی', 'wp-dorna'); ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
