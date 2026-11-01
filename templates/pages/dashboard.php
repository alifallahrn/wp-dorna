<?php
/**
 * @var bool   $has_key
 * @var array  $status
 * @var array  $full
 * @var array  $orders
 * @var int    $next_run
 * @var array  $callouts
 * @var string $sync_url
 * @var string $order_url
 */

use WPDorna\Admin\Format;
use WPDorna\Admin\Icons;

defined('ABSPATH') || exit;

$stats      = isset($status['last_stats']) && is_array($status['last_stats']) ? $status['last_stats'] : array();
$sync_ok    = $has_key && empty($status['last_error']) && !empty($status['last_success']);
$sync_tone  = !$has_key ? 'mut' : (!empty($status['last_error']) ? 'warn' : ($sync_ok ? 'good' : 'mut'));
$sync_label = !$has_key ? __('متصل نیست', 'wp-dorna') : (!empty($status['last_error']) ? __('نیازمند توجه', 'wp-dorna') : ($sync_ok ? __('سالم', 'wp-dorna') : __('در انتظار اولین اجرا', 'wp-dorna')));
?>

<?php foreach ($callouts as $callout) : ?>
    <div class="wpd-callout is-<?php echo esc_attr($callout['tone']); ?>" role="status">
        <span class="wpd-ic is-<?php echo esc_attr($callout['tone']); ?>"><?php echo Icons::svg($callout['icon']); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <div class="wpd-callout__text">
            <strong><?php echo esc_html($callout['title']); ?></strong>
            <p><?php echo esc_html($callout['text']); ?></p>
        </div>
        <a class="wpd-btn" href="<?php echo esc_url($callout['action'][1]); ?>"><?php echo esc_html($callout['action'][0]); ?></a>
    </div>
<?php endforeach; ?>

<section class="wpd-grid wpd-grid--3">
    <article class="wpd-card wpd-kpi">
        <span class="wpd-kpi__label"><?php esc_html_e('بروزرسانی خودکار محصولات', 'wp-dorna'); ?></span>
        <span class="wpd-badge is-<?php echo esc_attr($sync_tone); ?>"><i></i><?php echo esc_html($sync_label); ?></span>
        <span class="wpd-kpi__value"><?php echo esc_html(Format::ago($status['last_success'])); ?></span>
        <span class="wpd-kpi__hint">
            <?php
            if ($next_run && $has_key) {
                if ($next_run > time()) {
                    /* translators: %s: relative time such as "1 minute" */
                    printf(esc_html__('اجرای بعدی: %s دیگر', 'wp-dorna'), esc_html(human_time_diff(time(), $next_run)));
                } else {
                    esc_html_e('اجرای بعدی: به‌زودی (وردپرس با بازدید از سایت اجرا می‌کند).', 'wp-dorna');
                }
            } else {
                esc_html_e('هر دقیقه تغییرهای درنا را می‌گیرد.', 'wp-dorna');
            }
            ?>
        </span>
    </article>

    <article class="wpd-card wpd-kpi">
        <span class="wpd-kpi__label"><?php esc_html_e('سفارش‌های ۳۰ روز اخیر', 'wp-dorna'); ?></span>
        <span class="wpd-kpi__value"><?php echo esc_html(Format::num($orders['sent'])); ?>
            <small><?php esc_html_e('فاکتور ثبت‌شده', 'wp-dorna'); ?></small>
        </span>
        <span class="wpd-kpi__hint">
            <?php if ($orders['unsent'] > 0) : ?>
                <a href="<?php echo esc_url($order_url); ?>" class="wpd-link is-warn">
                    <?php
                    /* translators: %s: number of orders */
                    printf(esc_html__('%s سفارش هنوز نرسیده', 'wp-dorna'), esc_html(Format::num($orders['unsent'])));
                    ?>
                </a>
            <?php else : ?>
                <?php esc_html_e('همه‌ی سفارش‌ها در درنا ثبت شده‌اند.', 'wp-dorna'); ?>
            <?php endif; ?>
        </span>
    </article>

    <article class="wpd-card wpd-kpi">
        <span class="wpd-kpi__label"><?php esc_html_e('سینک کامل', 'wp-dorna'); ?></span>
        <?php
        $full_tone  = array('completed' => 'good', 'running' => 'info', 'paused' => 'warn', 'failed' => 'bad');
        $full_label = array(
            'idle'      => __('هنوز اجرا نشده', 'wp-dorna'),
            'running'   => __('در حال اجرا', 'wp-dorna'),
            'paused'    => __('متوقف‌شده', 'wp-dorna'),
            'completed' => __('انجام شده', 'wp-dorna'),
            'cancelled' => __('لغو شده', 'wp-dorna'),
            'failed'    => __('ناموفق', 'wp-dorna'),
        );
        ?>
        <span class="wpd-badge is-<?php echo esc_attr(isset($full_tone[$full['status']]) ? $full_tone[$full['status']] : 'mut'); ?>"><i></i><?php echo esc_html($full_label[$full['status']] ?? $full['status']); ?></span>
        <span class="wpd-kpi__value"><?php echo esc_html($full['finished_at'] && $full['status'] === 'completed' ? Format::ago($full['finished_at']) : '—'); ?></span>
        <span class="wpd-kpi__hint"><a class="wpd-link" href="<?php echo esc_url($sync_url); ?>"><?php esc_html_e('مدیریت سینک کامل', 'wp-dorna'); ?></a></span>
    </article>
</section>

<?php if ($stats) : ?>
    <section class="wpd-card">
        <h2 class="wpd-card__title"><?php esc_html_e('آخرین اجرای خودکار', 'wp-dorna'); ?></h2>
        <dl class="wpd-facts">
            <div><dt><?php esc_html_e('دریافت‌شده از درنا', 'wp-dorna'); ?></dt><dd><?php echo esc_html(Format::num($stats['fetched'] ?? 0)); ?></dd></div>
            <div><dt><?php esc_html_e('بروزرسانی‌شده', 'wp-dorna'); ?></dt><dd><?php echo esc_html(Format::num($stats['updated'] ?? 0)); ?></dd></div>
            <div><dt><?php esc_html_e('بدون تغییر', 'wp-dorna'); ?></dt><dd><?php echo esc_html(Format::num($stats['unchanged'] ?? 0)); ?></dd></div>
            <div><dt><?php esc_html_e('در سایت نیست', 'wp-dorna'); ?></dt><dd><?php echo esc_html(Format::num($stats['missing'] ?? 0)); ?></dd></div>
            <div><dt><?php esc_html_e('خطا', 'wp-dorna'); ?></dt><dd><?php echo esc_html(Format::num($stats['failed'] ?? 0)); ?></dd></div>
        </dl>
    </section>
<?php endif; ?>

<?php if (!$has_key && !$callouts) : ?>
    <div class="wpd-empty"><?php echo Icons::svg('link'); // phpcs:ignore WordPress.Security.EscapeOutput ?><p><?php esc_html_e('برای شروع، توکن درنا را در تنظیمات وارد کنید.', 'wp-dorna'); ?></p></div>
<?php endif; ?>
