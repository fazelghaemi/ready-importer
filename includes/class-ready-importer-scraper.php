<?php
/**
 * کلاس اسکرپر (Scraper) - نسخه حرفه‌ای (ارتقا یافته)
 *
 * بر اساس تحلیل واقعی دیجی‌کالا (dkp-15180004)
 * 1. استخراج JSON-LD (پایه)
 * 2. استخراج window['__PRELOADED_STATE__'] (پیشرفته و حیاتی)
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

    private $dom;
    private $xpath;
    private $html_body = '';
    
    /**
     * @var array|null داده‌های JSON-LD (برای گوگل)
     */
    private $json_ld_data = null;

    /**
     * @var array|null داده‌های اصلی صفحه (برای اپ)
     */
    private $preloaded_state = null;

    /**
     * متد اصلی: اسکرپ کردن داده‌های محصول
     */
    public function scrape_product_data($url) {
        
        // --- ۱. دریافت HTML صفحه ---
        $response = wp_remote_get($url, array(
            'timeout'    => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ));

        if (is_wp_error($response)) {
            return new WP_Error('http_error', sprintf(__('خطا در ارتباط: %s', RPI_TEXT_DOMAIN), $response->get_error_message()));
        }
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            return new WP_Error('http_code_error', sprintf(__('دیجی‌کالا با کد %d پاسخ داد (لینک %s).', RPI_TEXT_DOMAIN), $http_code, $url));
        }
        $this->html_body = wp_remote_retrieve_body($response);
        if (empty($this->html_body)) {
            return new WP_Error('empty_body', __('پاسخ دریافتی خالی بود.', RPI_TEXT_DOMAIN));
        }

        // --- ۲. آماده‌سازی پارسر (DOMDocument) ---
        libxml_use_internal_errors(true);
        $this->dom = new DOMDocument();
        $this->dom->loadHTML($this->html_body);
        libxml_clear_errors();
        $this->xpath = new DOMXPath($this->dom);

        // --- ۳. استخراج داده‌های کلیدی ---
        $this->json_ld_data = $this->_extract_json_ld();
        $this->preloaded_state = $this->_extract_preloaded_state();
        
        if ($this->preloaded_state === null && $this->json_ld_data === null) {
            return new WP_Error('no_data', __('هیچ داده ساختاریافته‌ای (JSON-LD یا Preloaded State) پیدا نشد. ساختار دیجی‌کالا احتمالاً تغییر کرده.', RPI_TEXT_DOMAIN));
        }

        // --- ۴. جمع‌آوری نهایی داده‌ها ---
        $scraped_data = array();
        
        // اولویت با preloaded_state است، اگر نبود از json_ld
        $main_product_data = $this->preloaded_state['product']['data'] ?? null;

        $scraped_data['title'] = $main_product_data['title_fa'] ?? $this->json_ld_data['name'] ?? '';
        if (empty($scraped_data['title'])) {
             return new WP_Error('no_title', __('عنوان محصول پیدا نشد.', RPI_TEXT_DOMAIN));
        }
        
        $scraped_data['sku'] = $main_product_data['dkp'] ? 'DKP-' . $main_product_data['dkp'] : ($this->json_ld_data['sku'] ?? 'RPI-' . rand(100000, 999999));
        
        // توضیحات معمولاً فقط در JSON-LD کامل است
        $scraped_data['description'] = $this->json_ld_data['description'] ?? ($main_product_data['review']['description'] ?? '');
        
        // توضیحات کوتاه (ویژگی‌های کلیدی)
        $scraped_data['short_description'] = $this->_scrape_short_description($main_product_data);
        
        $price_data = $this->_scrape_price($main_product_data);
        $scraped_data['regular_price'] = $price_data['regular']; // قیمت‌ها به ریال هستند
        $scraped_data['sale_price'] = $price_data['sale']; // قیمت‌ها به ریال هستند

        $scraped_data['images'] = $this->_scrape_images($main_product_data);
        $scraped_data['attributes'] = $this->_scrape_attributes($main_product_data); // مشخصات فنی
        $scraped_data['categories'] = $this->_scrape_categories($main_product_data);
        $scraped_data['brand'] = $main_product_data['brand']['title_fa'] ?? $this->json_ld_data['brand']['name'] ?? '';

        // استخراج متغیرها (رنگ‌ها، گارانتی‌ها)
        $scraped_data['variations'] = $this->_scrape_variations($main_product_data);

        if (empty($scraped_data['regular_price']) && !empty($scraped_data['variations'])) {
            $prices = wp_list_pluck($scraped_data['variations'], 'price');
            if (!empty($prices)) {
                $scraped_data['regular_price'] = min($prices); // قیمت "شروع از" (به ریال)
            }
        }
        
        return $scraped_data;
    }

    /**
     * متد کمکی: استخراج داده‌های JSON-LD از HTML.
     */
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
    
    /**
     * متد کمکی: استخراج داده‌های PRELOADED_STATE از HTML.
     */
    private function _extract_preloaded_state() {
        // این regex به دنبال آبجکت JSON بعد از window['__PRELOADED_STATE__'] = می‌گردد
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

    /**
     * استخراج توضیحات کوتاه (ویژگی‌های کلیدی)
     */
    private function _scrape_short_description($main_product_data) {
        if (isset($main_product_data['review']['attributes'])) {
            $html = '<ul>';
            foreach ($main_product_data['review']['attributes'] as $attr) {
                 $html .= '<li>' . esc_html($attr['title']) . ': ' . esc_html($attr['values'][0]) . '</li>';
            }
            $html .= '</ul>';
            return $html;
        }
        return '';
    }

    /**
     * استخراج قیمت (به ریال)
     */
    private function _scrape_price($main_product_data) {
        $prices = ['regular' => '', 'sale' => ''];
        
        // قیمت‌ها از preloaded_state بسیار دقیق‌تر هستند
        $default_variant = $this->preloaded_state['variants']['data'][$main_product_data['default_variant']] ?? null;
        
        if ($default_variant) {
            $prices['regular'] = $default_variant['price']['rrp_price']; // قیمت اصلی (ریال)
            $prices['sale'] = $default_variant['price']['selling_price']; // قیمت فروش (ریال)
            
            // اگر قیمت فروش با اصلی یکی بود، یعنی تخفیف ندارد
            if ($prices['regular'] == $prices['sale']) {
                $prices['sale'] = '';
            }
            return $prices;
        }

        // فال‌بک به JSON-LD (کمتر دقیق)
        if (isset($this->json_ld_data['offers'])) {
            $offers = $this->json_ld_data['offers'][0] ?? $this->json_ld_data['offers'];
            if (isset($offers['price'])) {
                $prices['regular'] = preg_replace("/[^0-9]/", "", $offers['price']);
            }
        }
        return $prices;
    }

    /**
     * استخراج تصاویر گالری
     */
    private function _scrape_images($main_product_data) {
        $images = array();
        
        // اولویت با preloaded_state
        if (isset($main_product_data['images']['gallery'])) {
            foreach ($main_product_data['images']['gallery'] as $img) {
                $images[] = $img['url'][0]; // [0] معمولاً URL اصلی است
            }
        }
        
        // فال‌بک به JSON-LD
        if (empty($images) && isset($this->json_ld_data['image'])) {
            $images = (array)$this->json_ld_data['image'];
        }
        
        // پاک‌سازی و حذف واترمارک
        $cleaned_images = array();
        foreach ($images as $img_url) {
            $cleaned_images[] = preg_replace('/\?.*/', '', $img_url); // حذف query string
        }
        return array_unique(array_filter($cleaned_images));
    }

    /**
     * استخراج مشخصات فنی (Attributes)
     */
    private function _scrape_attributes($main_product_data) {
        $attributes = array();
        if (isset($main_product_data['specifications'][0]['attributes'])) {
            foreach ($main_product_data['specifications'][0]['attributes'] as $attr) {
                $attributes[] = array(
                    'name' => sanitize_text_field($attr['title']),
                    'value' => implode(', ', $attr['values']), // مقادیر ممکن است آرایه باشند
                    'is_visible' => 1,
                    'is_variation' => 0
                );
            }
        }
        return $attributes;
    }

    /**
     * استخراج دسته‌بندی‌ها (Breadcrumbs)
     */
    private function _scrape_categories($main_product_data) {
        $categories = array();
        if (isset($main_product_data['category']['title_fa'])) {
             $categories[] = $main_product_data['category']['title_fa'];
        }
        // فال‌بک به بردکرامب (دقیق‌تر است)
        if (isset($this->preloaded_state['breadcrumbs']['data']['items'])) {
            $categories = array(); // پاک کردن قبلی
             foreach ($this->preloaded_state['breadcrumbs']['data']['items'] as $item) {
                 $categories[] = $item['title_fa'];
             }
        }
        return array_filter($categories);
    }

    /**
     * استخراج متغیرها (Variations)
     */
    private function _scrape_variations($main_product_data) {
        $variations = array();
        
        // منبع اصلی داده: preloaded_state.variants.data
        if (empty($this->preloaded_state['variants']['data'])) {
            return $variations; // محصول ساده است
        }
        
        $all_variants = $this->preloaded_state['variants']['data'];
        
        foreach ($all_variants as $variant_id => $variant) {
            
            $price_rial = $variant['price']['selling_price'];
            $sku = $variant['id'] ?? $variant_id;
            
            // ویژگی‌های این متغیر (مثلاً رنگ و گارانتی)
            $var_attributes = array();
            
            // ۱. استخراج رنگ
            if (isset($variant['color']['title'])) {
                $var_attributes[] = array('name' => 'رنگ', 'value' => $variant['color']['title']);
            }
            // ۲. استخراج گارانتی
            if (isset($variant['warranty']['title_fa'])) {
                 $var_attributes[] = array('name' => ' گارانتی', 'value' => $variant['warranty']['title_fa']);
            }
            // ... (می‌توان سایز و... را هم به همین شکل اضافه کرد)
            
            if (!empty($var_attributes)) {
                 $variations[] = array(
                     'sku' => 'DKP-VAR-' . $sku,
                     'price' => $price_rial, // به ریال، تبدیل در Importer
                     'attributes' => $var_attributes // آرایه‌ای از ویژگی‌ها
                 );
            }
        }

        return $variations;
    }
}