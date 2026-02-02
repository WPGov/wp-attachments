<?php

function wpatt_plugin_options()
{
    if (!current_user_can('manage_options')) { 
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-attachments')); 
    }

    $updated = false;

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
        $updated = true;
    }

    // Handle appearance settings submission securely
    if (isset($_POST['submit-appearance'])) {
        check_admin_referer('wpatt_appearance_settings');

        update_option('wpa_ict', sanitize_text_field($_POST['style'] ?? ''));
        update_option('wpa_template', sanitize_text_field($_POST['template'] ?? ''));
        update_option('wpa_template_custom', wp_kses_post($_POST['wpa_template_custom'] ?? ''));
        $updated = true;
    }

    if ($updated) {
        add_settings_error('wpatt_messages', 'wpatt_message', __('Settings Saved', 'wp-attachments'), 'updated');
    }

    wpa_register_initial_settings();

    echo '<div class="wrap wpatt-settings-wrap">';

    echo '<div style="float:right; margin-top: 20px;">
        <a href="https://wordpress.org/support/plugin/wp-attachments/reviews/#new-post" target="_blank" class="button">' . esc_html__('Rate this plugin ★★★★★', 'wp-attachments') . '</a>
        <a href="https://wordpress.org/plugins/wp-attachments/#developers" target="_blank" class="button">' . esc_html__('Changelog', 'wp-attachments') . '</a>
    </div>';

    echo '<h1>' . esc_html__('WP Attachments Settings', 'wp-attachments') . '</h1>';
    
    settings_errors('wpatt_messages');

    echo '<style>
        .wpatt-settings-wrap .nav-tab-wrapper { margin-bottom: 24px; }
        .wpatt-settings-wrap .form-table th { width: 220px; vertical-align: top; padding-top: 16px; }
        .wpatt-settings-wrap .form-table td { padding-top: 12px; }
        .wpatt-settings-wrap input[type="text"] { width: 100%; max-width: 400px; }
        .wpatt-settings-wrap .wpatt-desc { color: #666; font-size: 0.9em; margin-top: 4px; display: block; }
        .wpatt-settings-wrap .wpatt-checkbox-group label { display: block; margin-top: 8px; }
        .wpatt-settings-wrap .wpatt-template-radio { margin-bottom: 20px; display: block; padding: 12px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; }
        .wpatt-settings-wrap .wpatt-template-radio:hover { background: #f6f7f7; }
        .wpatt-settings-wrap .wpatt-template-radio input[type="radio"] { margin-right: 10px; }
        .wpatt-settings-wrap textarea { font-family: monospace; width: 100%; max-width: 600px; }
        .wpatt-cpt-table { border-collapse: collapse; width: 100%; max-width: 600px; margin-top: 10px; background: #fff; border: 1px solid #ccd0d4; }
        .wpatt-cpt-table th, .wpatt-cpt-table td { text-align: left; padding: 10px; border-bottom: 1px solid #ccd0d4; }
        .wpatt-cpt-table th { background: #f6f7f7; font-weight: 600; }
        .wpatt-cpt-table tr:last-child td { border-bottom: none; }
    </style>';

    $current = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

    $tabs = array(
        'general' => esc_html__('General Settings', 'wp-attachments'),
        'appearance' => esc_html__('Appearance & Templates', 'wp-attachments')
    );

    echo '<h2 class="nav-tab-wrapper">';
    foreach ($tabs as $tab => $name) {
        $class = ($tab === $current) ? ' nav-tab-active' : '';
        echo "<a class='nav-tab{$class}' href='" . esc_url(add_query_arg('tab', $tab)) . "'>{$name}</a>";
    }
    echo '</h2>';

    echo '<form method="post" action="">';

    switch ($current) {
        case 'general':
            wp_nonce_field('wpatt_general_settings');
            echo '<table class="form-table">';
            echo '<tr valign="top">
                <th scope="row">' . esc_html__('List Header', 'wp-attachments') . '</th>
                <td>
                    <input type="text" name="wpatt_option_localization_n" value="' . esc_attr(get_option('wpatt_option_localization')) . '" />
                    <span class="wpatt-desc">' . esc_html__('Text displayed above the attachments list (e.g., "Downloads" or "Attachments").', 'wp-attachments') . '</span>
                </td>
            </tr>';
            
            echo '<tr valign="top">
                <th scope="row">' . esc_html__('Display Options', 'wp-attachments') . '</th>
                <td>
                    <div class="wpatt-checkbox-group">
                        <label>
                            <input type="checkbox" name="wpatt_show_orderby_n" ' . (get_option('wpatt_show_orderby') == '1' ? 'checked' : '') . '/>
                            ' . esc_html__('Show orderby dropdown (Date, Title, File size)', 'wp-attachments') . '
                        </label>
                        <label>
                            <input type="checkbox" name="wpatt_option_showdate_n" ' . (get_option('wpatt_option_showdate') == '1' ? 'checked' : '') . '/>
                            ' . esc_html__('Show file date in the list', 'wp-attachments') . '
                        </label>
                        <label>
                            <input type="checkbox" name="wpatt_option_includeimages_n" ' . (get_option('wpatt_option_includeimages') == '1' ? 'checked' : '') . '/>
                            ' . esc_html__('Include images in the attachments list (.jpg, .png, etc.)', 'wp-attachments') . '
                        </label>
                        <label>
                            <input type="checkbox" name="wpatt_option_targetblank_n" ' . (get_option('wpatt_option_targetblank') == '1' ? 'checked' : '') . '/>
                            ' . esc_html__('Open links in a new tab', 'wp-attachments') . '
                        </label>
                         <label>
                            <input type="checkbox" name="wpatt_option_restrictload_n" ' . (get_option('wpatt_option_restrictload') == '1' ? 'checked' : '') . '/>
                            ' . esc_html__('Restrict loading to single posts/pages only (disable on archives/home)', 'wp-attachments') . '
                        </label>
                    </div>
                </td>
            </tr>';

            echo '<tr valign="top">
                <th scope="row">' . esc_html__('Date Format', 'wp-attachments') . '</th>
                <td>
                    <input type="text" name="wpatt_option_date_localization_n" value="' . esc_attr(get_option('wpatt_option_date_localization')) . '" />
                    <span class="wpatt-desc">' . sprintf(
                        esc_html__('Date format following PHP date standards. Default: %s', 'wp-attachments'),
                        '<code>d.m.Y</code>'
                    ) . '</span>
                </td>
            </tr>';

            echo '<tr valign="top">
                <th scope="row">' . esc_html__('Download Tracker', 'wp-attachments') . '</th>
                <td>
                    <div class="wpatt-checkbox-group">
                        <label>
                            <input type="checkbox" name="wpatt_counter_n" ' . (get_option('wpatt_counter') == '1' ? 'checked' : '') . '/>
                            ' . esc_html__('Enable download counter', 'wp-attachments') . '
                        </label>
                        <label>
                            <input type="checkbox" name="wpatt_excludelogged_counter_n" ' . (get_option('wpatt_excludelogged_counter') == '1' ? 'checked' : '') . '/>
                            ' . esc_html__('Exclude logged-in users from counting', 'wp-attachments') . '
                        </label>
                    </div>
                </td>
            </tr>';

            echo '<tr valign="top">
                <th scope="row">' . esc_html__('Post Type Permissions', 'wp-attachments') . '</th>
                <td>
                    <p class="description">' . esc_html__('Configure where WP Attachments should be available and if they should show by default.', 'wp-attachments') . '</p>
                    <table class="wpatt-cpt-table">
                        <thead>
                            <tr>
                                <th>' . esc_html__('Post Type', 'wp-attachments') . '</th>
                                <th>' . esc_html__('Enable Metabox', 'wp-attachments') . '</th>
                                <th>' . esc_html__('Display Default', 'wp-attachments') . '</th>
                            </tr>
                        </thead>
                        <tbody>';
            $post_types = get_post_types(['public' => true], 'objects');
            foreach ($post_types as $post_type) {
                if ($post_type->name === 'attachment') continue;
                $mb_enabled = get_option('wpatt_enable_metabox_' . $post_type->name, '1');
                $disp_enabled = get_option('wpatt_enable_display_' . $post_type->name, '1');
                echo '<tr>
                    <td><strong>' . esc_html($post_type->labels->singular_name) . '</strong></td>
                    <td style="text-align:center;">
                        <input type="checkbox" name="wpatt_enable_' . esc_attr($post_type->name) . '" value="1" ' . checked($mb_enabled, '1', false) . ' />
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" name="wpatt_defaulton_' . esc_attr($post_type->name) . '" value="1" ' . checked($disp_enabled, '1', false) . ' />
                    </td>
                </tr>';
            }
            echo '</tbody></table>
                </td>
            </tr>';
            echo '</table>';
            submit_button(__('Save General Settings', 'wp-attachments'), 'primary', 'submit-general');
            break;

        case 'appearance':
            wp_nonce_field('wpatt_appearance_settings');
            echo '<table class="form-table">';
            echo '<tr valign="top">
                <th scope="row">' . esc_html__('Icon Pack', 'wp-attachments') . '</th>
                <td>
                    <fieldset>';
            $icon_packs = [
                0 => ['label' => 'Fugue Icons', 'author' => 'Yusuke Kamiyamane'],
                1 => ['label' => 'Crystal Clear', 'author' => 'Everaldo Coelho', 'url' => 'https://www.everaldo.com/'],
                2 => ['label' => 'Diagona Icons', 'author' => 'Asher Abbasi'],
                3 => ['label' => 'Page Icons', 'author' => 'Matthew Skiles', 'url' => 'https://iconblock.com/'],
            ];
            foreach ($icon_packs as $val => $pack) {
                $checked = (intval(get_option('wpa_ict')) === $val) ? 'checked' : '';
                echo '<label class="wpatt-template-radio">
                    <input type="radio" value="' . esc_attr($val) . '" name="style" ' . $checked . '> 
                    <strong>' . esc_html($pack['label']) . '</strong>
                    <span style="float:right;">
                        <img src="' . esc_url(plugins_url('wp-attachments/styles/' . $val . '/document.png')) . '" style="vertical-align:middle;"/>
                        <img src="' . esc_url(plugins_url('wp-attachments/styles/' . $val . '/document-word.png')) . '" style="vertical-align:middle;"/>
                        <img src="' . esc_url(plugins_url('wp-attachments/styles/' . $val . '/document-pdf.png')) . '" style="vertical-align:middle;"/>
                    </span>
                    <br><small class="wpatt-desc">' . esc_html__('Author', 'wp-attachments') . ': ' . (isset($pack['url']) ? '<a href="' . esc_url($pack['url']) . '" target="_blank">' . esc_html($pack['author']) . '</a>' : esc_html($pack['author'])) . '</small>
                </label>';
            }
            echo '</fieldset></td></tr>';

            echo '<tr valign="top">
                <th scope="row">' . esc_html__('Display Template', 'wp-attachments') . '</th>
                <td>
                    <fieldset>';
            $templates = [
                0 => [
                    'label' => 'Simple List',
                    'code' => '<a href="%URL%">%TITLE%</a> <small>(%SIZE%)</small>',
                ],
                1 => [
                    'label' => 'List with date',
                    'code' => '<a href="%URL%">%TITLE%</a> <small>(%SIZE%)</small> <div style="float:right;">%DATE%</div>',
                ],
                2 => [
                    'label' => 'Detailed List',
                    'code' => '<a href="%URL%">%TITLE%</a> <small>&bull; %SIZE% &bull; %DOWNLOADS% clicks</small> <div style="float:right;">%DATE%</div><br><small>%CAPTION%</small>',
                ],
            ];
            foreach ($templates as $val => $tpl) {
                $checked = (intval(get_option('wpa_template')) === $val) ? 'checked' : '';
                echo '<label class="wpatt-template-radio">
                    <input type="radio" value="' . esc_attr($val) . '" name="template" ' . $checked . '> <strong>' . esc_html($tpl['label']) . '</strong>
                    <br><code style="background:transparent; padding:0;">' . esc_html($tpl['code']) . '</code>
                </label>';
            }
            echo '<label class="wpatt-template-radio">
                <input type="radio" value="3" name="template" ' . (intval(get_option('wpa_template')) === 3 ? 'checked' : '') . '> <strong>' . esc_html__('Custom Template', 'wp-attachments') . '</strong>
                <textarea name="wpa_template_custom" rows="4">' . esc_textarea(get_option('wpa_template_custom')) . '</textarea>
                <br><span class="wpatt-desc">' . esc_html__('Tags:', 'wp-attachments') . ' <code>%URL%</code>, <code>%TITLE%</code>, <code>%SIZE%</code>, <code>%DATE%</code>, <code>%DOWNLOADS%</code>, <code>%CAPTION%</code></span>
            </label>';
            echo '</fieldset></td></tr>';
            echo '</table>';
            submit_button(__('Save Appearance Settings', 'wp-attachments'), 'primary', 'submit-appearance');
            break;
    }

    echo '</form>';
    echo '</div>';
}

?>
