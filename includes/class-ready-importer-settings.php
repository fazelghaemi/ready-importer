<?php
/**
 * کلاس مدیریت تنظیمات (Settings API)
 *
 * --- جدید (فاز ۲) ---
 *
 * این کلاس تمام منطق مربوط به ثبت، نمایش، و اعتبارسنجی
 * گزینه‌های افزونه در صفحه "تنظیمات" را با استفاده از
 * WordPress Settings API مدیریت می‌کند.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/includes
 * @author     Ready Studio
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class Ready_Importer_Settings {

    /**
     * @var string نام گروه گزینه‌ها (Option Group)
     */
    private $option_group = 'rpi_settings_group';

    /**
     * @var string نام گزینه (Option Name) که در دیتابیس ذخیره می‌شود
     */
    private $option_name = 'rpi_settings';

    /**
     * ثبت کردن تمام تنظیمات، بخش‌ها و فیلدها
     */
    public function register_settings() {
        
        // ۱. ثبت تنظیمات اصلی (نامی که در جدول wp_options ذخیره می‌شود)
        register_setting(
            $this->option_group,       // نام گروه
            $this->option_name,        // نام گزینه
            array($this, 'sanitize')   // متد اعتبارسنجی (Sanitize)
        );

        // --- بخش اول: تنظیمات قیمت‌گذاری ---
        add_settings_section(
            'rpi_settings_section_pricing', // ID بخش
            __('قوانین قیمت‌گذاری', RPI_TEXT_DOMAIN), // عنوان
            array($this, 'print_section_info_pricing'), // متد callback برای توضیحات بخش
            $this->option_group // شناسه صفحه (باید با گروه یکی باشد)
        );

        add_settings_field(
            'price_rules', // ID فیلد
            __('قوانین افزایش قیمت', RPI_TEXT_DOMAIN), // عنوان فیلد
            array($this, 'field_callback_price_rules'), // متد callback برای رندر فیلد
            $this->option_group, // شناسه صفحه
            'rpi_settings_section_pricing' // ID بخش
        );
        
        add_settings_field(
            'round_prices', 
            __('گرد کردن قیمت نهایی', RPI_TEXT_DOMAIN), 
            array($this, 'field_callback_round_prices'), 
            $this->option_group, 
            'rpi_settings_section_pricing'
        );

        // --- بخش دوم: تنظیمات محتوا (سئو) ---
        add_settings_section(
            'rpi_settings_section_content', 
            __('قوانین محتوا (سئو)', RPI_TEXT_DOMAIN), 
            array($this, 'print_section_info_content'), 
            $this->option_group
        );

        add_settings_field(
            'find_replace_rules', 
            __('جستجو و جایگزینی', RPI_TEXT_DOMAIN), 
            array($this, 'field_callback_find_replace_rules'), 
            $this->option_group, 
            'rpi_settings_section_content'
        );
        
        // --- بخش سوم: تنظیمات درون‌ریزی ---
        add_settings_section(
            'rpi_settings_section_import', 
            __('تنظیمات درون‌ریزی', RPI_TEXT_DOMAIN), 
            null, 
            $this->option_group
        );

        add_settings_field(
            'brand_taxonomy', 
            __('تاکسونومی برند', RPI_TEXT_DOMAIN), 
            array($this, 'field_callback_brand_taxonomy'), 
            $this->option_group, 
            'rpi_settings_section_import'
        );

        add_settings_field(
            'hotlink_images', 
            __('نمایش مستقیم تصاویر (Hotlink)', RPI_TEXT_DOMAIN), 
            array($this, 'field_callback_hotlink_images'), 
            $this->option_group, 
            'rpi_settings_section_import'
        );
        
        // --- بخش چهارم: تنظیمات پیشرفته (API) ---
        add_settings_section(
            'rpi_settings_section_api', 
            __('تنظیمات پیشرفته (API)', RPI_TEXT_DOMAIN), 
            null, 
            $this->option_group
        );

        add_settings_field(
            'proxy_url', 
            __('آدرس پروکسی', RPI_TEXT_DOMAIN), 
            array($this, 'field_callback_proxy_url'), 
            $this->option_group, 
            'rpi_settings_section_api'
        );
    }

    /**
     * متد اعتبارسنجی (Sanitize)
     * این متد قبل از ذخیره‌سازی در دیتابیس، تمام ورودی‌ها را پاک‌سازی می‌کند.
     */
    public function sanitize($input) {
        $sanitized_input = array();
        
        // TODO: اعتبارسنجی دقیق برای هر فیلد (مثلاً price_rules, find_replace_rules)
        // ...

        // مثال ساده:
        if (isset($input['round_prices'])) {
            $sanitized_input['round_prices'] = sanitize_text_field($input['round_prices']);
        }
        if (isset($input['brand_taxonomy'])) {
            $sanitized_input['brand_taxonomy'] = sanitize_text_field($input['brand_taxonomy']);
        }
        if (isset($input['hotlink_images'])) {
            $sanitized_input['hotlink_images'] = '1';
        }
         if (isset($input['proxy_url'])) {
            $sanitized_input['proxy_url'] = esc_url_raw($input['proxy_url']);
        }

        return $input; // فعلاً همه را برمی‌گردانیم (اعتبارسنجی بعداً تکمیل می‌شود)
    }

    /**
     * توضیحات بخش قیمت‌گذاری
     */
    public function print_section_info_pricing() {
        echo '<p class="rpi-card__description">' . __('قوانین مربوط به تغییر قیمت محصولات هنگام درون‌ریزی. قیمت‌ها ابتدا از ریال به تومان تبدیل شده، سپس این قوانین روی آن‌ها اعمال می‌شود.', RPI_TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * توضیحات بخش محتوا
     */
    public function print_section_info_content() {
        echo '<p class="rpi-card__description">' . __('عناوین و توضیحات محصولات را برای سئوی بهتر یا حذف نام برند، به صورت خودکار تغییر دهید.', RPI_TEXT_DOMAIN) . '</p>';
    }


    /* *************************************************************** */
    /* * متدهای Callback برای رندر کردن فیلدهای فرم * */
    /* *************************************************************** */

    /**
     * فیلد: قوانین افزایش قیمت (تکرارشونده)
     */
    public function field_callback_price_rules() {
        // TODO: پیاده‌سازی فیلد تکرارشونده با جاوااسکریپت
        echo '<p class="description">' . __('(در دست توسعه) قانون: اگر قیمت بین X و Y بود، Z درصد/تومان اضافه کن.', RPI_TEXT_DOMAIN) . '</p>';
    }

    /**
     * فیلد: گرد کردن قیمت
     */
    public function field_callback_round_prices() {
        $options = (array) get_option($this->option_name);
        $value = $options['round_prices'] ?? 'none';
        ?>
        <select id="round_prices" name="<?php echo $this->option_name; ?>[round_prices]" class="rpi-input-field rpi-input-field--small">
            <option value="none" <?php selected($value, 'none'); ?>><?php _e('گرد نکن', RPI_TEXT_DOMAIN); ?></option>
            <option value="1000" <?php selected($value, '1000'); ?>><?php _e('گرد کردن به ۱,۰۰۰ تومان', RPI_TEXT_DOMAIN); ?></option>
            <option value="10000" <?php selected($value, '10000'); ?>><?php _e('گرد کردن به ۱۰,۰۰۰ تومان', RPI_TEXT_DOMAIN); ?></option>
        </select>
        <?php
    }

    /**
     * فیلد: جستجو و جایگزینی (تکرارشونده)
     */
    public function field_callback_find_replace_rules() {
        // TODO: پیاده‌سازی فیلد تکرارشونده با جاوااسکریپت
         echo '<p class="description">' . __('(در دست توسعه) قانون: کلمه X را با Y در عنوان/توضیحات جایگزین کن.', RPI_TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * فیلد: تاکسونومی برند
     */
    public function field_callback_brand_taxonomy() {
        $options = (array) get_option($this->option_name);
        $value = $options['brand_taxonomy'] ?? 'none';
        
        // دریافت تمام تاکسونومی‌های موجود
        $taxonomies = get_taxonomies(array('object_type' => array('product')), 'objects');
        ?>
        <select id="brand_taxonomy" name="<?php echo $this->option_name; ?>[brand_taxonomy]" class="rpi-input-field rpi-input-field--small">
            <option value="none" <?php selected($value, 'none'); ?>><?php _e('ذخیره نکن', RPI_TEXT_DOMAIN); ?></option>
            <option value="pa_brand" <?php selected($value, 'pa_brand'); ?>><?php _e('ویژگی سراسری (pa_brand)', RPI_TEXT_DOMAIN); ?></option>
            <?php
            foreach ($taxonomies as $tax_slug => $tax) {
                // فقط تاکسونومی‌های محصول که pa_ (ویژگی) نیستند را نشان بده (مثل YITH Brand)
                if (strpos($tax_slug, 'pa_') === false && $tax_slug !== 'product_cat' && $tax_slug !== 'product_tag') {
                    printf(
                        '<option value="%s" %s>%s (%s)</option>',
                        esc_attr($tax_slug),
                        selected($value, $tax_slug, false),
                        esc_html($tax->label),
                        esc_html($tax_slug)
                    );
                }
            }
            ?>
        </select>
         <p class="description"><?php _e('برند محصول اسکرپ شده در کدام تاکسونومی ذخیره شود؟ (سازگار با افزونه‌های برند)', RPI_TEXT_DOMAIN); ?></p>
        <?php
    }

    /**
     * فیلد: هات‌لینک تصاویر
     */
    public function field_callback_hotlink_images() {
        $options = (array) get_option($this->option_name);
        $checked = isset($options['hotlink_images']) ? '1' : '0';
        ?>
        <label class="rpi-switch">
            <input id="hotlink_images" name="<?php echo $this->option_name; ?>[hotlink_images]" type="checkbox" value="1" <?php checked($checked, '1'); ?>>
            <span class="rpi-slider"></span>
        </label>
        <span class="rpi-switch-label"><?php _e('فعال‌سازی نمایش مستقیم (Hotlinking)', RPI_TEXT_DOMAIN); ?></span>
        <p class="description"><?php _e('در صورت فعال‌سازی، تصاویر در هاست شما آپلود نمی‌شوند و مستقیماً از سرور دیجی‌کالا نمایش داده می‌شوند. (باعث صرفه‌جویی در فضا اما غیر پیشنهادی)', RPI_TEXT_DOMAIN); ?></p>
        <?php
    }

    /**
     * فیلد: آدرس پروکسی
     */
    public function field_callback_proxy_url() {
         $options = (array) get_option($this->option_name);
         $value = $options['proxy_url'] ?? '';
         ?>
         <input type="text" id="proxy_url" name="<?php echo $this->option_name; ?>[proxy_url]" value="<?php echo esc_attr($value); ?>" class="rpi-input-field" dir="ltr" placeholder="http://user:pass@proxy.example.com:8080">
         <p class="description"><?php _e('برای جلوگیری از بلاک شدن IP در اسکرپ‌های سنگین، آدرس پروکسی خود را وارد کنید.', RPI_TEXT_DOMAIN); ?></p>
         <?php
    }
}