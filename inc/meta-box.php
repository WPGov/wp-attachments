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

    /**
     * Is the current admin screen one that actually shows the metabox?
     *
     * get_current_screen()->base covers post.php and post-new.php for every
     * post type, and works in both the classic and the block editor.
     */
    private function isMetaBoxScreen()
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post' || $screen->post_type === 'attachment') {
            return false;
        }

        return get_option('wpatt_enable_metabox_' . $screen->post_type, '1') === '1';
    }

    public function admin_enqueue_scripts()
    {
        // Without this guard the media library, the metabox script and all of
        // the CSS loaded on every single admin screen.
        if (!$this->isMetaBoxScreen()) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_script(
            'wp-attachments',
            plugin_dir_url(__FILE__) . 'scripts/metabox.js',
            array('jquery-ui-sortable'),
            '6.0',
            true
        );

        wp_localize_script('wp-attachments', 'WP_Attachments_Vars', array(
            'editMedia'           => __('Edit Media', 'wp-attachments'),
            'youSure'             => __('Are you sure you want to do this?', 'wp-attachments'),
            'confirmDelete'       => __('Are you sure you want to delete this file permanently?', 'wp-attachments'),
            'mediaFrameTitle'     => __('Add Media Attachments', 'wp-attachments'),
            'mediaFrameButton'    => __('Attach to Post', 'wp-attachments'),
            'saveFirst'           => __('Please save this content before adding attachments.', 'wp-attachments'),
            /* translators: 1: file name, 2: title of the content it is currently attached to, 3: that content's ID. */
            'reattachWarning'     => __('The file "%1$s" is already attached to "%2$s" (ID: %3$s).' . "\n"
                                        . 'Attaching it here will detach it from its current location.' . "\n\n"
                                        . 'Do you want to proceed?', 'wp-attachments'),
            /* translators: 1: new position, 2: total number of files. */
            'movedTo'             => __('Moved to position %1$s of %2$s', 'wp-attachments'),
            'doneEditing'         => __('Done', 'wp-attachments'),
            'noTitle'             => __('(no title)', 'wp-attachments'),
            /* translators: %s: attachment title. */
            'previewLabel'        => __('Preview %s', 'wp-attachments'),
            'previewFallback'     => __('File Preview', 'wp-attachments'),
            'previewUnavailable'  => __('Preview not available for this file type.', 'wp-attachments'),
            'downloadFile'        => __('Download file', 'wp-attachments'),
            'postID'              => get_the_ID(), // Use get_the_ID() instead of $_GET
            'ajaxurl'             => admin_url('admin-ajax.php'),
            'nonce'               => wp_create_nonce('wpa-attachments-nonce')
        ));

        // Inline-only stylesheet: registering a handle keeps the CSS inside the
        // normal dependency pipeline instead of echoing it mid-request.
        wp_register_style('wpa-metabox', false, array(), '6.0');
        wp_enqueue_style('wpa-metabox');
        wp_add_inline_style('wpa-metabox', $this->getStyles());
    }

    /**
     * Metabox stylesheet.
     *
     * Everything is scoped under .wpa-attachments-wrapper so nothing leaks into
     * the rest of wp-admin, and the accent colour follows the admin colour
     * scheme wherever the block editor exposes its custom properties.
     */
    private function getStyles()
    {
        return <<<'CSS'
.wpa-attachments-wrapper {
    --wpa-accent: var(--wp-admin-theme-color, #2271b1);
    --wpa-accent-dark: var(--wp-admin-theme-color-darker-10, #135e96);
    --wpa-text: #1d2327;
    --wpa-muted: #646970;
    --wpa-border: #dcdcde;
    --wpa-border-strong: #c3c4c7;
    --wpa-surface: #fff;
    --wpa-surface-alt: #f6f7f7;
    --wpa-danger: #d63638;
    --wpa-radius: 4px;
    font-size: 13px;
    line-height: 1.5;
    color: var(--wpa-text);
}

/* ---------- stats bar ---------- */
.wpa-attachments-wrapper .wpa-attachments-stats {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 18px;
    margin: 0 0 12px;
    padding: 0 0 12px;
    border-bottom: 1px solid var(--wpa-border);
    color: var(--wpa-muted);
    font-size: 12px;
}
.wpa-attachments-wrapper .wpa-attachments-stat {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.wpa-attachments-wrapper .wpa-stat-icon {
    display: block;
    width: 15px;
    height: 15px;
    color: var(--wpa-border-strong);
}
.wpa-attachments-wrapper .wpa-attachments-stat strong {
    font-weight: 600;
    color: var(--wpa-text);
}

/* ---------- list ---------- */
.wpa-attachments-wrapper .wpa-attachment-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
}
.wpa-attachments-wrapper .wpa-attachment-item {
    display: grid;
    grid-template-columns: auto auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: var(--wpa-surface);
    border: 1px solid var(--wpa-border);
    border-radius: var(--wpa-radius);
    transition: border-color .15s ease, box-shadow .15s ease;
}
.wpa-attachments-wrapper .wpa-attachment-item:hover {
    border-color: var(--wpa-border-strong);
    box-shadow: 0 1px 2px rgba(0, 0, 0, .06);
}
.wpa-attachments-wrapper .wpa-attachment-item:focus-within {
    border-color: var(--wpa-accent);
    box-shadow: 0 0 0 1px var(--wpa-accent);
}
.wpa-attachments-wrapper .wpa-attachment-item.ui-sortable-helper {
    border-color: var(--wpa-accent);
    box-shadow: 0 6px 18px rgba(0, 0, 0, .16);
}
.wpa-attachments-wrapper .wpa-attachment-item.ui-sortable-placeholder {
    visibility: visible !important;
    background: var(--wpa-surface-alt);
    border: 1px dashed var(--wpa-accent);
    box-shadow: none;
}

/* ---------- drag handle ---------- */
.wpa-attachments-wrapper .wpa-attachment-move {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
}
.wpa-attachments-wrapper .wpa-move,
.wpa-attachments-wrapper .wpa-attachment-drag-handle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    margin: 0;
    padding: 0;
    border: 0;
    border-radius: 3px;
    background: none;
    /* #c3c4c7 on white is roughly 1.9:1 -- effectively invisible. */
    color: #787c82;
    transition: color .15s ease, background .15s ease, box-shadow .15s ease;
}
.wpa-attachments-wrapper .wpa-move {
    height: 16px;
    cursor: pointer;
}
.wpa-attachments-wrapper .wpa-attachment-drag-handle {
    height: 20px;
    cursor: grab;
}
.wpa-attachments-wrapper .wpa-attachment-drag-handle:active { cursor: grabbing; }
.wpa-attachments-wrapper .wpa-chevron { display: block; width: 13px; height: 13px; }
.wpa-attachments-wrapper .wpa-move:hover:not(:disabled),
.wpa-attachments-wrapper .wpa-attachment-drag-handle:hover {
    color: var(--wpa-accent);
    background: #f0f0f1;
}
.wpa-attachments-wrapper .wpa-move:disabled {
    opacity: .35;
    cursor: default;
}
.wpa-attachments-wrapper .wpa-move:focus-visible,
.wpa-attachments-wrapper .wpa-attachment-drag-handle:focus-visible {
    outline: none;
    color: var(--wpa-accent);
    box-shadow: 0 0 0 2px var(--wpa-accent);
}
.wpa-attachments-wrapper .wpa-grip {
    display: block;
    width: 10px;
    height: 16px;
    fill: currentColor;
}


/* ---------- preview thumb ---------- */
.wpa-attachments-wrapper .wpa-attachment-preview {
    position: relative;
    width: 44px;
    height: 44px;
    border: 1px solid var(--wpa-border);
    border-radius: 3px;
    background: var(--wpa-surface-alt);
    overflow: hidden;
    flex-shrink: 0;
}
.wpa-attachments-wrapper .wpa-preview-trigger,
.wpa-attachments-wrapper .wpa-preview-static {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
    border: 0;
    background: none;
    color: inherit; /* takes the file-type colour from the tile */
}
.wpa-attachments-wrapper .wpa-preview-trigger { cursor: pointer; }
.wpa-attachments-wrapper .wpa-preview-trigger:hover { background: rgba(0, 0, 0, .05); }
.wpa-attachments-wrapper .wpa-preview-trigger:focus-visible {
    outline: 2px solid var(--wpa-accent);
    outline-offset: -2px;
}
.wpa-attachments-wrapper .wpa-preview-trigger img,
.wpa-attachments-wrapper .wpa-preview-static img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.wpa-attachments-wrapper .wpa-file-icon {
    display: block;
    width: 26px;
    height: 26px;
}

/*
 * File-type colours. The tile carries both the tint and the colour; the SVG
 * picks the colour up through currentColor.
 */
.wpa-attachments-wrapper .wpa-attachment-preview { color: var(--wpa-muted); }
.wpa-attachments-wrapper .wpa-file--pdf     { color: #c9282a; background: #fdf1f1; border-color: #f4d4d4; }
.wpa-attachments-wrapper .wpa-file--doc     { color: #1c5f96; background: #eef4fa; border-color: #cfe0ee; }
.wpa-attachments-wrapper .wpa-file--sheet   { color: #0a7040; background: #edf7f1; border-color: #cbe7d8; }
.wpa-attachments-wrapper .wpa-file--slides  { color: #a35a00; background: #fdf4e9; border-color: #f2ddc2; }
.wpa-attachments-wrapper .wpa-file--archive { color: #7a6100; background: #fbf6e3; border-color: #ebdfb4; }
.wpa-attachments-wrapper .wpa-file--audio   { color: #6d47a1; background: #f4effb; border-color: #ded1f0; }
.wpa-attachments-wrapper .wpa-file--video   { color: #2f45c5; background: #eef0fd; border-color: #d2d8f6; }
.wpa-attachments-wrapper .wpa-file--image   { color: #0a6f6a; background: #ecf6f5; border-color: #c8e4e2; }
.wpa-attachments-wrapper .wpa-file--code    { color: #3c434a; background: #f0f1f2; border-color: #dcdcde; }
.wpa-attachments-wrapper .wpa-file--text    { color: #4b5157; background: #f2f3f4; border-color: #dcdcde; }
.wpa-attachments-wrapper .wpa-preview-badge {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 22px;
    height: 22px;
    pointer-events: none;
}
.wpa-attachments-wrapper .wpa-preview-badge svg { display: block; width: 22px; height: 22px; }

/* ---------- file info ---------- */
.wpa-attachments-wrapper .wpa-attachment-info { min-width: 0; }
.wpa-attachments-wrapper .wpa-attachment-title {
    font-weight: 600;
    margin-bottom: 2px;
}
.wpa-attachments-wrapper .wpa-attachment-title a {
    display: block;
    color: var(--wpa-text);
    text-decoration: none;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.wpa-attachments-wrapper .wpa-attachment-title a:hover,
.wpa-attachments-wrapper .wpa-attachment-title a:focus { color: var(--wpa-accent); }
.wpa-attachments-wrapper .wpa-attachment-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 3px 8px;
    font-size: 12px;
    color: var(--wpa-muted);
}
.wpa-attachments-wrapper .wpa-attachment-type {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: .03em;
    padding: 1px 5px;
    border: 1px solid var(--wpa-border);
    border-radius: 2px;
    background: var(--wpa-surface-alt);
}

/* ---------- row actions ---------- */
.wpa-attachments-wrapper .wpa-attachment-actions {
    display: flex;
    gap: 4px;
}
.wpa-attachments-wrapper .wpa-attachment-actions .button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    min-height: 30px;
    padding: 0;
    color: #7c8185;
    background: transparent;
    border-color: transparent;
    box-shadow: none;
    transition: background .15s ease, color .15s ease, border-color .15s ease;
}
.wpa-attachments-wrapper .wpa-action-icon { display: block; width: 18px; height: 18px; }
.wpa-attachments-wrapper .wpa-attachment-actions .button:hover {
    color: var(--wpa-accent-dark);
    background: #f0f0f1;
    border-color: #dcdcde;
}
.wpa-attachments-wrapper .wpa-attachment-actions .button:focus-visible {
    color: var(--wpa-accent);
    border-color: var(--wpa-accent);
    box-shadow: 0 0 0 1px var(--wpa-accent);
}
.wpa-attachments-wrapper .wpa-attachment-actions .wpa-delete-action:hover {
    color: var(--wpa-danger);
    background: #fcf0f0;
    border-color: #f1c9c9;
}

/* ---------- empty state ---------- */
.wpa-attachments-wrapper .wpa-no-attachments {
    text-align: center;
    padding: 32px 20px;
    border: 1px dashed var(--wpa-border-strong);
    border-radius: var(--wpa-radius);
    background: var(--wpa-surface-alt);
    color: var(--wpa-muted);
}
.wpa-attachments-wrapper .wpa-no-attachments .dashicons {
    font-size: 36px;
    width: 36px;
    height: 36px;
    color: var(--wpa-border-strong);
}
.wpa-attachments-wrapper .wpa-no-attachments h4 {
    margin: 10px 0 4px;
    font-size: 13px;
    font-weight: 600;
    color: var(--wpa-text);
}
.wpa-attachments-wrapper .wpa-no-attachments p { margin: 0; font-size: 12px; }

/* ---------- footer ---------- */
.wpa-attachments-wrapper .wpa-attachments-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid var(--wpa-border);
}
.wpa-attachments-wrapper .wpa-toggle-wrapper {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.wpa-attachments-wrapper .wpa-toggle-wrapper label {
    margin: 0;
    cursor: pointer;
    color: var(--wpa-text);
}
.wpa-attachments-wrapper .wpa-toggle-wrapper input[type="checkbox"] {
    appearance: none;
    -webkit-appearance: none;
    position: relative;
    flex-shrink: 0;
    width: 36px;
    height: 20px;
    min-width: 36px;
    margin: 0;
    padding: 0;
    border: 0;
    border-radius: 10px;
    background: var(--wpa-border-strong);
    box-shadow: none;
    cursor: pointer;
    transition: background .15s ease;
}
.wpa-attachments-wrapper .wpa-toggle-wrapper input[type="checkbox"]::before,
.wpa-attachments-wrapper .wpa-toggle-wrapper input[type="checkbox"]:checked::before {
    content: "";
    display: block;
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    margin: 0;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .25);
    transition: transform .15s ease;
}
.wpa-attachments-wrapper .wpa-toggle-wrapper input[type="checkbox"]:checked { background: var(--wpa-accent); }
.wpa-attachments-wrapper .wpa-toggle-wrapper input[type="checkbox"]:checked::before { transform: translateX(16px); }
.wpa-attachments-wrapper .wpa-toggle-wrapper input[type="checkbox"]:focus-visible {
    outline: 2px solid var(--wpa-accent);
    outline-offset: 2px;
}

/* ---------- preview modal ---------- */
.wpa-preview-modal {
    display: none;
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 100050;
    padding: 24px 16px;
    background: rgba(0, 0, 0, .75);
    overflow: auto;
}
.wpa-preview-modal.is-open { display: block; }
body.wpa-modal-open { overflow: hidden; }
.wpa-preview-modal .wpa-preview-content {
    position: relative;
    width: 100%;
    max-width: 880px;
    margin: 0 auto;
    padding: 18px 22px 22px;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, .3);
}
.wpa-preview-modal .wpa-preview-heading {
    margin: 0 40px 14px 0;
    padding: 0;
    font-size: 15px;
    line-height: 1.4;
    color: #1d2327;
    word-break: break-word;
}
.wpa-preview-modal .wpa-preview-close {
    position: absolute;
    top: 12px;
    right: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
    border: 0;
    border-radius: 3px;
    background: none;
    color: #646970;
    cursor: pointer;
}
.wpa-preview-modal .wpa-preview-close:hover { background: #f0f0f1; color: #1d2327; }
.wpa-preview-modal .wpa-preview-close:focus-visible {
    outline: 2px solid var(--wp-admin-theme-color, #2271b1);
    outline-offset: 1px;
}
.wpa-preview-modal .wpa-preview-file { text-align: center; }
.wpa-preview-modal .wpa-preview-file img,
.wpa-preview-modal .wpa-preview-file video {
    max-width: 100%;
    max-height: 70vh;
    border-radius: 2px;
}
.wpa-preview-modal .wpa-preview-file audio { width: 100%; }
.wpa-preview-modal .wpa-preview-file iframe {
    width: 100%;
    height: 70vh;
    border: 0;
    border-radius: 2px;
}
.wpa-preview-modal .wpa-preview-file pre {
    text-align: left;
    max-height: 60vh;
    overflow: auto;
    padding: 14px;
    border-radius: 3px;
    background: #f6f7f7;
    font-size: 12px;
}

/* ---------- narrow screens ---------- */
@media screen and (max-width: 782px) {
    .wpa-attachments-wrapper .wpa-attachment-item {
        grid-template-columns: auto auto minmax(0, 1fr);
        row-gap: 10px;
    }
    .wpa-attachments-wrapper .wpa-attachment-actions {
        grid-column: 1 / -1;
        justify-content: flex-end;
    }
    .wpa-attachments-wrapper .wpa-attachment-actions .button {
        width: 34px;
        height: 34px;
        min-height: 34px;
    }
    .wpa-attachments-wrapper .wpa-attachments-footer { flex-direction: column; align-items: stretch; }
}

@media (prefers-reduced-motion: reduce) {
    .wpa-attachments-wrapper *,
    .wpa-preview-modal * {
        transition: none !important;
        animation: none !important;
    }
}
CSS;
    }

    /**
     * Build a nonce-protected unattach URL for a given attachment.
     */
    private function getUnattachUrl($attachment_id)
    {
        $attachment_id = absint($attachment_id);

        // Nonce added via add_query_arg rather than wp_nonce_url(): the latter
        // returns an already esc_html'd URL, which the template would then
        // escape a second time.
        return add_query_arg(
            array(
                'page'     => 'unattach',
                'noheader' => 'true',
                'id'       => $attachment_id,
                'referer'  => remove_query_arg('message'),
                '_wpnonce' => wp_create_nonce('wpa_unattach_' . $attachment_id),
            ),
            admin_url('tools.php')
        );
    }

    /**
     * Reduce a MIME type to one of the icon families below.
     */
    private function getFileType($mime_type)
    {
        $mime_type = strtolower((string) $mime_type);

        // Checked in order: the first prefix that matches wins, so the more
        // specific entries (text/csv) come before the broad ones (text/).
        $prefixes = array(
            'image/'    => 'image',
            'video/'    => 'video',
            'audio/'    => 'audio',
            'text/csv'  => 'sheet',
            'text/html' => 'code',
            'text/'     => 'text',
        );
        foreach ($prefixes as $prefix => $type) {
            if (strpos($mime_type, $prefix) === 0) {
                return $type;
            }
        }

        $needles = array(
            'pdf'               => 'pdf',
            'wordprocessing'    => 'doc',
            'msword'            => 'doc',
            'opendocument.text' => 'doc',
            'rtf'               => 'doc',
            'spreadsheet'       => 'sheet',
            'ms-excel'          => 'sheet',
            'presentation'      => 'slides',
            'ms-powerpoint'     => 'slides',
            'zip'               => 'archive',
            'compressed'        => 'archive',
            'tar'               => 'archive',
            'gzip'              => 'archive',
            'json'              => 'code',
            'xml'               => 'code',
            'javascript'        => 'code',
        );
        foreach ($needles as $needle => $type) {
            if (strpos($mime_type, $needle) !== false) {
                return $type;
            }
        }

        return 'default';
    }

    /**
     * Inline SVG icon for a file family.
     *
     * Inline rather than a font or sprite: crisp at any pixel density, colour
     * comes from the tile via currentColor, and it costs no extra request.
     */
    private function getFileIconSvg($type)
    {
        $open  = '<svg class="wpa-file-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">';
        $close = '</svg>';

        // Sheet of paper with a folded corner, shared by the document families.
        $page = '<path d="M14 2.75H7A2.25 2.25 0 0 0 4.75 5v14A2.25 2.25 0 0 0 7 21.25h10A2.25 2.25 0 0 0 19.25 19V8L14 2.75Z" fill="currentColor" fill-opacity=".13"/>'
              . '<path d="M14 2.75H7A2.25 2.25 0 0 0 4.75 5v14A2.25 2.25 0 0 0 7 21.25h10A2.25 2.25 0 0 0 19.25 19V8L14 2.75Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>'
              . '<path d="M13.75 3v4.25H18" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>';

        // Rounded frame shared by the media families.
        $frame = '<rect x="3.75" y="4.75" width="16.5" height="14.5" rx="2.25" fill="currentColor" fill-opacity=".13"/>'
               . '<rect x="3.75" y="4.75" width="16.5" height="14.5" rx="2.25" stroke="currentColor" stroke-width="1.5"/>';

        $glyphs = array(
            'pdf' => $page
                . '<path d="M8 12.5h8M8 15.5h8M8 18.5h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',

            'doc' => $page
                . '<path d="M8 12.5h8M8 15.5h8M8 18.5h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',

            'text' => $page
                . '<path d="M8 12.5h8M8 15.5h8M8 18.5h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',

            'sheet' => $page
                . '<rect x="7.25" y="12.25" width="9.5" height="6.5" rx="1" stroke="currentColor" stroke-width="1.5"/>'
                . '<path d="M12 12.25v6.5M7.25 15.5h9.5" stroke="currentColor" stroke-width="1.5"/>',

            'slides' => $page
                . '<rect x="7.25" y="12.25" width="9.5" height="6.5" rx="1" stroke="currentColor" stroke-width="1.5"/>'
                . '<path d="M9.5 16.5l2-2 1.75 1.75 1.25-1.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',

            'code' => $page
                . '<path d="M10.25 12.75 7.75 15.5l2.5 2.75M13.75 12.75l2.5 2.75-2.5 2.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',

            'image' => $frame
                . '<circle cx="8.75" cy="10" r="1.5" fill="currentColor"/>'
                . '<path d="M4.75 17.5 9.5 12.75l3.25 3.25 2.25-1.75 4.25 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',

            'video' => $frame
                . '<path d="M10.25 9.25v5.5l5-2.75-5-2.75Z" fill="currentColor"/>',

            'audio' => '<path d="M9.5 16.5V6.75l8.75-1.75V14.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
                . '<ellipse cx="7.25" cy="16.75" rx="2.75" ry="2.25" fill="currentColor"/>'
                . '<ellipse cx="16" cy="14.75" rx="2.75" ry="2.25" fill="currentColor"/>',

            'archive' => '<path d="M4.75 7.75h14.5V19A2.25 2.25 0 0 1 17 21.25H7A2.25 2.25 0 0 1 4.75 19V7.75Z" fill="currentColor" fill-opacity=".13"/>'
                . '<rect x="3.75" y="3.75" width="16.5" height="4" rx="1.25" stroke="currentColor" stroke-width="1.5"/>'
                . '<path d="M5 7.75V19A2.25 2.25 0 0 0 7.25 21.25h9.5A2.25 2.25 0 0 0 19 19V7.75" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>'
                . '<path d="M10.25 11.5h3.5M10.25 14.5h3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',

            'default' => $page
                . '<path d="M8 13.5h8M8 16.5h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
        );

        $glyph = isset($glyphs[$type]) ? $glyphs[$type] : $glyphs['default'];

        return $open . $glyph . $close;
    }

    /**
     * Row-action icons, drawn to stay legible at 18px.
     */
    private function getActionIcon($name)
    {
        $paths = array(
            'view' => '<path d="M2.5 12S6 5.75 12 5.75 21.5 12 21.5 12 18 18.25 12 18.25 2.5 12 2.5 12Z"/>'
                    . '<circle cx="12" cy="12" r="2.75"/>',

            'edit' => '<path d="M4 20h4L19.5 8.5a2.12 2.12 0 0 0-3-3L5 17v3Z"/>'
                    . '<path d="M14.5 6.5 18.5 10.5"/>',

            // Two chain halves pulling apart: a clean "detach" at small sizes.
            'unlink' => '<path d="M10.5 13.5 8 16a3.9 3.9 0 0 1-5.5-5.5L5 8"/>'
                      . '<path d="M13.5 10.5 16 8a3.9 3.9 0 0 1 5.5 5.5L19 16"/>',

            'trash' => '<path d="M4 6.75h16"/>'
                     . '<path d="M9.5 6.75V5.5A1.5 1.5 0 0 1 11 4h2a1.5 1.5 0 0 1 1.5 1.5v1.25"/>'
                     . '<path d="M6.75 6.75 7.5 19a2 2 0 0 0 2 1.9h5a2 2 0 0 0 2-1.9l.75-12.25"/>'
                     . '<path d="M10.25 10.5v6M13.75 10.5v6"/>',
        );

        $d = isset($paths[$name]) ? $paths[$name] : '';

        return '<svg class="wpa-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            . ' stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"'
            . ' aria-hidden="true" focusable="false">' . $d . '</svg>';
    }

    /**
     * Chevron used by the move up / move down buttons.
     */
    private function getChevronSvg($direction)
    {
        $d = ('up' === $direction) ? 'm6 15 6-6 6 6' : 'm6 9 6 6 6-6';

        return '<svg class="wpa-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            . ' stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"'
            . ' aria-hidden="true" focusable="false"><path d="' . $d . '"/></svg>';
    }

    /**
     * Six-dot grip. Reads as "drag me" far better than a hamburger glyph.
     */
    private function getDragHandleSvg()
    {
        return '<svg class="wpa-grip" viewBox="0 0 10 16" aria-hidden="true" focusable="false">'
            . '<circle cx="2.5" cy="3" r="1.35"/><circle cx="7.5" cy="3" r="1.35"/>'
            . '<circle cx="2.5" cy="8" r="1.35"/><circle cx="7.5" cy="8" r="1.35"/>'
            . '<circle cx="2.5" cy="13" r="1.35"/><circle cx="7.5" cy="13" r="1.35"/>'
            . '</svg>';
    }

    /**
     * Thumbnail (or file-type icon) for one attachment.
     *
     * Previewable types render as a real <button> so the preview is reachable
     * by keyboard and needs no inline onclick handler; anything else renders as
     * a plain decorative icon.
     */
    private function getAttachmentPreview($attachment)
    {
        $attachment_id  = $attachment->ID;
        $mime_type      = (string) $attachment->post_mime_type;
        $attachment_url = wp_get_attachment_url($attachment_id);
        $title          = $attachment->post_title;

        $is_image = strpos($mime_type, 'image/') === 0;
        $is_video = strpos($mime_type, 'video/') === 0;

        $thumb = ($is_image || $is_video)
            ? wp_get_attachment_image_src($attachment_id, 'thumbnail')
            : false;

        if ($thumb) {
            // Decorative: the button below carries the accessible name.
            $inner = '<img src="' . esc_url($thumb[0]) . '" alt="" loading="lazy" />';
            if ($is_video) {
                $inner .= '<span class="wpa-preview-badge" aria-hidden="true">'
                    . '<svg viewBox="0 0 24 24" fill="none" focusable="false"><circle cx="12" cy="12" r="9" fill="rgba(0,0,0,.55)"/><path d="M10 8.5v7l5.5-3.5-5.5-3.5Z" fill="#fff"/></svg>'
                    . '</span>';
            }
        } else {
            $inner = $this->getFileIconSvg($this->getFileType($mime_type));
        }

        $previewable = $is_image
            || $is_video
            || strpos($mime_type, 'audio/') === 0
            || strpos($mime_type, 'text/') === 0
            || $mime_type === 'application/pdf';

        if (!$previewable || !$attachment_url) {
            return '<span class="wpa-preview-static">' . $inner . '</span>';
        }

        return sprintf(
            '<button type="button" class="wpa-preview-trigger" data-url="%1$s" data-mime="%2$s" data-title="%3$s"><span class="screen-reader-text">%4$s</span>%5$s</button>',
            esc_url($attachment_url),
            esc_attr($mime_type),
            esc_attr($title),
            /* translators: %s: attachment title. */
            esc_html(sprintf(__('Preview %s', 'wp-attachments'), $title)),
            $inner
        );
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

    /**
     * Count and total byte size for a set of attachments.
     *
     * @param array $attachments WP_Post objects or attachment IDs.
     * @return array With 'count' and 'size' keys.
     */
    private function getStats($attachments)
    {
        $count = 0;
        $size  = 0;

        foreach ((array) $attachments as $attachment) {
            $count++;
            $id   = is_object($attachment) ? $attachment->ID : (int) $attachment;
            $path = get_attached_file($id);
            if ($path && file_exists($path)) {
                $size += filesize($path);
            }
        }

        return array('count' => $count, 'size' => $size);
    }

    /**
     * Output the contents of the stats bar. Also reused by the attach AJAX
     * response so the counters stay correct without a page reload.
     */
    private function renderStats($stats)
    {
        printf(
            '<span class="wpa-attachments-stat">'
            . '<svg class="wpa-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M13.75 3.25V8h4.75"/>'
            . '</svg>%s</span>',
            sprintf(
                /* translators: %s: number of attached files. */
                esc_html(_n('%s file', '%s files', $stats['count'], 'wp-attachments')),
                '<strong>' . esc_html(number_format_i18n($stats['count'])) . '</strong>'
            )
        );

        printf(
            '<span class="wpa-attachments-stat">'
            . '<svg class="wpa-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<path d="m12 3 9 4.5-9 4.5-9-4.5L12 3Z"/><path d="m3 12 9 4.5 9-4.5"/><path d="m3 16.5 9 4.5 9-4.5"/>'
            . '</svg>%s</span>',
            sprintf(
                /* translators: %s: combined size of the attached files. */
                esc_html__('%s total', 'wp-attachments'),
                '<strong>' . esc_html(wpatt_format_bytes($stats['size'], 1)) . '</strong>'
            )
        );
    }

    /**
     * Placeholder shown inside the list container when nothing is attached.
     */
    private function printEmptyState()
    {
        ?>
        <div class="wpa-no-attachments">
            <span class="dashicons dashicons-admin-media" aria-hidden="true"></span>
            <h4><?php esc_html_e('No media attached yet', 'wp-attachments'); ?></h4>
            <?php if (current_user_can('upload_files')) : ?>
                <p><?php esc_html_e('Use the Add Media button below to attach files to this content.', 'wp-attachments'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function printMetaBox($post)
    {
        $attachments = new WP_Query(array(
            'post_parent'    => $post->ID,
            'post_type'      => 'attachment',
            'post_status'    => 'any',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'posts_per_page' => -1
        ));

        $stats = $this->getStats($attachments->posts);

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
            <div class="wpa-attachments-stats" id="wpa-attachments-stats">
                <?php $this->renderStats($stats); ?>
            </div>

            <?php
            /*
             * The list container is rendered unconditionally. It used to exist
             * only when the post already had attachments, so the first file
             * attached through the media modal was appended to an empty jQuery
             * set and silently vanished until the page was reloaded.
             */
            ?>
            <div class="wpa-attachment-list" id="wpa-attachment-list">
                <?php if (!empty($attachments->posts)) : ?>
                    <?php foreach ($attachments->posts as $attachment) : ?>
                        <?php $this->print_single_attachment_item($attachment); ?>
                    <?php endforeach; ?>
                <?php else : ?>
                    <?php $this->printEmptyState(); ?>
                <?php endif; ?>
            </div>

            <p id="wpa-reorder-help" class="screen-reader-text"><?php
                esc_html_e('Press the up or down arrow key to move this file in the list.', 'wp-attachments');
            ?></p>
            <div id="wpa-reorder-status" class="screen-reader-text" role="status" aria-live="polite"></div>

            <div class="wpa-attachments-footer">
                <div class="wpa-toggle-wrapper">
                    <input type="checkbox" id="wpa_off_n" name="wpa_off" <?php checked(!$is_off); ?> />
                    <label for="wpa_off_n"><?php esc_html_e('Display attachments in frontend', 'wp-attachments'); ?></label>
                </div>
                <?php if (current_user_can('upload_files')) : ?>
                    <div class="wpa-attachments-footer-buttons">
                        <?php // type="button": inside the edit form a bare <button> defaults to submit. ?>
                        <button type="button" class="button button-primary add_media wpa_attach_file">
                            <?php esc_html_e('Add Media', 'wp-attachments'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- File Preview Modal -->
        <div id="wpa-preview-modal" class="wpa-preview-modal" role="dialog" aria-modal="true" aria-labelledby="wpa-preview-title" aria-hidden="true">
            <div class="wpa-preview-content">
                <button type="button" class="wpa-preview-close" aria-label="<?php esc_attr_e('Close preview', 'wp-attachments'); ?>">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
                <h2 id="wpa-preview-title" class="wpa-preview-heading"></h2>
                <div id="wpa-preview-file" class="wpa-preview-file"></div>
            </div>
        </div>

        <input type="hidden" name="wpa_checkfieldpreventautosaveonnewcpt" value="1" />

        <?php
    }

    // AJAX: Attach media to post
    public function wp_ajax_wpa_attach_media() // renamed from ij_attach_media
    {
        check_ajax_referer('wpa-attachments-nonce', 'nonce');
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }
        $attachment_id = intval($_POST['attachment_id']);
        $post_id = intval($_POST['post_id']);
        if (!$attachment_id || !$post_id) {
            wp_send_json_error('Missing data');
        }
        
        // Object-level authorization: verify user can edit both the target post and the attachment
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied: cannot edit target post');
        }
        if (!current_user_can('edit_post', $attachment_id)) {
            wp_send_json_error('Permission denied: cannot edit attachment');
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

        // Refreshed counters, so the stats bar stays truthful without a reload.
        $sibling_ids = get_posts(array(
            'post_parent'    => $post_id,
            'post_type'      => 'attachment',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        ob_start();
        $this->renderStats($this->getStats($sibling_ids));
        $stats = ob_get_clean();

        wp_send_json_success(array(
            'html'  => $html,
            'stats' => $stats,
        ));
    }

    // AJAX: Re-align attachments
    public function wp_ajax_wpa_realign() // renamed from ij_realign
    {
        check_ajax_referer('wpa-attachments-nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied', 403);
        }

        $alignment = isset($_REQUEST['alignment']) ? wp_unslash($_REQUEST['alignment']) : array();
        if (!is_array($alignment)) {
            $alignment = explode(',', $alignment);
        }
        $alignment = array_values(array_filter(array_map('absint', $alignment)));

        $updated = 0;
        $skipped = 0;

        foreach ($alignment as $i => $attachment_id) {
            $attachment = get_post($attachment_id);

            if (!$attachment || $attachment->post_type !== 'attachment') {
                continue;
            }

            // Object-level authorization: never trust a blanket 'edit_posts'.
            if (!current_user_can('edit_post', $attachment_id)) {
                $skipped++;
                continue;
            }

            if ((int) $attachment->menu_order === $i) {
                continue; // Already in place, skip the write.
            }

            // Update only menu_order: passing the whole post object re-runs
            // kses on post_content for users without 'unfiltered_html'.
            wp_update_post(array(
                'ID'         => $attachment_id,
                'menu_order' => $i,
            ));
            $updated++;
        }

        wp_send_json_success(array(
            'updated' => $updated,
            'skipped' => $skipped,
        ));
    }

    /**
     * Render one row. Single source of truth for the item markup: printMetaBox()
     * loops over it, and the attach AJAX handler buffers it for the response.
     */
    private function print_single_attachment_item($attachment)
    {
        $attachment_id   = $attachment->ID;
        $attachment_url  = wp_get_attachment_url($attachment_id);
        $attachment_mime = sanitize_title($attachment->post_mime_type);
        $attachment_path = get_attached_file($attachment_id);
        $file_type       = $this->getFileType($attachment->post_mime_type);

        $title = ('' !== trim((string) $attachment->post_title))
            ? $attachment->post_title
            : __('(no title)', 'wp-attachments');

        $file_size = ($attachment_path && file_exists($attachment_path))
            ? wpatt_format_bytes(filesize($attachment_path), 1)
            : __('Not found', 'wp-attachments');

        $formatted_date = $this->formatDate($attachment->post_date);

        // parse_url() first: a query string would otherwise end up in the extension.
        $url_path       = $attachment_url ? parse_url($attachment_url, PHP_URL_PATH) : '';
        $file_extension = $url_path ? strtoupper(pathinfo($url_path, PATHINFO_EXTENSION)) : '';
        ?>
        <div class="wpa-attachment-item mime-<?php echo esc_attr($attachment_mime); ?>"
             data-mimetype="<?php echo esc_attr($attachment->post_mime_type); ?>"
             data-attachmentid="<?php echo esc_attr($attachment_id); ?>"
             data-url="<?php echo esc_url($attachment_url); ?>"
             data-title="<?php echo esc_attr($title); ?>">

            <div class="wpa-attachment-move">
                <button type="button" class="wpa-move wpa-move-up"
                        title="<?php esc_attr_e('Move up', 'wp-attachments'); ?>">
                    <?php echo $this->getChevronSvg('up'); ?>
                    <span class="screen-reader-text"><?php
                        /* translators: %s: attachment title. */
                        echo esc_html( sprintf( __( 'Move %s up', 'wp-attachments' ), $title ) );
                    ?></span>
                </button>

                <button type="button"
                        class="wpa-attachment-drag-handle"
                        aria-describedby="wpa-reorder-help"
                        title="<?php esc_attr_e('Drag to reorder', 'wp-attachments'); ?>">
                    <span class="screen-reader-text"><?php
                        /* translators: %s: attachment title. */
                        echo esc_html( sprintf( __( 'Reorder %s', 'wp-attachments' ), $title ) );
                    ?></span>
                    <?php echo $this->getDragHandleSvg(); ?>
                </button>

                <button type="button" class="wpa-move wpa-move-down"
                        title="<?php esc_attr_e('Move down', 'wp-attachments'); ?>">
                    <?php echo $this->getChevronSvg('down'); ?>
                    <span class="screen-reader-text"><?php
                        /* translators: %s: attachment title. */
                        echo esc_html( sprintf( __( 'Move %s down', 'wp-attachments' ), $title ) );
                    ?></span>
                </button>
            </div>

            <div class="wpa-attachment-preview wpa-file--<?php echo esc_attr($file_type); ?>">
                <?php echo $this->getAttachmentPreview($attachment); ?>
            </div>

            <div class="wpa-attachment-info">
                <div class="wpa-attachment-title">
                    <a href="<?php echo esc_url($attachment_url); ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       title="<?php echo esc_attr($title); ?>"><?php echo esc_html($title); ?></a>
                </div>
                <div class="wpa-attachment-meta">
                    <?php if ($file_extension) : ?>
                        <span class="wpa-attachment-type"><?php echo esc_html($file_extension); ?></span>
                    <?php endif; ?>
                    <span class="wpa-attachment-size"><?php echo esc_html($file_size); ?></span>
                    <span class="wpa-attachment-date"><?php echo esc_html($formatted_date); ?></span>
                </div>
            </div>

            <div class="wpa-attachment-actions">
                <a href="<?php echo esc_url($attachment_url); ?>"
                   class="button button-secondary"
                   title="<?php esc_attr_e('View', 'wp-attachments'); ?>"
                   target="_blank"
                   rel="noopener noreferrer">
                    <?php echo $this->getActionIcon('view'); ?>
                    <span class="screen-reader-text"><?php esc_html_e('View', 'wp-attachments'); ?></span>
                </a>
                <a href="<?php echo esc_url(admin_url('post.php?post=' . $attachment_id . '&action=edit')); ?>"
                   class="button button-secondary wpa-edit-attachment"
                   title="<?php esc_attr_e('Edit', 'wp-attachments'); ?>"
                   target="_blank">
                    <?php echo $this->getActionIcon('edit'); ?>
                    <span class="screen-reader-text"><?php esc_html_e('Edit', 'wp-attachments'); ?></span>
                </a>
                <a href="<?php echo esc_url($this->getUnattachUrl($attachment_id)); ?>"
                   class="button button-secondary wpa-unattach-action"
                   title="<?php esc_attr_e('Unattach', 'wp-attachments'); ?>">
                    <?php echo $this->getActionIcon('unlink'); ?>
                    <span class="screen-reader-text"><?php esc_html_e('Unattach', 'wp-attachments'); ?></span>
                </a>
                <a href="<?php echo esc_url(add_query_arg('forcedelete', 'true', get_delete_post_link($attachment_id, '', true))); ?>"
                   class="button button-secondary wpa-delete-action"
                   title="<?php esc_attr_e('Delete permanently', 'wp-attachments'); ?>">
                    <?php echo $this->getActionIcon('trash'); ?>
                    <span class="screen-reader-text"><?php esc_html_e('Delete permanently', 'wp-attachments'); ?></span>
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