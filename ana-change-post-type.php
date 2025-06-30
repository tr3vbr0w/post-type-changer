<?php

/**
 * Plugin Name: Change Post Type Tool
 * Description: Adds an admin page to bulk update post types for pages matching a specific WP_Query.
 * Version: 1.0
 * Author: Your Name
 */

// Register admin menu
add_action('admin_menu', function () {
    add_menu_page(
        'Change Post Type',
        'Post Type Changer',
        'manage_options',
        'change-post-type-tool',
        'cptt_render_admin_page',
        'dashicons-randomize',
        80
    );
    
    // Add submenu for post export
    add_submenu_page(
        'change-post-type-tool',
        'Export Posts',
        'Export Posts',
        'manage_options',
        'export-posts',
        'cptt_render_export_page'
    );
});

// Render admin page
function cptt_render_admin_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (isset($_POST['cptt_submit']) && check_admin_referer('cptt_action', 'cptt_nonce')) {
        cptt_run_post_type_update();
        echo '<div class="notice notice-success"><p>Post types updated successfully.</p></div>';
    }

    // Preview the query
    $query = new WP_Query([
        'post_type' => 'page', // Customize this to your target post type
        'post_parent' => 13,
        'posts_per_page' => -1,
    ]);

    echo '<div class="wrap">';
    echo '<h1>Change Post Type Tool</h1>';
    echo '<p>Found <strong>' . esc_html($query->found_posts) . '</strong> pages matching the query.</p>';

    echo '<form method="post">';
    wp_nonce_field('cptt_action', 'cptt_nonce');
    echo '<input type="submit" name="cptt_submit" class="button button-primary" value="Change Post Type to new_post_type">';
    echo '</form>';
    echo '</div>';
}

// Render export page
function cptt_render_export_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (isset($_POST['cptt_export']) && check_admin_referer('cptt_export_action', 'cptt_export_nonce')) {
        $post_type = sanitize_text_field($_POST['post_type']);
        cptt_export_posts($post_type);
        return;
    }

    // Get all registered post types
    $post_types = get_post_types(['public' => true], 'objects');

    echo '<div class="wrap">';
    echo '<h1>Export Posts by Post Type</h1>';
    echo '<form method="post">';
    wp_nonce_field('cptt_export_action', 'cptt_export_nonce');
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="post_type">Post Type:</label></th>';
    echo '<td>';
    echo '<select name="post_type" id="post_type" required>';
    echo '<option value="">Select a post type...</option>';
    foreach ($post_types as $post_type => $post_type_obj) {
        echo '<option value="' . esc_attr($post_type) . '">' . esc_html($post_type_obj->labels->name) . ' (' . esc_html($post_type) . ')</option>';
    }
    echo '</select>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="submit">';
    echo '<input type="submit" name="cptt_export" class="button button-primary" value="Export Posts">';
    echo '</p>';
    echo '</form>';
    echo '</div>';
}

/**
 * Export posts of a specific post type
 * 
 * @param string $post_type The post type to export
 * @return void
 */
function cptt_export_posts($post_type)
{
    // Validate post type
    if (!post_type_exists($post_type)) {
        wp_die('Invalid post type specified.');
    }

    // Query all posts of the specified type
    $posts = cptt_get_posts_by_type($post_type);
    
    if (empty($posts)) {
        echo '<div class="wrap">';
        echo '<h1>Export Posts by Post Type</h1>';
        echo '<div class="notice notice-warning"><p>No posts found for post type: <strong>' . esc_html($post_type) . '</strong></p></div>';
        echo '<p><a href="' . admin_url('admin.php?page=export-posts') . '" class="button">Back to Export</a></p>';
        echo '</div>';
        return;
    }

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="posts-' . $post_type . '-' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add CSV headers
    fputcsv($output, ['Post ID', 'Post Title', 'Post Type', 'Post Status', 'Post Date']);

    // Add data rows
    foreach ($posts as $post) {
        fputcsv($output, [
            $post['ID'],
            $post['post_title'],
            $post['post_type'],
            $post['post_status'],
            $post['post_date']
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Get posts of a specific post type
 * 
 * @param string $post_type The post type to query
 * @return array Array of posts with ID, title, and post type
 */
function cptt_get_posts_by_type($post_type)
{
    $query = new WP_Query([
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'post_status' => 'any', // Include all post statuses
        'orderby' => 'title',
        'order' => 'ASC'
    ]);

    $posts = [];
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post = get_post();
            
            $posts[] = [
                'ID' => $post->ID,
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'post_status' => $post->post_status,
                'post_date' => $post->post_date
            ];
        }
    }
    
    wp_reset_postdata();
    
    return $posts;
}

// Execute the post type change
//**TODO** -Remove parent page type for videos - these were all imported for some reason, and are affecting the permalink structure
function cptt_run_post_type_update()
{
    $query = new WP_Query([
        'post_type' => 'page',
        'post_parent' => 13,    // Customize this
        'posts_per_page' => -1,
    ]);

    

    foreach ($query->posts as $post) {

        wp_update_post([
            'ID' => $post->ID,
            'post_type' => 'travel' // Change to your target post type
        ]);
    }
}
 