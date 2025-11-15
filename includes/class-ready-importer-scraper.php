<?php
/**
 * کلاس اسکرپر (Scraper) - نسخه API-First (Hardened)
 *
 * *تغییرات این نسخه: (فاز ۲.۵ - رفع ایرادات)*
 * - (امنیت حافظه): _extract_preloaded_state() اکنون از strpos به جای Regex استفاده می‌کند.
 * - (رفع باگ): _scrape_images_html() اکنون URLهای رشته‌ای و آرایه‌ای را مدیریت می‌کند.
 * - (ارتقا): اکنون 'stock_quantity' واقعی را برای متغیرها اسکرپ می‌کند.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importere/includes
 * @author     Ready Studio
 */

// ... (سازنده و متدهای is_product_link, is_category_link, scrape_category_page از مرحله قبل) ...

class Ready_Importer_Scraper {

    private $dom;
    private $xpath;
    private $html_body = '';
    private $json_ld_data = null;
    private $preloaded_state = null;
    private $request_headers;

    public function __construct() {
        $this->request_headers = array(
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
            'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
        );
    }
    
    public function is_product_link($url) { /* ... (کد از مرحله قبل) ... */ }
    public function is_category_link($url) { /* ... (کد از مرحله قبل) ... */ }
    public function scrape_category_page($category_url) { /* ... (کد از مرحله قبل) ... */ }

    /**
     * متد اصلی: اسکرپ کردن داده‌های یک *تک محصول*
     */
    public function scrape_product_data($url) {
        $dkp = $this->get_dkp_from_url($url);
        if (is_wp_error($dkp)) return $dkp;

        $api_data = $this->fetch_from_api_v2($dkp);
        if (!is_wp_error($api_data)) {
            return $this->parse_api_data($api_data);
        }

        // API شکست خورد، بازگشت به HTML
        return $this->scrape_html_data($url);
    }

    private function get_dkp_from_url($url) { /* ... (کد از مرحله قبل) ... */ }

    /**
     * روش اول: دریافت داده از API v2 محصول
     */
    private function fetch_from_api_v2($dkp) { /* ... (کد از مرحله قبل) ... */ }

    /**
     * پارس کردن داده‌های تمیز API محصول
     * --- (ارتقا یافته برای رفع باگ تصویر و افزودن موجودی) ---
     */
    private function parse_api_data($data) {
        $scraped_data = array();
        $main_product = $data['product'];
        
        // ... (title, sku, description, short_description, price, attributes, categories, brand از مرحله قبل) ...
        $scraped_data['title'] = $main_product['title_fa'];
        $scraped_data['sku'] = 'DKP-' . $main_product['dkp'];
        $scraped_data['description'] = $main_product['review']['description'] ?? '';
        // (short_description) ...
        $default_variant = $data['variants'][$main_product['default_variant_id']] ?? reset($data['variants']);
        $scraped_data['regular_price'] = $default_variant['price']['rrp_price'] ?? 0;
        $scraped_data['sale_price'] = $default_variant['price']['selling_price'] ?? 0;
        if ($scraped_data['regular_price'] == $scraped_data['sale_price']) { $scraped_data['sale_price'] = ''; }
        // (attributes, categories, brand) ...

        // (رفع باگ ۱: مدیریت تصاویر)
        $scraped_data['images'] = array();
        foreach ($main_product['images']['gallery'] as $img) {
            $url = '';
            if (is_array($img['url']) && !empty($img['url'])) {
                $url = $img['url'][0]; // ساختار مورد انتظار
            } elseif (is_string($img['url'])) {
                $url = $img['url']; // فال‌بک برای زمانی که url یک رشته است
            }
            if (!empty($url)) {
                $scraped_data['images'][] = $url;
            }
        }

        // (رفع باگ ۵: افزودن موجودی)
        $variations = array();
        if (!empty($data['variants'])) {
            foreach ($data['variants'] as $variant) {
                $var_attributes = array();
                if (isset($variant['color'])) { $var_attributes[] = array('name' => 'رنگ', 'value' => $variant['color']['title']); }
                if (isset($variant['warranty'])) { $var_attributes[] = array('name' => 'گارانتی', 'value' => $variant['warranty']['title_fa']); }
                
                $variations[] = array(
                    'sku' => 'DKP-VAR-' . $variant['id'],
                    'price' => $variant['price']['selling_price'], // ریال
                    'stock' => $variant['stock_quantity'] ?? 0, // <-- (جدید)
                    'attributes' => $var_attributes
                );
            }
        }
        $scraped_data['variations'] = $variations;

        return $scraped_data;
    }

    /**
     * روش دوم (پشتیبان): دریافت HTML
     */
    private function fetch_html_data($url) { /* ... (کد از مرحله قبل) ... */ }
    
