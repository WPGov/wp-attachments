<?php
/*
Plugin Name: WP Attachments
Plugin URI:   https://wordpress.org/plugins/wp-attachments
Description: Powerful solution to manage and show your WordPress media in posts and pages
Author: Marco Milesi
Author URI:   https://www.marcomilesi.com
Version: 5.2
Text Domain: wp-attachments
*/

require_once( plugin_dir_path(__FILE__) . 'inc/attach_unattach_reattach.php' );

function wpa_action_init() {
    load_plugin_textdomain( 'wp-attachments' );

    // Only process download if counter is enabled and download param is present
    if ( get_option('wpatt_counter') && isset($_GET['download']) ) {
        if ( !is_attachment() ) {
            $download_id = intval($_GET['download']); // Sanitize input

            $excludelogged = true;
            if ( get_option('wpatt_excludelogged_counter') ) {
                $excludelogged = !is_user_logged_in();
            }

            if ( $excludelogged && wpa_is_valid_download($download_id) ) {
                $newcounter = intval(get_post_meta($download_id, "wpa-download", true));
                $newcounter++;
                update_post_meta($download_id, 'wpa-download', $newcounter );
            }
            $redirect_url = wp_get_attachment_url($download_id);
            if ($redirect_url) {
                wp_safe_redirect(esc_url_raw($redirect_url));
                exit;
            }
        }
    }

    $wpa_ict = (int) get_option('wpa_ict', 0);
    wp_enqueue_style('wpa-css', plugin_dir_url(__FILE__) . 'styles/' . $wpa_ict . '/wpa.css');
}
add_action('init', 'wpa_action_init');

add_action('admin_init', function() {
    load_plugin_textdomain( 'wp-attachments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    require_once(plugin_dir_path(__FILE__) . 'inc/settings.php');
    require_once(plugin_dir_path(__FILE__) . 'inc/meta-box.php');
    if (get_option('wpatt_counter')) { require_once(plugin_dir_path(__FILE__) . 'inc/counter.php'); }

    $arraya_wpa_v = get_plugin_data ( __FILE__ );
    $nuova_versione = $arraya_wpa_v['Version'];

    update_option( 'wpa_version_number', $nuova_versione );
} );

function wpatt_format_bytes($a_bytes) {

    if ($a_bytes < 1024) {
        return '1kB';
    } elseif ($a_bytes < 1048576) {
        return ceil(round($a_bytes / 1024, 2)) . ' kB';
    } elseif ($a_bytes < 1073741824) {
        return ceil(round($a_bytes / 1048576, 2)) . ' MB';
    } elseif ($a_bytes < 1099511627776) {
        return ceil(round($a_bytes / 1073741824, 2)) . ' GB';
    } else {
        return round($a_bytes / 1208925819614629174706176, 2) . ' ERROR';
    }

}

add_action('woocommerce_order_details_after_customer_details', function( $order ) {
    if ( is_wc_endpoint_url( 'view-order' ) ) { // "My Account" > "Order View"
        echo wpatt_content_filter( '', $order );
    }
}, 10, 4 );


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

    $orderby = sanitize_text_field(get_query_var('orderby'));
    $order = 'ASC';
    $horderby = $hdate = $htitle = '';
    if ($orderby === '') {
        $orderby = 'menu_order';
        $horderby = ' selected';
    } elseif ($orderby === 'date') {
        $hdate = ' selected';
        $order = 'DESC';
    } elseif ($orderby === 'title') {
        $htitle = ' selected';
    }

    $attachments = get_posts(array(
        'post_type'      => 'attachment',
        'orderby'        => $orderby,
        'order'          => $order,
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'post_parent'    => $post->ID
    ));

    $toShow = 0;

    $orderby_html = '';
    if ( get_option('wpatt_show_orderby') != 0 && count($attachments) > 1 ) {
        $orderby_html = '<div style="float:right;">
            <form>
            <select name="orderby" onchange="if (this.value) window.location.replace(this.value);">
              <option'.$horderby.' value="'.esc_url(remove_query_arg( 'orderby' )).'">'. esc_html__('Order by', 'wp-attachments') .'</option>
              <option'.$hdate.' value="'.esc_url(add_query_arg( 'orderby', 'date')).'">'. esc_html__('Date', 'wp-attachments') .'</option>
              <option'.$htitle.' value="'.esc_url(add_query_arg( 'orderby', 'title')).'">'. esc_html__('Name', 'wp-attachments') .'</option>
            </select>
            </form>
        </div>';
    }

    if ($attachments) {
        $content_l = '<!-- WP Attachments -->
        <div style="width:100%;margin:10px 0 10px 0;">
            <h3>' . $orderby_html . esc_html( get_option('wpatt_option_localization') ) . '</h3>
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
                    $wpattachments_string = '<a href="%URL%">%TITLE%</a> <small>(%SIZE%)</small> <div style="float:right;">%DATE%</div>';
                    break;
                case 2:
                    $wpattachments_string = '<a href="%URL%">%TITLE%</a> <small>&bull; %SIZE% &bull; %DOWNLOADS% click</small> <div style="float:right;">%DATE%</div><br><small>%CAPTION%</small>';
                    break;
                case 3:
                    $wpattachments_string = html_entity_decode( get_option('wpa_template_custom') );
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
            $wpattachments_string = str_replace("%DATE%", esc_html( date_i18n( get_option('date_format'), strtotime($attachment->post_date) ) ), $wpattachments_string);
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
function wpa_is_valid_download($ID) {
    $ID = intval($ID);
    $ip = '';
    if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
        $ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
    } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
    } else {
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
    }
    $array = get_post_meta($ID, 'wpa-download-control', false );
    wpa_save_download_control($ID, $ip);

    if ( empty($array) || empty($array[0][0]) || $array[0][0] !== $ip ) {
        return true;
    } else {
        $to_time = strtotime( current_time('mysql') );
        $from_time = isset($array[0][1]) ? strtotime($array[0][1]) : 0;
        if ( $from_time && round(abs($to_time - $from_time) / 60,2) <= 5 ) {
            return false;
        } else {
            return true;
        }
    }
}

function wpa_save_download_control($ID, $IP) {
    $ID = intval($ID);
    $IP = sanitize_text_field($IP);
    $array = array( 0 => $IP, 1 => current_time('mysql') );
    update_post_meta($ID, 'wpa-download-control', $array );
    return;
}

add_action('admin_init', function() {
    $wpatt_option_showdate_get = get_option('wpatt_option_showdate');
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
