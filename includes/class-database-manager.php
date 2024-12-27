
<?php
namespace AITranslator;

class Database_Manager {
    /**
     * DB version
     */
    private $db_version = '1.0.0';

    /**
     * Tables to create
     */
    private $tables = [
        'translations' => "
            CREATE TABLE IF NOT EXISTS {prefix}ait_translations (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                post_id bigint(20) unsigned NOT NULL,
                source_language varchar(10) NOT NULL,
                target_language varchar(10) NOT NULL,
                original_content longtext NOT NULL,
                translated_content longtext NOT NULL,
                translation_status varchar(20) NOT NULL DEFAULT 'pending',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY post_id (post_id),
                KEY translation_status (translation_status)
            ) {charset_collate};
        ",
        'queue' => "
            CREATE TABLE IF NOT EXISTS {prefix}ait_queue (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                post_id bigint(20) unsigned NOT NULL,
                target_language varchar(10) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                error_message text,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY post_id (post_id),
                KEY status (status)
            ) {charset_collate};
        "
    ];

    /**
     * Install database tables
     */
    public function install() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();

        foreach ($this->tables as $table => $sql) {
            $sql = str_replace(
                ['{prefix}', '{charset_collate}'],
                [$wpdb->prefix, $charset_collate],
                $sql
            );
            
            dbDelta($sql);
        }

        $this->maybe_update();
        
        add_option('ait_db_version', $this->db_version);
    }

    /**
     * Check if database needs updating
     */
    public function maybe_update() {
        if (get_option('ait_db_version') != $this->db_version) {
            $this->update();
        }
    }

    /**
     * Update database
     */
    private function update() {
        $installed_version = get_option('ait_db_version');
        
        // Add update logic here when needed
        // Example:
        // if (version_compare($installed_version, '1.1.0', '<')) {
        //     $this->update_to_110();
        // }
        
        update_option('ait_db_version', $this->db_version);
    }

    /**
     * Remove plugin tables
     */
    public function uninstall() {
        global $wpdb;

        foreach (array_keys($this->tables) as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ait_{$table}");
        }

        delete_option('ait_db_version');
    }

    /**
     * Get table name
     *
     * @param string $table Table name without prefix
     * @return string Full table name with prefix
     */
    public function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'ait_' . $table;
    }

    /**
     * Check if tables exist
     *
     * @return bool
     */
    public function tables_exist() {
        global $wpdb;
        
        foreach (array_keys($this->tables) as $table) {
            $table_name = $this->get_table_name($table);
            $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if ($result != $table_name) {
                return false;
            }
        }
        
        return true;
    }
}