<?php

/**
 * کلاس بارگذار اصلی افزونه (Loader)
 *
 * *تغییرات این نسخه: (تکمیل فاز ۳)*
 * - اضافه کردن هوک `add_meta_boxes` برای نمایش لاگ CPT.
 * - اضافه کردن هوک `admin_head` برای حذف ویرایشگر پیش‌فرض CPT.
 *
 * @package    Ready_Importer
 * @subpackage Ready_Importer/includes
 * @author     Ready Studio
 */
class Ready_Importer_Loader {

    protected $actions;
    protected $filters;

    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    public function add_action($hook_name, $component, $callback_name, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add_hook_to_collection($this->actions, $hook_name, $component, $callback_name, $priority, $accepted_args);
    }
    public function add_filter($hook_name, $component, $callback_name, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add_hook_to_collection($this->filters, $hook_name, $component, $callback_name, $priority, $accepted_args);
    }
    private function add_hook_to_collection($hooks, $hook_name, $component, $callback_name, $priority, $accepted_args) {
        $hooks[] = array('hook' => $hook_name, 'component' => $component, 'callback' => $callback_name, 'priority' => $priority, 'accepted_args' => $accepted_args);
        return $hooks;
    }

    /**
     * متد اصلی اجرای افزونه.
     */
    public function run() {
        
        // ۱. بارگذاری و تعریف ماژول‌ها
        require_once RPI_PLUGIN_PATH . 'includes/class-ready-importer-cpt.php';
        $cpt_module = new Ready_Importer_CPT();

        $admin_module = new Ready_Importer_Admin(RPI_VERSION);
        $ajax_module = new Ready_Importer_Ajax();

        // ۲. تعریف هوک‌های ماژول‌ها
        
        // --- هوک‌های ماژول CPT ---
        $this->add_action('init', $cpt_module, 'register_cpt_and_statuses');
        // ستون‌های سفارشی
        $this->add_filter('manage_rpi_import_task_posts_columns', $cpt_module, 'set_custom_columns');
        $this->add_action('manage_rpi_import_task_posts_custom_column', $cpt_module, 'manage_custom_columns', 10, 2);
        // (جدید) متا باکس نمایشگر لاگ
        $this->add_action('add_meta_boxes', $cpt_module, 'add_log_meta_box');
        // (جدید) حذف ویرایشگر پیش‌فرض
        $this->add_action('admin_head', $cpt_module, 'remove_default_editor');

        // --- هوک‌های ماژول ادمین ---
        $this->add_action('admin_menu', $admin_module, 'add_plugin_admin_menu');
        $this->add_action('admin_enqueue_scripts', $admin_module, 'enqueue_styles_and_scripts');
        $this->add_action('admin_init', $admin_module, 'register_settings');
        $plugin_basename = RPI_PLUGIN_BASENAME;
        $this->add_filter("plugin_action_links_{$plugin_basename}", $admin_module, 'add_settings_link');

        // --- هوک‌های ماژول ایجکس ---
        $this->add_action('wp_ajax_rpi_create_task', $ajax_module, 'handle_create_task');
        $this->add_action('wp_ajax_rpi_process_single_link', $ajax_module, 'handle_process_single_link');
        $this->add_action('wp_ajax_rpi_complete_task', $ajax_module, 'handle_complete_task');

        // ۳. ثبت نهایی هوک‌ها در وردپرس
        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }
}