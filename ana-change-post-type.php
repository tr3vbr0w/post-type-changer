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
    
    // Add submenu for CSV import
    add_submenu_page(
        'change-post-type-tool',
        'Import CSV',
        'Import CSV',
        'manage_options',
        'import-csv',
        'cptt_render_import_page'
    );
    
    // Add submenu for parent page analysis
    add_submenu_page(
        'change-post-type-tool',
        'Parent Page Analysis',
        'Parent Analysis',
        'manage_options',
        'parent-analysis',
        'cptt_render_parent_analysis_page'
    );
    
    // Add submenu for parent_page field analysis
    add_submenu_page(
        'change-post-type-tool',
        'Parent Page Field Analysis',
        'Parent Page Field',
        'manage_options',
        'parent-page-field',
        'cptt_render_parent_page_field_page'
    );
    
    // Add submenu for CSV title-based post type changes
    add_submenu_page(
        'change-post-type-tool',
        'CSV Title-Based Post Type Change',
        'CSV Title Match',
        'manage_options',
        'csv-title-match',
        'cptt_render_csv_title_match_page'
    );
    
    // Add submenu for automatic post type changes
    add_submenu_page(
        'change-post-type-tool',
        'Auto Post Type Change',
        'Auto Post Type',
        'manage_options',
        'auto-post-type',
        'cptt_render_auto_post_type_page'
    );
});

// Render admin page
function cptt_render_admin_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
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

// Render import page
function cptt_render_import_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (isset($_POST['cptt_import']) && check_admin_referer('cptt_import_action', 'cptt_import_nonce')) {
        cptt_process_csv_import();
    }

    echo '<div class="wrap">';
    echo '<h1>Import CSV to Change Post Types</h1>';
    echo '<p>Upload a CSV file with the following columns: <strong>page_slug</strong>, <strong>page_id</strong>, <strong>page_title</strong></p>';
    echo '<p>The system will verify each page by ID and slug before changing the post type to <strong>stories-essays</strong>.</p>';
    
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('cptt_import_action', 'cptt_import_nonce');
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="csv_file">CSV File:</label></th>';
    echo '<td>';
    echo '<input type="file" name="csv_file" id="csv_file" accept=".csv" required>';
    echo '<p class="description">Select a CSV file with page_slug, page_id, and page_title columns.</p>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="submit">';
    echo '<input type="submit" name="cptt_import" class="button button-primary" value="Process CSV and Change Post Types">';
    echo '</p>';
    echo '</form>';
    echo '</div>';
}

