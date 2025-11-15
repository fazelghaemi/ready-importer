<?php
/**
 * Ready Importer
 *
 * @package           Ready_Importer
 * @author            Ready Studio
 * @copyright         2025 Ready Studio
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Ready Importer
 * Plugin URI:        https://ready-studio.com/plugins/ready-importer
 * Description:       افزونه‌ای حرفه‌ای و زیبا از ردی استودیو برای اسکرپ و درون‌ریزی محصولات از دیجی‌کالا به ووکامرس.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Ready Studio
 * Author URI:        https://ready-studio.com
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       ready-importer
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 */

// -----------------------------------------------------------------------------
// ۱. بررسی امنیتی: جلوگیری از دسترسی مستقیم
// -----------------------------------------------------------------------------
// اگر وردپرس بارگذاری نشده باشد و کسی بخواهد مستقیماً این فایل را باز کند، اجرا متوقف می‌شود.
if (!defined('ABSPATH')) {
    exit; // خروج امن
}

// -----------------------------------------------------------------------------
// ۲. تعریف ثابت‌های اصلی افزونه (Plugin Constants)
// -----------------------------------------------------------------------------
// این ثابت‌ها به ما کمک می‌کنند تا به راحتی به فایل‌ها و آدرس‌های افزونه دسترسی داشته باشیم.

/**
 * نسخه فعلی افزونه.
 * @var string
 */
if (!defined('RPI_VERSION')) {
    define('RPI_VERSION', '1.0.0');
}

/**
 * فایل اصلی افزونه.
 * @var string
 */
if (!defined('RPI_PLUGIN_FILE')) {
    define('RPI_PLUGIN_FILE', __FILE__);
}

/**
 * مسیر کامل پوشه افزونه در سرور (با / در انتها).
 * @var string
 */
if (!defined('RPI_PLUGIN_PATH')) {
    define('RPI_PLUGIN_PATH', plugin_dir_path(RPI_PLUGIN_FILE));
}

/**
 * آدرس URL پوشه افزونه در وب (با / در انتها).
 * @var string
 */
if (!defined('RPI_PLUGIN_URL')) {
    define('RPI_PLUGIN_URL', plugin_dir_url(RPI_PLUGIN_FILE));
}

/**
 * نام فایل اصلی افزونه به همراه پوشه (مثال: ready-importer/ready-importer.php).
 * @var string
 */
if (!defined('RPI_PLUGIN_BASENAME')) {
    define('RPI_PLUGIN_BASENAME', plugin_basename(RPI_PLUGIN_FILE));
}

/**
 * شناسه (Text Domain) افزونه برای ترجمه.
 * @var string
 */
if (!defined('RPI_TEXT_DOMAIN')) {
    define('RPI_TEXT_DOMAIN', 'ready-importer');
}


// -----------------------------------------------------------------------------
// ۳. بارگذار خودکار کلاس‌ها (Autoloader)
// -----------------------------------------------------------------------------
// این بخش حیاتی و حرفه‌ای، ما را از نوشتن require_once برای هر کلاس بی‌نیاز می‌کند.
// این تابع به صورت خودکار کلاس‌هایی که با 'Ready_Importer_' شروع می‌شوند را
// از پوشه 'includes/' بارگذاری می‌کند.
// مثال:
// فراخوانی کلاس Ready_Importer_Admin -> فایل class-ready-importer-admin.php را بارگذاری می‌کند.

spl_autoload_register(function ($class_name) {
    // فقط کلاس‌های این افزونه را بارگذاری کن (باید پیشوند مشخص داشته باشند)
    $prefix = 'Ready_Importer_';
    if (strpos($class_name, $prefix) !== 0) {
        return;
    }

    // مسیر پایه پوشه کلاس‌ها
    $base_dir = RPI_PLUGIN_PATH . 'includes/';

    // تبدیل نام کلاس به نام فایل بر اساس استاندارد وردپرس
    // ۱. حذف پیشوند 'Ready_Importer_'
    // ۲. تبدیل حروف بزرگ به کوچک
    // ۳. تبدیل '_' به '-'
    // ۴. اضافه کردن 'class-' به ابتدا و '.php' به انتها
    
    // مثال: Ready_Importer_Admin_Page
    // می‌شود: class-ready-importer-admin-page.php
    
    // جدا کردن نام کلاس از پیشوند
    $class_without_prefix = substr($class_name, strlen($prefix));

    // تبدیل به حروف کوچک و جایگزینی آندرلاین با خط تیره
    $class_file_base = str_replace(
        '_', // تبدیل آندرلاین
        '-', // به خط تیره
        strtolower($class_without_prefix) // تبدیل به حروف کوچک
    );

    // فایل کامل به همراه پسوند
    $file = $base_dir . 'class-ready-importer-' . $class_file_base . '.php';

    // اگر فایل وجود داشت، آن را بارگذاری کن
    if (file_exists($file)) {
        require_once $file;
    }
});


