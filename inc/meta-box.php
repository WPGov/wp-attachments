<?php

class WP_Attachments
{
    private $actions = array(
        'add_meta_boxes', 'admin_enqueue_scripts',
        'wp_ajax_wpa_realign', 'wp_ajax_wpa_attach_media' // renamed from ij_
    );

    private static $instance;

    private function __construct()
    {
        foreach ($this->actions as $action)
            add_action($action, array($this, $action));
    }

    static public function getInstance()
    {
        if (!isset(self::$instance))
            self::$instance = new WP_Attachments();

        return self::$instance;
    }

    public function __clone()
    {
        throw new Exception("Clone is disallowed.");
    }

    public function add_meta_boxes()
    {
        // Get all post types that support attachments
        $post_types = get_post_types(array('public' => true), 'names');
        foreach ($post_types as $post_type) {
            if ($post_type === 'attachment') continue;
            // Check if enabled for this post type
            if (get_option('wpatt_enable_metabox_' . $post_type, '1') !== '1') continue;
            add_meta_box(
                'wpa-attachments',
                __('Media Attachments', 'wp-attachments'),
                array($this, 'printMetaBox'),
                $post_type,
                'normal',
                'high',
                array(
                    '__back_compat_meta_box' => false,
                    '__block_editor_compatible_meta_box' => true
                )
            );
        }
    }

