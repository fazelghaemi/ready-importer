<?php
/**
 * کلاس مدیریت درخواست‌های AJAX افزونه
 *
 * این کلاس مسئولیت مدیریت تمام درخواست‌های AJAX ورودی را بر عهده دارد.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/includes
 * @author     Ready Studio
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class Ready_Importer_Ajax {

    /**
     * آبجکتی از کلاس Scraper.
     * @var Ready_Importer_Scraper
     */
    private $scraper;

    /**
     * آبجکتی از کلاس Importer.
     * @var Ready_Importer_Importer
     */
    private $importer;

    /**
     * سازنده کلاس.
     * ماژول‌های مورد نیاز (Scraper و Importer) را مقداردهی می‌کند.
     */
    public function __construct() {
        // ما کلاس‌های اسکرپر و ایمپورتر را در اینجا نمونه‌سازی می‌کنیم
        // تا در متد پردازشگر ایجکس آماده استفاده باشند.
        // این کلاس‌ها توسط Autoloader فایل اصلی افزونه بارگذاری می‌شوند.
        $this->scraper = new Ready_Importer_Scraper();
        $this->importer = new Ready_Importer_Importer();
    }

    /**
     * هوک ایجکس: پردازش یک لینک محصول
     *
     * این متد قلب تپنده افزونه است. فقط *یک* لینک را دریافت،
     * اسکرپ و درون‌ریزی می‌کند و نتیجه را به صورت JSON برمی‌گرداند.
     * منطق صف و تکرار در جاوااسکریپت سمت کلاینت مدیریت می‌شود.
     */
    public function handle_process_single_link() {
        
        // ۱. بررسی امنیتی (Nonce)
        check_ajax_referer('rpi_importer_nonce', 'nonce');

        // ۲. بررسی سطح دسترسی کاربر
        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                array('message' => __('شما دسترسی لازم برای انجام این کار را ندارید.', RPI_TEXT_DOMAIN)),
                403 // کد وضعیت "Forbidden"
            );
        }

        // ۳. دریافت، اعتبارسنجی و پاک‌سازی ورودی‌ها
        // ما انتظار داریم این متغیرها در هر درخواست AJAX ارسال شوند.
        
        $link = isset($_POST['link']) ? sanitize_text_field(wp_unslash($_POST['link'])) : '';
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $product_status = isset($_POST['product_status']) ? sanitize_key($_POST['product_status']) : 'draft';

        // اعتبارسنجی اولیه
        if (empty($link) || !filter_var($link, FILTER_VALIDATE_URL) || !strpos($link, 'digikala.com')) {
            wp_send_json_error(
                array('message' => sprintf(__('لینک نامعتبر است: %s', RPI_TEXT_DOMAIN), $link)),
                400 // کد وضعیت "Bad Request"
            );
        }

        if ($category_id === 0) {
            wp_send_json_error(
                array('message' => __('دسته‌بندی مقصد انتخاب نشده است.', RPI_TEXT_DOMAIN)),
                400
            );
        }

        // شروع فرآیند...
        try {
            
            // --- ۴. مرحله اسکرپ (Scrape) ---
            // کلاس اسکرپر فراخوانی می‌شود.
            // این متد باید تمام داده‌های ساختاریافته را برگرداند.
            $scraped_data = $this->scraper->scrape_product_data($link);

            if (is_wp_error($scraped_data)) {
                // اگر اسکرپر خطا برگرداند (مثلاً صفحه 404 یا تغییر ساختار)
                throw new Exception($scraped_data->get_error_message());
            }

            // --- ۵. مرحله درون‌ریزی (Import) ---
            // داده‌های اسکرپ شده به کلاس ایمپورتر ارسال می‌شوند.
            // همچنین تنظیمات فرم (دسته‌بندی و وضعیت) را به آن پاس می‌دهیم.
            $import_result = $this->importer->import_product($scraped_data, $category_id, $product_status);

            if (is_wp_error($import_result)) {
                // اگر درون‌ریزی خطا داشت (مثلاً مشکل در ذخیره تصویر)
                throw new Exception($import_result->get_error_message());
            }
            
            // --- ۶. ارسال پاسخ موفقیت‌آمیز ---
            // $import_result شامل اطلاعاتی مثل ID محصول جدید و پیام موفقیت است
            wp_send_json_success(array(
                'message' => $import_result['message'], // مثال: "محصول 'X' با موفقیت (ID: 123) وارد شد."
                'product_id' => $import_result['product_id'],
                'original_link' => $link
            ));

        } catch (Exception $e) {
            // مدیریت یکپارچه خطاها
            wp_send_json_error(
                array(
                    'message' => sprintf(__('خطا در پردازش لینک %s: %s', RPI_TEXT_DOMAIN), $link, $e->getMessage())
                ),
                500 // خطای داخلی سرور
            );
        }
    }
}