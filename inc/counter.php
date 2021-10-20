<?php

    function wpa_download_attachment_columns($columns) {
    wpa_admin_head();
	$columns['wpa-download'] = __("Downloads");
	return $columns;
    }
    add_filter("manage_media_columns", "wpa_download_attachment_columns", null, 2);

    function wpa_download_show_column($name) {
        global $post;
        switch ($name) {
            case 'wpa-download':
                $value = '<center><span style="display: initial;" class="dashicons dashicons-download"></span> '.wpa_get_downloads($post->ID).'</center>';
                echo $value;
                break;
        }
    }
    add_action('manage_media_custom_column', 'wpa_download_show_column', null, 2);

    function wpa_column_register_sortable( $columns ) {
        $columns['wpa-download'] = 'wpa-download';

        return $columns;
    }
    add_filter( 'manage_upload_sortable_columns', 'wpa_column_register_sortable' );

    function wpa_column_orderby( $vars ) {
        
        if ( isset( $vars['orderby'] ) && 'wpa-download' == $vars['orderby'] ) {
            $vars = array_merge( $vars, array(
                'meta_key' => 'wpa-download',
                'orderby' => 'meta_value_num'
            ) );
        }

        return $vars;
    }
    add_filter( 'request', 'wpa_column_orderby' );

    //add_action('admin_head', 'hidey_admin_head');

    function wpa_admin_head() {
        echo '<style type="text/css">';
        echo '#wpa-download {   width: 110px; }';
        echo '</style>';
    }

?>