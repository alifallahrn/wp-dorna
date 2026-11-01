<?php
/**
 * First-run checklist.
 *
 * @var array  $steps        [{key, done, optional}]
 * @var bool   $ready        the required steps are done
 * @var array  $connection
 * @var string $status       connection status
 * @var array  $schedule
 * @var string $start_action
 * @var string $import_url
 * @var string $settings_url
 * @var array  $full
 */

use WPDorna\Admin\Format;
use WPDorna\Admin\Icons;
use WPDorna\Admin\View;
use WPDorna\Admin\Pages\SetupPage;

defined('ABSPATH') || exit;

$done = array();
foreach ($steps as $step) {
    $done[$step['key']] = $step['done'];
}

$titles = array(
    'connect'   => __('اتصال به درنا', 'wp-dorna'),
    'full_sync' => __('سینک کامل اول', 'wp-dorna'),
    'import'    => __('ورود کالاهای جدید', 'wp-dorna'),
    'schedule'  => __('سینک شبانه', 'wp-dorna'),
);
$number = 0;
?>
<section class="wpd-card wpd-card--flush" id="wpd-setup">
    <ol class="wpd-steps">
        <?php foreach ($steps as $step) : $number++; $key = $step['key']; ?>
            <li class="wpd-step<?php echo $step['done'] ? ' is-done' : ''; ?>">
                <span class="wpd-step__num" aria-hidden="true"><?php echo $step['done'] ? Icons::svg('check') : esc_html(Format::num($number)); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div class="wpd-step__body">
                    <h2 class="wpd-step__title">
                        <?php echo esc_html($titles[$key]); ?>
                        <?php if ($step['optional']) : ?><span class="wpd-badge is-mut"><i></i><?php esc_html_e('اختیاری', 'wp-dorna'); ?></span><?php endif; ?>
                        <?php if ($step['done']) : ?><span class="wpd-badge is-good"><i></i><?php esc_html_e('انجام شد', 'wp-dorna'); ?></span><?php endif; ?>
                    </h2>

                    <?php if ($key === 'connect') : ?>
                        <?php if ($step['done']) : ?>
                            <p class="wpd-muted">
                                <?php
                                echo $connection['business_name'] !== ''
                                    /* translators: %s: business name */
                                    ? esc_html(sprintf(__('وصل به کسب‌وکار «%s»', 'wp-dorna'), $connection['business_name']))
                                    : esc_html__('با توکن دستی به درنا وصل است.', 'wp-dorna');
                                ?>
                            </p>
                        <?php else : ?>
                            <p class="wpd-muted"><?php esc_html_e('وارد حساب درنا می‌شوید، کسب‌وکار را انتخاب و تأیید می‌کنید و به همین‌جا برمی‌گردید. نیازی به کپی‌کردن توکن نیست.', 'wp-dorna'); ?></p>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wpd-inline">
                                <input type="hidden" name="action" value="<?php echo esc_attr($start_action); ?>">
                                <?php wp_nonce_field($start_action); ?>
                                <button type="submit" class="wpd-btn is-primary"><?php echo Icons::svg('link'); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e('اتصال به درنا', 'wp-dorna'); ?></span></button>
                                <a class="wpd-link" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('وارد کردن دستی توکن', 'wp-dorna'); ?></a>
                            </form>
                        <?php endif; ?>

                    <?php elseif ($key === 'full_sync') : ?>
                        <?php if (!$done['connect']) : ?>
                            <p class="wpd-muted"><?php esc_html_e('اول به درنا وصل شوید.', 'wp-dorna'); ?></p>
                        <?php else : ?>
                            <?php View::render('partials/sku-hint'); ?>
                            <p class="wpd-muted"><?php esc_html_e('همه‌ی کالاهای درنا را یک‌بار آرام بررسی می‌کند تا قیمت و موجودی سایت با درنا یکی شود. می‌توانید صفحه را ببندید.', 'wp-dorna'); ?></p>
                            <?php View::render('partials/full-sync-panel'); ?>
                        <?php endif; ?>

                    <?php elseif ($key === 'import') : ?>
                        <p class="wpd-muted"><?php esc_html_e('اگر در درنا کالایی هست که در سایت نیست، می‌توانید به‌صورت پیش‌نویس وارد کنید. اگر همه‌ی کالاها در سایت هست، رد شوید.', 'wp-dorna'); ?></p>
                        <?php if (!$step['done']) : ?>
                            <div class="wpd-actions">
                                <a class="wpd-btn" href="<?php echo esc_url($import_url); ?>"><?php echo Icons::svg('download'); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e('رفتن به ورود کالا', 'wp-dorna'); ?></span></a>
                                <button type="button" class="wpd-btn is-ghost" data-wizard-step="import"><?php esc_html_e('بعداً', 'wp-dorna'); ?></button>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($key === 'schedule') : ?>
                        <?php View::render('partials/schedule-form', array('schedule' => array_merge($schedule, array('mode' => $schedule['mode'] === 'off' && !$step['done'] ? 'nightly' : $schedule['mode'])), 'card' => false)); ?>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>

    <div class="wpd-toolbar wpd-toolbar--end">
        <button type="button" class="wpd-btn is-ghost" data-wizard-finish><?php esc_html_e('رد کردن راه‌اندازی', 'wp-dorna'); ?></button>
        <button type="button" class="wpd-btn is-primary" data-wizard-finish <?php disabled(!$ready); ?>><?php esc_html_e('پایان راه‌اندازی', 'wp-dorna'); ?></button>
    </div>
</section>
