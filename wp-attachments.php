<?php
/*
Plugin Name: WP Attachments
Plugin URI:   https://wordpress.org/plugins/wp-attachments
Description: Powerful solution to manage and show your WordPress media in posts and pages
Author: Marco Milesi
Author URI:   https://www.marcomilesi.com
Version: 6.1
Requires at least: 4.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wp-attachments
*/

// Keep in sync with the Version header above: it versions the CSS and JS
// URLs, so a stale value keeps browsers on the old files after an update.
define( 'WPATT_VERSION', '6.1' );

require_once( plugin_dir_path(__FILE__) . 'inc/attach_unattach_reattach.php' );
require_once( plugin_dir_path(__FILE__) . 'inc/file-types.php' );

add_action('init', function () {
    load_plugin_textdomain( 'wp-attachments' );
});

// Frontend only: enqueueing on 'init' also loaded it in wp-admin and on the login screen.
add_action('wp_enqueue_scripts', 'wpatt_enqueue_list_styles');

// Settings screen: the template previews use the real list styles.
add_action('admin_enqueue_scripts', function ($hook) {
    if ('settings_page_wpatt-option-page' === $hook) {
        wpatt_enqueue_list_styles();
    }
});

/**
 * Stylesheets of the attachments list: the shared one, then the icon pack.
 *
 * Also used by the settings screen, so its previews look like the site.
 */
function wpatt_enqueue_list_styles() {
    $base = plugin_dir_url(__FILE__) . 'styles/';

    wp_enqueue_style('wpa-common', $base . 'common.css', array(), WPATT_VERSION);
    wp_enqueue_style('wpa-css', $base . wpatt_icon_pack() . '/wpa.css', array('wpa-common'), WPATT_VERSION);
}

/** Modern icon pack: inline SVG icons instead of PNG backgrounds. */
define( 'WPATT_PACK_MODERN', 5 );

/** Card template: always uses the Modern icons, whatever the pack. */
define( 'WPATT_TEMPLATE_CARD', 4 );

/**
 * Selected icon pack, 0-5. Modern is the default for new installs.
 *
 * @return int
 */
function wpatt_icon_pack() {
    // Fallback 0, not Modern: sites updated from an older version may never
    // have saved this option, and must keep the pack they have always shown.
    // New installs get Modern from wpa_register_initial_settings().
    $pack = (int) get_option( 'wpa_ict', 0 );

    return ( $pack >= 0 && $pack <= WPATT_PACK_MODERN ) ? $pack : 0;
}

/**
 * Selected display template, 0-4.
 *
 * @return int
 */
function wpatt_template_id() {
    $template = (int) get_option( 'wpa_template', 0 );

    return ( $template >= 0 && $template <= WPATT_TEMPLATE_CARD ) ? $template : 0;
}

/**
 * How the SVG icons are coloured: 'type' (a colour per file type), 'theme'
 * (the text colour of the theme) or 'custom' (one colour chosen in the settings).
 *
 * @return string
 */
function wpatt_icons_color_mode() {
    $mode = get_option( 'wpatt_icons_color', 'type' );

    return in_array( $mode, array( 'type', 'theme', 'custom' ), true ) ? $mode : 'type';
}

/**
 * The custom icon colour, as a hex value.
 *
 * @return string
 */
function wpatt_icons_custom_color() {
    $color = sanitize_hex_color( (string) get_option( 'wpatt_icons_custom_color', '#1c5f96' ) );

    return $color ? $color : '#1c5f96';
}

/**
 * Class and inline style that colour the SVG icons inside an element.
 *
 * The custom colour travels as a CSS variable on the wrapper, so a single
 * rule in common.css covers it and nothing is printed in the page head.
 *
 * @return array { @type string $class Class to append, with a leading space. @type string $style Inline style. }
 */
function wpatt_icons_color_attrs() {
    switch ( wpatt_icons_color_mode() ) {
        case 'custom':
            return array( 'class' => ' wpa-icons-custom', 'style' => '--wpa-custom:' . wpatt_icons_custom_color() );
        case 'theme':
            return array( 'class' => '', 'style' => '' );
        default:
            return array( 'class' => ' wpa-icons-color', 'style' => '' );
    }
}

