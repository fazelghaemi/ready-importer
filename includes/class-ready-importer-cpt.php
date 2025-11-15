<?php
/**
 * کلاس مدیریت CPT (Custom Post Type) برای وظایف
 *
 * --- جدید (فاز ۳ - مدیریت وظایف) ---
 *
 * این کلاس مسئولیت‌های زیر را بر عهده دارد:
 * 1. ثبت نوع پست سفارشی `rpi_import_task` برای نگهداری لاگ‌ها.
 * 2. ثبت وضعیت‌های سفارشی (rpi-running, rpi-complete).
 * 3. مدیریت ستون‌های سفارشی در صفحه لیست وظایف (Task Manager).
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
     */
    public function register_cpt_and_statuses() {
        $this->register_post_type();
        $this->register_custom_statuses();
    }

    /**
     * ثبت نوع پست سفارشی 'rpi_import_task'
     */
    private function register_post_type() {
        $labels = array(
            'name'               => __('وظایف درون‌ریزی', RPI_TEXT_DOMAIN),
            'singular_name'      => __('وظیفه درون‌ریزی', RPI_TEXT_DOMAIN),
            'menu_name'          => __('مدیریت وظایف', RPI_TEXT_DOMAIN),
            'name_admin_bar'     => __('وظیفه', RPI_TEXT_DOMAIN),
            'all_items'          => __('همه وظایف', RPI_TEXT_DOMAIN),
            'add_new_item'       => __('افزودن وظیفه جدید', RPI_TEXT_DOMAIN),
            'add_new'            => __('افزودن جدید', RPI_TEXT_DOMAIN),
            'new_item'           => __('وظیفه جدید', RPI_TEXT_DOMAIN),
            'edit_item'          => __('مشاهده لاگ وظیفه', RPI_TEXT_DOMAIN),
            'update_item'        => __('به‌روزرسانی وظیفه', RPI_TEXT_DOMAIN),
            'view_item'          => __('مشاهده وظیفه', RPI_TEXT_DOMAIN),
            'search_items'       => __('جستجوی وظایف', RPI_TEXT_DOMAIN),
            'not_found'          => __('هیچ وظیفه‌ای یافت نشد', RPI_TEXT_DOMAIN),
            'not_found_in_trash' => __('هیچ وظیفه‌ای در زباله‌دان یافت نشد', RPI_TEXT_DOMAIN),
        );
        $args = array(
            'label'               => __('وظیفه درون‌ریزی', RPI_TEXT_DOMAIN),
            'labels'              => $labels,
            'supports'            => array('title', 'custom-fields'), // ما از 'custom-fields' برای لاگ‌ها استفاده می‌کنیم
            'hierarchical'        => false,
            'public'              => false, // در بخش کاربری نمایش داده نشود
            'show_ui'             => true,  // در پیشخوان نمایش داده شود
            'show_in_menu'        => false, // ما منوی خودمان را می‌سازیم
            'show_in_admin_bar'   => false,
            'show_in_nav_menus'   => false,
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'capabilities' => array(
                'create_posts' => 'do_not_allow', // جلوگیری از ساخت دستی
            ),
        );
        register_post_type($this->post_type, $args);
    }

    /**
     * ثبت وضعیت‌های سفارشی برای CPT
     */
    private function register_custom_statuses() {
        register_post_status('rpi-pending', array(
            'label'                     => _x('در صف', 'post status', RPI_TEXT_DOMAIN),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('در صف <span class="count">(%s)</span>', 'در صف <span class="count">(%s)</span>', RPI_TEXT_DOMAIN),
        ));
        register_post_status('rpi-running', array(
            'label'                     => _x('در حال اجرا', 'post status', RPI_TEXT_DOMAIN),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('در حال اجرا <span class="count">(%s)</span>', 'در حال اجرا <span class="count">(%s)</span>', RPI_TEXT_DOMAIN),
        ));
        register_post_status('rpi-complete', array(
            'label'                     => _x('تکمیل شده', 'post status', RPI_TEXT_DOMAIN),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('تکمیل شده <span class="count">(%s)</span>', 'تکمیل شده <span class="count">(%s)</span>', RPI_TEXT_DOMAIN),
        ));
    }

    /**
     * هوک: تعریف ستون‌های سفارشی برای لیست CPT
     */
    public function set_custom_columns($columns) {
        unset($columns['date']); // حذف ستون تاریخ پیش‌فرض
        
        $new_columns = array();
        $new_columns['title'] = __('وظیفه (ایجاد شده توسط)', RPI_TEXT_DOMAIN);
        $new_columns['rpi_status'] = __('وضعیت', RPI_TEXT_DOMAIN);
        $new_columns['rpi_counts'] = __('نتایج', RPI_TEXT_DOMAIN);
        $new_columns['rpi_date'] = __('تاریخ اجرا', RPI_TEXT_DOMAIN);
        
        return $new_columns;
    }

    /**
     * هوک: پر کردن محتوای ستون‌های سفارشی
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