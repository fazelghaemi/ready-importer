<?php
/**
 * کلاس درون‌ریز (Importer) - (Hardened v2)
 *
 * *تغییرات این نسخه: (رفع خطای Fatal Error 500)*
 * - (رفع باگ): انتقال require_once های حساس (ABSPATH) از __construct
 * به داخل متدهایی که از آن‌ها استفاده می‌کنند (import_product).
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

    private $settings;

    /**
     * سازنده کلاس
     *
     * --- (آپدیت حیاتی) ---
     * require_once های حساس حذف شدند تا از خطای Undefined constant ABSPATH
     * در طول بارگذاری AJAX جلوگیری شود.
     */
    public function __construct() {
        // (require_once ها به import_product منتقل شدند)
        
        // تنظیمات را می‌توان با خیال راحت در سازنده خواند
        $this->settings = (array) get_option('rpi_settings', array());
    }

    /**
     * متد کمکی: تبدیل ریال به تومان
     */
    private function convert_rial_to_toman($rial_price) {
        $price = preg_replace("/[^0-9]/", "", $rial_price);
        if (empty($price) || !is_numeric($price)) return '';
        return strval(intval($price) / 10);
    }

    /**
     * متد کمکی: اعمال قوانین قیمت‌گذاری و گرد کردن
     *
     * (جدید) این متد اکنون public است تا توسط تست واحد قابل دسترسی باشد
     */
    public function apply_price_rules_and_rounding($price_toman) {
        if (empty($price_toman) || !is_numeric($price_toman)) {
            return $price_toman;
        }

        $price = floatval($price_toman);
        $original_price = $price; 

        // ۱. اعمال قوانین بازه قیمت
        if (isset($this->settings['price_rules']) && is_array($this->settings['price_rules'])) {
            foreach ($this->settings['price_rules'] as $rule) {
                // اطمینان از اینکه مقادیر خالی به عنوان 0 یا INF در نظر گرفته می‌شوند
                $min = (empty($rule['min_price']) && $rule['min_price'] !== '0') ? 0 : floatval($rule['min_price']);
                $max = (empty($rule['max_price']) && $rule['max_price'] !== '0') ? INF : floatval($rule['max_price']);
                $value = floatval($rule['value']);
                
                if ($price >= $min && $price <= $max) {
                    if ($rule['type'] === 'percent') {
                        // اعمال درصد بر اساس قیمت *اصلی*
                        $price = $original_price + ($original_price * $value / 100);
                    } else { // fixed
                        // اعمال مبلغ ثابت بر اساس قیمت *اصلی*
                        $price = $original_price + $value;
                    }
                    // مهم: فقط اولین قانون منطبق اعمال می‌شود
                    break; 
                }
            }
        }

        // ۲. (رفع باگ ۴: بررسی صریح‌تر)
        $rounding_precision = $this->settings['round_prices'] ?? 'none';
        if ($rounding_precision !== 'none' && is_numeric($rounding_precision)) {
            $precision = intval($rounding_precision);
            if ($precision > 0) {
                // گرد کردن به بالا به نزدیک‌ترین ضریب (مثال: ۱۰۰۰)
                $price = ceil($price / $precision) * $precision;
            }
        }

        return strval($price);
    }

    /**
     * متد کمکی: اعمال قوانین جستجو و جایگزینی
     *
     * (جدید) این متد اکنون public است تا توسط تست واحد قابل دسترسی باشد
     */
    public function apply_content_rules($text, $area = 'all') {
        if (empty($text) || !isset($this->settings['find_replace_rules']) || !is_array($this->settings['find_replace_rules'])) {
            return $text;
        }
        
        $find = array();
        $replace = array();

        // جمع‌آوری تمام قوانین منطبق
        foreach ($this->settings['find_replace_rules'] as $rule) {
            if (empty($rule['find'])) continue;
            
            // بررسی اینکه آیا قانون برای این بخش (area) اعمال می‌شود یا خیر
            if ($rule['area'] === 'all' || $rule['area'] === $area) {
                $find[] = $rule['find'];
                $replace[] = $rule['replace'];
            }
        }

        if (!empty($find)) {
            // اجرای تمام جایگزینی‌ها به یکباره
            $text = str_replace($find, $replace, $text);
        }
        return $text;
    }


    /**
     * متد اصلی: درون‌ریزی محصول
     * --- (ارتقا یافته برای بارگذاری وابستگی‌ها) ---
     */
    public function import_product($data, $category_id, $product_status) {
        
        // --- (جدید) بارگذاری وابستگی‌های حساس در زمان اجرا ---
        // این فایل‌ها فقط زمانی که واقعاً درون‌ریزی انجام می‌شود، بارگذاری می‌شوند
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        // ---

        $existing_product_id = wc_get_product_id_by_sku($data['sku']);
        $is_update = ($existing_product_id > 0);
        $is_variable = !empty($data['variations']);

        // ۱. دریافت یا ایجاد آبجکت محصول
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

        // --- ۲. پاک‌سازی و اعمال قوانین محتوا (رفع باگ XSS) ---
        $raw_title = $this->apply_content_rules($data['title'], 'title');
        $raw_description = $this->apply_content_rules($data['description'], 'desc');
        $raw_short_description = $this->apply_content_rules($data['short_description'], 'desc');
        
        // پاک‌سازی نهایی *بعد* از اعمال قوانین
        $final_title = sanitize_text_field($raw_title);
        $final_description = wp_kses_post($raw_description); // اجازه HTML امن
        $final_short_description = wp_kses_post($raw_short_description);
        
        $product->set_name($final_title);
        if (!$is_update) { // اسلاگ را فقط در زمان ایجاد محصول تنظیم کن
            $product->set_slug(sanitize_title($final_title));
        }
        $product->set_description($final_description);
        $product->set_short_description($final_short_description);
        $product->set_sku(sanitize_text_field($data['sku']));
        $product->set_status($product_status);

        // --- ۳. اعمال قوانین قیمت‌گذاری ---
        $regular_price_toman = $this->convert_rial_to_toman($data['regular_price']);
        $sale_price_toman = $this->convert_rial_to_toman($data['sale_price']);
        $final_regular_price = $this->apply_price_rules_and_rounding($regular_price_toman);
        $final_sale_price = $this->apply_price_rules_and_rounding($sale_price_toman);

        if (!$is_variable) {
            $product->set_regular_price($final_regular_price);
            $product->set_sale_price($final_sale_price);
        } else {
            // در محصولات متغیر، قیمت اصلی معمولاً قیمت کمترین متغیر است
            $product->set_regular_price($final_regular_price);
            // $product->set_sale_price($final_sale_price); // قیمت متغیرها مهم‌تر است
        }

        // --- ۴. دسته‌بندی‌ها و ویژگی‌ها ---
        $product->set_category_ids(array($category_id));
        $wc_attributes = $this->prepare_attributes($data['attributes'], $data['variations']);
        $product->set_attributes($wc_attributes);

        // --- ۵. ذخیره محصول ---
        $product_id = $product->save();
        if ($product_id === 0 || is_wp_error($product_id)) {
            return new WP_Error('save_error', __('خطا در ذخیره‌سازی محصول اصلی.', RPI_TEXT_DOMAIN));
        }

        // --- ۶. اعمال تاکسونومی‌ها (برند و برچسب) ---
        // (باید *بعد* از save() اجرا شوند)
        $tag_ids = $this->get_or_create_terms($data['categories'], 'product_tag');
        if (!empty($tag_ids)) {
            $product->set_tag_ids($tag_ids);
        }
        
        $brand_taxonomy = $this->settings['brand_taxonomy'] ?? 'none';
        if (!empty($data['brand']) && $brand_taxonomy !== 'none') {
            $brand_ids = $this->get_or_create_terms(array($data['brand']), $brand_taxonomy);
            if (!empty($brand_ids)) {
                wp_set_object_terms($product_id, $brand_ids, $brand_taxonomy, false);
            }
        }
        
        // --- ۷. ساخت متغیرها (فقط متغیر) ---
        if ($is_variable) {
            $this->create_variations($product_id, $data['variations']);
        }
        
        // --- ۸. مدیریت تصاویر ---
        if (!empty($data['images'])) {
            $this->assign_images_to_product($product_id, $data['images'], $final_title, $is_update);
        }
        
        // --- ۹. نتیجه ---
        $action_text = $is_update ? 'به‌روز شد' : 'وارد شد';
        return array(
            'product_id' => $product_id,
            'message'    => sprintf(__("محصول '%s' با موفقیت به عنوان %s (ID: %d) %s.", RPI_TEXT_DOMAIN), $final_title, ($is_variable ? 'متغیر' : 'ساده'), $product_id, $action_text)
        );
    }
    
    /**
     * متد کمکی: آماده‌سازی آرایه ویژگی‌ها برای ووکامرس
     */
    private function prepare_attributes($scraped_attrs, $scraped_vars) {
        $wc_attributes = array();
        $variation_attr_names = array();

        // ۱. ویژگی‌های متغیر (مثل رنگ، گارانتی)
        if (!empty($scraped_vars)) {
            // ابتدا نام ویژگی‌های متغیر را پیدا کن (مثال: 'رنگ', 'گارانتی')
            foreach ($scraped_vars[0]['attributes'] as $attr) {
                $variation_attr_names[] = $attr['name'];
            }
            $variation_attr_names = array_unique($variation_attr_names);
        
            // برای هر ویژگی متغیر، تمام گزینه‌های ممکن را جمع‌آوری کن
            foreach ($variation_attr_names as $var_attr_name) {
                $attribute = new WC_Product_Attribute();
                $attribute->set_name($var_attr_name); // مثال: 'رنگ'
                
                $all_options = array();
                foreach ($scraped_vars as $var) {
                    foreach ($var['attributes'] as $attr) {
                        if ($attr['name'] === $var_attr_name) {
                            $all_options[] = $attr['value'];
                        }
                    }
                }
                $options = array_unique($all_options);
                
                $attribute->set_options($options); // مثال: ['قرمز', 'آبی']
                $attribute->set_visible(true);
                $attribute->set_variation(true); // مهم: این ویژگی برای متغیرها است
                $wc_attributes[] = $attribute;
            }
        }

        // ۲. ویژگی‌های ثابت (مشخصات فنی)
        if (!empty($scraped_attrs)) {
            foreach ($scraped_attrs as $attr) {
                // اگر این ویژگی قبلاً به عنوان ویژگی متغیر اضافه نشده، آن را اضافه کن
                if (!in_array($attr['name'], $variation_attr_names)) {
                    $attribute = new WC_Product_Attribute();
                    $attribute->set_name($attr['name']);
                    $attribute->set_options(array($attr['value']));
                    $attribute->set_visible(true);
                    $attribute->set_variation(false); // مهم: این ویژگی فقط نمایشی است
                    $wc_attributes[] = $attribute;
                }
            }
        }
        
        return $wc_attributes;
    }

    /**
     * متد کمکی: ساخت متغیرها (Variations)
     * --- (ارتقا یافته برای موجودی کالا) ---
     */
    private function create_variations($product_id, $scraped_vars) {
        
        // حذف متغیرهای قدیمی برای جلوگیری از همپوشانی در زمان آپدیت
        $product = wc_get_product($product_id);
        if ($product) {
            foreach ($product->get_children() as $child_id) {
                $child = wc_get_product($child_id);
                if ($child) {
                    $child->delete(true);
                }
            }
        }

        foreach ($scraped_vars as $var_data) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_sku(sanitize_text_field($var_data['sku']));
            
            // اعمال قوانین قیمت‌گذاری روی هر متغیر
            $price_toman = $this->convert_rial_to_toman($var_data['price']);
            $final_var_price = $this->apply_price_rules_and_rounding($price_toman);

            $variation->set_regular_price($final_var_price);
            $variation->set_price($final_var_price); // قیمت فروش را هم برابر قرار می‌دهیم
            
            // --- (جدید - رفع باگ ۵: موجودی واقعی) ---
            $stock = (int)($var_data['stock'] ?? 0);
            $variation->set_manage_stock(true); // همیشه مدیریت را فعال کن
            $variation->set_stock_quantity($stock);
            $variation->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
            // ---
            
            $variation->set_status('publish'); // متغیرها باید منتشر شوند

            // تنظیم ویژگی‌های این متغیر (مثال: 'رنگ' = 'قرمز')
            $wc_var_attributes = array();
            foreach ($var_data['attributes'] as $attr) {
                // نام ویژگی (مثل 'رنگ') را به اسلاگ تبدیل می‌کنیم (مثل 'رنگ')
                // مقدار (مثل 'قرمز') را همانطور که هست استفاده می‌کنیم
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
     */
    private function assign_images_to_product($product_id, $images, $post_title, $is_update) {
        
        // --- (جدید) بارگذاری وابستگی‌ها ---
        // این توابع در media_sideload_image (که در upload_image_from_url است)
        // و wp_insert_post (که در assign_hotlinked_images است) استفاده می‌شوند.
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        // بررسی گزینه Hotlinking
        if (isset($this->settings['hotlink_images']) && $this->settings['hotlink_images'] === '1') {
            $this->assign_hotlinked_images($product_id, $images, $post_title, $is_update);
            return;
        }

        // اگر در حال آپدیت هستیم و محصول از قبل تصویر شاخص دارد، تصاویر را دوباره دانلود نکن
        // TODO: این باید به یک گزینه در تنظیمات ("به‌روزرسانی اجباری تصاویر") تبدیل شود
        if ($is_update && has_post_thumbnail($product_id)) {
            return;
        }

        $image_ids = array();
        foreach ($images as $image_url) {
            $attachment_id = $this->upload_image_from_url($image_url, $post_title, $product_id);
            if (!is_wp_error($attachment_id)) {
                $image_ids[] = $attachment_id;
            }
        }
        
        // تنظیم تصویر شاخص و گالری
        if (!empty($image_ids)) {
            set_post_thumbnail($product_id, $image_ids[0]);
            if (count($image_ids) > 1) {
                update_post_meta($product_id, '_product_image_gallery', implode(',', array_slice($image_ids, 1)));
            }
        }
    }

    /**
     * متد کمکی: دانلود یک تصویر از URL و آپلود آن در وردپرس
     */
    private function upload_image_from_url($image_url, $post_title, $post_id = 0) {
        // افزودن http: اگر وجود ندارد
        if (strpos($image_url, '//') === 0) {
            $image_url = 'https:' . $image_url;
        }
        
        // حذف Query String از URL (مثال: ?x-img-ops=...)
        $image_url = strtok($image_url, '?');

        // استفاده از تابع هسته وردپرس برای سایدلود کردن تصویر
        // این تابع تصویر را دانلود، در کتابخانه رسانه ذخیره و پیوست می‌کند
        $attachment_id = media_sideload_image($image_url, $post_id, $post_title, 'id');
        
        return $attachment_id;
    }

    /**
     * متد کمکی: ساخت پیوست‌های "پوسته" (Placeholder) برای Hotlinking
     */
    private function assign_hotlinked_images($product_id, $images, $post_title, $is_update) {
        
        // TODO: این منطق باید تکمیل شود.
        // این یک قابلیت بسیار پیشرفته است که نیاز به بازنویسی فیلترهای
        // 'wp_get_attachment_url' و 'wp_get_attachment_image_src' دارد
        // تا وردپرس را مجبور کند URL خارجی را به جای URL داخلی نمایش دهد.
        // (مشابه کاری که افزونه رقیب در digi-product-import.php انجام داده بود)

        // فعلاً، ما فقط تصویر اول را به عنوان "پوسته" ذخیره می‌کنیم
        
        if ($is_update && has_post_thumbnail($product_id)) {
            return; // در زمان آپدیت فعلاً کاری نکن
        }

        if (empty($images[0])) return;
        
        $image_url = strtok($images[0], '?'); // URL تصویر اصلی

        // ساخت یک پیوست ساختگی
        $attachment_data = array(
            'post_mime_type' => 'image/jpeg', // فرض می‌کنیم jpeg است
            'post_title'     => $post_title,
            'post_content'   => '',
            'post_status'    => 'inherit',
            'guid'           => $image_url // نکته کلیدی: GUID را برابر URL خارجی قرار می‌دهیم
        );
        
        $attachment_id = wp_insert_attachment($attachment_data, $image_url, $product_id);
        
        if (!is_wp_error($attachment_id)) {
            // تنظیم به عنوان تصویر شاخص
            set_post_thumbnail($product_id, $attachment_id);
            
            // (گالری برای hotlinking فعلاً پشتیبانی نمی‌شود)
        }
    }

    /**
     * متد کمکی: دریافت یا ساخت Term ها (برای برچسب‌ها و برند)
     */
    private function get_or_create_terms($term_names, $taxonomy) {
        $term_ids = array();
        foreach ($term_names as $term_name) {
            $term_name = sanitize_text_field($term_name);
            if (empty($term_name)) continue;

            $term = get_term_by('name', $term_name, $taxonomy);
            
            if ($term === false) {
                // Term وجود ندارد، آن را بساز
                $new_term = wp_insert_term($term_name, $taxonomy);
                if (!is_wp_error($new_term)) {
                    $term_ids[] = $new_term['term_id'];
                }
            } else {
                // Term وجود دارد
                $term_ids[] = $term->term_id;
            }
        }
        return $term_ids;
    }
}