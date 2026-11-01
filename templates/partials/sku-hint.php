<?php
/**
 * Before the first full sync: Dorna and the store match products by code, so the code
 * (SKU) has to be set on the store's products first. Shown in the setup checklist.
 */

use WPDorna\Admin\Icons;

defined('ABSPATH') || exit;
?>
<div class="wpd-hint">
    <strong><?php echo Icons::svg('alert'); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e('اول کدها را بگذارید', 'wp-dorna'); ?></span></strong>
    <p><?php esc_html_e('سایت و درنا کالاها را با «کد کالا» به هم وصل می‌کنند. «شناسه محصول (SKU)» هر کالای سایت باید دقیقاً همان کد کالایی باشد که در درنا برایش ثبت شده؛ کالایی که کدش یکی نباشد یا خالی باشد از درنا بروز نمی‌شود.', 'wp-dorna'); ?></p>
    <ol>
        <li><?php esc_html_e('کالا را در وردپرس باز کنید: «محصولات ← همه محصولات ← ویرایش».', 'wp-dorna'); ?></li>
        <li><?php esc_html_e('در «اطلاعات محصول ← موجودی انبار»، کد کالای درنا را در «شناسه محصول (SKU)» بنویسید و کالا را ذخیره کنید.', 'wp-dorna'); ?></li>
        <li><?php esc_html_e('در کادر «انتشار» همان صفحه، دکمه‌ی «بروزرسانی از درنا» را بزنید تا قیمت و موجودی همان لحظه خوانده شود.', 'wp-dorna'); ?></li>
    </ol>
    <p><?php esc_html_e('کالاهای زیاد دارید؟ بعد از گذاشتن کدها، سینک کامل زیر همه را یک‌جا بروزرسانی می‌کند. کالاهای درنا که هنوز در سایت نیستند را هم می‌توانید با کدشان از «ورود کالا» وارد کنید.', 'wp-dorna'); ?></p>
</div>
