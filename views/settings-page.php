<?php
/**
 * فایل View صفحه تنظیمات (Settings Page)
 *
 * --- جدید (فاز ۲) ---
 *
 * این فایل فرم HTML صفحه تنظیمات را با استفاده از
 * Settings API وردپرس و با همان برندینگ ردی استودیو رندر می‌کند.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/admin/views
 * @author     Ready Studio
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// (متغیرهای هدر از main-page.php کپی شده‌اند)
$plugin_version = defined('RPI_VERSION') ? RPI_VERSION : '1.0.0';
$logo_url = RPI_PLUGIN_URL . 'assets/logo/readystudio-logo.svg';

?>

<!--
    این div والد اصلی است که CSS ما به آن متصل می‌شود
    تا استایل‌های ما فقط در این صفحه اعمال شوند.
-->
<div class="wrap" id="rpi-admin-wrapper">

    <!-- ۱. هدر صفحه با برندینگ ردی استودیو -->
    <header class="rpi-header">
        <div class="rpi-header__title-wrapper">
            <div class="rpi-header__logo">
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php _e('لوگوی ردی استودیو', RPI_TEXT_DOMAIN); ?>">
            </div>
            <div>
                <h1 class="rpi-header__title"><?php _e('تنظیمات Ready Importer', RPI_TEXT_DOMAIN); ?></h1>
                <p class="rpi-header__subtitle"><?php _e('قوانین و تنظیمات پیشرفته درون‌ریزی را مدیریت کنید', RPI_TEXT_DOMAIN); ?></p>
            </div>
        </div>
        <div class="rpi-header__version">
            <span><?php printf(__('نسخه %s', RPI_TEXT_DOMAIN), esc_html($plugin_version)); ?></span>
        </div>
    </header>

    <!-- ۲. فرم تنظیمات وردپرس -->
    <form method="post" action="options.php">
        <?php
            // این توابع حیاتی وردپرس، تمام بخش‌ها، فیلدها و Nonceها را رندر می‌کنند
            settings_fields('rpi_settings_group'); // نام گروهی که در کلاس Settings ثبت کردیم
        ?>
        
        <!-- 
            ما بخش‌ها را درون کارت‌های زیبای خودمان رندر می‌کنیم 
            do_settings_sections نام گروه را *نمی‌گیرد*، بلکه شناسه صفحه (slug) را می‌گیرد.
        -->
        
        <!-- کارت اول: تنظیمات قیمت‌گذاری -->
        <div class="rpi-card">
            <div class="rpi-card__body rpi-settings-table">
                <?php do_settings_sections('rpi_settings_group'); // شناسه صفحه ما ?>
            </div>
        </div>
        
        <?php
            // دکمه ذخیره تنظیمات
            submit_button(__('ذخیره تنظیمات', RPI_TEXT_DOMAIN), 'rpi-button rpi-button--primary');
        ?>
    </form>
</div>