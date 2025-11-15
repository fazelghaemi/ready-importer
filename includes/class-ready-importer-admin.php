<?php

/**
 * کلاس مدیریت بخش ادمین افزونه
 *
 * این کلاس تمام فعالیت‌های مربوط به پیشخوان وردپرس را مدیریت می‌کند.
 * شامل ساخت منو، بارگذاری اسکریپت‌ها و استایل‌ها، و رندر کردن صفحات افزونه.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/includes
 * @author     Ready Studio
 */
class Ready_Importer_Admin {

    /**
     * نسخه افزونه.
     *
     * @access private
     * @var string $version نسخه فعلی افزونه.
     */
    private $version;

    /**
     * شناسه (slug) صفحه اصلی افزونه.
     *
     * @access private
     * @var string $plugin_slug شناسه منحصربفرد صفحه منو.
     */
    private $plugin_slug;

    /**
     * سازنده کلاس.
     *
     * @param string $version نسخه فعلی افزونه.
     */
    public function __construct($version) {
        $this->version = $version;
        $this->plugin_slug = 'ready-importer';
    }

    /**
     * هوک: اضافه کردن منوی افزونه به پیشخوان وردپرس.
     *
     * این متد توسط Ready_Importer_Loader فراخوانی می‌شود.
     */
    public function add_plugin_admin_menu() {
        
        // آیکون منو (لوگوی SVG ردی استودیو)
        // ما از یک data URI برای SVG استفاده می‌کنیم تا به فایل فیزیکی وابسته نباشیم
        // و رنگ آن را با CSS (fill='currentColor') مدیریت کنیم.
        $svg_icon_path = RPI_PLUGIN_PATH . 'assets/logo/readystudio-logo.svg';
        $svg_icon_data_uri = '';

        if (file_exists($svg_icon_path)) {
            $svg_content = file_get_contents($svg_icon_path);
            // بهینه‌سازی SVG برای منوی وردپرس (تنظیم رنگ و اندازه)
            $svg_content = preg_replace(
                '/<svg /',
                '<svg width="20" height="20" fill="currentColor" ',
                $svg_content,
                1
            );
            $svg_icon_data_uri = 'data:image/svg+xml;base64,' . base64_encode($svg_content);
        } else {
            // فال‌بک در صورت نبودن فایل
            $svg_icon_data_uri = 'dashicons-download';
        }


        // اضافه کردن منوی اصلی (Top-level Menu)
        add_menu_page(
            __('Ready Importer', RPI_TEXT_DOMAIN), // عنوان صفحه (Title tag)
            __('Ready Importer', RPI_TEXT_DOMAIN), // عنوان منو (Menu title)
            'manage_options',                    // سطح دسترسی مورد نیاز
            $this->plugin_slug,                  // شناسه (Slug) منو
            array($this, 'display_plugin_admin_page'), // متد callback برای رندر صفحه
            $svg_icon_data_uri,                  // آیکون SVG
            58                                   // موقعیت در منو (نزدیک ووکامرس)
        );

        // (اختیاری) اضافه کردن زیرمنو (Submenu)
        // مثال: زیرمنوی "درون‌ریزی جدید" که همان صفحه اصلی است
        add_submenu_page(
            $this->plugin_slug,                  // شناسه والد
            __('درون‌ریزی جدید', RPI_TEXT_DOMAIN), // عنوان صفحه
            __('درون‌ریزی جدید', RPI_TEXT_DOMAIN), // عنوان منو
            'manage_options',
            $this->plugin_slug,                  // شناسه (باید مشابه والد باشد تا تکراری نشود)
            array($this, 'display_plugin_admin_page')
        );

        // مثال: زیرمنوی "تنظیمات"
        add_submenu_page(
            $this->plugin_slug,
            __('تنظیمات', RPI_TEXT_DOMAIN),
            __('تنظیمات', RPI_TEXT_DOMAIN),
            'manage_options',
            $this->plugin_slug . '-settings',
            array($this, 'display_plugin_settings_page') // یک متد جداگانه برای صفحه تنظیمات
        );
    }

