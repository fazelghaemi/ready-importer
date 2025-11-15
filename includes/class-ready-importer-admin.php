<?php
/**
 * کلاس مدیریت بخش ادمین افزونه
 *
 * *تغییرات این نسخه: (فاز ۲)*
 * - فراخوانی و بارگذاری کلاس Settings (برای صفحه تنظیمات).
 * - پیاده‌سازی متد display_plugin_settings_page.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/includes
 * @author     Ready Studio
 */
class Ready_Importer_Admin {

    // ... (متغیرهای $version و $plugin_slug از قبل وجود دارند) ...
    private $version;
    private $plugin_slug;

    /**
     * @var Ready_Importer_Settings آبجکت کلاس تنظیمات
     */
    private $settings;

    /**
     * سازنده کلاس.
     */
    public function __construct($version) {
        $this->version = $version;
        $this->plugin_slug = 'ready-importer';

        // --- جدید (فاز ۲) ---
        // کلاس تنظیمات را بارگذاری و مقداردهی اولیه کن
        require_once RPI_PLUGIN_PATH . 'includes/class-ready-importer-settings.php';
        $this->settings = new Ready_Importer_Settings();
    }
    
    /**
     * هوک: ثبت کلاس تنظیمات در وردپرس
     * (این متد جدید است و باید در Loader فراخوانی شود)
     */
    public function register_settings() {
        $this->settings->register_settings();
    }


    /**
     * هوک: اضافه کردن منوی افزونه به پیشخوان وردپرس.
     * (کد از مرحله قبل - بدون تغییر)
     */
    public function add_plugin_admin_menu() {
        
        // (کد آیکون SVG از مرحله قبل)
        $svg_icon_path = RPI_PLUGIN_PATH . 'assets/logo/readystudio-logo.svg';
        $svg_icon_data_uri = '';
        if (file_exists($svg_icon_path)) {
            $svg_content = file_get_contents($svg_icon_path);
            $svg_content = preg_replace('/<svg /', '<svg width="20" height="20" fill="currentColor" ', $svg_content, 1);
            $svg_icon_data_uri = 'data:image/svg+xml;base64,' . base64_encode($svg_content);
        } else {
            $svg_icon_data_uri = 'dashicons-download';
        }

        // اضافه کردن منوی اصلی
        add_menu_page(
            __('Ready Importer', RPI_TEXT_DOMAIN), 
            __('Ready Importer', RPI_TEXT_DOMAIN), 
            'manage_options',                    
            $this->plugin_slug,                  
            array($this, 'display_plugin_admin_page'), 
            $svg_icon_data_uri,                  
            58                                   
        );

        // زیرمنوی "درون‌ریزی جدید"
        add_submenu_page(
            $this->plugin_slug,                  
            __('درون‌ریزی جدید', RPI_TEXT_DOMAIN), 
            __('درون‌ریزی جدید', RPI_TEXT_DOMAIN), 
            'manage_options',
            $this->plugin_slug,                  
            array($this, 'display_plugin_admin_page')
        );

        // زیرمنوی "تنظیمات"
        add_submenu_page(
            $this->plugin_slug,
            __('تنظیمات', RPI_TEXT_DOMAIN),
            __('تنظیمات', RPI_TEXT_DOMAIN),
            'manage_options',
            $this->plugin_slug . '-settings',
            array($this, 'display_plugin_settings_page') // <-- این متد اکنون پیاده‌سازی می‌شود
        );
    }

    /**
     * هوک: بارگذاری استایل‌ها و اسکریپت‌ها.
     * (کد از مرحله قبل - بدون تغییر)
     */
    public function enqueue_styles_and_scripts($hook_suffix) {
        
        // فقط در صفحات افزونه‌ی ما بارگذاری شود
        if (strpos($hook_suffix, $this->plugin_slug) === false) {
            return;
        }

        // --- ۱. بارگذاری فونت ---
        $font_face_css = $this->get_font_face_css();
        wp_add_inline_style('wp-admin', $font_face_css); 

        // --- ۲. تزریق متغیرهای CSS (رنگ‌های سازمانی) ---
        $branding_css = $this->get_branding_css_variables();
        wp_add_inline_style('wp-admin', $branding_css);

        // --- ۳. بارگذاری فایل CSS اصلی ادمین ---
        wp_enqueue_style(
            RPI_TEXT_DOMAIN . '-admin-style', 
            RPI_PLUGIN_URL . 'admin/css/ready-importer-admin.css',
            array(), $this->version
        );

        // --- ۴. بارگذاری فایل JS اصلی ادمین ---
        wp_enqueue_script(
            RPI_TEXT_DOMAIN . '-admin-script', 
            RPI_PLUGIN_URL . 'admin/js/ready-importer-admin.js',
            array('jquery'), $this->version, true
        );
        
        // --- جدید (فاز ۲) ---
        // اگر در صفحه تنظیمات هستیم، اسکریپت مورد نیاز برای فیلدهای تکرارشونده را لود کن
        if ($hook_suffix === 'ready-importer_page_ready-importer-settings') {
             wp_enqueue_script(
                RPI_TEXT_DOMAIN . '-settings-script', 
                RPI_PLUGIN_URL . 'admin/js/ready-importer-settings.js', // فایل جدید
                array('jquery'), $this->version, true
            );
        }

        // ارسال متغیرهای PHP به جاوااسکریپت (کد از مرحله قبل)
        wp_localize_script(
            RPI_TEXT_DOMAIN . '-admin-script',
            'rpi_ajax_object', 
            array(
                'ajax_url' => admin_url('admin-ajax.php'), 
                'nonce'    => wp_create_nonce('rpi_importer_nonce'), 
                'text'     => array(
                    'loading' => __('در حال پردازش...', RPI_TEXT_DOMAIN),
                    'error'   => __('خطایی رخ داد. لطفاً دوباره تلاش کنید.', RPI_TEXT_DOMAIN),
                )
            )
        );
    }

    // ... (متدهای get_font_face_css, get_branding_css_variables, add_settings_link از مرحله قبل) ...
    private function get_font_face_css() { /* ... (کد از مرحله قبل) ... */ }
    private function get_branding_css_variables() { /* ... (کد از مرحله قبل) ... */ }
    public function add_settings_link($links) { /* ... (کد از مرحله قبل) ... */ }


    /**
     * متد Callback: رندر کردن صفحه اصلی افزونه (درون‌ریزی).
     * (کد از مرحله قبل - بدون تغییر)
     */
    public function display_plugin_admin_page() {
        require_once RPI_PLUGIN_PATH . 'admin/views/main-page.php';
    }

    /**
     * متد Callback: رندر کردن صفحه تنظیمات افزونه.
     * --- جدید (فاز ۲) ---
     */
    public function display_plugin_settings_page() {
        // ما منطق نمایش را در فایل view جداگانه‌ای نگه می‌داریم
        require_once RPI_PLUGIN_PATH . 'admin/views/settings-page.php';
    }
}