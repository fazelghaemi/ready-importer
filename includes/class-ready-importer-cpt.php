<?php
/**
 * کلاس مدیریت CPT (Custom Post Type) برای وظایف
 *
 * *تغییرات این نسخه: (تکمیل فاز ۳)*
 * - اضافه شدن "متا باکس نمایشگر لاگ" (Log Viewer Meta Box).
 * - حذف ویرایشگر پیش‌فرض وردپرس از صفحه CPT.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/includes
 * @author     Ready Studio
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class Ready_Importer_CPT {

    /**
     * @var string نام نوع پست سفارشی
     */
    private $post_type = 'rpi_import_task';

    /**
     * سازنده کلاس - خالی
     */
    public function __construct() {
        // هوک‌ها در Loader ثبت می‌شوند
    }

    /**
     * هوک (init): ثبت نوع پست سفارشی و وضعیت‌ها
     * (کد از مرحله قبل)
     */
    public function register_cpt_and_statuses() {
        $this->register_post_type();
        $this->register_custom_statuses();
    }

    /**
     * ثبت نوع پست سفارشی 'rpi_import_task'
     * (کد از مرحله قبل - بدون تغییر)
     */
    private function register_post_type() {
        $labels = array(
            'name'               => __('وظایف درون‌ریزی', RPI_TEXT_DOMAIN),
            'singular_name'      => __('وظیفه درون‌ریزی', RPI_TEXT_DOMAIN),
            'menu_name'          => __('مدیریت وظایف', RPI_TEXT_DOMAIN),
            'all_items'          => __('همه وظایف', RPI_TEXT_DOMAIN),
            'edit_item'          => __('مشاهده لاگ وظیفه', RPI_TEXT_DOMAIN),
            'view_item'          => __('مشاهده وظیفه', RPI_TEXT_DOMAIN),
            // ... (سایر لیبل‌ها)
        );
        $args = array(
            'label'               => __('وظیفه درون‌ریزی', RPI_TEXT_DOMAIN),
            'labels'              => $labels,
            'supports'            => array('title', 'custom-fields'),
            'hierarchical'        => false,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false, // (توسط Admin Class مدیریت می‌شود)
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'capabilities' => array(
                'create_posts' => 'do_not_allow', 
            ),
        );
        register_post_type($this->post_type, $args);
    }

    /**
     * ثبت وضعیت‌های سفارشی (rpi-pending, rpi-running, rpi-complete)
     * (کد از مرحله قبل - بدون تغییر)
     */
    private function register_custom_statuses() {
        register_post_status('rpi-pending', array(
            'label' => _x('در صف', 'post status', RPI_TEXT_DOMAIN), 'public' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true,
            'label_count' => _n_noop('در صف <span class="count">(%s)</span>', 'در صف <span class="count">(%s)</span>', RPI_TEXT_DOMAIN),
        ));
        register_post_status('rpi-running', array(
            'label' => _x('در حال اجرا', 'post status', RPI_TEXT_DOMAIN), 'public' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true,
            'label_count' => _n_noop('در حال اجرا <span class="count">(%s)</span>', 'در حال اجرا <span class="count">(%s)</span>', RPI_TEXT_DOMAIN),
        ));
        register_post_status('rpi-complete', array(
            'label' => _x('تکمیل شده', 'post status', RPI_TEXT_DOMAIN), 'public' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true,
            'label_count' => _n_noop('تکمیل شده <span class="count">(%s)</span>', 'تکمیل شده <span class="count">(%s)</span>', RPI_TEXT_DOMAIN),
        ));
    }

    /**
     * هوک (add_meta_boxes): اضافه کردن متا باکس لاگ
     * --- (جدید) ---
     */
    public function add_log_meta_box() {
        add_meta_box(
            'rpi_log_viewer_metabox', // ID
            __('لاگ اجرای وظیفه', RPI_TEXT_DOMAIN), // عنوان
            array($this, 'render_log_meta_box'), // متد رندر
            $this->post_type, // فقط در CPT ما
            'normal', // موقعیت (normal, side, advanced)
            'high' // اولویت (high, low)
        );
    }

    /**
     * متد Callback: رندر کردن محتوای متا باکس لاگ
     * --- (جدید) ---
     */
    public function render_log_meta_box($post) {
        // دریافت تمام لاگ‌های ذخیره شده برای این پست (وظیفه)
        // لاگ‌ها به صورت ردیف‌های متادیتا مجزا ذخیره شده‌اند
        $logs = get_post_meta($post->ID, '_rpi_task_log');
        
        echo '<div id="rpi-log-viewer-metabox" class="rpi-log-container">';
        
        if (empty($logs)) {
            echo '<p>' . __('هیچ لاگی برای این وظیفه ثبت نشده است.', RPI_TEXT_DOMAIN) . '</p>';
        } else {
            foreach ($logs as $log_entry) {
                // $log_entry یک آرایه است: array('time', 'type', 'message')
                if (!is_array($log_entry) || !isset($log_entry['type']) || !isset($log_entry['message'])) {
                    continue;
                }
                
                $type_class = 'log-' . esc_attr($log_entry['type']); // log-info, log-success, log-error
                $log_time = wp_date('H:i:s', $log_entry['time']);
                $message = esc_html($log_entry['message']);
                
                echo '<div class="rpi-log-item ' . $type_class . '">';
                echo '<span class="log-icon"></span>';
                echo '<span class="log-time">[' . $log_time . ']</span> ';
                echo '<span>' . $message . '</span>';
                echo '</div>';
            }
        }
        
        echo '</div>';
    }

    /**
     * هوک (admin_head): حذف ویرایشگر پیش‌فرض وردپرس
     * --- (جدید) ---
     */
    public function remove_default_editor() {
        global $pagenow;
        // فقط در صفحات 'post.php' (ویرایش) و 'post-new.php' (جدید)
        if (in_array($pagenow, array('post.php', 'post-new.php')) && get_post_type() === $this->post_type) {
            // مخفی کردن ویرایشگر اصلی
            echo '<style>
                #postdivrich, #post-body-content {
                    display: none;
                }
                /* جابجایی متا باکس لاگ به جای ویرایشگر */
                #rpi_log_viewer_metabox {
                    margin-top: -10px;
                }
            </style>';
        }
    }


    /**
     * هوک: تعریف ستون‌های سفارشی
     * (کد از مرحله قبل)
     */
    public function set_custom_columns($columns) {
        unset($columns['date']);
        $new_columns = array();
        $new_columns['title'] = __('وظیفه (ایجاد شده توسط)', RPI_TEXT_DOMAIN);
        $new_columns['rpi_status'] = __('وضعیت', RPI_TEXT_DOMAIN);
        $new_columns['rpi_counts'] = __('نتایج', RPI_TEXT_DOMAIN);
        $new_columns['rpi_date'] = __('تاریخ اجرا', RPI_TEXT_DOMAIN);
        return $new_columns;
    }

    /**
     * هوک: پر کردن محتوای ستون‌های سفارشی
     * (کد از مرحله قبل)
     */
    public function manage_custom_columns($column, $post_id) {
        switch ($column) {
            case 'rpi_status':
                $status = get_post_status($post_id);
                $status_text = $status;
                $status_class = '';
                switch ($status) {
                    case 'rpi-pending':
                        $status_text = __('در صف', RPI_TEXT_DOMAIN);
                        $status_class = 'rpi-status-pending';
                        break;
                    case 'rpi-running':
                        $status_text = __('در حال اجرا', RPI_TEXT_DOMAIN);
                        $status_class = 'rpi-status-running';
                        break;
                    case 'rpi-complete':
                        $status_text = __('تکمیل شده', RPI_TEXT_DOMAIN);
                        $status_class = 'rpi-status-complete';
                        break;
                    case 'auto-draft':
                        $status_text = __('درحال ایجاد...', RPI_TEXT_DOMAIN);
                        $status_class = 'rpi-status-pending';
                        break;
                }
                echo '<span class="rpi-status-badge ' . $status_class . '">' . esc_html($status_text) . '</span>';
                break;
            case 'rpi_counts':
                $total = (int) get_post_meta($post_id, '_rpi_total_count', true);
                $success = (int) get_post_meta($post_id, '_rpi_success_count', true);
                $errors = (int) get_post_meta($post_id, '_rpi_error_count', true);
                printf(
                    __('<strong>کل:</strong> %d <br> <strong style="color:green;">موفق:</strong> %d <br> <strong style="color:red;">ناموفق:</strong> %d', RPI_TEXT_DOMAIN),
                    $total, $success, $errors
                );
                break;
            case 'rpi_date':
                echo get_the_modified_date('Y/m/d H:i:s', $post_id);
                break;
        }
    }
}