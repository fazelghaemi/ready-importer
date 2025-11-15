<?php
/**
 * فایل View مرحله ۱: دریافت لینک‌ها
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/admin/views
 * @author     Ready Studio
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="rpi-card">
    <div class="rpi-card__header">
        <h2 class="rpi-card__title"><?php _e('مرحله ۱: لینک محصولات را وارد کنید', RPI_TEXT_DOMAIN); ?></h2>
        <p class="rpi-card__description">
            <?php _e('لینک محصولات مورد نظر از دیجی‌کالا را در کادر زیر وارد کنید. هر لینک باید در یک خط جداگانه باشد.', RPI_TEXT_DOMAIN); ?>
        </p>
    </div>
    <div class="rpi-card__body">
        <div class="rpi-form-group">
            <label for="rpi-product-links"><?php _e('لینک محصولات (هر لینک در یک خط)', RPI_TEXT_DOMAIN); ?></label>
            <textarea id="rpi-product-links" 
                      name="rpi_product_links" 
                      class="rpi-textarea-field"
                      rows="10"
                      placeholder="https://www.digikala.com/product/dkp-123...
https://www.digikala.com/product/dkp-456...
https://www.digikala.com/product/dkp-789..."></textarea>
            <p class="description">
                <?php _e('می‌توانید لینک صفحه دسته‌بندی دیجی‌کالا را نیز وارد کنید تا تمام محصولات آن صفحه اسکرپ شوند. (این قابلیت در حال توسعه است)', RPI_TEXT_DOMAIN); ?>
            </p>
        </div>

        <div class="rpi-form-group" style="text-align: left;"> <!-- دکمه در انتهای فرم (سمت چپ در RTL) -->
            <!-- این دکمه باید به صورت <button type="submit"> باشد تا فرم را به مرحله بعد ببرد -->
             <a href="<?Fphp echo esc_url(admin_url('admin.php?page=ready-importer&step=2')); ?>"
               class="rpi-button rpi-button--primary">
                <?php _e('ادامه (تنظیمات دسته‌بندی)', RPI_TEXT_DOMAIN); ?>
             </a>
             
             <!-- 
                نکته: در پیاده‌سازی نهایی، ما با کلیک روی این دکمه، 
                لینک‌ها را با ایجکس اعتبارسنجی می‌کنیم و سپس
                کاربر را به مرحله ۲ می‌بریم (یا مرحله ۲ را در همین صفحه نشان می‌دهیم).
                فعلاً برای سادگی، فقط لینک به ?step=2 می‌دهیم.
             -->

        </div>
    </div>
</div>