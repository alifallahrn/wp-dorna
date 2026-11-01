<?php
/**
 * @var array $status
 */

use WPDorna\Admin\Format;
use WPDorna\Admin\Icons;

defined('ABSPATH') || exit;
?>
<section class="wpd-card wpd-fs" id="wpd-fs">
    <div class="wpd-fs__head">
        <div>
            <span class="wpd-badge is-mut" id="wpd-fs-badge"><i></i><span id="wpd-fs-status">—</span></span>
            <p class="wpd-muted" id="wpd-fs-eta"></p>
        </div>
        <div class="wpd-actions">
            <button type="button" class="wpd-btn is-primary" data-fs="start"><?php echo Icons::svg('play'); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e('شروع سینک کامل', 'wp-dorna'); ?></span></button>
            <button type="button" class="wpd-btn" data-fs="pause"><?php echo Icons::svg('pause'); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e('توقف', 'wp-dorna'); ?></span></button>
            <button type="button" class="wpd-btn is-primary" data-fs="resume"><?php echo Icons::svg('play'); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e('ادامه', 'wp-dorna'); ?></span></button>
            <button type="button" class="wpd-btn is-ghost" data-fs="cancel"><?php echo Icons::svg('x'); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e('لغو', 'wp-dorna'); ?></span></button>
        </div>
    </div>

    <div class="wpd-progress-row">
        <div class="wpd-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php esc_attr_e('پیشرفت سینک', 'wp-dorna'); ?>">
            <div class="wpd-progress__fill" id="wpd-fs-fill"></div>
        </div>
        <span class="wpd-progress__label" id="wpd-fs-percent">۰٪</span>
    </div>

    <dl class="wpd-facts wpd-facts--5">
        <div><dt><?php esc_html_e('بررسی‌شده', 'wp-dorna'); ?></dt><dd><span id="wpd-fs-checked">۰</span> <small><?php esc_html_e('از', 'wp-dorna'); ?> <span id="wpd-fs-total">۰</span></small></dd></div>
        <div><dt><?php esc_html_e('بروزرسانی‌شده', 'wp-dorna'); ?></dt><dd id="wpd-fs-updated">۰</dd></div>
        <div><dt><?php esc_html_e('بدون تغییر', 'wp-dorna'); ?></dt><dd id="wpd-fs-unchanged">۰</dd></div>
        <div><dt><?php esc_html_e('در سایت نیست', 'wp-dorna'); ?></dt><dd id="wpd-fs-missing">۰</dd></div>
        <div><dt><?php esc_html_e('خطا', 'wp-dorna'); ?></dt><dd id="wpd-fs-failed">۰</dd></div>
    </dl>

    <p class="wpd-note" id="wpd-fs-note"></p>
    <ul class="wpd-errors" id="wpd-fs-errors"></ul>

    <details class="wpd-details" id="wpd-fs-report" hidden>
        <summary><?php esc_html_e('کالاهای سایت که در درنا پیدا نشدند', 'wp-dorna'); ?> (<span id="wpd-fs-report-count">۰</span>)</summary>
        <p class="wpd-muted"><?php esc_html_e('این کالاها در درنا نیستند یا غیرفعال‌اند؛ تغییری در آن‌ها داده نشده است.', 'wp-dorna'); ?></p>
        <textarea class="wpd-input" readonly rows="8" dir="ltr" id="wpd-fs-report-list"></textarea>
    </details>
</section>

<section class="wpd-card">
    <h2 class="wpd-card__title"><?php esc_html_e('چطور کار می‌کند؟', 'wp-dorna'); ?></h2>
    <ul class="wpd-list">
        <li><?php esc_html_e('هر چند ثانیه فقط یک صفحه‌ی ۱۰۰ کالایی از درنا گرفته می‌شود تا به سرور درنا فشار نیاید.', 'wp-dorna'); ?></li>
        <li><?php esc_html_e('با بستن این صفحه هم در پس‌زمینه ادامه پیدا می‌کند؛ می‌توانید هر زمان متوقف یا لغو کنید.', 'wp-dorna'); ?></li>
        <li><?php esc_html_e('در طول اجرا، بروزرسانی خودکار هر دقیقه متوقف است و بعد از پایان از همین‌جا ادامه می‌دهد.', 'wp-dorna'); ?></li>
        <li><?php esc_html_e('موجودی کالاهایی که سفارش ارسال‌نشده به درنا دارند تغییر نمی‌کند تا فروش‌های تازه از دست نرود.', 'wp-dorna'); ?></li>
    </ul>
    <p class="wpd-muted">
        <?php
        /* translators: %s: relative time */
        printf(esc_html__('آخرین بروزرسانی خودکار موفق: %s', 'wp-dorna'), esc_html(Format::ago($status['last_success'])));
        ?>
    </p>
</section>
