<?php
/**
 * فایل View مرحله ۲: تنظیمات درون‌ریزی و لاگ
 *
 * (این فایل کامل و به‌روز است)
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

<!-- کارت ۱: تنظیمات اصلی -->
<div class="rpi-card">
    <div class="rpi-card__header">
        <h2 class="rpi-card__title"><?php _e('مرحله ۲: تنظیمات نهایی درون‌ریزی', RPI_TEXT_DOMAIN); ?></h2>
        <p class="rpi-card__description">
            <?php _e('تنظیمات مربوط به نحوه ذخیره محصولات در ووکامرس را انتخاب کنید.', RPI_TEXT_DOMAIN); ?>
        </p>
    </div>
    <div class="rpi-card__body">
        
        <!-- انتخاب دسته‌بندی ووکامرس -->
        <div class="rpi-form-group">
            <label for="rpi-product-category"><?php _e('انتخاب دسته‌بندی مقصد', RPI_TEXT_DOMAIN); ?></label>
            <?php
                // نمایش دراپ‌داون دسته‌بندی‌های ووکامرس
                wp_dropdown_categories(array(
                    'taxonomy'         => 'product_cat', // فقط دسته‌بندی‌های ووکامرس
                    'name'             => 'rpi_product_category',
                    'id'               => 'rpi-product-category',
                    'class'            => 'rpi-input-field', // استفاده از استایل فیلدها
                    'show_option_none' => __('— یک دسته‌بندی را انتخاب کنید —', RPI_TEXT_DOMAIN),
                    'hierarchical'     => true,
                    'value_field'      => 'term_id',
                    'hide_empty'       => false,
                ));
            ?>
            <p class="description">
                <?php _e('محصولات وارد شده در این دسته‌بندی قرار خواهند گرفت.', RPI_TEXT_DOMAIN); ?>
            </p>
        </div>

        <!-- سایر تنظیمات -->
         <div class="rpi-form-group">
            <label for="rpi-product-status"><?php _e('وضعیت محصول پس از درون‌ریزی', RPI_TEXT_DOMAIN); ?></label>
            <select id="rpi-product-status" name="rpi_product_status" class="rpi-input-field rpi-input-field--small">
                <option value="publish"><?php _e('منتشر شده', RPI_TEXT_DOMAIN); ?></option>
                <option value="draft" selected><?php _e('پیش‌نویس (توصیه می‌شود)', RPI_TEXT_DOMAIN); ?></option>
                <option value="pending"><?php _e('در انتظار بازبینی', RPI_TEXT_DOMAIN); ?></option>
            </select>
            <p class="description">
                <?php _e('توصیه می‌شود ابتدا محصولات را به صورت پیش‌نویس وارد کنید تا بتوانید آن‌ها را بازبینی نمایید.', RPI_TEXT_DOMAIN); ?>
            </p>
        </div>

        <!-- دکمه شروع درون‌ریزی در main-page.php مدیریت می‌شود -->

    </div>
</div>

<!-- کارت ۲: نوار پیشرفت و لاگ -->
<div class="rpi-card">
    <div class="rpi-card__header">
        <h2 class="rpi-card__title"><?php _e('وضعیت فرآیند (Log)', RPI_TEXT_DOMAIN); ?></h2>
    </div>
    <div class="rpi-card__body">
        <!-- نوار پیشرفت (توسط JS ساخته می‌شود) -->
        <div id="rpi-progress-bar-container" style="display: none; margin-bottom: 20px;">
            <!-- محتوای نوار پیشرفت در JS (admin.js) ساخته می‌شود -->
        </div>
        
        <!-- کانتینر لاگ‌ها -->
        <div id="rpi-log-container" style="height: 250px; background: var(--rpi-color-midnight); border-radius: var(--rpi-radius-md); padding: 15px; overflow-y: auto; font-family: monospace; font-size: 13px; color: #f1f1f1; border: 1px solid var(--rpi-color-border); line-height: 1.6;">
            <?php _e('برای شروع فرآیند، روی دکمه "شروع درون‌ریزی نهایی" کلیک کنید...', RPI_TEXT_DOMAIN); ?>
        </div>
    </div>
</div>