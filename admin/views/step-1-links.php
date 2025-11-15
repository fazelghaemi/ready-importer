<?php
/**
 * فایل View مرحله ۱: دریافت لینک‌ها
 * (این فایل توسط main-page.php بارگذاری می‌شود)
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
        <h2 class="rpi-card__title"><?php _e('مرحله ۱: لینک‌ها را وارد کنید', RPI_TEXT_DOMAIN); ?></h2>
        <p class="rpi-card__description">
            <?php _e('لینک محصولات یا دسته‌بندی‌های مورد نظر از دیجی‌کالا را در کادر زیر وارد کنید. هر لینک باید در یک خط جداگانه باشد.', RPI_TEXT_DOMAIN); ?>
        </p>
    </div>
    <div class="rpi-card__body">
        <div class="rpi-form-group">
            <label for="rpi-product-links"><?php _e('لینک محصولات یا دسته‌بندی‌ها (هر لینک در یک خط)', RPI_TEXT_DOMAIN); ?></label>
            <textarea id="rpi-product-links" 
                      name="rpi_product_links" 
                      class="rpi-textarea-field"
                      rows="10"
                      placeholder="https://www.digikala.com/product/dkp-123... (لینک محصول)
https://www.digikala.com/search/category-mobile-phone/ (لینک دسته‌بندی)
https://www.digikala.com/product/dkp-789..."></textarea>
            <p class="description">
                <?php _e('افزونه به صورت خودکار لینک‌های دسته‌بندی را شناسایی کرده و تمام محصولات *صفحه اول* آن دسته‌بندی را به صف اضافه می‌کند.', RPI_TEXT_DOMAIN); ?>
            </p>
        </div>

        <div class="rpi-form-group" style="text-align: left;"> <!-- دکمه در انتهای فرم (سمت چپ در RTL) -->
             <button type="button" id="rpi-continue-button"
               class="rpi-button rpi-button--primary">
                <?php _e('ادامه (تنظیمات دسته‌بندی)', RPI_TEXT_DOMAIN); ?>
             </button>
        </div>
    </div>
</div>