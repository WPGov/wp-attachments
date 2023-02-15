<?php
function wpa_upload_columns($columns) {

	unset($columns['parent']);
	$columns['wpattachments_parent'] = __('Parent');

	return $columns;

}
 function wpa_media_custom_columns($column_name, $id) {
	
	$post = get_post($id);
	__('Include images','wp-attachments');
	if($column_name != 'wpattachments_parent')
		return;
		$canwpa = true;
		if ( !current_user_can('edit_post', $post->ID) ) {
			$canwpa = false;
		}
		if ( $post->post_parent > 0 ) {
			if ( get_post($post->post_parent) ) {
				$title =_draft_or_post_title($post->post_parent);
			}
			?>
			<strong><a href="<?php echo get_edit_post_link( $post->post_parent ); ?>"><?php echo $title ?></a></strong><br/><?php echo get_the_date(); ?>
			
			<?php if ($canwpa) { ?>
				<hr>
				<a class="button button-small hide-if-no-js" onclick="findPosts.open('media[]','<?php echo $post->ID ?>');return false;" href="#the-list"><?php _e('Re-Attach','wp-attachments'); ?></a>
				<?php $url = admin_url('tools.php?page=unattach&noheader=true&&id=' . $post->ID); ?>
				<a class="button button-small " href="<?php echo esc_url( $url );?>"><?php echo _e('Unattach','wp-attachments'); ?></a>
			<?php
			}
		} else {
			?>
			<strong><?php _e('Parent term does not exist.'); ?></strong>
			<?php if ($canwpa) { ?>
				<hr>
				<a class="button button-primary button-small hide-if-no-js" onclick="findPosts.open('media[]','<?php echo $post->ID ?>');return false;" href="#the-list"><?php _e('Attach','wp-attachments'); ?></a>
			<?php }
		}

}
function custom_admin_css() {
	echo '<style>
	#wpattachments_parent {
  		width: 15%;
	}
   	</style>';
	add_filter("manage_upload_columns", 'wpa_upload_columns');
	add_action("manage_media_custom_column", 'wpa_media_custom_columns', 0, 2);
 }
add_action('admin_head', 'custom_admin_css');



//action to set post_parent to 0 on attachment
function unattach_do_it() {
	global $wpdb;
	
	if (!empty($_REQUEST['id'])) {
		$wpdb->update($wpdb->posts, array('post_parent'=>0), array('id'=>$_REQUEST['id'], 'post_type'=>'attachment'));
	}
	
	wp_redirect( 'upload.php?mode=list' );
	exit;
}

//set it up
add_action( 'admin_menu', 'unattach_init' );
function unattach_init() {
	if ( current_user_can( 'upload_files' ) ) {
		//this is hacky but couldn't find the right hook
		add_submenu_page('tools.php', 'Unattach Media', 'Unattach', 'upload_files', 'unattach', 'unattach_do_it');
		remove_submenu_page('tools.php', 'unattach');
	}
}
?>