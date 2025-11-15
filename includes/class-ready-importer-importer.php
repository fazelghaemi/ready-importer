<?php
/**
 * کلاس درون‌ریز (Importer)
 *
 * مسئولیت: دریافت آرایه‌ای از داده‌های اسکرپ شده و
 * ساخت/به‌روزرسانی محصول در ووکامرس.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/includes
 * @author     Ready Studio
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class Ready_Importer_Importer {

    /**
     * سازنده کلاس.
     */
    public function __construct() {
        // اطمینان از لود شدن فایل‌های مورد نیاز ووکامرس و وردپرس برای مدیریت تصاویر
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
    }

    /**
     * متد اصلی: درون‌ریزی محصول
     *
     * @param array $data           آرایه داده‌های اسکرپ شده از کلاس Scraper.
     * @param int   $category_id    ID دسته‌بندی مقصد در ووکامرس.
     * @param string $product_status وضعیت محصول (draft, publish, pending).
     * @return array|WP_Error       اطلاعات محصول وارد شده یا خطای وردپرس.
     */
    public function import_product($data, $category_id, $product_status) {
        
        // --- ۱. بررسی محصول تکراری (اختیاری اما مهم) ---
        // می‌توانیم بر اساس SKU یا عنوان بررسی کنیم که محصول قبلاً وارد نشده باشد.
        // فعلاً برای سادگی، این بخش را رد می‌کنیم.
        
        // --- ۲. ساخت آبجکت محصول ووکامرس ---
        $product = new WC_Product_Simple();

        // --- ۳. تنظیم داده‌های اصلی ---
        $product->set_name(sanitize_text_field($data['title']));
        $product->set_slug(sanitize_title($data['title']));
        $product->set_description($data['description']); // ووکامرس خودش sanitize می‌کند
        $product->set_short_description($data['short_description']);
        $product->set_sku(sanitize_text_field($data['sku']));
        
        // وضعیت (پیش‌نویس، منتشر شده و...)
        $product->set_status($product_status);

        // --- ۴. تنظیم قیمت ---
        $product->set_regular_price($data['regular_price']);
        if (!empty($data['sale_price'])) {
            $product->set_sale_price($data['sale_price']);
        }

        // --- ۵. تنظیم دسته‌بندی‌ها ---
        // ما دسته‌بندی انتخاب شده توسط کاربر را به عنوان دسته‌بندی اصلی تنظیم می‌کنیم
        $product->set_category_ids(array($category_id));
        
        // (اختیاری) می‌توانیم دسته‌بندی‌های اسکرپ شده را به عنوان برچسب اضافه کنیم
        $tag_ids = $this->get_or_create_tags($data['categories']);
        $product->set_tag_ids($tag_ids);
        
        // --- ۶. مدیریت تصاویر (بخش حیاتی) ---
        if (!empty($data['images'])) {
            $image_ids = array();
            foreach ($data['images'] as $index => $image_url) {
                // 'true' یعنی این تصویر شاخص است (فقط برای اولین تصویر)
                $is_featured = ($index === 0); 
                $attachment_id = $this->upload_image_from_url($image_url, $data['title']);

                if (is_wp_error($attachment_id)) {
                    // اگر آپلود تصویر شکست خورد، لاگ می‌کنیم اما فرآیند را متوقف نمی‌کنیم
                    // (بعداً یک کلاس لاگر حرفه‌ای اضافه خواهیم کرد)
                } else {
                    $image_ids[] = $attachment_id;
                    if ($is_featured) {
                        $product->set_image_id($attachment_id);
                    }
                }
            }
            // اضافه کردن بقیه تصاویر به گالری
            if (count($image_ids) > 1) {
                $product->set_gallery_image_ids(array_slice($image_ids, 1));
            }
        }
        
        // --- ۷. مدیریت ویژگی‌ها (Attributes) ---
        // (این بخش برای محصولات متغیر حیاتی خواهد بود)
        // فعلاً همه را به عنوان ویژگی ساده اضافه می‌کنیم.
        $wc_attributes = array();
        foreach ($data['attributes'] as $attr) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name($attr['name']);
            $attribute->set_options(array($attr['value']));
            $attribute->set_visible($attr['is_visible']);
            $attribute->set_variation(false); // فعلاً محصول ساده است
            $wc_attributes[] = $attribute;
        }
        $product->set_attributes($wc_attributes);

        // --- ۸. ذخیره نهایی محصول ---
        $product_id = $product->save();

        if ($product_id === 0 || is_wp_error($product_id)) {
            return new WP_Error('save_error', __('خطا در ذخیره‌سازی محصول در دیتابیس ووکامرس.', RPI_TEXT_DOMAIN));
        }

        // --- ۹. برگرداندن نتیجه موفق ---
        return array(
            'product_id' => $product_id,
            'message'    => sprintf(__("محصول '%s' با موفقیت به عنوان %s (ID: %d) وارد شد.", RPI_TEXT_DOMAIN), $data['title'], $product_status, $product_id)
        );
    }

    /**
     * متد کمکی: آپلود یک تصویر از URL به کتابخانه رسانه وردپرس.
     *
     * @param string $image_url آدرس کامل تصویر.
     * @param string $post_title عنوانی که برای alt و title تصویر استفاده می‌شود.
     * @return int|WP_Error ID فایل ضمیمه شده یا خطای وردپرس.
     */
    private function upload_image_from_url($image_url, $post_title) {
        // media_sideload_image این تابع را در فایل‌های 'wp-admin/includes/...' که در سازنده لود کردیم، پیدا می‌کند
        
        // به این تابع یک عنوان (desc) می‌دهیم که همان عنوان محصول است
        $attachment_id = media_sideload_image($image_url, 0, $post_title, 'id');
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // (اختیاری) تنظیم متن جایگزین (Alt Text) برای سئو
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $post_title);

        return $attachment_id;
    }

    /**
     * متد کمکی: دریافت یا ساخت برچسب‌ها.
     *
     * @param array $tag_names آرایه‌ای از نام برچسب‌ها.
     * @return array آرایه‌ای از ID برچسب‌ها.
     */
    private function get_or_create_tags($tag_names) {
        $tag_ids = array();
        foreach ($tag_names as $tag_name) {
            $term = term_exists($tag_name, 'product_tag');
            if ($term !== 0 && $term !== null) {
                $tag_ids[] = $term['term_id'];
            } else {
                $new_term = wp_insert_term($tag_name, 'product_tag');
                if (!is_wp_error($new_term)) {
                    $tag_ids[] = $new_term['term_id'];
                }
            }
        }
        return $tag_ids;
    }
}