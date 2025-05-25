<?php
// Add custom column to Media Library
function wpa_upload_columns($columns) {
    unset($columns['parent']);
    $columns['wpattachments_parent'] = esc_html__('Parent', 'wp-attachments');
    return $columns;
}

// Render custom column content
function wpa_media_custom_columns($column_name, $id) {
    if ($column_name !== 'wpattachments_parent') {
        return;
    }

    $post = get_post($id);
    if (!$post) {
        echo esc_html__('Not found', 'wp-attachments');
        return;
    }

    $can_edit = current_user_can('edit_post', $post->ID);

    echo '<div class="wpa-parent-cell">';

    if ($post->post_parent > 0 && get_post($post->post_parent)) {
        $title = esc_html(get_the_title($post->post_parent));
        $edit_link = get_edit_post_link($post->post_parent);
        // Use the attachment's upload date
        $date = esc_html(date_i18n(get_option('date_format'), strtotime($post->post_date)));

        echo '<strong><a href="' . esc_url($edit_link) . '">' . $title . '</a></strong><br />';
        echo '<span class="description">' . $date . '</span>';

        if ($can_edit) {
            echo '<div class="wpa-btn-group">';
            echo '<a class="button button-secondary button-small hide-if-no-js" onclick="findPosts.open(\'media[]\',\'' . esc_attr($post->ID) . '\');return false;" href="#the-list">';
            esc_html_e('Re-Attach', 'wp-attachments');
            echo '</a>';

            $url = wp_nonce_url(admin_url('tools.php?page=unattach&noheader=true&id=' . absint($post->ID)), 'wpa_unattach_' . $post->ID);
            echo '<a class="button button-link-delete button-small" href="' . esc_url($url) . '">';
            esc_html_e('Unattach', 'wp-attachments');
            echo '</a>';
            echo '</div>';
        }
    } else {
        echo '<strong>' . esc_html__('No parent.', 'wp-attachments') . '</strong>';
        if ($can_edit) {
            echo '<div class="wpa-btn-group">';
            echo '<a class="button button-primary button-small hide-if-no-js" onclick="findPosts.open(\'media[]\',\'' . esc_attr($post->ID) . '\');return false;" href="#the-list">';
            esc_html_e('Attach', 'wp-attachments');
            echo '</a>';
            echo '</div>';
        }
    }

    echo '</div>';
}

// Add custom CSS and hooks
function wpa_custom_admin_css() {
    echo '<style>
        #wpattachments_parent { width: 15%; }
        .wpa-parent-cell { min-width: 180px; }
        .wpa-btn-group {
            display: flex;
            gap: 6px;
            margin-top: 8px;
        }
        .wpa-parent-cell .button { margin-bottom: 0; }
        .wpa-parent-cell .button-link-delete {
            color: #b32d2e;
            border-color: #b32d2e;
            background: #fff;
        }
        .wpa-parent-cell .button-link-delete:hover {
            background: #fbeaea;
            color: #a00;
            border-color: #a00;
        }
    </style>';
}
add_action('admin_head', 'wpa_custom_admin_css');
add_filter('manage_upload_columns', 'wpa_upload_columns');
add_action('manage_media_custom_column', 'wpa_media_custom_columns', 10, 2);

// Unattach action with nonce check
function wpa_unattach_do_it() {
    global $wpdb;

    if (!empty($_REQUEST['id'])) {
        $id = absint($_REQUEST['id']);
        if (!current_user_can('edit_post', $id)) {
            wp_die(__('You do not have permission.', 'wp-attachments'));
        }
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wpa_unattach_' . $id)) {
            wp_die(__('Security check failed.', 'wp-attachments'));
        }
        $wpdb->update($wpdb->posts, array('post_parent' => 0), array('ID' => $id, 'post_type' => 'attachment'));
    }
    wp_redirect(admin_url('upload.php?mode=list'));
    exit;
}

// Register submenu for unattach
function wpa_unattach_init() {
    if (current_user_can('upload_files')) {
        add_submenu_page('tools.php', __('Unattach Media', 'wp-attachments'), __('Unattach', 'wp-attachments'), 'upload_files', 'unattach', 'wpa_unattach_do_it');
        remove_submenu_page('tools.php', 'unattach');
    }
}
add_action('admin_menu', 'wpa_unattach_init');
?>