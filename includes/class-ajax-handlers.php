<?php
namespace AITranslator;

use AITranslator\Translation\Manager;
use AITranslator\Translation\Queue;
use AITranslator\Translation\Post;

class Ajax_Handlers {
    
    public function __construct() {
        $this->register_handlers();
    }

    /**
     * Register all AJAX handlers
     */
    private function register_handlers() {
        add_action('wp_ajax_ait_get_post_details', [$this, 'get_post_details']);
        add_action('wp_ajax_ait_get_bulk_post_details', [$this, 'get_bulk_post_details']);
        add_action('wp_ajax_ait_translate_post', [$this, 'translate_post']);
        add_action('wp_ajax_ait_bulk_translate', [$this, 'bulk_translate']);
        add_action('wp_ajax_ait_rephrase_content', [$this, 'rephrase_content']);
        add_action('wp_ajax_ait_get_translation_status', [$this, 'get_translation_status']);
    }

    /**
     * Get post details for translation
     */
    public function get_post_details() {
        if (!check_ajax_referer('ait_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token', 'ai-translator')]);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID', 'ai-translator')]);
        }

        $post = new Post($post_id);
        $content = $post->get_translatable_content();

        if (is_wp_error($content)) {
            wp_send_json_error(['message' => $content->get_error_message()]);
        }

        $translation_manager = new Manager();
        $translation_status = $translation_manager->get_translation_status($post_id);

        wp_send_json_success([
            'content' => $this->format_content_for_display($content),
            'status' => $this->format_status_for_display($translation_status)
        ]);
    }

    /**
     * Get details for bulk translation
     */
    public function get_bulk_post_details() {
        if (!check_ajax_referer('ait_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token', 'ai-translator')]);
        }

        $post_ids = isset($_POST['post_ids']) ? array_map('intval', explode(',', $_POST['post_ids'])) : [];
        
        if (empty($post_ids)) {
            wp_send_json_error(['message' => __('No posts selected', 'ai-translator')]);
        }

        $posts_details = [];
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if ($post) {
                $posts_details[] = [
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'type' => get_post_type_object($post->post_type)->labels->singular_name
                ];
            }
        }

        wp_send_json_success([
            'content' => $this->format_bulk_posts_for_display($posts_details)
        ]);
    }

    /**
     * Handle single post translation
     */
    public function translate_post() {
        if (!check_ajax_referer('ait_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token', 'ai-translator')]);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $languages = isset($_POST['languages']) ? (array) $_POST['languages'] : [];

        if (!$post_id || empty($languages)) {
            wp_send_json_error(['message' => __('Invalid parameters', 'ai-translator')]);
        }

        $translation_manager = new Manager();
        $results = [];

        foreach ($languages as $lang) {
            $result = $translation_manager->translate_post($post_id, $lang);
            $results[$lang] = is_wp_error($result) ? $result->get_error_message() : 'success';
        }

        wp_send_json_success([
            'results' => $results
        ]);
    }

    /**
     * Handle bulk translation
     */
    public function bulk_translate() {
        if (!check_ajax_referer('ait_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token', 'ai-translator')]);
        }

        $post_ids = isset($_POST['post_ids']) ? array_map('intval', explode(',', $_POST['post_ids'])) : [];
        $languages = isset($_POST['languages']) ? (array) $_POST['languages'] : [];

        if (empty($post_ids) || empty($languages)) {
            wp_send_json_error(['message' => __('Invalid parameters', 'ai-translator')]);
        }

        $queue = new Queue();
        $result = $queue->add_to_queue($post_ids, $languages);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Posts added to translation queue', 'ai-translator')
        ]);
    }

    /**
     * Handle content rephrasing
     */
    public function rephrase_content() {
        if (!check_ajax_referer('ait_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token', 'ai-translator')]);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $lang = isset($_POST['lang']) ? sanitize_text_field($_POST['lang']) : '';

        if (!$post_id || !$lang) {
            wp_send_json_error(['message' => __('Invalid parameters', 'ai-translator')]);
        }

        $translation_manager = new Manager();
        $result = $translation_manager->rephrase_content($post_id, $lang);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'content' => $result
        ]);
    }

    /**
     * Get translation status
     */
    public function get_translation_status() {
        if (!check_ajax_referer('ait_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token', 'ai-translator')]);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID', 'ai-translator')]);
        }

        $translation_manager = new Manager();
        $status = $translation_manager->get_translation_status($post_id);

        wp_send_json_success([
            'status' => $this->format_status_for_display($status)
        ]);
    }

    /**
     * Format content for display in the translation interface
     *
     * @param array $content
     * @return string Formatted HTML
     */
    private function format_content_for_display($content) {
        $html = '<div class="ait-content-preview">';

        // Format post fields
        foreach ($content as $field => $data) {
            if ($data['type'] === 'post_field') {
                $html .= sprintf(
                    '<div class="ait-field-group">
                        <h4>%s</h4>
                        <div class="ait-field-content">%s</div>
                    </div>',
                    esc_html(ucfirst(str_replace('post_', '', $field))),
                    wp_kses_post($data['content'])
                );
            }
        }

        // Format ACF fields
        if (isset($content['acf']) && !empty($content['acf']['fields'])) {
            $html .= '<div class="ait-acf-fields">';
            $html .= '<h3>' . __('Custom Fields', 'ai-translator') . '</h3>';
            $html .= $this->format_acf_fields_for_display($content['acf']['fields']);
            $html .= '</div>';
        }

        // Format meta fields
        if (isset($content['meta']) && !empty($content['meta']['fields'])) {
            $html .= '<div class="ait-meta-fields">';
            $html .= '<h3>' . __('Meta Fields', 'ai-translator') . '</h3>';
            foreach ($content['meta']['fields'] as $key => $data) {
                $html .= sprintf(
                    '<div class="ait-field-group">
                        <h4>%s</h4>
                        <div class="ait-field-content">%s</div>
                    </div>',
                    esc_html($key),
                    wp_kses_post($data['content'])
                );
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Format ACF fields for display
     *
     * @param array $fields
     * @return string Formatted HTML
     */
    private function format_acf_fields_for_display($fields) {
        $html = '';

        foreach ($fields as $key => $field) {
            switch ($field['type']) {
                case 'text':
                case 'textarea':
                case 'wysiwyg':
                    $html .= sprintf(
                        '<div class="ait-field-group">
                            <h4>%s</h4>
                            <div class="ait-field-content">%s</div>
                        </div>',
                        esc_html($field['label']),
                        wp_kses_post($field['content'])
                    );
                    break;

                case 'repeater':
                    if (!empty($field['rows'])) {
                        $html .= sprintf('<div class="ait-repeater-field"><h4>%s</h4>', esc_html($field['label']));
                        foreach ($field['rows'] as $index => $row) {
                            $html .= sprintf(
                                '<div class="ait-repeater-row"><h5>%s %d</h5>%s</div>',
                                __('Row', 'ai-translator'),
                                $index + 1,
                                $this->format_acf_fields_for_display($row)
                            );
                        }
                        $html .= '</div>';
                    }
                    break;

                case 'flexible_content':
                    if (!empty($field['layouts'])) {
                        $html .= sprintf('<div class="ait-flexible-field"><h4>%s</h4>', esc_html($field['label']));
                        foreach ($field['layouts'] as $layout) {
                            $html .= sprintf(
                                '<div class="ait-flexible-layout"><h5>%s</h5>%s</div>',
                                esc_html($layout['layout_type']),
                                $this->format_acf_fields_for_display($layout['fields'])
                            );
                        }
                        $html .= '</div>';
                    }
                    break;
            }
        }

        return $html;
    }

    /**
     * Format translation status for display
     *
     * @param array $status
     * @return string Formatted HTML
     */
    private function format_status_for_display($status) {
        $html = '<div class="ait-translation-status">';
        
        foreach ($status as $lang => $data) {
            $status_class = $data['exists'] ? 'complete' : 'pending';
            $status_text = $data['exists'] ? 
                sprintf(__('Translated (ID: %d)', 'ai-translator'), $data['post_id']) : 
                __('Not translated', 'ai-translator');

            $html .= sprintf(
                '<div class="ait-status-item %s">
                    <span class="ait-language">%s:</span>
                    <span class="ait-status">%s</span>
                </div>',
                esc_attr($status_class),
                esc_html($lang),
                esc_html($status_text)
            );
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Format bulk posts list for display
     *
     * @param array $posts
     * @return string Formatted HTML
     */
    private function format_bulk_posts_for_display($posts) {
        $html = '<div class="ait-bulk-posts-list">';
        $html .= '<h3>' . __('Selected Posts', 'ai-translator') . '</h3>';
        
        $html .= '<table class="ait-table">';
        $html .= '<thead><tr>';
        $html .= '<th>' . __('Title', 'ai-translator') . '</th>';
        $html .= '<th>' . __('Type', 'ai-translator') . '</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        foreach ($posts as $post) {
            $html .= sprintf(
                '<tr>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                esc_html($post['title']),
                esc_html($post['type'])
            );
        }

        $html .= '</tbody></table>';
        $html .= '</div>';

        return $html;
    }
}