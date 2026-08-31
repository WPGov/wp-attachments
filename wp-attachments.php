<?php
/*
Plugin Name: WP Attachments
Plugin URI:   https://wordpress.org/plugins/wp-attachments
Description: Powerful solution to manage and show your WordPress media in posts and pages
Author: Marco Milesi
Author URI:   https://www.marcomilesi.com
Version: 6.0.2
Requires at least: 4.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wp-attachments
*/

// Keep in sync with the Version header above.
define( 'WPATT_VERSION', '6.0' );

require_once( plugin_dir_path(__FILE__) . 'inc/attach_unattach_reattach.php' );

add_action('init', function () {
    load_plugin_textdomain( 'wp-attachments' );

    $wpa_ict = (int) get_option('wpa_ict', 0);
    wp_enqueue_style('wpa-css', plugin_dir_url(__FILE__) . 'styles/' . $wpa_ict . '/wpa.css');
});

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

    $excludelogged = true;
    if ( get_option('wpatt_excludelogged_counter') ) {
        $excludelogged = !is_user_logged_in();
    }

    if ( $excludelogged && wpa_is_countable_request() && wpa_is_valid_download($download_id) ) {
        $newcounter = intval(get_post_meta($download_id, "wpa-download", true));
        $newcounter++;
        update_post_meta($download_id, 'wpa-download', $newcounter );
    }

    $redirect_url = wp_get_attachment_url($download_id);
    if ($redirect_url) {
        wp_safe_redirect(esc_url_raw($redirect_url));
        exit;
    }
});

/**
 * Handle redirect after deleting an attachment from the metabox
 */
add_action('deleted_post', function($post_id, $post) {
    if ($post->post_type !== 'attachment') {
        return;
    }

    if (isset($_REQUEST['forcedelete']) && $_REQUEST['forcedelete'] === 'true') {
        $referer = wp_get_referer();
        if ($referer && strpos($referer, 'post.php') !== false) {
            wp_safe_redirect(remove_query_arg('message', $referer));
            exit;
        }
    }
}, 10, 2);

add_action('admin_init', function() {
    load_plugin_textdomain( 'wp-attachments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

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

    if ( !is_object($post) || empty($post->ID) || get_post_meta($post->ID, 'wpa_off', true) || post_password_required() || ( get_option('wpatt_option_restrictload') && !is_single() && !is_page() ) ) {
        return $content;
    }

    $enabled = get_option('wpatt_enable_metabox_' . $post->post_type, '1');
    if ( $enabled !== '1' ) {
        return $content;
    }

    return $content . wpatt_get_attachments_html( $post->ID );
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
        $content_l = '<!-- WP Attachments -->
        <div class="wpa-attachments-block">
            <div class="wpa-attachments-head">
                <h3 class="wpa-attachments-title">' . esc_html( get_option('wpatt_option_localization') ) . '</h3>'
                . $orderby_html . '
            </div>
        <ul class="post-attachments">';

        foreach ($attachments as $attachment) {
            $include_images = get_option('wpatt_option_includeimages');
            if ($include_images !== '1' && wp_attachment_is_image( $attachment->ID )) {
                continue;
            }

            if ( !apply_filters( 'wpatt_accepted_formats', sanitize_title($attachment->post_mime_type) ) ) {
                continue;
            }

            $class = "post-attachment mime-" . sanitize_title($attachment->post_mime_type);

            $file_path = get_attached_file($attachment->ID);
            $wpatt_fs = (file_exists($file_path)) ? wpatt_format_bytes(filesize($file_path)) : 'ERROR';

            $wpatt_date = new DateTime($attachment->post_date);

            switch ( get_option('wpa_template') ) {
                case 1:
                    $wpattachments_string = '<a href="%URL%">%TITLE%</a> <small>(%SIZE%)</small> <span class="wpa-attachment-date">%DATE%</span>';
                    break;
                case 2:
                    $wpattachments_string = '<a href="%URL%">%TITLE%</a> <small>&bull; %SIZE% &bull; %DOWNLOADS% click</small> <span class="wpa-attachment-date">%DATE%</span><br><small>%CAPTION%</small>';
                    break;
                case 3:
                    // Legacy templates were stored HTML-encoded, so they still need
                    // decoding -- but kses must run again afterwards, otherwise an
                    // encoded <script> smuggled past the save-time wp_kses_post()
                    // would be decoded straight into the page.
                    $wpattachments_string = wp_kses_post( html_entity_decode( get_option('wpa_template_custom') ) );
                    break;
                default:
                    $wpattachments_string = '<a href="%URL%">%TITLE%</a> <small>(%SIZE%)</small>';
            }

            $wpattachments_string = apply_filters( 'wpatt_before_entry_html', $wpattachments_string );

            if ( get_option('wpatt_option_targetblank') ) {
                $wpattachments_string = str_replace('<a href', '<a target="_blank" rel="noopener noreferrer" href', $wpattachments_string);
            }

            if ( get_option('wpatt_counter') ) {
                $url = add_query_arg( 'download', $attachment->ID, get_permalink() );
            } else {
                $url = wp_get_attachment_url($attachment->ID);
            }

            $wpattachments_string = str_replace("%URL%", esc_url( $url ), $wpattachments_string);
            $wpattachments_string = str_replace("%TITLE%", esc_html( $attachment->post_title ), $wpattachments_string);
            $wpattachments_string = str_replace("%SIZE%", esc_html( $wpatt_fs ), $wpattachments_string);
            $wpatt_date_format = get_option('wpatt_option_date_localization');
            if ( '' === trim( (string) $wpatt_date_format ) ) {
                $wpatt_date_format = get_option('date_format');
            }
            $wpattachments_string = str_replace("%DATE%", esc_html( date_i18n( $wpatt_date_format, strtotime($attachment->post_date) ) ), $wpattachments_string);
            $wpattachments_string = str_replace("%CAPTION%", esc_html( $attachment->post_excerpt ), $wpattachments_string);
            $wpattachments_string = str_replace("%DESCRIPTION%", esc_html( $attachment->post_content ), $wpattachments_string);
            $wpattachments_string = str_replace("%AUTHOR%", esc_html( get_the_author_meta( 'display_name', $attachment->post_author) ), $wpattachments_string);
            $wpattachments_string = str_replace("%DOWNLOADS%", intval(wpa_get_downloads($attachment->ID)), $wpattachments_string);

            $content_l .= '<li class="' . esc_attr($class) . '">' . apply_filters( 'wpatt_after_entry_html', $wpattachments_string ) . '</li>';
            $toShow = 1;
        }
        $content_l .= '</ul></div>';
        if ( $toShow ) {
            $content .= apply_filters( 'wpatt_list_html', $content_l );
        }
    }
    return $content;
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

add_action('admin_init', function() {
    if (get_option('wpatt_option_localization') == '') {
        $value = __('Attachments','wp-attachments');
        update_option('wpatt_option_localization', $value);
    }
});

add_action('admin_menu', function() {
    add_options_page('WP Attachments - Settings', 'WP Attachments', 'manage_options', 'wpatt-option-page', 'wpatt_plugin_options');
});

function wpa_register_initial_settings() {
    if ( !get_option('wpatt_option_localization') ) {
        update_option('wpatt_option_localization', __('Attachments','wp-attachments'));
    }
    if ( !get_option('wpatt_option_date_localization') ) {
        update_option('wpatt_option_date_localization', 'd.m.Y');
    }
    if ( !get_option('wpa_ict') ) {
        update_option('wpa_ict', '0');
    }
    if ( !get_option('wpa_template') ) {
        update_option('wpa_template', '0');
    }
}

?>
