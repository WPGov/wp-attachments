<?php
/**
 * Uninstall routine for WP Attachments.
 *
 * Runs only when the plugin is deleted from the Plugins screen.
 * Removes all plugin data including visitor IP records.
 *
 * @package WP_Attachments
 */

// Never run this file outside of the WordPress uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Clean up IP data that was stored by the plugin.
// Keep download counters (wpa-download) and post settings (wpa_off).
delete_post_meta_by_key( 'wpa-download-control' );
