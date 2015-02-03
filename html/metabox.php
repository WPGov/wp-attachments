	<?php if ($attachments->have_posts()): ?>
	<div id="ij-post-attachments" class="ij-post-attachment-list">
		<ul class="alignleft">
			<?php while ($attachments->have_posts()): $atchment = $attachments->next_post(); ?>
			<li class="ij-post-attachment"
			    data-mimetype="<?php echo $atchment->post_mime_type; ?>"
			    data-alt="<?php echo esc_attr(get_post_meta(611, '_wp_attachment_image_alt', true)); ?>"
			    data-attachmentid="<?php echo $atchment->ID; ?>"
			    data-url="<?php echo wp_get_attachment_url($atchment->ID); ?>"
			    data-title="<?php echo esc_attr($atchment->post_title); ?>">
				<!--Item title-->
				<div class="ij-post-attachment-title">
					<div style="float:left;">
						<?php echo wp_get_attachment_image($atchment->ID, array(80, 60), true); ?>
					</div>
					<div style="float:right;">
					<?php
					$wpatt_option_includeimages_get = get_option('wpatt_option_includeimages');
					if (!($wpatt_option_includeimages_get == '1' && wp_attachment_is_image($atchment->ID))) {
						echo '<img title="This file will be listed in the front-end" src="' . plugin_dir_url(__FILE__) . 'eye.png"/>';
					}
					?>
						<a href="#insert-media-button" class="button button-small"><?php _e('Insert into post'); ?></a>
						<a href="<?php echo wp_get_attachment_url($atchment->ID); ?>" class="button button-small"><?php _e('Edit'); ?></a>					
						<?php $url = admin_url('tools.php?page=unattach&noheader=true&&id=' . $atchment->ID); ?>
						<a class="button button-small " href="<?php echo esc_url( $url );?>" onclick = "if (! confirm('<?php _e('Are you sure you want to do this?');?>')) { return false; }"><?php echo _e('Unattach','wp-attachments'); ?></a>
						<a href="<?php echo get_delete_post_link($atchment->ID); ?>" class="button button-small" onclick = "if (! confirm('<?php _e('Are you sure you want to do this?');?>')) { return false; }"><?php _e('Remove'); ?></a>
					</div>
					<h3>
					<?php echo (strlen($atchment->post_title) > 22) ? (substr(esc_html($atchment->post_title), 0, 22) . '...') : esc_html($atchment->post_title); ?></h3>
				</div>

				<!--Item body-->
				<div style="padding:1px 5px 5px">
					<div class="ij-post-attachment-type">
						<?php
							echo '<div style="float:right;"><b>';
							if ((file_exists(get_attached_file($atchment->ID)))) {
								$wpatt_fs = wpatt_format_bytes(filesize(get_attached_file($atchment->ID)));
							} else {
								$wpatt_fs = 'not found';
							}
							echo $wpatt_fs;
							echo '</b></div>';
							$strr = array("image/", "application/");
							echo strtoupper(str_replace($strr, '', get_post_mime_type($atchment->ID)));
						?>
					</div>
					
				</div>
			</li>
			<?php endwhile; ?>
		</ul>
		<div class="clear"></div>
	</div>
	<?php else: ?>
	<p><?php _e('No media attachments found.'); ?></p>
	<?php endif; ?>
	<div id="wp-content-media-buttons" style="float:none;" class="wp-media-buttons">
		<center>
			<a href="#" id="insert-media-button" class="button insert-media add_media" data-editor="content"><span class="wp-media-buttons-icon"></span> <?php _e('Add Media'); ?></a>
		</center>
		<div class="clear"></div>
	</div>