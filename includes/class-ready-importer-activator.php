<?php

/**
 * کلاس فعال‌ساز افزونه
 *
 * این کلاس مسئولیت اجرای کدهایی را دارد که فقط *یک بار*
 * در هنگام فعال‌سازی افزونه باید اجرا شوند.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/includes
 * @author     Ready Studio
 */
class Ready_Importer_Activator {

    /**
     * متد اصلی فعال‌سازی.
     *
     * این متد استاتیک توسط هوک register_activation_hook فراخوانی می‌شود.
     * در حال حاضر، این متد کارهای زیر را انجام می‌دهد:
     * 1. تنظیم یک آپشن (option) پیش‌فرض در دیتابیس (مثلاً برای نسخه).
     * 2. پاک کردن (Flush) قوانین بازنویسی (Rewrite Rules) وردپرس.
     *
     * کارهای دیگری مانند ساخت جداول سفارشی دیتابیس نیز می‌توانند در اینجا اضافه شوند.
     */
    public static function activate() {
        
        // ۱. تنظیم گزینه‌های پیش‌فرض
        // می‌توانیم نسخه فعلی افزونه را در دیتابیس ذخیره کنیم
        // تا در به‌روزرسانی‌های آینده برای اجرای کدهای مهاجرت (Migration) از آن استفاده کنیم.
        if (get_option('ready_importer_version') === false) {
            add_option('ready_importer_version', RPI_VERSION);
        } else {
            update_option('ready_importer_version', RPI_VERSION);
        }

        // ۲. پاک کردن قوانین بازنویسی
        // اگر افزونه شما نوع پست سفارشی (Custom Post Type) یا تاکسونومی سفارشی
        // اضافه می‌کرد، این کار ضروری بود. اما انجام آن ضرری ندارد.
        flush_rewrite_rules();
        
        // ۳. (اختیاری) تنظیم یک وظیفه زمان‌بندی شده (Cron Job) پیش‌فرض
        // مثال: اگر بخواهیم یک کرون برای به‌روزرسانی خودکار قیمت‌ها داشته باشیم.
        // فعلاً آن را غیرفعال نگه می‌داریم تا بعداً در بخش تنظیمات فعال شود.
        /*
        if (!wp_next_scheduled('rpi_daily_price_update_cron')) {
            wp_schedule_event(time(), 'daily', 'rpi_daily_price_update_cron');
        }
        */
    }
}