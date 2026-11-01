<?php
/**
 * @var array $status
 * @var array $schedule
 */

use WPDorna\Admin\Format;
use WPDorna\Admin\Icons;

defined('ABSPATH') || exit;
?>
<?php \WPDorna\Admin\View::render('partials/full-sync-panel'); ?>

<?php \WPDorna\Admin\View::render('partials/schedule-form', array('schedule' => $schedule, 'card' => true)); ?>

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
