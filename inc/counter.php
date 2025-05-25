<?php

if ( ! class_exists( 'WPA_Download_Counter' ) ) {
    class WPA_Download_Counter {

        public function __construct() {
            add_filter( 'manage_media_columns', [ $this, 'add_download_column' ] );
            add_action( 'manage_media_custom_column', [ $this, 'show_download_column' ], 10, 1 );
            add_filter( 'manage_upload_sortable_columns', [ $this, 'register_sortable_column' ] );
            add_filter( 'request', [ $this, 'handle_column_orderby' ] );
            add_action( 'admin_head', [ $this, 'admin_column_css' ] );
        }

        /**
         * Add Downloads column to Media Library.
         */
        public function add_download_column( $columns ) {
            $columns['wpa-download'] = __( 'Downloads', 'wp-attachments' );
            return $columns;
        }

        /**
         * Output the Downloads column content.
         */
        public function show_download_column( $column_name ) {
            global $post;
            if ( $column_name === 'wpa-download' ) {
                $downloads = (int) wpa_get_downloads( $post->ID );
                printf(
                    '<span class="wpa-download-count" title="%s"><span class="dashicons dashicons-download"></span> %s</span>',
                    esc_attr__( 'Number of downloads', 'wp-attachments' ),
                    esc_html( number_format_i18n( $downloads ) )
                );
            }
        }

        /**
         * Make the Downloads column sortable.
         */
        public function register_sortable_column( $columns ) {
            $columns['wpa-download'] = 'wpa-download';
            return $columns;
        }

        /**
         * Handle sorting by Downloads column.
         */
        public function handle_column_orderby( $vars ) {
            if ( isset( $vars['orderby'] ) && $vars['orderby'] === 'wpa-download' ) {
                $vars = array_merge( $vars, [
                    'meta_key' => 'wpa-download',
                    'orderby'  => 'meta_value_num',
                ] );
            }
            return $vars;
        }

        /**
         * Output custom CSS for the Downloads column in admin.
         */
        public function admin_column_css() {
            echo '<style>
                .column-wpa-download { width: 110px; text-align: center; }
                .wpa-download-count { display: inline-flex; align-items: center; gap: 4px; font-weight: 600; }
                .wpa-download-count .dashicons { margin-right: 2px; }
            </style>';
        }
    }

    // Initialize the class.
    new WPA_Download_Counter();
}

?>