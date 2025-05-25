<?php

function wpatt_plugin_options()
{
    if (!current_user_can('manage_options')) { 
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-attachments')); 
    }

    // Handle general settings submission securely
    if (isset($_POST['submit-general'])) {
        check_admin_referer('wpatt_general_settings');

        update_option('wpatt_option_localization', sanitize_text_field($_POST["wpatt_option_localization_n"] ?? ''));
        update_option('wpatt_option_date_localization', sanitize_text_field($_POST["wpatt_option_date_localization_n"] ?? ''));
        
        update_option('wpatt_show_orderby', !empty($_POST['wpatt_show_orderby_n']) ? '1' : '0');
        update_option('wpatt_option_showdate', !empty($_POST['wpatt_option_showdate_n']) ? '1' : '0');
        update_option('wpatt_option_includeimages', !empty($_POST['wpatt_option_includeimages_n']) ? '1' : '0');
        update_option('wpatt_option_targetblank', !empty($_POST['wpatt_option_targetblank_n']) ? '1' : '0');
        update_option('wpatt_option_restrictload', !empty($_POST['wpatt_option_restrictload_n']) ? '1' : '0');
        update_option('wpatt_counter', !empty($_POST['wpatt_counter_n']) ? '1' : '0');
        update_option('wpatt_excludelogged_counter', !empty($_POST['wpatt_excludelogged_counter_n']) ? '1' : '0');
    }

    // Handle appearance settings submission securely
    if (isset($_POST['submit-appearance'])) {
        check_admin_referer('wpatt_appearance_settings');

        update_option('wpa_ict', sanitize_text_field($_POST['style'] ?? ''));
        update_option('wpa_template', sanitize_text_field($_POST['template'] ?? ''));
        update_option('wpa_template_custom', wp_kses_post($_POST['wpa_template_custom'] ?? ''));
    }

    wpa_register_initial_settings();

    echo '<div class="wrap">';

    echo '<h2><strong style="font-size: 1.2em;">WP Attachments</strong><small> ' . esc_html(get_option('wpa_version_number')) . '</small></h2><br><br>';

    echo '<form method="post" name="options" target="_self">';

    settings_fields('wpatt_option_group');

    $current = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

    $tabs = array(
        'general' => esc_html__('Settings', 'wp-attachments'),
        'appearance' => esc_html__('Appearance', 'wp-attachments')
    );

    echo '<h2 class="nav-tab-wrapper">';
    echo '<div style="float:right;">
        <a href="https://wordpress.org/support/view/plugin-reviews/wp-attachments" target="_blank" class="add-new-h2">' . esc_html__('Rate this plugin', 'wp-attachments') . '</a>
        <a href="https://wordpress.org/plugins/wp-attachments/changelog/" target="_blank" class="add-new-h2">' . esc_html__('Changelog', 'wp-attachments') . '</a>
    </div>';
    foreach ($tabs as $tab => $name) {
        $class = ($tab === $current) ? ' nav-tab-active' : '';
        echo "<a class='nav-tab{$class}' href='" . esc_url(add_query_arg('tab', $tab)) . "'>{$name}</a>";
    }
    echo '</h2>';

    $tab = $current;

    echo '<table class="form-table">';
    switch ($tab) {
        case 'general':
            echo '<tr valign="top">
                <th scope="row">' . esc_html__('List Head', 'wp-attachments') . '</th>
                <td>
                    <input type="text" name="wpatt_option_localization_n" value="' . esc_attr(get_option('wpatt_option_localization')) . '" />&nbsp;' . esc_html__('Attachments list title', 'wp-attachments') . '<br>
                    <input type="checkbox" name="wpatt_show_orderby_n" ' . (get_option('wpatt_show_orderby') == '1' ? 'checked' : '') . '/>&nbsp;' . esc_html__('Show orderby dropdown (date, title)', 'wp-attachments') . '
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">' . esc_html__('Date Format', 'wp-attachments') . '</th>
                <td>
                    <input type="text" name="wpatt_option_date_localization_n" value="' . esc_attr(get_option('wpatt_option_date_localization')) . '" />&nbsp;' . esc_html__('The format for dates', 'wp-attachments') . '<small> (' . strtolower(esc_html__('Default', 'wp-attachments')) . ': <code>d.m.Y</code>)</small>
                </td>
            </tr>
            <tr>
                <th scope="row">' . esc_html__('Include images', 'wp-attachments') . '</th>
                <td>
                    <input type="checkbox" name="wpatt_option_includeimages_n" ' . (get_option('wpatt_option_includeimages') == '1' ? 'checked' : '') . '/>&nbsp;' . esc_html__('Check this option if you want to include images (.jpg, .jpeg, .gif, .png) in the attachments list', 'wp-attachments') . '
                </td>
            </tr>
            <tr>
                <th scope="row">' . esc_html__('Open in new tab', 'wp-attachments') . '</th>
                <td>
                    <input type="checkbox" name="wpatt_option_targetblank_n" ' . (get_option('wpatt_option_targetblank') == '1' ? 'checked' : '') . '/>&nbsp;' . esc_html__('Check this option if you want to add target=\"_blank\" to every file listed in order to open it in a new tab', 'wp-attachments') . '
                </td>
            </tr>
            <tr>
                <th scope="row">' . esc_html__('Restrict loading', 'wp-attachments') . '</th>
                <td>
                    <input type="checkbox" name="wpatt_option_restrictload_n" ' . (get_option('wpatt_option_restrictload') == '1' ? 'checked' : '') . '/>&nbsp;' . esc_html__('Check this option if you want to restrict the plugin to single or page views (not archive or other views)', 'wp-attachments') . '
                </td>
            </tr>
            <tr>
                <th scope="row">' . esc_html__('Download counter', 'wp-attachments') . '</th>
                <td>
                    <input type="checkbox" name="wpatt_counter_n" ' . (get_option('wpatt_counter') == '1' ? 'checked' : '') . '/>&nbsp;' . esc_html__('Check this option if you want to enable download counter', 'wp-attachments') . '<br>
                    <input type="checkbox" name="wpatt_excludelogged_counter_n" ' . (get_option('wpatt_excludelogged_counter') == '1' ? 'checked' : '') . '/>&nbsp;' . esc_html__('Check this option to exclude logged-in users from the counter', 'wp-attachments') . '
                </td>
            </tr>';
            wp_nonce_field('wpatt_general_settings');
            echo '<p class="submit"><input type="submit" class="button-primary" name="submit-general" value="' . esc_attr__('Save Changes', 'wp-attachments') . '" /></p>';
            break;

        case 'appearance':
            echo '<tr valign="top">
                <th scope="row">' . esc_html__('Icon pack', 'wp-attachments') . '</th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" value="0" name="style" ' . (intval(get_option('wpa_ict')) === 0 ? 'checked' : '') . '> <strong>Fugue Icons (default)</strong>
                            <img src="' . esc_url(plugins_url('wp-attachments/styles/0/document.png')) . '"/>
                            <img src="' . esc_url(plugins_url('wp-attachments/styles/0/document-word.png')) . '"/>
                            <img src="' . esc_url(plugins_url('wp-attachments/styles/0/document-pdf.png')) . '"/>
                            <br><small>' . esc_html__('Author', 'wp-attachments') . ': Yusuke Kamiyamane</small>
                        </label><br>
                        <label>
                            <input type="radio" value="1" name="style" ' . (intval(get_option('wpa_ict')) === 1 ? 'checked' : '') . '> <strong>Crystal Clear</strong>
                            <img src="' . esc_url(plugins_url('wp-attachments/styles/1/document.png')) . '"/>
                            <img src="' . esc_url(plugins_url('wp-attachments/styles/1/document-word.png')) . '"/>
                            <img src="' . esc_url(plugins_url('wp-attachments/styles/1/document-pdf.png')) . '"/>
                            <br><small>' . esc_html__('Author', 'wp-attachments') . ': <a href="https://www.everaldo.com/">Everaldo Coelho</a></small>
                        </label><br>
                        <label>
                            <input type="radio" value="2" name="style" ' . (intval(get_option('wpa_ict')) === 2 ? 'checked' : '') . '> <strong>Diagona Icons</strong>
                            <img src="' . esc_url(plugins_url('wp-attachments/styles/2/document.png')) . '"/>
                            <img src="' . esc_url(plugins_url('wp-attachments/styles/2/document-word.png')) . '"/>
                            <img src="' . esc_url(plugins_url('wp-attachments/styles/2/document-pdf.png')) . '"/>
                            <br><small>' . esc_html__('Author', 'wp-attachments') . ': Asher Abbasi</small>
                        </label><br>
                        <label>
                            <input type="radio" value="3" name="style" ' . (intval(get_option('wpa_ict')) === 3 ? 'checked' : '') . '> <strong>Page Icons</strong>
                            <img src="' . esc_url(plugins_url('wp-attachments/styles/3/document.png')) . '"/>
                            <img src="' . esc_url(plugins_url('wp-attachments/styles/3/document-word.png')) . '"/>
                            <img src="' . esc_url(plugins_url('wp-attachments/styles/3/document-pdf.png')) . '"/>
                            <br><small>' . esc_html__('Author', 'wp-attachments') . ': <a href="https://iconblock.com/">Matthew Skiles</a></small>
                        </label><br>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">' . esc_html__('Choose a Template', 'wp-attachments') . '</th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" value="0" name="template" ' . (intval(get_option('wpa_template')) === 0 ? 'checked' : '') . '> <strong>Simple List (Default)</strong>
                            <br>
                            <code>
                                &lt;a href="%URL%"&gt;%TITLE%&lt;/a&gt; &lt;small&gt;(%SIZE%)&lt;/small&gt;
                            </code>
                        </label><br>
                        <label>
                            <input type="radio" value="1" name="template" ' . (intval(get_option('wpa_template')) === 1 ? 'checked' : '') . '> <strong>List with Date on Right</strong>
                            <br>
                            <code>
                                &lt;a href="%URL%"&gt;%TITLE%&lt;/a&gt; &lt;small&gt;(%SIZE%)&lt;/small&gt; &lt;div style="float:right;"&gt;%DATE%&lt;/div&gt;
                            </code>
                        </label><br>
                        <label>
                            <input type="radio" value="2" name="template" ' . (intval(get_option('wpa_template')) === 2 ? 'checked' : '') . '> <strong>Detailed List with Downloads and Caption</strong>
                            <br>
                            <code>
                                &lt;a href="%URL%"&gt;%TITLE%&lt;/a&gt; &lt;small&gt;&amp;bull; %SIZE% &amp;bull; %DOWNLOADS% click&lt;/small&gt; &lt;div style="float:right;"&gt;%DATE%&lt;/div&gt;&lt;br&gt;&lt;small&gt;%CAPTION%&lt;/small&gt;
                            </code>
                        </label><br>
                        <label>
                            <input type="radio" value="3" name="template" ' . (intval(get_option('wpa_template')) === 3 ? 'checked' : '') . '> <strong>Custom Template</strong>
                            <br>
                            <textarea id="wpa_template_custom" name="wpa_template_custom" style="min-height: 120px;min-width: 340px;">' . esc_textarea(get_option('wpa_template_custom')) . '</textarea>
                            <br><small>' . esc_html__('Use %URL%, %TITLE%, %SIZE%, %DATE%, %DOWNLOADS%, %CAPTION% tags', 'wp-attachments') . '</small>
                        </label><br>
                    </fieldset>
                </td>
            </tr>';
            wp_nonce_field('wpatt_appearance_settings');
            echo '<p class="submit"><input type="submit" class="button-primary" name="submit-appearance" value="' . esc_attr__('Save Changes', 'wp-attachments') . '" /></p>';
            break;
    }
    echo '</table>';
    echo '</form>';
    echo '</div>';
}
?>
