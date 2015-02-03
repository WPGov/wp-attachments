<?php

	function wpatt_plugin_options()
    {
    
    if (!current_user_can('manage_options'))
        {
        
        wp_die(__('You do not have sufficient permissions to access this page.'));
        
        }
    
    
    
    if (isset($_POST['Submit'])) {
        
        $wpatt_option_localization_get = $_POST["wpatt_option_localization_n"];
        
        update_option('wpatt_option_localization', $wpatt_option_localization_get);
        
        if (isset($_POST['wpatt_option_showdate_n'])) {
			update_option('wpatt_option_showdate', '1');
		} else {
			update_option('wpatt_option_showdate', '0');
		}
       
	    if (isset($_POST['wpatt_option_includeimages_n'])) {
            update_option('wpatt_option_includeimages', '1');
		} else {
			update_option('wpatt_option_includeimages', '0');
		}
		
		if (isset($_POST['wpatt_option_targetblank_n'])) {
            update_option('wpatt_option_targetblank', '1');
		} else {
			update_option('wpatt_option_targetblank', '0');
		}
	
	}
    
    
    
    echo '<div class="wrap">';
    
    screen_icon();
	
	echo '<div style="float:right;">
		<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
		<input type="hidden" name="cmd" value="_s-xclick">
		<input type="hidden" name="hosted_button_id" value="F2JK36SCXKTE2">
		<input type="image" src="https://www.paypalobjects.com/it_IT/IT/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - Il metodo rapido, affidabile e innovativo per pagare e farsi pagare.">
		<img alt="" border="0" src="https://www.paypalobjects.com/it_IT/i/scr/pixel.gif" width="1" height="1">
		</form>
		</div>';
    
    echo '<h2>WP Attachments ' . get_option('wpa_version_number') . ' <a href="http://wordpress.org/support/view/plugin-reviews/wp-attachments" target="_blank" class="add-new-h2"><em>Rate this plugin!</em></a><a href="http://wordpress.org/plugins/wp-attachments/changelog/" target="_blank" class="add-new-h2"><em>Changelog</em></a></h2>';
	
	echo '<h3>';
	_e('Options','wp-attachments');
	echo '</h3>';
    
    echo '<div id="welcome-panel" class="welcome-panel">';
    
    echo '<form method="post" name="options" target="_self">';
    
    settings_fields('wpatt_option_group');
    
    echo '

	<table class="form-table">

	

        <tr valign="top">

        <th scope="row">' . __('List Title','wp-attachments') . '</th>

        <td><input type="text" name="wpatt_option_localization_n" value="';
    
    echo get_option('wpatt_option_localization');
    
    echo '" />&nbsp;' . __('Insert here the title you want for the attachments list','wp-attachments') . '</td></tr>';
    
        echo '<tr><th scope="row">' . __('Include images','wp-attachments') . '</th>
        <td><input type="checkbox" name="wpatt_option_includeimages_n" ';
    $wpatt_option_includeimages_get = get_option('wpatt_option_includeimages');
    if ($wpatt_option_includeimages_get == '1') {
		echo 'checked=\'checked\'';
	}
    echo '/>&nbsp;' . __('Check this option if you want to include images (.jpg, .jpeg, .gif, .png) in the attachments list','wp-attachments') . '</td>';
    echo '</tr>';

    echo '<tr><th scope="row">' . __('Show date','wp-attachments') . '</th>
        <td><input type="checkbox" name="wpatt_option_showdate_n" ';
    $wpatt_option_showdate_get = get_option('wpatt_option_showdate');
    if ($wpatt_option_showdate_get == '1') {
		echo 'checked=\'checked\'';
	}
    echo '/>&nbsp;' . __('Check this if you want to show the date of file upload','wp-attachments') . '</td>';
    echo '</tr>';
	
	echo '<tr><th scope="row">' . __('Open in new tab','wp-attachments') . '</th>
        <td><input type="checkbox" name="wpatt_option_targetblank_n" ';
    $wpatt_option_targetblank_get = get_option('wpatt_option_targetblank');
    if ($wpatt_option_targetblank_get == '1') {
		echo 'checked=\'checked\'';
	}
    echo '/>&nbsp;' . __('Check this option if you want to add target="_blank" to every file listed in order to open it in a new tab','wp-attachments') . '</td>';
    echo '</tr></table>';
    
    
    
    echo '</table><p class="submit"><input type="submit" class="button-primary" name="Submit" value="' . __('Save') . '" /></p>';
    
    echo '</form></div>
	<h3>' . __('Help, Support & Feedback','wp-attachments') . '</h3>
	<a href="http://wordpress.org/plugins/wp-attachments/" title "WP Attachments Wordpress Plugin>http://wordpress.org/plugins/wp-attachments/</a><br/>' . __('This plugin is mantained by <a href=\"http://marcomilesi.ml\" title=\"Marco Milesi\">Marco Milesi</a> and is based on simplicity and intuitiveness. Keep it updated :)<br/>Thank You for using WP Attachments!','wp-attachments') . '</div>';
    
    }
?>