
<?php
namespace AITranslator\Translation;

class Queue {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ait_queue';
    }

    /**
     * Add items to translation queue
     *
     * @param array $post_ids Array of post IDs
     * @param array $languages Target languages
     * @return bool|WP_Error
     */
    public function add_to_queue($post_ids, $languages) {
        global $wpdb;

        if (empty($post_ids) || empty($languages)) {
            return new \WP_Error('invalid_input', __('Invalid post IDs or languages', 'ai-translator'));
        }

        $values = [];
        $placeholders = [];
        $current_time = current_time('mysql');

        foreach ($post_ids as $post_id) {
            foreach ($languages as $lang) {
                $values[] = $post_id;
                $values[] = $lang;
                $values[] = 'pending';
                $values[] = $current_time;
                $placeholders[] = '(%d, %s, %s, %s)';
            }
        }

        $query = $wpdb->prepare(
            "INSERT INTO {$this->table_name} (post_id, target_language, status, created_at) VALUES " . 
            implode(', ', $placeholders),
            $values
        );

        $result = $wpdb->query($query);

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to add items to queue', 'ai-translator'));
        }

        // Schedule processing if not already scheduled
        if (!wp_next_scheduled('ait_process_translation_queue')) {
            wp_schedule_single_event(time() + 60, 'ait_process_translation_queue');
        }

        return true;
    }

    /**
     * Process items in the queue
     *
     * @param int $batch_size Number of items to process
     * @return void
     */
    public function process_queue($batch_size = 5) {
        global $wpdb;

        // Get items to process
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE status = 'pending' 
            ORDER BY created_at ASC 
            LIMIT %d",
            $batch_size
        ));

        if (empty($items)) {
            return;
        }

        $translator = new Manager();

        foreach ($items as $item) {
            // Update status to processing
            $wpdb->update(
                $this->table_name,
                ['status' => 'processing'],
                ['id' => $item->id]
            );

            // Attempt translation
            $result = $translator->translate_post($item->post_id, $item->target_language);

            // Update status based on result
            $status = is_wp_error($result) ? 'failed' : 'completed';
            $error_message = is_wp_error($result) ? $result->get_error_message() : '';

            $wpdb->update(
                $this->table_name,
                [
                    'status' => $status,
                    'error_message' => $error_message,
                    'completed_at' => current_time('mysql')
                ],
                ['id' => $item->id]
            );
        }

        // Check if more items need processing
        $pending_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending'"
        );

        if ($pending_count > 0) {
            wp_schedule_single_event(time() + 60, 'ait_process_translation_queue');
        }
    }

    /**
     * Get queue status
     *
     * @return array Queue statistics
     */
    public function get_queue_status() {
        global $wpdb;

        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
            FROM {$this->table_name} 
            GROUP BY status",
            ARRAY_A
        );

        $status = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        ];

        foreach ($status_counts as $count) {
            $status[$count['status']] = (int) $count['count'];
        }

        return $status;
    }

    /**
     * Clear completed and failed items
     *
     * @param int $days_old Clear items older than this many days
     * @return int Number of items cleared
     */
    public function clear_old_items($days_old = 7) {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} 
            WHERE status IN ('completed', 'failed') 
            AND completed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_old
        ));
    }

    /**
     * Retry failed items
     *
     * @return int Number of items reset
     */
    public function retry_failed() {
        global $wpdb;

        return $wpdb->query(
            "UPDATE {$this->table_name} 
            SET status = 'pending', 
                error_message = '', 
                completed_at = NULL 
            WHERE status = 'failed'"
        );
    }

    /**
     * Get queue items for specific posts
     *
     * @param array $post_ids Array of post IDs
     * @return array Queue items
     */
    public function get_items_by_posts($post_ids) {
        global $wpdb;

        if (empty($post_ids)) {
            return [];
        }

        $placeholders = array_fill(0, count($post_ids), '%d');
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE post_id IN (" . implode(',', $placeholders) . ")",
            $post_ids
        ));
    }
}