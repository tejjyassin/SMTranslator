// File: includes/class-assets-manager.php

<?php
namespace AITranslator;

class Assets_Manager {
    /**
     * Register hooks
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook_suffix The current admin page
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on plugin pages
        if (!$this->is_plugin_page($hook_suffix)) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'ait-admin-styles',
            AIT_PLUGIN_URL . 'admin/css/admin.css',
            [],
            AIT_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'ait-admin-script',
            AIT_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            AIT_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'ait-admin-script',
            'aitVars',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ait_nonce'),
                'noPostsSelected' => __('Please select at least one post to translate.', 'ai-translator'),
                'noLanguagesSelected' => __('Please select at least one target language.', 'ai-translator'),
                'translationSuccess' => __('Translation process has been initiated successfully.', 'ai-translator'),
                'ajaxError' => __('An error occurred while processing your request.', 'ai-translator'),
                'confirmBulk' => __('Are you sure you want to translate the selected posts?', 'ai-translator'),
                'processing' => __('Processing...', 'ai-translator')
            ]
        );
    }

    /**
     * Check if current page is a plugin page
     *
     * @param string $hook_suffix
     * @return bool
     */
    private function is_plugin_page($hook_suffix) {
        $plugin_pages = [
            'toplevel_page_ai-translator',
            'ai-translator_page_ai-translator-settings'
        ];

        return in_array($hook_suffix, $plugin_pages);
    }
}