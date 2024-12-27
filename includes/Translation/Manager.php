<?php
namespace AITranslator\Translation;

use AITranslator\Api\OpenAI;

class Manager {
    private $openai;

    public function __construct() {
        $this->openai = new OpenAI();
    }

    public function translate_post($post_id, $target_language) {
        $post = get_post($post_id);
        if (!$post) {   
            return false;
        }

        // Translate title
        $translated_title = $this->openai->translate($post->post_title, $target_language);
        if (is_wp_error($translated_title)) {
            return $translated_title;
        }

        // Translate content
        $translated_content = $this->openai->translate($post->post_content, $target_language);
        if (is_wp_error($translated_content)) {
            return $translated_content;
        }

        // Create the translated post
        $translated_post = array(
            'post_title'   => $translated_title,
            'post_content' => $translated_content,
            'post_status'  => 'draft',
            'post_author'  => get_current_user_id(),
            'post_type'    => $post->post_type,
        );

        // Insert the translated post
        $translated_post_id = wp_insert_post($translated_post);

        if (is_wp_error($translated_post_id)) {
            return $translated_post_id;
        }

        // Store translation metadata
        update_post_meta($translated_post_id, '_ait_original_post', $post_id);
        update_post_meta($translated_post_id, '_ait_language', $target_language);
        update_post_meta($post_id, '_ait_translation_' . $target_language, $translated_post_id);

        return $translated_post_id;
    }
}