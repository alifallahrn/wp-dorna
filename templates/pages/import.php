<?php
use WPDorna\Admin\Icons;

defined('ABSPATH') || exit;
?>
<section class="wpd-card" id="wpd-import">
    <div class="wpd-fs__head">
        <div>
            <h2 class="wpd-card__title"><?php esc_html_e('کالاهای جدید درنا', 'wp-dorna'); ?></h2>
            <p class="wpd-muted" id="wpd-import-note"><?php esc_html_e('ابتدا کالاهای درنا بررسی می‌شود؛ بعد می‌توانید همه‌ی کالاهای جدید را وارد کنید. کالاها پیش‌نویس ساخته می‌شوند و منتشر نمی‌شوند.', 'wp-dorna'); ?></p>
        </div>
        <div class="wpd-actions">
            <button type="button" class="wpd-btn" data-import="scan"><?php echo Icons::svg('sync'); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e('بررسی کالاهای درنا', 'wp-dorna'); ?></span></button>
            <button type="button" class="wpd-btn is-primary" data-import="run" hidden><?php echo Icons::svg('download'); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e('وارد کردن کالاها', 'wp-dorna'); ?></span></button>
        </div>
    </div>

    <div class="wpd-progress-row" id="wpd-import-progress" hidden>
        <div class="wpd-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php esc_attr_e('پیشرفت ورود کالا', 'wp-dorna'); ?>">
            <div class="wpd-progress__fill" id="wpd-import-fill"></div>
        </div>
        <span class="wpd-progress__label" id="wpd-import-percent">۰٪</span>
    </div>

    <div class="wpd-table-wrap" id="wpd-import-table" hidden>
        <table class="wpd-table">
            <thead><tr>
                <th><?php esc_html_e('کالا', 'wp-dorna'); ?></th>
                <th><?php esc_html_e('کد کالا', 'wp-dorna'); ?></th>
                <th><?php esc_html_e('موجودی', 'wp-dorna'); ?></th>
                <th><?php esc_html_e('وضعیت', 'wp-dorna'); ?></th>
            </tr></thead>
            <tbody id="wpd-import-rows"></tbody>
        </table>
    </div>

    <div class="wpd-empty" id="wpd-import-empty" hidden><?php echo Icons::svg('check'); // phpcs:ignore WordPress.Security.EscapeOutput ?><p><?php esc_html_e('کالای جدیدی برای وارد کردن نیست؛ همه‌ی کالاهای درنا در سایت هستند.', 'wp-dorna'); ?></p></div>
</section>