    public function admin_enqueue_scripts()
    {
        global $hook_suffix;
        // Remove this check as it prevents loading in Gutenberg
        // if (!in_array($hook_suffix, array('post.php', 'post-new.php'))) {
        //     return;
        // }

        wp_enqueue_media();

        wp_enqueue_script(
            'wp-attachments',
            plugin_dir_url(__FILE__) . 'scripts/metabox.js',
            // Add wp-editor as dependency
            array('jquery-ui-sortable', 'wp-i18n', 'wp-editor', 'wp-blocks', 'wp-components'),
            '0.4.0'
        );

        // Add script for Gutenberg compatibility
        wp_add_inline_script('wp-attachments', '
            wp.domReady(function() {
                // Re-initialize metabox functionality after Gutenberg loads
                if (window.WP_Attachments && typeof window.WP_Attachments.init === "function") {
                    window.WP_Attachments.init();
                }
            });
        ');

        wp_localize_script('wp-attachments', 'WP_Attachments_Vars', array(
            'editMedia' => __('Edit Media', 'wp-attachments'),
            'youSure' => __('Are you sure you want to do this?', 'wp-attachments'),
            'postID' => get_the_ID(), // Use get_the_ID() instead of $_GET
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpa-attachments-nonce')
        ));

        $this->addCustomStyles();
    }

    private function addCustomStyles()
    {
        ?>
        <style>
        /* Updated for WP Attachments */
        .wpa-attachments-wrapper { padding: 0; font-size: 13px; }
        .wpa-attachments-header { background: #f6f7f7; border-bottom: 1px solid #dcdcde; padding: 16px; margin: -12px -12px 16px -12px; }
        .wpa-attachments-header h4 { margin: 0; font-size: 13px; font-weight: 500; color: #1d2327; display: flex; align-items: center; }
        .wpa-attachments-header .dashicons { color: #2271b1; margin-right: 8px; }
        .wpa-attachments-stats { margin-top: 8px; color: #646970; font-size: 12px; display: flex; gap: 16px; }
        .wpa-attachments-stats strong { font-weight: 500; }
        .wpa-attachment-list { margin: 0 -12px; padding: 0 12px; }
        .wpa-attachment-item { display: flex; align-items: center; padding: 12px; border: 1px solid #dcdcde; background: #fff; margin-bottom: 8px; border-radius: 4px; transition: all 0.2s ease; position: relative; }
        .wpa-attachment-item.ui-sortable-helper { box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: scale(1.02); z-index: 100; }
        .wpa-attachment-item.ui-sortable-placeholder { visibility: visible !important; background: #f0f6fc; border: 1px dashed #2271b1; }
        .wpa-attachment-item:hover { border-color: #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .wpa-attachment-preview { width: 48px; height: 48px; margin-right: 12px; border: 1px solid #dcdcde; border-radius: 3px; display: flex; align-items: center; justify-content: center; background: #f6f7f7; flex-shrink: 0; overflow: hidden; }
        .wpa-attachment-preview img { width: 100%; height: 100%; object-fit: cover; }
        .wpa-attachment-preview .dashicons { font-size: 28px; color: #2271b1; }
        .wpa-attachment-info { flex: 1; min-width: 0; }
        .wpa-attachment-title { font-weight: 500; margin-bottom: 4px; line-height: 1.4; }
        .wpa-attachment-title a { text-decoration: none; color: #2271b1; }
        .wpa-attachment-title a:hover { color: #135e96; }
        .wpa-attachment-meta { font-size: 12px, color: #646970; display: flex; flex-wrap: wrap; gap: 8px; }
        .wpa-attachment-meta span { display: inline-flex; align-items: center; }
        .wpa-attachment-meta .dashicons { font-size: 16px; margin-right: 2px; }
        .wpa-attachment-actions { display: flex; gap: 4px; }
        .wpa-attachment-actions .button { padding: 4px 8px; min-width: 28px; height: 28px; line-height: 1; border-radius: 2px; }
        .wpa-attachment-actions .button .dashicons { font-size: 16px; }
        .wpa-attachment-drag-handle { cursor: move; padding: 8px; margin: -8px 8px -8px -8px; color: #646970; opacity: 0.5; transition: opacity 0.2s ease; }
        .wpa-attachment-item:hover .wpa-attachment-drag-handle { opacity: 1; }
        .wpa-no-attachments { text-align: center; padding: 40px 20px; color: #646970; border: 1px dashed #dcdcde; border-radius: 4px; margin: 0 -12px; background: #f6f7f7; }
        .wpa-no-attachments .dashicons { font-size: 48px; color: #dcdcde; margin-bottom: 16px; }
        .wpa-no-attachments h4 { margin: 0 0 8px; font-size: 14px; font-weight: 500; }
        .wpa-no-attachments p { margin: 0; font-size: 13px; }
        .wpa-attachments-footer { border-top: 1px solid #dcdcde; padding: 16px; margin: 16px -12px -12px; background: #f6f7f7; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .wpa-toggle-wrapper { display: flex; align-items: center; gap: 8px; }
        .wpa-toggle-wrapper label { margin: 0; font-size: 13px; color: #1d2327; cursor: pointer; }
        .wpa-toggle-wrapper input[type="checkbox"] { -webkit-appearance: none; -moz-appearance: none; appearance: none; width: 40px; height: 20px; background: #dcdcde; border-radius: 10px; position: relative; cursor: pointer; outline: none; transition: background 0.2s ease; }
        .wpa-toggle-wrapper input[type="checkbox"]:checked { background: #2271b1; }
        .wpa-toggle-wrapper input[type="checkbox"]::after { content: ''; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; background: #fff; border-radius: 50%; transition: transform 0.2s ease; }
        .wpa-toggle-wrapper input[type="checkbox"]:checked::after { transform: translateX(20px); }
        .wpa-attachments-footer-buttons { display: flex; gap: 8px; }
        .wpa-preview-modal { display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.85); }
        .wpa-preview-content { position: relative; margin: 2% auto; padding: 30px; width: 90%; max-width: 900px; background: white; border-radius: 4px; max-height: 90vh; overflow: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .wpa-preview-close { position: absolute; top: 15px; right: 20px; font-size: 28px; font-weight: bold; cursor: pointer; color: #646970; background: none; border: none; padding: 0; }
        .wpa-preview-close:hover { color: #1d2327; }
        .wpa-preview-file { text-align: center; margin: 20px 0; }
        .wpa-preview-file img, .wpa-preview-file video { max-width: 100%; max-height: 70vh; border-radius: 2px; }
        .wpa-preview-file audio { width: 100%; }
        .wpa-preview-file iframe { width: 100%; height: 70vh; border: none; border-radius: 2px; }
        @media (max-width: 782px) {
            .wpa-attachment-item { flex-wrap: wrap; padding: 12px 8px; }
            .wpa-attachment-preview { width: 40px; height: 40px; margin-right: 8px; }
            .wpa-attachments-footer { flex-direction: column; align-items: flex-start; }
            .wpa-attachments-footer-buttons { width: 100%; justify-content: flex-end; }
        }
        </style>
        <?php
    }

    private function getFileIcon($mime_type)
    {
        $icons = array(
            'image' => 'dashicons-format-image',
            'video' => 'dashicons-format-video',
            'audio' => 'dashicons-format-audio',
            'application/pdf' => 'dashicons-pdf',
            'application/zip' => 'dashicons-media-archive',
            'text' => 'dashicons-media-text',
            'application/msword' => 'dashicons-media-document',
            'application/vnd.ms-excel' => 'dashicons-media-spreadsheet',
            'application/vnd.ms-powerpoint' => 'dashicons-media-interactive',
        );

        foreach ($icons as $type => $icon) {
            if (strpos($mime_type, $type) === 0) {
                return $icon;
            }
        }

        return 'dashicons-media-default';
    }

    private function getAttachmentPreview($attachment)
    {
        $attachment_id = $attachment->ID;
        $mime_type = $attachment->post_mime_type;
        $attachment_url = wp_get_attachment_url($attachment_id);

        if (strpos($mime_type, 'image/') === 0) {
            $thumb = wp_get_attachment_image_src($attachment_id, 'thumbnail');
            if ($thumb) {
                return '<img src="' . esc_url($thumb[0]) . '" alt="' . esc_attr($attachment->post_title) . '" onclick="wpaPreviewFile(\'' . esc_js($attachment_url) . '\', \'' . esc_js($mime_type) . '\', \'' . esc_js($attachment->post_title) . '\')" style="cursor: pointer;">';
            }
        } elseif (strpos($mime_type, 'video/') === 0) {
            $thumb = wp_get_attachment_image_src($attachment_id, 'thumbnail');
            if ($thumb) {
                return '<div onclick="wpaPreviewFile(\'' . esc_js($attachment_url) . '\', \'' . esc_js($mime_type) . '\', \'' . esc_js($attachment->post_title) . '\')" style="cursor: pointer; position: relative;"><img src="' . esc_url($thumb[0]) . '" alt="' . esc_attr($attachment->post_title) . '"><span class="dashicons dashicons-controls-play" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; text-shadow: 0 0 3px rgba(0,0,0,0.8);"></span></div>';
            }
        } elseif (strpos($mime_type, 'audio/') === 0) {
            return '<span class="dashicons ' . $this->getFileIcon($mime_type) . '" onclick="wpaPreviewFile(\'' . esc_js($attachment_url) . '\', \'' . esc_js($mime_type) . '\', \'' . esc_js($attachment->post_title) . '\')" style="cursor: pointer;"></span>';
        } elseif ($mime_type === 'application/pdf') {
            return '<span class="dashicons dashicons-pdf" onclick="wpaPreviewFile(\'' . esc_js($attachment_url) . '\', \'' . esc_js($mime_type) . '\', \'' . esc_js($attachment->post_title) . '\')" style="cursor: pointer;"></span>';
        }

        $icon_class = $this->getFileIcon($mime_type);
        return '<span class="dashicons ' . $icon_class . '" onclick="wpaPreviewFile(\'' . esc_js($attachment_url) . '\', \'' . esc_js($mime_type) . '\', \'' . esc_js($attachment->post_title) . '\')" style="cursor: pointer;"></span>';
    }

    private function formatBytes($size)
    {
        if ($size == 0) return '0 B';
        $units = array('B', 'KB', 'MB', 'GB');
        $unitIndex = 0;
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        return round($size, 1) . ' ' . $units[$unitIndex];
    }

    private function formatDate($date)
    {
        $post_date = strtotime($date);
        $time_diff = time() - $post_date;
        if ($time_diff < DAY_IN_SECONDS) {
            return human_time_diff($post_date) . ' ' . __('ago');
        }
        return date_i18n('M j, Y', $post_date);
    }

    public function printMetaBox($post)
    {
        $attachments = new WP_Query(array(
            'post_parent'   => $post->ID,
            'post_type'     => 'attachment',
            'post_status'   => 'any',
            'orderby'       => 'menu_order',
            'order'         => 'ASC',
            'posts_per_page' => -1
        ));

        $total_attachments = $attachments->found_posts;
        $total_size = 0;

        if ($attachments->have_posts()) {
            foreach ($attachments->posts as $att) {
                $attachment_path = get_attached_file($att->ID);
                if (file_exists($attachment_path)) {
                    $total_size += filesize($attachment_path);
                }
            }
        }

        // Get default ON/OFF for this post type
        $default_on = get_option('wpatt_enable_display_' . $post->post_type, '1');
        $is_off = get_post_meta($post->ID, 'wpa_off', true);
        // If meta not set, use default
        if ( $is_off == 1 ) {
            // Disabled by user
        } else if (in_array($post->post_status, array('auto-draft', 'draft', 'new'))) {
            $is_off = ($default_on === '1') ? '' : '1';
        } else {
            $is_off = ($default_on === '1') ? '' : '1';
        }
        ?>

        <div class="wpa-attachments-wrapper">
            <div class="wpa-attachments-header">
                <div class="wpa-attachments-stats">
                    <span><strong><?php echo $total_attachments; ?></strong> <?php _e('files', 'wp-attachments'); ?></span>
                    <span><strong><?php echo $this->formatBytes($total_size); ?></strong> <?php _e('total size', 'wp-attachments'); ?></span>
                </div>
            </div>

            <?php if ($attachments->have_posts()): ?>
                <div class="wpa-attachment-list" id="wpa-attachment-list">
                    <?php while ($attachments->have_posts()): $attachment = $attachments->next_post(); ?>
                        <?php
                        $attachment_id = $attachment->ID;
                        $attachment_url = wp_get_attachment_url($attachment_id);
                        $attachment_title = esc_html($attachment->post_title);
                        $attachment_mime = sanitize_title($attachment->post_mime_type);

                        $attachment_path = get_attached_file($attachment_id);
                        $file_size = file_exists($attachment_path)
                            ? $this->formatBytes(filesize($attachment_path))
                            : __('Not found', 'wp-attachments');

                        $formatted_date = $this->formatDate($attachment->post_date);
                        $file_extension = strtoupper(pathinfo($attachment_url, PATHINFO_EXTENSION));
                        ?>
                        <div class="wpa-attachment-item mime-<?php echo $attachment_mime; ?>"
                             data-mimetype="<?php echo esc_attr($attachment->post_mime_type); ?>"
                             data-attachmentid="<?php echo esc_attr($attachment_id); ?>"
                             data-url="<?php echo esc_url($attachment_url); ?>"
                             data-title="<?php echo $attachment_title; ?>">
                            
                            <span class="dashicons dashicons-move wpa-attachment-drag-handle" aria-hidden="true"></span>
                            <div class="wpa-attachment-preview">
                                <?php echo $this->getAttachmentPreview($attachment); ?>
                            </div>
                            <div class="wpa-attachment-info">
                                <div class="wpa-attachment-title">
                                    <a href="<?php echo esc_url($attachment_url); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo $attachment_title; ?>
                                    </a>
                                </div>
                                <div class="wpa-attachment-meta">
                                    <span class="wpa-attachment-date"><span class="dashicons dashicons-calendar"></span> <?php echo $formatted_date; ?></span>
                                    <span class="wpa-attachment-type"><?php echo $file_extension; ?></span>
                                    <span class="wpa-attachment-size"><span class="dashicons dashicons-database"></span> <?php echo $file_size; ?></span>
                                </div>
                            </div>
                            <div class="wpa-attachment-actions">
                                <a href="<?php echo esc_url($attachment_url); ?>" 
                                   class="button button-secondary" 
                                   title="<?php esc_attr_e('View', 'wp-attachments'); ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <span class="screen-reader-text"><?php _e('View', 'wp-attachments'); ?></span>
                                </a>
                                <a 
                                   class="button button-secondary wpa-edit-attachment" 
                                   title="<?php esc_attr_e('Edit', 'wp-attachments'); ?>"
                                   href="<?php echo esc_url(admin_url('post.php?post=' . $attachment_id . '&action=edit')); ?>"
                                   target="_blank">
                                    <span class="dashicons dashicons-edit"></span>
                                    <span class="screen-reader-text"><?php _e('Edit', 'wp-attachments'); ?></span>
                                </a>
                                <a href="<?php echo esc_url(admin_url("tools.php?page=unattach&noheader=true&id=$attachment_id")); ?>"
                                   class="button button-secondary"
                                   title="<?php esc_attr_e('Unattach', 'wp-attachments'); ?>">
                                    <span class="dashicons dashicons-editor-unlink"></span>
                                    <span class="screen-reader-text"><?php _e('Unattach', 'wp-attachments'); ?></span>
                                </a>
                                <a href="<?php echo esc_url(get_delete_post_link($attachment_id)); ?>"
                                   class="button button-secondary"
                                   onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this permanently?', 'wp-attachments')); ?>');"
                                   title="<?php esc_attr_e('Delete', 'wp-attachments'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                    <span class="screen-reader-text"><?php _e('Delete', 'wp-attachments'); ?></span>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="wpa-no-attachments">
                    <div class="dashicons dashicons-admin-media"></div>
                    <h4><?php _e('No media attachments found', 'wp-attachments'); ?></h4>
                    <p><?php _e('Click "Add Media" below to attach files to this post.', 'wp-attachments'); ?></p>
                </div>
            <?php endif; ?>

            <div class="wpa-attachments-footer">
                <div class="wpa-toggle-wrapper">
                    <input type="checkbox" id="wpa_off_n" name="wpa_off" <?php checked(!$is_off); ?> />
                    <label for="wpa_off_n"><?php _e('Display attachments in frontend', 'wp-attachments'); ?></label>
                </div>
                <div class="wpa-attachments-footer-buttons">
                    <button class="button button-primary add_media wpa_attach_file" title="<?php esc_attr_e('Add Media', 'wp-attachments'); ?>">
                        <?php _e('Add Media', 'wp-attachments'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- File Preview Modal -->
        <div id="wpa-preview-modal" class="wpa-preview-modal">
            <div class="wpa-preview-content">
                <button type="button" class="wpa-preview-close" aria-label="<?php esc_attr_e('Close preview', 'wp-attachments'); ?>">&times;</button>
                <h3 id="wpa-preview-title"></h3>
                <div id="wpa-preview-file" class="wpa-preview-file"></div>
            </div>
        </div>

        <input type="hidden" name="wpa_checkfieldpreventautosaveonnewcpt" value="1" />

        <?php
        wp_reset_postdata();
    }

    // AJAX: Attach media to post
    public function wp_ajax_wpa_attach_media() // renamed from ij_attach_media
    {
        check_ajax_referer('wpa-attachments-nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        $attachment_id = intval($_POST['attachment_id']);
        $post_id = intval($_POST['post_id']);
        if (!$attachment_id || !$post_id) {
            wp_send_json_error('Missing data');
        }
        wp_update_post(array(
            'ID' => $attachment_id,
            'post_parent' => $post_id
        ));

        // Get the attachment object
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            wp_send_json_error('Attachment not found');
        }

        // Generate the HTML for the new attachment item
        ob_start();
        $this->print_single_attachment_item($attachment);
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    // AJAX: Re-align attachments
    public function wp_ajax_wpa_realign() // renamed from ij_realign
    {
        check_ajax_referer('wpa-attachments-nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_die(-1);
        }
        header('Content-Type: application/json');
        $alignment = isset($_REQUEST['alignment']) ? $_REQUEST['alignment'] : array();
        if (!is_array($alignment)) {
            $alignment = array_map('trim', explode(',', $alignment));
        }
        $alignment = array_values(array_filter($alignment, 'is_numeric'));
        $count = count($alignment);
        for ($i = 0; $i < $count; $i++) {
            $attachment = get_post($alignment[$i]);
            if ($attachment && $attachment->post_type === 'attachment') {
                $attachment->menu_order = $i;
                wp_update_post($attachment);
            }
        }
        wp_send_json_success();
    }

    // Add this helper method to render a single attachment item
    private function print_single_attachment_item($attachment)
    {
        $attachment_id = $attachment->ID;
        $attachment_url = wp_get_attachment_url($attachment_id);
        $attachment_title = esc_html($attachment->post_title);
        $attachment_mime = sanitize_title($attachment->post_mime_type);

        $attachment_path = get_attached_file($attachment_id);
        $file_size = file_exists($attachment_path)
            ? $this->formatBytes(filesize($attachment_path))
            : __('Not found', 'wp-attachments');

        $formatted_date = $this->formatDate($attachment->post_date);
        $file_extension = strtoupper(pathinfo($attachment_url, PATHINFO_EXTENSION));
        ?>
        <div class="wpa-attachment-item mime-<?php echo $attachment_mime; ?>"
             data-mimetype="<?php echo esc_attr($attachment->post_mime_type); ?>"
             data-attachmentid="<?php echo esc_attr($attachment_id); ?>"
             data-url="<?php echo esc_url($attachment_url); ?>"
             data-title="<?php echo $attachment_title; ?>">
            <span class="dashicons dashicons-move wpa-attachment-drag-handle" aria-hidden="true"></span>
            <div class="wpa-attachment-preview">
                <?php echo $this->getAttachmentPreview($attachment); ?>
            </div>
            <div class="wpa-attachment-info">
                <div class="wpa-attachment-title">
                    <a href="<?php echo esc_url($attachment_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo $attachment_title; ?>
                    </a>
                </div>
                <div class="wpa-attachment-meta">
                    <span class="wpa-attachment-date"><span class="dashicons dashicons-calendar"></span> <?php echo $formatted_date; ?></span>
                    <span class="wpa-attachment-type"><?php echo $file_extension; ?></span>
                    <span class="wpa-attachment-size"><span class="dashicons dashicons-database"></span> <?php echo $file_size; ?></span>
                </div>
            </div>
            <div class="wpa-attachment-actions">
                <a href="<?php echo esc_url($attachment_url); ?>" 
                   class="button button-secondary" 
                   title="<?php esc_attr_e('View', 'wp-attachments'); ?>" 
                   target="_blank" 
                   rel="noopener noreferrer">
                    <span class="dashicons dashicons-visibility"></span>
                    <span class="screen-reader-text"><?php _e('View', 'wp-attachments'); ?></span>
                </a>
                <a 
                   class="button button-secondary wpa-edit-attachment" 
                   title="<?php esc_attr_e('Edit', 'wp-attachments'); ?>"
                   href="<?php echo esc_url(admin_url('post.php?post=' . $attachment_id . '&action=edit')); ?>"
                   target="_blank">
                    <span class="dashicons dashicons-edit"></span>
                    <span class="screen-reader-text"><?php _e('Edit', 'wp-attachments'); ?></span>
                </a>
                <a href="<?php echo esc_url(admin_url("tools.php?page=unattach&noheader=true&id=$attachment_id")); ?>"
                   class="button button-secondary"
                   title="<?php esc_attr_e('Unattach', 'wp-attachments'); ?>">
                    <span class="dashicons dashicons-editor-unlink"></span>
                    <span class="screen-reader-text"><?php _e('Unattach', 'wp-attachments'); ?></span>
                </a>
                <a href="<?php echo esc_url(get_delete_post_link($attachment_id)); ?>"
                   class="button button-secondary"
                   onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this permanently?', 'wp-attachments')); ?>');"
                   title="<?php esc_attr_e('Delete', 'wp-attachments'); ?>">
                    <span class="dashicons dashicons-trash"></span>
                    <span class="screen-reader-text"><?php _e('Delete', 'wp-attachments'); ?></span>
                </a>
            </div>
        </div>
        <?php
    }
}

$WP_Attachments = WP_Attachments::getInstance();

add_action('save_post', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (empty($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    if (!isset($_POST['wpa_checkfieldpreventautosaveonnewcpt'])) {
        // Don't update meta if our box wasn't submitted (e.g. auto-draft)
        return;
    }
    if ( isset($_POST["wpa_off"]) ) {
        delete_post_meta($post_id, "wpa_off");
    } else {
        update_post_meta($post_id, "wpa_off", isset($_POST["wpa_off"]) ? '' : '1');
    }
});

add_action('plugins_loaded', function() {
    load_plugin_textdomain('wp-attachments', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});
?>