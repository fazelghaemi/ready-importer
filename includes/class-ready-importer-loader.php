<?php

/**
 * کلاس بارگذار اصلی افزونه (Loader)
 *
 * این کلاس مسئولیت اصلی مدیریت و ثبت تمام هوک‌های (actions و filters)
 * افزونه در وردپرس را بر عهده دارد.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/includes
 * @author     Ready Studio
 */
class Ready_Importer_Loader {

    /**
     * آرایه‌ای برای نگهداری تمام اکشن‌های (actions) افزونه.
     *
     * @access   protected
     * @var      array    $actions    آرایه‌ای از اکشن‌ها که باید ثبت شوند.
     */
    protected $actions;

    /**
     * آرایه‌ای برای نگهداری تمام فیلترهای (filters) افزونه.
     *
     * @access   protected
     * @var      array    $filters    آرایه‌ای از فیلترها که باید ثبت شوند.
     */
    protected $filters;

    /**
     * سازنده کلاس (Constructor)
     *
     * آرایه‌ها را مقداردهی اولیه می‌کند.
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * اضافه کردن یک اکشن (action) جدید به لیست انتظار.
     *
     * @param string   $hook_name      نام هوک وردپرس (مثال: 'admin_menu').
     * @param object   $component      آبجکتی از کلاسی که متد callback در آن قرار دارد.
     * @param string   $callback_name  نام متدی که باید در زمان اجرای هوک فراخوانی شود.
     * @param int      $priority       (اختیاری) اولویت اجرای هوک. پیش‌فرض: 10.
     * @param int      $accepted_args  (اختیاری) تعداد آرگومان‌هایی که متد callback می‌پذیرد. پیش‌فرض: 1.
     */
    public function add_action($hook_name, $component, $callback_name, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add_hook_to_collection($this->actions, $hook_name, $component, $callback_name, $priority, $accepted_args);
    }

    /**
     * اضافه کردن یک فیلتر (filter) جدید به لیست انتظار.
     *
     * @param string   $hook_name      نام هوک وردپرس (مثال: 'plugin_action_links').
     * @param object   $component      آبجکتی از کلاسی که متد callback در آن قرار دارد.
     * @param string   $callback_name  نام متدی که باید در زمان اجرای هوک فراخوانی شود.
     * @param int      $priority       (اختیاری) اولویت اجرای هوک. پیش‌فرض: 10.
     * @param int      $accepted_args  (اختیاری) تعداد آرگومان‌هایی که متد callback می‌پذیرد. پیش‌فرض: 1.
     */
    public function add_filter($hook_name, $component, $callback_name, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add_hook_to_collection($this->filters, $hook_name, $component, $callback_name, $priority, $accepted_args);
    }

    /**
     * متد کمکی برای اضافه کردن هوک‌ها به آرایه‌های داخلی.
     *
     * @access   private
     * @param array    $hooks          آرایه مرجع (actions یا filters) که هوک به آن اضافه می‌شود.
     * @param string   $hook_name      نام هوک.
     * @param object   $component      آبجکت کلاس.
     * @param string   $callback_name  نام متد.
     * @param int      $priority       اولویت.
     * @param int      $accepted_args  تعداد آرگومان‌ها.
     * @return   array                 آرایه به‌روز شده هوک‌ها.
     */
    private function add_hook_to_collection($hooks, $hook_name, $component, $callback_name, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'            => $hook_name,
            'component'       => $component,
            'callback'        => $callback_name,
            'priority'        => $priority,
            'accepted_args'   => $accepted_args
        );
        return $hooks;
    }

    /**
     * متد اصلی اجرای افزونه.
     *
     * این متد توسط فایل اصلی افزونه (`ready-importer.php`) فراخوانی می‌شود.
     * وظیفه آن:
     * 1. بارگذاری و تعریف وابستگی‌ها (ماژول‌ها مانند Admin, Ajax).
     * 2. تعریف هوک‌های مورد نیاز آن ماژول‌ها.
     * 3. ثبت نهایی تمام هوک‌ها در وردپرس.
     */
    public function run() {
        
        // ۱. بارگذاری و تعریف ماژول‌ها
        $admin_module = new Ready_Importer_Admin(RPI_VERSION);
        $ajax_module = new Ready_Importer_Ajax();
        // $scraper_module = new Ready_Importer_Scraper(); (توسط ایجکس فراخوانی می‌شود)
        // $importer_module = new Ready_Importer_Importer(); (توسط ایجکس فراخوانی می‌شود)


        // ۲. تعریف هوک‌های ماژول‌ها
        
        // --- هوک‌های ماژول ادمین ---
        
        // هوک برای اضافه کردن منوی افزونه در پیشخوان وردپرس
        $this->add_action('admin_menu', $admin_module, 'add_plugin_admin_menu');
        
        // هوک برای بارگذاری فایل‌های CSS و JS در صفحات ادمین افزونه
        $this->add_action('admin_enqueue_scripts', $admin_module, 'enqueue_styles_and_scripts');

        // --- آپدیت مهم (فاز ۲): ثبت تنظیمات ---
        // این هوک حیاتی، Settings API وردپرس را فعال می‌کند و باید فراخوانی شود.
        $this->add_action('admin_init', $admin_module, 'register_settings');

        // هوک برای اضافه کردن لینک "تنظیمات" در صفحه افزونه‌ها
        $plugin_basename = RPI_PLUGIN_BASENAME; // از فایل اصلی
        $this->add_filter("plugin_action_links_{$plugin_basename}", $admin_module, 'add_settings_link');


        // --- هوک‌های ماژول ایجکس ---
        
        // هوک ایجکس برای پردازش *یک* لینک
        $this->add_action('wp_ajax_rpi_process_single_link', $ajax_module, 'handle_process_single_link');


        // ۳. ثبت نهایی هوک‌ها در وردپرس
        
        // ثبت تمام اکشن‌ها
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // ثبت تمام فیلترها
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}