// Render parent analysis page
function cptt_render_parent_analysis_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Get all pages with parent pages
    $pages_with_parents = cptt_get_pages_with_parents();
    
    echo '<div class="wrap">';
    echo '<h1>Parent Page Analysis</h1>';
    echo '<p>This page shows all pages that have a parent page assigned.</p>';
    
    if (empty($pages_with_parents)) {
        echo '<div class="notice notice-info"><p>No pages with parent pages found.</p></div>';
    } else {
        echo '<h2>Pages with Parent Pages (' . count($pages_with_parents) . ' total)</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Page ID</th>';
        echo '<th>Page Title</th>';
        echo '<th>Page Slug</th>';
        echo '<th>Parent Page ID</th>';
        echo '<th>Parent Page Title</th>';
        echo '<th>Parent Page Slug</th>';
        echo '<th>Page Status</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($pages_with_parents as $page) {
            echo '<tr>';
            echo '<td>' . esc_html($page['ID']) . '</td>';
            echo '<td>' . esc_html($page['post_title']) . '</td>';
            echo '<td>' . esc_html($page['post_name']) . '</td>';
            echo '<td>' . esc_html($page['parent_id']) . '</td>';
            echo '<td>' . esc_html($page['parent_title']) . '</td>';
            echo '<td>' . esc_html($page['parent_slug']) . '</td>';
            echo '<td>' . esc_html($page['post_status']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Group by parent page for summary
        $parent_summary = [];
        foreach ($pages_with_parents as $page) {
            $parent_id = $page['parent_id'];
            if (!isset($parent_summary[$parent_id])) {
                $parent_summary[$parent_id] = [
                    'title' => $page['parent_title'],
                    'slug' => $page['parent_slug'],
                    'count' => 0,
                    'pages' => []
                ];
            }
            $parent_summary[$parent_id]['count']++;
            $parent_summary[$parent_id]['pages'][] = $page['post_title'];
        }
        
        echo '<h2>Summary by Parent Page</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Parent Page ID</th>';
        echo '<th>Parent Page Title</th>';
        echo '<th>Parent Page Slug</th>';
        echo '<th>Child Pages Count</th>';
        echo '<th>Child Pages</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($parent_summary as $parent_id => $parent_data) {
            echo '<tr>';
            echo '<td>' . esc_html($parent_id) . '</td>';
            echo '<td>' . esc_html($parent_data['title']) . '</td>';
            echo '<td>' . esc_html($parent_data['slug']) . '</td>';
            echo '<td>' . esc_html($parent_data['count']) . '</td>';
            echo '<td>' . esc_html(implode(', ', $parent_data['pages'])) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    echo '</div>';
}

// Render auto post type page
function cptt_render_auto_post_type_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (isset($_POST['cptt_auto_change']) && check_admin_referer('cptt_auto_action', 'cptt_auto_nonce')) {
        cptt_process_auto_post_type_changes();
    }

    // Get registered post types
    $registered_post_types = get_post_types(['public' => true], 'objects');
    
    // Get pages with parents for preview
    $pages_with_parents = cptt_get_pages_with_parents();
    $matching_pages = cptt_get_matching_pages_for_auto_change($pages_with_parents, $registered_post_types);

    echo '<div class="wrap">';
    echo '<h1>Auto Post Type Change</h1>';
    echo '<p>This tool will automatically change post types of pages based on their parent page slug matching registered post type slugs.</p>';
    
    if (empty($matching_pages)) {
        echo '<div class="notice notice-info"><p>No pages found that match the criteria for automatic post type changes.</p></div>';
    } else {
        echo '<h2>Pages That Would Be Changed (' . count($matching_pages) . ' total)</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Page ID</th>';
        echo '<th>Page Title</th>';
        echo '<th>Current Post Type</th>';
        echo '<th>Parent Page Slug</th>';
        echo '<th>Matching Post Type</th>';
        echo '<th>Page Status</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($matching_pages as $page) {
            echo '<tr>';
            echo '<td>' . esc_html($page['ID']) . '</td>';
            echo '<td>' . esc_html($page['post_title']) . '</td>';
            echo '<td>' . esc_html($page['current_post_type']) . '</td>';
            echo '<td>' . esc_html($page['parent_slug']) . '</td>';
            echo '<td>' . esc_html($page['matching_post_type']) . '</td>';
            echo '<td>' . esc_html($page['post_status']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        echo '<form method="post">';
        wp_nonce_field('cptt_auto_action', 'cptt_auto_nonce');
        echo '<p class="submit">';
        echo '<input type="submit" name="cptt_auto_change" class="button button-primary" value="Change Post Types Automatically">';
        echo '</p>';
        echo '</form>';
    }
    
    echo '<h2>Registered Post Types</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Post Type</th>';
    echo '<th>Label</th>';
    echo '<th>Slug</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($registered_post_types as $post_type => $post_type_obj) {
        echo '<tr>';
        echo '<td>' . esc_html($post_type) . '</td>';
        echo '<td>' . esc_html($post_type_obj->labels->name) . '</td>';
        echo '<td>' . esc_html($post_type) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    echo '</div>';
}

// Render parent page field analysis page
function cptt_render_parent_page_field_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (isset($_POST['cptt_change_parent_page_types']) && check_admin_referer('cptt_parent_page_action', 'cptt_parent_page_nonce')) {
        cptt_process_parent_page_field_changes();
    }

    // Get all pages with parent_page field values
    $pages_with_parent_page_field = cptt_get_pages_with_parent_page_field();
    
    echo '<div class="wrap">';
    echo '<h1>Parent Page Field Analysis</h1>';
    echo '<p>This page shows all pages that have a \'parent_page\' field assigned and allows you to change their post types.</p>';
    
    if (empty($pages_with_parent_page_field)) {
        echo '<div class="notice notice-info"><p>No pages with parent_page field found.</p></div>';
    } else {
        echo '<h2>Pages with Parent Page Field (' . count($pages_with_parent_page_field) . ' total)</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Page ID</th>';
        echo '<th>Page Title</th>';
        echo '<th>Page Slug</th>';
        echo '<th>Current Post Type</th>';
        echo '<th>Parent Page Field Value</th>';
        echo '<th>Parent Page Title</th>';
        echo '<th>Page Status</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($pages_with_parent_page_field as $page) {
            echo '<tr>';
            echo '<td>' . esc_html($page['ID']) . '</td>';
            echo '<td>' . esc_html($page['post_title']) . '</td>';
            echo '<td>' . esc_html($page['post_name']) . '</td>';
            echo '<td>' . esc_html($page['post_type']) . '</td>';
            echo '<td>' . esc_html($page['parent_page_id']) . '</td>';
            echo '<td>' . esc_html($page['parent_page_title']) . '</td>';
            echo '<td>' . esc_html($page['post_status']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Group by parent page for summary
        $parent_summary = [];
        foreach ($pages_with_parent_page_field as $page) {
            $parent_id = $page['parent_page_id'];
            if (!isset($parent_summary[$parent_id])) {
                $parent_summary[$parent_id] = [
                    'title' => $page['parent_page_title'],
                    'count' => 0,
                    'pages' => []
                ];
            }
            $parent_summary[$parent_id]['count']++;
            $parent_summary[$parent_id]['pages'][] = $page['post_title'];
        }
        
        echo '<h2>Summary by Parent Page Field Value</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Parent Page ID</th>';
        echo '<th>Parent Page Title</th>';
        echo '<th>Child Pages Count</th>';
        echo '<th>Child Pages</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($parent_summary as $parent_id => $parent_data) {
            echo '<tr>';
            echo '<td>' . esc_html($parent_id) . '</td>';
            echo '<td>' . esc_html($parent_data['title']) . '</td>';
            echo '<td>' . esc_html($parent_data['count']) . '</td>';
            echo '<td>' . esc_html(implode(', ', $parent_data['pages'])) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Add form to change post types based on parent_page field
        echo '<h2>Change Post Types Based on Parent Page Field</h2>';
        echo '<p>This will change the post type of pages based on their parent_page field value. Select a target post type for each parent page.</p>';
        
        echo '<form method="post">';
        wp_nonce_field('cptt_parent_page_action', 'cptt_parent_page_nonce');
        
        // Get all registered post types
        $registered_post_types = get_post_types(['public' => true], 'objects');
        
        echo '<table class="form-table">';
        foreach ($parent_summary as $parent_id => $parent_data) {
            echo '<tr>';
            echo '<th scope="row">' . esc_html($parent_data['title']) . ' (ID: ' . esc_html($parent_id) . ')</th>';
            echo '<td>';
            echo '<select name="parent_page_post_types[' . esc_attr($parent_id) . ']">';
            echo '<option value="">Keep current post type</option>';
            foreach ($registered_post_types as $post_type => $post_type_obj) {
                echo '<option value="' . esc_attr($post_type) . '">' . esc_html($post_type_obj->labels->name) . ' (' . esc_html($post_type) . ')</option>';
            }
            echo '</select>';
            echo '<p class="description">Affects ' . esc_html($parent_data['count']) . ' pages</p>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="cptt_change_parent_page_types" class="button button-primary" value="Change Post Types Based on Parent Page Field">';
        echo '</p>';
        echo '</form>';
    }
    
    echo '</div>';
}

/**
 * Get all pages that have a parent_page field assigned
 * 
 * @return array Array of pages with parent_page field information
 */
function cptt_get_pages_with_parent_page_field()
{
    $query = new WP_Query([
        'post_type' => 'page',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'orderby' => 'title',
        'order' => 'ASC'
    ]);

    $pages_with_parent_page_field = [];
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post = get_post();
            
            // Check if this page has a parent_page field
            $parent_page_value = get_field('parent_page', $post->ID);
            
            if ($parent_page_value) {
                $parent_page_id = '';
                $parent_page_title = '';
                
                // Handle different types of parent_page values
                if (is_array($parent_page_value)) {
                    if (isset($parent_page_value['ID'])) {
                        $parent_page_id = $parent_page_value['ID'];
                        $parent_page_title = $parent_page_value['post_title'];
                    } elseif (isset($parent_page_value[0]['ID'])) {
                        $parent_page_id = $parent_page_value[0]['ID'];
                        $parent_page_title = $parent_page_value[0]['post_title'];
                    }
                } elseif (is_numeric($parent_page_value)) {
                    $parent_page_id = $parent_page_value;
                    $parent_post = get_post($parent_page_value);
                    $parent_page_title = $parent_post ? $parent_post->post_title : 'Unknown';
                } elseif (is_string($parent_page_value)) {
                    $parent_page_id = $parent_page_value;
                    $parent_page_title = $parent_page_value;
                }
                
                if ($parent_page_id) {
                    $pages_with_parent_page_field[] = [
                        'ID' => $post->ID,
                        'post_title' => $post->post_title,
                        'post_name' => $post->post_name,
                        'post_type' => $post->post_type,
                        'post_status' => $post->post_status,
                        'parent_page_id' => $parent_page_id,
                        'parent_page_title' => $parent_page_title
                    ];
                }
            }
        }
    }
    
    wp_reset_postdata();
    
    return $pages_with_parent_page_field;
}

