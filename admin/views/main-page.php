<?php
/**
 * فایل View اصلی صفحه ادمین Ready Importer
 * (صفحه "درون‌ریزی جدید")
 *
 * این فایل توسط کلاس Ready_Importer_Admin رندر می‌شود.
 * مسئولیت آن نمایش ساختار کلی صفحه، هدر، و مدیریت
 * جابجایی بین فایل‌های View مربوط به هر مرحله (Step) است.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/admin/views
 * @author     Ready Studio
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// دریافت نسخه افزونه (برای نمایش در هدر)
$plugin_version = defined('RPI_VERSION') ? RPI_VERSION : '1.0.0';

// دریافت آدرس لوگو
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
                <h1 class="rpi-header__title"><?php _e('Ready Importer', RPI_TEXT_DOMAIN); ?></h1>
                <p class="rpi-header__subtitle"><?php _e('درون‌ریزی هوشمند محصولات از دیجی‌کالا', RPI_TEXT_DOMAIN); ?></p>
            </div>
        </div>
        <div class="rpi-header__version">
            <span><?php printf(__('نسخه %s', RPI_TEXT_DOMAIN), esc_html($plugin_version)); ?></span>
        </div>
    </header>

    <!-- ۲. بدنه اصلی صفحه -->
    <main class="rpi-main-content">
        
        <!-- فرآیند گام به گام (Stepper) -->
        <div class="rpi-stepper">
            <div class="rpi-stepper__item rpi-stepper__item--active">
                <div class="rpi-stepper__number">1</div>
                <div class="rpi-stepper__label"><?php _e('لینک محصولات', RPI_TEXT_DOMAIN); ?></div>
            </div>
            <div class="rpi-stepper__item">
                <div class="rpi-stepper__number">2</div>
                <div class="rpi-stepper__label"><?php _e('تنظیمات درون‌ریزی', RPI_TEXT_DOMAIN); ?></div>
            </div>
        </div>

        <!-- 
            فرم واحد که بین مراحل جابجا می‌شود
        -->
        <form id="rpi-importer-form" method="POST" action="">
            <?php
                // توکن امنیتی وردپرس
                wp_nonce_field('rpi_importer_action', 'rpi_importer_nonce');
            ?>
            
            <!-- فیلد مخفی برای ذخیره لینک‌های *خام* وارد شده توسط کاربر -->
            <input type="hidden" id="rpi-links-storage" name="rpi_links_storage">

            <!-- محتوای مرحله ۱: دریافت لینک‌ها -->
            <div id="rpi-step-1-content">
                <?php
                    // بارگذاری فایل مرحله ۱
                    if (file_exists(RPI_PLUGIN_PATH . 'admin/views/step-1-links.php')) {
                        require_once RPI_PLUGIN_PATH . 'admin/views/step-1-links.php';
                    } else {
                        echo '<div class="rpi-card"><div class="rpi-card__body"><b>خطای Fatal:</b> فایل <code>admin/views/step-1-links.php</code> یافت نشد.</div></div>';
                    }
                ?>
            </div>

            <!-- محتوای مرحله ۲: تنظیمات دسته‌بندی -->
            <div id="rpi-step-2-content" style="display:none;">
                 <?php
                    // بارگذاری فایل مرحله ۲
                    if (file_exists(RPI_PLUGIN_PATH . 'admin/views/step-2-settings.php')) {
                        require_once RPI_PLUGIN_PATH . 'admin/views/step-2-settings.php';
                    } else {
                        echo '<div class="rpi-card"><div class="rpi-card__body"><b>خطای Fatal:</b> فایل <code>admin/views/step-2-settings.php</code> یافت نشد.</div></div>';
                    }
                ?>
            </div>

            <!-- دکمه‌های ناوبری (توسط JS مدیریت می‌شوند) -->
            <div class="rpi-navigation-buttons" style="display: flex; justify-content: space-between; margin-top: 24px;">
                
                <!-- دکمه بازگشت -->
                <button type="button" id="rpi-back-button"
                   class="rpi-button rpi-button--default"
                   style="display: none;">
                   <?php _e(' بازگشت (مرحله قبل)', RPI_TEXT_DOMAIN); ?>
                </button>

                <!-- دکمه ادامه (در step-1-links.php قرار دارد) -->
                
                <!-- دکمه شروع درون‌ریزی -->
                 <button type="button" id="rpi-start-import-button"
                        class="rpi-button rpi-button--primary"
                        style="margin-right: auto; display: none;">
                    <?php _e('شروع درون‌ریزی نهایی', RPI_TEXT_DOMAIN); ?>
                </button>
            </div>

        </form>

    </main>

</div>