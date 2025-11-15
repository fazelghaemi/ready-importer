<?php
/**
 * کلاس اسکرپر (Scraper) - نسخه API-First (ارتقا یافته)
 *
 * استراتژی:
 * 1. (ترجیحی) اتصال به API رسمی دیجی‌کالا (v2/product)
 * 2. (پشتیبان) اسکرپ HTML (JSON-LD + PRELOADED_STATE)
 *
 * *تغییرات این نسخه: (فاز ۲)*
 * - اضافه شدن هدر User-Agent واقعی به تمام درخواست‌ها برای جلوگیری از بلاک شدن.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/includes
 * @author     Ready Studio
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class Ready_Importer_Scraper {

    // (متغیرهای قبلی)
    private $dom;
    private $xpath;
    private $html_body = '';
    private $json_ld_data = null;
    private $preloaded_state = null;

    /**
     * @var array هدرهای استاندارد برای شبیه‌سازی مرورگر
     */
    private $request_headers;

    public function __construct() {
        // هدرهایی مشابه یک مرورگر واقعی تنظیم می‌کنیم
        // بر اساس تحلیل فایل digi-request.php
        $this->request_headers = array(
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
            'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7'
        );
    }

    /**
     * متد اصلی: اسکرپ کردن داده‌های محصول
     */
    public function scrape_product_data($url) {
        
        $dkp = $this->get_dkp_from_url($url);
        if (is_wp_error($dkp)) {
            return $dkp;
        }

        $api_data = $this->fetch_from_api_v2($dkp);
        if (!is_wp_error($api_data)) {
            return $this->parse_api_data($api_data);
        }

        // API شکست خورد، بازگشت به HTML
        return $this->scrape_html_data($url);
    }

    /**
     * استخراج DKP (مانند dkp-15180004) از URL
     */
    private function get_dkp_from_url($url) {
        if (preg_match('/\/product\/(dkp-\d+)/', $url, $matches)) {
            return $matches[1];
        }
        return new WP_Error('no_dkp', __('کد محصول (DKP) در لینک پیدا نشد.', RPI_TEXT_DOMAIN));
    }

    /**
     * روش اول: دریافت داده از API v2 دیجی‌کالا
     */
    private function fetch_from_api_v2($dkp) {
        $api_url = 'https://api.digikala.com/v2/product/' . str_replace('dkp-', '', $dkp);
        
        $response = wp_remote_get($api_url, array(
            'timeout'    => 20,
            'headers'    => $this->request_headers // <-- ارتقا: هدرها اضافه شد
        ));

        if (is_wp_error($response)) { /* ... (مدیریت خطا) ... */ }
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) { /* ... (مدیریت خطا) ... */ }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['status']) || $data['status'] !== 200 || !isset($data['data'])) {
            return new WP_Error('api_json_error', 'پاسخ JSON دریافتی از API نامعتبر است.');
        }

        return $data['data'];
    }

    /**
     * پارس کردن داده‌های تمیز API به فرمت استاندارد افزونه
     * (کد از مرحله قبل - بدون تغییر)
     */
    private function parse_api_data($data) {
        $scraped_data = array();
        $main_product = $data['product'];
        
        $scraped_data['title'] = $main_product['title_fa'];
        $scraped_data['sku'] = 'DKP-' . $main_product['dkp'];
        $scraped_data['description'] = $main_product['review']['description'] ?? '';
        
        // ... (کد توضیحات کوتاه از مرحله قبل)
        if (isset($main_product['review']['attributes'])) {
            $html = '<ul>';
            foreach ($main_product['review']['attributes'] as $attr) {
                 $html .= '<li>' . esc_html($attr['title']) . ': ' . esc_html($attr['values'][0]) . '</li>';
            }
            $scraped_data['short_description'] = $html . '</ul>';
        } else {
             $scraped_data['short_description'] = '';
        }

        // ... (کد قیمت از مرحله قبل)
        $default_variant = $data['variants'][$main_product['default_variant_id']] ?? reset($data['variants']);
        $scraped_data['regular_price'] = $default_variant['price']['rrp_price'] ?? 0; // ریال
        $scraped_data['sale_price'] = $default_variant['price']['selling_price'] ?? 0; // ریال
        if ($scraped_data['regular_price'] == $scraped_data['sale_price']) {
            $scraped_data['sale_price'] = ''; 
        }

        // ... (کد تصاویر، مشخصات، دسته‌بندی، برند از مرحله قبل)
        $scraped_data['images'] = array_column($main_product['images']['gallery'], 'url');
        
        $attributes = array();
        if (isset($main_product['specifications'][0]['attributes'])) {
            foreach ($main_product['specifications'][0]['attributes'] as $attr) {
                $attributes[] = array('name' => sanitize_text_field($attr['title']), 'value' => implode(', ', $attr['values']), 'is_visible' => 1, 'is_variation' => 0);
            }
        }
        $scraped_data['attributes'] = $attributes;
        $scraped_data['categories'] = array_column($data['breadcrumbs'], 'title_fa');
        $scraped_data['brand'] = $main_product['brand']['title_fa'] ?? '';

        // ... (کد متغیرها از مرحله قبل)
        $variations = array();
        if (!empty($data['variants'])) {
            foreach ($data['variants'] as $variant) {
                $var_attributes = array();
                if (isset($variant['color'])) { $var_attributes[] = array('name' => 'رنگ', 'value' => $variant['color']['title']); }
                if (isset($variant['warranty'])) { $var_attributes[] = array('name' => 'گارانتی', 'value' => $variant['warranty']['title_fa']); }
                
                $variations[] = array('sku' => 'DKP-VAR-' . $variant['id'], 'price' => $variant['price']['selling_price'], 'attributes' => $var_attributes);
            }
        }
        $scraped_data['variations'] = $variations;

        return $scraped_data;
    }

    /**
     * روش دوم (پشتیبان): دریافت HTML
     */
    private function fetch_html_data($url) {
        $response = wp_remote_get($url, array(
            'timeout'    => 30,
            'headers'    => $this->request_headers // <-- ارتقا: هدرها اضافه شد
        ));

        if (is_wp_error($response)) {
            return new WP_Error('html_http_error', 'خطای WP_Error در اسکرپ HTML: ' . $response->get_error_message());
        }
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
             return new WP_Error('html_code_error', 'اسکرپ HTML: کد ' . $http_code . ' دریافت شد.');
        }
        
        $this->html_body = wp_remote_retrieve_body($response);
        if (empty($this->html_body)) {
            return new WP_Error('html_empty_body', 'اسکرپ HTML: پاسخ خالی بود.');
        }
        
        return true; // موفقیت
    }
    
    /**
     * روش دوم (پشتیبان): اسکرپ کردن مستقیم HTML
     */
    private function scrape_html_data($url) {
        
        // --- ۱. دریافت HTML ---
        $html_fetch = $this->fetch_html_data($url);
        if (is_wp_error($html_fetch)) {
            return $html_fetch; // برگرداندن خطای دریافت HTML
        }

        // --- ۲. آماده‌سازی پارسر ---
        libxml_use_internal_errors(true);
        $this->dom = new DOMDocument();
        $this->dom->loadHTML($this->html_body);
        libxml_clear_errors();
        $this->xpath = new DOMXPath($this->dom);

        // --- ۳. استخراج داده‌ها ---
        $this->json_ld_data = $this->_extract_json_ld();
        $this->preloaded_state = $this->_extract_preloaded_state();
        
        if ($this->preloaded_state === null && $this->json_ld_data === null) {
            return new WP_Error('no_data_html', __('اسکرپ HTML شکست خورد: هیچ داده ساختاریافته‌ای پیدا نشد.', RPI_TEXT_DOMAIN));
        }
        
        $main_product_data = $this->preloaded_state['product']['data'] ?? null;
        if ($main_product_data === null) {
             return new WP_Error('no_preloaded_state', __('داده‌های PRELOADED_STATE در HTML پیدا نشد.', RPI_TEXT_DOMAIN));
        }

        // --- ۴. پارس کردن داده‌های HTML ---
        // (این کدها دقیقاً همان کدهای مرحله قبل هستند)
        $scraped_data = array();
        $scraped_data['title'] = $main_product_data['title_fa'] ?? $this->json_ld_data['name'] ?? '';
        $scraped_data['sku'] = 'DKP-' . $main_product_data['dkp'];
        $scraped_data['description'] = $this->json_ld_data['description'] ?? ($main_product_data['review']['description'] ?? '');
        $scraped_data['short_description'] = $this->_scrape_short_description_html($main_product_data);
        
        $price_data = $this->_scrape_price_html($main_product_data);
        $scraped_data['regular_price'] = $price_data['regular']; // ریال
        $scraped_data['sale_price'] = $price_data['sale']; // ریال

        $scraped_data['images'] = $this->_scrape_images_html($main_product_data);
        $scraped_data['attributes'] = $this->_scrape_attributes_html($main_product_data);
        $scraped_data['categories'] = $this->_scrape_categories_html($main_product_data);
        $scraped_data['brand'] = $main_product_data['brand']['title_fa'] ?? $this->json_ld_data['brand']['name'] ?? '';
        $scraped_data['variations'] = $this->_scrape_variations_html($main_product_data);
        
        if (empty($scraped_data['regular_price']) && !empty($scraped_data['variations'])) {
            $prices = wp_list_pluck($scraped_data['variations'], 'price');
            if (!empty($prices)) {
                $scraped_data['regular_price'] = min($prices);
            }
        }

        return $scraped_data;
    }


    /* *************************************************************** */
    /* * متدهای کمکی اسکرپ HTML (کدهای مرحله قبل - بدون تغییر) * */
    /* *************************************************************** */

    private function _extract_json_ld() {
        $nodes = $this->xpath->query('//script[@type="application/ld+json"]');
        if ($nodes->length > 0) {
            foreach ($nodes as $node) {
                $json = json_decode($node->nodeValue, true);
                if ($json && isset($json['@type']) && ($json['@type'] == 'Product' || in_array('Product', (array)$json['@type']))) {
                    return $json;
                }
            }
        }
        return null;
    }
    
    private function _extract_preloaded_state() {
        if (preg_match('/window\[\'__PRELOADED_STATE__\'\]\s*=\s*({.*?});/s', $this->html_body, $matches)) {
            if (isset($matches[1])) {
                $json_data = json_decode($matches[1], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $json_data;
                }
            }
        }
        return null;
    }

    private function _scrape_short_description_html($main_product_data) {
        if (isset($main_product_data['review']['attributes'])) {
            $html = '<ul>';
            foreach ($main_product_data['review']['attributes'] as $attr) {
                 $html .= '<li>' . esc_html($attr['title']) . ': ' . esc_html($attr['values'][0]) . '</li>';
            }
            return $html . '</ul>';
        }
        return '';
    }

    private function _scrape_price_html($main_product_data) {
        $prices = ['regular' => '', 'sale' => ''];
        $default_variant = $this->preloaded_state['variants']['data'][$main_product_data['default_variant']] ?? null;
        if ($default_variant) {
            $prices['regular'] = $default_variant['price']['rrp_price']; // ریال
            $prices['sale'] = $default_variant['price']['selling_price']; // ریال
            if ($prices['regular'] == $prices['sale']) { $prices['sale'] = ''; }
            return $prices;
        }
        return $prices;
    }

    private function _scrape_images_html($main_product_data) {
        $images = array();
        if (isset($main_product_data['images']['gallery'])) {
            foreach ($main_product_data['images']['gallery'] as $img) {
                $images[] = $img['url'][0]; 
            }
        }
        if (empty($images) && isset($this->json_ld_data['image'])) {
            $images = (array)$this->json_ld_data['image'];
        }
        $cleaned_images = array();
        foreach ($images as $img_url) {
            $cleaned_images[] = preg_replace('/\?.*/', '', $img_url);
        }
        return array_unique(array_filter($cleaned_images));
    }

    private function _scrape_attributes_html($main_product_data) {
        $attributes = array();
        if (isset($main_product_data['specifications'][0]['attributes'])) {
            foreach ($main_product_data['specifications'][0]['attributes'] as $attr) {
                $attributes[] = array('name' => sanitize_text_field($attr['title']), 'value' => implode(', ', $attr['values']), 'is_visible' => 1, 'is_variation' => 0);
            }
        }
        return $attributes;
    }

    private function _scrape_categories_html($main_product_data) {
        $categories = array();
        if (isset($this->preloaded_state['breadcrumbs']['data']['items'])) {
             foreach ($this->preloaded_state['breadcrumbs']['data']['items'] as $item) {
                 $categories[] = $item['title_fa'];
             }
        }
        return array_filter($categories);
    }

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
                     'attributes' => $var_attributes
                 );
            }
        }
        return $variations;
    }
}