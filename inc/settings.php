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

        $heading_tag = sanitize_key($_POST['wpatt_option_heading_tag_n'] ?? 'h3');
        update_option('wpatt_option_heading_tag', in_array($heading_tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6'), true) ? $heading_tag : 'h3');
        
        update_option('wpatt_show_orderby', !empty($_POST['wpatt_show_orderby_n']) ? '1' : '0');
        update_option('wpatt_option_includeimages', !empty($_POST['wpatt_option_includeimages_n']) ? '1' : '0');
        update_option('wpatt_option_targetblank', !empty($_POST['wpatt_option_targetblank_n']) ? '1' : '0');
        update_option('wpatt_option_restrictload', !empty($_POST['wpatt_option_restrictload_n']) ? '1' : '0');
        update_option('wpatt_counter', !empty($_POST['wpatt_counter_n']) ? '1' : '0');
        update_option('wpatt_excludelogged_counter', !empty($_POST['wpatt_excludelogged_counter_n']) ? '1' : '0');

        // Save per-post-type metabox and frontend settings
        $post_types = get_post_types(['public' => true], 'objects');
        foreach ($post_types as $post_type) {
            if ($post_type->name === 'attachment') continue;

            // Save metabox enabled/disabled
            $metabox_enabled = !empty($_POST['wpatt_enable_' . $post_type->name]) ? '1' : '0';
            update_option('wpatt_enable_metabox_' . $post_type->name, $metabox_enabled);

            // Save frontend list enabled/disabled
            $frontend_enabled = !empty($_POST['wpatt_frontend_' . $post_type->name]) ? '1' : '0';
            update_option('wpatt_enable_frontend_' . $post_type->name, $frontend_enabled);

            // Replaced by the frontend switch above.
            delete_option('wpatt_enable_display_' . $post_type->name);
        }
        $updated = true;
    }

    // Handle appearance settings submission securely
    if (isset($_POST['submit-appearance'])) {
        check_admin_referer('wpatt_appearance_settings');

        $pack = (int) ($_POST['style'] ?? 0);
        update_option('wpa_ict', (string) (($pack >= 0 && $pack <= WPATT_PACK_MODERN) ? $pack : 0));
        $template = (int) ($_POST['template'] ?? 0);
        update_option('wpa_template', (string) (($template >= 0 && $template <= WPATT_TEMPLATE_CARD) ? $template : 0));
        $color_mode = sanitize_key($_POST['wpatt_icons_color'] ?? 'type');
        update_option('wpatt_icons_color', in_array($color_mode, array('type', 'theme', 'custom'), true) ? $color_mode : 'type');
        // Modern Card: unticked checkboxes are not posted, so start from all off;
        // wpatt_get_card_options() validates layout and width.
        $card_post = isset($_POST['wpatt_card']) && is_array($_POST['wpatt_card']) ? wp_unslash($_POST['wpatt_card']) : array();
        $card_save = array();
        foreach (array('ext', 'size', 'date', 'downloads', 'caption') as $flag) {
            $card_save[$flag] = empty($card_post[$flag]) ? 0 : 1;
        }
        $card_save['layout'] = sanitize_key($card_post['layout'] ?? 'grid');
        $card_save['width']  = sanitize_key($card_post['width'] ?? 'normal');
        update_option('wpatt_card', wpatt_get_card_options($card_save));

        $custom_color = sanitize_hex_color(wp_unslash($_POST['wpatt_icons_custom_color'] ?? ''));
        if ($custom_color) {
            update_option('wpatt_icons_custom_color', $custom_color);
        }
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
        .wpatt-settings-wrap .wpatt-heading-tag { margin: 10px 0 0; }
        .wpatt-settings-wrap .wpatt-heading-tag label { margin-right: 8px; }
        .wpatt-settings-wrap textarea { font-family: monospace; width: 100%; max-width: 600px; }
        /* Appearance: one bordered row per option, title and description aligned. */
        .wpatt-settings-wrap .wpatt-intro { margin: 0 0 10px; }
        .wpatt-settings-wrap .wpatt-options { display: flex; flex-direction: column; gap: 8px; max-width: 760px; }
        .wpatt-settings-wrap .wpatt-option { padding: 12px 14px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px; }
        .wpatt-settings-wrap .wpatt-option:has(> .wpatt-option-head input:checked) { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
        .wpatt-settings-wrap .wpatt-option-head { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .wpatt-settings-wrap .wpatt-option-head input[type="radio"] { margin: 0; }
        .wpatt-settings-wrap .wpatt-option-title { font-weight: 600; }
        .wpatt-settings-wrap .wpatt-option-icons { display: inline-flex; gap: 6px; align-items: center; margin-left: auto; }
        .wpatt-settings-wrap .wpatt-option-icons img, .wpatt-settings-wrap .wpatt-option-icons .wpa-icon { display: block; width: 16px; height: 16px; margin: 0; }
        .wpatt-settings-wrap .wpatt-option-desc { margin: 4px 0 0 26px; color: #646970; font-size: 12px; }
        .wpatt-settings-wrap .wpatt-suboptions { margin: 12px 0 0 26px; padding-top: 12px; border-top: 1px solid #f0f0f1; }
        .wpatt-settings-wrap .wpatt-suboptions .wpatt-option-desc { margin-left: 0; }
        .wpatt-settings-wrap .wpatt-field { display: grid; grid-template-columns: 96px minmax(0, 1fr); align-items: start; gap: 8px; min-height: 30px; }
        .wpatt-settings-wrap .wpatt-field-name { line-height: 30px; }
        .wpatt-settings-wrap .wpatt-field-controls { min-height: 30px; }
        .wpatt-settings-wrap .wpatt-field + .wpatt-field { margin-top: 6px; }
        .wpatt-settings-wrap .wpatt-field-name { font-weight: 600; }
        .wpatt-settings-wrap .wpatt-field-controls { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 18px; }
        .wpatt-settings-wrap .wpatt-field-controls label { display: inline-flex; align-items: center; gap: 6px; margin: 0; }
        .wpatt-settings-wrap .wpatt-field-controls input[type="radio"], .wpatt-settings-wrap .wpatt-field-controls input[type="checkbox"] { margin: 0; }
        .wpatt-settings-wrap .wpatt-color { width: 40px; height: 28px; padding: 2px; margin-left: -10px; border: 1px solid #8c8f94; border-radius: 4px; background: #fff; cursor: pointer; }
        .wpatt-settings-wrap .wpatt-hint { color: #646970; font-size: 12px; }
        .wpatt-settings-wrap .wpatt-preview { margin: 12px 0 0 26px; padding: 12px 14px; background: #f6f7f7; border-radius: 4px; font-size: 14px; }
        .wpatt-settings-wrap .wpatt-preview .wpa-attachments-block { margin: 0; }
        .wpatt-settings-wrap .wpatt-preview ul.wpa-cards li.post-attachment { background: #fff; }
        .wpatt-settings-wrap .wpatt-preview li { margin-bottom: 4px; }
        .wpatt-settings-wrap .wpatt-code { margin: 10px 0 0 26px; }
        .wpatt-settings-wrap .wpatt-code summary { cursor: pointer; color: #2271b1; font-size: 12px; }
        .wpatt-settings-wrap .wpatt-code code { display: block; margin: 8px 0; padding: 8px 10px; background: #f6f7f7; font-size: 12px; word-break: break-all; }
        .wpatt-settings-wrap .wpatt-suboptions textarea { display: block; max-width: none; }
        .wpatt-cpt-table { border-collapse: collapse; width: 100%; max-width: 600px; margin-top: 10px; background: #fff; border: 1px solid #ccd0d4; }
        .wpatt-cpt-table th, .wpatt-cpt-table td { text-align: left; padding: 10px; border-bottom: 1px solid #ccd0d4; }
        .wpatt-cpt-table th { background: #f6f7f7; font-weight: 600; }
        .wpatt-cpt-table th + th { text-align: center; }
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
                    <span class="wpatt-desc">' . esc_html__('Text displayed above the attachments list (e.g., "Downloads" or "Attachments"). Leave empty to hide the heading.', 'wp-attachments') . '</span>
                    <p class="wpatt-heading-tag">
                        <label for="wpatt_heading_tag">' . esc_html__('Heading level', 'wp-attachments') . '</label>
                        <select id="wpatt_heading_tag" name="wpatt_option_heading_tag_n">';
            foreach (array('h1', 'h2', 'h3', 'h4', 'h5', 'h6') as $tag) {
                echo '<option value="' . esc_attr($tag) . '" ' . selected(wpatt_heading_tag(), $tag, false) . '>' . esc_html(strtoupper($tag)) . '</option>';
            }
            echo '      </select>
                    </p>
                </td>
            </tr>';
            
            echo '<tr valign="top">
                <th scope="row">' . esc_html__('Display Options', 'wp-attachments') . '</th>
                <td>
                    <div class="wpatt-checkbox-group">
                        <label>
                            <input type="checkbox" name="wpatt_show_orderby_n" ' . (get_option('wpatt_show_orderby') == '1' ? 'checked' : '') . '/>
                            ' . esc_html__('Show the sort links above the list (Date, Name)', 'wp-attachments') . '
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
                        /* translators: %s: the WordPress date format, e.g. F j, Y */
                        esc_html__('Format for the %%DATE%% tag, following PHP date standards (e.g. d.m.Y). Leave empty to use the WordPress date format: %s', 'wp-attachments'),
                        '<code>' . esc_html(get_option('date_format')) . '</code>'
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
                    <p class="description">' . esc_html__('Configure where WP Attachments should be available.', 'wp-attachments') . '<br>' . esc_html__('"Enable Frontend" shows the list after the content. Each post can still hide it from the Media Attachments box of the editor.', 'wp-attachments') . '</p>
                    <table class="wpatt-cpt-table">
                        <thead>
                            <tr>
                                <th>' . esc_html__('Post Type', 'wp-attachments') . '</th>
                                <th>' . esc_html__('Enable Metabox', 'wp-attachments') . '</th>
                                <th>' . esc_html__('Enable Frontend', 'wp-attachments') . '</th>
                            </tr>
                        </thead>
                        <tbody>';
            $post_types = get_post_types(['public' => true], 'objects');
            foreach ($post_types as $post_type) {
                if ($post_type->name === 'attachment') continue;
                $mb_enabled = get_option('wpatt_enable_metabox_' . $post_type->name, '1');
                $fe_enabled = wpatt_is_frontend_enabled($post_type->name) ? '1' : '0';
                echo '<tr>
                    <td><strong>' . esc_html($post_type->labels->singular_name) . '</strong></td>
                    <td style="text-align:center;">
                        <input type="checkbox" name="wpatt_enable_' . esc_attr($post_type->name) . '" value="1" ' . checked($mb_enabled, '1', false) . ' />
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" name="wpatt_frontend_' . esc_attr($post_type->name) . '" value="1" ' . checked($fe_enabled, '1', false) . ' />
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
            echo '<table class="form-table" role="presentation">';

            /* ---------- Icon pack ---------- */
            echo '<tr>
                <th scope="row" id="wpatt-pack-heading">' . esc_html__('Icon Pack', 'wp-attachments') . '</th>
                <td>
                    <div class="wpatt-options" role="radiogroup" aria-labelledby="wpatt-pack-heading">';

            $icon_packs = [
                WPATT_PACK_MODERN => ['label' => 'Modern SVG', 'desc' => __('Sharp vector icons for every file type. Used by the Modern Card template too.', 'wp-attachments')],
                0 => ['label' => 'Fugue Icons', 'author' => 'Yusuke Kamiyamane'],
                1 => ['label' => 'Crystal Clear', 'author' => 'Everaldo Coelho', 'url' => 'https://www.everaldo.com/'],
                2 => ['label' => 'Diagona Icons', 'author' => 'Asher Abbasi'],
                3 => ['label' => 'Page Icons', 'author' => 'Matthew Skiles', 'url' => 'https://iconblock.com/'],
                // Dropped from this list in 5.0.12 by mistake: sites that had
                // picked it kept using it but saw no option selected here.
                4 => ['label' => 'Matrilineare'],
            ];
            foreach ($icon_packs as $val => $pack) {
                if (WPATT_PACK_MODERN === $val) {
                    // Same markup as the site, so the colour option shows here too.
                    $colors = wpatt_icons_color_attrs();
                    $icons  = '<span class="wpatt-option-icons' . $colors['class'] . '"'
                        . ($colors['style'] ? ' style="' . esc_attr($colors['style']) . '"' : '') . '>';
                    foreach (array('pdf', 'doc', 'sheet', 'archive') as $type) {
                        $icons .= '<span class="wpa-type-' . $type . '">' . wpatt_get_file_icon_svg($type, 'wpa-icon') . '</span>';
                    }
                    $icons .= '</span>';
                } else {
                    $icons = '<span class="wpatt-option-icons">';
                    foreach (array('document', 'document-word', 'document-pdf') as $png) {
                        $icons .= '<img src="' . esc_url(plugins_url('styles/' . $val . '/' . $png . '.png', dirname(__FILE__))) . '" alt="" width="16" height="16" />';
                    }
                    $icons .= '</span>';
                }

                if (isset($pack['desc'])) {
                    $meta = esc_html($pack['desc']);
                } elseif (isset($pack['author'])) {
                    $meta = esc_html__('Author', 'wp-attachments') . ': ' . (isset($pack['url']) ? '<a href="' . esc_url($pack['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($pack['author']) . '</a>' : esc_html($pack['author']));
                } else {
                    $meta = '';
                }

                echo '<div class="wpatt-option">
                    <label class="wpatt-option-head">
                        <input type="radio" value="' . esc_attr($val) . '" name="style" ' . checked(wpatt_icon_pack(), $val, false) . ' />
                        <span class="wpatt-option-title">' . esc_html($pack['label']) . '</span>' . $icons . '
                    </label>'
                    . ($meta ? '<p class="wpatt-option-desc">' . $meta . '</p>' : '');

                if (WPATT_PACK_MODERN === $val) {
                    // Outside the <label>: a label holding other controls would
                    // also tick the pack radio on every click.
                    $color_mode = wpatt_icons_color_mode();
                    echo '<div class="wpatt-suboptions">
                        <div class="wpatt-field" role="radiogroup" aria-labelledby="wpatt-color-heading">
                            <span class="wpatt-field-name" id="wpatt-color-heading">' . esc_html__('Icon colour', 'wp-attachments') . '</span>
                            <span class="wpatt-field-controls">
                                <label><input type="radio" name="wpatt_icons_color" value="type" ' . checked($color_mode, 'type', false) . ' /> ' . esc_html__('By file type', 'wp-attachments') . '</label>
                                <label><input type="radio" name="wpatt_icons_color" value="theme" ' . checked($color_mode, 'theme', false) . ' /> ' . esc_html__('Theme text colour', 'wp-attachments') . '</label>
                                <label><input type="radio" name="wpatt_icons_color" value="custom" ' . checked($color_mode, 'custom', false) . ' /> ' . esc_html__('Custom', 'wp-attachments') . '</label>
                                <input type="color" class="wpatt-color" name="wpatt_icons_custom_color" value="' . esc_attr(wpatt_icons_custom_color()) . '" aria-label="' . esc_attr__('Custom colour', 'wp-attachments') . '" />
                            </span>
                        </div>
                        <p class="wpatt-option-desc">' . esc_html__('Also applies to the Modern Card template, whatever the icon pack. Choose a custom colour dark enough to stand out against the page.', 'wp-attachments') . '</p>
                    </div>';
                }

                echo '</div>';
            }
            echo '</div></td></tr>';

            /* ---------- Display template ---------- */
            echo '<tr>
                <th scope="row" id="wpatt-template-heading">' . esc_html__('Display Template', 'wp-attachments') . '</th>
                <td>
                    <p class="description wpatt-intro">' . esc_html__('Previews use sample files and the saved settings.', 'wp-attachments') . '</p>
                    <div class="wpatt-options" role="radiogroup" aria-labelledby="wpatt-template-heading">';

            $card      = wpatt_get_card_options();
            $templates = [
                WPATT_TEMPLATE_CARD => [__('Modern Card', 'wp-attachments'), __('Always uses the Modern SVG icons.', 'wp-attachments')],
                0                   => [__('Simple List', 'wp-attachments'), ''],
                1                   => [__('List with date', 'wp-attachments'), ''],
                2                   => [__('Detailed List', 'wp-attachments'), ''],
            ];
            foreach ($templates as $val => $tpl) {
                $code = wpatt_get_template_string($val, $card);

                echo '<div class="wpatt-option">
                    <label class="wpatt-option-head">
                        <input type="radio" value="' . esc_attr($val) . '" name="template" ' . checked(wpatt_template_id(), $val, false) . ' />
                        <span class="wpatt-option-title">' . esc_html($tpl[0]) . '</span>
                    </label>'
                    . ($tpl[1] ? '<p class="wpatt-option-desc">' . esc_html($tpl[1]) . '</p>' : '');

                if (WPATT_TEMPLATE_CARD === $val) {
                    $flags = array(
                        'ext'       => __('Extension', 'wp-attachments'),
                        'size'      => __('Size', 'wp-attachments'),
                        'date'      => __('Date', 'wp-attachments'),
                        'downloads' => __('Downloads', 'wp-attachments'),
                        'caption'   => __('Caption', 'wp-attachments'),
                    );
                    echo '<div class="wpatt-suboptions">
                        <div class="wpatt-field" role="group" aria-labelledby="wpatt-card-show">
                            <span class="wpatt-field-name" id="wpatt-card-show">' . esc_html__('Show', 'wp-attachments') . '</span>
                            <span class="wpatt-field-controls">';
                    foreach ($flags as $key => $flag_label) {
                        echo '<label><input type="checkbox" name="wpatt_card[' . esc_attr($key) . ']" value="1" ' . checked($card[$key], 1, false) . ' /> ' . esc_html($flag_label) . '</label>';
                    }
                    echo '</span>
                        </div>
                        <div class="wpatt-field" role="radiogroup" aria-labelledby="wpatt-card-layout">
                            <span class="wpatt-field-name" id="wpatt-card-layout">' . esc_html__('Layout', 'wp-attachments') . '</span>
                            <span class="wpatt-field-controls">
                                <label><input type="radio" name="wpatt_card[layout]" value="grid" ' . checked($card['layout'], 'grid', false) . ' /> ' . esc_html__('Grid', 'wp-attachments') . '</label>
                                <label><input type="radio" name="wpatt_card[layout]" value="rows" ' . checked($card['layout'], 'rows', false) . ' /> ' . esc_html__('One per row', 'wp-attachments') . '</label>
                            </span>
                        </div>
                        <div class="wpatt-field">
                            <label class="wpatt-field-name" for="wpatt-card-width">' . esc_html__('Card width', 'wp-attachments') . '</label>
                            <span class="wpatt-field-controls">
                                <select id="wpatt-card-width" name="wpatt_card[width]">
                                    <option value="compact" ' . selected($card['width'], 'compact', false) . '>' . esc_html__('Compact', 'wp-attachments') . '</option>
                                    <option value="normal" ' . selected($card['width'], 'normal', false) . '>' . esc_html__('Normal', 'wp-attachments') . '</option>
                                    <option value="wide" ' . selected($card['width'], 'wide', false) . '>' . esc_html__('Wide', 'wp-attachments') . '</option>
                                </select>
                                <span class="wpatt-hint">' . esc_html__('Grid only. Downloads appear when the download counter is on.', 'wp-attachments') . '</span>
                            </span>
                        </div>
                    </div>';
                }

                // inert: the sample links must not be focusable or clickable.
                echo '<div class="wpatt-preview" inert aria-hidden="true">' . wpatt_render_template_preview($val) . '</div>
                    <details class="wpatt-code">
                        <summary>' . esc_html__('Template code', 'wp-attachments') . '</summary>
                        <code>' . esc_html($code) . '</code>
                        <button type="button" class="button button-small wpatt-use-template hide-if-no-js" data-code="' . esc_attr($code) . '">' . esc_html__('Use as custom template', 'wp-attachments') . '</button>
                    </details>
                </div>';
            }

            $custom = (string) get_option('wpa_template_custom');
            echo '<div class="wpatt-option">
                    <label class="wpatt-option-head">
                        <input type="radio" value="3" name="template" ' . checked(wpatt_template_id(), 3, false) . ' />
                        <span class="wpatt-option-title">' . esc_html__('Custom Template', 'wp-attachments') . '</span>
                    </label>
                    <div class="wpatt-suboptions">
                        <textarea id="wpa_template_custom" name="wpa_template_custom" rows="4" aria-label="' . esc_attr__('Custom Template', 'wp-attachments') . '">' . esc_textarea($custom) . '</textarea>
                        <p class="wpatt-option-desc">' . esc_html__('Tags:', 'wp-attachments') . ' <code>%URL%</code> <code>%TITLE%</code> <code>%SIZE%</code> <code>%DATE%</code> <code>%DOWNLOADS%</code> <code>%CAPTION%</code> <code>%DESCRIPTION%</code> <code>%AUTHOR%</code> <code>%EXT%</code> <code>%MIME%</code> <code>%ICON%</code></p>
                    </div>'
                    . ('' !== trim($custom) ? '<div class="wpatt-preview" inert aria-hidden="true">' . wpatt_render_template_preview(3) . '</div>' : '') . '
                </div>';

            echo '</div></td></tr>';
            echo '</table>';

            // Admin only: copy a template into the custom one; picking a colour
            // selects the Custom colour option.
            echo '<script>
document.addEventListener("click", function (e) {
    var button = e.target.closest(".wpatt-use-template");
    if (!button) { return; }
    var field = document.getElementById("wpa_template_custom");
    if (field.value.trim() !== "" && !window.confirm(' . wp_json_encode(__('Replace the current custom template?', 'wp-attachments')) . ')) { return; }
    field.value = button.getAttribute("data-code");
    document.querySelector("input[name=template][value=\'3\']").checked = true;
    field.focus();
});
document.addEventListener("input", function (e) {
    if (e.target.classList && e.target.classList.contains("wpatt-color")) {
        document.querySelector("input[name=wpatt_icons_color][value=custom]").checked = true;
    }
});
</script>';
            submit_button(__('Save Appearance Settings', 'wp-attachments'), 'primary', 'submit-appearance');
            break;
    }

    echo '</form>';
    echo '</div>';
}

?>
