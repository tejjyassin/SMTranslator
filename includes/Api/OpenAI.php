<?php
namespace AITranslator\Api;

class OpenAI {
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';

    public function __construct() {
        $this->api_key = get_option('ait_openai_api_key');
    }

    public function translate($content, $target_language) {
        try {
            if (empty($this->api_key)) {
                throw new \Exception('OpenAI API key is not configured');
            }

            $prompt = sprintf(
                "Translate this text to %s. Preserve all HTML tags and formatting:\n\n%s",
                $target_language,
                $content
            );
            
            $response = wp_remote_post($this->api_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 60,
                'body' => json_encode([
                    'model' => 'gpt-4',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "You are a professional translator. Maintain formatting and structure."
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.3
                ])
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (!isset($body['choices'][0]['message']['content'])) {
                throw new \Exception('Invalid response format from OpenAI API');
            }

            return [
                'success' => true,
                'content' => $body['choices'][0]['message']['content']
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}