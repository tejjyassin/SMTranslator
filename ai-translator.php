<?php
/**
 * Plugin Name: AI Translator
 * Plugin URI: https://your-domain.com/ai-translator
 * Description: AI-powered content translation plugin with WPML & ACF compatibility
 * Version: 1.0.0
 * Author: Yassine TEJJANI @SynergieMedia 
 * Author URI: www.synergie-media.com
 * Text Domain: ai-translator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


// Define plugin constants
define('AIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Add admin menu
add_action('admin_menu', 'ait_add_admin_menu');


function ait_add_admin_menu() {
    add_menu_page(
        'AI Translator',
        'AI Translator',
        'manage_options',
        'ai-translator',
        'ait_render_main_page',
        'dashicons-translation'
    );

    add_submenu_page(
        'ai-translator', // Parent slug
        'Settings', // Page title
        'Settings', // Menu title
        'manage_options', // Capability required
        'ai-translator-settings', // Menu slug
        'ait_render_settings_page' // Function to display the settings page
    );
}

function ait_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('ait_settings');
            do_settings_sections('ait_settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">OpenAI API Key</th>
                    <td>
                        <input type="password" 
                               name="ait_openai_api_key" 
                               value="<?php echo esc_attr(get_option('ait_openai_api_key')); ?>" 
                               class="regular-text"
                        />
                        <p class="description">Enter your OpenAI API key. You can get one from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Dashboard</a></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}




// Update the enqueue function to properly localize the script
function ait_enqueue_admin_assets($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'ai-translator') === false) {
        return;
    }

    wp_enqueue_style(
        'ait-admin-style',
        AIT_PLUGIN_URL . 'admin/css/admin.css',
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'ait-admin-script',
        AIT_PLUGIN_URL . 'admin/js/admin.js',
        ['jquery'],
        '1.0.0',
        true
    );

    // Localize script
    wp_localize_script(
        'ait-admin-script',
        'aitVars',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ait_translate_nonce'),
            'strings' => [
                'error' => __('An error occurred', 'ai-translator'),
                'success' => __('Translation completed', 'ai-translator')
            ]
        ]
    );
}
add_action('admin_enqueue_scripts', 'ait_enqueue_admin_assets');

class AIT_Translation_Handler {
    public function handle_translation() {
        // Verify nonce
        if (!check_ajax_referer('ait_translate_nonce', 'security', false)) {
            wp_send_json_error([
                'message' => 'Security check failed'
            ]);
            wp_die();
        }

        // Check if WPML is active
        if (!defined('ICL_SITEPRESS_VERSION')) {
            wp_send_json_error([
                'message' => 'WPML is required for translations'
            ]);
            wp_die();
        }

        global $sitepress;

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $languages = isset($_POST['languages']) ? (array) $_POST['languages'] : [];

        if (!$post_id || empty($languages)) {
            wp_send_json_error([
                'message' => 'Missing required parameters'
            ]);
            wp_die();
        }

        try {
            $translator = new \AITranslator\Api\OpenAI();
            $original_post = get_post($post_id);
            
            if (!$original_post) {
                throw new Exception('Post not found');
            }

            // Get the source language
            $source_language = $sitepress->get_language_for_element($post_id, 'post_' . $original_post->post_type);

            $results = [];
            foreach ($languages as $target_lang) {
                // Check if translation already exists
                $trid = $sitepress->get_element_trid($post_id, 'post_' . $original_post->post_type);
                $translations = $sitepress->get_element_translations($trid, 'post_' . $original_post->post_type);
                
                if (isset($translations[$target_lang])) {
                    $results[$target_lang] = [
                        'success' => false,
                        'message' => 'Translation already exists'
                    ];
                    continue;
                }

                // Translate title
                $translated_title = $translator->translate($original_post->post_title, $target_lang);
                
                // Translate content
                $translated_content = $translator->translate($original_post->post_content, $target_lang);

                if (!$translated_content['success']) {
                    $results[$target_lang] = [
                        'success' => false,
                        'message' => $translated_content['error']
                    ];
                    continue;
                }

                // Create new post with translated content
                // Create new post with translated content
                $translated_post = array(
                    'post_title'   => $translated_title['content'],
                    'post_content' => $translated_content['content'],
                    'post_status'  => 'draft',
                    'post_author'  => get_current_user_id(),
                    'post_type'    => $original_post->post_type,
                );

                // Insert the translated post
                $new_post_id = wp_insert_post($translated_post);

                if (is_wp_error($new_post_id)) {
                    $results[$target_lang] = [
                        'success' => false,
                        'message' => $new_post_id->get_error_message()
                    ];
                    continue;
                }

                // Set language information in WPML first
                $sitepress->set_element_language_details(
                    $new_post_id,
                    'post_' . $original_post->post_type,
                    $trid,
                    $target_lang,
                    $source_language
                );

                // Then copy and translate post meta including ACF fields
                $this->copy_post_meta($post_id, $new_post_id, $translator, $target_lang);


                $results[$target_lang] = [
                    'success' => true,
                    'post_id' => $new_post_id,
                    'edit_url' => get_edit_post_link($new_post_id, 'raw')
                ];
            }

            wp_send_json_success([
                'message' => 'Translation completed',
                'results' => $results
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }

        wp_die();
    }

    private function copy_post_meta($original_id, $translated_id, $translator, $target_lang) {
        // Debug log
        error_log('Starting ACF fields translation for post ' . $original_id);
        
        // Get all ACF fields for this post
        if (function_exists('get_fields')) {
            $acf_fields = get_fields($original_id, false); // Add false parameter to get raw values
            error_log('ACF Fields found: ' . print_r($acf_fields, true));
            
            if ($acf_fields) {
                foreach ($acf_fields as $field_key => $field_value) {
                    // Get field object to check field type
                    $field_object = get_field_object($field_key, $original_id);
                    error_log('Processing field: ' . $field_key . ' of type: ' . ($field_object ? $field_object['type'] : 'unknown'));
                    
                    if ($field_object) {
                        $translated_value = $this->translate_acf_field(
                            $field_value, 
                            $field_object['type'], 
                            $translator, 
                            $target_lang
                        );
                        
                        error_log('Translated value for ' . $field_key . ': ' . print_r($translated_value, true));
                        update_field($field_object['key'], $translated_value, $translated_id);
                    }
                }
            }
        }
    
        // Handle regular post meta
        $meta_keys = get_post_custom_keys($original_id);
        if (!empty($meta_keys)) {
            foreach ($meta_keys as $meta_key) {
                // Skip internal meta and ACF fields
                if (strpos($meta_key, '_') === 0 || strpos($meta_key, 'field_') === 0) {
                    continue;
                }
    
                $meta_values = get_post_meta($original_id, $meta_key);
                foreach ($meta_values as $meta_value) {
                    if (is_string($meta_value) && !empty($meta_value)) {
                        $translated_meta = $translator->translate($meta_value, $target_lang);
                        if ($translated_meta['success']) {
                            add_post_meta($translated_id, $meta_key, $translated_meta['content']);
                        } else {
                            add_post_meta($translated_id, $meta_key, $meta_value);
                        }
                    } else {
                        add_post_meta($translated_id, $meta_key, $meta_value);
                    }
                }
            }
        }
    }
    
    /**
     * Translate ACF field based on field type
     */
    private function translate_acf_field($value, $field_type, $translator, $target_lang) {
        switch ($field_type) {
            case 'text':
            case 'textarea':
            case 'wysiwyg':
                if (!empty($value)) {
                    $translated = $translator->translate($value, $target_lang);
                    return $translated['success'] ? $translated['content'] : $value;
                }
                return $value;
    
            case 'repeater':
                if (is_array($value)) {
                    foreach ($value as $row_index => $row) {
                        foreach ($row as $sub_field_key => $sub_field_value) {
                            $sub_field = get_field_object($sub_field_key);
                            if ($sub_field) {
                                $value[$row_index][$sub_field_key] = $this->translate_acf_field(
                                    $sub_field_value,
                                    $sub_field['type'],
                                    $translator,
                                    $target_lang
                                );
                            }
                        }
                    }
                }
                return $value;
    
            case 'flexible_content':
                if (is_array($value)) {
                    foreach ($value as $layout_index => $layout) {
                        foreach ($layout as $layout_field_key => $layout_field_value) {
                            if ($layout_field_key === 'acf_fc_layout') continue;
                            
                            $layout_field = get_field_object($layout_field_key);
                            if ($layout_field) {
                                $value[$layout_index][$layout_field_key] = $this->translate_acf_field(
                                    $layout_field_value,
                                    $layout_field['type'],
                                    $translator,
                                    $target_lang
                                );
                            }
                        }
                    }
                }
                return $value;
    
            case 'relationship':
            case 'post_object':
            case 'page_link':
            case 'image':
            case 'file':
            case 'gallery':
                // For these fields, we keep the same value as they are references
                return $value;
    
            case 'group':
                if (is_array($value)) {
                    foreach ($value as $group_field_key => $group_field_value) {
                        $group_field = get_field_object($group_field_key);
                        if ($group_field) {
                            $value[$group_field_key] = $this->translate_acf_field(
                                $group_field_value,
                                $group_field['type'],
                                $translator,
                                $target_lang
                            );
                        }
                    }
                }
                return $value;
    
            default:
                // For any other field type, return the original value
                return $value;
        }
    }
}

