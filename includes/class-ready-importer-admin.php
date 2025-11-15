<?php
/**
 * کلاس مدیریت بخش ادمین افزونه
 *
 * *تغییرات این نسخه: (رفع خطای Fatal Error)*
 * - اضافه شدن بررسی دفاعی (is_array) در متد add_settings_link
 * برای جلوگیری از خطای TypeError: count()
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/includes
 * @author     Ready Studio
 */
class Ready_Importer_Admin {

    private $version;
    private $plugin_slug;
    private $settings;

    /**
     * سازنده کلاس
     * (کد از مرحله قبل - بدون تغییر)
     */
    public function __construct($version) {
        $this->version = $version;
        $this->plugin_slug = 'ready-importer';

        // بارگذاری کلاس تنظیمات
        // ما این require را یکبار در Loader انجام دادیم، اما برای اطمینان اینجا هم قرار می‌دهیم
        if (!class_exists('Ready_Importer_Settings')) {
            require_once RPI_PLUGIN_PATH . 'includes/class-ready-importer-settings.php';
        }
        $this->settings = new Ready_Importer_Settings();
    }
    
    /**
     * هوک (admin_init): ثبت تنظیمات
     * (کد از مرحله قبل - بدون تغییر)
     */
    public function register_settings() {
        $this->settings->register_settings();
    }

    /**
     * هوک (admin_menu): اضافه کردن منوی افزونه
     * (کد از مرحله قبل - بدون تغییر)
     */
    public function add_plugin_admin_menu() {
        
        // (کد آیکون SVG)
        $svg_icon_path = RPI_PLUGIN_PATH . 'assets/logo/readystudio-logo.svg';
        $svg_icon_data_uri = '';
        if (file_exists($svg_icon_path)) {
            $svg_content = file_get_contents($svg_icon_path);
            $svg_content = preg_replace('/<svg /', '<svg width="20" height="20" fill="currentColor" ', $svg_content, 1);
            $svg_icon_data_uri = 'data:image/svg+xml;base64,' . base64_encode($svg_content);
        } else {
            $svg_icon_data_uri = 'dashicons-download';
        }

        // منوی اصلی
        add_menu_page(
            __('Ready Importer', RPI_TEXT_DOMAIN), 
            __('Ready Importer', RPI_TEXT_DOMAIN), 
            'manage_options', $this->plugin_slug, 
            array($this, 'display_plugin_admin_page'), 
            $svg_icon_data_uri, 58
        );

        // زیرمنوی "درون‌ریزی جدید"
        add_submenu_page(
            $this->plugin_slug,
            __('درون‌ریزی جدید', RPI_TEXT_DOMAIN), 
            __('درون‌ریزی جدید', RPI_TEXT_DOMAIN), 
            'manage_options', $this->plugin_slug,
            array($this, 'display_plugin_admin_page')
        );
        
        // زیرمنوی "مدیریت وظایف"
        add_submenu_page(
            $this->plugin_slug,
            __('مدیریت وظایف', RPI_TEXT_DOMAIN),
            __('مدیریت وظایف', RPI_TEXT_DOMAIN),
            'manage_options',
            'edit.php?post_type=rpi_import_task'
        );

        // زیرمنوی "تنظیمات"
        add_submenu_page(
            $this->plugin_slug,
            __('تنظیمات', RPI_TEXT_DOMAIN),
            __('تنظیمات', RPI_TEXT_DOMAIN),
            'manage_options',
            $this->plugin_slug . '-settings',
            array($this, 'display_plugin_settings_page')
        );
    }

