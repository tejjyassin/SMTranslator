<!-- File: admin/views/main-page.php -->

<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap ait-container">
    <h1 class="wp-heading-inline"><?php _e('AI Translator', 'ai-translator'); ?></h1>
    
    <!-- Bulk Actions -->
    <div class="ait-bulk-actions">
        <label>
            <input type="checkbox" id="ait-select-all">
            <?php _e('Select All', 'ai-translator'); ?>
        </label>
        <button id="ait-bulk-translate" class="ait-button">
            <?php _e('Bulk Translate', 'ai-translator'); ?>
        </button>
    </div>

    <!-- Posts Table -->
    <table class="ait-table">
        <thead>
            <tr>
                <th class="check-column">
                    <span class="screen-reader-text"><?php _e('Select', 'ai-translator'); ?></span>
                </th>
                <th><?php _e('Title', 'ai-translator'); ?></th>
                <th><?php _e('Type', 'ai-translator'); ?></th>
                <th><?php _e('Language', 'ai-translator'); ?></th>
                <th><?php _e('Translation Status', 'ai-translator'); ?></th>
                <th><?php _e('Actions', 'ai-translator'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $posts_query = new WP_Query([
                'post_type' => get_option('ait_post_types', ['post', 'page']),
                'posts_per_page' => 20,
                'paged' => get_query_var('paged') ? get_query_var('paged') : 1
            ]);

            if ($posts_query->have_posts()) :
                while ($posts_query->have_posts()) : $posts_query->the_post();
                    $post_id = get_the_ID();
                    $post_type_obj = get_post_type_object(get_post_type());
                    $translation_status = apply_filters('ait_translation_status', [], $post_id);
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="ait-post-checkbox" value="<?php echo esc_attr($post_id); ?>">
                        </td>
                        <td>
                            <strong><?php the_title(); ?></strong>
                        </td>
                        <td>
                            <?php echo esc_html($post_type_obj->labels->singular_name); ?>
                        </td>
                        <td>
                            <?php echo esc_html(apply_filters('ait_post_language', 'fr', $post_id)); ?>
                        </td>
                        <td>
                            <?php
                            foreach ($translation_status as $lang => $status) {
                                $status_class = $status['exists'] ? 'completed' : 'pending';
                                printf(
                                    '<span class="ait-status %s">%s: %s</span><br>',
                                    esc_attr($status_class),
                                    esc_html($lang),
                                    esc_html($status['exists'] ? __('Completed', 'ai-translator') : __('Pending', 'ai-translator'))
                                );
                            }
                            ?>
                        </td>
                        <td>
                            <button class="ait-button ait-translate-button" data-post-id="<?php echo esc_attr($post_id); ?>">
                                <?php _e('Translate', 'ai-translator'); ?>
                            </button>
                        </td>
                    </tr>
                <?php
                endwhile;
            else :
                ?>
                <tr>
                    <td colspan="6"><?php _e('No posts found.', 'ai-translator'); ?></td>
                </tr>
            <?php
            endif;
            wp_reset_postdata();
            ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $posts_query->max_num_pages,
                'current' => max(1, get_query_var('paged'))
            ]);
            ?>
        </div>
    </div>
</div>

<!-- Translation Modal -->
<div id="ait-translation-modal" class="ait-modal">
    <div class="ait-modal-content">
        <span class="ait-modal-close">&times;</span>
        <h2><?php _e('Translate Content', 'ai-translator'); ?></h2>
        
        <form id="ait-translation-form">
            <div class="ait-language-selector">
                <h3><?php _e('Select Target Languages', 'ai-translator'); ?></h3>
                <select id="ait-target-languages" multiple>
                    <?php
                    $target_languages = [
                        'en' => __('English', 'ai-translator'),
                        'ar' => __('Arabic', 'ai-translator'),
                        'es' => __('Spanish', 'ai-translator')
                    ];
                    foreach ($target_languages as $code => $name) {
                        printf(
                            '<option value="%s">%s</option>',
                            esc_attr($code),
                            esc_html($name)
                        );
                    }
                    ?>
                </select>
            </div>

            <div class="ait-translation-container">
                <div class="ait-content-box">
                    <h3><?php _e('Original Content', 'ai-translator'); ?></h3>
                    <div id="ait-original-content"></div>
                </div>
                <div class="ait-content-box">
                    <h3><?php _e('Translation Status', 'ai-translator'); ?></h3>
                    <div id="ait-translation-status"></div>
                </div>
            </div>

            <input type="hidden" id="ait-post-id" value="">
            <button type="submit" class="ait-button">
                <?php _e('Start Translation', 'ai-translator'); ?>
            </button>
        </form>
    </div>
</div>

<!-- Bulk Translation Modal -->
<div id="ait-bulk-translation-modal" class="ait-modal">
    <div class="ait-modal-content">
        <span class="ait-modal-close">&times;</span>
        <h2><?php _e('Bulk Translation', 'ai-translator'); ?></h2>
        
        <form id="ait-bulk-translation-form">
            <div class="ait-language-selector">
                <h3><?php _e('Select Target Languages', 'ai-translator'); ?></h3>
                <select id="ait-bulk-target-languages" multiple>
                    <?php
                    foreach ($target_languages as $code => $name) {
                        printf(
                            '<option value="%s">%s</option>',
                            esc_attr($code),
                            esc_html($name)
                        );
                    }
                    ?>
                </select>
            </div>

            <div id="ait-bulk-posts-list"></div>

            <input type="hidden" id="ait-bulk-post-ids" value="">
            <button type="submit" class="ait-button">
                <?php _e('Start Bulk Translation', 'ai-translator'); ?>
            </button>
        </form>
    </div>
</div>