// Initialize the translation handler
$translation_handler = new AIT_Translation_Handler();

// Register AJAX handlers
add_action('wp_ajax_ait_translate_post', [$translation_handler, 'handle_translation']);



// Add the AJAX action hooks
// Register AJAX actions early
add_action('init', 'ait_register_ajax_actions');

function ait_register_ajax_actions() {
    // add_action('wp_ajax_ait_translate_post', 'ait_handle_translation');
    add_action('wp_ajax_nopriv_ait_translate_post', 'ait_handle_translation'); // If needed for non-logged in users
}
// AJAX handler for bulk translation
function ait_handle_bulk_translation() {
    // Verify nonce
    if (!check_ajax_referer('ait_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Invalid security token']);
    }

    $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : [];
    $target_languages = isset($_POST['languages']) ? (array) $_POST['languages'] : [];

    if (empty($post_ids) || empty($target_languages)) {
        wp_send_json_error(['message' => 'Invalid parameters']);
    }

    // TODO: Add actual bulk translation logic here
    // For now, just return success
    wp_send_json_success([
        'message' => 'Bulk translation initiated',
        'post_ids' => $post_ids,
        'languages' => $target_languages
    ]);
}

function ait_render_main_page() {
    ?>
    <div class="wrap ait-container">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="ait-content">
            <table class="ait-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Language</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $posts = get_posts([
                        'post_type' => 'post',
                        'posts_per_page' => 10
                    ]);

                    foreach ($posts as $post) {
                        ?>
                        <tr>
                            <td><input type="checkbox" class="post-select" value="<?php echo esc_attr($post->ID); ?>"></td>
                            <td><?php echo esc_html($post->post_title); ?></td>
                            <td><?php echo esc_html($post->post_type); ?></td>
                            <td>FR</td>
                            <td>Not translated</td>
                            <td>
                                <button class="ait-button translate-button" data-post-id="<?php echo esc_attr($post->ID); ?>">
                                    Translate
                                </button>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>

            <div class="ait-bulk-actions">
                <button class="ait-button" id="bulk-translate">Bulk Translate</button>
            </div>
        </div>
    </div>
    <?php
}


// Add this with your other add_action calls
add_action('admin_init', 'ait_register_settings');

function ait_register_settings() {
    register_setting('ait_settings', 'ait_openai_api_key');
    register_setting('ait_settings', 'ait_source_language');
    register_setting('ait_settings', 'ait_target_languages');
}

// Plugin constants
// Plugin constants - only define if not already defined
if (!defined('AIT_VERSION')) {
    define('AIT_VERSION', '1.0.0');
}
if (!defined('AIT_PLUGIN_DIR')) {
    define('AIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('AIT_PLUGIN_URL')) {
    define('AIT_PLUGIN_URL', plugin_dir_url(__FILE__));
}
define('AIT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader function
spl_autoload_register(function ($class) {
    $prefix = 'AITranslator\\';
    $base_dir = AIT_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});



// Initialize the plugin
function ait_init() {
    require_once AIT_PLUGIN_DIR . 'includes/class-ai-translator.php';
    require_once AIT_PLUGIN_DIR . 'includes/Api/OpenAI.php';

    return AITranslator\AI_Translator::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'ait_init');
