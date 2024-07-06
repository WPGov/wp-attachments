<?php
/*
Plugin Name: WP Attachments
Plugin URI:   https://wordpress.org/plugins/wp-attachments
Description: Powerful solution to manage and show your WordPress media in posts and pages
Author: Marco Milesi
Author URI:   https://www.marcomilesi.com
Version: 5.0.12
Text Domain: wp-attachments
*/

require_once( plugin_dir_path(__FILE__) . 'inc/attach_unattach_reattach.php' );

function wpa_action_init() {
    
    load_plugin_textdomain( 'wp-attachments' );
    
    if (get_option('wpatt_counter') && isset($_GET['download']) ) {
        if ( !is_attachment() ) {

            $excludelogged = true;
            if ( get_option('wpatt_excludelogged_counter') ) { //voglio escludere
                $excludelogged =  !is_user_logged_in();
            }

            if ( $excludelogged && wpa_is_valid_download($_GET['download']) ) {
                $newcounter = get_post_meta($_GET['download'], "wpa-download", true);
                if (!$newcounter) { $newcounter = 0; }
                $newcounter++;
                update_post_meta($_GET['download'], 'wpa-download', $newcounter );
            }
            wp_redirect(esc_url_raw(wp_get_attachment_url($_GET['download'])));
            exit;
        }
    }

    if (get_option('wpa_ict') > 0) { $wpa_ict = get_option('wpa_ict'); } else { $wpa_ict = 0; }
    wp_enqueue_style('wpa-css', plugin_dir_url(__FILE__) . 'styles/'.$wpa_ict.'/wpa.css');
} add_action('init', 'wpa_action_init');

