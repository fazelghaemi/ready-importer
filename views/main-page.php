<?php
/**
 * فایل View اصلی صفحه ادمین Ready Importer
 *
 * این فایل توسط کلاس Ready_Importer_Admin رندر می‌شود.
 * مسئولیت آن نمایش ساختار کلی صفحه، هدر، و فراخوانی
 * فایل‌های View مربوط به هر مرحله (Step) است.
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
// این متغیر در کلاس Admin در دسترس است، اما اینجا برای اطمینان مجدد تعریف می‌کنیم.
$plugin_version = defined('RPI_VERSION') ? RPI_VERSION : '1.0.0';

// دریافت آدرس لوگو
$logo_url = RPI_PLUGIN_URL . 'assets/logo/readystudio-logo.svg';

// تعیین مرحله فعلی (Step)
// ما از یک query string ساده مثل ?step=2 استفاده خواهیم کرد.
$current_step = isset($_GET['step']) ? intval($_GET['step']) : 1;
if ($current_step !== 1 && $current_step !== 2) {
    $current_step = 1;
}

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
            <div class="rpi-stepper__item <?php echo ($current_step == 1) ? 'rpi-stepper__item--active' : ''; ?>">
                <div class="rpi-stepper__number">1</div>
                <div class="rpi-stepper__label"><?php _e('لینک محصولات', RPI_TEXT_DOMAIN); ?></div>
            </div>
            <div class="rpi-stepper__item <?php echo ($current_step == 2) ? 'rpi-stepper__item--active' : ''; ?>">
                <div class="rpi-stepper__number">2</div>
                <div class="rpi-stepper__label"><?php _e('تنظیمات درون‌ریزی', RPI_TEXT_DOMAIN); ?></div>
            </div>
            <!-- گام سوم (پردازش) را می‌توان بعداً اضافه کرد -->
        </div>

        <!-- 
            بارگذاری فایل View مربوط به هر مرحله
            ما از یک فرم واحد استفاده می‌کنیم که بین مراحل جابجا می‌شود
        -->
        <form id="rpi-importer-form" method="POST" action="">
            <?php
                // توکن امنیتی وردپرس برای فرم
                wp_nonce_field('rpi_importer_action', 'rpi_importer_nonce');
            ?>

            <!-- محتوای مرحله ۱: دریافت لینک‌ها -->
            <div id="rpi-step-1-content" <?php echo ($current_step == 1) ? '' : 'style="display:none;"'; ?>>
                <?php require_once RPI_PLUGIN_PATH . 'admin/views/step-1-links.php'; ?>
            </div>

            <!-- محتوای مرحله ۲: تنظیمات دسته‌بندی -->
            <div id="rpi-step-2-content" <?php echo ($current_step == 2) ? '' : 'style="display:none;"'; ?>>
                <?php require_once RPI_PLUGIN_PATH . 'admin/views/step-2-settings.php'; ?>
            </div>

            <!-- دکمه‌های ناوبری -->
            <div class="rpi-navigation-buttons" style="display: flex; justify-content: space-between; margin-top: 24px;">
                
                <!-- دکمه بازگشت (فقط در مرحله ۲ نمایش داده می‌شود) -->
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_slug . '&step=1')); ?>"
                   class="rpi-button rpi-button--default <?php echo ($current_step == 2) ? '' : 'hidden'; ?>">
                   <?php _e(' بازگشت (مرحله قبل)', RPI_TEXT_DOMAIN); ?>
                </a>

                <!-- دکمه ادامه (فقط در مرحله ۱ نمایش داده می‌شود) -->
                <!-- این دکمه در فایل step-1-links.php قرار داده شده تا بخشی از فرم باشد -->
                
                <!-- دکمه شروع درون‌ریزی (فقط در مرحله ۲ نمایش داده می‌شود) -->
                 <button id="rpi-start-import-button"
                        class="rpi-button rpi-button--primary <?php echo ($current_step == 2) ? '' : 'hidden'; ?>"
                        style="margin-right: auto; /* دکمه را به سمت راست (در RTL) می‌برد */">
                    <?php _e('شروع درون‌ریزی نهایی', RPI_TEXT_DOMAIN); ?>
                </button>
            </div>

        </form>

    </main>

</div>