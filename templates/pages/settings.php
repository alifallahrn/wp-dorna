<?php
/**
 * @var string $masked
 * @var bool   $from_constant
 * @var bool   $remove_data
 * @var bool   $saved
 * @var string $action
 * @var string $post_url
 */

use WPDorna\Admin\Icons;

defined('ABSPATH') || exit;
?>
<?php if ($saved) : ?>
    <div class="wpd-callout is-good" role="status">
        <span class="wpd-ic is-good"><?php echo Icons::svg('check'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <div class="wpd-callout__text"><strong><?php esc_html_e('تنظیمات ذخیره شد.', 'wp-dorna'); ?></strong></div>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url($post_url); ?>" class="wpd-settings" id="wpd-settings">
    <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
    <?php wp_nonce_field($action); ?>

    <section class="wpd-card wpd-card--flush">
        <div class="wpd-row">
            <div class="wpd-row__text">
                <label for="wpd-api-key"><?php esc_html_e('توکن درنا', 'wp-dorna'); ?></label>
                <p><?php esc_html_e('از پنل درنا بخش اتصال به سایت، توکن را کپی کنید و اینجا بچسبانید.', 'wp-dorna'); ?></p>
                <?php if ($from_constant) : ?>
                    <p class="wpd-note"><?php esc_html_e('توکن در فایل wp-config.php تعریف شده و همان استفاده می‌شود.', 'wp-dorna'); ?></p>
                <?php elseif ($masked) : ?>
                    <p class="wpd-note"><?php esc_html_e('توکن فعلی:', 'wp-dorna'); ?> <bdi dir="ltr"><?php echo esc_html($masked); ?></bdi> — <?php esc_html_e('برای نگه داشتن آن، این کادر را خالی بگذارید.', 'wp-dorna'); ?></p>
                <?php endif; ?>
            </div>
            <div class="wpd-row__control">
                <input type="password" class="wpd-input" id="wpd-api-key" name="api_key" dir="ltr" autocomplete="off" spellcheck="false"
                       placeholder="<?php echo $masked ? esc_attr__('توکن تازه (اختیاری)', 'wp-dorna') : esc_attr__('توکن را اینجا بچسبانید', 'wp-dorna'); ?>"
                       <?php disabled($from_constant); ?>>
            </div>
        </div>

        <div class="wpd-row">
            <div class="wpd-row__text">
                <span class="wpd-label"><?php esc_html_e('آزمایش اتصال', 'wp-dorna'); ?></span>
                <p><?php esc_html_e('با توکن ذخیره‌شده با درنا تماس می‌گیرد و نتیجه را نشان می‌دهد. اول تنظیمات را ذخیره کنید.', 'wp-dorna'); ?></p>
                <p class="wpd-note" id="wpd-test-result" hidden></p>
            </div>
            <div class="wpd-row__control">
                <button type="button" class="wpd-btn" data-settings="test"><?php echo Icons::svg('link'); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e('آزمایش اتصال', 'wp-dorna'); ?></span></button>
            </div>
        </div>

        <div class="wpd-row">
            <div class="wpd-row__text">
                <label for="wpd-remove-data"><?php esc_html_e('حذف اطلاعات هنگام پاک‌کردن افزونه', 'wp-dorna'); ?></label>
                <p><?php esc_html_e('اگر روشن باشد، با حذف افزونه تنظیمات و وضعیت همگام‌سازی هم پاک می‌شود. فاکتورهای ثبت‌شده‌ی سفارش‌ها دست نمی‌خورند.', 'wp-dorna'); ?></p>
            </div>
            <div class="wpd-row__control">
                <label class="wpd-switch">
                    <input type="checkbox" id="wpd-remove-data" name="remove_data" value="1" <?php checked($remove_data); ?>>
                    <span class="wpd-switch__track"></span>
                </label>
            </div>
        </div>
    </section>

    <div class="wpd-savebar">
        <button type="submit" class="wpd-btn is-primary"><?php esc_html_e('ذخیره تنظیمات', 'wp-dorna'); ?></button>
    </div>
</form>