add_action('admin_init', function() {
    load_plugin_textdomain( 'wp-attachments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    require_once(plugin_dir_path(__FILE__) . 'inc/settings.php');
    require_once(plugin_dir_path(__FILE__) . 'inc/ij-post-attachments.php');
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

    $somethingtoshow = 0;
    $content_l = null;

    $checkrestrict = false;
    if ( get_option('wpatt_option_restrictload') && !is_single() && !is_page() ) { $checkrestrict = true; }

    if ($post->ID == '0' || $post->ID == NULL || get_post_meta($post->ID, 'wpa_off', true) || post_password_required() || $checkrestrict ) { return $content; }

    $orderby = get_query_var('orderby');
    if ($orderby == '') {
        $orderby = 'menu_order';
        $horderby = ' selected';
        $order = 'ASC';
    } else if ($orderby == 'date' ) {
        $hdate = ' selected';
        $order = 'DESC';
    } else if ($orderby == 'title' ) {
        $htitle = ' selected';
        $order = 'ASC';
    }
    
    $attachments = get_posts(array(
        'post_type' => 'attachment',
        'orderby' => $orderby,
        'order' => $order,
        'posts_per_page' => -1,
        'post_status'   => 'any',
        'post_parent' => $post->ID
    ));

    $orderby_html = '';
    if ( get_option('wpatt_show_orderby') != 0 && count($attachments) > 1 ) {
        $orderby_html = '<div style="float:right;">
                <form>
                <select name="orderby" onchange="if (this.value) window.location.replace(this.value);">
                  <option'.$horderby.' value="'.esc_url(remove_query_arg( 'orderby' )).'">'. __('Order by') .'</option>
                  <option'.$hdate.' value="'.esc_url(add_query_arg( 'orderby', 'date')).'">'. __('Date') .'</option>
                  <option'.$htitle.' value="'.esc_url(add_query_arg( 'orderby', 'title')).'">'. __('Name') .'</option>
                </select>
                </form>
            </div>';
    }
    
    if ($attachments)
        {

        $content_l .= '<!-- WP Attachments -->
        <div style="width:100%;margin:10px 0 10px 0;">
            <h3>' . $orderby_html . esc_html(sanitize_text_field( get_option('wpatt_option_localization') )). '</h3>
        <ul class="post-attachments">';

        foreach ($attachments as $attachment)
            {

            $wpatt_option_includeimages_get = get_option('wpatt_option_includeimages');
            if ($wpatt_option_includeimages_get == '1') {
            } else if ( wp_attachment_is_image( $attachment->ID ) ) {
                continue;
            }
            
            if ( !apply_filters( 'wpatt_accepted_formats', sanitize_title($attachment->post_mime_type) ) ) {
                continue;
            }
            
            $somethingtoshow = 1;

            $class = "post-attachment mime-" . sanitize_title($attachment->post_mime_type);

            if ((file_exists(get_attached_file($attachment->ID)))) {
                $wpatt_fs = wpatt_format_bytes(filesize(get_attached_file($attachment->ID)));
            } else {
                $wpatt_fs = 'ERROR';
            }
            $wpatt_date = new DateTime($attachment->post_date);

            switch ( get_option('wpa_template') ) {
                case 1: //STANDARD WITH DATE
                    $wpattachments_string = '<a href="%URL%">%TITLE%</a> <small>(%SIZE%)</small> <div style="float:right;">%DATE%</div>';
                    break;
                case 2: //EXTENDED
                    $wpattachments_string = '<a href="%URL%">%TITLE%</a> <small>&bull; %SIZE% &bull; %DOWNLOADS% click</small> <div style="float:right;">%DATE%</div><br><small>%CAPTION%</small>';
                    break;
                case 3: //CUSTOM
                    $wpattachments_string =  html_entity_decode( get_option('wpa_template_custom') );
                    break;
                default: //DEFAULT
                    $wpattachments_string = '<a href="%URL%">%TITLE%</a> <small>(%SIZE%)</small>';
            }

            $wpattachments_string = apply_filters( 'wpatt_before_entry_html', $wpattachments_string );
            
            if ( get_option('wpatt_option_targetblank') ) {
                $wpattachments_string = str_replace('<a href', '<a target="_blank" href', $wpattachments_string);
            }

            if ( get_option('wpatt_counter') ) {
                $url = add_query_arg( 'download', $attachment->ID, get_permalink() );
            } else {
                $url = wp_get_attachment_url($attachment->ID);
            }
            $wpattachments_string = str_replace("%URL%", esc_url( $url ), $wpattachments_string);
            $wpattachments_string = str_replace("%TITLE%", sanitize_text_field( $attachment->post_title ), $wpattachments_string);
            $wpattachments_string = str_replace("%SIZE%", $wpatt_fs, $wpattachments_string);
            $wpattachments_string = str_replace("%DATE%", $wpatt_date->format(get_option('wpatt_option_date_localization')), $wpattachments_string);
            $wpattachments_string = str_replace("%CAPTION%", sanitize_text_field( $attachment->post_excerpt ), $wpattachments_string);
            $wpattachments_string = str_replace("%DESCRIPTION%", sanitize_text_field( $attachment->post_content ), $wpattachments_string);
            $wpattachments_string = str_replace("%AUTHOR%", get_the_author_meta( 'display_name', $attachment->post_author), $wpattachments_string);

            $wpattachments_string = str_replace("%DOWNLOADS%", wpa_get_downloads($attachment->ID), $wpattachments_string);

            $content_l .= '<li class="' . $class . '">' . apply_filters( 'wpatt_after_entry_html', $wpattachments_string ) . '</li>';

            }
        $content_l .= '</ul></div>';

        }
        if ($somethingtoshow == 1) {
            $content .= apply_filters( 'wpatt_list_html', $content_l );
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
    if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
    //check ip from share internet
    $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
    //to check ip is pass from proxy
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
    $ip = $_SERVER['REMOTE_ADDR'];
    }
    $array[] = get_post_meta($ID, 'wpa-download-control', false );
    wpa_save_download_control($ID, $ip);
    if ( $array[0][0][0] != $ip ) {
        return true;
    } else {
        $to_time = strtotime( date('Y-m-d H:i:s') );
        $from_time = strtotime($array[0][0][1]);
        if ( round(abs($to_time - $from_time) / 60,2) <= 5 ) {
            return false;
        } else {
            return true;
        }
    }
}
function wpa_save_download_control($ID, $IP) {
    $array = array( 0 => $IP, 1 => date('Y-m-d H:i:s') );
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
