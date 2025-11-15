/**
 * فایل جاوااسکریپت بخش تنظیمات Ready Importer
 *
 * --- آپدیت اساسی (فاز ۲) ---
 *
 * این فایل مسئولیت مدیریت UI فیلدهای تکرارشونده (Repeater)
 * برای قوانین قیمت و جستجو/جایگزینی را بر عهده دارد.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/admin/js
 * @author     Ready Studio
 */
(function($) {
    'use strict';

    /**
     * متد کمکی برای مدیریت فیلدهای تکرارشونده
     * @param {string} wrapperId ID المان والد (wrapper)
     * @param {string} templateId ID المان الگو (template)
     * @param {string} addButtonId ID دکمه "افزودن"
     */
    function initializeRepeater(wrapperId, templateId, addButtonId) {
        
        var $wrapper = $(wrapperId);
        var $template = $(templateId);
        var $addButton = $(addButtonId);

        // بررسی وجود الگو برای جلوگیری از خطای JS
        if ($template.length === 0) {
            console.error('Ready Importer Error: الگوی تکرارشونده پیدا نشد:', templateId);
            return;
        }

        // کلیک روی دکمه "افزودن"
        $addButton.on('click', function() {
            // ۱. الگو (template) را کپی کن
            var $newRow = $template.clone();
            
            // ۲. کلاس الگو را حذف و ID را پاک کن تا قابل استفاده باشد
            $newRow.removeClass('rpi-repeater-template').removeAttr('id');
            
            // ۳. ایندکس جدید را محاسبه کن (بر اساس timestamp برای یونیک بودن)
            // این روش از ایجاد ایندکس‌های تکراری، حتی پس از حذف ردیف‌ها، جلوگیری می‌کند.
            var newIndex = new Date().getTime();
            
            // ۴. ویژگی 'name' فیلدهای input/select را به‌روز کن
            $newRow.find('input, select').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    // جایگزینی پلیس‌هولدر __INDEX__ با ایندکس یونیک
                    $(this).attr('name', name.replace('__INDEX__', newIndex));
                }
                // فیلدها را فعال کن (اگر در الگو disabled بودند)
                $(this).prop('disabled', false);
            });
            
            // ۵. ردیف جدید را به انتهای wrapper اضافه کن
            $wrapper.append($newRow);
        });

        // کلیک روی دکمه "حذف ردیف" (Event Delegation)
        // ما از on() روی والد استفاده می‌کنیم تا ردیف‌های جدیدی که به صورت پویا اضافه می‌شوند را هم پشتیبانی کند
        $wrapper.on('click', '.rpi-button-remove-row', function() {
            // اگر این تنها ردیف باقی‌مانده است (به جز هدر و الگو)، آن را حذف نکن، فقط خالی کن
            if ($wrapper.find('.rpi-repeater-row').not('.rpi-repeater-header, .rpi-repeater-template').length <= 1) {
                $(this).closest('.rpi-repeater-row').find('input[type="text"], input[type="number"]').val('');
            } else {
                // در غیر این صورت، ردیف را به طور کامل حذف کن
                $(this).closest('.rpi-repeater-row').remove();
            }
        });
    }

    // اجرای کد پس از بارگذاری کامل DOM
    $(document).ready(function() {
        
        console.log('Ready Importer Settings JS Loaded (v2 - Repeater Enabled).');

        // --- ۱. فعال‌سازی قوانین قیمت (Price Rules) ---
        initializeRepeater(
            '#rpi-price-rules-wrapper',      // ID والد
            '#rpi-price-rule-template',      // ID الگو
            '#rpi-add-price-rule'            // ID دکمه افزودن
        );

        // --- ۲. فعال‌سازی قوانین جستجو/جایگزینی (Find/Replace Rules) ---
        initializeRepeater(
            '#rpi-find-replace-wrapper',     // ID والد
            '#rpi-find-replace-template',    // ID الگو
            '#rpi-add-find-replace-rule'     // ID دکمه افزودن
        );

    }); // انتهای document.ready

})(jQuery);