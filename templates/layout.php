<?php
/**
 * Shell of every plugin screen: branded header and page navigation.
 *
 * @var \WPDorna\Admin\Pages\Page[] $pages
 * @var \WPDorna\Admin\Pages\Page   $current
 * @var callable                    $content
 */

use WPDorna\Admin\Icons;

defined('ABSPATH') || exit;
?>
<div class="wrap wpd" dir="rtl">
    <header class="wpd-head">
        <div class="wpd-head__brand">
            <span class="wpd-logo" aria-hidden="true"><?php echo Icons::svg('link'); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup ?></span>
            <div>
                <span class="wpd-eyebrow"><?php esc_html_e('اتصال ووکامرس به درنا', 'wp-dorna'); ?></span>
                <h1 class="wpd-title"><?php echo esc_html($current->title()); ?></h1>
                <p class="wpd-purpose"><?php echo esc_html($current->purpose()); ?></p>
            </div>
        </div>

        <nav class="wpd-nav" aria-label="<?php esc_attr_e('بخش‌های افزونه', 'wp-dorna'); ?>">
            <?php foreach ($pages as $page) : ?>
                <a class="wpd-nav__link<?php echo $page->slug() === $current->slug() ? ' is-active' : ''; ?>"
                   href="<?php echo esc_url($page->url()); ?>"
                   <?php echo $page->slug() === $current->slug() ? 'aria-current="page"' : ''; ?>>
                    <?php echo Icons::svg($page->icon()); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <span><?php echo esc_html($page->label()); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <main class="wpd-main">
        <?php call_user_func($content); ?>
    </main>
</div>
