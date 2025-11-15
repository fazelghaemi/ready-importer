<?php
/**
 * فایل تست واحد (Unit Test) ساده
 *
 * --- (جدید) ---
 *
 * این فایل برای تست منطق‌های خالص (Pure Functions) در کلاس Importer است.
 * برای اجرا، این فایل را در پوشه /tests/ در ریشه افزونه قرار دهید
 * و سپس فایل Importer را در خط 30 include کنید.
 *
 * اجرا: (از طریق مرورگر یا CLI)
 * $ php tests/unit-tests.php
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/tests
 * @author     Ready Studio
 */

// --- ۱. شبیه‌سازی محیط وردپرس ---
// ما از بارگذاری کامل وردپرس (wp-load.php) خودداری می‌کنیم تا تست سبک باشد

// شبیه‌سازی توابع ترجمه
if (!function_exists('__')) {
    function __($text, $domain) {
        return $text;
    }
}
// شبیه‌سازی توابع پاک‌سازی (Sanitization)
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post($str) {
        // شبیه‌سازی ساده: اجازه دادن به تگ‌های <p> <a> <strong>
        return strip_tags($str, '<p><a><strong><ul><li>');
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

// شبیه‌سازی تابع get_option برای بازگرداندن تنظیمات تست ما
global $RPI_TEST_SETTINGS;
$RPI_TEST_SETTINGS = array(); // پیش‌فرض خالی

if (!function_exists('get_option')) {
    function get_option($option_name, $default = false) {
        global $RPI_TEST_SETTINGS;
        if ($option_name === 'rpi_settings') {
            return $RPI_TEST_SETTINGS;
        }
        return $default;
    }
}

// --- ۲. بارگذاری کلاس مورد تست ---
// مسیر را بر اساس محل این فایل تنظیم کنید
require_once dirname(__FILE__) . '/../includes/class-ready-importer-importer.php';


// --- ۳. تعریف ابزارهای تست ---
$tests_passed = 0;
$tests_failed = 0;

function assert_equals($expected, $actual, $test_name) {
    global $tests_passed, $tests_failed;
    if ($expected == $actual) {
        $tests_passed++;
        echo "<p style='color:green; margin: 2px 0;'>✔ PASS: $test_name</p>";
    } else {
        $tests_failed++;
        echo "<p style='color:red; margin: 2px 0;'>✖ FAIL: $test_name</p>";
        echo "<div style='background:#fff0f0; border:1px solid red; padding: 5px; margin-bottom: 10px;'>";
        echo "  <strong>Expected:</strong> " . htmlspecialchars(var_export($expected, true)) . "<br>";
        echo "  <strong>Actual:</strong>   " . htmlspecialchars(var_export($actual, true));
        echo "</div>";
    }
}

echo '<!DOCTYPE html><html dir="ltr"><body style="font-family: sans-serif; background: #f9f9f9; padding: 10px;">';
echo '<h1>تست واحد Ready_Importer_Importer</h1>';

// --- ۴. اجرای تست‌ها ---

// --- تست ۱: قوانین قیمت‌گذاری (Price Rules) ---
echo '<h2>--- تست قوانین قیمت (apply_price_rules_and_rounding) ---</h2>';

// تنظیمات تست
$RPI_TEST_SETTINGS = array(
    'price_rules' => array(
        array('min_price' => '0', 'max_price' => '100000', 'type' => 'percent', 'value' => '20'), // قانون ۱: زیر ۱۰۰هزار، ۲۰ درصد
        array('min_price' => '100001', 'max_price' => '500000', 'type' => 'fixed', 'value' => '25000'), // قانون ۲: بین ۱۰۰ تا ۵۰۰، ۲۵هزار ثابت
    ),
    'round_prices' => '1000' // گرد کردن به ۱۰۰۰
);

// ساخت نمونه جدید از Importer (تا تنظیمات جدید را بخواند)
$importer = new Ready_Importer_Importer();

// تست‌ها
assert_equals('60000', $importer->apply_price_rules_and_rounding('50000'), 'قانون ۱ (درصد): ۵۰,۰۰۰ + ۲۰% = ۶۰,۰۰۰');
assert_equals('150000', $importer->apply_price_rules_and_rounding('125000'), 'قانون ۲ (ثابت): ۱۲۵,۰۰۰ + ۲۵,۰۰۰ = ۱۵۰,۰۰۰');
assert_equals('1000000', $importer->apply_price_rules_and_rounding('1000000'), 'خارج از بازه: بدون تغییر');
assert_equals('605000', $importer->apply_price_rules_and_rounding('605000'), 'خارج از بازه ۲: بدون تغییر');
assert_equals('101000', $importer->apply_price_rules_and_rounding('100100'), 'قانون ۲ (ثابت) + گرد کردن: ۱۰۰,۱۰۰ + ۲۵,۰۰۰ = ۱۲۵,۱۰۰ -> ۱۲۶,۰۰۰');
assert_equals('126000', $importer->apply_price_rules_and_rounding('100100'), 'تست گرد کردن (۱۰۰,۱۰۰ + ۲۵۰۰۰ = ۱۲۵,۱۰۰ -> ۱۲۶,۰۰۰)');
assert_equals('60000', $importer->apply_price_rules_and_rounding('49999'), 'تست گرد کردن (۴۹,۹۹۹ + ۲۰% = ۵۹,۹۹۸.۸ -> ۶۰,۰۰۰)');
assert_equals('', $importer->apply_price_rules_and_rounding(''), 'ورودی خالی');
assert_equals('abc', $importer->apply_price_rules_and_rounding('abc'), 'ورودی غیر عددی');


// --- تست ۲: قوانین محتوا (Content Rules) و امنیت XSS ---
echo '<h2>--- تست قوانین محتوا (apply_content_rules) و پاک‌سازی (Sanitization) ---</h2>';

// تنظیمات تست
$RPI_TEST_SETTINGS = array(
    'find_replace_rules' => array(
        array('find' => 'دیجی‌کالا', 'replace' => 'فروشگاه ما', 'area' => 'all'),
        array('find' => 'گارانتی اصلی', 'replace' => 'گارانتی شرکتی', 'area' => 'title'),
        array('find' => 'توضیحات', 'replace' => 'بررسی تخصصی', 'area' => 'desc'),
    )
);

$importer = new Ready_Importer_Importer();

// داده‌های خام اسکرپ شده
$test_data = array(
    'title' => 'گوشی A5 (گارانتی اصلی) - دیجی‌کالا',
    'description' => '<p>توضیحات کامل محصول دیجی‌کالا.</p> <strong>عالی!</strong>',
    'short_description' => 'توضیحات کوتاه دیجی‌کالا',
);
$test_data_xss = array(
    'title' => 'محصول <script>alert(1)</script>',
    'description' => '<p>تست XSS <img src=x onerror=alert(2)></p> <a href="javascript:alert(3)">لینک بد</a>',
    'short_description' => 'توضیحات کوتاه <svg/onload=alert(4)>',
);

// --- تست ۲.۱: تست قوانین جایگزینی ---
$title_result = $importer->apply_content_rules($test_data['title'], 'title');
assert_equals('گوشی A5 (گارانتی شرکتی) - فروشگاه ما', $title_result, 'جایگزینی عنوان (قانون title و all)');

$desc_result = $importer->apply_content_rules($test_data['description'], 'desc');
assert_equals('<p>بررسی تخصصی کامل محصول فروشگاه ما.</p> <strong>عالی!</strong>', $desc_result, 'جایگزینی توضیحات (قانون desc و all)');

$short_desc_result = $importer->apply_content_rules($test_data['short_description'], 'title');
assert_equals('توضیحات کوتاه دیجی‌کالا', $short_desc_result, 'جایگزینی (قانون اعمال نمی‌شود)');


// --- تست ۲.۲: تست پاک‌سازی (Sanitization) نهایی ---
// (شبیه‌سازی متد import_product)
$raw_title = $importer->apply_content_rules($test_data_xss['title'], 'title');
$final_title = sanitize_text_field($raw_title);
assert_equals('محصول', $final_title, 'پاک‌سازی XSS عنوان (حذف تگ script)');

$raw_desc = $importer->apply_content_rules($test_data_xss['description'], 'desc');
$final_desc = wp_kses_post($raw_desc);
assert_equals('<p>تست XSS <img> <a>لینک بد</a></p>', $final_desc, 'پاک‌سازی XSS توضیحات (حذف onerror و javascript:)');

$raw_short_desc = $importer->apply_content_rules($test_data_xss['short_description'], 'desc');
$final_short_desc = wp_kses_post($raw_short_desc);
assert_equals('توضیحات کوتاه', $final_short_desc, 'پاک‌سازی XSS توضیحات کوتاه (حذف تگ svg)');


// --- ۵. نمایش نتایج نهایی ---
echo '<h2>--- نتایج نهایی ---</h2>';
if ($tests_failed == 0) {
    echo "<h3 style='color:green;'>✔✔✔ تمام $tests_passed تست با موفقیت انجام شد. ✔✔✔</h3>";
} else {
    echo "<h3 style='color:red;'>✖✖✖ $tests_failed تست از $tests_passed تست شکست خورد. ✖✖✖</h3>";
}
echo '</body></html>';