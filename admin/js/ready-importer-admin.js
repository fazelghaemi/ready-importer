/**
 * فایل جاوااسکریپت بخش ادمین افزونه Ready Importer
 *
 * *تغییرات این نسخه: (رفع باگ alert)*
 * - جایگزینی تمام `alert()` ها با تابع `rpiShowMessage()`.
 * - اضافه شدن تابع `rpiShowMessage()` برای نمایش پیام در UI.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/admin/js
 * @author     Ready Studio
 */
(function($) {
    'use strict';

    // متغیرهای سراسری
    var rpiQueue = []; 
    var rpiSettings = {}; 
    var rpiIsProcessing = false; 
    var $logContainer; 
    var $startButton;
    var $progressBarContainer;
    var $backButton;
    var $continueButton;
    var $messageContainer; // (جدید)

    /**
     * (جدید) تابع کمکی برای نمایش پیام‌ها به جای alert()
     * @param {string} message پیام
     * @param {string} type نوع پیام ('error' یا 'success')
     */
    function rpiShowMessage(message, type = 'error') {
        var icon_svg = '<svg class="rpi-message-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-7-8a7 7 0 1114 0 7 7 0 01-14 0zm8-4a1 1 0 00-1.414 1.414L9 9.586l-.707-.707a1 1 0 00-1.414 1.414l1.414 1.414a1 1 0 001.414 0l2.828-2.828a1 1 0 000-1.414L11 6.586l-.293.293z" clip-rule="evenodd"></path></svg>';
        if (type === 'error') {
            icon_svg = '<svg class="rpi-message-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-7-8a7 7 0 1114 0 7 7 0 01-14 0zm8-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 102 0V6zm-1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path></svg>';
        }
        
        var messageHtml = `
            <div class="rpi-message rpi-message--${type}">
                ${icon_svg}
                <span>${message}</span>
            </div>
        `;
        
        $messageContainer.html(messageHtml).slideDown(300);
        
        // مخفی کردن خودکار پیام بعد از ۵ ثانیه
        setTimeout(function() {
            $messageContainer.slideUp(300);
        }, 5000);
    }
    /**
     * (جدید) تابع کمکی برای مخفی کردن پیام
     */
    function rpiHideMessage() {
         $messageContainer.slideUp(200);
    }


    $(document).ready(function() {
        
        console.log('Ready Importer Admin JS Loaded (v3.2 - Alert Fix).');

        // تعریف متغیرهای DOM
        $logContainer = $('#rpi-log-container');
        $startButton = $('#rpi-start-import-button');
        $progressBarContainer = $('#rpi-progress-bar-container');
        $backButton = $('#rpi-back-button');
        $continueButton = $('#rpi-continue-button');
        $messageContainer = $('#rpi-message-container'); // (جدید)

        // --- ۱. مدیریت جابجایی بین مراحل (Stepper) ---
        $continueButton.on('click', function() {
            rpiHideMessage(); // (جدید) مخفی کردن پیام‌های قبلی
            var $linksTextarea = $('#rpi-product-links');
            var linksRaw = $linksTextarea.val();
            
            // (رفع باگ) استفاده از rpiShowMessage به جای alert
            if (linksRaw.trim() === '') {
                rpiShowMessage('لطفاً حداقل یک لینک محصول یا دسته‌بندی وارد کنید.');
                $linksTextarea.focus(); return;
            }
            var links = linksRaw.split('\n').map(link => link.trim()).filter(link => link !== '' && link.startsWith('http'));
            
            // (رفع باگ) استفاده از rpiShowMessage به جای alert
            if (links.length === 0) {
                 rpiShowMessage('هیچ لینک معتبری (شروع با http) پیدا نشد. لطفاً لینک‌ها را بررسی کنید.');
                $linksTextarea.focus(); return;
            }
            
            $('#rpi-links-storage').val(JSON.stringify(links)); 
            
            // جابجایی UI
            $('#rpi-step-1-content').fadeOut(300, function() { $('#rpi-step-2-content').fadeIn(300); });
            $('.rpi-stepper__item').removeClass('rpi-stepper__item--active');
            $('.rpi-stepper__item').eq(1).addClass('rpi-stepper__item--active');
            $backButton.show(); $(this).hide(); $startButton.show();
        });

        $backButton.on('click', function() {
            if (rpiIsProcessing) return; 
            rpiHideMessage(); // (جدید)
            $('#rpi-step-2-content').fadeOut(300, function() { $('#rpi-step-1-content').fadeIn(300); });
            $('.rpi-stepper__item').removeClass('rpi-stepper__item--active');
            $('.rpi-stepper__item').eq(0).addClass('rpi-stepper__item--active');
            $(this).hide(); $continueButton.show(); $startButton.hide();
        });


        // --- ۲. مدیریت فرآیند اصلی درون‌ریزی (شروع صف) ---
        
        $startButton.on('click', function(e) {
            e.preventDefault();
            rpiHideMessage(); // (جدید)
            
            if (rpiIsProcessing) {
                // (رفع باگ) استفاده از rpiShowMessage به جای alert
                rpiShowMessage('فرآیند در حال اجرا است. لطفاً صبر کنید...');
                return;
            }

            // ۱. جمع‌آوری نهایی داده‌ها
            var linksJson = $('#rpi-links-storage').val();
            var categoryId = $('#rpi-product-category').val();
            var productStatus = $('#rpi-product-status').val();

            // ۲. اعتبارسنجی نهایی
            // (رفع باگ) استفاده از rpiShowMessage به جای alert
            if (categoryId === '0' || categoryId === null || categoryId === '') {
                rpiShowMessage('لطفاً یک دسته‌بندی مقصد معتبر انتخاب کنید.');
                $('#rpi-product-category').focus();
                return;
            }
            
            var rawLinks = [];
            try { rawLinks = JSON.parse(linksJson); } catch (e) {
                rpiShowMessage('خطا در خواندن لیست لینک‌ها. لطفاً به مرحله 1 بازگردید.'); return;
            }
            if (rawLinks.length === 0) {
                 rpiShowMessage('لیست لینک‌ها خالی است. لطفاً به مرحله 1 بازگردید.'); return;
            }

            // ۳. آماده‌سازی UI
            rpiIsProcessing = true;
            $logContainer.empty().html('<p class="log-info">در حال ایجاد وظیفه و پردازش لینک‌های دسته‌بندی... (این مرحله ممکن است چند ثانیه طول بکشد)</p>');
            $startButton.prop('disabled', true).text(rpi_ajax_object.text.loading);
            $backButton.prop('disabled', true);
            $progressBarContainer.hide();

            // --- ۴. مرحله ۱: ایجاد وظیفه و تفکیک لینک‌ها ---
            $.ajax({
                url: rpi_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'rpi_create_task',
                    nonce: rpi_ajax_object.nonce,
                    links: rawLinks
                },
                success: function(response) {
                    if (response.success) {
                        rpiQueue = response.data.links; 
                        rpiSettings = {
                            task_id: response.data.task_id,
                            category_id: categoryId,
                            product_status: productStatus,
                            total_links: response.data.count,
                            processed_count: 0,
                            success_count: 0,
                            error_count: 0
                        };
                        
                        $logContainer.empty();
                        addLogMessage('وظیفه ' + rpiSettings.task_id + ' ایجاد شد. تعداد ' + rpiSettings.total_links + ' محصول آماده درون‌ریزی است.', 'success');
                        
                        if(response.data.warnings && response.data.warnings.length > 0) {
                            response.data.warnings.forEach(function(warning) {
                                addLogMessage(warning, 'error');
                            });
                        }
                        
                        $progressBarContainer.show();
                        updateProgressBar();
                        processNextQueueItem(); 
                    } else {
                        // (جدید) نمایش خطا در UI
                        addLogMessage('خطای حیاتی در ایجاد وظیفه: ' + response.data.message, 'error');
                        rpiShowMessage('خطای حیاتی: ' + response.data.message, 'error');
                        rpiIsProcessing = false;
                        $startButton.prop('disabled', false).text('شروع درون‌ریزی نهایی');
                        $backButton.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    // (جدید) نمایش خطا در UI
                    var errorMsg = xhr.responseJSON ? xhr.responseJSON.data.message : 'خطای ناشناخته سرور.';
                    addLogMessage('خطای سرور در ایجاد وظیفه: ' + errorMsg, 'error');
                    rpiShowMessage('خطای سرور: ' + errorMsg, 'error');
                    rpiIsProcessing = false;
                    $startButton.prop('disabled', false).text('شروع درون‌ریزی نهایی');
                    $backButton.prop('disabled', false);
                }
            });
        });

    }); // --- انتهای document.ready ---

    /**
     * متد اصلی پردازش صف
     * (کد از مرحله قبل - بدون تغییر)
     */
    function processNextQueueItem() {
        if (rpiQueue.length === 0) {
            // --- صف تمام شد ---
            rpiIsProcessing = false;
            $startButton.prop('disabled', false).text('فرآیند تکمیل شد');
            $backButton.prop('disabled', false);
            
            var finalMessage = '<strong>فرآیند تکمیل شد.</strong> نتایج: ' + 
                rpiSettings.success_count + ' موفق، ' + 
                rpiSettings.error_count + ' ناموفق.';
            
            addLogMessage(finalMessage, 'success');
            
            // (جدید) نمایش پیام نهایی در بالای صفحه
            rpiShowMessage(finalMessage, 'success');
            
            $.ajax({
                url: rpi_ajax_object.ajax_url, type: 'POST',
                data: {
                    action: 'rpi_complete_task',
                    nonce: rpi_ajax_object.nonce,
                    task_id: rpiSettings.task_id
                }
            });
            return;
        }

        var linkToProcess = rpiQueue.shift();
        addLogMessage('درحال پردازش لینک: ' + linkToProcess, 'info');

        // ارسال درخواست ایجکس
        $.ajax({
            url: rpi_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'rpi_process_single_link',
                nonce: rpi_ajax_object.nonce,
                task_id: rpiSettings.task_id, 
                link: linkToProcess,
                category_id: rpiSettings.category_id,
                product_status: rpiSettings.product_status
            },
            success: function(response) {
                if (response.success) {
                    rpiSettings.processed_count++;
                    rpiSettings.success_count++;
                    addLogMessage(response.data.message, 'success');
                } else {
                    rpiSettings.processed_count++;
                    rpiSettings.error_count++;
                    addLogMessage(response.data.message, 'error');
                }
            },
            error: function(xhr) {
                rpiSettings.processed_count++;
                rpiSettings.error_count++;
                var errorMessage = xhr.responseJSON ? xhr.responseJSON.data.message : (rpi_ajax_object.text.error);
                addLogMessage('خطای سرور: ' + errorMessage, 'error');
            },
            complete: function() {
                updateProgressBar();
                setTimeout(processNextQueueItem, 500); // 500 میلی‌ثانیه
            }
        });
    }

    /**
     * متد کمکی: اضافه کردن پیام به لاگ
     * (کد از مرحله قبل - بدون تغییر)
     */
    function addLogMessage(message, type) {
        var typeClass = 'log-info';
        if (type === 'success') typeClass = 'log-success';
        if (type === 'error') typeClass = 'log-error';
        var logTime = new Date().toLocaleTimeString();
        $logContainer.append('<div class="rpi-log-item ' + typeClass + '"><span class="log-icon"></span><span class="log-time">['+logTime+']</span> ' + message + '</div>');
        $logContainer.scrollTop($logContainer[0].scrollHeight);
    }

    /**
     * متد کمکی: به‌روزرسانی نوار پیشرفت
     * (کد از مرحله قبل - بدون تغییر)
     */
    function updateProgressBar() {
        var percent = 0;
        if (rpiSettings.total_links > 0) {
            percent = (rpiSettings.processed_count / rpiSettings.total_links) * 100;
        }
        if ($('#rpi-progress-text').length === 0) {
             $progressBarContainer.html('<div id="rpi-progress-text" style="font-weight:bold; margin-bottom: 5px;"></div><div id="rpi-progress-bar-bg" style="background: #e0e0e0; border-radius: 4px; overflow: hidden;"><div id="rpi-progress-bar-inner" style="width: 0%; background: var(--rpi-color-aqua); height: 10px; transition: width 0.3s ease;"></div></div>');
        }
        $('#rpi-progress-bar-inner').css('width', percent + '%');
        $('#rpi-progress-text').text(
            'پردازش ' + rpiSettings.processed_count + ' از ' + rpiSettings.total_links + ' (' + Math.round(percent) + '%)'
        );
    }

})(jQuery);