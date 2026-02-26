<?php

function whatsapp_fc_paras_send_lineup_message($event_id, $days_before = 0) {

    $log_prefix = "[Event $event_id] ";

    // Check: is lineup verzenden ingeschakeld?
    if (get_option('whatsapp_send_lineup_enabled', '1') !== '1') {
        Waha_Helpers::info($log_prefix . 'Line-up verzenden is uitgeschakeld.');
        return false;
    }

    // Controleer of het event geldig is
    $event = get_post($event_id);
    if (!$event || $event->post_type !== 'sp_event') {
        Waha_Helpers::info($log_prefix . 'Ongeldig event ID.');
        return false;
    }

    // Check of de wedstrijdstatus wel "ok" is
    $status = get_post_meta($event_id, 'sp_status', true);
    if (!((is_array($status) && in_array('ok', $status)) || $status === 'ok')) {
        Waha_Helpers::info($log_prefix . 'Wedstrijd heeft geen status OK, lineup wordt niet verzonden.');
        return false;
    }

    // Check of deze lineup al verzonden is voor deze timing (3d of 1d)
    $meta_key = $days_before === 3 ? '_lineup_sent_3' : ($days_before === 1 ? '_lineup_sent_1' : '');
    if ($meta_key && get_post_meta($event_id, $meta_key, true)) {
        Waha_Helpers::info($log_prefix . "Lineup bericht al verzonden voor {$days_before} dagen voor het event.");
        return false;
    }

    // === Datum en locatie ===
    $event_date = date('d-m-Y H:i', strtotime($event->post_date));
    $terms = get_the_terms($event_id, 'sp_venue');
    $location = 'Locatie niet beschikbaar';

    if ($terms && !is_wp_error($terms)) {
        $locations = [];
        $addresses = [];

        foreach ($terms as $term) {
            $locations[] = $term->name;
            $venue_data = get_option('taxonomy_' . $term->term_id);
            if (!empty($venue_data['sp_address'])) $addresses[] = $venue_data['sp_address'];
        }

        $location = !empty($addresses) ? implode(', ', $addresses) : implode(', ', $locations);
    }

    // === Teams en tegenstander ===
    $sp_players = get_post_meta($event_id, 'sp_players', true);
    if (!is_array($sp_players) || empty($sp_players)) {
        Waha_Helpers::info($log_prefix . 'Geen spelersinformatie beschikbaar voor dit event.');
        return false;
    }

    $our_team_id = Waha_Options::get_team_id();
    $opponent_id = null;
    foreach (array_keys($sp_players) as $team_id) {
        if ($team_id != $our_team_id) {
            $opponent_id = $team_id;
            break;
        }
    }

    $opponent = 'Onbekende tegenstander';
    if ($opponent_id) {
        $opponent_post = get_post($opponent_id);
        if ($opponent_post && $opponent_post->post_type === 'sp_team') {
            $opponent = html_entity_decode(get_the_title($opponent_post), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    $teams_sanitized = array_map('strval', array_keys($sp_players));
    $wedstrijd_type = 'Onbekend';
    if (count($teams_sanitized) >= 2) {
        if ($teams_sanitized[0] === (string)$our_team_id) $wedstrijd_type = 'THUIS-wedstrijd';
        elseif ($teams_sanitized[1] === (string)$our_team_id) $wedstrijd_type = 'UIT-wedstrijd';
    }

    // === Spelerslijst bouwen ===
    $lineups = [];
    $subs = [];
    $oncall = [];
    $basis_count = 0;

    $team_players = $sp_players[$our_team_id];
    foreach ($team_players as $pid => $pdata) {
        if ($pid === 0) continue;

        $player_name  = get_the_title($pid);
        $shirt_number = $pdata['number'] ?? '';
        $status       = $pdata['status'] ?? '';

        $display = (!empty($shirt_number) ? '#'.$shirt_number.' | ' : '') . $player_name;

        if ($status === 'sub') $subs[] = $display;
        elseif ($status === 'lineup') {
            if ($basis_count < 11) { $lineups[] = $display; $basis_count++; }
            else $oncall[] = $display;
        }
    }

    $player_lines = [];
    if ($lineups) { $player_lines[] = "⚽ BASIS-11:"; foreach ($lineups as $l) $player_lines[] = "- " . $l; }
    if ($subs)    { $player_lines[] = "🔁 WISSELS:"; foreach ($subs as $s) $player_lines[] = "- " . $s; }
    if ($oncall)  { $player_lines[] = "🟠 ON-CALL:"; foreach ($oncall as $o) $player_lines[] = "- " . $o; }

    if (!$lineups || !$subs) {
        Waha_Helpers::lineup_log($log_prefix . 'Fallback naar sp_player array gebruikt.');
        $player_lines = ["⚽ Aanwezig:"];
        foreach (get_post_meta($event_id, 'sp_player', false) as $pid) {
            if ($pid == 0 || $pid == 5406) continue;
            $player_lines[] = "- " . get_the_title($pid);
        }
    }
	
	// Haal de algemene instructies op uit opties
	$lineup_instructions = Waha_Options::get_lineup_instructions();
	// === Bericht samenstellen ===
    $message = sprintf(
        "Datum: %s\n%s\n%s vs %s\n\nVoorlopige spelerslijst:\n%s\n\n%s",
        $event_date,
        $location,
        $wedstrijd_type,
        $opponent,
        implode("\n", $player_lines),
        $lineup_instructions
    );

    // === Verzenden via WA_Service ===
    $wa = new WA_Service();
    $chatId = get_option('whatsapp_lineup_chatId');
    $result = $wa->send_text($chatId, $message);
	
	if (is_array($result) && !empty($result['success'])) {
    	Waha_Helpers::update_meta($event_id, '_lineup_sent_3', 1);
	}
    if (is_wp_error($result)) {
        Waha_Helpers::lineup_log('WhatsApp verzenden mislukt: ' . $result->get_error_message());
        return false;
    }

    Waha_Helpers::lineup_log('Line-up verzonden voor ' . get_the_title($event_id));
    return $result;
}