
<?php
namespace AITranslator;

class Notice_Handler {
    /**
     * Notice queue
     */
    private $notices = [];

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_notices', [$this, 'display_notices']);
        add_action('wp_ajax_ait_dismiss_notice', [$this, 'dismiss_notice']);
    }

    /**
     * Add notice to queue
     *
     * @param string $message Notice message
     * @param string $type Notice type (error, warning, success, info)
     * @param bool $dismissible Whether the notice is dismissible
     * @param string $id Unique identifier for the notice
     */
    public function add_notice($message, $type = 'info', $dismissible = true, $id = '') {
        if ($id && $this->is_dismissed($id)) {
            return;
        }

        $this->notices[] = [
            'message' => $message,
            'type' => $type,
            'dismissible' => $dismissible,
            'id' => $id
        ];
    }

    /**
     * Display all queued notices
     */
    public function display_notices() {
        foreach ($this->notices as $notice) {
            $class = 'notice notice-' . $notice['type'];
            if ($notice['dismissible']) {
                $class .= ' is-dismissible';
            }

            $notice_id = $notice['id'] ? ' data-notice-id="' . esc_attr($notice['id']) . '"' : '';

            printf(
                '<div class="%1$s"%2$s><p>%3$s</p></div>',
                esc_attr($class),
                $notice_id,
                wp_kses_post($notice['message'])
            );
        }

        if (!empty($this->notices)) {
            $this->enqueue_dismissible_script();
        }
    }

    /**
     * Enqueue script for dismissible notices
     */
    private function enqueue_dismissible_script() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.notice.is-dismissible[data-notice-id]').on('click', '.notice-dismiss', function() {
                var $notice = $(this).closest('.notice');
                var noticeId = $notice.data('notice-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ait_dismiss_notice',
                        notice_id: noticeId,
                        nonce: '<?php echo wp_create_nonce('ait_dismiss_notice'); ?>'
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for dismissing notices
     */
    public function dismiss_notice() {
        if (!check_ajax_referer('ait_dismiss_notice', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token', 'ai-translator')]);
        }

        $notice_id = isset($_POST['notice_id']) ? sanitize_key($_POST['notice_id']) : '';
        
        if ($notice_id) {
            $dismissed_notices = get_option('ait_dismissed_notices', []);
            $dismissed_notices[$notice_id] = time();
            update_option('ait_dismissed_notices', $dismissed_notices);
        }

        wp_send_json_success();
    }

    /**
     * Check if a notice has been dismissed
     *
     * @param string $id Notice ID
     * @return bool
     */
    private function is_dismissed($id) {
        $dismissed_notices = get_option('ait_dismissed_notices', []);
        return isset($dismissed_notices[$id]);
    }

    /**
     * Add configuration check notices
     */
    public function check_configuration() {
        // Check API key
        if (!get_option('ait_api_key')) {
            $this->add_notice(
                sprintf(
                    __('AI Translator requires an OpenAI API key. Please add it in the <a href="%s">settings page</a>.', 'ai-translator'),
                    admin_url('admin.php?page=ai-translator-settings')
                ),
                'error',
                true,
                'missing_api_key'
            );
        }

        // Check WPML
        if (!defined('ICL_SITEPRESS_VERSION')) {
            $this->add_notice(
                __('AI Translator requires WPML to be installed and activated.', 'ai-translator'),
                'error',
                true,
                'missing_wpml'
            );
        }

        // Check ACF
        if (!class_exists('ACF')) {
            $this->add_notice(
                __('AI Translator requires Advanced Custom Fields (ACF) to be installed and activated.', 'ai-translator'),
                'error',
                true,
                'missing_acf'
            );
        }

        // Check database tables
        $db_manager = new Database_Manager();
        if (!$db_manager->tables_exist()) {
            $this->add_notice(
                __('AI Translator database tables are missing. Please deactivate and reactivate the plugin.', 'ai-translator'),
                'error',
                true,
                'missing_tables'
            );
        }
    }
}