/**
 * Process post type changes based on parent_page field values
 * 
 * @return void
 */
function cptt_process_parent_page_field_changes()
{
    if (!isset($_POST['parent_page_post_types']) || !is_array($_POST['parent_page_post_types'])) {
        echo '<div class="notice notice-error"><p>No post type changes specified.</p></div>';
        return;
    }
    
    $parent_page_post_types = $_POST['parent_page_post_types'];
    $results = [
        'success' => 0,
        'errors' => [],
        'skipped' => 0
    ];
    
    // Get all pages with parent_page field
    $pages_with_parent_page_field = cptt_get_pages_with_parent_page_field();
    
    foreach ($pages_with_parent_page_field as $page) {
        $page_id = $page['ID'];
        $parent_page_id = $page['parent_page_id'];
        $current_post_type = $page['post_type'];
        
        // Check if there's a post type change specified for this parent page
        if (isset($parent_page_post_types[$parent_page_id]) && !empty($parent_page_post_types[$parent_page_id])) {
            $new_post_type = $parent_page_post_types[$parent_page_id];
            
            // Only change if the post type is different
            if ($current_post_type !== $new_post_type) {
                $update_result = wp_update_post([
                    'ID' => $page_id,
                    'post_type' => $new_post_type
                ], true);
                
                if (is_wp_error($update_result)) {
                    $results['errors'][] = "Page ID $page_id ({$page['post_title']}): Error updating post type to $new_post_type: " . $update_result->get_error_message();
                } else {
                    $results['success']++;
                }
            } else {
                $results['skipped']++;
            }
        }
    }
    
    echo '<div class="wrap">';
    echo '<h1>Parent Page Field Post Type Change Results</h1>';
    
    if ($results['success'] > 0) {
        echo '<div class="notice notice-success"><p>Successfully updated <strong>' . $results['success'] . '</strong> post types.</p></div>';
    }
    
    if ($results['skipped'] > 0) {
        echo '<div class="notice notice-info"><p>Skipped <strong>' . $results['skipped'] . '</strong> pages (already have the correct post type).</p></div>';
    }
    
    if (!empty($results['errors'])) {
        echo '<div class="notice notice-error"><p>Found <strong>' . count($results['errors']) . '</strong> errors:</p>';
        echo '<ul>';
        foreach ($results['errors'] as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }
    
    echo '<p><a href="' . admin_url('admin.php?page=parent-page-field') . '" class="button">Back to Parent Page Field Analysis</a></p>';
    echo '</div>';
}

// Render CSV title-based post type change page
function cptt_render_csv_title_match_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (isset($_POST['cptt_process_title_csv']) && check_admin_referer('cptt_title_csv_action', 'cptt_title_csv_nonce')) {
        cptt_process_title_based_csv_import();
    }

    // Get all registered post types for reference
    $registered_post_types = get_post_types(['public' => true], 'objects');

    echo '<div class="wrap">';
    echo '<h1>CSV Title-Based Post Type Change</h1>';
    echo '<p>Upload a CSV file to change post types based on page titles and parent slugs.</p>';
    
    echo '<h2>CSV Format Requirements</h2>';
    echo '<div class="notice notice-info">';
    echo '<p><strong>Required columns:</strong></p>';
    echo '<ul>';
    echo '<li><strong>Title</strong> - The exact title of the page to match</li>';
    echo '<li><strong>Parent Slug</strong> - The slug that should match a registered post type</li>';
    echo '</ul>';
    echo '<p><strong>Optional columns:</strong></p>';
    echo '<ul>';
    echo '<li><strong>Page ID</strong> - For additional verification (recommended)</li>';
    echo '<li><strong>Page Slug</strong> - For additional verification</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '<h2>How It Works</h2>';
    echo '<ol>';
    echo '<li>Upload a CSV file with the required columns</li>';
    echo '<li>The system will find posts by matching the "Title" column in both "page" and "stories-essays" post types</li>';
    echo '<li>If "Parent Slug" has a value, it will check if a post type exists with that slug</li>';
    echo '<li>If a matching post type is found, the post\'s post type will be changed</li>';
    echo '<li>If "Parent Slug" is empty, the post will be skipped</li>';
    echo '<li>All unmatched items will be listed in the results</li>';
    echo '</ol>';
    
    echo '<h2>Registered Post Types</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Post Type Slug</th>';
    echo '<th>Post Type Label</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($registered_post_types as $post_type => $post_type_obj) {
        echo '<tr>';
        echo '<td><code>' . esc_html($post_type) . '</code></td>';
        echo '<td>' . esc_html($post_type_obj->labels->name) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    echo '<h2>Upload CSV File</h2>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('cptt_title_csv_action', 'cptt_title_csv_nonce');
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="title_csv_file">CSV File:</label></th>';
    echo '<td>';
    echo '<input type="file" name="title_csv_file" id="title_csv_file" accept=".csv" required>';
    echo '<p class="description">Select a CSV file with Title and Parent Slug columns.</p>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="submit">';
    echo '<input type="submit" name="cptt_process_title_csv" class="button button-primary" value="Process CSV and Change Post Types">';
    echo '</p>';
    echo '</form>';
    echo '</div>';
}