    /**
     * هوک: بارگذاری استایل‌ها و اسکریپت‌های مورد نیاز در ادمین.
     *
     * @param string $hook_suffix شناسه صفحه فعلی ادمین.
     */
    public function enqueue_styles_and_scripts($hook_suffix) {
        
        // ما فقط در صفحه افزونه خودمان (و زیرمنوهایش)
        // این فایل‌ها را بارگذاری می‌کنیم تا با افزونه‌های دیگر تداخل نکند.
        
        // شناسه‌های صفحات ما: 'toplevel_page_ready-importer' و 'ready-importer_page_ready-importer-settings'
        if (strpos($hook_suffix, $this->plugin_slug) === false) {
            return;
        }

        // --- ۱. بارگذاری فونت ---
        // ما فونت را به صورت یک استایل inline تعریف می‌کنیم
        $font_face_css = $this->get_font_face_css();
        wp_add_inline_style('wp-admin', $font_face_css); // به یک هندل موجود اضافه می‌کنیم

        // --- ۲. تزریق متغیرهای CSS (رنگ‌های سازمانی) ---
        $branding_css = $this->get_branding_css_variables();
        wp_add_inline_style('wp-admin', $branding_css);

        // --- ۳. بارگذاری فایل CSS اصلی ادمین ---
        wp_enqueue_style(
            RPI_TEXT_DOMAIN . '-admin-style', // نام یکتا
            RPI_PLUGIN_URL . 'admin/css/ready-importer-admin.css', // آدرس فایل
            array(), // وابستگی‌ها (ندارد)
            $this->version // نسخه (برای کش‌بندی)
        );

        // --- ۴. بارگذاری فایل JS اصلی ادمین ---
        wp_enqueue_script(
            RPI_TEXT_DOMAIN . '-admin-script', // نام یکتا
            RPI_PLUGIN_URL . 'admin/js/ready-importer-admin.js', // آدرس فایل
            array('jquery'), // وابستگی‌ها (به جی‌کوئری وردپرس)
            $this->version, // نسخه
            true // در فوتر بارگذاری شود
        );

        // (اختیاری) ارسال متغیرهای PHP به جاوااسکریپت
        wp_localize_script(
            RPI_TEXT_DOMAIN . '-admin-script',
            'rpi_ajax_object', // نام آبجکت در جاوااسکریپت
            array(
                'ajax_url' => admin_url('admin-ajax.php'), // آدرس ایجکس
                'nonce'    => wp_create_nonce('rpi_importer_nonce'), // توکن امنیتی
                'text'     => array(
                    'loading' => __('در حال پردازش...', RPI_TEXT_DOMAIN),
                    'error'   => __('خطایی رخ داد. لطفاً دوباره تلاش کنید.', RPI_TEXT_DOMAIN),
                )
            )
        );
    }

    /**
     * متد کمکی برای تولید CSS @font-face
     *
     * @return string رشته CSS
     */
    private function get_font_face_css() {
        $font_url = RPI_PLUGIN_URL . 'assets/font/readyfont.woff';
        return "
            @font-face {
                font-family: 'ReadyFont';
                src: url('{$font_url}') format('woff');
                font-weight: normal;
                font-style: normal;
                font-display: swap;
            }
        ";
    }

    /**
     * متد کمکی برای تولید متغیرهای CSS برندینگ
     *
     * @return string رشته CSS
     */
    private function get_branding_css_variables() {
        // هویت بصری ردی استودیو
        $colors = array(
            '--rpi-color-midnight'  => '#010101', // مشکی میدنایت
            '--rpi-color-aqua'      => '#01ada1', // سبز Aqua Mint
            '--rpi-color-aqua-hover' => '#009086', // سبز هاور
            '--rpi-color-text-primary' => '#1E1E1E', // متن اصلی
            '--rpi-color-text-secondary' => '#6B7280', // متن ثانویه (خاکستری)
            '--rpi-color-bg-light'    => '#F9FAFB', // پس‌زمینه خیلی روشن (سفید دودی)
            '--rpi-color-bg-white'    => '#FFFFFF', // سفید
            '--rpi-color-border'      => '#E5E7EB', // رنگ بوردر
            '--rpi-radius-md'         => '8px',    // دورگرد متوسط (مثل فیلدها)
            '--rpi-radius-lg'         => '14px',   // دورگرد بزرگ (مثل کارت‌ها)
            '--rpi-shadow-md'         => '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);' // سایه نرم
        );

        // تبدیل آرایه به رشته :root
        $css_vars = ':root {';
        foreach ($colors as $key => $value) {
            $css_vars .= "    {$key}: {$value};" . PHP_EOL;
        }
        $css_vars .= '}';
        
        return $css_vars;
    }


    /**
     * هوک: اضافه کردن لینک "تنظیمات" در صفحه افزونه‌ها.
     *
     * @param array $links آرایه‌ای از لینک‌های موجود.
     * @return array آرایه به‌روز شده لینک‌ها.
     */
    public function add_settings_link($links) {
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
     */
    public function display_plugin_admin_page() {
        // ما منطق نمایش را در فایل view جداگانه‌ای نگه می‌داریم
        // تا کدنویسی تمیز باشد.
        require_once RPI_PLUGIN_PATH . 'admin/views/main-page.php';
    }

    /**
     * متد Callback: رندر کردن صفحه تنظیمات افزونه.
     */
    public function display_plugin_settings_page() {
        // (فعلاً برای نمونه)
        echo '<div class="wrap"><h1>' . __('تنظیمات Ready Importer', RPI_TEXT_DOMAIN) . '</h1></div>';
    }
}