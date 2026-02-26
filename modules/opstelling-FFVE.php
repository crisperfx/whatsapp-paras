<?php

function render_formation_visibility_box($post) {
    $visible = get_post_meta($post->ID, 'formation_ve_visible', true);
    wp_nonce_field('save_formation_visibility', 'formation_visibility_nonce');
    ?>
    <label>
        <input type="checkbox" name="formation_ve_visible" value="1" <?php checked($visible, '1'); ?>>
        <?php _e('Opstelling publiek zichtbaar', 'textdomain'); ?>
    </label>
    <?php
}

// Opslaan
add_action('save_post_sp_event', function($post_id) {
    if (!isset($_POST['formation_visibility_nonce']) || 
        !wp_verify_nonce($_POST['formation_visibility_nonce'], 'save_formation_visibility')) {
        return;
    }
    $visible = isset($_POST['formation_ve_visible']) ? '1' : '0';
    update_post_meta($post_id, 'formation_ve_visible', $visible);
});
// Voeg knop toe aan zijbalk sp_event editor
add_action('post_submitbox_misc_actions', function() {
    global $post, $wpdb;
    if ($post->post_type !== 'sp_event') return;

    $formation_id = get_post_meta($post->ID, 'formation_ve_id', true);
    $selected_layout_id = get_post_meta($post->ID, 'formation_ve_layout_id', true);

    $layouts = $wpdb->get_results("SELECT layout_id, description FROM {$wpdb->prefix}daextvffve_layout ORDER BY description ASC");

    $url = admin_url('admin-ajax.php?action=generate_formation_ve&post_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce('generate_formation_ve_' . $post->ID));
    ?>
    <div class="misc-pub-section">
        <label for="formation-ve-layout"><strong>Opstelling-soort:</strong></label><br>
        <select id="formation-ve-layout" name="formation-ve-layout">
            <?php foreach ($layouts as $layout): ?>
                <option value="<?php echo esc_attr($layout->layout_id); ?>" <?php selected($selected_layout_id, $layout->layout_id); ?>>
                    <?php echo esc_html($layout->description); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <a href="#" class="button button-small" id="generate-formation-ve"
           data-url="<?php echo esc_url($url); ?>">
            <?php echo $formation_id ? 'Bijwerken' : 'Genereer'; ?>
        </a>
        <span id="formation-ve-status" style="margin-left:10px;"></span>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#generate-formation-ve').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var status = $('#formation-ve-status');
            btn.prop('disabled', true);
            status.text(' Bezig...');

            var layout = $('#formation-ve-layout').val();
            var url = btn.data('url') + '&layout_id=' + layout;

            $.get(url, function(response) {
                if (response.success) {
                    status.text(' Opstelling #' + response.data.id + (response.data.updated ? ' bijgewerkt' : ' aangemaakt'));
                } else {
                    status.text(' Fout: ' + (response.data || 'Onbekend'));
                }
                btn.prop('disabled', false);
            });
        });
    });
    </script>
    <?php
});


// AJAX handler koppelen
add_action('wp_ajax_generate_formation_ve', function() {
    $post_id = absint($_GET['post_id'] ?? 0);
    $nonce = $_GET['_wpnonce'] ?? '';
    $layout_id = absint($_GET['layout_id'] ?? 0);

    if ($layout_id > 0) {
        update_post_meta($post_id, 'formation_ve_layout_id', $layout_id);
    }

    $result = generate_formation_from_event($post_id, $layout_id);

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    } else {
        $was_existing = get_post_meta($post_id, 'formation_ve_id', true) == $result;
        wp_send_json_success(['id' => $result, 'updated' => $was_existing]);
    }
});


