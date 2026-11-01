<?php
/**
 * @var string[] $dates
 * @var string   $date
 * @var string[] $lines
 * @var string   $url
 */

use WPDorna\Admin\Icons;

defined('ABSPATH') || exit;
?>
<section class="wpd-card wpd-card--flush" id="wpd-logs">
    <?php if (!$dates) : ?>
        <div class="wpd-empty"><?php echo Icons::svg('check'); // phpcs:ignore WordPress.Security.EscapeOutput ?><p><?php esc_html_e('هنوز چیزی ثبت نشده؛ یعنی مشکلی پیش نیامده است.', 'wp-dorna'); ?></p></div>
    <?php else : ?>
        <div class="wpd-toolbar">
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="wpd-inline">
                <input type="hidden" name="page" value="wp-dorna-logs">
                <label class="wpd-muted" for="wpd-log-date"><?php esc_html_e('روز', 'wp-dorna'); ?></label>
                <select class="wpd-input" id="wpd-log-date" name="date" onchange="this.form.submit()">
                    <?php foreach ($dates as $item) : ?>
                        <option value="<?php echo esc_attr($item); ?>" <?php selected($item, $date); ?>><?php echo esc_html($item); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <button type="button" class="wpd-btn is-ghost" data-logs="clear"><?php esc_html_e('پاک‌کردن همه‌ی گزارش‌ها', 'wp-dorna'); ?></button>
        </div>

        <div class="wpd-log" tabindex="0">
            <?php foreach ($lines as $line) :
                // "[00:08:18] ERROR message {"json":"context"}"
                if (!preg_match('/^\[([\d:]+)\] (ERROR|WARN|INFO) (.*?)(?: (\{.*\}))?$/u', $line, $part)) {
                    $part = array($line, '', 'INFO', $line, '');
                }
                $tone = array('ERROR' => 'bad', 'WARN' => 'warn', 'INFO' => 'mut');
                ?>
                <div class="wpd-log__line is-<?php echo esc_attr($tone[$part[2]]); ?>">
                    <bdi dir="ltr" class="wpd-log__meta"><?php echo esc_html($part[1] . ' ' . $part[2]); ?></bdi>
                    <span><?php echo esc_html($part[3]); ?></span>
                    <?php $context = !empty($part[4]) ? json_decode($part[4], true) : null; ?>
                    <?php if (is_array($context)) : ?>
                        <span class="wpd-log__ctx">
                            <?php foreach ($context as $key => $value) : ?>
                                <span class="wpd-log__kv"><bdi dir="ltr"><?php echo esc_html($key); ?></bdi>: <bdi dir="auto"><?php echo esc_html(is_scalar($value) ? (string) $value : wp_json_encode($value, JSON_UNESCAPED_UNICODE)); ?></bdi></span>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
