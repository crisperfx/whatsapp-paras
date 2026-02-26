<?php
// Shortcode voor wissel UI
add_shortcode('sp_lineup_switcher', 'render_lineup_switcher_ui');
function render_lineup_switcher_ui() {
    if ( ! current_user_can('edit_sp_events') ) return 'Je hebt geen toegang.';

    global $post;
    if ( get_post_type($post) !== 'sp_event' ) return '';

    $event_id = $post->ID;
    $players = get_post_meta($event_id, 'sp_players', true);
    $team_id = Waha_Options::get_team_id();
    if ( ! is_array($players) || empty($players) ) return 'Geen spelers gevonden.';

    if ( ! isset($players[$team_id]) || ! is_array($players[$team_id]) ) return 'Geen spelers gevonden voor team ' . $team_id . '.';
    $team_players = $players[$team_id];

    $lineups = [];
    $subs = [];

    foreach ($team_players as $player_id => $data) {
        if ( isset($data['status']) ) {
            if ($data['status'] === 'lineup') {
                $lineups[$player_id] = $data;
            } elseif ($data['status'] === 'sub') {
                $subs[$player_id] = $data;
            }
        }
    }

    if ( empty($lineups) ) return 'Geen line-up spelers gevonden.';

    ob_start();
    ?>
    <form method="post">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr><th>Line-up Speler</th><th>Wissel met</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lineups as $lineup_id => $lineup_data): ?>
                <tr>
                    <td><?php echo esc_html( get_the_title($lineup_id) ); ?></td>
                    <td>
                        <select name="substitutions[<?php echo esc_attr($lineup_id); ?>]">
                            <option value="">-- Kies reservespeler --</option>
                            <?php foreach ($subs as $sub_id => $sub_data): ?>
                                <option value="<?php echo esc_attr($sub_id); ?>"><?php echo esc_html( get_the_title($sub_id) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php wp_nonce_field('sp_save_substitutions', 'sp_sub_nonce'); ?>
        <p><input type="submit" name="save_subs" class="button-primary" value="Opslaan"></p>
    </form>
    <?php
    return ob_get_clean();
}

// Formulierverwerking op frontend
add_action('template_redirect', 'handle_sp_lineup_switch');
function handle_sp_lineup_switch() {
    if ( ! is_singular('sp_event') ) return;
    if ( ! current_user_can('edit_sp_events') ) return;
    if ( ! isset($_POST['save_subs']) || ! isset($_POST['sp_sub_nonce']) ) return;
    if ( ! wp_verify_nonce($_POST['sp_sub_nonce'], 'sp_save_substitutions') ) return;

    global $post;
    $event_id = $post->ID;
    $substitutions = $_POST['substitutions'] ?? [];
	$team_id = Waha_Options::get_team_id();
    $players = get_post_meta($event_id, 'sp_players', true);
    if (!is_array($players) || empty($players)) return;

    if (!isset($players[$team_id]) || !is_array($players[$team_id])) return;

    $team_players = $players[$team_id];

    // Haal sp_timeline op of zet als lege array
    $timeline = get_post_meta($event_id, 'sp_timeline', true);
    if (!is_array($timeline)) {
        $timeline = [];
    }
    if (!isset($timeline[$team_id]) || !is_array($timeline[$team_id])) {
        $timeline[$team_id] = [];
    }

$match_time = Waha_Helpers::get_match_time($event_id);
$minutes_passed = $match_time['minutes']; // of met seconden


foreach ($substitutions as $lineup_id => $new_sub_id) {
    if (!$new_sub_id) continue;

    // Alleen de sub speler aanpassen: status blijft 'sub', maar we voegen de ID van de line-up speler toe
    $players[$team_id][$new_sub_id]['status'] = 'sub';
    $players[$team_id][$new_sub_id]['sub'] = intval($lineup_id);

    // Timeline voor de speler die het veld opkomt (sub)
    if (!isset($timeline[$team_id][$new_sub_id])) {
        $timeline[$team_id][$new_sub_id] = [];
    }
    $timeline[$team_id][$new_sub_id]['sub'] = [
        0 => (string)$minutes_passed
    ];
}



    // Bewaar nieuwe meta
    update_post_meta($event_id, 'sp_players', $players);
    update_post_meta($event_id, 'sp_timeline', $timeline);

    wp_safe_redirect(get_permalink($event_id));
    exit;
}

function sportspress_output_event_lineup_switcher() {
    echo do_shortcode('[sp_lineup_switcher]');
}

add_filter('sportspress_after_event_template', function($sections) {
    if (current_user_can('edit_sp_events')) {
        $sections['lineup_switcher'] = array(
            'title'   => esc_html__('Wissels', 'textdomain'),
            'option'  => 'sportspress_event_show_lineup_switcher',
            'action'  => 'sportspress_output_event_lineup_switcher',
            'default' => 'yes',
        );
    }
    return $sections;
});