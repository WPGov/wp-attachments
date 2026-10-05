<?php
/**
 * File families and their icons.
 *
 * Shared by the metabox, the frontend list and the settings preview, so the
 * same file looks the same everywhere.
 *
 * @package WP_Attachments
 */

/**
 * Reduce a MIME type to one of the icon families.
 *
 * @param string $mime_type MIME type.
 * @return string One of pdf, doc, sheet, slides, archive, audio, video, image, code, text, default.
 */
function wpatt_get_file_type( $mime_type ) {
	$mime_type = strtolower( (string) $mime_type );

	// Checked in order: the first prefix that matches wins, so the more
	// specific entries (text/csv) come before the broad ones (text/).
	$prefixes = array(
		'image/'    => 'image',
		'video/'    => 'video',
		'audio/'    => 'audio',
		'text/csv'  => 'sheet',
		'text/html' => 'code',
		'text/'     => 'text',
	);
	foreach ( $prefixes as $prefix => $type ) {
		if ( strpos( $mime_type, $prefix ) === 0 ) {
			return $type;
		}
	}

	$needles = array(
		'pdf'               => 'pdf',
		'wordprocessing'    => 'doc',
		'msword'            => 'doc',
		'opendocument.text' => 'doc',
		'rtf'               => 'doc',
		'spreadsheet'       => 'sheet',
		'ms-excel'          => 'sheet',
		'presentation'      => 'slides',
		'ms-powerpoint'     => 'slides',
		'zip'               => 'archive',
		'compressed'        => 'archive',
		'tar'               => 'archive',
		'gzip'              => 'archive',
		'json'              => 'code',
		'xml'               => 'code',
		'javascript'        => 'code',
	);
	foreach ( $needles as $needle => $type ) {
		if ( strpos( $mime_type, $needle ) !== false ) {
			return $type;
		}
	}

	return 'default';
}

/**
 * Inline SVG icon for a file family.
 *
 * Inline rather than a font, sprite or CSS mask: crisp at any pixel density,
 * coloured by the surrounding text through currentColor, no extra request,
 * and nothing a theme has to support.
 *
 * Every family has its own shape, so the icons still tell the types apart
 * when they are shown in a single colour.
 *
 * @param string $type  File family, from wpatt_get_file_type().
 * @param string $class CSS class for the <svg>.
 * @return string
 */
function wpatt_get_file_icon_svg( $type, $class = 'wpa-file-icon' ) {
	// Sheet of paper with a folded corner, shared by the document families.
	$page = '<path d="M14 2.75H7A2.25 2.25 0 0 0 4.75 5v14A2.25 2.25 0 0 0 7 21.25h10A2.25 2.25 0 0 0 19.25 19V8L14 2.75Z" fill="currentColor" fill-opacity=".13"/>'
		. '<path d="M14 2.75H7A2.25 2.25 0 0 0 4.75 5v14A2.25 2.25 0 0 0 7 21.25h10A2.25 2.25 0 0 0 19.25 19V8L14 2.75Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>'
		. '<path d="M13.75 3v4.25H18" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>';

	// Rounded frame shared by the media families.
	$frame = '<rect x="3.75" y="4.75" width="16.5" height="14.5" rx="2.25" fill="currentColor" fill-opacity=".13"/>'
		. '<rect x="3.75" y="4.75" width="16.5" height="14.5" rx="2.25" stroke="currentColor" stroke-width="1.5"/>';

	$glyphs = array(
		// Solid label band: tells a PDF from a text document without colour.
		'pdf'     => $page
			. '<rect x="6.75" y="12.5" width="10.5" height="5.5" rx="1" fill="currentColor"/>',

		'doc'     => $page
			. '<path d="M8 12.5h8M8 15.5h8M8 18.5h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',

		'text'    => $page
			. '<path d="M8 13.5h8M8 16.5h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',

		'sheet'   => $page
			. '<rect x="7.25" y="12.25" width="9.5" height="6.5" rx="1" stroke="currentColor" stroke-width="1.5"/>'
			. '<path d="M12 12.25v6.5M7.25 15.5h9.5" stroke="currentColor" stroke-width="1.5"/>',

		'slides'  => $page
			. '<rect x="7.25" y="12.25" width="9.5" height="6.5" rx="1" stroke="currentColor" stroke-width="1.5"/>'
			. '<path d="M9.5 16.5l2-2 1.75 1.75 1.25-1.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',

		'code'    => $page
			. '<path d="M10.25 12.75 7.75 15.5l2.5 2.75M13.75 12.75l2.5 2.75-2.5 2.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',

		'image'   => $frame
			. '<circle cx="8.75" cy="10" r="1.5" fill="currentColor"/>'
			. '<path d="M4.75 17.5 9.5 12.75l3.25 3.25 2.25-1.75 4.25 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',

		'video'   => $frame
			. '<path d="M10.25 9.25v5.5l5-2.75-5-2.75Z" fill="currentColor"/>',

		'audio'   => '<path d="M9.5 16.5V6.75l8.75-1.75V14.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
			. '<ellipse cx="7.25" cy="16.75" rx="2.75" ry="2.25" fill="currentColor"/>'
			. '<ellipse cx="16" cy="14.75" rx="2.75" ry="2.25" fill="currentColor"/>',

		'archive' => '<path d="M4.75 7.75h14.5V19A2.25 2.25 0 0 1 17 21.25H7A2.25 2.25 0 0 1 4.75 19V7.75Z" fill="currentColor" fill-opacity=".13"/>'
			. '<rect x="3.75" y="3.75" width="16.5" height="4" rx="1.25" stroke="currentColor" stroke-width="1.5"/>'
			. '<path d="M5 7.75V19A2.25 2.25 0 0 0 7.25 21.25h9.5A2.25 2.25 0 0 0 19 19V7.75" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>'
			. '<path d="M10.25 11.5h3.5M10.25 14.5h3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',

		'default' => $page,
	);

	$glyph = isset( $glyphs[ $type ] ) ? $glyphs[ $type ] : $glyphs['default'];

	// width/height attributes keep the icon small even where a theme's CSS
	// does not reach it (or resets svg sizing).
	return '<svg class="' . esc_attr( $class ) . '" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">'
		. $glyph . '</svg>';
}

/**
 * File extension of an attachment, upper case, from its URL.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function wpatt_get_file_extension( $attachment_id ) {
	$url  = wp_get_attachment_url( $attachment_id );
	// parse_url() first: a query string would otherwise end up in the extension.
	$path = $url ? wp_parse_url( $url, PHP_URL_PATH ) : '';

	return $path ? strtoupper( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';
}
