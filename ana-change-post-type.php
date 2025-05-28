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
        'post_parent' => 114,
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

// Execute the post type change
//**TODO** -Remove parent page type for videos - these were all imported for some reason, and are affecting the permalink structure
function cptt_run_post_type_update()
{
    $query = new WP_Query([
        'post_type' => 'page',
        'post_parent' => 114,    // Customize this
        'posts_per_page' => -1,
    ]);

    

    foreach ($query->posts as $post) {

        wp_update_post([
            'ID' => $post->ID,
            'post_type' => 'history' // Change to your target post type
        ]);
    }
}
