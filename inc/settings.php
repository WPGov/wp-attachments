<?php

function wpatt_plugin_options()
{
    if (!current_user_can('manage_options')) { 
        wp_die(__('You do not have sufficient permissions to access this page.')); 
    }
    
    // Verify nonce for general settings submission
    if (isset($_POST['submit-general'])) {
        check_admin_referer('wpatt_general_settings');

        update_option('wpatt_option_localization', sanitize_text_field($_POST["wpatt_option_localization_n"]));
        update_option('wpatt_option_date_localization', sanitize_text_field($_POST["wpatt_option_date_localization_n"]));
        
        update_option('wpatt_show_orderby', isset($_POST['wpatt_show_orderby_n']) ? '1' : '0');
        update_option('wpatt_option_showdate', isset($_POST['wpatt_option_showdate_n']) ? '1' : '0');
        update_option('wpatt_option_includeimages', isset($_POST['wpatt_option_includeimages_n']) ? '1' : '0');
        update_option('wpatt_option_targetblank', isset($_POST['wpatt_option_targetblank_n']) ? '1' : '0');
        update_option('wpatt_option_restrictload', isset($_POST['wpatt_option_restrictload_n']) ? '1' : '0');
        update_option('wpatt_counter', isset($_POST['wpatt_counter_n']) ? '1' : '0');
        update_option('wpatt_excludelogged_counter', isset($_POST['wpatt_excludelogged_counter_n']) ? '1' : '0');
    }

    // Verify nonce for appearance settings submission
    if (isset($_POST['submit-appearance'])) {
        check_admin_referer('wpatt_appearance_settings');

        update_option('wpa_ict', sanitize_text_field($_POST['style']));
        update_option('wpa_template', sanitize_text_field($_POST['template']));
        update_option('wpa_template_custom', stripslashes(sanitize_text_field($_POST['wpa_template_custom'])));
    }
    
    wpa_register_initial_settings();
    
    echo '<div class="wrap">';

    echo '<h2><strong style="font-size: 1.2em;">WP Attachments</strong><small> ' . get_option('wpa_version_number') . '</small></h2><br><br>';
    
    echo '<form method="post" name="options" target="_self">';
    
    settings_fields('wpatt_option_group');

    if (isset($_GET['tab'])) { 
        $current = sanitize_text_field($_GET['tab']);  
    } else { 
        $current = 'general'; 
    }

    $tabs = array(
        'general' => __('Settings'),
        'appearance' => __('Appearance')
    );

    echo '<h2 class="nav-tab-wrapper">';
    echo '<div style="float:right;">
        <a href="http://wordpress.org/support/view/plugin-reviews/wp-attachments" target="_blank" class="add-new-h2">Rate this plugin</a>
        <a href="http://wordpress.org/plugins/wp-attachments/changelog/" target="_blank" class="add-new-h2">Changelog</a>
    </div>';
    foreach($tabs as $tab => $name){
        $class = ($tab == $current) ? ' nav-tab-active' : '';
        echo "<a class='nav-tab$class' href='?page=wpatt-option-page&tab=$tab'>$name</a>";
    }
    echo '</h2>';

    if (isset($_GET['tab'])) { 
        $tab = sanitize_text_field($_GET['tab']); 
    } else { 
        $tab = 'general'; 
    }

    echo '<table class="form-table">';
    switch ($tab){
        case 'general' :
            echo '<table class="form-table">
                <tr valign="top">
                <th scope="row">' . __('List Head','wp-attachments') . '</th>
                <td><input type="text" name="wpatt_option_localization_n" value="' . esc_html(get_option('wpatt_option_localization')) . '" />&nbsp;' . __('Attachments list title','wp-attachments') . '<br>
                <input type="checkbox" name="wpatt_show_orderby_n" ' . (get_option('wpatt_show_orderby') == '1' ? 'checked' : '') . '/>&nbsp;' . __('Show orderby dropdown (date, title)','wp-attachments') . '</td></tr>
                <tr valign="top">
                <th scope="row">' . __('Date Format') . '</th>
                <td><input type="text" name="wpatt_option_date_localization_n" value="' . esc_html(get_option('wpatt_option_date_localization')) . '" />&nbsp;' . __('The format for dates','wp-attachments') . '<small> (' . strtolower(__('Default')) . ': <code>d.m.Y</code>)</small></td></tr>';
            echo '<tr><th scope="row">' . __('Include images','wp-attachments') . '</th>
                <td><input type="checkbox" name="wpatt_option_includeimages_n" ' . (get_option('wpatt_option_includeimages') == '1' ? 'checked' : '') . '/>&nbsp;' . __('Check this option if you want to include images (.jpg, .jpeg, .gif, .png) in the attachments list','wp-attachments') . '</td></tr>';
            echo '<tr><th scope="row">' . __('Open in new tab','wp-attachments') . '</th>
                <td><input type="checkbox" name="wpatt_option_targetblank_n" ' . (get_option('wpatt_option_targetblank') == '1' ? 'checked' : '') . '/>&nbsp;' . __('Check this option if you want to add target="_blank" to every file listed in order to open it in a new tab','wp-attachments') . '</td></tr>';
            echo '<tr><th scope="row">' . __('Restrict loading','wp-attachments') . '</th>
                <td><input type="checkbox" name="wpatt_option_restrictload_n" ' . (get_option('wpatt_option_restrictload') == '1' ? 'checked' : '') . '/>&nbsp;' . __('Check this option if you want to restrict the plugin to single or page views (not archive or other views)','wp-attachments') . '</td></tr>';
            echo '<tr><th scope="row">' . __('Download counter','wp-attachments') . '</th>
                <td><input type="checkbox" name="wpatt_counter_n" ' . (get_option('wpatt_counter') == '1' ? 'checked' : '') . '/>&nbsp;' . __('Check this option if you want to enable download counter','wp-attachments') . '<br>
                <input type="checkbox" name="wpatt_excludelogged_counter_n" ' . (get_option('wpatt_excludelogged_counter') == '1' ? 'checked' : '') . '/>&nbsp;' . __('Check this option to exclude logged-in users from the counter','wp-attachments') . '</td></tr>';
            echo '</table>';
            wp_nonce_field('wpatt_general_settings');
            echo '<p class="submit"><input type="submit" class="button-primary" name="submit-general" value="' . __('Save Changes') . '" /></p>';
            break;

        case 'appearance' :
            // Additional appearance settings form fields and nonce field
            echo '<table class="form-table">
                <tbody>
                <tr valign="top">
                    <th scope="row">Icon pack</th>
                    <td>
                    <fieldset>
                        <label>
                            <input type="radio" value="0" name="style" ' . (!get_option('wpa_ict')>0 ? 'checked' : '') . '> <strong>Fugue Icons (default)</strong>
                            <img src="' . plugins_url() . '/wp-attachments/styles/0/document.png"/>
                            <img src="' . plugins_url() . '/wp-attachments/styles/0/document-word.png"/>
                            <img src="' . plugins_url() . '/wp-attachments/styles/0/document-pdf.png"/>
                            <br><small>' . __('Author') . ': Yusuke Kamiyamane</small>
                        </label><br>
                        <label>
                            <input type="radio" value="1" name="style" ' . (get_option('wpa_ict') == 1 ? 'checked' : '') . '> <strong>Crystal Clear</strong>
                            <img src="' . plugins_url() . '/wp-attachments/styles/1/document.png"/>
                            <img src="' . plugins_url() . '/wp-attachments/styles/1/document-word.png"/>
                            <img src="' . plugins_url() . '/wp-attachments/styles/1/document-pdf.png"/>
                            <br><small>' . __('Author') . ': <a href="http://www.everaldo.com/">Everaldo Coelho</a></small>
                        </label><br>
                        <label>
                            <input type="radio" value="2" name="style" ' . (get_option('wpa_ict') == 2 ? 'checked' : '') . '> <strong>Diagona Icons</strong>
                            <img src="' . plugins_url() . '/wp-attachments/styles/2/document.png"/>
                            <img src="' . plugins_url() . '/wp-attachments/styles/2/document-word.png"/>
                            <img src="' . plugins_url() . '/wp-attachments/styles/2/document-pdf.png"/>
                            <br><small>' . __('Author') . ': Asher Abbasi</small>
                        </label><br>
                        <label>
                            <input type="radio" value="3" name="style" ' . (get_option('wpa_ict') == 3 ? 'checked' : '') . '> <strong>Page Icons</strong>
                            <img src="' . plugins_url() . '/wp-attachments/styles/3/document.png"/>
                            <img src="' . plugins_url() . '/wp-attachments/styles/3/document-word.png"/>
                            <img src="' . plugins_url() . '/wp-attachments/styles/3/document-pdf.png"/>
                            <br><small>' . __('Author') . ': <a href="http://iconblock.com/">Matthew Skiles</a></small>
                        </label><br>
                    </fieldset>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Choose a Template</th>
                    <td>
                    <fieldset>
                        <label>
                            <input type="radio" value="0" name="template" ' . (get_option('wpa_template') != 1 ? 'checked' : '') . '> <strong>Default</strong>
                            <br>
                            <code>
                            <strong>&lt;li&gt;</strong>&lt;a href="%URL%" title="%TITLE%" %TARGET%&gt;%ICON% %TITLE%&lt;/a&gt;&lt;/li&gt;
                            </code>
                        </label><br>
                        <label>
                            <input type="radio" value="1" name="template" ' . (get_option('wpa_template') == 1 ? 'checked' : '') . '> <strong>Custom Template</strong>
                            <br>
                            <textarea id="wpa_template_custom" name="wpa_template_custom" style="min-height: 120px;min-width: 340px;">' . esc_html(get_option('wpa_template_custom')) . '</textarea>
                            <br><small>' . __('Use %URL%, %TITLE%, %TARGET%, %ICON% tags') . '</small>
                        </label><br>
                    </fieldset>
                    </td>
                </tr>
                </tbody>
            </table>';
            wp_nonce_field('wpatt_appearance_settings');
            echo '<p class="submit"><input type="submit" class="button-primary" name="submit-appearance" value="' . __('Save Changes') . '" /></p>';
            break;
    }
    echo '</table>';
    echo '</form>';
    echo '</div>';
}
?>
