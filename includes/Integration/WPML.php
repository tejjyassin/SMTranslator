<?php
namespace AITranslator\Integration;

class WPML {
    private $sitepress;
    
    public function __construct() {
        global $sitepress;
        $this->sitepress = $sitepress;
    }

    /**
     * Check if WPML is active and configured
     *
     * @return bool
     */
    public function is_active() {
        return defined('ICL_SITEPRESS_VERSION') && !empty($this->sitepress);
    }

    /**
     * Get available languages from WPML
     *
     * @return array Array of language codes and names
     */
    public function get_languages() {
        if (!$this->is_active()) {
            return [];
        }

        $languages = [];
        $wpml_languages = $this->sitepress->get_active_languages();
        
        foreach ($wpml_languages as $code => $language) {
            $languages[$code] = [
                'code' => $code,
                'name' => $language['display_name'],
                'native_name' => $language['native_name'],
                'default' => $this->sitepress->get_default_language() === $code
            ];
        }

        return $languages;
    }

    /**
     * Create WPML translation entry
     *
     * @param int $post_id Original post ID
     * @param string $target_lang Target language code
     * @return int|WP_Error New translation ID or error
     */
    public function create_translation($post_id, $target_lang) {
        if (!$this->is_active()) {
            return new \WP_Error('wpml_inactive', __('WPML is not active', 'ai-translator'));
        }

        // Get original post language
        $source_lang = $this->sitepress->get_language_for_element($post_id, 'post_' . get_post_type($post_id));

        // Check if translation already exists
        $translation_id = $this->sitepress->get_element_trid($post_id);
        if ($translation_id) {
            $translations = $this->sitepress->get_element_translations($translation_id);
            if (isset($translations[$target_lang])) {
                return new \WP_Error(
                    'translation_exists',
                    __('Translation already exists for this language', 'ai-translator')
                );
            }
        }

        // Get original post
        $post = get_post($post_id);
        if (!$post) {
            return new \WP_Error('invalid_post', __('Invalid post ID', 'ai-translator'));
        }

        // Create translation post
        $translation_data = [
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_type' => $post->post_type,
            'post_status' => 'draft',
            'post_author' => get_current_user_id()
        ];

        $translation_post_id = wp_insert_post($translation_data);
        if (is_wp_error($translation_post_id)) {
            return $translation_post_id;
        }

        // Set language information
        $this->sitepress->set_element_language_details(
            $translation_post_id,
            'post_' . $post->post_type,
            $translation_id,
            $target_lang,
            $source_lang
        );

        return $translation_post_id;
    }

    /**
     * Update WPML translation content
     *
     * @param int $translation_id Translation post ID
     * @param array $content Translated content array
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_translation($translation_id, $content) {
        if (!$this->is_active()) {
            return new \WP_Error('wpml_inactive', __('WPML is not active', 'ai-translator'));
        }

        $update_data = [
            'ID' => $translation_id
        ];

        if (isset($content['post_title'])) {
            $update_data['post_title'] = $content['post_title'];
        }

        if (isset($content['post_content'])) {
            $update_data['post_content'] = $content['post_content'];
        }

        if (isset($content['post_excerpt'])) {
            $update_data['post_excerpt'] = $content['post_excerpt'];
        }

        $result = wp_update_post($update_data, true);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Get translation status
     *
     * @param int $post_id Post ID
     * @return array Translation status for each language
     */
    public function get_translation_status($post_id) {
        if (!$this->is_active()) {
            return [];
        }

        $translation_id = $this->sitepress->get_element_trid($post_id);
        if (!$translation_id) {
            return [];
        }

        $translations = $this->sitepress->get_element_translations($translation_id);
        $status = [];

        foreach ($this->get_languages() as $code => $language) {
            $status[$code] = [
                'exists' => isset($translations[$code]),
                'post_id' => isset($translations[$code]) ? $translations[$code]->element_id : null,
                'status' => isset($translations[$code]) ? get_post_status($translations[$code]->element_id) : 'none'
            ];
        }

        return $status;
    }
}