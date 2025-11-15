<?php
/**
 * فایل View صفحه تنظیمات (Settings Page)
 *
 * (این فایل دومین فایلی بود که خطا می‌داد)
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/admin/views
 * @author     Ready Studio
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

$plugin_version = defined('RPI_VERSION') ? RPI_VERSION : '1.0.0';
$logo_url = RPI_PLUGIN_URL . 'assets/logo/readystudio-logo.svg';

?>

<div class="wrap" id="rpi-admin-wrapper">

    <!-- ۱. هدر صفحه -->
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
    
    <!-- نمایش پیام ذخیره شدن تنظیمات وردپرس -->
    <?php settings_errors(); ?>

    <!-- ۲. فرم تنظیمات وردپرس -->
    <form method="post" action="options.php">
        <?php
            // رندر کردن فیلدهای امنیتی و نام گروه
            settings_fields('rpi_settings_group');
        ?>
        
        <?php
            // رندر کردن بخش‌ها و فیلدها در کارت‌های مجزا
            
            global $wp_settings_sections;
            $page_slug = 'rpi_settings_group';

            if (!isset($wp_settings_sections[$page_slug])) {
                echo '<div class="rpi-card"><div class="rpi-card__body">خطا: بخش‌های تنظیمات یافت نشدند.</div></div>';
                return;
            }
        ?>

        <!-- کارت اول: تنظیمات قیمت‌گذاری -->
        <div class="rpi-card">
            <div class="rpi-card__header">
                <h2 class="rpi-card__title"><?php _e('قوانین قیمت‌گذاری', RPI_TEXT_DOMAIN); ?></h2>
            </div>
            <div class="rpi-card__body rpi-settings-table">
                <table class="form-table">
                    <?php
                        if (isset($wp_settings_sections[$page_slug]['rpi_settings_section_pricing'])) {
                            call_user_func($wp_settings_sections[$page_slug]['rpi_settings_section_pricing']['callback']);
                            do_settings_fields($page_slug, 'rpi_settings_section_pricing');
                        }
                    ?>
                </table>
            </div>
        </div>
        
        <!-- کارت دوم: تنظیمات محتوا (سئو) -->
        <div class="rpi-card">
            <div class="rpi-card__header">
                <h2 class="rpi-card__title"><?php _e('قوانین محتوا (سئو)', RPI_TEXT_DOMAIN); ?></h2>
            </div>
            <div class="rpi-card__body rpi-settings-table">
                <table class="form-table">
                    <?php
                        if (isset($wp_settings_sections[$page_slug]['rpi_settings_section_content'])) {
                            call_user_func($wp_settings_sections[$page_slug]['rpi_settings_section_content']['callback']);
                            do_settings_fields($page_slug, 'rpi_settings_section_content');
                        }
                    ?>
                </table>
            </div>
        </div>

        <!-- کارت سوم: تنظیمات درون‌ریزی -->
        <div class="rpi-card">
            <div class="rpi-card__header">
                <h2 class="rpi-card__title"><?php _e('تنظیمات درون‌ریزی', RPI_TEXT_DOMAIN); ?></h2>
            </div>
            <div class="rpi-card__body rpi-settings-table">
                <table class="form-table">
                    <?php
                        if (isset($wp_settings_sections[$page_slug]['rpi_settings_section_import'])) {
                            call_user_func($wp_settings_sections[$page_slug]['rpi_settings_section_import']['callback']);
                            do_settings_fields($page_slug, 'rpi_settings_section_import');
                        }
                    ?>
                </table>
            </div>
        </div>
        
        <!-- کارت چهارم: تنظیمات پیشرفته (API) -->
        <div class="rpi-card">
            <div class="rpi-card__header">
                <h2 class="rpi-card__title"><?php _e('تنظیمات پیشرفته (API)', RPI_TEXT_DOMAIN); ?></h2>
            </div>
            <div class="rpi-card__body rpi-settings-table">
                <table class="form-table">
                    <?php
                        if (isset($wp_settings_sections[$page_slug]['rpi_settings_section_api'])) {
                            call_user_func($wp_settings_sections[$page_slug]['rpi_settings_section_api']['callback']);
                            do_settings_fields($page_slug, 'rpi_settings_section_api');
                        }
                    ?>
                </table>
            </div>
        </div>
        
        <?php
            // دکمه ذخیره تنظیمات
            submit_button(__('ذخیره تنظیمات', RPI_TEXT_DOMAIN), 'rpi-button rpi-button--primary');
        ?>
    </form>
</div>