<?php
/**
 * @var array  $connection        stored connection data
 * @var string $status            none | connected | manual | rejected | mismatch
 * @var string $start_action
 * @var string $disconnect_action
 * @var bool   $wizard_open
 * @var string $masked
 * @var bool   $from_constant
 * @var bool   $remove_data
 * @var bool   $saved
 * @var string $action
 * @var string $post_url
 */

use WPDorna\Admin\Format;
use WPDorna\Admin\Icons;
use WPDorna\Admin\Pages\SetupPage;

defined('ABSPATH') || exit;

$headline = array(
    'none'      => array('mut', __('به درنا وصل نیستید', 'wp-dorna')),
    'connected' => array('good', __('به درنا وصل است', 'wp-dorna')),
    'manual'    => array('info', __('وصل با توکن دستی', 'wp-dorna')),
    'rejected'  => array('bad', __('درنا توکن را قبول نمی‌کند', 'wp-dorna')),
    'mismatch'  => array('bad', __('این سایت با توکن سایت دیگری وصل است', 'wp-dorna')),
);
list($tone, $title) = $headline[$status];

$help = array(
    'none'      => __('«اتصال به درنا» را بزنید، وارد حساب درنا شوید و کسب‌وکار را تأیید کنید. به همین صفحه برمی‌گردید و همه‌چیز آماده است.', 'wp-dorna'),
    'manual'    => __('با دکمه‌ی اتصال، توکن را خود درنا برای همین سایت می‌سازد؛ آدرس سایت در درنا ثبت می‌شود و وضعیت اتصال را روی داشبورد درنا می‌بینید.', 'wp-dorna'),
    'rejected'  => __('اتصال از سمت درنا قطع شده یا سایت دیگری جایش وصل شده است. همگام‌سازی و ارسال فاکتورها متوقف است؛ دوباره وصل شوید.', 'wp-dorna'),
    'mismatch'  => __('درنا توکن را فقط از آدرس ثبت‌شده قبول می‌کند. اگر این یک نسخه‌ی تستی یا کپی سایت اصلی است، همین‌طور بماند تا روی کسب‌وکار واقعی چیزی نوشته نشود. اگر سایت اصلی است، دوباره وصل شوید.', 'wp-dorna'),
);
?>
<?php if ($saved) : ?>
    <div class="wpd-callout is-good" role="status">
        <span class="wpd-ic is-good"><?php echo Icons::svg('check'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <div class="wpd-callout__text"><strong><?php esc_html_e('تنظیمات ذخیره شد.', 'wp-dorna'); ?></strong></div>
    </div>
<?php endif; ?>

<section class="wpd-card wpd-conn" id="wpd-connection">
    <div class="wpd-conn__head">
        <span class="wpd-ic is-<?php echo esc_attr($tone === 'info' ? '' : $tone); ?>"><?php echo Icons::svg($tone === 'bad' ? 'alert' : 'link'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <div class="wpd-conn__text">
            <h2 class="wpd-card__title"><?php echo esc_html($title); ?></h2>

            <?php if ($status === 'connected') : ?>
                <p class="wpd-muted">
                    <?php
                    /* translators: 1: business name, 2: business username */
                    printf(esc_html__('کسب‌وکار: %1$s (%2$s)', 'wp-dorna'), esc_html($connection['business_name']), '<bdi dir="ltr">' . esc_html($connection['business_username']) . '</bdi>'); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
                    ?>
                </p>
                <p class="wpd-muted">
                    <?php
                    /* translators: 1: site address, 2: relative time */
                    printf(esc_html__('آدرس ثبت‌شده در درنا: %1$s — وصل‌شده %2$s', 'wp-dorna'), '<bdi dir="ltr">' . esc_html(preg_replace('#^https?://#', '', $connection['site'])) . '</bdi>', esc_html(Format::ago($connection['connected_at']))); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
                    ?>
                </p>
            <?php else : ?>
                <p class="wpd-muted"><?php echo esc_html($help[$status]); ?></p>
            <?php endif; ?>

            <p class="wpd-note" id="wpd-test-result" hidden></p>
        </div>

        <div class="wpd-actions">
            <?php if ($status === 'connected' || $status === 'manual') : ?>
                <button type="button" class="wpd-btn" data-settings="test"><?php echo Icons::svg('sync'); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e('آزمایش اتصال', 'wp-dorna'); ?></span></button>
            <?php endif; ?>

            <?php if ($status !== 'connected') : ?>
                <form method="post" action="<?php echo esc_url($post_url); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr($start_action); ?>">
                    <?php wp_nonce_field($start_action); ?>
                    <button type="submit" class="wpd-btn is-primary"><?php echo Icons::svg('link'); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo $status === 'none' ? esc_html__('اتصال به درنا', 'wp-dorna') : esc_html__('اتصال دوباره با دکمه', 'wp-dorna'); ?></span></button>
                </form>
            <?php endif; ?>

            <?php if ($status !== 'none') : ?>
                <form method="post" action="<?php echo esc_url($post_url); ?>" data-confirm="disconnect">
                    <input type="hidden" name="action" value="<?php echo esc_attr($disconnect_action); ?>">
                    <?php wp_nonce_field($disconnect_action); ?>
                    <button type="submit" class="wpd-btn is-ghost"><?php esc_html_e('قطع اتصال', 'wp-dorna'); ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<form method="post" action="<?php echo esc_url($post_url); ?>" class="wpd-settings" id="wpd-settings">
    <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
    <?php wp_nonce_field($action); ?>

    <section class="wpd-card wpd-card--flush">
        <details class="wpd-row-details" <?php echo $status === 'manual' ? 'open' : ''; ?>>
            <summary class="wpd-row-details__summary"><?php esc_html_e('پیشرفته: وارد کردن دستی توکن', 'wp-dorna'); ?></summary>
            <div class="wpd-row">
                <div class="wpd-row__text">
                    <label for="wpd-api-key"><?php esc_html_e('توکن درنا', 'wp-dorna'); ?></label>
                    <p><?php esc_html_e('فقط اگر نمی‌توانید با دکمه‌ی اتصال وصل شوید. توکن دستی به آدرس سایت قفل نمی‌شود.', 'wp-dorna'); ?></p>
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
        </details>

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

        <div class="wpd-row">
            <div class="wpd-row__text">
                <span class="wpd-label"><?php esc_html_e('راه‌اندازی اولیه', 'wp-dorna'); ?></span>
                <p><?php esc_html_e('چهار قدم کوتاه: اتصال، سینک کامل، ورود کالا و سینک شبانه.', 'wp-dorna'); ?></p>
            </div>
            <div class="wpd-row__control">
                <?php if ($wizard_open) : ?>
                    <a class="wpd-btn" href="<?php echo esc_url(admin_url('admin.php?page=wp-dorna-setup')); ?>"><?php esc_html_e('ادامه‌ی راه‌اندازی', 'wp-dorna'); ?></a>
                <?php else : ?>
                    <button type="submit" class="wpd-btn" form="wpd-reopen-wizard"><?php esc_html_e('اجرای دوباره‌ی راه‌اندازی', 'wp-dorna'); ?></button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="wpd-savebar">
        <button type="submit" class="wpd-btn is-primary"><?php esc_html_e('ذخیره تنظیمات', 'wp-dorna'); ?></button>
    </div>
</form>

<form method="post" action="<?php echo esc_url($post_url); ?>" id="wpd-reopen-wizard" hidden>
    <input type="hidden" name="action" value="<?php echo esc_attr(SetupPage::REOPEN_ACTION); ?>">
    <?php wp_nonce_field(SetupPage::REOPEN_ACTION); ?>
</form>
