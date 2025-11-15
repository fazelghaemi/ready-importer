<?php

/**
 * کلاس غیرفعال‌ساز افزونه
 *
 * این کلاس مسئولیت اجرای کدهایی را دارد که فقط *یک بار*
 * در هنگام غیرفعال‌سازی افزونه باید اجرا شوند.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/includes
 * @author     Ready Studio
 */
class Ready_Importer_Deactivator {

    /**
     * متد اصلی غیرفعال‌سازی.
     *
     * این متد استاتیک توسط هوک register_deactivation_hook فراخوانی می‌شود.
     * معمولاً کارهای پاکسازی در اینجا انجام می‌شود.
     *
     * 1. پاک کردن وظایف زمان‌بندی شده (Cron Jobs) که افزونه اضافه کرده.
     * 2. پاک کردن (Flush) قوانین بازنویسی (Rewrite Rules) وردپرس.
     */
    public static function deactivate() {
        
        // ۱. پاک کردن وظایف زمان‌بندی شده
        // اگر کرون جابی (مثل مثال فایل Activator) تنظیم کرده باشیم،
        // باید در زمان غیرفعال‌سازی آن را پاک کنیم تا در پس‌زمینه سرور باقی نماند.
        /*
        $timestamp = wp_next_scheduled('rpi_daily_price_update_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'rpi_daily_price_update_cron');
        }
        */

        // ۲. پاک کردن قوانین بازنویسی
        flush_rewrite_rules();
    }
}