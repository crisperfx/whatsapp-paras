<?php

add_action('rest_api_init', function () {
    register_rest_route('waha-webhook/v1', '/handler', [
        'methods'             => 'POST',
        'callback'            => 'waha_webhook_handler',
        'permission_callback' => '__return_true',
    ]);
});

function waha_webhook_handler(WP_REST_Request $request)
{
    // Check of webhook aanstaat
    if (!Waha_Options::is_webhook_enabled()) {
    Waha_Helpers::waha_log("Webhook is uitgeschakeld.");
    return;
	}
    $payload = $request->get_json_params();

    // Event ID uit payload
    $full_id = $payload['payload']['eventCreationKey']['id'] ?? '';
    Waha_Helpers::waha_log("Volledige key van het event: $full_id");
    $parts = explode('_', $full_id);
    $whatsapp_event_id = $parts[2] ?? '';
    Waha_Helpers::waha_log("Effectieve event-key: $whatsapp_event_id");

    // Zoek sp_event
    $found_event_id = false;
    $query = new WP_Query([
        'post_type'      => 'sp_event',
        'posts_per_page' => -1,
        'post_status'    => 'any',
    ]);

    while ($query->have_posts()) {
        $query->the_post();
        $pid  = get_the_ID();
$post = get_post($pid); // haal WP_Post object
$title_match = html_entity_decode($post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $meta = get_post_meta($pid, '_whatsapp_event_id', true);
        $meta_array = is_array($meta) ? $meta : explode(',', trim($meta, '[]'));

        if (in_array($whatsapp_event_id, $meta_array, true)) {
            $found_event_id = $pid;
            break;
        }
    }
    wp_reset_postdata();

    if (!$found_event_id) {
        Waha_Helpers::waha_log("===Geen wedstrijd gevonden voor ID $whatsapp_event_id===");
        return;
    }

    $event_id = $found_event_id;
    Waha_Helpers::waha_log("Wedstrijd $title_match gevonden voor ID $event_id");

    // Haal participant uit payload (kan op meerdere plekken zitten)
    $participant = [];

    if (!empty($payload['payload']['_data']['Info']['SenderAlt'])) {
        $participant[] = $payload['payload']['_data']['Info']['SenderAlt'];
    }
    if (!empty($payload['payload']['participant'])) {
        $participant[] = $payload['payload']['participant'];
    }

    // Flatten array
    $participant = array_map(fn($v) => is_array($v) ? reset($v) : $v, $participant);

    // Bepaal status
    $status = strtoupper($payload['payload']['eventResponse']['response'] ?? '');
    switch ($status) {
        case 'GOING':
            $status_lower = 'going';
            break;
        case 'MAYBE':
        case 'NOT_GOING':
            $status_lower = 'not_going';
            break;
        default:
            $status_lower = '';
    }

    if (!$participant || !$status_lower) {
        Waha_Helpers::waha_log("===Ongeldige antwoord, kijk WAHA na!! ===");
    }

    if (is_array($participant)) {
        $participant = reset($participant);
    }

    // Zoek sp_player ID via linkedin of LIDwhatsapp meta
    $player_id = 0;
    $sp_player_query = new WP_Query([
        'post_type'      => 'sp_player',
        'posts_per_page' => 1,
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'   => 'linkedin',
                'value' => $participant,
            ],
            [
                'key'   => 'LIDwhatsapp',
                'value' => $participant,
            ],
        ],
    ]);

    if ($sp_player_query->have_posts()) {
        $sp_player_query->the_post();
        $player_id = get_the_ID();
    }
    wp_reset_postdata();

    $player_name = get_the_title($player_id);

    if (!$player_id) {
        Waha_Helpers::waha_log("Geen speler gevonden met nummer $participant");
        return new WP_REST_Response(['status' => 'error', 'message' => 'Geen speler gevonden'], 404);
    }

    // Ã¢Å“â€¦ Altijd responses updaten
    $responses = get_post_meta($event_id, '_whatsapp_event_responses', true);
    if (!is_array($responses)) {
        $responses = [];
    }
    $responses[$player_id] = $status_lower;
    update_post_meta($event_id, '_whatsapp_event_responses', $responses);
    Waha_Helpers::waha_log("Aanwezigheid bijgewerkt: $player_name => $status_lower");

    // --------------------------
    // Ã¢Å“â€¦ NOT_GOING logica
    if ($status_lower === 'not_going') {
        $players = get_post_meta($event_id, 'sp_player', false);

        if (in_array($player_id, $players)) {
            $players = array_filter($players, fn($v) => $v != $player_id);
            delete_post_meta($event_id, 'sp_player');

            foreach ($players as $val) {
                add_post_meta($event_id, 'sp_player', $val);
            }

            Waha_Helpers::waha_log("$player_name is verwijderd uit sp_player.");
        } else {
            Waha_Helpers::waha_log("$player_name stond nog niet in de lijst.");
        }


    }

    // --------------------------
    // Ã¢Å“â€¦ GOING logica
    if ($status_lower === 'going') {
        $players = get_post_meta($event_id, 'sp_player', false);

        if (!in_array($player_id, $players)) {
            $our_team_id = Waha_Options::get_team_id();
$sp_players = get_post_meta($event_id, 'sp_players', true);
$our_team_position = null;

if (is_array($sp_players)) {
    $teams = array_keys($sp_players);   // Ã°Å¸â€˜Ë† DIT is de waarheid
    $teams = array_map('intval', $teams);

    $our_team_position = array_search((int) $our_team_id, $teams, true);
}

Waha_Helpers::waha_log('Onze team positie: ' . var_export($our_team_position, true));

            $zero_indices = [];
            foreach ($players as $i => $v) {
                if ($v == 0) {
                    $zero_indices[] = $i;
                }
            }

            if (count($zero_indices) >= 2) {
                $second_zero_index = $zero_indices[1];

                if ($our_team_position === 0) {
                    array_splice($players, $second_zero_index, 0, $player_id);
                } elseif ($our_team_position === 1) {
                    array_splice($players, $second_zero_index + 1, 0, $player_id);
                } else {
                    $players[] = $player_id;
                }
            } else {
                $players[] = $player_id;
            }

            delete_post_meta($event_id, 'sp_player');
            foreach ($players as $val) {
                add_post_meta($event_id, 'sp_player', $val);
            }

            Waha_Helpers::waha_log("$player_name is toegevoegd in sp_player.");
        } else {
            Waha_Helpers::waha_log("$player_name stond al in de lijst.");
        }


    }
}
;