/**
 * Handle ?download=ID hits: count the click, then redirect to the real file.
 *
 * Runs on 'template_redirect' because conditional tags such as is_attachment()
 * are not reliable before the main query has run.
 */
add_action('template_redirect', function () {
    if ( ! get_option('wpatt_counter') || ! isset($_GET['download']) ) {
        return;
    }

    if ( is_attachment() ) {
        return;
    }

    $download_id = absint( wp_unslash($_GET['download']) );
    if ( ! $download_id || get_post_type($download_id) !== 'attachment' ) {
        return;
    }

    if ( ! wpa_can_download( $download_id ) ) {
        return;
    }

    $excludelogged = true;
    if ( get_option('wpatt_excludelogged_counter') ) {
        $excludelogged = !is_user_logged_in();
    }

    if ( $excludelogged && wpa_is_countable_request() && wpa_is_valid_download($download_id) ) {
        $newcounter = intval(get_post_meta($download_id, "wpa-download", true));
        $newcounter++;
        update_post_meta($download_id, 'wpa-download', $newcounter );
    }

    // wp_redirect(), not wp_safe_redirect(): the URL comes from the database,
    // not from the request, and media offloaded to a CDN or S3 lives on
    // another host -- which wp_safe_redirect() turned into a jump to wp-admin.
    $redirect_url = wp_get_attachment_url($download_id);
    if ($redirect_url) {
        wp_redirect(esc_url_raw($redirect_url));
        exit;
    }
});

/**
 * May the current visitor follow a ?download= link to this attachment?
 *
 * The redirect reveals the file URL, so it follows the visibility of the
 * content the file is attached to: an ID must not be enough to dig out the
 * files of private, draft or password protected posts.
 *
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function wpa_can_download( $attachment_id ) {
    $attachment = get_post( $attachment_id );
    if ( ! $attachment ) {
        return false;
    }

    $parent_id = (int) $attachment->post_parent;
    $allowed   = true;

    if ( $parent_id ) {
        // WooCommerce orders, which under HPOS are not posts: customers may
        // follow the links shown on their own order.
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $parent_id ) : false;

        if ( $order ) {
            $allowed = current_user_can( 'view_order', $parent_id ) || current_user_can( 'edit_shop_orders' );
        } else {
            $parent = get_post( $parent_id );

            if ( ! $parent ) {
                $allowed = true; // Orphaned: behaves like an unattached file.
            } elseif ( post_password_required( $parent ) ) {
                $allowed = false;
            } else {
                $public  = function_exists( 'is_post_publicly_viewable' )
                    ? is_post_publicly_viewable( $parent )
                    : ( 'publish' === get_post_status( $parent ) );
                $allowed = $public || current_user_can( 'read_post', $parent_id );
            }
        }
    }

    /**
     * Filter whether the current visitor may download an attachment through the counter link.
     *
     * @param bool $allowed       Whether the download is allowed.
     * @param int  $attachment_id Attachment ID.
     */
    return (bool) apply_filters( 'wpatt_can_download', $allowed, $attachment_id );
}

/**
 * Send the user back to the editor after deleting an attachment from the metabox.
 *
 * Core redirects to the Media Library, so the destination is swapped through
 * the wp_redirect filter. It must not redirect and exit from 'deleted_post':
 * wp_delete_attachment() removes the files from disk only after that hook,
 * so exiting there left the original and every thumbnail publicly reachable.
 */
add_action('deleted_post', function($post_id, $post) {
    if ( ! $post || $post->post_type !== 'attachment' ) {
        return;
    }

    if ( ! isset($_REQUEST['forcedelete']) || $_REQUEST['forcedelete'] !== 'true' ) {
        return;
    }

    $referer = wp_get_referer();
    if ( ! $referer || strpos($referer, 'post.php') === false ) {
        return;
    }

    add_filter('wp_redirect', function() use ($referer) {
        return remove_query_arg('message', $referer);
    }, 99);
}, 10, 2);

