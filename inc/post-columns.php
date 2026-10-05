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
	$label  = esc_html_x( 'Files', 'list table column heading', 'wp-attachments' );
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$out    = array();

	// List switched off for the whole post type: say so once, here.
	if ( $screen && $screen->post_type && ! wpatt_is_frontend_enabled( $screen->post_type ) ) {
		$off   = __( 'The list of files is not shown on the site for this content type.', 'wp-attachments' );
		$label = '<span class="wpa-files-heading-off" title="' . esc_attr( $off ) . '">' . $label . wpatt_eye_off_svg()
			. '<span class="screen-reader-text">' . esc_html( $off ) . '</span></span>';
	}

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

	// Whether this post shows its list on the site, as set in its metabox.
	// When the whole post type is off, every row would carry the same mark,
	// so the column heading explains it once instead.
	if ( ! wpatt_is_frontend_enabled( get_post_type( $post_id ) ) ) {
		return;
	}

	$shown = wpatt_is_display_enabled( $post_id );
	$state = $shown ? __( 'Shown on the site', 'wp-attachments' ) : __( 'Not shown on the site', 'wp-attachments' );

	printf(
		'<span class="wpa-files-state %1$s" title="%2$s">%3$s<span class="screen-reader-text">%2$s</span></span>',
		$shown ? 'is-shown' : 'is-hidden',
		esc_attr( $state ),
		$shown ? wpatt_eye_svg() : wpatt_eye_off_svg()
	);
}

/**
 * Eye: the list is shown on the frontend for this post.
 */
function wpatt_eye_svg() {
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
		. ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
		. '<path d="M2.5 12S6 5.75 12 5.75 21.5 12 21.5 12 18 18.25 12 18.25 2.5 12 2.5 12Z"/>'
		. '<circle cx="12" cy="12" r="2.75"/>'
		. '</svg>';
}

/**
 * Crossed-out eye: the list is hidden on the frontend for this post.
 */
function wpatt_eye_off_svg() {
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
		. ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
		. '<path d="M10.6 5.8A9.8 9.8 0 0 1 12 5.75c6 0 9.5 6.25 9.5 6.25a17 17 0 0 1-2.3 3.1M6.5 7.4C3.9 9.1 2.5 12 2.5 12s3.5 6.25 9.5 6.25a9.6 9.6 0 0 0 4.6-1.15"/>'
		. '<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>'
		. '<path d="M3 3l18 18"/>'
		. '</svg>';
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
 * Files filter above the list table, next to the date and category ones.
 *
 * A native select (it combines with the other filters and keeps working
 * with the core Filter button), followed by a legend with the same icons the
 * column uses -- options of a select cannot hold icons.
 */
add_action(
	'restrict_manage_posts',
	function ( $post_type, $which = 'top' ) {
		if ( 'top' !== $which || ! in_array( $post_type, wpatt_column_post_types(), true ) ) {
			return;
		}

		$frontend = wpatt_is_frontend_enabled( $post_type );
		$current  = wpatt_files_filter_mode();

		$options = array(
			''        => __( 'All files', 'wp-attachments' ),
			'with'    => __( 'With files', 'wp-attachments' ),
			'without' => __( 'Without files', 'wp-attachments' ),
		);
		if ( $frontend ) {
			$options['shown']  = __( 'Shown on the site', 'wp-attachments' );
			$options['hidden'] = __( 'Not shown on the site', 'wp-attachments' );
		}

		echo '<label for="wpa-files-filter" class="screen-reader-text">' . esc_html__( 'Filter by attached files', 'wp-attachments' ) . '</label>';
		echo '<select name="wpa_files" id="wpa-files-filter">';
		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}
		echo '</select>';

		// Decorative: the cells carry the same information as text.
		echo '<span class="wpa-files-legend" aria-hidden="true">';
		echo '<span>' . wpatt_paperclip_svg() . esc_html__( 'Files', 'wp-attachments' ) . '</span>';
		if ( $frontend ) {
			echo '<span>' . wpatt_eye_svg() . esc_html__( 'Shown', 'wp-attachments' ) . '</span>';
			echo '<span>' . wpatt_eye_off_svg() . esc_html__( 'Not shown', 'wp-attachments' ) . '</span>';
		}
		echo '</span>';
	},
	10,
	2
);

