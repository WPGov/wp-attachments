<?php
/*
 BASED ON IJ POST ATTACHMENTS BY GUSTAVO HENKE - http://www.injoin.com.br
*/

//<editor-fold desc="Constants">
define('IJ_POST_ATTACHMENTS_DIR', dirname(__FILE__));
define('IJ_POST_ATTACHMENTS_URL', plugin_dir_url(__FILE__));
define('IJ_POST_ATTACHMENTS_VER', '0.1.0');
//</editor-fold>

class IJ_Post_Attachments
{

	//<editor-fold desc="Properties">
	/**
	 * List of methods that are WP actions.
	 *
	 * @since   0.0.1a
	 * @var     array
	 */
	private $actions = array(
		'add_meta_boxes', 'admin_enqueue_scripts',
		'wp_ajax_ij_realign', 'wp_ajax_ij_attachment_edit'
	);

	/**
	 * The singleton instance of this class
	 *
	 * @since   0.0.1a
	 * @var     IJ_Post_Attachments
	 */
	private static $instance;
	//</editor-fold>

	//<editor-fold desc="Basic methods">
	/**
	 * Constructor
	 *
	 * @since   0.0.1a
	 * @return  IJ_Post_Attachments
	 */
	private function __construct()
	{
		foreach ($this->actions as $action)
			add_action($action, array($this, $action));
	}

	/**
	 * Singleton access for the class
	 *
	 * @static
	 * @since   0.0.1a
	 * @return  IJ_Post_Attachments
	 */
	static public function getInstance()
	{
		if (!isset(self::$instance))
			self::$instance = new IJ_Post_Attachments();

		return self::$instance;
	}

	/**
	 * Disallows cloning this class
	 *
	 * @since   0.0.1a
	 * @throws  Exception
	 * @return  void
	 */
	public function __clone()
	{
		throw new Exception("Clone is disallowed.");
	}
	//</editor-fold>

	//<editor-fold desc="Metabox">
	/**
	 * Add the plugin meta box
	 *
	 * @since   0.0.1a
	 * @return  void
	 */
	public function add_meta_boxes()
	{
		$pid = isset($_GET['post']) ? $_GET['post'] : 0;
		global $pagenow;
		if (!(in_array( $pagenow, array( 'post-new.php' ) )) && ($pid == '0' || $pid == NULL)) {
			return; //Additional check to avoid showing the metabox in not-intended pages (ex. Members Plugin)
		}

		add_meta_box( 'wpa-attachments', __('Media'), array($this, 'printMetaBox'), null, 'normal', 'high' );
	}

	/**
	 * Enqueue the JS files needed by the plugin
	 *
	 * @since	0.0.1a
	 * @return	void
	 */
	public function admin_enqueue_scripts()
	{
		global $hook_suffix;
		if ($hook_suffix != 'post.php')
			return;

		wp_enqueue_media();

		wp_enqueue_script(
			'ij-post-attachments', IJ_POST_ATTACHMENTS_URL . 'scripts/ij-post-attachments.js',
			array('jquery-ui-sortable'), IJ_POST_ATTACHMENTS_VER
		);

		wp_localize_script('ij-post-attachments', 'IJ_Post_Attachments_Vars', array(
			'editMedia' => __('Edit Media'),
            'youSure' => __('Are you sure you want to do this?'),
			'postID'    => isset($_GET['post']) ? $_GET['post'] : 0
		));
	}

