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
 * Version:           1.1.0
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
if (!defined('ABSPATH')) {
    exit; // خروج امن
}

// -----------------------------------------------------------------------------
// ۲. (جدید) بررسی نیازمندی‌های پایه (نسخه PHP)
// -----------------------------------------------------------------------------
if (!defined('RPI_MIN_PHP_VERSION')) {
    define('RPI_MIN_PHP_VERSION', '7.4');
}

if (version_compare(PHP_VERSION, RPI_MIN_PHP_VERSION, '<')) {
    // یک تابع برای نمایش خطا در ادمین ثبت کن
    add_action('admin_notices', function() {
        $message = sprintf(
            // translators: 1. Current PHP version, 2. Required PHP version
            __('افزونه Ready Importer برای اجرا به نسخه PHP %2$s یا بالاتر نیاز دارد. شما در حال حاضر از نسخه %1$s استفاده می‌کنید. لطفاً نسخه PHP خود را ارتقا دهید.', 'ready-importer'),
            PHP_VERSION,
            RPI_MIN_PHP_VERSION
        );
        printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
    });
    // اجرای ادامه افزونه را متوقف کن
    return;
}


// -----------------------------------------------------------------------------
// ۳. تعریف ثابت‌های اصلی افزونه (Plugin Constants)
// -----------------------------------------------------------------------------

if (!defined('RPI_VERSION')) {
    define('RPI_VERSION', '1.1.0');
}
if (!defined('RPI_PLUGIN_FILE')) {
    define('RPI_PLUGIN_FILE', __FILE__);
}
if (!defined('RPI_PLUGIN_PATH')) {
    define('RPI_PLUGIN_PATH', plugin_dir_path(RPI_PLUGIN_FILE));
}
if (!defined('RPI_PLUGIN_URL')) {
    define('RPI_PLUGIN_URL', plugin_dir_url(RPI_PLUGIN_FILE));
}
if (!defined('RPI_PLUGIN_BASENAME')) {
    define('RPI_PLUGIN_BASENAME', plugin_basename(RPI_PLUGIN_FILE));
}
if (!defined('RPI_TEXT_DOMAIN')) {
    define('RPI_TEXT_DOMAIN', 'ready-importer');
}


// -----------------------------------------------------------------------------
// ۴. بارگذار خودکار کلاس‌ها (Autoloader)
// -----------------------------------------------------------------------------
// (کد از مرحله قبل - بدون تغییر)
spl_autoload_register(function ($class_name) {
    $prefix = 'Ready_Importer_';
    if (strpos($class_name, $prefix) !== 0) {
        return;
    }
    $base_dir = RPI_PLUGIN_PATH . 'includes/';
    $class_without_prefix = substr($class_name, strlen($prefix));
    $class_file_base = str_replace('_', '-', strtolower($class_without_prefix));
    $file = $base_dir . 'class-ready-importer-' . $class_file_base . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});


// -----------------------------------------------------------------------------
// ۵. هوک‌های فعال‌سازی و غیرفعال‌سازی (Plugin Hooks)
// -----------------------------------------------------------------------------

/**
 * تابع فعال‌سازی افزونه.
 */
function rpi_activate_plugin() {
    // بررسی نیازمندی ووکامرس
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(RPI_PLUGIN_BASENAME);
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
    
    // فراخوانی کلاس فعال‌ساز
    require_once RPI_PLUGIN_PATH . 'includes/class-ready-importer-activator.php';
    Ready_Importer_Activator::activate();
}

/**
 * تابع غیرفعال‌سازی افزونه.
 */
function rpi_deactivate_plugin() {
    require_once RPI_PLUGIN_PATH . 'includes/class-ready-importer-deactivator.php';
    Ready_Importer_Deactivator::deactivate();
}

register_activation_hook(RPI_PLUGIN_FILE, 'rpi_activate_plugin');
register_deactivation_hook(RPI_PLUGIN_FILE, 'rpi_deactivate_plugin');


// -----------------------------------------------------------------------------
// ۶. اجرای هسته اصلی افزونه (Run The Plugin)
// -----------------------------------------------------------------------------

/**
 * تابع اصلی برای راه‌اندازی افزونه.
 */
function rpi_run_ready_importer() {
    // بارگذاری کلاس اصلی Loader
    require_once RPI_PLUGIN_PATH . 'includes/class-ready-importer-loader.php';
    
    // ایجاد یک نمونه از کلاس لودر و اجرای آن
    $plugin_loader = new Ready_Importer_Loader();
    $plugin_loader->run();
}

// اجرای افزونه پس از بارگذاری کامل وردپرس و سایر افزونه‌ها
add_action('plugins_loaded', 'rpi_run_ready_importer');