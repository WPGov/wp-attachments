<?php
/**
 * "Files" column for the post, page and custom post type list tables.
 *
 * Shows how many attachments belong to each row. The counts for the whole
 * screen are fetched with a single grouped query, not one per row.
 *
 * @package WP_Attachments
 */

/**
 * Post types that get the column: the same ones that get the metabox.
 *
 * @return string[]
 */
function wpatt_column_post_types() {
	$types = get_post_types( array( 'public' => true ), 'names' );
	$out   = array();

	foreach ( $types as $type ) {
		if ( 'attachment' === $type ) {
			continue;
		}
		if ( get_option( 'wpatt_enable_metabox_' . $type, '1' ) !== '1' ) {
			continue;
		}
		$out[] = $type;
	}

	return $out;
}

foreach ( wpatt_column_post_types() as $wpatt_type ) {
	add_filter( "manage_{$wpatt_type}_posts_columns", 'wpatt_add_files_column' );
	add_action( "manage_{$wpatt_type}_posts_custom_column", 'wpatt_render_files_column', 10, 2 );
}
unset( $wpatt_type );

/**
 * Insert the column just before Date, the conventional slot for extra metadata.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function wpatt_add_files_column( $columns ) {
	$label = esc_html_x( 'Files', 'list table column heading', 'wp-attachments' );
	$out   = array();

	foreach ( $columns as $key => $value ) {
		if ( 'date' === $key ) {
			$out['wpa_files'] = $label;
		}
		$out[ $key ] = $value;
	}

	if ( ! isset( $out['wpa_files'] ) ) {
		$out['wpa_files'] = $label;
	}

	return $out;
}

/**
 * Attachment counts for every row currently on screen.
 *
 * Primed once from the main query and reused, so a 20 row screen costs one
 * extra query rather than twenty.
 *
 * @return array<int,int> Parent post ID => number of attachments.
 */
function wpatt_get_attachment_counts() {
	static $counts = null;

	if ( null !== $counts ) {
		return $counts;
	}

	global $wp_query, $wpdb;

	$counts = array();
	$ids    = array();

	if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
		foreach ( $wp_query->posts as $post ) {
			$ids[] = is_object( $post ) ? (int) $post->ID : (int) $post;
		}
	}

	$ids = array_filter( array_unique( $ids ) );

	if ( empty( $ids ) ) {
		return $counts;
	}

	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

	// Trashed attachments are excluded, to match what the metabox lists.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_parent, COUNT(*) AS total
			 FROM {$wpdb->posts}
			 WHERE post_type = 'attachment'
			   AND post_status != 'trash'
			   AND post_parent IN ($placeholders)
			 GROUP BY post_parent",
			$ids
		)
	);

	foreach ( $rows as $row ) {
		$counts[ (int) $row->post_parent ] = (int) $row->total;
	}

	return $counts;
}

/**
 * Render one cell.
 *
 * @param string $column  Column key.
 * @param int    $post_id Row post ID.
 */
function wpatt_render_files_column( $column, $post_id ) {
	if ( 'wpa_files' !== $column ) {
		return;
	}

	$counts = wpatt_get_attachment_counts();
	$count  = isset( $counts[ $post_id ] ) ? $counts[ $post_id ] : 0;

	if ( ! $count ) {
		echo '<span class="wpa-files-count is-zero" aria-hidden="true">&mdash;</span>';
		echo '<span class="screen-reader-text">' . esc_html__( 'No files attached', 'wp-attachments' ) . '</span>';
		return;
	}

	// Media Library, filtered to this parent. WordPress passes post_parent
	// from the query string straight into the attachments query, so no extra
	// handling is needed at the other end.
	$url = add_query_arg(
		array(
			'mode'        => 'list',
			'post_parent' => (int) $post_id,
		),
		admin_url( 'upload.php' )
	);

	$label = sprintf(
		/* translators: %s: number of attached files. */
		_n( 'View %s attached file', 'View %s attached files', $count, 'wp-attachments' ),
		number_format_i18n( $count )
	);

	printf(
		'<a class="wpa-files-link" href="%1$s" title="%2$s">%3$s<span aria-hidden="true">%4$s</span><span class="screen-reader-text">%2$s</span></a>',
		esc_url( $url ),
		esc_attr( $label ),
		wpatt_paperclip_svg(),
		esc_html( number_format_i18n( $count ) )
	);
}

/**
 * Paperclip mark. Without it the cell is a bare digit that could be a count of
 * anything; with it the column reads at a glance.
 */
function wpatt_paperclip_svg() {
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
		. ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
		. '<path d="M21 11.5 12.5 20a5 5 0 0 1-7-7l8.5-8.5a3.5 3.5 0 0 1 5 5L10.5 18a2 2 0 0 1-3-3l8-8"/>'
		. '</svg>';
}

/**
 * Tell people why the Media Library is showing a subset.
 *
 * Landing on a filtered list with no explanation is disorienting: WordPress
 * gives no hint that post_parent is in play.
 */
add_action(
	'admin_notices',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'upload' !== $screen->base || empty( $_GET['post_parent'] ) ) {
			return;
		}

		$parent = get_post( absint( wp_unslash( $_GET['post_parent'] ) ) );

		if ( ! $parent ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %s: title of the post the files are attached to. */
					__( 'Showing only the files attached to "%s".', 'wp-attachments' ),
					get_the_title( $parent )
				)
			),
			esc_url( remove_query_arg( 'post_parent' ) ),
			esc_html__( 'Show all files', 'wp-attachments' )
		);
	}
);

/**
 * Column styling. Loaded only on the list tables that show it.
 */
add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}
		if ( ! in_array( $screen->post_type, wpatt_column_post_types(), true ) ) {
			return;
		}

		wp_register_style( 'wpa-post-columns', false, array(), WPATT_VERSION );
		wp_enqueue_style( 'wpa-post-columns' );
		wp_add_inline_style(
			'wpa-post-columns',
			/* .widefat th wins over a bare .column-* selector, so the header
			   needs the extra specificity or it stays left aligned. */
			'.widefat th.column-wpa_files,.widefat td.column-wpa_files{width:5.5em;text-align:center;}
.wpa-files-count{font-variant-numeric:tabular-nums;}
.wpa-files-count.is-zero{color:#a7aaad;}
.wpa-files-link{display:inline-flex;align-items:center;gap:4px;text-decoration:none;font-variant-numeric:tabular-nums;}
.wpa-files-link svg{width:13px;height:13px;flex-shrink:0;opacity:.65;}
.wpa-files-link:hover svg,.wpa-files-link:focus svg{opacity:1;}
@media screen and (max-width:782px){
.widefat th.column-wpa_files,.widefat td.column-wpa_files{width:auto;text-align:left;}
.wpa-files-link{display:inline-flex;}
}'
		);
	}
);
