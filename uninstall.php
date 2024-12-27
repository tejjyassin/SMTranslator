
<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

class AI_Translator_Uninstaller {
    /**
     * Run uninstallation cleanup
     */
    public static function uninstall() {
        self::remove_database_tables();
        self::remove_options();
        self::remove_roles_capabilities();
        self::remove_directories();
        self::clear_schedules();
    }

    /**
     * Remove database tables
     */
    private static function remove_database_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'ait_translations',
            $wpdb->prefix . 'ait_queue'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }

    /**
     * Remove plugin options
     */
    private static function remove_options() {
        $options = [
            'ait_api_key',
            'ait_source_language',
            'ait_target_languages',
            'ait_post_types',
            'ait_batch_size',
            'ait_api_model',
            'ait_translation_memory_enabled',
            'ait_auto_translate_enabled',
            'ait_translation_quality_check',
            'ait_plugin_activated',
            'ait_plugin_version',
            'ait_db_version',
            'ait_dismissed_notices'
        ];

        foreach ($options as $option) {
            delete_option($option);
        }

        // Delete all options with ait_ prefix
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ait_%'");
    }

    /**
     * Remove roles and capabilities
     */
    private static function remove_roles_capabilities() {
        // Remove custom role
        remove_role('translator');

        // Remove capabilities from administrator
        $admin = get_role('administrator');
        $capabilities = [
            'manage_translations',
            'translate_posts',
            'edit_translations',
            'delete_translations',
            'manage_translation_settings'
        ];

        if ($admin) {
            foreach ($capabilities as $cap) {
                $admin->remove_cap($cap);
            }
        }
    }

    /**
     * Remove plugin directories
     */
    private static function remove_directories() {
        // Remove cache directory
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/ait-cache';

        if (is_dir($cache_dir)) {
            self::recursive_remove_directory($cache_dir);
        }
    }

    /**
     * Recursively remove a directory
     */
    private static function recursive_remove_directory($directory) {
        foreach (glob("{$directory}/*") as $file) {
            if (is_dir($file)) {
                self::recursive_remove_directory($file);
            } else {
                unlink($file);
            }
        }
        rmdir($directory);
    }

    /**
     * Clear scheduled hooks
     */
    private static function clear_schedules() {
        wp_clear_scheduled_hook('ait_process_translation_queue');
        wp_clear_scheduled_hook('ait_cleanup_old_translations');
    }
}

// Run uninstaller
AI_Translator_Uninstaller::uninstall();