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

        // Save per-post-type metabox and default display settings
        $post_types = get_post_types(['public' => true], 'objects');
        foreach ($post_types as $post_type) {
            if ($post_type->name === 'attachment') continue;

            // Save metabox enabled/disabled
            $metabox_enabled = !empty($_POST['wpatt_enable_' . $post_type->name]) ? '1' : '0';
            update_option('wpatt_enable_metabox_' . $post_type->name, $metabox_enabled);

            // Save default display enabled/disabled
            $display_enabled = !empty($_POST['wpatt_defaulton_' . $post_type->name]) ? '1' : '0';
            update_option('wpatt_enable_display_' . $post_type->name, $display_enabled);
        }
    }

    // Handle appearance settings submission securely
    if (isset($_POST['submit-appearance'])) {
        check_admin_referer('wpatt_appearance_settings');

        update_option('wpa_ict', sanitize_text_field($_POST['style'] ?? ''));
        update_option('wpa_template', sanitize_text_field($_POST['template'] ?? ''));
        update_option('wpa_template_custom', wp_kses_post($_POST['wpa_template_custom'] ?? ''));
    }

    wpa_register_initial_settings();

    echo '<div class="wrap wpatt-settings-wrap">';

    echo '<style>
        .wpatt-settings-wrap h2 strong { font-size: 1.5em; }
        .wpatt-settings-wrap .nav-tab-wrapper { margin-bottom: 24px; }
        .wpatt-settings-wrap .form-table th { width: 220px; vertical-align: top; padding-top: 16px; }
        .wpatt-settings-wrap .form-table td { padding-top: 12px; }
        .wpatt-settings-wrap input[type="text"] { min-width: 260px; }
        .wpatt-settings-wrap .add-new-h2 { margin-left: 8px; }
        .wpatt-settings-wrap .wpatt-section-title { font-size: 1.1em; margin-top: 32px; margin-bottom: 8px; color: #2271b1; }
        .wpatt-settings-wrap .wpatt-checkbox-group label { display: block; margin-bottom: 6px; }
        .wpatt-settings-wrap .wpatt-template-radio { margin-bottom: 12px; display: block; }
        .wpatt-settings-wrap textarea { font-family: monospace; }
        .wpatt-settings-wrap .wpatt-desc { color: #666; font-size: 0.96em; }
    </style>';

    echo '<h2>
        <strong>WP Attachments</strong>
        <small style="font-size:0.8em; color:#888;">' . esc_html(get_option('wpa_version_number')) . '</small>
    </h2>';

    echo '<div style="float:right; margin-top:-36px;">
        <a href="https://wordpress.org/support/view/plugin-reviews/wp-attachments" target="_blank" class="add-new-h2">' . esc_html__('Rate this plugin', 'wp-attachments') . '</a>
        <a href="https://wordpress.org/plugins/wp-attachments/changelog/" target="_blank" class="add-new-h2">' . esc_html__('Changelog', 'wp-attachments') . '</a>
    </div>';

    echo '<form method="post" name="options" target="_self" style="margin-top:32px;">';

    settings_fields('wpatt_option_group');

    $current = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

    $tabs = array(
        'general' => esc_html__('Settings', 'wp-attachments'),
        'appearance' => esc_html__('Appearance', 'wp-attachments')
    );

    echo '<h2 class="nav-tab-wrapper">';
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
                    <input type="text" name="wpatt_option_localization_n" value="' . esc_attr(get_option('wpatt_option_localization')) . '" />
                    <div class="wpatt-desc">' . esc_html__('Attachments list title', 'wp-attachments') . '</div>
                    <div class="wpatt-checkbox-group">
                        <label>
                            <input type="checkbox" name="wpatt_show_orderby_n" ' . (get_option('wpatt_show_orderby') == '1' ? 'checked' : '') . '/>
                            ' . esc_html__('Show orderby dropdown (date, title)', 'wp-attachments') . '
                        </label>
                    </div>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">' . esc_html__('Date Format', 'wp-attachments') . '</th>
                <td>
                    <input type="text" name="wpatt_option_date_localization_n" value="' . esc_attr(get_option('wpatt_option_date_localization')) . '" />
                    <div class="wpatt-desc">' . esc_html__('The format for dates', 'wp-attachments') . ' <small>(' . strtolower(esc_html__('Default', 'wp-attachments')) . ': <code>d.m.Y</code>)</small></div>
                </td>
            </tr>
            <tr>
                <th scope="row">' . esc_html__('Include images', 'wp-attachments') . '</th>
                <td>
                    <label>
                        <input type="checkbox" name="wpatt_option_includeimages_n" ' . (get_option('wpatt_option_includeimages') == '1' ? 'checked' : '') . '/>
                        ' . esc_html__('Check this option if you want to include images (.jpg, .jpeg, .gif, .png) in the attachments list', 'wp-attachments') . '
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">' . esc_html__('Open in new tab', 'wp-attachments') . '</th>
                <td>
                    <label>
                        <input type="checkbox" name="wpatt_option_targetblank_n" ' . (get_option('wpatt_option_targetblank') == '1' ? 'checked' : '') . '/>
                        ' . esc_html__('Check this option if you want to add target="_blank" to every file listed in order to open it in a new tab', 'wp-attachments') . '
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">' . esc_html__('Restrict loading', 'wp-attachments') . '</th>
                <td>
                    <label>
                        <input type="checkbox" name="wpatt_option_restrictload_n" ' . (get_option('wpatt_option_restrictload') == '1' ? 'checked' : '') . '/>
                        ' . esc_html__('Check this option if you want to restrict the plugin to single or page views (not archive or other views)', 'wp-attachments') . '
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">' . esc_html__('Download counter', 'wp-attachments') . '</th>
                <td>
                    <div class="wpatt-checkbox-group">
                        <label>
                            <input type="checkbox" name="wpatt_counter_n" ' . (get_option('wpatt_counter') == '1' ? 'checked' : '') . '/>
                            ' . esc_html__('Enable download counter', 'wp-attachments') . '
                        </label>
                        <label>
                            <input type="checkbox" name="wpatt_excludelogged_counter_n" ' . (get_option('wpatt_excludelogged_counter') == '1' ? 'checked' : '') . '/>
                            ' . esc_html__('Exclude logged-in users from the counter', 'wp-attachments') . '
                        </label>
                    </div>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">' . esc_html__('Post Type Defaults', 'wp-attachments') . '</th>
                <td>
                    <div style="margin-bottom:18px;">
                        <strong>' . esc_html__('Enable', 'wp-attachments') . '</strong><br>
                        <span class="wpatt-desc">' . esc_html__('Enable or disable the WP Attachments metabox for each post type. If disabled, the metabox will not appear and attachments will not be available for that post type.', 'wp-attachments') . '</span>
                        <table style="border-collapse:collapse; min-width: 480px; margin-top:8px;">';
            $post_types = get_post_types(['public' => true], 'objects');
            foreach ($post_types as $post_type) {
                if ($post_type->name === 'attachment') continue;
                $enabled = get_option('wpatt_enable_metabox_' . $post_type->name, '1');
                echo '<tr>
                    <td style="padding:4px 12px 4px 0;"><strong>' . esc_html($post_type->labels->singular_name) . '</strong></td>
                    <td style="padding:4px 12px;">
                        <input type="checkbox" class="wpatt-enable-metabox" data-cpt="' . esc_attr($post_type->name) . '" name="wpatt_enable_' . esc_attr($post_type->name) . '" value="1" ' . checked($enabled, '1', false) . ' aria-label="' . esc_attr__('Show metabox for', 'wp-attachments') . ' ' . esc_attr($post_type->labels->singular_name) . '" />
                    </td>
                </tr>';
            }
            echo '</table>
                    </div>
                    <div>
                        <strong>' . esc_html__('Display Attachments by Default', 'wp-attachments') . '</strong><br>
                        <span class="wpatt-desc">' . esc_html__('Choose whether attachments should be displayed by default on the frontend for new posts of each post type. This can be changed per post.', 'wp-attachments') . '</span>
                        <table style="border-collapse:collapse; min-width: 480px; margin-top:8px;">';
        foreach ($post_types as $post_type) {
            if ($post_type->name === 'attachment') continue;
            $default_on = get_option('wpatt_enable_display_' . $post_type->name, '1');
            $enabled = get_option('wpatt_enable_metabox_' . $post_type->name, '1');
            echo '<tr>
                <td style="padding:4px 12px 4px 0;"><strong>' . esc_html($post_type->labels->singular_name) . '</strong></td>
                <td style="padding:4px 12px;">
                    <input type="checkbox" class="wpatt-defaulton" data-cpt="' . esc_attr($post_type->name) . '" name="wpatt_defaulton_' . esc_attr($post_type->name) . '" value="1" ' . checked($default_on, '1', false) . ' aria-label="' . esc_attr__('Display attachments by default for', 'wp-attachments') . ' ' . esc_attr($post_type->labels->singular_name) . '" />
                </td>
            </tr>';
        }
        echo '</table>
        </div>
    </td>
</tr>';
            wp_nonce_field('wpatt_general_settings');
            echo '<p class="submit"><input type="submit" class="button-primary" name="submit-general" value="' . esc_attr__('Save Changes', 'wp-attachments') . '" /></p>';
            break;

        case 'appearance':
            echo '<tr valign="top">
                <th scope="row">' . esc_html__('Icon pack', 'wp-attachments') . '</th>
                <td>
                    <fieldset>';
            $icon_packs = [
                0 => [
                    'label' => 'Fugue Icons (default)',
                    'author' => 'Yusuke Kamiyamane',
                    'author_url' => '',
                ],
                1 => [
                    'label' => 'Crystal Clear',
                    'author' => 'Everaldo Coelho',
                    'author_url' => 'https://www.everaldo.com/',
                ],
                2 => [
                    'label' => 'Diagona Icons',
                    'author' => 'Asher Abbasi',
                    'author_url' => '',
                ],
                3 => [
                    'label' => 'Page Icons',
                    'author' => 'Matthew Skiles',
                    'author_url' => 'https://iconblock.com/',
                ],
            ];
            foreach ($icon_packs as $val => $pack) {
                echo '<label class="wpatt-template-radio">
                    <input type="radio" value="' . esc_attr($val) . '" name="style" ' . (intval(get_option('wpa_ict')) === $val ? 'checked' : '') . '> <strong>' . esc_html($pack['label']) . '</strong>
                    <img src="' . esc_url(plugins_url('wp-attachments/styles/' . $val . '/document.png')) . '" style="vertical-align:middle; margin:0 2px;"/>
                    <img src="' . esc_url(plugins_url('wp-attachments/styles/' . $val . '/document-word.png')) . '" style="vertical-align:middle; margin:0 2px;"/>
                    <img src="' . esc_url(plugins_url('wp-attachments/styles/' . $val . '/document-pdf.png')) . '" style="vertical-align:middle; margin:0 2px;"/>
                    <br><small>' . esc_html__('Author', 'wp-attachments') . ': ';
                if ($pack['author_url']) {
                    echo '<a href="' . esc_url($pack['author_url']) . '" target="_blank">' . esc_html($pack['author']) . '</a>';
                } else {
                    echo esc_html($pack['author']);
                }
                echo '</small>
                </label>';
            }
            echo '</fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">' . esc_html__('Choose a Template', 'wp-attachments') . '</th>
                <td>
                    <fieldset>';
            $templates = [
                0 => [
                    'label' => 'Simple List (Default)',
                    'code' => '&lt;a href="%URL%"&gt;%TITLE%&lt;/a&gt; &lt;small&gt;(%SIZE%)&lt;/small&gt;',
                ],
                1 => [
                    'label' => 'List with Date on Right',
                    'code' => '&lt;a href="%URL%"&gt;%TITLE%&lt;/a&gt; &lt;small&gt;(%SIZE%)&lt;/small&gt; &lt;div style="float:right;"&gt;%DATE%&lt;/div&gt;',
                ],
                2 => [
                    'label' => 'Detailed List with Downloads and Caption',
                    'code' => '&lt;a href="%URL%"&gt;%TITLE%&lt;/a&gt; &lt;small&gt;&amp;bull; %SIZE% &amp;bull; %DOWNLOADS% click&lt;/small&gt; &lt;div style="float:right;"&gt;%DATE%&lt;/div&gt;&lt;br&gt;&lt;small&gt;%CAPTION%&lt;/small&gt;',
                ],
            ];
            foreach ($templates as $val => $tpl) {
                echo '<label class="wpatt-template-radio">
                    <input type="radio" value="' . esc_attr($val) . '" name="template" ' . (intval(get_option('wpa_template')) === $val ? 'checked' : '') . '> <strong>' . esc_html($tpl['label']) . '</strong>
                    <br>
                    <code>' . $tpl['code'] . '</code>
                </label>';
            }
            echo '<label class="wpatt-template-radio">
                    <input type="radio" value="3" name="template" ' . (intval(get_option('wpa_template')) === 3 ? 'checked' : '') . '> <strong>Custom Template</strong>
                    <br>
                    <textarea id="wpa_template_custom" name="wpa_template_custom" style="min-height: 120px;min-width: 340px;">' . esc_textarea(get_option('wpa_template_custom')) . '</textarea>
                    <br><small>' . esc_html__('Use %URL%, %TITLE%, %SIZE%, %DATE%, %DOWNLOADS%, %CAPTION% tags', 'wp-attachments') . '</small>
                </label>';
            echo '</fieldset>
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
