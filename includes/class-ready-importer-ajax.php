<?php
/**
 * کلاس مدیریت درخواست‌های AJAX افزونه
 *
 * *تغییرات این نسخه: (رفع خطای Fatal Error 500)*
 * - (رفع باگ): انتقال ساخت نمونه (new) از Scraper و Importer
 * از __construct به *داخل* متدهای handle_*
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/includes
 * @author     Ready Studio
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ready_Importer_Ajax {

    // (حذف شد) private $scraper;
    // (حذف شد) private $importer;

    /**
     * سازنده کلاس
     * --- (آپدیت حیاتی) ---
     * سازنده اکنون خالی است تا از خطای Fatal Error در
     * بارگذاری اولیه AJAX جلوگیری شود.
     */
    public function __construct() {
        // خالی!
    }

    /**
     * هوک ایجکس: ایجاد وظیفه و تفکیک لینک‌ها
     * --- (آپدیت حیاتی) ---
     */
    public function handle_create_task() {
        try {
            // ۱. بررسی امنیتی و دسترسی
            check_ajax_referer('rpi_importer_nonce', 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('دسترسی غیر مجاز.', RPI_TEXT_DOMAIN)), 403);
            }

            // --- (جدید) ساخت نمونه در زمان اجرا ---
            $scraper = new Ready_Importer_Scraper();

            // ۲. دریافت لینک‌های خام
            $links = isset($_POST['links']) && is_array($_POST['links'])
                     ? array_map('sanitize_text_field', wp_unslash($_POST['links']))
                     : array();
            if (empty($links)) {
                wp_send_json_error(array('message' => __('هیچ لینکی ارسال نشد.', RPI_TEXT_DOMAIN)), 400);
            }

            // ۳. ایجاد پست CPT "وظیفه"
            $task_title = sprintf(__('وظیفه درون‌ریزی در %s', RPI_TEXT_DOMAIN), wp_date('Y/m/d H:i:s'));
            $task_id = wp_insert_post(array(
                'post_title'   => $task_title,
                'post_type'    => 'rpi_import_task',
                'post_status'  => 'rpi-running',
                'post_author'  => get_current_user_id(),
            ));
            if (is_wp_error($task_id)) {
                wp_send_json_error(array('message' => __('خطا در ایجاد وظیفه در دیتابیس.', RPI_TEXT_DOMAIN)), 500);
            }

            $this->append_log_to_task($task_id, 'info', sprintf(__('وظیفه %d ایجاد شد. در حال تفکیک لینک‌ها...', RPI_TEXT_DOMAIN), $task_id));

            // ۴. تفکیک لینک‌ها
            $final_links = array();
            $error_messages = array();
            foreach ($links as $link) {
                if (empty($link)) continue;
                
                if ($scraper->is_category_link($link)) {
                    $product_links = $scraper->scrape_category_page($link);
                    if (is_wp_error($product_links)) {
                        throw new Exception($product_links->get_error_message());
                    }
                    if (!empty($product_links)) {
                        $final_links = array_merge($final_links, $product_links);
                        $this->append_log_to_task($task_id, 'info', sprintf(__('%d محصول از دسته‌بندی %s یافت شد.', RPI_TEXT_DOMAIN), count($product_links), $link));
                    }
                } elseif ($scraper->is_product_link($link)) {
                    $final_links[] = $link;
                } else {
                    throw new Exception(sprintf(__('لینک نادیده گرفته شد (معتبر نیست): %s', RPI_TEXT_DOMAIN), $link));
                }
            }
            
            $final_links = array_unique($final_links);
            $total_count = count($final_links);

            if ($total_count === 0) {
                 $error_msg = !empty($error_messages) ? implode("\n", $error_messages) : __('هیچ لینک محصول معتبری یافت نشد.', RPI_TEXT_DOMAIN);
                 $this->append_log_to_task($task_id, 'error', $error_msg);
                 wp_update_post(array('ID' => $task_id, 'post_status' => 'rpi-complete'));
                 wp_send_json_error(array('message' => $error_msg), 500);
            }

            // ۵. ذخیره متادیتای وظیفه
            update_post_meta($task_id, '_rpi_total_count', $total_count);
            update_post_meta($task_id, '_rpi_success_count', 0);
            update_post_meta($task_id, '_rpi_error_count', 0);
            $this->append_log_to_task($task_id, 'success', sprintf(__('تفکیک کامل شد. %d محصول در صف قرار گرفت.', RPI_TEXT_DOMAIN), $total_count));

            // ۶. ارسال پاسخ
            wp_send_json_success(array(
                'task_id'  => $task_id,
                'links'    => array_values($final_links),
                'count'    => $total_count,
                'warnings' => $error_messages
            ));

        } catch (Throwable $t) {
            // دریافت هرگونه خطای Fatal (مثل Class not found)
            $error_message = sprintf(
                __('خطای Fatal سرور (500): %s در فایل %s (خط %s)', RPI_TEXT_DOMAIN),
                $t->getMessage(), basename($t->getFile()), $t->getLine()
            );
            if (isset($task_id) && is_numeric($task_id) && $task_id > 0) {
                $this->append_log_to_task($task_id, 'error', $error_message);
                wp_update_post(array('ID' => $task_id, 'post_status' => 'rpi-complete'));
            }
            wp_send_json_error(array('message' => $error_message), 500);
        }
    }


    /**
     * هوک ایجکس: پردازش *یک* لینک محصول
     * --- (آپدیت حیاتی) ---
     */
    public function handle_process_single_link() {
        
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0; // (اول task_id را بگیر)

        try {
            check_ajax_referer('rpi_importer_nonce', 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('دسترسی غیر مجاز.', RPI_TEXT_DOMAIN)), 403);
            }

            // --- (جدید) ساخت نمونه در زمان اجرا ---
            $scraper = new Ready_Importer_Scraper();
            $importer = new Ready_Importer_Importer();

            $link = isset($_POST['link']) ? sanitize_text_field(wp_unslash($_POST['link'])) : '';
            $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
            $product_status = isset($_POST['product_status']) ? sanitize_key($_POST['product_status']) : 'draft';

            if ($task_id === 0) {
                wp_send_json_error(array('message' => __('خطای داخلی: ID وظیفه (task_id) دریافت نشد.', RPI_TEXT_DOMAIN)), 400);
            }

            $log_message = '';
            $log_type = 'info';

            try {
                if (empty($link) || !$scraper->is_product_link($link)) {
                    throw new Exception(sprintf(__('لینک محصول نامعتبر است: %s', RPI_TEXT_DOMAIN), $link));
                }
                if ($category_id === 0) {
                    throw new Exception(__('دسته‌بندی مقصد انتخاب نشده است.', RPI_TEXT_DOMAIN));
                }
                
                $scraped_data = $scraper->scrape_product_data($link);
                if (is_wp_error($scraped_data)) throw new Exception($scraped_data->get_error_message());

                $import_result = $importer->import_product($scraped_data, $category_id, $product_status);
                if (is_wp_error($import_result)) throw new Exception($import_result->get_error_message());
                
                $log_message = $import_result['message'];
                $log_type = 'success';
                $this->increment_task_meta($task_id, '_rpi_success_count');
                
                wp_send_json_success(array(
                    'message' => $log_message,
                    'product_id' => $import_result['product_id']
                ));

            } catch (Exception $e) {
                $log_message = sprintf(__('خطا در %s: %s', RPI_TEXT_DOMAIN), $link, $e->getMessage());
                $log_type = 'error';
                $this->increment_task_meta($task_id, '_rpi_error_count');
                wp_send_json_error(array('message' => $log_message), 500);

            } finally {
                $this->append_log_to_task($task_id, $log_type, $log_message);
            }

        } catch (Throwable $t) {
            // (جدید) دریافت خطاهای Fatal در پردازش تک لینک
             $error_message = sprintf(
                __('خطای Fatal سرور (500): %s در فایل %s (خط %s)', RPI_TEXT_DOMAIN),
                $t->getMessage(), basename($t->getFile()), $t->getLine()
            );
            if ($task_id > 0) {
                $this->append_log_to_task($task_id, 'error', $error_message);
                $this->increment_task_meta($task_id, '_rpi_error_count');
            }
            wp_send_json_error(array('message' => $error_message), 500);
        }
    }
    
    // ... (متدهای handle_complete_task, append_log_to_task, increment_task_meta از مرحله قبل) ...
    public function handle_complete_task() { /* ... */ }
    private function append_log_to_task($task_id, $type, $message) { /* ... */ }
    private function increment_task_meta($task_id, $meta_key) { /* ... */ }
}