    /**
     * هوک (admin_enqueue_scripts): بارگذاری استایل‌ها و اسکریپت‌ها
     * (کد از مرحله قبل - بدون تغییر)
     */
    public function enqueue_styles_and_scripts($hook_suffix) {
        
        $is_rpi_page = (strpos($hook_suffix, $this->plugin_slug) !== false) || 
                       ($hook_suffix === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'rpi_import_task') ||
                       ($hook_suffix === 'post.php' && isset($_GET['post']) && get_post_type($_GET['post']) === 'rpi_import_task'); // (پشتیبانی از صفحه ویرایش CPT)

        if (!$is_rpi_page) {
            return;
        }

        // ۱. فونت
        wp_add_inline_style('wp-admin', $this->get_font_face_css()); 
        // ۲. متغیرهای CSS
        wp_add_inline_style('wp-admin', $this->get_branding_css_variables());
        // ۳. CSS اصلی
        wp_enqueue_style(
            RPI_TEXT_DOMAIN . '-admin-style', 
            RPI_PLUGIN_URL . 'admin/css/ready-importer-admin.css',
            array(), $this->version
        );

        // ۴. JS اصلی ادمین (فقط در صفحه درون‌ریزی)
        if (strpos($hook_suffix, 'page_ready-importer') !== false && $hook_suffix !== 'ready-importer_page_ready-importer-settings') {
            wp_enqueue_script(
                RPI_TEXT_DOMAIN . '-admin-script', 
                RPI_PLUGIN_URL . 'admin/js/ready-importer-admin.js',
                array('jquery'), $this->version, true
            );
            wp_localize_script(
                RPI_TEXT_DOMAIN . '-admin-script', 'rpi_ajax_object', 
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
        
        // ۵. JS تنظیمات (فقط در صفحه تنظیمات)
        if ($hook_suffix === 'ready-importer_page_ready-importer-settings') {
             wp_enqueue_script(
                RPI_TEXT_DOMAIN . '-settings-script', 
                RPI_PLUGIN_URL . 'admin/js/ready-importer-settings.js',
                array('jquery'), $this->version, true
            );
        }
    }

    // --- (متدهای کمکی CSS - بدون تغییر) ---
    private function get_font_face_css() {
        $font_url = RPI_PLUGIN_URL . 'assets/font/readyfont.woff';
        return "@font-face {font-family: 'ReadyFont'; src: url('{$font_url}') format('woff'); font-weight: normal; font-style: normal; font-display: swap;}";
    }
    private function get_branding_css_variables() {
        $colors = array(
            '--rpi-color-midnight'  => '#010101', '--rpi-color-aqua' => '#01ada1', '--rpi-color-aqua-hover' => '#009086',
            '--rpi-color-text-primary' => '#1E1E1E', '--rpi-color-text-secondary' => '#6B7280', '--rpi-color-bg-light' => '#F9FAFB',
            '--rpi-color-bg-white' => '#FFFFFF', '--rpi-color-border' => '#E5E7EB', '--rpi-radius-md' => '8px',
            '--rpi-radius-lg' => '14px', '--rpi-shadow-md' => '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);'
        );
        $css_vars = ':root {';
        foreach ($colors as $key => $value) { $css_vars .= "    {$key}: {$value};" . PHP_EOL; }
        $css_vars .= '}';
        return $css_vars;
    }


    /**
     * هوک (plugin_action_links): اضافه کردن لینک "تنظیمات"
     * --- آپدیت (رفع خطا) ---
     */
    public function add_settings_link($links) {
        
        // --- (جدید) رفع خطای Fatal Error در plugins.php ---
        // این یک اقدام دفاعی است. اگر افزونه دیگری $links را به null
        // تغییر داده باشد، ما آن را به یک آرایه خالی تبدیل می‌کنیم
        // تا از خطای TypeError: count(): Argument #1... جلوگیری کنیم.
        if (!is_array($links)) {
            $links = array();
        }

        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=' . $this->plugin_slug . '-settings')),
            __('تنظیمات', RPI_TEXT_DOMAIN)
        );
        
        // اضافه کردن لینک جدید به ابتدای آرایه
        array_unshift($links, $settings_link);
        
        return $links;
    }

    /**
     * متد Callback: رندر کردن صفحه اصلی افزونه (درون‌ریزی).
     * (کد از مرحله قبل - بدون تغییر)
     */
    public function display_plugin_admin_page() {
        // این فایل باید در admin/views/ وجود داشته باشد
        require_once RPI_PLUGIN_PATH . 'admin/views/main-page.php';
    }

    /**
     * متد Callback: رندر کردن صفحه تنظیمات افزونه.
     * (کد از مرحله قبل - بدون تغییر)
     */
    public function display_plugin_settings_page() {
        // این فایل باید در admin/views/ وجود داشته باشد
        require_once RPI_PLUGIN_PATH . 'admin/views/settings-page.php';
    }
}