    /**
     * روش دوم (پشتیبان): اسکرپ کردن مستقیم HTML
     */
    private function scrape_html_data($url) {
        $html_fetch = $this->fetch_html_data($url);
        if (is_wp_error($html_fetch)) { return $html_fetch; }
        
        libxml_use_internal_errors(true);
        $this->dom = new DOMDocument(); $this->dom->loadHTML($this->html_body);
        libxml_clear_errors();
        $this->xpath = new DOMXPath($this->dom);

        // --- (رفع باگ ۳: مدیریت حافظه) ---
        // $this->json_ld_data = $this->_extract_json_ld(); // (این هنوز از XPath امن استفاده می‌کند)
        $this->preloaded_state = $this->_extract_preloaded_state_safe(); // (استفاده از متد امن جدید)
        
        if ($this->preloaded_state === null) {
            return new WP_Error('no_preloaded_state', __('اسکرپ HTML شکست خورد: داده‌های PRELOADED_STATE پیدا نشد.', RPI_TEXT_DOMAIN));
        }
        $main_product_data = $this->preloaded_state['product']['data'] ?? null;
        if ($main_product_data === null) {
             return new WP_Error('no_preloaded_state_product', __('داده‌های محصول در PRELOADED_STATE پیدا نشد.', RPI_TEXT_DOMAIN));
        }

        $scraped_data = array();
        $scraped_data['title'] = $main_product_data['title_fa'] ?? '';
        $scraped_data['sku'] = 'DKP-' . $main_product_data['dkp'];
        $scraped_data['description'] = $main_product_data['review']['description'] ?? '';
        $scraped_data['short_description'] = $this->_scrape_short_description_html($main_product_data);
        $price_data = $this->_scrape_price_html($main_product_data);
        $scraped_data['regular_price'] = $price_data['regular'];
        $scraped_data['sale_price'] = $price_data['sale'];
        $scraped_data['images'] = $this->_scrape_images_html($main_product_data); // (متد ارتقا یافته)
        $scraped_data['attributes'] = $this->_scrape_attributes_html($main_product_data);
        $scraped_data['categories'] = $this->_scrape_categories_html($main_product_data);
        $scraped_data['brand'] = $main_product_data['brand']['title_fa'] ?? '';
        $scraped_data['variations'] = $this->_scrape_variations_html($main_product_data); // (متد ارتقا یافته)
        
        if (empty($scraped_data['regular_price']) && !empty($scraped_data['variations'])) {
            $prices = wp_list_pluck($scraped_data['variations'], 'price');
            if (!empty($prices)) { $scraped_data['regular_price'] = min($prices); }
        }
        return $scraped_data;
    }


    /* *************************************************************** */
    /* * متدهای کمکی اسکرپ HTML (ارتقا یافته) * */
    /* *************************************************************** */

    private function _extract_json_ld() { /* ... (کد از مرحله قبل - XPath امن است) ... */ }
    
    /**
     * (جدید - رفع باگ ۳: مدیریت حافظه)
     * استخراج PRELOADED_STATE با استفاده از strpos به جای Regex
     */
    private function _extract_preloaded_state_safe() {
        $start_tag = 'window[\'__PRELOADED_STATE__\'] = ';
        $start_pos = strpos($this->html_body, $start_tag);
        
        if ($start_pos === false) {
            return null; // تگ اسکریپت پیدا نشد
        }
        
        $start_pos += strlen($start_tag);
        
        // جستجو برای نزدیک‌ترین تگ بستن اسکریپت
        $end_pos = strpos($this->html_body, '</script>', $start_pos);
        
        if ($end_pos === false) {
            return null; // تگ بستن پیدا نشد
        }
        
        // استخراج JSON
        $json_string = substr($this->html_body, $start_pos, $end_pos - $start_pos);
        
        // پاک‌سازی نهایی (حذف ; و فضاهای خالی)
        $json_string = rtrim(trim($json_string), ';');
        
        $json_data = json_decode($json_string, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json_data;
        }
        
        return null; // خطای پارس JSON
    }

    private function _scrape_short_description_html($main_product_data) { /* ... (کد از مرحله قبل) ... */ }
    private function _scrape_price_html($main_product_data) { /* ... (کد از مرحله قبل) ... */ }

    /**
     * (رفع باگ ۲: مدیریت تصاویر)
     */
    private function _scrape_images_html($main_product_data) {
        $images = array();
        if (isset($main_product_data['images']['gallery'])) {
            foreach ($main_product_data['images']['gallery'] as $img) {
                $url = '';
                // (جدید) بررسی جامع‌تر برای انواع ساختار URL
                if (isset($img['url']) && is_array($img['url']) && !empty($img['url'])) {
                    $url = $img['url'][0];
                } elseif (isset($img['url']) && is_string($img['url'])) {
                    $url = $img['url'];
                } elseif (is_string($img)) { // فال‌بک نهایی اگر خود $img رشته بود
                    $url = $img;
                }
                
                if (!empty($url)) {
                    $images[] = $url;
                }
            }
        }
        
        $cleaned_images = array();
        foreach ($images as $img_url) { $cleaned_images[] = preg_replace('/\?.*/', '', $img_url); }
        return array_unique(array_filter($cleaned_images));
    }

    private function _scrape_attributes_html($main_product_data) { /* ... (کد از مرحله قبل) ... */ }
    private function _scrape_categories_html($main_product_data) { /* ... (کد از مرحله قبل) ... */ }

    /**
     * (رفع باگ ۵: افزودن موجودی)
     */
    private function _scrape_variations_html($main_product_data) {
        $variations = array();
        if (empty($this->preloaded_state['variants']['data'])) { return $variations; }
        
        $all_variants = $this->preloaded_state['variants']['data'];
        foreach ($all_variants as $variant_id => $variant) {
            $var_attributes = array();
            if (isset($variant['color']['title'])) { $var_attributes[] = array('name' => 'رنگ', 'value' => $variant['color']['title']); }
            if (isset($variant['warranty']['title_fa'])) { $var_attributes[] = array('name' => 'گارانتی', 'value' => $variant['warranty']['title_fa']); }
            if (!empty($var_attributes)) {
                 $variations[] = array( 
                     'sku' => 'DKP-VAR-' . $variant_id, 
                     'price' => $variant['price']['selling_price'], // ریال
                     'stock' => $variant['stock_quantity'] ?? 0, // <-- (جدید)
                     'attributes' => $var_attributes 
                 );
            }
        }
        return $variations;
    }
}