// File: includes/Integration/ACF.php

<?php
namespace AITranslator\Integration;

class ACF {
    /**
     * Check if ACF is active
     *
     * @return bool
     */
    public function is_active() {
        return class_exists('ACF');
    }

    /**
     * Get all ACF fields for a post
     *
     * @param int $post_id
     * @return array
     */
    public function get_fields($post_id) {
        if (!$this->is_active()) {
            return [];
        }

        $fields = get_fields($post_id);
        if (!$fields) {
            return [];
        }

        return $this->process_fields($fields);
    }

    /**
     * Process fields recursively to get translatable content
     *
     * @param array $fields
     * @return array
     */
    private function process_fields($fields) {
        $translatable_fields = [];

        foreach ($fields as $key => $value) {
            // Skip non-translatable fields
            if ($this->should_skip_field($key)) {
                continue;
            }

            if (is_array($value)) {
                // Handle repeater fields
                if (isset($value[0]) && is_array($value[0])) {
                    $translatable_fields[$key] = [];
                    foreach ($value as $index => $row) {
                        $translatable_fields[$key][$index] = $this->process_fields($row);
                    }
                } else {
                    // Regular array field
                    $translatable_fields[$key] = $this->process_fields($value);
                }
            } else {
                // Regular field
                $field_obj = get_field_object($key);
                if ($this->is_translatable_field($field_obj)) {
                    $translatable_fields[$key] = [
                        'value' => $value,
                        'type' => $field_obj['type'],
                        'label' => $field_obj['label']
                    ];
                }
            }
        }

        return $translatable_fields;
    }

    /**
     * Check if field should be translated
     *
     * @param array $field ACF field object
     * @return bool
     */
    private function is_translatable_field($field) {
        if (!$field) {
            return false;
        }

        $translatable_types = [
            'text',
            'textarea',
            'wysiwyg',
            'url',
            'link'
        ];

        return in_array($field['type'], $translatable_types);
    }

    /**
     * Check if field should be skipped
     *
     * @param string $key Field key
     * @return bool
     */
    private function should_skip_field($key) {
        $skip_fields = [
            '_edit_last',
            '_edit_lock',
            '_thumbnail_id'
        ];

        return in_array($key, $skip_fields) || strpos($key, '_') === 0;
    }

    /**
     * Update ACF fields with translated content
     *
     * @param int $post_id
     * @param array $translated_fields
     * @return bool|WP_Error
     */
    public function update_fields($post_id, $translated_fields) {
        if (!$this->is_active()) {
            return new \WP_Error('acf_inactive', __('ACF is not active', 'ai-translator'));
        }

        foreach ($translated_fields as $key => $value) {
            if (is_array($value) && isset($value[0]) && is_array($value[0])) {
                // Handle repeater fields
                foreach ($value as $index => $row) {
                    $this->update_fields($post_id, $row);
                }
            } elseif (is_array($value) && isset($value['value'])) {
                // Regular field
                update_field($key, $value['value'], $post_id);
            } elseif (is_array($value)) {
                // Nested fields
                $this->update_fields($post_id, $value);
            }
        }

        return true;
    }

    /**
     * Get field groups for post type
     *
     * @param string $post_type
     * @return array
     */
    public function get_field_groups($post_type) {
        if (!$this->is_active()) {
            return [];
        }

        $field_groups = acf_get_field_groups([
            'post_type' => $post_type
        ]);

        $groups = [];
        foreach ($field_groups as $group) {
            $fields = acf_get_fields($group['key']);
            $groups[$group['key']] = [
                'title' => $group['title'],
                'fields' => $this->get_translatable_fields($fields)
            ];
        }

        return $groups;
    }

    /**
     * Get translatable fields from field list
     *
     * @param array $fields
     * @return array
     */
    private function get_translatable_fields($fields) {
        $translatable = [];

        foreach ($fields as $field) {
            if ($this->is_translatable_field($field)) {
                $translatable[$field['key']] = [
                    'name' => $field['name'],
                    'label' => $field['label'],
                    'type' => $field['type']
                ];
            } elseif ($field['type'] === 'repeater') {
                $sub_fields = $this->get_translatable_fields($field['sub_fields']);
                if (!empty($sub_fields)) {
                    $translatable[$field['key']] = [
                        'name' => $field['name'],
                        'label' => $field['label'],
                        'type' => 'repeater',
                        'sub_fields' => $sub_fields
                    ];
                }
            }
        }

        return $translatable;
    }
}