/**
 * Process CSV import for title-based post type changes
 * 
 * @return void
 */
function cptt_process_title_based_csv_import()
{
    // Check if file was uploaded
    if (!isset($_FILES['title_csv_file']) || $_FILES['title_csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="notice notice-error"><p>Error uploading file. Please try again.</p></div>';
        return;
    }

    $file = $_FILES['title_csv_file'];
    
    // Validate file type
    if ($file['type'] !== 'text/csv' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        echo '<div class="notice notice-error"><p>Please upload a valid CSV file.</p></div>';
        return;
    }

    // Read CSV file
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        echo '<div class="notice notice-error"><p>Error reading CSV file.</p></div>';
        return;
    }

    // Read header row
    $headers = fgetcsv($handle);
    if (!$headers || count($headers) < 2) {
        echo '<div class="notice notice-error"><p>CSV file must have at least 2 columns: Title and Parent Slug</p></div>';
        fclose($handle);
        return;
    }

    // Validate headers
    $required_headers = ['Title', 'Parent Slug'];
    $header_map = array_flip($headers);
    
    foreach ($required_headers as $required) {
        if (!isset($header_map[$required])) {
            echo '<div class="notice notice-error"><p>CSV file must contain column: ' . esc_html($required) . '</p></div>';
            fclose($handle);
            return;
        }
    }

    // Get all registered post types
    $registered_post_types = get_post_types(['public' => true], 'objects');
    $post_type_slugs = array_keys($registered_post_types);

    $results = [
        'success' => 0,
        'errors' => [],
        'skipped' => 0,
        'not_found' => [],
        'unmatched' => []
    ];

    $row_number = 1; // Start at 1 since we already read the header
    
    // Process each row
    while (($row = fgetcsv($handle)) !== false) {
        $row_number++;
        
        if (count($row) < 2) {
            $results['errors'][] = "Row $row_number: Insufficient columns";
            continue;
        }

        $page_title = trim($row[$header_map['Title']]);
        $parent_slug = trim($row[$header_map['Parent Slug']]);
        
        // Optional fields for verification
        $page_id = isset($header_map['Page ID']) ? intval(trim($row[$header_map['Page ID']])) : null;
        $page_slug = isset($header_map['Page Slug']) ? trim($row[$header_map['Page Slug']]) : null;

        // Validate required data
        if (empty($page_title)) {
            $results['errors'][] = "Row $row_number: Title is required";
            continue;
        }

        // Skip if no parent slug provided
        if (empty($parent_slug)) {
            $results['skipped']++;
            continue;
        }

        // Check if parent slug matches a registered post type
        if (!in_array($parent_slug, $post_type_slugs)) {
            $results['errors'][] = "Row $row_number: Parent Slug '$parent_slug' does not match any registered post type";
            continue;
        }

        // Find the post by title in both 'page' and 'stories-essays' post types
        $post_query = new WP_Query([
            'post_type' => ['page'],
            'post_status' => 'any',
            'posts_per_page' => 1,
            'title' => $page_title,
            'meta_query' => []
        ]);

        if (!$post_query->have_posts()) {
            $results['not_found'][] = [
                'row' => $row_number,
                'title' => $page_title,
                'parent_slug' => $parent_slug,
                'reason' => 'No post found with this title in page post type'
            ];
            continue;
        }

        $post = $post_query->posts[0];
        wp_reset_postdata();

        // Additional verification if Page ID is provided
        if ($page_id && $post->ID !== $page_id) {
            $results['errors'][] = "Row $row_number: Page ID mismatch for title '$page_title' (expected: $page_id, found: {$post->ID})";
            continue;
        }

        // Additional verification if Page Slug is provided
        if ($page_slug && $post->post_name !== $page_slug) {
            $results['errors'][] = "Row $row_number: Page slug mismatch for title '$page_title' (expected: $page_slug, found: {$post->post_name})";
            continue;
        }

        // Check if post type is already correct
        if ($post->post_type === $parent_slug) {
            $results['unmatched'][] = [
                'row' => $row_number,
                'title' => $page_title,
                'current_post_type' => $post->post_type,
                'target_post_type' => $parent_slug,
                'reason' => 'Post already has the target post type'
            ];
            continue;
        }

        // Change post type
        $update_result = wp_update_post([
            'ID' => $post->ID,
            'post_type' => $parent_slug
        ], true);

        if (is_wp_error($update_result)) {
            $results['errors'][] = "Row $row_number: Error updating post type for '$page_title' (ID: {$post->ID}): " . $update_result->get_error_message();
        } else {
            $results['success']++;
        }
    }

    fclose($handle);

    // Display results
    echo '<div class="wrap">';
    echo '<h1>CSV Title-Based Post Type Change Results</h1>';
    
    if ($results['success'] > 0) {
        echo '<div class="notice notice-success"><p>Successfully updated <strong>' . $results['success'] . '</strong> post types.</p></div>';
    }
    
    if ($results['skipped'] > 0) {
        echo '<div class="notice notice-info"><p>Skipped <strong>' . $results['skipped'] . '</strong> posts (no parent slug provided).</p></div>';
    }
    
    if (!empty($results['not_found'])) {
        echo '<div class="notice notice-warning"><p>Could not find <strong>' . count($results['not_found']) . '</strong> posts by title.</p></div>';
    }
    
    if (!empty($results['unmatched'])) {
        echo '<div class="notice notice-warning"><p><strong>' . count($results['unmatched']) . '</strong> posts could not be changed.</p></div>';
    }
    
    if (!empty($results['errors'])) {
        echo '<div class="notice notice-error"><p>Found <strong>' . count($results['errors']) . '</strong> errors:</p>';
        echo '<ul>';
        foreach ($results['errors'] as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }
    
    // Display detailed table for not found items
    if (!empty($results['not_found'])) {
        echo '<h2>Posts Not Found by Title</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Row</th>';
        echo '<th>Title</th>';
        echo '<th>Parent Slug</th>';
        echo '<th>Reason</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($results['not_found'] as $item) {
            echo '<tr>';
            echo '<td>' . esc_html($item['row']) . '</td>';
            echo '<td>' . esc_html($item['title']) . '</td>';
            echo '<td>' . esc_html($item['parent_slug']) . '</td>';
            echo '<td>' . esc_html($item['reason']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    // Display detailed table for unmatched items
    if (!empty($results['unmatched'])) {
        echo '<h2>Posts That Could Not Be Changed</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Row</th>';
        echo '<th>Title</th>';
        echo '<th>Current Post Type</th>';
        echo '<th>Target Post Type</th>';
        echo '<th>Reason</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($results['unmatched'] as $item) {
            echo '<tr>';
            echo '<td>' . esc_html($item['row']) . '</td>';
            echo '<td>' . esc_html($item['title']) . '</td>';
            echo '<td>' . esc_html($item['current_post_type']) . '</td>';
            echo '<td>' . esc_html($item['target_post_type']) . '</td>';
            echo '<td>' . esc_html($item['reason']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    echo '<p><a href="' . admin_url('admin.php?page=csv-title-match') . '" class="button">Back to CSV Title Match</a></p>';
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

/**
 * Process CSV import and change post types
 * 
 * @return void
 */
function cptt_process_csv_import()
{
    // Check if file was uploaded
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="notice notice-error"><p>Error uploading file. Please try again.</p></div>';
        return;
    }

    $file = $_FILES['csv_file'];
    
    // Validate file type
    if ($file['type'] !== 'text/csv' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        echo '<div class="notice notice-error"><p>Please upload a valid CSV file.</p></div>';
        return;
    }

    // Read CSV file
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        echo '<div class="notice notice-error"><p>Error reading CSV file.</p></div>';
        return;
    }

    // Read header row
    $headers = fgetcsv($handle);
    if (!$headers || count($headers) < 3) {
        echo '<div class="notice notice-error"><p>CSV file must have at least 3 columns: page_slug, page_id, page_title</p></div>';
        fclose($handle);
        return;
    }

    // Validate headers
    $required_headers = ['page_slug', 'page_id', 'page_title'];
    $header_map = array_flip($headers);
    
    foreach ($required_headers as $required) {
        if (!isset($header_map[$required])) {
            echo '<div class="notice notice-error"><p>CSV file must contain column: ' . esc_html($required) . '</p></div>';
            fclose($handle);
            return;
        }
    }

    $results = [
        'success' => 0,
        'errors' => [],
        'skipped' => 0
    ];

    $row_number = 1; // Start at 1 since we already read the header
    
    // Process each row
    while (($row = fgetcsv($handle)) !== false) {
        $row_number++;
        
        if (count($row) < 3) {
            $results['errors'][] = "Row $row_number: Insufficient columns";
            continue;
        }

        $page_slug = trim($row[$header_map['page_slug']]);
        $page_id = intval(trim($row[$header_map['page_id']]));
        $page_title = trim($row[$header_map['page_title']]);

        // Validate data
        if (empty($page_slug) || empty($page_id) || empty($page_title)) {
            $results['errors'][] = "Row $row_number: Missing required data";
            continue;
        }

        // Get the post by ID
        $post = get_post($page_id);
        
        if (!$post) {
            $results['errors'][] = "Row $row_number: Page ID $page_id not found";
            continue;
        }

        // Verify it's a page
        if ($post->post_type !== 'page') {
            $results['errors'][] = "Row $row_number: Post ID $page_id is not a page (type: {$post->post_type})";
            continue;
        }

        // Verify the slug matches
        $current_slug = $post->post_name;
        if ($current_slug !== $page_slug) {
            $results['errors'][] = "Row $row_number: Slug mismatch for ID $page_id (expected: $page_slug, found: $current_slug)";
            continue;
        }

        // Verify the title matches (case-insensitive)
        if (strtolower($post->post_title) !== strtolower($page_title)) {
            $results['errors'][] = "Row $row_number: Title mismatch for ID $page_id (expected: $page_title, found: {$post->post_title})";
            continue;
        }

    }

    fclose($handle);

    // Display results
    echo '<div class="wrap">';
    echo '<h1>CSV Import Results</h1>';
    
    if ($results['success'] > 0) {
        echo '<div class="notice notice-success"><p>Successfully updated <strong>' . $results['success'] . '</strong> post types to stories-essays.</p></div>';
    }
    
    if (!empty($results['errors'])) {
        echo '<div class="notice notice-error"><p>Found <strong>' . count($results['errors']) . '</strong> errors:</p>';
        echo '<ul>';
        foreach ($results['errors'] as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }
    
    echo '<p><a href="' . admin_url('admin.php?page=import-csv') . '" class="button">Back to Import</a></p>';
    echo '</div>';
}

/**
 * Get all pages that have a parent page assigned
 * 
 * @return array Array of pages with parent information
 */
function cptt_get_pages_with_parents()
{
    $query = new WP_Query([
        'post_type' => 'page',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'meta_query' => [
            [
                'key' => '_wp_page_template',
                'compare' => 'EXISTS'
            ]
        ]
    ]);

    $pages_with_parents = [];
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post = get_post();
            
            // Only include pages that have a parent (post_parent > 0)
            if ($post->post_parent > 0) {
                // Get parent page information
                $parent = get_post($post->post_parent);
                
                if ($parent) {
                    $pages_with_parents[] = [
                        'ID' => $post->ID,
                        'post_title' => $post->post_title,
                        'post_name' => $post->post_name,
                        'post_status' => $post->post_status,
                        'parent_id' => $parent->ID,
                        'parent_title' => $parent->post_title,
                        'parent_slug' => $parent->post_name
                    ];
                }
            }
        }
    }
    
    wp_reset_postdata();
    
    return $pages_with_parents;
}

/**
 * Get pages that match the criteria for automatic post type changes.
 * 
 * @param array $pages_with_parents Array of pages with parent information.
 * @param array $registered_post_types Array of registered post types.
 * @return array Array of pages that would be changed.
 */
function cptt_get_matching_pages_for_auto_change($pages_with_parents, $registered_post_types)
{
    $matching_pages = [];

    foreach ($pages_with_parents as $page) {
        $parent_slug = $page['parent_slug'];
        
        // Get the current post type for this page
        $current_post = get_post($page['ID']);
        $current_post_type = $current_post ? $current_post->post_type : 'page';

        foreach ($registered_post_types as $post_type => $post_type_obj) {
            if (strtolower($parent_slug) === strtolower($post_type)) {
                $matching_pages[] = [
                    'ID' => $page['ID'],
                    'post_title' => $page['post_title'],
                    'current_post_type' => $current_post_type,
                    'parent_slug' => $parent_slug,
                    'matching_post_type' => $post_type,
                    'post_status' => $page['post_status']
                ];
                break; // Found a matching post type, move to the next page
            }
        }
    }

    return $matching_pages;
}

/**
 * Process automatic post type changes.
 * 
 * @return void
 */
function cptt_process_auto_post_type_changes()
{
    $matching_pages = cptt_get_matching_pages_for_auto_change(cptt_get_pages_with_parents(), get_post_types(['public' => true], 'objects'));

    if (empty($matching_pages)) {
        echo '<div class="wrap">';
        echo '<h1>Auto Post Type Change</h1>';
        echo '<div class="notice notice-info"><p>No pages found that match the criteria for automatic post type changes.</p></div>';
        echo '<p><a href="' . admin_url('admin.php?page=auto-post-type') . '" class="button">Back to Auto Post Type Change</a></p>';
        echo '</div>';
        return;
    }

    $results = [
        'success' => 0,
        'errors' => [],
        'skipped' => 0
    ];

    foreach ($matching_pages as $page) {
        $page_id = $page['ID'];
        $current_post_type = $page['current_post_type'];
        $new_post_type = $page['matching_post_type'];

        // Only change if the post type is not already the new one
        if ($current_post_type !== $new_post_type) {
            $update_result = wp_update_post([
                'ID' => $page_id,
                'post_type' => $new_post_type
            ], true);

            if (is_wp_error($update_result)) {
                $results['errors'][] = "Page ID $page_id: Error updating post type to $new_post_type: " . $update_result->get_error_message();
            } else {
                $results['success']++;
            }
        } else {
            $results['skipped']++;
        }
    }

    echo '<div class="wrap">';
    echo '<h1>Auto Post Type Change Results</h1>';
    
    if ($results['success'] > 0) {
        echo '<div class="notice notice-success"><p>Successfully updated <strong>' . $results['success'] . '</strong> post types.</p></div>';
    }
    
    if (!empty($results['errors'])) {
        echo '<div class="notice notice-error"><p>Found <strong>' . count($results['errors']) . '</strong> errors:</p>';
        echo '<ul>';
        foreach ($results['errors'] as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }
    
    echo '<p><a href="' . admin_url('admin.php?page=auto-post-type') . '" class="button">Back to Auto Post Type Change</a></p>';
    echo '</div>';
}


 