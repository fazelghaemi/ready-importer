<?php
/**
 * کلاس درون‌ریز (Importer) - نسخه حرفه‌ای (ارتقا یافته)
 *
 * - پشتیبانی کامل از محصولات ساده و متغیر
 * - **جدید: تبدیل قیمت از ریال به تومان**
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

    public function __construct() {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
    }

    /**
     * متد کمکی: تبدیل قیمت از ریال به تومان.
     * @param string|int $rial_price
     * @return string
     */
    private function convert_rial_to_toman($rial_price) {
        $price = preg_replace("/[^0-9]/", "", $rial_price); // حذف هر چیزی جز عدد
        if (empty($price) || !is_numeric($price)) {
            return '';
        }
        return strval(intval($price) / 10);
    }

    /**
     * متد اصلی: درون‌ریزی محصول
     */
    public function import_product($data, $category_id, $product_status) {
        
        $existing_product_id = wc_get_product_id_by_sku($data['sku']);
        $is_update = ($existing_product_id > 0);
        $is_variable = !empty($data['variations']);

        if ($is_variable) {
            $product = $is_update ? wc_get_product($existing_product_id) : new WC_Product_Variable();
            if (!$product || !$product instanceof WC_Product_Variable) {
                 $product = new WC_Product_Variable();
                 $is_update = false;
            }
        } else {
            $product = $is_update ? wc_get_product($existing_product_id) : new WC_Product_Simple();
             if (!$product || !$product instanceof WC_Product_Simple) {
                 $product = new WC_Product_Simple();
                 $is_update = false;
            }
        }

        $product->set_name(sanitize_text_field($data['title']));
        if (!$is_update) {
            $product->set_slug(sanitize_title($data['title']));
        }
        $product->set_description($data['description']);
        $product->set_short_description($data['short_description']);
        $product->set_sku(sanitize_text_field($data['sku']));
        $product->set_status($product_status);

        // --- ۴. تنظیم قیمت (با تبدیل ریال به تومان) ---
        $regular_price_toman = $this->convert_rial_to_toman($data['regular_price']);
        $sale_price_toman = $this->convert_rial_to_toman($data['sale_price']);

        if (!$is_variable) {
            $product->set_regular_price($regular_price_toman);
            $product->set_sale_price($sale_price_toman);
        } else {
            $product->set_regular_price($regular_price_toman); // قیمت "شروع از"
        }

        $product->set_category_ids(array($category_id));
        $tag_ids = $this->get_or_create_terms($data['categories'], 'product_tag');
        $product->set_tag_ids($tag_ids);

        if (!empty($data['brand'])) {
            // اطمینان از وجود تاکسونومی برند (سازگاری با افزونه‌های برند)
            $brand_taxonomy = 'product_brand'; // مثال: YITH
            if (!taxonomy_exists($brand_taxonomy)) {
                $brand_taxonomy = 'pa_brand'; // فال‌بک به ویژگی ووکامرس
                 // TODO: این بخش باید در تنظیمات افزونه قابل конфиگ باشد
            }
            
            $brand_ids = $this->get_or_create_terms(array($data['brand']), $brand_taxonomy);
            if (!empty($brand_ids)) {
                wp_set_object_terms($product->get_id(), $brand_ids, $brand_taxonomy);
            }
        }

        // --- ۷. مدیریت ویژگی‌ها ---
        $wc_attributes = $this->prepare_attributes($data['attributes'], $data['variations']);
        $product->set_attributes($wc_attributes);

        // --- ۸. ذخیره محصول (قبل از افزودن متغیرها) ---
        $product_id = $product->save();
        if ($product_id === 0 || is_wp_error($product_id)) {
            return new WP_Error('save_error', __('خطا در ذخیره‌سازی محصول اصلی.', RPI_TEXT_DOMAIN));
        }

        // --- ۹. (فقط متغیر) ساخت متغیرها ---
        if ($is_variable) {
            $this->create_variations($product_id, $data['variations']);
        }
        
        // --- ۱۰. مدیریت تصاویر ---
        if (!empty($data['images'])) {
            $this->assign_images_to_product($product_id, $data['images'], $data['title']);
        }
        
        // --- ۱۱. برگرداندن نتیجه موفق ---
        $action_text = $is_update ? 'به‌روز شد' : 'وارد شد';
        return array(
            'product_id' => $product_id,
            'message'    => sprintf(__("محصول '%s' با موفقیت به عنوان %s (ID: %d) %s.", RPI_TEXT_DOMAIN), $data['title'], ($is_variable ? 'متغیر' : 'ساده'), $product_id, $action_text)
        );
    }

    /**
     * متد کمکی: آماده‌سازی آرایه ویژگی‌ها برای ووکامرس.
     */
    private function prepare_attributes($scraped_attrs, $scraped_vars) {
        $wc_attributes = array();
        $variation_attr_names = array(); // نام ویژگی‌هایی که متغیر هستند (مثل 'رنگ')

        // ۱. ابتدا نام ویژگی‌های متغیر را پیدا کن
        if (!empty($scraped_vars)) {
            // تمام نام‌های ویژگی‌ها را از اولین متغیر جمع کن
            foreach ($scraped_vars[0]['attributes'] as $attr) {
                $variation_attr_names[] = $attr['name'];
            }
        }
        $variation_attr_names = array_unique($variation_attr_names);

        // ۲. ویژگی‌های متغیر را بساز
        foreach ($variation_attr_names as $var_attr_name) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name($var_attr_name); // مثال: 'رنگ'
            
            // تمام مقادیر ممکن این ویژگی را از *همه* متغیرها جمع‌آوری کن
            $all_options = array();
            foreach ($scraped_vars as $var) {
                foreach ($var['attributes'] as $attr) {
                    if ($attr['name'] === $var_attr_name) {
                        $all_options[] = $attr['value'];
                    }
                }
            }
            $options = array_unique($all_options);
            
            $attribute->set_options($options);
            $attribute->set_visible(true);
            $attribute->set_variation(true); // مهم!
            $wc_attributes[] = $attribute;
        }

        // ۳. ویژگی‌های معمولی (مشخصات فنی) را اضافه کن
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
     * متد کمکی: ساخت متغیرها (Variations) برای یک محصول متغیر.
     */
    private function create_variations($product_id, $scraped_vars) {
        foreach ($scraped_vars as $var_data) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_sku(sanitize_text_field($var_data['sku']));
            
            // **تبدیل ریال به تومان**
            $price_toman = $this->convert_rial_to_toman($var_data['price']);
            $variation->set_regular_price($price_toman);
            
            $variation->set_manage_stock(true); 
            $variation->set_stock_quantity(10); // TODO: اسکرپ کردن موجودی
            $variation->set_status('publish');

            // تنظیم ویژگی‌های این متغیر (مثلاً: 'رنگ' = 'قرمز')
            $wc_var_attributes = array();
            foreach ($var_data['attributes'] as $attr) {
                $attr_name_slug = sanitize_title($attr['name']); // مثال: 'رنگ' -> 'رنگ'
                $attr_value = $attr['value']; // مثال: 'قرمز'
                $wc_var_attributes[$attr_name_slug] = $attr_value;
            }
            $variation->set_attributes($wc_var_attributes);

            $variation->save();
        }
    }

    /**
     * متد کمکی: آپلود تصاویر و الصاق به محصول.
     */
    private function assign_images_to_product($product_id, $images, $post_title) {
        $image_ids = array();
        
        // جلوگیری از دانلود مجدد تصاویر در زمان آپدیت
        if (has_post_thumbnail($product_id)) {
            $image_ids[] = get_post_thumbnail_id($product_id);
        }
        $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
        if (!empty($gallery_ids)) {
             $image_ids = array_merge($image_ids, explode(',', $gallery_ids));
        }
        
        // اگر قبلاً تصویر داشته، آپلود جدید انجام نده (برای سرعت)
        // TODO: باید در تنظیمات گزینه‌ای برای "به‌روزرسانی اجباری تصاویر" باشد
        if (!empty($image_ids)) {
            return;
        }

        $image_ids = array(); // آرایه را خالی کن تا از نو آپلود شوند
        foreach ($images as $image_url) {
            $attachment_id = $this->upload_image_from_url($image_url, $post_title, $product_id);

            if (!is_wp_error($attachment_id)) {
                $image_ids[] = $attachment_id;
            }
        }
        
        if (!empty($image_ids)) {
            set_post_thumbnail($product_id, $image_ids[0]);
            if (count($image_ids) > 1) {
                update_post_meta($product_id, '_product_image_gallery', implode(',', array_slice($image_ids, 1)));
            }
        }
    }

    /**
     * متد کمکی: آپلود یک تصویر از URL.
     */
    private function upload_image_from_url($image_url, $post_title, $post_id = 0) {
        $attachment_id = media_sideload_image($image_url, $post_id, $post_title, 'id');
        if (!is_wp_error($attachment_id)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $post_title);
        }
        return $attachment_id;
    }

    /**
     * متد کمکی: دریافت یا ساخت Term ها.
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