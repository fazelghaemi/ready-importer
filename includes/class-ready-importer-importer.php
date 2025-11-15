<?php
/**
 * کلاس درون‌ریز (Importer) - نسخه حرفه‌ای نهایی (فاز ۲)
 *
 * این کلاس اکنون به WordPress Settings API متصل است و
 * تمام قوانین پیشرفته (قیمت‌گذاری، محتوا، هات‌لینک) را اعمال می‌کند.
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
     * @var array تنظیمات ذخیره شده افزونه از دیتابیس
     */
    private $settings;

    /**
     * سازنده کلاس
     *
     * نیازمندی‌های وردپرس را بارگذاری کرده و تنظیمات افزونه را
     * از دیتابیس واکشی می‌کند.
     */
    public function __construct() {
        // بارگذاری فایل‌های مورد نیاز وردپرس برای مدیریت رسانه
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        // --- جدید (فاز ۲): بارگذاری تنظیمات ذخیره شده ---
        $this->settings = (array) get_option('rpi_settings', array());
    }

    /**
     * متد اصلی: درون‌ریزی محصول
     *
     * داده‌های اسکرپ شده را دریافت کرده، قوانین تنظیمات را اعمال
     * و محصول را در ووکامرس ایجاد یا به‌روزرسانی می‌کند.
     *
     * @param array $data           آرایه داده‌های اسکرپ شده از کلاس Scraper.
     * @param int   $category_id    ID دسته‌بندی مقصد در ووکامرس.
     * @param string $product_status وضعیت محصول (draft, publish, pending).
     * @return array|WP_Error       اطلاعات محصول وارد شده یا خطای وردپرس.
     */
    public function import_product($data, $category_id, $product_status) {
        
        // --- ۱. بررسی محصول تکراری (بر اساس SKU) ---
        $existing_product_id = wc_get_product_id_by_sku($data['sku']);
        $is_update = ($existing_product_id > 0);
        
        // --- ۲. تصمیم‌گیری: محصول ساده یا متغیر؟ ---
        $is_variable = !empty($data['variations']);

        if ($is_variable) {
            $product = $is_update ? wc_get_product($existing_product_id) : new WC_Product_Variable();
            if (!$product || !$product instanceof WC_Product_Variable) {
                 $product = new WC_Product_Variable(); $is_update = false;
            }
        } else {
            $product = $is_update ? wc_get_product($existing_product_id) : new WC_Product_Simple();
             if (!$product || !$product instanceof WC_Product_Simple) {
                 $product = new WC_Product_Simple(); $is_update = false;
            }
        }

        // --- ۳. اعمال قوانین محتوا و تنظیم داده‌های اصلی ---
        
        // (جدید) اعمال قوانین جستجو/جایگزینی
        $final_title = $this->apply_content_rules($data['title'], 'title');
        $final_description = $this->apply_content_rules($data['description'], 'desc');
        $final_short_description = $this->apply_content_rules($data['short_description'], 'desc');

        $product->set_name(sanitize_text_field($final_title));
        if (!$is_update) {
            $product->set_slug(sanitize_title($final_title)); // اسلاگ فقط در زمان ساخت
        }
        $product->set_description($final_description);
        $product->set_short_description($final_short_description);
        $product->set_sku(sanitize_text_field($data['sku']));
        $product->set_status($product_status);

        // --- ۴. اعمال قوانین قیمت‌گذاری (با تبدیل ریال به تومان) ---
        $regular_price_toman = $this->convert_rial_to_toman($data['regular_price']);
        $sale_price_toman = $this->convert_rial_to_toman($data['sale_price']);

        // (جدید) اعمال قوانین قیمت و گرد کردن
        $final_regular_price = $this->apply_price_rules_and_rounding($regular_price_toman);
        $final_sale_price = $this->apply_price_rules_and_rounding($sale_price_toman);

        if (!$is_variable) {
            $product->set_regular_price($final_regular_price);
            $product->set_sale_price($final_sale_price);
        } else {
            $product->set_regular_price($final_regular_price); // قیمت "شروع از"
        }

        // --- ۵. تنظیم دسته‌بندی‌ها و برچسب‌ها ---
        $product->set_category_ids(array($category_id));
        $tag_ids = $this->get_or_create_terms($data['categories'], 'product_tag');
        $product->set_tag_ids($tag_ids);

        // --- ۶. مدیریت برند (بر اساس تنظیمات) ---
        $brand_taxonomy = $this->settings['brand_taxonomy'] ?? 'none';
        if (!empty($data['brand']) && $brand_taxonomy !== 'none') {
            $brand_ids = $this->get_or_create_terms(array($data['brand']), $brand_taxonomy);
            if (!empty($brand_ids)) {
                // این تابع برند را به محصول الصاق می‌کند
                wp_set_object_terms($product->get_id(), $brand_ids, $brand_taxonomy, false);
            }
        }

        // --- ۷. مدیریت ویژگی‌ها (Attributes) ---
        $wc_attributes = $this->prepare_attributes($data['attributes'], $data['variations']);
        $product->set_attributes($wc_attributes);

        // --- ۸. ذخیره محصول (قبل از افزودن متغیرها و تصاویر) ---
        $product_id = $product->save();
        if ($product_id === 0 || is_wp_error($product_id)) {
            return new WP_Error('save_error', __('خطا در ذخیره‌سازی محصول اصلی.', RPI_TEXT_DOMAIN));
        }

        // --- ۹. (فقط متغیر) ساخت متغیرها (Variations) ---
        if ($is_variable) {
            $this->create_variations($product_id, $data['variations']);
        }
        
        // --- ۱۰. مدیریت تصاویر (بر اساس تنظیمات Hotlink) ---
        if (!empty($data['images'])) {
            $this->assign_images_to_product($product_id, $data['images'], $final_title, $is_update);
        }
        
        // --- ۱۱. برگرداندن نتیجه موفق ---
        $action_text = $is_update ? 'به‌روز شد' : 'وارد شد';
        return array(
            'product_id' => $product_id,
            'message'    => sprintf(__("محصول '%s' با موفقیت به عنوان %s (ID: %d) %s.", RPI_TEXT_DOMAIN), $final_title, ($is_variable ? 'متغیر' : 'ساده'), $product_id, $action_text)
        );
    }
    
    /* *************************************************************** */
    /* * متدهای کمکی جدید (فاز ۲) برای اعمال تنظیمات * */
    /* *************************************************************** */

    /**
     * متد کمکی: تبدیل قیمت از ریال به تومان.
     */
    private function convert_rial_to_toman($rial_price) {
        $price = preg_replace("/[^0-9]/", "", $rial_price);
        if (empty($price) || !is_numeric($price)) return '';
        return strval(intval($price) / 10);
    }

    /**
     * متد کمکی: اعمال قوانین قیمت‌گذاری و گرد کردن
     */
    private function apply_price_rules_and_rounding($price_toman) {
        if (empty($price_toman) || !is_numeric($price_toman)) {
            return $price_toman;
        }

        $price = floatval($price_toman);
        $original_price = $price; // برای قوانین درصدی

        // ۱. اعمال قوانین بازه قیمت
        if (isset($this->settings['price_rules']) && is_array($this->settings['price_rules'])) {
            foreach ($this->settings['price_rules'] as $rule) {
                $min = empty($rule['min_price']) ? 0 : floatval($rule['min_price']);
                $max = empty($rule['max_price']) ? INF : floatval($rule['max_price']);
                $value = floatval($rule['value']);
                
                if ($price >= $min && $price <= $max) {
                    if ($rule['type'] === 'percent') {
                        $price = $original_price + ($original_price * $value / 100);
                    } else { // fixed
                        $price = $original_price + $value;
                    }
                    // فقط اولین قانون منطبق اعمال می‌شود
                    break; 
                }
            }
        }

        // ۲. اعمال گرد کردن
        if (isset($this->settings['round_prices']) && $this->settings['round_prices'] !== 'none') {
            $precision = intval($this->settings['round_prices']); // 1000 or 10000
            if ($precision > 0) {
                $price = ceil($price / $precision) * $precision;
            }
        }

        return strval($price);
    }

    /**
     * متد کمکی: اعمال قوانین جستجو و جایگزینی
     */
    private function apply_content_rules($text, $area = 'all') {
        if (empty($text) || !isset($this->settings['find_replace_rules']) || !is_array($this->settings['find_replace_rules'])) {
            return $text;
        }

        $find = array();
        $replace = array();

        foreach ($this->settings['find_replace_rules'] as $rule) {
            if (empty($rule['find'])) continue;
            
            // بررسی اینکه آیا قانون برای این بخش (title, desc, all) هست یا نه
            if ($rule['area'] === 'all' || $rule['area'] === $area) {
                $find[] = $rule['find'];
                $replace[] = $rule['replace'];
            }
        }

        if (!empty($find)) {
            $text = str_replace($find, $replace, $text);
        }

        return $text;
    }


    /* *************************************************************** */
    /* * متدهای هسته درون‌ریزی (آپدیت شده برای فاز ۲) * */
    /* *************************************************************** */

    /**
     * متد کمکی: آماده‌سازی آرایه ویژگی‌ها برای ووکامرس.
     * (کد از مرحله قبل - بدون تغییر)
     */
    private function prepare_attributes($scraped_attrs, $scraped_vars) {
        $wc_attributes = array();
        $variation_attr_names = array();
        if (!empty($scraped_vars)) {
            foreach ($scraped_vars[0]['attributes'] as $attr) { $variation_attr_names[] = $attr['name']; }
        }
        $variation_attr_names = array_unique($variation_attr_names);
        foreach ($variation_attr_names as $var_attr_name) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name($var_attr_name);
            $all_options = array();
            foreach ($scraped_vars as $var) {
                foreach ($var['attributes'] as $attr) {
                    if ($attr['name'] === $var_attr_name) { $all_options[] = $attr['value']; }
                }
            }
            $options = array_unique($all_options);
            $attribute->set_options($options);
            $attribute->set_visible(true);
            $attribute->set_variation(true);
            $wc_attributes[] = $attribute;
        }
        foreach ($scraped_attrs as $attr) {
            if (!in_array($attr['name'], $variation_attr_names)) {
                $attribute = new WC_Product_Attribute();
                $attribute->set_name($attr['name']);
                $attribute->set_options(array($attr['value']));
                $attribute->set_visible(true);
                $attribute->set_variation(false);
                $wc_attributes[] = $attribute;
            }
        }
        return $wc_attributes;
    }

    /**
     * متد کمکی: ساخت متغیرها (Variations)
     * --- آپدیت (فاز ۲): اعمال قوانین قیمت‌گذاری ---
     */
    private function create_variations($product_id, $scraped_vars) {
        foreach ($scraped_vars as $var_data) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_sku(sanitize_text_field($var_data['sku']));
            
            // **تبدیل ریال به تومان**
            $price_toman = $this->convert_rial_to_toman($var_data['price']);
            
            // **(جدید) اعمال قوانین قیمت و گرد کردن**
            $final_var_price = $this->apply_price_rules_and_rounding($price_toman);

            $variation->set_regular_price($final_var_price);
            
            $variation->set_manage_stock(true); 
            $variation->set_stock_quantity(10); // TODO: اسکرپ کردن موجودی
            $variation->set_status('publish');

            $wc_var_attributes = array();
            foreach ($var_data['attributes'] as $attr) {
                $attr_name_slug = sanitize_title($attr['name']);
                $attr_value = $attr['value'];
                $wc_var_attributes[$attr_name_slug] = $attr_value;
            }
            $variation->set_attributes($wc_var_attributes);

            $variation->save();
        }
    }

    /**
     * متد کمکی: آپلود تصاویر و الصاق به محصول.
     * --- آپدیت اساسی (فاز ۲): پشتیبانی از Hotlink ---
     */
    private function assign_images_to_product($product_id, $images, $post_title, $is_update) {
        
        // --- (جدید) بررسی تنظیمات Hotlink ---
        if (isset($this->settings['hotlink_images']) && $this->settings['hotlink_images'] === '1') {
            // کاربر هات‌لینک را انتخاب کرده، تصاویر را دانلود *نکن*
            $this->assign_hotlinked_images($product_id, $images, $post_title, $is_update);
            return;
        }

        // --- منطق عادی (دانلود تصاویر) ---
        
        // (کد از مرحله قبل) جلوگیری از دانلود مجدد تصاویر در زمان آپدیت
        // TODO: این باید به یک گزینه "به‌روزرسانی اجباری تصاویر" در تنظیمات متصل شود
        if ($is_update && has_post_thumbnail($product_id)) {
            return; // فعلاً در زمان آپدیت، تصاویر را رها کن
        }

        $image_ids = array();
        foreach ($images as $image_url) {
            $attachment_id = $this->upload_image_from_url($image_url, $post_title, $product_id);

            if (!is_wp_error($attachment_id)) {
                $image_ids[] = $attachment_id;
            }
        }
        
        if (!empty($image_ids)) {
            set_post_thumbnail($product_id, $image_ids[0]); // تصویر شاخص
            if (count($image_ids) > 1) {
                update_post_meta($product_id, '_product_image_gallery', implode(',', array_slice($image_ids, 1)));
            }
        }
    }

    /**
     * متد کمکی: آپلود یک تصویر از URL (دانلود واقعی).
     */
    private function upload_image_from_url($image_url, $post_title, $post_id = 0) {
        // media_sideload_image تصویر را دانلود و در کتابخانه رسانه ذخیره می‌کند
        $attachment_id = media_sideload_image($image_url, $post_id, $post_title, 'id');
        if (!is_wp_error($attachment_id)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $post_title);
        }
        return $attachment_id;
    }
    
    /**
     * متد کمکی: پیوست کردن تصاویر به صورت Hotlink (بدون دانلود)
     * --- جدید (فاز ۲) ---
     */
    private function assign_hotlinked_images($product_id, $images, $post_title, $is_update) {
        // این یک تکنیک پیشرفته برای فریب دادن ووکامرس است
        // ما یک پیوست "جعلی" می‌سازیم که GUID آن به URL خارجی اشاره دارد.
        
        // (مشابه رقیب) اگر محصول آپدیت می‌شود و تصویر دارد، رها کن
        if ($is_update && has_post_thumbnail($product_id)) {
            return;
        }
        
        $image_ids = array();
        $gallery_ids = array();

        foreach ($images as $index => $image_url) {
            $file_name = basename(parse_url($image_url, PHP_URL_PATH));
            
            $attachment_data = array(
                'post_author'    => 0, // نویسنده 0 برای شناسایی
                'post_title'     => $post_title . ' - ' . ($index + 1),
                'post_name'      => sanitize_title($file_name),
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_parent'    => $product_id,
                'post_type'      => 'attachment',
                'post_mime_type' => 'image/jpeg', // فرض می‌کنیم JPEG است
                'guid'           => esc_url_raw($image_url) // حیاتی: آدرس خارجی
            );

            // پیوست "جعلی" را در دیتابیس درج کن
            $attachment_id = wp_insert_post($attachment_data);

            if (!is_wp_error($attachment_id)) {
                // (مشابه رقیب) ما متادیتای پیوست را هم روی آدرس خارجی تنظیم می‌کنیم
                update_post_meta($attachment_id, '_wp_attached_file', $image_url);
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $post_title);
                
                if ($index === 0) {
                    $image_ids[] = $attachment_id; // تصویر شاخص
                } else {
                    $gallery_ids[] = $attachment_id; // گالری
                }
            }
        }
        
        if (!empty($image_ids)) {
            set_post_thumbnail($product_id, $image_ids[0]);
        }
        if (!empty($gallery_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        }
    }


    /**
     * متد کمکی: دریافت یا ساخت Term ها.
     * (کد از مرحله قبل - بدون تغییر)
     */
    private function get_or_create_terms($term_names, $taxonomy) {
        if (empty($term_names) || !taxonomy_exists($taxonomy)) {
            return array();
        }
        $term_ids = array();
        foreach ($term_names as $term_name) {
            $term_name = sanitize_text_field($term_name);
            if(empty($term_name)) continue;
            $term = term_exists($term_name, $taxonomy);
            if ($term !== 0 && $term !== null) {
                $term_ids[] = (int)$term['term_id'];
            } else {
                $new_term = wp_insert_term($term_name, $taxonomy);
                if (!is_wp_error($new_term)) {
                    $term_ids[] = (int)$new_term['term_id'];
                }
            }
        }
        return array_unique($term_ids);
    }
}