add_action('admin_init', function() {
    require_once(plugin_dir_path(__FILE__) . 'inc/settings.php');
    require_once(plugin_dir_path(__FILE__) . 'inc/meta-box.php');
    require_once(plugin_dir_path(__FILE__) . 'inc/post-columns.php');
    if (get_option('wpatt_counter')) { require_once(plugin_dir_path(__FILE__) . 'inc/counter.php'); }

    // get_plugin_data() re-read and parsed this file on every admin request.
    update_option( 'wpa_version_number', WPATT_VERSION );
} );

/**
 * Format a byte count for display.
 *
 * Thin wrapper around core size_format(); kept as a function for
 * back-compat with themes that may already call it.
 *
 * @param int $a_bytes   Size in bytes.
 * @param int $decimals  Decimal places to show.
 * @return string
 */
function wpatt_format_bytes($a_bytes, $decimals = 0) {
    $a_bytes = (int) $a_bytes;

    // size_format() would render this as '0.0 B' when decimals are requested.
    if ( $a_bytes <= 0 ) {
        return size_format( 0 );
    }

    $formatted = size_format( $a_bytes, $decimals );

    return ( false === $formatted ) ? size_format( 0 ) : $formatted;
}

/**
 * Size of an attachment in bytes.
 *
 * Read from the attachment metadata first (WordPress 6.0+ stores it there),
 * so it also works when the file is not on the local disk, as with media
 * offloaded to a CDN or S3. Falls back to the local file.
 *
 * @param int $attachment_id Attachment ID.
 * @return int|false Size in bytes, or false when it cannot be determined.
 */
function wpatt_get_attachment_filesize( $attachment_id ) {
    $meta = wp_get_attachment_metadata( $attachment_id );
    if ( is_array( $meta ) && ! empty( $meta['filesize'] ) ) {
        return (int) $meta['filesize'];
    }

    $path = get_attached_file( $attachment_id );
    if ( $path && file_exists( $path ) ) {
        return (int) filesize( $path );
    }

    return false;
}

add_action('woocommerce_order_details_after_customer_details', function( $order ) {
    // "My Account" > "Order View". Under HPOS an order is not a post, so it has
    // no ->ID and must not be passed through the the_content filter.
    if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
        return;
    }
    if ( ! is_wc_endpoint_url( 'view-order' ) ) {
        return;
    }

    echo wpatt_get_attachments_html( $order->get_id() );
}, 10, 1 );

/**
 * Tell WooCommerce this plugin is safe with High-Performance Order Storage.
 * Without it WooCommerce lists the plugin as incompatible.
 */