// -----------------------------------------------------------------------------
// ۴. هوک‌های فعال‌سازی و غیرفعال‌سازی (Plugin Hooks)
// -----------------------------------------------------------------------------

/**
 * تابع فعال‌سازی افزونه.
 * این تابع زمانی اجرا می‌شود که کاربر روی دکمه "فعال کردن" کلیک می‌کند.
 * ما مسئولیت اصلی را به کلاس اختصاصی Activator می‌سپاریم.
 */
function rpi_activate_plugin() {
    // بررسی نیازمندی‌ها (Dependency Check)
    // این افزونه بدون ووکامرس کار نمی‌کند.
    if (!class_exists('WooCommerce')) {
        // افزونه را غیرفعال کن
        deactivate_plugins(RPI_PLUGIN_BASENAME);
        
        // یک پیام خطا نمایش بده
        wp_die(
            sprintf(
                __('افزونه "Ready Importer" برای کار کردن نیازمند نصب و فعال بودن افزونه "ووکامرس" است. لطفاً ابتدا ووکامرس را نصب و فعال کنید. %s بازگشت %s', RPI_TEXT_DOMAIN),
                '<a href="' . esc_url(admin_url('plugins.php')) . '">',
                '</a>'
            ),
            __('خطای فعال‌سازی افزونه', RPI_TEXT_DOMAIN),
            ['back_link' => true]
        );
    }
    
    // فراخوانی کلاس فعال‌ساز (که توسط Autoloader بارگذاری می‌شود)
    // این کلاس می‌تواند کارهایی مثل ساخت جداول دیتابیس یا تنظیم گزینه‌های پیش‌فرض را انجام دهد
    require_once RPI_PLUGIN_PATH . 'includes/class-ready-importer-activator.php';
    Ready_Importer_Activator::activate();
}

/**
 * تابع غیرفعال‌سازی افزونه.
 * این تابع زمانی اجرا می‌شود که کاربر روی دکمه "غیرفعال کردن" کلیک می‌کند.
 * مسئولیت اصلی به کلاس Deactivator سپرده می‌شود.
 */
function rpi_deactivate_plugin() {
    // فراخوانی کلاس غیرفعال‌ساز (که توسط Autoloader بارگذاری می‌شود)
    // این کلاس می‌تواند کارهایی مثل پاک کردن Cron Job ها را انجام دهد
    require_once RPI_PLUGIN_PATH . 'includes/class-ready-importer-deactivator.php';
    Ready_Importer_Deactivator::deactivate();
}

// ثبت هوک‌ها در وردپرس
register_activation_hook(RPI_PLUGIN_FILE, 'rpi_activate_plugin');
register_deactivation_hook(RPI_PLUGIN_FILE, 'rpi_deactivate_plugin');


// -----------------------------------------------------------------------------
// ۵. اجرای هسته اصلی افزونه (Run The Plugin)
// -----------------------------------------------------------------------------

/**
 * تابع اصلی برای راه‌اندازی افزونه.
 * این تابع کلاس اصلی 'Ready_Importer_Loader' را فراخوانی می‌کند.
 * این کلاس مسئولیت تعریف تمام هوک‌ها، فیلترها، و بارگذاری ماژول ادمین را بر عهده دارد.
 */
function rpi_run_ready_importer() {
    // بارگذاری دستی فایل Loader (چون Autoloader ممکن است هنوز کلاس اصلی را نشناسد)
    // اگرچه Autoloader تعریف شده، اما برای اجرای اولیه، بارگذاری دستی کلاس Loader امن‌تر است.
    require_once RPI_PLUGIN_PATH . 'includes/class-ready-importer-loader.php';
    
    // ایجاد یک نمونه از کلاس لودر
    $plugin_loader = new Ready_Importer_Loader();
    
    // اجرای متد run (که تمام هوک‌ها را ثبت می‌کند)
    $plugin_loader->run();
}

// ما افزونه را مستقیماً اجرا نمی‌کنیم، بلکه آن را به هوک 'plugins_loaded' وردپرس متصل می‌کنیم.
// این تضمین می‌کند که افزونه ما *بعد* از بارگذاری تمام افزونه‌های دیگر (مخصوصا ووکامرس) اجرا شود.
add_action('plugins_loaded', 'rpi_run_ready_importer');