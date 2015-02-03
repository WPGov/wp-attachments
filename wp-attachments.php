<?php
/*
Plugin Name: WP Attachments
Plugin URI: http://marcomilesi.ml
Description: Automatically shows your attachments under every post and page content. Simple. Automatic. Easy. As it has to be!
Author: Marco Milesi
Version: 3.5.7
Author URI: http://marcomilesi.ml
*/

function wpa_action_init()
{
    load_plugin_textdomain( 'wp-attachments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    update_option( 'wpa_version_number', '3.5.7' );
    wp_enqueue_style('wpa-css', plugin_dir_url(__FILE__) . 'styles/frontend.css');
}

// Add actions
add_action('init', 'wpa_action_init');
require_once(plugin_dir_path(__FILE__) . 'settings.php');
require_once(plugin_dir_path(__FILE__) . 'ij-post-attachments.php');
require_once(plugin_dir_path(__FILE__) . 'attach_unattach_reattach.php');

function wpatt_format_bytes($a_bytes)
    {

    if ($a_bytes < 1024)
        {

        return '< 1KB';

        }
    elseif ($a_bytes < 1048576)
        {

        return round($a_bytes / 1024, 2) . ' KB';

        }
    elseif ($a_bytes < 1073741824)
        {

        return round($a_bytes / 1048576, 2) . ' MB';

        }
    elseif ($a_bytes < 1099511627776)
        {

        return round($a_bytes / 1073741824, 2) . ' GB';

        }
    else
        {

        return round($a_bytes / 1208925819614629174706176, 2) . ' ERROR';

        }

    }


add_filter('the_content', 'wpatt_job_cpt_template_filter');

function wpatt_job_cpt_template_filter($content)
    {
    global $post;
    $somethingtoshow = 0;
    $content_l = null;

    if ($post->ID == '0' || $post->ID == NULL) { return $content; } //Skip the attachments list if POST ID is null

    $attachments = get_posts(array(
        'post_type' => 'attachment',
        'orderby' => 'menu_order',
        'order' => 'ASC',
        'posts_per_page' => 100,
        'post_parent' => $post->ID
    ));

    if ($attachments)
        {
        $content_l .= '<div style="width:100%;margin:10px 0 10px 0;"><h3>' . get_option('wpatt_option_localization') . '</h3>
        <ul class="post-attachments">';

        foreach ($attachments as $attachment)
            {

            $wpatt_option_includeimages_get = get_option('wpatt_option_includeimages');
            if ($wpatt_option_includeimages_get == '1') {
            } else if ( wp_attachment_is_image( $attachment->ID ) ) {
                continue;
            }
            $somethingtoshow = 1;

            $class = "post-attachment mime-" . sanitize_title($attachment->post_mime_type);

             $wpatt_option_targetblank_get = get_option('wpatt_option_targetblank');

            $content_l .= '<li class="' . $class . '"><a ';

            if ($wpatt_option_targetblank_get == '1') { $content_l .= 'target="_blank" '; }

            if ((file_exists(get_attached_file($attachment->ID)))) {
                $wpatt_fs = wpatt_format_bytes(filesize(get_attached_file($attachment->ID)));
            } else {
                $wpatt_fs = 'not found';
            }

            $content_l .= 'href="' . wp_get_attachment_url($attachment->ID) . '">' . $attachment->post_title . '</a> (' . $wpatt_fs;


            $wpatt_option_showdate_get = get_option('wpatt_option_showdate');

            if ($wpatt_option_showdate_get == '1')
                {

                $wpatt_date = new DateTime($attachment->post_date);

                $content_l .= '<div style="float:right;">' . $wpatt_date->format('d.m.Y') . '</div>';

                }

            $content_l .= ')</li>';

            }
        $content_l .= '</ul></div>';

        }
        if ($somethingtoshow == 1) {
            $content .= $content_l;
        }
    return $content;
    }


/* Register Settings */

add_action('admin_init', 'wpatt_reg_settings');

function wpatt_reg_settings() {
        register_setting('wpatt_options_group', 'wpatt_option_showdate', 'intval');
    register_setting('wpatt_options_group', 'wpatt_option_includeimages', 'intval');
        register_setting('wpatt_options_group', 'wpatt_option_localization');
    register_setting('wpatt_options_group', 'wpatt_disable_backend');
    register_setting('wpatt_options_group', 'wpatt_option_targetblank', 'intval');

    /* Preopulate 'Attachments' */

    $wpatt_option_showdate_get = get_option('wpatt_option_showdate');

    if (get_option('wpatt_option_localization') == '')
        {
        $value = __('Attachments','wp-attachments');
        update_option('wpatt_option_localization', $value);
        }
    }

add_action('admin_menu', 'wpatt_plugin_menu');

function wpatt_plugin_menu(){
    add_options_page('WP Attachments - Settings', 'WP Attachments', 'manage_options', 'wpatt-option-page', 'wpatt_plugin_options');
}

?>
