<?php
/**
 * Scheduled full sync: off / nightly / weekly at an hour. Saved over AJAX (assets/js/admin.js).
 *
 * @var array $schedule {mode, hour, weekday, next_due}
 * @var bool  $card     wrap in a card (the sync screen) or not (the setup step)
 */

use WPDorna\Admin\Format;

defined('ABSPATH') || exit;

$card = !empty($card);

// Persian week: Saturday first. Values follow PHP's date('w') (0 = Sunday).
$weekdays = array(
    6 => __('شنبه', 'wp-dorna'),
    0 => __('یکشنبه', 'wp-dorna'),
    1 => __('دوشنبه', 'wp-dorna'),
    2 => __('سه‌شنبه', 'wp-dorna'),
    3 => __('چهارشنبه', 'wp-dorna'),
    4 => __('پنجشنبه', 'wp-dorna'),
    5 => __('جمعه', 'wp-dorna'),
);
?>
<?php if ($card) : ?>
<section class="wpd-card">
    <h2 class="wpd-card__title"><?php esc_html_e('سینک خودکار', 'wp-dorna'); ?></h2>
<?php endif; ?>

    <p class="wpd-muted"><?php esc_html_e('سینک کامل را خودکار در ساعت کم‌بازدید اجرا کنید تا هر ناهماهنگی احتمالی هر شب اصلاح شود. به درنا فشاری نمی‌آید (هر چند ثانیه یک درخواست).', 'wp-dorna'); ?></p>

    <form class="wpd-schedule" data-schedule-form>
        <label class="wpd-field">
            <span><?php esc_html_e('اجرا', 'wp-dorna'); ?></span>
            <select class="wpd-input" name="mode" data-schedule="mode">
                <option value="off" <?php selected($schedule['mode'], 'off'); ?>><?php esc_html_e('خاموش', 'wp-dorna'); ?></option>
                <option value="nightly" <?php selected($schedule['mode'], 'nightly'); ?>><?php esc_html_e('هر شب', 'wp-dorna'); ?></option>
                <option value="weekly" <?php selected($schedule['mode'], 'weekly'); ?>><?php esc_html_e('هر هفته', 'wp-dorna'); ?></option>
            </select>
        </label>

        <label class="wpd-field" data-schedule="weekday" <?php echo $schedule['mode'] === 'weekly' ? '' : 'hidden'; ?>>
            <span><?php esc_html_e('روز', 'wp-dorna'); ?></span>
            <select class="wpd-input" name="weekday">
                <?php foreach ($weekdays as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($schedule['weekday'], $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="wpd-field" data-schedule="hour" <?php echo $schedule['mode'] === 'off' ? 'hidden' : ''; ?>>
            <span><?php esc_html_e('ساعت', 'wp-dorna'); ?></span>
            <select class="wpd-input" name="hour">
                <?php for ($hour = 0; $hour < 24; $hour++) : ?>
                    <option value="<?php echo esc_attr($hour); ?>" <?php selected($schedule['hour'], $hour); ?>><?php echo esc_html(Format::digits(sprintf('%02d:00', $hour))); ?></option>
                <?php endfor; ?>
            </select>
        </label>

        <button type="submit" class="wpd-btn is-primary"><?php esc_html_e('ذخیره', 'wp-dorna'); ?></button>
    </form>

    <p class="wpd-note" data-schedule="message" <?php echo $schedule['next_due'] && $schedule['mode'] !== 'off' ? '' : 'hidden'; ?>>
        <?php
        if ($schedule['next_due'] && $schedule['mode'] !== 'off') {
            /* translators: %s: date and time */
            printf(esc_html__('اجرای بعدی: %s', 'wp-dorna'), esc_html(Format::datetime($schedule['next_due'])));
        }
        ?>
    </p>
    <p class="wpd-muted"><?php esc_html_e('وردپرس فقط وقتی کاری انجام می‌دهد که کسی به سایت سر بزند؛ اگر در آن ساعت بازدیدی نباشد، اولین بازدید بعدی (تا ۶ ساعت بعد) اجرایش می‌کند. برای دقت کامل، cron سرور را فعال کنید.', 'wp-dorna'); ?></p>

<?php if ($card) : ?>
</section>
<?php endif; ?>
