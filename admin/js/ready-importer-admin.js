/**
 * فایل جاوااسکریپت بخش ادمین افزونه Ready Importer
 *
 * این فایل مسئولیت مدیریت تعاملات کاربری (UI) و
 * ارسال درخواست‌های ایجکس (AJAX) به بک‌اند را بر عهده دارد.
 *
 * آبجکت rpi_ajax_object از طریق wp_localize_script در دسترس است
 * و شامل ajax_url و nonce می‌باشد.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        
        console.log('Ready Importer Admin JS Loaded.');
        
        // مثال: مدیریت کلیک روی دکمه "شروع درون‌ریزی"
        $('#rpi-start-import-button').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $form = $button.closest('form');
            var $linksTextarea = $form.find('#rpi-product-links');
            
            // ۱. غیرفعال کردن دکمه و نمایش لودینگ
            $button.prop('disabled', true).text(rpi_ajax_object.text.loading);
            
            // ۲. جمع‌آوری داده‌ها
            var links = $linksTextarea.val().split('\n').filter(link => link.trim() !== '');
            var category = $form.find('#rpi-product-category').val();
            
            if (links.length === 0) {
                alert('لطفاً حداقل یک لینک محصول وارد کنید.');
                $button.prop('disabled', false).text('شروع درون‌ریزی');
                return;
            }

            console.log('شروع فرآیند برای ' + links.length + ' لینک در دسته‌بندی ' + category);
            
            // ۳. ارسال درخواست ایجکس
            // (این بخش در مراحل بعدی و پس از ساخت کلاس Ajax تکمیل خواهد شد)
            
            /*
            $.ajax({
                url: rpi_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'rpi_start_scraping', // اکشنی که در Loader تعریف خواهیم کرد
                    nonce: rpi_ajax_object.nonce,
                    links: links,
                    category_id: category
                },
                success: function(response) {
                    if(response.success) {
                        console.log('فرآیند با موفقیت شروع شد:', response.data);
                        // TODO: نمایش نوار پیشرفت یا پیام موفقیت
                        $button.text('فرآیند آغاز شد');
                    } else {
                        console.error('خطا در شروع فرآیند:', response.data);
                        alert(response.data.message || rpi_ajax_object.text.error);
                        $button.prop('disabled', false).text('شروع درون‌ریزی');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('خطای ایجکس:', error);
                    alert(rpi_ajax_object.text.error);
                    $button.prop('disabled', false).text('شروع درون‌ریزی');
                }
            });
            */

            // شبیه‌سازی موقت برای نمایش
             setTimeout(function() {
                 $button.prop('disabled', false).text('شروع درون‌ریزی');
                 console.log('شبیه‌سازی ایجکس تمام شد.');
             }, 2000);

        });

    }); // انتهای document.ready

})(jQuery);