	/**
	 * Create the meta box below the post editor and list the files
	 *
	 * @since   0.0.1a
	 * @param   object $post
	 * @return  void
	 */
	public function printMetaBox($post)
	{
		$attachments = new WP_Query(array(
			'post_parent'   => $post->ID,
			'post_type'     => 'attachment',
			'post_status'   => 'any',
			'orderby'       => 'menu_order',
			'order'         => 'ASC',
            'posts_per_page' => -1
		));
		
		
		if ($attachments->have_posts()): ?>

			<div id="ij-post-attachments" class="ij-post-attachment-list">
				<ul>
					<?php while ($attachments->have_posts()): $atchment = $attachments->next_post(); ?>
					<li class="ij-post-attachment"
						data-mimetype="<?php echo $atchment->post_mime_type; ?>"
						data-alt="<?php echo esc_attr(get_post_meta(611, '_wp_attachment_image_alt', true)); ?>"
						data-attachmentid="<?php echo $atchment->ID; ?>"
						data-url="<?php echo wp_get_attachment_url($atchment->ID); ?>"
						data-title="<?php echo esc_attr($atchment->post_title); ?>">
				   
						<table class="widefat" style="padding: 0 0 17px 0;">
							<td width="50%" style="text-align: left;">
								<li style="height: 20px;" class="post-attachment mime-<?php echo sanitize_title($atchment->post_mime_type); ?>">
								<a target="_blank" href="<?php echo wp_get_attachment_url($atchment->ID); ?>">
									<?php echo esc_html($atchment->post_title); ?>
								</a>
									<?php
										$wpatt_date = new DateTime($atchment->post_date);
										echo '<br><small>'.$wpatt_date->format(get_option('wpatt_option_date_localization')).' - '.pathinfo(wp_get_attachment_url($atchment->ID), PATHINFO_EXTENSION).'</small>';
									?>
								</li>
							</td>
							<td width="10%">
								<?php if (get_option('wpatt_counter')) { ?>
								<span style="display: initial;" class="dashicons dashicons-download"></span> <?php echo wpa_get_downloads($atchment->ID); ?>
								<?php } ?>
							</td>
							<td width="10%">
							<?php
								if ((file_exists(get_attached_file($atchment->ID)))) {
									echo wpatt_format_bytes(filesize(get_attached_file($atchment->ID)));
								} else {
									echo 'not found';
								}
								?>
							</td>
							<td width="30%" style="text-align: right;vertical-align: middle;">
								<span>
									<a href="<?php echo wp_get_attachment_url($atchment->ID); ?>" class="button button-small ij-post-attachment-edit" title="<?php _e('Edit'); ?>">
										<span class="dashicons dashicons-edit"></span>
									</a>
									<a class="button button-small " href="<?php echo esc_url( admin_url('tools.php?page=unattach&noheader=true&id=' . $atchment->ID) );?>"
									onclick = "if (! confirm('<?php _e('Are you sure you want to do this?');?>')) { return false; }" title="<?php echo _e('Unattach','wp-attachments'); ?>">
										<span class="dashicons dashicons-editor-unlink"></span>
									</a>
									<a href="<?php echo get_delete_post_link($atchment->ID);?>" class="button button-small ij-post-attachment-delete" title="<?php _e('Delete'); ?>">
										<span class="dashicons dashicons-trash"></span>
									</a>
								</span>
							</td>
						</table>
					</li>
					<?php endwhile; ?>
				</ul>
				<div class="clear"></div>
			</div>
			<?php else: ?>
			<p><?php _e('No media attachments found.'); ?></p>
			<?php endif; ?>
		
		
			<div id="wp-content-media-buttons" style=" float: none;height: 50px;text-align:center;" class="wp-media-buttons">
				<div style="float: right; margin: 15px 17px 0 0; position: relative;">
					<input type="checkbox" id="wpa_off_n" name="wpa_off" <?php if ( get_post_meta($post->ID, 'wpa_off', true) ) { echo 'checked="checked"'; } ?> />
					<label for="wpa_off_n"><?php _e('Deactivate'); ?></label>
				</div>
				<a style="margin-top: 10px;" class="button wpa_attach_file add_media" title="<?php _e('Add Media'); ?>">
					<span class="wp-media-buttons-icon"></span> <?php _e('Add Media'); ?>
				</a>
				<button style="margin-top: 10px;" name="save" type="submit" class="button button-primary" id="publish" accesskey="p" title="<?php _e('Update'); ?>">
					<?php _e('Update'); ?>
				</button> 
			</div>

		<script>
			jQuery(document).ready(function() {
		
				jQuery('.wpa_attach_file').on('click', function(e) {
					e.preventDefault();
		
					var frame = wp.media({
						title : '<?php _e('Add Media'); ?>',
						frame: 'select',
						multiple : false,
						library : {
							uploadedTo : <?php echo get_the_id(); ?>
						},
						button: {
							text: 'Close'
						},
					});
		
					wp.media.model.settings.post.id = <?php echo get_the_id(); ?>;
		
					frame.open();
				});
			});
		</script>
		<?php
	}
	//</editor-fold>

	//<editor-fold desc="Attachment Edition Screen">
	/**
	 * Output the script/link tags needed by the edit iframe
	 *
	 * @since   0.0.1a
	 * @return  void
	 */
	public function attachmentEditHeadIframe()
	{
		global $wp_scripts;
		wp_default_scripts($wp_scripts);

		// I don't know if all these scripts are really needed by the media edit screen.
		// They're just there, so they'll be here too :P
		include IJ_POST_ATTACHMENTS_DIR . '/html/attachmentEditHead.php';

		// Add the needed vars to set the thumbnail :)
		$wp_scripts->localize('set-post-thumbnail', 'post_id', $_GET['post_id']);
		$wp_scripts->print_extra_script('set-post-thumbnail');
	}

	/**
	 * Echoes the content of the edit iframe
	 *
	 * @since   0.0.1a
	 * @return  void
	 */
	public function attachmentEditIframe()
	{
		$url    = admin_url('media.php');
		$id     = $_REQUEST['attachment_id'];
		include IJ_POST_ATTACHMENTS_DIR . '/html/attachmentEditIframe.php';
	}

	/**
	 * Initialize the attachment edit pop-up.
	 *
	 * @since   0.0.1a
	 * @return  void
	 */
	public function wp_ajax_ij_attachment_edit()
	{
		add_action('admin_head-media-upload-popup', array($this, 'attachmentEditHeadIframe'));
		wp_iframe(array($this, 'attachmentEditIframe'));

		// Without the line below, the WP AJAX caller (admin-ajax.php) would print an '0'
		// at the end of the request.
		die;
	}
	//</editor-fold>

	//<editor-fold desc="Attachment sorting">
	/**
	 * Re-align attachments.
	 *
	 * @since   0.0.1a
	 * @return  void
	 */
	public function wp_ajax_ij_realign()
	{
		header('Content-Type: text/plain');

		$alignment = $_REQUEST['alignment'];
		if (!is_array($alignment))
			$alignment = array_map('trim', explode(',', $alignment));

		$alignment = array_values($alignment);
		$count = count($alignment);

		for ($i = 0; $i < $count; $i++) {
			if (!is_numeric($alignment[$i]))
				continue;

			$attachment = get_post($alignment[$i]);
			$attachment->menu_order = $i;
			wp_update_post($attachment);
		}
	}
	//</editor-fold>

}

$IJ_Post_Attachments = IJ_Post_Attachments::getInstance();

add_action( 'save_post', function( $post_id ) {
    
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    if ( empty( $post_id ) ) {
        return;
    }
    

	update_post_meta($post_id, "wpa_off", isset($_POST["wpa_off"]));
});
?>