add_action('before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});


add_filter('the_content', 'wpatt_content_filter');

function wpatt_content_filter( $content, $post = null ) {
    if ( !$post ) {
        global $post;
    }

    if ( !is_object($post) || empty($post->ID) || post_password_required() || ( get_option('wpatt_option_restrictload') && !is_single() && !is_page() ) ) {
        return $content;
    }

    if ( ! wpatt_is_frontend_enabled( $post->post_type ) || ! wpatt_is_display_enabled( $post ) ) {
        return $content;
    }

    if ( ! wpatt_should_render( $post ) ) {
        return $content;
    }

    return $content . wpatt_get_attachments_html( $post->ID );
}

/**
 * Is this the_content call one the list belongs to?
 *
 * the_content also runs for feeds, REST responses, automatic excerpts and,
 * in block themes, for every post inside Query Loop blocks such as the
 * "More posts" section under a single post -- each of which would get its
 * own copy of the list.
 *
 * @param WP_Post $post Post being rendered.
 * @return bool
 */
function wpatt_should_render( $post ) {
    $render = true;

    if ( is_feed() || doing_filter( 'get_the_excerpt' ) ) {
        $render = false;
    } elseif ( function_exists( 'wp_is_serving_rest_request' ) ? wp_is_serving_rest_request() : ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        $render = false;
    } elseif ( is_singular() && (int) get_queried_object_id() !== (int) $post->ID ) {
        // On a single post or page, only that content gets the list.
        $render = false;
    }

    /**
     * Filter whether the attachments list is appended to this content.
     *
     * @param bool    $render Whether the list is shown.
     * @param WP_Post $post   Post being rendered.
     */
    return (bool) apply_filters( 'wpatt_should_render', $render, $post );
}

/**
 * Is the frontend list switched on for this post type?
 *
 * Until "Enable Frontend" is first saved it follows "Enable Metabox", which
 * is what used to hide the list before the two were separate settings.
 *
 * @param string $post_type Post type name.
 * @return bool
 */
function wpatt_is_frontend_enabled( $post_type ) {
    $metabox = get_option( 'wpatt_enable_metabox_' . $post_type, '1' );

    return get_option( 'wpatt_enable_frontend_' . $post_type, $metabox ) === '1';
}

/**
 * Has this post not been opted out of the list?
 *
 * The list shows by default; the metabox toggle is the exception that turns
 * it off, stored as 'wpa_off' = '1'.
 *
 * @param WP_Post|int $post Post object or ID.
 * @return bool
 */
function wpatt_is_display_enabled( $post ) {
    $post = get_post( $post );
    if ( ! $post ) {
        return false;
    }

    return '1' !== (string) get_post_meta( $post->ID, 'wpa_off', true );
}

/**
 * Build the attachments list for a given parent ID.
 *
 * Kept separate from the the_content filter so callers that are not posts --
 * WooCommerce orders under HPOS, for instance -- can render the same list
 * without faking a WP_Post object.
 *
 * @param int $parent_id Parent object ID.
 * @return string HTML, or an empty string when there is nothing to show.
 */
function wpatt_get_attachments_html( $parent_id ) {
    $parent_id = absint( $parent_id );
    if ( ! $parent_id ) {
        return '';
    }

    $content = '';

    $orderby = sanitize_text_field(get_query_var('orderby'));
    $order   = 'ASC';
    if ($orderby === 'date') {
        $order = 'DESC';
    } elseif ($orderby !== 'title') {
        $orderby = 'menu_order';
    }

    $attachments = get_posts(array(
        'post_type'      => 'attachment',
        'orderby'        => $orderby,
        'order'          => $order,
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'post_parent'    => $parent_id
    ));

    $toShow = 0;

    $orderby_html = '';
    if ( get_option('wpatt_show_orderby') != 0 && count($attachments) > 1 ) {
        $sort_links = array(
            'menu_order' => array( esc_html__( 'Default', 'wp-attachments' ), remove_query_arg( 'orderby' ) ),
            'date'       => array( esc_html__( 'Date', 'wp-attachments' ),    add_query_arg( 'orderby', 'date' ) ),
            'title'      => array( esc_html__( 'Name', 'wp-attachments' ),    add_query_arg( 'orderby', 'title' ) ),
        );

        $sort_items = '';
        foreach ( $sort_links as $sort_key => $sort_link ) {
            $is_current  = ( $orderby === $sort_key );
            $sort_items .= '<a class="wpa-orderby-link' . ( $is_current ? ' is-current' : '' ) . '"'
                . ' href="' . esc_url( $sort_link[1] ) . '"'
                . ( $is_current ? ' aria-current="true"' : '' ) . '>'
                . $sort_link[0] . '</a>';
        }

        $orderby_html = '<span class="wpa-orderby"><span class="wpa-orderby-label">'
            . esc_html__( 'Sort by:', 'wp-attachments' ) . '</span>' . $sort_items . '</span>';
    }

    if ($attachments) {
        // The default applies only until the settings are first saved; an
        // empty header saved on purpose hides the heading.
        $heading_text = trim( (string) get_option( 'wpatt_option_localization', __( 'Attachments', 'wp-attachments' ) ) );
        $heading_tag  = wpatt_heading_tag();
        $heading_html = ( '' !== $heading_text )
            ? '<' . $heading_tag . ' class="wpa-attachments-title">' . esc_html( $heading_text ) . '</' . $heading_tag . '>'
            : '';

        $head_html = ( '' !== $heading_html || '' !== $orderby_html )
            ? '<div class="wpa-attachments-head">' . $heading_html . $orderby_html . '</div>'
            : '';

        $template_id = wpatt_template_id();
        $template    = wpatt_get_template_string( $template_id );
        $items       = '';

        foreach ($attachments as $attachment) {
            $include_images = get_option('wpatt_option_includeimages');
            if ($include_images !== '1' && wp_attachment_is_image( $attachment->ID )) {
                continue;
            }

            if ( !apply_filters( 'wpatt_accepted_formats', sanitize_title($attachment->post_mime_type) ) ) {
                continue;
            }

            $items .= wpatt_render_list_item( $template_id, $template, wpatt_get_entry_data( $attachment ) );
            $toShow = 1;
        }

        if ( $toShow ) {
            $content .= apply_filters( 'wpatt_list_html', wpatt_wrap_list( $items, $head_html, $template_id ) );
        }
    }
    return $content;
}

/**
 * Markup of a display template, with its %TAG% placeholders.
 *
 * @param int        $template_id 0 simple, 1 with date, 2 detailed, 3 custom, 4 Modern Card.
 * @param array|null $card        Modern Card options, from wpatt_get_card_options().
 * @return string
 */
function wpatt_get_template_string( $template_id, $card = null ) {
    switch ( (int) $template_id ) {
        case 1:
            return '<a href="%URL%">%TITLE%</a> <small>(%SIZE%)</small> <span class="wpa-attachment-date">%DATE%</span>';
        case 2:
            return '<a href="%URL%">%TITLE%</a> <small>&bull; %SIZE% &bull; %DOWNLOADS% click</small> <span class="wpa-attachment-date">%DATE%</span><br><small>%CAPTION%</small>';
        case 3:
            // Legacy templates were stored HTML-encoded, so they still need
            // decoding -- but kses must run again afterwards, otherwise an
            // encoded <script> smuggled past the save-time wp_kses_post()
            // would be decoded straight into the page.
            return wp_kses_post( html_entity_decode( (string) get_option('wpa_template_custom') ) );
        case WPATT_TEMPLATE_CARD:
            return wpatt_get_card_template( is_array( $card ) ? $card : wpatt_get_card_options() );
        default:
            return '<a href="%URL%">%TITLE%</a> <small>(%SIZE%)</small>';
    }
}

/**
 * Markup of the Modern Card template for the given options.
 *
 * Each detail sits in its own <span> with no whitespace around the tag, so
 * the CSS can hide the empty ones (:empty) and put a separator only between
 * the details that are actually shown.
 *
 * @param array $card Options from wpatt_get_card_options().
 * @return string
 */
function wpatt_get_card_template( array $card ) {
    $meta = '';
    if ( $card['ext'] ) {
        $meta .= '<span>%EXT%</span>';
    }
    if ( $card['size'] ) {
        $meta .= '<span>%SIZE%</span>';
    }
    if ( $card['date'] ) {
        $meta .= '<span>%DATE%</span>';
    }
    if ( $card['downloads'] && get_option( 'wpatt_counter' ) ) {
        /* translators: %s: number of downloads. */
        $meta .= '<span>' . sprintf( esc_html__( 'Downloads: %s', 'wp-attachments' ), '%DOWNLOADS%' ) . '</span>';
    }

    // href first: the "open in a new tab" option matches '<a href'.
    return '<span class="wpa-card-icon">%ICON%</span>'
        . '<span class="wpa-card-body"><a href="%URL%" class="wpa-card-title">%TITLE%</a>'
        . ( '' !== $meta ? '<span class="wpa-card-meta">' . $meta . '</span>' : '' )
        . ( $card['caption'] ? '<span class="wpa-card-caption">%CAPTION%</span>' : '' )
        . '</span>';
}

/**
 * Values for the template tags of one attachment, not yet escaped.
 *
 * @param WP_Post $attachment Attachment.
 * @return array
 */
function wpatt_get_entry_data( $attachment ) {
    $bytes = wpatt_get_attachment_filesize( $attachment->ID );

    return array(
        'url'         => get_option('wpatt_counter')
            ? add_query_arg( 'download', $attachment->ID, get_permalink() )
            : wp_get_attachment_url( $attachment->ID ),
        'title'       => $attachment->post_title,
        'size'        => ( false !== $bytes ) ? wpatt_format_bytes( $bytes ) : '—',
        'date'        => wpatt_format_entry_date( $attachment->post_date ),
        'caption'     => $attachment->post_excerpt,
        'description' => $attachment->post_content,
        'author'      => get_the_author_meta( 'display_name', $attachment->post_author ),
        'downloads'   => (int) wpa_get_downloads( $attachment->ID ),
        'ext'         => wpatt_get_file_extension( $attachment->ID ),
        'mime'        => (string) $attachment->post_mime_type,
    );
}

/**
 * Date of an attachment in the format chosen in the settings.
 *
 * @param string $post_date Date as stored in the post.
 * @return string
 */
function wpatt_format_entry_date( $post_date ) {
    $format = get_option('wpatt_option_date_localization');
    if ( '' === trim( (string) $format ) ) {
        $format = get_option('date_format');
    }

    return date_i18n( $format, strtotime( $post_date ) );
}

/**
 * One <li> of the list.
 *
 * @param int    $template_id Template ID.
 * @param string $template    Template markup.
 * @param array  $data        Values from wpatt_get_entry_data().
 * @return string
 */
function wpatt_render_list_item( $template_id, $template, array $data ) {
    $type = wpatt_get_file_type( $data['mime'] );
    $icon = wpatt_get_file_icon_svg( $type, 'wpa-icon' );

    $html = apply_filters( 'wpatt_before_entry_html', $template );

    if ( get_option('wpatt_option_targetblank') ) {
        $html = str_replace('<a href', '<a target="_blank" rel="noopener noreferrer" href', $html);
    }

    // strtr() replaces in one pass, so a title that happens to contain a tag
    // such as %URL% is not expanded a second time.
    $html = strtr( $html, array(
        '%URL%'         => esc_url( $data['url'] ),
        '%TITLE%'       => esc_html( $data['title'] ),
        '%SIZE%'        => esc_html( $data['size'] ),
        '%DATE%'        => esc_html( $data['date'] ),
        '%CAPTION%'     => esc_html( $data['caption'] ),
        '%DESCRIPTION%' => esc_html( $data['description'] ),
        '%AUTHOR%'      => esc_html( $data['author'] ),
        '%DOWNLOADS%'   => (int) $data['downloads'],
        '%EXT%'         => esc_html( $data['ext'] ),
        '%MIME%'        => esc_html( $data['mime'] ),
        '%ICON%'        => $icon,
    ) );

    $html = apply_filters( 'wpatt_after_entry_html', $html );

    // Modern pack: the icon leads the entry. The Card template places it
    // itself through %ICON%.
    if ( WPATT_PACK_MODERN === wpatt_icon_pack() && WPATT_TEMPLATE_CARD !== (int) $template_id ) {
        $html = $icon . $html;
    }

    $class = 'post-attachment mime-' . sanitize_title( $data['mime'] ) . ' wpa-type-' . $type;

    return '<li class="' . esc_attr( $class ) . '">' . $html . '</li>';
}

/**
 * Wrap the items in the list block.
 *
 * @param string $items       <li> elements.
 * @param string $head_html   Heading and sort links, may be empty.
 * @param int        $template_id Template ID.
 * @param array|null $card        Modern Card options, from wpatt_get_card_options().
 * @return string
 */
function wpatt_wrap_list( $items, $head_html, $template_id, $card = null ) {
    $is_card  = ( WPATT_TEMPLATE_CARD === (int) $template_id );
    $uses_svg = ( WPATT_PACK_MODERN === wpatt_icon_pack() || $is_card );

    $colors      = $uses_svg ? wpatt_icons_color_attrs() : array( 'class' => '', 'style' => '' );
    $block_class = 'wpa-attachments-block' . $colors['class'];
    $list_class  = 'post-attachments';

    if ( $is_card ) {
        $card        = is_array( $card ) ? $card : wpatt_get_card_options();
        $list_class .= ' wpa-cards';
        if ( 'rows' === $card['layout'] ) {
            $list_class .= ' wpa-cards--rows';
        } elseif ( 'normal' !== $card['width'] ) {
            $list_class .= ' wpa-cards--' . $card['width'];
        }
    }

    return '<!-- WP Attachments --><div class="' . esc_attr( $block_class ) . '"'
        . ( $colors['style'] ? ' style="' . esc_attr( $colors['style'] ) . '"' : '' ) . '>' . $head_html
        . '<ul class="' . esc_attr( $list_class ) . '">' . $items . '</ul></div>';
}

/**
 * List preview for the settings screen, built from sample files.
 *
 * Goes through the same functions as the real list, so it shows what the
 * site will show with the saved icon pack.
 *
 * @param int $template_id Template ID.
 * @return string
 */
function wpatt_render_template_preview( $template_id ) {
    $samples = array(
        array(
            'title'   => __( 'Annual report', 'wp-attachments' ),
            'mime'    => 'application/pdf',
            'ext'     => 'PDF',
            'size'    => '1.2 MB',
            'caption' => __( 'Approved by the board', 'wp-attachments' ),
        ),
        array(
            'title'   => __( 'Application form', 'wp-attachments' ),
            'mime'    => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ext'     => 'DOCX',
            'size'    => '48 KB',
            'caption' => '',
        ),
    );

    $template = wpatt_get_template_string( $template_id );
    $items    = '';

    foreach ( $samples as $i => $sample ) {
        $items .= wpatt_render_list_item( $template_id, $template, array(
            'url'         => '#',
            'title'       => $sample['title'],
            'size'        => $sample['size'],
            'date'        => wpatt_format_entry_date( gmdate( 'Y-m-d H:i:s', time() - $i * DAY_IN_SECONDS ) ),
            'caption'     => $sample['caption'],
            'description' => '',
            'author'      => wp_get_current_user()->display_name,
            'downloads'   => 12 - $i * 7,
            'ext'         => $sample['ext'],
            'mime'        => $sample['mime'],
        ) );
    }

    return wpatt_wrap_list( $items, '', $template_id );
}


/* Register Settings */

function wpa_get_downloads($ID) {
    if (get_post_meta($ID, "wpa-download", true)) {
        return get_post_meta($ID, "wpa-download", true);
    } else { return 0; }
}
/**
 * Should this request be allowed to move a download counter at all?
 *
 * Filters out browser speculative loads and obvious automation, which would
 * otherwise inflate the numbers without anybody having clicked anything.
 *
 * @return bool
 */
function wpa_is_countable_request() {
    // Chrome and Firefox announce prefetch / prerender / preview loads.
    $speculative_headers = array( 'HTTP_SEC_PURPOSE', 'HTTP_PURPOSE', 'HTTP_X_PURPOSE', 'HTTP_X_MOZ' );
    foreach ( $speculative_headers as $header ) {
        if ( ! empty( $_SERVER[ $header ] )
            && preg_match( '/prefetch|prerender|preview/i', (string) $_SERVER[ $header ] ) ) {
            return false;
        }
    }

    $agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

    // No user agent at all is almost always a script.
    $countable = ( '' !== $agent );

    if ( $countable ) {
        $bots = '/bot|crawl|spider|slurp|curl|wget|python-requests|okhttp|headless'
              . '|facebookexternalhit|whatsapp|telegram|monitor|uptime|pingdom|lighthouse|preview/i';
        $countable = ! preg_match( $bots, $agent );
    }

    /**
     * Filter whether the current request may increment a download counter.
     *
     * @param bool $countable Whether the request looks like a real visitor.
     */
    return (bool) apply_filters( 'wpatt_count_download_request', $countable );
}

/**
 * Has this visitor already been counted for this file recently?
 *
 * The throttle key is a salted hash held in a transient, so nothing
 * identifying is written anywhere and the record expires by itself. The old
 * implementation stored a plain text IP address in post meta, kept only one
 * address per attachment -- which meant two visitors alternating cancelled
 * each other's throttle -- and never expired.
 *
 * @param int $ID Attachment ID.
 * @return bool True when the hit should be counted.
 */
function wpa_is_valid_download( $ID ) {
    $ID = absint( $ID );
    if ( ! $ID ) {
        return false;
    }

    // REMOTE_ADDR only: HTTP_CLIENT_IP and X_FORWARDED_FOR are attacker
    // controlled, so trusting them made the throttle trivial to bypass.
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';

    // No usable address: count the hit rather than silently drop it.
    if ( '' === $ip ) {
        return true;
    }

    /**
     * Filter how long the same visitor is ignored for the same file.
     *
     * @param int $seconds Throttle window.
     * @param int $ID      Attachment ID.
     */
    $window = (int) apply_filters( 'wpatt_download_throttle', 5 * MINUTE_IN_SECONDS, $ID );
    if ( $window < 1 ) {
        return true;
    }

    $key = 'wpa_dl_' . wp_hash( $ID . '|' . $ip );

    if ( get_transient( $key ) ) {
        return false;
    }

    set_transient( $key, 1, $window );

    return true;
}

add_action('admin_init', 'wpa_register_initial_settings', 5);
// Also on activation: WP-CLI and automated installers activate without ever
// loading an admin page, which left a new site on the legacy defaults.
register_activation_hook(__FILE__, 'wpa_register_initial_settings');

add_action('admin_menu', function() {
    add_options_page('WP Attachments - Settings', 'WP Attachments', 'manage_options', 'wpatt-option-page', 'wpatt_plugin_options');
});

/**
 * Seed the options on first run only.
 *
 * add_option() leaves existing values alone, so an empty list header or
 * date format saved on purpose is kept instead of being refilled on the
 * next admin page load.
 */
function wpa_register_initial_settings() {
    // Every earlier version stored these two on its first admin page load,
    // so their absence means a brand new install. Runs at priority 5, before
    // the admin_init callback that writes wpa_version_number.
    $fresh_install = ( false === get_option('wpatt_option_localization') && false === get_option('wpa_version_number') );

    add_option('wpatt_option_localization', __('Attachments','wp-attachments'));
    // Empty: follow the WordPress date format (Settings > General).
    add_option('wpatt_option_date_localization', '');
    add_option('wpatt_option_heading_tag', 'h3');
    add_option('wpatt_icons_color', 'type');
    add_option('wpatt_card', wpatt_card_defaults());

    // New look for new installs only: updated sites keep what they show today.
    add_option('wpa_ict', $fresh_install ? (string) WPATT_PACK_MODERN : '0');
    add_option('wpa_template', $fresh_install ? (string) WPATT_TEMPLATE_CARD : '0');
}

/**
 * Default options of the Modern Card template.
 *
 * @return array
 */
function wpatt_card_defaults() {
    return array(
        'ext'       => 1,
        'size'      => 1,
        'date'      => 1,
        'downloads' => 0,
        'caption'   => 0,
        'layout'    => 'grid',   // grid | rows
        'width'     => 'normal', // compact | normal | wide
    );
}

/**
 * Modern Card options: the saved ones, optionally overridden.
 *
 * The overrides are what a block or shortcode will pass for a single list,
 * so one page can show a wide grid and another one card per row.
 *
 * @param array $overrides Options for this list only.
 * @return array
 */
function wpatt_get_card_options( array $overrides = array() ) {
    $saved = get_option( 'wpatt_card', array() );
    $opts  = array_merge( wpatt_card_defaults(), is_array( $saved ) ? $saved : array(), $overrides );

    foreach ( array( 'ext', 'size', 'date', 'downloads', 'caption' ) as $flag ) {
        $opts[ $flag ] = empty( $opts[ $flag ] ) ? 0 : 1;
    }
    if ( ! in_array( $opts['layout'], array( 'grid', 'rows' ), true ) ) {
        $opts['layout'] = 'grid';
    }
    if ( ! in_array( $opts['width'], array( 'compact', 'normal', 'wide' ), true ) ) {
        $opts['width'] = 'normal';
    }

    return $opts;
}

/**
 * Heading level of the list title, as chosen in the settings.
 *
 * @return string One of h1-h6.
 */
function wpatt_heading_tag() {
    $tag = get_option('wpatt_option_heading_tag', 'h3');

    return in_array($tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6'), true) ? $tag : 'h3';
}

?>
