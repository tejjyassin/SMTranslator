// File: includes/class-settings-manager.php

<?php
namespace AITranslator;

class Settings_Manager {
    /**
     * Settings sections
     */
    private $sections = [];

    /**
     * Settings fields
     */
    private $fields = [];

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', [$this, 'init_settings']);
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        $this->init_sections();
        $this->init_fields();
        $this->register_settings();
    }

    /**
     * Initialize settings sections
     */
    private function init_sections() {
        $this->sections = [
            'api' => [
                'id' => 'ait_api_settings',
                'title' => __('API Settings', 'ai-translator'),
                'description' => __('Configure your OpenAI API settings', 'ai-translator'),
                'page' => 'ai-translator-settings'
            ],
            'languages' => [
                'id' => 'ait_language_settings',
                'title' => __('Language Settings', 'ai-translator'),
                'description' => __('Configure source and target languages', 'ai-translator'),
                'page' => 'ai-translator-settings'
            ],
            'content' => [
                'id' => 'ait_content_settings',
                'title' => __('Content Settings', 'ai-translator'),
                'description' => __('Configure which content types to translate', 'ai-translator'),
                'page' => 'ai-translator-settings'
            ],
            'advanced' => [
                'id' => 'ait_advanced_settings',
                'title' => __('Advanced Settings', 'ai-translator'),
                'description' => __('Configure advanced translation settings', 'ai-translator'),
                'page' => 'ai-translator-settings'
            ]
        ];
    }

    /**
     * Initialize settings fields
     */
    private function init_fields() {
        $this->fields = [
            'api_key' => [
                'id' => 'ait_api_key',
                'title' => __('OpenAI API Key', 'ai-translator'),
                'callback' => [$this, 'render_api_key_field'],
                'page' => 'ai-translator-settings',
                'section' => 'ait_api_settings',
                'args' => [
                    'label_for' => 'ait_api_key',
                    'class' => 'regular-text'
                ]
            ],
            'source_language' => [
                'id' => 'ait_source_language',
                'title' => __('Source Language', 'ai-translator'),
                'callback' => [$this, 'render_source_language_field'],
                'page' => 'ai-translator-settings',
                'section' => 'ait_language_settings',
                'args' => [
                    'label_for' => 'ait_source_language'
                ]
            ],
            'target_languages' => [
                'id' => 'ait_target_languages',
                'title' => __('Target Languages', 'ai-translator'),
                'callback' => [$this, 'render_target_languages_field'],
                'page' => 'ai-translator-settings',
                'section' => 'ait_language_settings',
                'args' => [
                    'label_for' => 'ait_target_languages'
                ]
            ],
            'post_types' => [
                'id' => 'ait_post_types',
                'title' => __('Post Types', 'ai-translator'),
                'callback' => [$this, 'render_post_types_field'],
                'page' => 'ai-translator-settings',
                'section' => 'ait_content_settings',
                'args' => [
                    'label_for' => 'ait_post_types'
                ]
            ],
            'batch_size' => [
                'id' => 'ait_batch_size',
                'title' => __('Batch Size', 'ai-translator'),
                'callback' => [$this, 'render_batch_size_field'],
                'page' => 'ai-translator-settings',
                'section' => 'ait_advanced_settings',
                'args' => [
                    'label_for' => 'ait_batch_size',
                    'class' => 'small-text'
                ]
            ]
        ];
    }

    /**
     * Register settings
     */
    private function register_settings() {
        // Register sections
        foreach ($this->sections as $section) {
            add_settings_section(
                $section['id'],
                $section['title'],
                function() use ($section) {
                    echo '<p>' . esc_html($section['description']) . '</p>';
                },
                $section['page']
            );
        }

        // Register fields
        foreach ($this->fields as $field) {
            register_setting('ait_settings', $field['id']);
            
            add_settings_field(
                $field['id'],
                $field['title'],
                $field['callback'],
                $field['page'],
                $field['section'],
                $field['args'] ?? []
            );
        }
    }

    /**
     * Render API key field
     */
    public function render_api_key_field($args) {
        $value = get_option('ait_api_key');
        ?>
        <input type="password"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($args['label_for']); ?>"
               value="<?php echo esc_attr($value); ?>"
               class="<?php echo esc_attr($args['class']); ?>"
        >
        <p class="description">
            <?php _e('Enter your OpenAI API key. You can get one from your OpenAI dashboard.', 'ai-translator'); ?>
        </p>
        <?php
    }

    /**
     * Render source language field
     */
    public function render_source_language_field($args) {
        $value = get_option('ait_source_language', 'fr');
        $languages = $this->get_available_languages();
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>"
                name="<?php echo esc_attr($args['label_for']); ?>">
            <?php foreach ($languages as $code => $name) : ?>
                <option value="<?php echo esc_attr($code); ?>" 
                        <?php selected($value, $code); ?>>
                    <?php echo esc_html($name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('Select the source language for content translation.', 'ai-translator'); ?>
        </p>
        <?php
    }

    /**
     * Render target languages field
     */
    public function render_target_languages_field($args) {
        $selected = get_option('ait_target_languages', []);
        $languages = $this->get_available_languages();
        ?>
        <div class="ait-checkbox-group">
            <?php foreach ($languages as $code => $name) : ?>
                <label>
                    <input type="checkbox"
                           name="<?php echo esc_attr($args['label_for']); ?>[]"
                           value="<?php echo esc_attr($code); ?>"
                           <?php checked(in_array($code, $selected)); ?>>
                    <?php echo esc_html($name); ?>
                </label><br>
            <?php endforeach; ?>
        </div>
        <p class="description">
            <?php _e('Select the target languages for translation.', 'ai-translator'); ?>
        </p>
        <?php
    }

    /**
     * Render post types field
     */
    public function render_post_types_field($args) {
        $selected = get_option('ait_post_types', ['post', 'page']);
        $post_types = get_post_types(['public' => true], 'objects');
        ?>
        <div class="ait-checkbox-group">
            <?php foreach ($post_types as $type) : ?>
                <label>
                    <input type="checkbox"
                           name="<?php echo esc_attr($args['label_for']); ?>[]"
                           value="<?php echo esc_attr($type->name); ?>"
                           <?php checked(in_array($type->name, $selected)); ?>>
                    <?php echo esc_html($type->label); ?>
                </label><br>
            <?php endforeach; ?>
        </div>
        <p class="description">
            <?php _e('Select which post types should be available for translation.', 'ai-translator'); ?>
        </p>
        <?php
    }

    /**
     * Render batch size field
     */
    public function render_batch_size_field($args) {
        $value = get_option('ait_batch_size', 5);
        ?>
        <input type="number"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($args['label_for']); ?>"
               value="<?php echo esc_attr($value); ?>"
               class="<?php echo esc_attr($args['class']); ?>"
               min="1"
               max="20">
        <p class="description">
            <?php _e('Number of posts to process in each batch (1-20).', 'ai-translator'); ?>
        </p>
        <?php
    }

    /**
     * Get available languages
     *
     * @return array Languages array
     */
    private function get_available_languages() {
        return [
            'fr' => __('French', 'ai-translator'),
            'en' => __('English', 'ai-translator'),
            'ar' => __('Arabic', 'ai-translator'),
            'es' => __('Spanish', 'ai-translator')
        ];
    }

    /**
     * Validate settings
     *
     * @param array $input Input array
     * @return array Sanitized input
     */
    public function validate_settings($input) {
        $output = [];

        foreach ($input as $key => $value) {
            switch ($key) {
                case 'ait_api_key':
                    $output[$key] = sanitize_text_field($value);
                    break;

                case 'ait_source_language':
                    $output[$key] = sanitize_text_field($value);
                    break;

                case 'ait_target_languages':
                case 'ait_post_types':
                    $output[$key] = array_map('sanitize_text_field', (array) $value);
                    break;

                case 'ait_batch_size':
                    $output[$key] = absint($value);
                    if ($output[$key] < 1) {
                        $output[$key] = 1;
                    } elseif ($output[$key] > 20) {
                        $output[$key] = 20;
                    }
                    break;

                default:
                    $output[$key] = sanitize_text_field($value);
                    break;
            }
        }

        return $output;
    }
}