// Opstelling genereren of bijwerken
function generate_formation_from_event($event_id, $layout_id = 0) {
    global $wpdb;
if (!$layout_id) {
    $layout_id = get_post_meta($event_id, 'formation_ve_layout_id', true);
}
if (!$layout_id) {
    $layout_id = 1; // fallback default
}

    $formation_table = $wpdb->prefix . 'daextvffve_formation';
    $formation_id = get_post_meta($event_id, 'formation_ve_id', true);
	$team_id = Waha_Options::get_team_id();

    $sp_players_raw = get_post_meta($event_id, 'sp_players', true);
    if (!is_array($sp_players_raw) || !isset($sp_players_raw)) {
        return new WP_Error('no_players', 'Geen spelers gevonden.');
    }

    $team_data = $sp_players_raw;
    if (!isset($team_data[$team_id]) || !is_array($team_data[$team_id])) {
        return new WP_Error('no_team', 'Geen spelers voor het opgegeven team.');
    }

    $player_entries = $team_data[$team_id];
    $players = [];

    foreach ($player_entries as $player_id => $data) {
        if (!is_numeric($player_id)) continue;
        if (!isset($data['status']) || $data['status'] !== 'lineup') continue;

        $name = get_the_title($player_id);
        $number = get_post_meta($player_id, 'sp_number', true);
        $players[] = [
            'name' => $name ?: '',
            'number' => $number ?: '',
        ];
    }

    // Vul aan tot 11 posities
        // Vul aan tot 11 posities
    while (count($players) < 11) {
        $players[] = ['name' => '', 'number' => ''];
    }

    // Beperk tot 11 basisspelers
    $players = array_slice($players, 0, 11);

    // Zoek nu subs (max 4) uit dezelfde lijst
    $subs = [];
    foreach ($player_entries as $player_id => $data) {
        if (!is_numeric($player_id)) continue;
        if (!isset($data['status']) || $data['status'] !== 'sub') continue;

        $name = get_the_title($player_id);
        $number = get_post_meta($player_id, 'sp_number', true);
        $subs[] = [
            'name' => $name ?: '',
            'number' => $number ?: '',
        ];
        if (count($subs) >= 4) break;
    }

    $data = [
        'description' => get_the_title($event_id),
        'layout_id' => $layout_id,
    ];

    foreach ($players as $i => $player) {
        $data['player_name_' . ($i + 1)] = $player['name'];
        $data['player_number_' . ($i + 1)] = $player['number'];
    }
    foreach ($subs as $i => $sub) {
        $data['player_name_' . (12 + $i)] = $sub['name'];
        $data['player_number_' . (12 + $i)] = $sub['number'];
    }

    // Check of formatie echt bestaat
    $exists = $formation_id && $wpdb->get_var($wpdb->prepare("SELECT formation_id FROM $formation_table WHERE formation_id = %d", $formation_id));

    if ($exists) {
        $wpdb->update($formation_table, $data, ['formation_id' => $formation_id]);
    } else {
        $wpdb->insert($formation_table, $data);
        $formation_id = $wpdb->insert_id;
        update_post_meta($event_id, 'formation_ve_id', $formation_id);
    }

    return $formation_id;
}

add_filter('sportspress_after_event_template', function($sections) {
    $sections['formation_ve'] = array(
        'title'   => esc_attr__('Opstellingen', 'textdomain'),
        'option'  => 'sportspress_event_show_formation_ve',
        'action'  => 'sportspress_output_event_formation_ve',
        'default' => 'yes',
    );
    return $sections;
});


function sportspress_output_event_formation_ve() {
    global $post;

    $formation_id = get_post_meta($post->ID, 'formation_ve_id', true);
    $visible      = get_post_meta($post->ID, 'formation_ve_visible', true);

    if (!$formation_id) {
        echo '<p>' . __('Geen opstelling beschikbaar.', 'textdomain') . '</p>';
        return;
    }

    // Alleen admins zien altijd, anderen alleen als zichtbaar is aangevinkt
    if ($visible === '1' || current_user_can('publish_pages')) {
        echo do_shortcode('[visual-football-formation-ve id="' . intval($formation_id) . '"]');
    } else {
        echo '<p>' . __('De opstelling is nog niet beschikbaar.', 'textdomain') . '</p>';
    }
}

add_action('wp_footer', function() {
    if (!is_singular('sp_event')) return;
    ?>
    <script>
jQuery(document).ready(function($) {
    console.log('jQuery voor opstelling-tab geladen');

    $('ul.sp-tab-menu a').on('click', function() {
        var target = $(this).attr('href');
        console.log('Tab klik:', target);

        if (target === '#sp-tab-content-formation_ve') {
            setTimeout(function() {
                console.log('Opstelling-tab geopend Ã¢â€ â€™ trigger viewport fix');
                $(window).trigger('resize');
            }, 50); // Korte delay zodat .sp-tab-content eerst zichtbaar is
        }
    });

    // Extra check als de tab al actief is via hash of standaard
    if (window.location.hash === '#sp-tab-content-formation_ve') {
        console.log('Tab al actief via hash, resize getriggerd');
        setTimeout(function() {
            $(window).trigger('resize');
        }, 300);
    }
});
</script>
    <?php
});