/**
 * Current value of the Files filter, or '' when it is not in use.
 *
 * @return string
 */
function wpatt_files_filter_mode() {
	$mode = isset( $_GET['wpa_files'] ) ? sanitize_key( wp_unslash( $_GET['wpa_files'] ) ) : '';

	return in_array( $mode, array( 'with', 'without', 'shown', 'hidden' ), true ) ? $mode : '';
}

/**
 * Apply the Files filter to the main list table query.
 */
add_action(
	'pre_get_posts',
	function ( $query ) {
		global $pagenow, $typenow;

		if ( ! is_admin() || ! $query->is_main_query() || 'edit.php' !== $pagenow ) {
			return;
		}

		$post_type = $typenow ? $typenow : 'post';
		$mode      = wpatt_files_filter_mode();

		if ( ! $mode || ! in_array( $post_type, wpatt_column_post_types(), true ) ) {
			return;
		}
		// Shown / not shown mean nothing while the whole post type is off.
		if ( in_array( $mode, array( 'shown', 'hidden' ), true ) && ! wpatt_is_frontend_enabled( $post_type ) ) {
			return;
		}

		$query->set( 'wpa_files_filter', $mode );

		// Same rule as wpatt_is_display_enabled(): only '1' hides the list.
		if ( 'hidden' === $mode || 'shown' === $mode ) {
			$meta_query   = (array) $query->get( 'meta_query' );
			$meta_query[] = ( 'hidden' === $mode )
				? array( 'key' => 'wpa_off', 'value' => '1' )
				: array(
					'relation' => 'OR',
					array( 'key' => 'wpa_off', 'compare' => 'NOT EXISTS' ),
					array( 'key' => 'wpa_off', 'value' => '1', 'compare' => '!=' ),
				);
			$query->set( 'meta_query', $meta_query );
		}
	}
);

/**
 * "Has attachments" condition for the Files filter.
 *
 * Same rule as the column count: attachments that are not in the trash.
 * Shown / not shown only cover posts with files, matching the eye icons.
 */
add_filter(
	'posts_where',
	function ( $where, $query ) {
		$mode = $query->get( 'wpa_files_filter' );
		if ( ! $mode ) {
			return $where;
		}

		global $wpdb;

		$exists = "EXISTS (SELECT 1 FROM {$wpdb->posts} AS wpa_att"
			. " WHERE wpa_att.post_parent = {$wpdb->posts}.ID"
			. " AND wpa_att.post_type = 'attachment'"
			. " AND wpa_att.post_status != 'trash')";

		return $where . ' AND ' . ( 'without' === $mode ? 'NOT ' : '' ) . $exists;
	},
	10,
	2
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
			'.widefat th.column-wpa_files,.widefat td.column-wpa_files{width:6.5em;text-align:center;}
.wpa-files-count{font-variant-numeric:tabular-nums;}
.wpa-files-count.is-zero{color:#a7aaad;}
.wpa-files-link{display:inline-flex;align-items:center;gap:4px;text-decoration:none;font-variant-numeric:tabular-nums;}
.wpa-files-link svg{width:13px;height:13px;flex-shrink:0;opacity:.65;}
.wpa-files-link:hover svg,.wpa-files-link:focus svg{opacity:1;}
.wpa-files-state,.wpa-files-heading-off{display:inline-flex;align-items:center;gap:4px;vertical-align:middle;}
.wpa-files-state{margin-left:6px;}
.wpa-files-state.is-shown{color:#a7aaad;}
.wpa-files-state.is-hidden{color:#646970;}
.wpa-files-state svg,.wpa-files-heading-off svg{width:14px;height:14px;flex-shrink:0;}
.tablenav .wpa-files-legend{display:inline-flex;align-items:center;gap:10px;height:30px;margin:0 10px 0 2px;color:#646970;font-size:12px;vertical-align:middle;}
.wpa-files-legend>span{display:inline-flex;align-items:center;gap:3px;}
.wpa-files-legend svg{width:13px;height:13px;flex-shrink:0;}
@media screen and (max-width:782px){
.tablenav .wpa-files-legend{display:none;}
.widefat th.column-wpa_files,.widefat td.column-wpa_files{width:auto;text-align:left;}
.wpa-files-link{display:inline-flex;}
}'
		);
	}
);
