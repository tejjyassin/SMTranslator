<?php
namespace AITranslator;

final class AI_Translator {
    /**
     * Plugin instance
     *
     * @var AI_Translator
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    private $db_manager;
    private $notice_handler;
    private $settings_manager;
    private $assets_manager;
    private $ajax_handler;

    /**
     * Get plugin instance
     *
     * @return AI_Translator
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }

    /**
     * Define plugin constants
     */
    private function define_constants() {
        if (!defined('AIT_VERSION')) {
            define('AIT_VERSION', '1.0.0');
        }
        if (!defined('AIT_PLUGIN_DIR')) {
            define('AIT_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
        }
        if (!defined('AIT_PLUGIN_URL')) {
            define('AIT_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
        }
        if (!defined('AIT_PLUGIN_BASENAME')) {
            define('AIT_PLUGIN_BASENAME', plugin_basename(dirname(dirname(__FILE__))) . '/ai-translator.php');
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize components on plugins loaded
        add_action('plugins_loaded', [$this, 'init_components']);

        // Register activation and deactivation hooks
        register_activation_hook(AIT_PLUGIN_BASENAME, [$this, 'activate']);
        register_deactivation_hook(AIT_PLUGIN_BASENAME, [$this, 'deactivate']);

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . AIT_PLUGIN_BASENAME, [$this, 'add_settings_link']);
    }

    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Initialize database manager
        $this->db_manager = new Database_Manager();

        // Initialize notice handler
        $this->notice_handler = new Notice_Handler();
        $this->notice_handler->check_configuration();

        // Initialize settings manager
        $this->settings_manager = new Settings_Manager();

        // Initialize assets manager
        $this->assets_manager = new Assets_Manager();

        // Initialize AJAX handler
        $this->ajax_handler = new Ajax_Handlers();

        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Install database tables
        $this->db_manager = new Database_Manager();
        $this->db_manager->install();

        // Set default options
        $this->set_default_options();

        // Clear any relevant caches
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Clear scheduled hooks
        wp_clear_scheduled_hooks('ait_process_translation_queue');

        // Clear any relevant caches
        flush_rewrite_rules();
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = [
            'ait_source_language' => 'fr',
            'ait_target_languages' => ['en', 'ar', 'es'],
            'ait_post_types' => ['post', 'page'],
            'ait_batch_size' => 5
        ];

        foreach ($defaults as $option => $value) {
            if (false === get_option($option)) {
                update_option($option, $value);
            }
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Translator', 'ai-translator'),
            __('AI Translator', 'ai-translator'),
            'manage_options',
            'ai-translator',
            [$this, 'render_main_page'],
            'dashicons-translation'
        );

        add_submenu_page(
            'ai-translator',
            __('Settings', 'ai-translator'),
            __('Settings', 'ai-translator'),
            'manage_options',
            'ai-translator-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Render main plugin page
     */
    public function render_main_page() {
        include AIT_PLUGIN_DIR . 'admin/views/main-page.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include AIT_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=ai-translator-settings'),
            __('Settings', 'ai-translator')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Get plugin components
     */
    public function get_db_manager() {
        return $this->db_manager;
    }

    public function get_notice_handler() {
        return $this->notice_handler;
    }

    public function get_settings_manager() {
        return $this->settings_manager;
    }

    public function get_assets_manager() {
        return $this->assets_manager;
    }

    public function get_ajax_handler() {
        return $this->ajax_handler;
    }
}