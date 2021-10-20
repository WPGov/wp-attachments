<?php

	function wpatt_plugin_options()
    {
    
    if (!current_user_can('manage_options')) { wp_die(__('You do not have sufficient permissions to access this page.')); }
    
    if (isset($_POST['submit-general'])) {
        
        update_option('wpatt_option_localization', $_POST["wpatt_option_localization_n"]);
        update_option('wpatt_option_date_localization', $_POST["wpatt_option_date_localization_n"]);
        
        if (isset($_POST['wpatt_show_orderby_n'])) {
			update_option('wpatt_show_orderby', '1');
		} else {
			update_option('wpatt_show_orderby', '0');
		}
        
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
        
        if (isset($_POST['wpatt_option_restrictload_n'])) {
            update_option('wpatt_option_restrictload', '1');
		} else {
			update_option('wpatt_option_restrictload', '0');
		}
        
        if (isset($_POST['wpatt_counter_n'])) {
            update_option('wpatt_counter', '1');
		} else {
			update_option('wpatt_counter', '0');
		}
        if (isset($_POST['wpatt_excludelogged_counter_n'])) {
            update_option('wpatt_excludelogged_counter', '1');
		} else {
			update_option('wpatt_excludelogged_counter', '0');
		}
	
	}
    if (isset($_POST['submit-appearance'])) {
        update_option('wpa_ict', $_POST['style']);
        update_option('wpa_template', $_POST['template']);
        update_option('wpa_template_custom', stripslashes($_POST['wpa_template_custom']));
    }
        wpa_register_initial_settings();
    
    
    
    echo '<div class="wrap">
        <form style="float:right;" action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
		<input type="hidden" name="cmd" value="_s-xclick">
		<input type="hidden" name="hosted_button_id" value="F2JK36SCXKTE2">
		<input type="image" src="https://www.paypalobjects.com/webstatic/en_US/btn/btn_donate_pp_142x27.png" border="0" name="submit" alt="PayPal - Il metodo rapido, affidabile e innovativo per pagare e farsi pagare.">
		</form>
        ';
    
    echo '<h2><strong style="font-size: 1.2em;">WP Attachments</strong><small> ' . get_option('wpa_version_number') . '</small></h2><br><br>';
    
    echo '<form method="post" name="options" target="_self">';
    
    settings_fields('wpatt_option_group');
        
    if ( isset ( $_GET['tab'] ) ) { $current = $_GET['tab'];  } else { $current = 'general'; }

    $tabs = array(
        'general' => __('Settings'),
        'appearance' => __('Appearance')
    );
    echo '<h2 class="nav-tab-wrapper">';
    echo '<div style="float:right;">
        <a href="http://wordpress.org/support/view/plugin-reviews/wp-attachments" target="_blank" class="add-new-h2">Rate this plugin</a>
        <a href="http://wordpress.org/plugins/wp-attachments/changelog/" target="_blank" class="add-new-h2">Changelog</a>
    </div>';
    foreach( $tabs as $tab => $name ){
        $class = ( $tab == $current ) ? ' nav-tab-active' : '';
        echo "<a class='nav-tab$class' href='?page=wpatt-option-page&tab=$tab'>$name</a>";

    }
    echo '</h2>';

        if ( isset ( $_GET['tab'] ) ) { $tab = $_GET['tab']; } else { $tab = 'general'; }

   echo '<table class="form-table">';
   switch ( $tab ){
      case 'general' :

       echo '<table class="form-table">
    
        <tr valign="top">

        <th scope="row">' . __('List Head','wp-attachments') . '</th>

        <td><input type="text" name="wpatt_option_localization_n" value="';
    
    echo get_option('wpatt_option_localization');
    
    echo '" />&nbsp;' . __('Attachments list title','wp-attachments') . '<br>
    <input type="checkbox" name="wpatt_show_orderby_n" ';
    $wpatt_show_orderby_get = get_option('wpatt_show_orderby');
    if ($wpatt_show_orderby_get == '1') {
		echo 'checked=\'checked\'';
	}
    echo '/>&nbsp;' . __('Show orderby dropdown (date, title)','wp-attachments').' </td></tr>
       
    <tr valign="top">
        <th scope="row">' . __('Date Format') . '</th>
        <td><input type="text" name="wpatt_option_date_localization_n" value="'.get_option('wpatt_option_date_localization').'" />&nbsp;'
        .__('The format for dates','wp-attachments') . '<small> ('.strtolower(__('Default')).': <code>d.m.Y</code>)</small></td></tr>';
    
        echo '<tr><th scope="row">' . __('Include images','wp-attachments') . '</th>
        <td><input type="checkbox" name="wpatt_option_includeimages_n" ';
    $wpatt_option_includeimages_get = get_option('wpatt_option_includeimages');
    if ($wpatt_option_includeimages_get == '1') {
		echo 'checked=\'checked\'';
	}
    echo '/>&nbsp;' . __('Check this option if you want to include images (.jpg, .jpeg, .gif, .png) in the attachments list','wp-attachments') . '</td>';
    echo '</tr>';
	
	echo '<tr><th scope="row">' . __('Open in new tab','wp-attachments') . '</th>
        <td><input type="checkbox" name="wpatt_option_targetblank_n" ';
    $wpatt_option_targetblank_get = get_option('wpatt_option_targetblank');
    if ($wpatt_option_targetblank_get == '1') {
		echo 'checked=\'checked\'';
	}
    echo '/>&nbsp;' . __('Check this option if you want to add target="_blank" to every file listed in order to open it in a new tab','wp-attachments') . '</td>';
    echo '</tr>';
        
    echo '<tr><th scope="row">' . __('Restrict loading','wp-attachments') . '</th>
        <td><input type="checkbox" name="wpatt_option_restrictload_n" ';
    $wpatt_option_targetblank_get = get_option('wpatt_option_restrictload');
    if ($wpatt_option_targetblank_get == '1') {
		echo 'checked=\'checked\'';
	}
    echo '/>&nbsp;' . __('Check this option if you want to restrict the plugin to single or page views (not archive or other views)','wp-attachments') . '</td>';
    echo '</tr>';
       
    echo '<tr><th scope="row">' . __('Download counter','wp-attachments') . '</th>
        <td><input type="checkbox" name="wpatt_counter_n" ';
    $wpatt_counter_get = get_option('wpatt_counter');
    if ($wpatt_counter_get == '1') {
		echo 'checked=\'checked\'';
	}
    echo '/>&nbsp;' . __('Check this option if you want to enable download counter','wp-attachments') .
        
        
    '<br><input type="checkbox" name="wpatt_excludelogged_counter_n" ';
    $wpatt_counter_get = get_option('wpatt_excludelogged_counter');
    if ($wpatt_counter_get == '1') {
		echo 'checked=\'checked\'';
	}
    echo '/>&nbsp;' . __('Check this option to exclude logged-in users from the counter','wp-attachments') . '</td>';
    echo '</tr>';
       
    echo '</table>
    <p class="submit"><input type="submit" class="button-primary" name="submit-general" value="'.__('Save Changes').'" /></p>';
       
      break;
      case 'appearance' :
?>       

<table class="form-table">
<tbody>
<tr valign="top">
    <th scope="row">Icon pack</th>
    <td>
    
    <fieldset>
        <label>
            <input type="radio" value="0" name="style" <?php if(!get_option('wpa_ict')>0){echo'checked=""';}?></inpu> <strong>Fugue Icons (default)</strong>
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/0/document.png'; ?>"/>
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/0/document-word.png'; ?>"/>
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/0/document-pdf.png'; ?>"/>
            <br><small><?php echo __('Author'); ?>: Yusuke Kamiyamane</small>
        </label><br>
        
        <label>
            <input type="radio" value="1" name="style" <?php if(get_option('wpa_ict')==1){echo'checked=""';}?>> <strong>Small & Flat</strong> 
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/1/document.png'; ?>"/>
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/1/document-word.png'; ?>"/>
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/1/document-pdf.png'; ?>"/>
            <br><small><?php echo __('Author'); ?>: Paomedia</small>
        </label>
        <br>
        <label>
            <input type="radio" value="2" name="style" <?php if(get_option('wpa_ict')==2){echo'checked=""';}?>> <strong>Font Awesome</strong> 
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/2/document.png'; ?>"/>
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/2/document-word.png'; ?>"/>
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/2/document-pdf.png'; ?>"/>
            <br><small><?php echo __('Author'); ?>: Dave Gandy</small>
        </label>
        <br>
         <label>
            <input type="radio" value="3" name="style" <?php if(get_option('wpa_ict')==3){echo'checked=""';}?>> <strong>White</strong>
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/3/document.png'; ?>"/>
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/3/document-word.png'; ?>"/>
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/3/document-pdf.png'; ?>"/>
            <br><small><?php echo __('Author'); ?>: FileTypeIcons.com</small>
        </label>
        <br>
         <label>
            <input type="radio" value="4" name="style" <?php if(get_option('wpa_ict')==4){echo'checked=""';}?>> <strong>Matrilineare</strong>
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/4/document.png'; ?>"/>
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/4/document-word.png'; ?>"/>
            <img src="<?php echo plugins_url() . '/wp-attachments/styles/4/document-pdf.png'; ?>"/>
            <br><small><?php echo __('Author'); ?>: Andrea Soragna</small>
        </label>
    </fieldset> 
    
    </td></tr>
    
    <tr valign="top">
        <th scope="row">
            <label>Template</label>
        </th>
        <td>
            <fieldset>
        <label>
            <input type="radio" value="0" name="template" <?php if(!get_option('wpa_template')>0){echo'checked=""';}?></inpu> <strong><?php echo __('Default'); ?></strong>
            <br><code>&lt;a href="%URL%">%TITLE%&lt;/a&gt; &lt;small&gt;(%SIZE%)&lt;/small&gt;</code>
        </label><br>
        
        <label>
            <input type="radio" value="1" name="template" <?php if(get_option('wpa_template')==1){echo'checked=""';}?>> <strong>Default with date</strong> 
            <br><code>&lt;a href="%URL%">%TITLE%&lt;/a&gt; &lt;small&gt;(%SIZE%)&lt;/small&gt; &lt;div style="float:right;"&gt;%DATE%&lt;/div&gt;</code>
        </label>
        <br>
        <label>
            <input type="radio" value="2" name="template" <?php if(get_option('wpa_template')==2){echo'checked=""';}?>> <strong>Extended</strong> 
            <br><code>&lt;a href="%URL%">%TITLE%&lt;/a> &lt;small>&bull; %SIZE% &bull; %DOWNLOADS% click&lt;/small> &lt;div style="float:right;">%DATE%&lt;/div><br>&lt;br>&lt;small>%CAPTION%&lt;/small></code>
        </label>
        <br>
         <label>
            <input type="radio" value="3" name="template" <?php if(get_option('wpa_template')==3){echo'checked=""';}?>> <strong><?php echo  __('Customize'); ?></strong>
        </label>
    </fieldset>
            <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
            <script type="text/javascript">
            $(document).ready(function() {
                $('input[type=radio][name=template]').change(function() {
                    if (this.value == "3") {
                        $('#wpa_template_custom').show();
                    } else {
                        $('#wpa_template_custom').hide();
                    }
                });
            });
            </script>
            <textarea id="wpa_template_custom" name="wpa_template_custom" class="widefat" cols="50" rows="5" 
                <?php if(get_option('wpa_template')!=3){echo'style="display: none;" ';}?>/>
                <?php echo html_entity_decode(get_option('wpa_template_custom')); ?></textarea>
            <p class="description">
    <h4>You can use HTML and the following placeholders:</h4>
        <style>
        .wpa-table td {
        padding: 5px;
        }
        </style>
        <table class="widefat wpa-table">
            <tbody>
                <tr>
                    <td><strong>%URL%</strong></td>
                    <td>The direct url to file</td>
                    <td><small>"mywebsite.it/***/hi.pdf"</small></td>
                </tr>
                <tr>
                    <td><strong>%TITLE%</strong></td>
                    <td>The title of the file</td>
                    <td><small>"My beautiful file"</small></td>
                </tr>
                <tr>
                    <td><strong>%CAPTION%</strong></td>
                    <td>The caption of the file</td>
                    <td><small>"My beautiful caption"</small></td>
                </tr>
                <tr>
                    <td><strong>%DESCRIPTION%</strong></td>
                    <td>The description of the file</td>
                    <td><small>"My long beautiful description"</small></td>
                </tr>
                <tr>
                    <td><strong>%SIZE%</strong></td>
                    <td>The size of the file</td>
                    <td><small>"188 kB"</small></td>
                </tr>
                <tr>
                    <td><strong>%DATE%</strong></td>
                    <td>The date of the file</td>
                    <td><small>"31.12.2015"</small></td>
                </tr>
                <tr>
                    <td><strong>%AUTHOR%</strong></td>
                    <td>The author "display_name"</td>
                    <td><small>"Marco Milesi"</small></td>
                </tr>
                <tr>
                    <td><strong>%DOWNLOADS%</strong></td>
                    <td>The number of times this file has been downloaded</td>
                    <td><small>"2"</small></td>
                </tr>
            </tbody>
        </table>
    
    </tbody>
</table>
<p class="submit"><input type="submit" class="button-primary" name="submit-appearance" value="<?php echo __('Save Changes'); ?>" /></p>
    
<?php
      break;
   }
   echo '</table>';
    
    
    
    echo '</table></form>
    <div style="float:right;">
    <iframe src="//www.facebook.com/plugins/likebox.php?href=https%3A%2F%2Fwww.facebook.com%2FWPGov&amp;width&amp;height=258&amp;colorscheme=light&amp;show_faces=true&amp;header=false&amp;stream=false&amp;show_border=false&amp;appId=262031607290004" scrolling="no" frameborder="0" style="border:none; overflow:hidden; height:258px;" allowTransparency="true"></iframe>
    </div>
    
    <div class="wrap about-wrap"><div class="about-text">
        <a href="http://wordpress.org/plugins/wp-attachments/" title "WP Attachments Wordpress Plugin>http://wordpress.org/plugins/wp-attachments/</a><br><br>
        <a style="float: left; margin: 5px; border: 1px solid #dfdfdf0;" href="http://marcomilesi.ml">
            <img src="http://www.gravatar.com/avatar/c70b8e378aa035f77ab7a3ddee83b892.jpg?s=80" class="gravatar">
        </a>
        Thank You for using this plugin!<br>
        If you like it, please leave a review or donate to keep it alive and updated<br>
        <small><a href="http://marcomilesi.ml">Developed by <strong>Marco Milesi</strong></a> &bull; <a href="http://facebook.com/WPGov">Follow us on Facebook</a></small>
    </div></div>';
    
    }
?>