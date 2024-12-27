
<?php
namespace AITranslator\Translation;

class Post {
    private $post_id;
    private $post;
    private $translatable_fields;

    public function __construct($post_id) {
        $this->post_id = $post_id;
        $this->post = get_post($post_id);
        $this->translatable_fields = [
            'post_title',
            'post_content',
            'post_excerpt'
        ];
    }

    /**
     * Get translatable content
     *
     * @return array|WP_Error Array of translatable content or error
     */
    public function get_translatable_content() {
        if (!$this->post) {
            return new \WP_Error('invalid_post', __('Invalid post ID', 'ai-translator'));
        }

        $content = [];

        // Get basic post fields
        foreach ($this->translatable_fields as $field) {
            if (!empty($this->post->$field)) {
                $content[$field] = [
                    'type' => 'post_field',
                    'content' => $this->post->$field
                ];
            }
        }

        // Get ACF fields if available
        if (class_exists('ACF')) {
            $acf_fields = get_fields($this->post_id);
            if ($acf_fields) {
                $content['acf'] = [
                    'type' => 'acf_fields',
                    'fields' => $this->process_acf_fields($acf_fields)
                ];
            }
        }

        // Get post meta
        $meta = $this->get_translatable_meta();
        if (!empty($meta)) {
            $content['meta'] = [
                'type' => 'post_meta',
                'fields' => $meta
            ];
        }

        return $content;
    }

    /**
     * Process ACF fields recursively
     *
     * @param array $fields ACF fields
     * @return array Processed fields
     */
    private function process_acf_fields($fields) {
        $processed = [];

        foreach ($fields as $key => $value) {
            // Skip internal ACF fields
            if (strpos($key, '_') === 0) {
                continue;
            }

            $field_object = get_field_object($key);
            
            if (!$field_object) {
                continue;
            }

            // Process based on field type
            switch ($field_object['type']) {
                case 'text':
                case 'textarea':
                case 'wysiwyg':
                    $processed[$key] = [
                        'type' => $field_object['type'],
                        'label' => $field_object['label'],
                        'content' => $value
                    ];
                    break;

                case 'repeater':
                    if (is_array($value)) {
                        $processed[$key] = [
                            'type' => 'repeater',
                            'label' => $field_object['label'],
                            'rows' => []
                        ];
                        foreach ($value as $row_index => $row) {
                            $processed[$key]['rows'][$row_index] = $this->process_acf_fields($row);
                        }
                    }
                    break;

                case 'flexible_content':
                    if (is_array($value)) {
                        $processed[$key] = [
                            'type' => 'flexible_content',
                            'label' => $field_object['label'],
                            'layouts' => []
                        ];
                        foreach ($value as $layout_index => $layout) {
                            $processed[$key]['layouts'][$layout_index] = [
                                'layout_type' => $layout['acf_fc_layout'],
                                'fields' => $this->process_acf_fields($layout)
                            ];
                        }
                    }
                    break;
            }
        }

        return $processed;
    }

    /**
     * Get translatable meta fields
     *
     * @return array Translatable meta fields
     */
    private function get_translatable_meta() {
        $translatable_meta = [];
        $meta_keys = get_post_custom_keys($this->post_id);

        if (!$meta_keys) {
            return $translatable_meta;
        }

        $exclude_meta = [
            '_edit_lock',
            '_edit_last',
            '_wp_page_template',
            '_thumbnail_id'
        ];

        foreach ($meta_keys as $key) {
            // Skip excluded and ACF fields
            if (in_array($key, $exclude_meta) || strpos($key, '_') === 0) {
                continue;
            }

            $meta_value = get_post_meta($this->post_id, $key, true);
            
            // Only include if it's a string and not empty
            if (is_string($meta_value) && !empty($meta_value)) {
                $translatable_meta[$key] = [
                    'type' => 'meta',
                    'content' => $meta_value
                ];
            }
        }

        return $translatable_meta;
    }

    /**
     * Update post with translated content
     *
     * @param array $translated_content Array of translated content
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_translated_content($translated_content) {
        if (!$this->post) {
            return new \WP_Error('invalid_post', __('Invalid post ID', 'ai-translator'));
        }

        $update_data = ['ID' => $this->post_id];

        // Update basic post fields
        foreach ($this->translatable_fields as $field) {
            if (isset($translated_content[$field])) {
                $update_data[$field] = $translated_content[$field]['content'];
            }
        }

        // Update post
        $result = wp_update_post($update_data, true);
        if (is_wp_error($result)) {
            return $result;
        }

        // Update ACF fields
        if (isset($translated_content['acf']) && class_exists('ACF')) {
            $this->update_acf_fields($translated_content['acf']['fields']);
        }

        // Update meta fields
        if (isset($translated_content['meta'])) {
            foreach ($translated_content['meta']['fields'] as $key => $value) {
                update_post_meta($this->post_id, $key, $value['content']);
            }
        }

        return true;
    }

    /**
     * Update ACF fields recursively
     *
     * @param array $fields Translated ACF fields
     */
    private function update_acf_fields($fields) {
        foreach ($fields as $key => $field) {
            switch ($field['type']) {
                case 'text':
                case 'textarea':
                case 'wysiwyg':
                    update_field($key, $field['content'], $this->post_id);
                    break;

                case 'repeater':
                    if (isset($field['rows'])) {
                        foreach ($field['rows'] as $row_index => $row) {
                            $this->update_acf_fields($row);
                        }
                    }
                    break;

                case 'flexible_content':
                    if (isset($field['layouts'])) {
                        foreach ($field['layouts'] as $layout) {
                            $this->update_acf_fields($layout['fields']);
                        }
                    }
                    break;
            }
        }
    }

    /**
     * Get post language
     *
     * @return string Post language code
     */
    public function get_language() {
        if (function_exists('wpml_get_language_information')) {
            $language_info = wpml_get_language_information(null, $this->post_id);
            return $language_info['language_code'];
        }
        
        return get_option('ait_source_language', 'fr');
    }
}