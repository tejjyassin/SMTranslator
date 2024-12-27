// File: includes/class-activator.php

<?php
namespace AITranslator;

class Activator {
    /**
     * Run activation tasks
     */
    public static function activate() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('AI Translator requires PHP 7.4 or higher.', 'ai-translator'),
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.8', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('AI Translator requires WordPress 5.8 or higher.', 'ai-translator'),
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }

        // Check if WPML is active
        if (!defined('ICL_SITEPRESS_VERSION')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('AI Translator requires WPML to be installed and activated.', 'ai-translator'),
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }

        // Check if ACF is active
        if (!class_exists('ACF')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('AI Translator requires Advanced Custom Fields (ACF) to be installed and activated.', 'ai-translator'),
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }

        // Set up database tables
        self::setup_database();

        // Set up default options
        self::setup_options();

        // Set up roles and capabilities
        self::setup_roles();

        // Create necessary directories
        self::setup_directories();

        // Schedule cron jobs
        self::setup_cron_jobs();

        // Set activation flag
        update_option('ait_plugin_activated', true);
        update_option('ait_plugin_version', AIT_VERSION);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Set up database tables
     */
    private static function setup_database() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Translations table
        $table_name = $wpdb->prefix . 'ait_translations';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            source_language varchar(10) NOT NULL,
            target_language varchar(10) NOT NULL,
            original_content longtext NOT NULL,
            translated_content longtext NOT NULL,
            translation_status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY translation_status (translation_status)
        ) $charset_collate;";
        dbDelta($sql);

        // Queue table
        $table_name = $wpdb->prefix . 'ait_queue';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            target_language varchar(10) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            error_message text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
    }

    /**
     * Set up default options
     */
    private static function setup_options() {
        $default_options = [
            'ait_source_language' => 'fr',
            'ait_target_languages' => ['en', 'ar', 'es'],
            'ait_post_types' => ['post', 'page'],
            'ait_batch_size' => 5,
            'ait_api_model' => 'gpt-4',
            'ait_translation_memory_enabled' => true,
            'ait_auto_translate_enabled' => false,
            'ait_translation_quality_check' => true
        ];

        foreach ($default_options as $option => $value) {
            if (false === get_option($option)) {
                update_option($option, $value);
            }
        }
    }

    /**
     * Set up roles and capabilities
     */
    private static function setup_roles() {
        // Add capabilities to administrator
        $admin = get_role('administrator');
        $capabilities = [
            'manage_translations',
            'translate_posts',
            'edit_translations',
            'delete_translations',
            'manage_translation_settings'
        ];

        foreach ($capabilities as $cap) {
            $admin->add_cap($cap);
        }

        // Create custom role for translators
        add_role(
            'translator',
            __('Translator', 'ai-translator'),
            [
                'read' => true,
                'translate_posts' => true,
                'edit_translations' => true
            ]
        );
    }

    /**
     * Set up necessary directories
     */
    private static function setup_directories() {
        // Create cache directory
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/ait-cache';

        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }

        // Create .htaccess file to protect cache directory
        $htaccess_file = $cache_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Deny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }

        // Create index.php file for security
        $index_file = $cache_dir . '/index.php';
        if (!file_exists($index_file)) {
            $index_content = "<?php\n// Silence is golden.";
            file_put_contents($index_file, $index_content);
        }
    }

    /**
     * Set up cron jobs
     */
    private static function setup_cron_jobs() {
        // Schedule translation queue processing
        if (!wp_next_scheduled('ait_process_translation_queue')) {
            wp_schedule_event(time(), 'every_5_minutes', 'ait_process_translation_queue');
        }

        // Schedule cleanup job
        if (!wp_next_scheduled('ait_cleanup_old_translations')) {
            wp_schedule_event(time(), 'daily', 'ait_cleanup_old_translations');
        }
    }
}