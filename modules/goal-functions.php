<?php
/**
 * Class and Function List:
 * Function list:
 * - whatsapp_send_goals_on_change()
 * Classes list:
 */

function whatsapp_send_goals_on_change($post_ID, $post, $update)
{
    $title = get_the_title($post_ID);
    $event_date = get_post_datetime(
        $post,
        "date",
        wp_timezone_string()
    )->format("Y-m-d");
    $today_date = current_datetime()->format("Y-m-d");

    Waha_helpers::wedstrijd_debug("=== SEND SCORES LIVE ===");
    Waha_helpers::wedstrijd_debug("ID match: $post_ID");
    Waha_helpers::wedstrijd_debug("Titel: $title");

    // Stop als post niet bestaat of niet van het juiste type is
    if (!$post || $post->post_type !== "sp_event") {
        Waha_Helpers::wedstrijd_debug(
            "Geen type-wedstrijd, geen live-score verzonden."
        );
        return;
    }
    // Stop als post private of wachtwoordbeveiligd is
    if ($post->post_status === "private" || !empty($post->post_password)) {
        Waha_Helpers::wedstrijd_debug(
            "Wedstrijd staat prive of heeft een wachtwoord."
        );
        return;
    }
    // Stop als post niet gepubliceerd is
    if ($post->post_status !== "publish") {
        Waha_Helpers::wedstrijd_debug(
            "Niet gepubliceert, geen live-score verzonden."
        );
        return;
    }

    // Extra check: alleen verzenden als sp_status = ok
    $status = get_post_meta($post_ID, "sp_status", true);
    if (!((is_array($status) && in_array("ok", $status)) || $status === "ok")) {
        Waha_Helpers::wedstrijd_debug(
            "Wedstrijd heeft geen sp_status = ok, geen live-score verzonden."
        );
        return;
    }

    // CHECK: Enkel op de dag zelf
    if ($event_date !== $today_date) {
        Waha_Helpers::wedstrijd_debug(
            "Datum $event_date is niet vandaag ($today_date), geen live-score verzonden."
        );
        return;
    }

    if (!$update) {
        return;
    }
    $match_time = Waha_Helpers::get_match_time($post_ID);

    // Check: alleen verzenden als match gestart is
    $started = (int) get_post_meta($post_ID, "_match_started_at", true);
    $paused = (int) get_post_meta($post_ID, "_match_paused_at", true);
    $stopped = (int) get_post_meta($post_ID, "_match_stopped_at", true);

    if (!$started) {
        Waha_Helpers::wedstrijd_debug(
            "Match nog niet gestart, geen live-score."
        );
        return;
    }

    if ($paused) {
        Waha_Helpers::wedstrijd_debug("Match in pauze, geen live-score.");
        return;
    }

    if ($stopped) {
        Waha_Helpers::wedstrijd_debug("Match gestopt, geen live-score.");
        return;
    }
    if (!Waha_Options::is_whatsapp_send_enabled()) {
        Waha_Helpers::wedstrijd_debug("Verzenden van scores uitgeschakeld");
        return;
    }
    // Verzendlogica uitvoeren
    $old_data = get_post_meta($post_ID, "_sp_players_old", true);
    $new_data = isset($_POST["sp_players"])
        ? $_POST["sp_players"]
        : get_post_meta($post_ID, "sp_players", true);

    if (empty($old_data)) {
        update_post_meta($post_ID, "_sp_players_old", $new_data);
        Waha_Helpers::wedstrijd_debug(
            "Old data was empty, saved new data and returning."
        );
        return;
    }

    if ($old_data === $new_data) {
        Waha_Helpers::wedstrijd_debug(
            "Old data equals new data, nothing changed."
        );
        return;
    }

    // Verwerk de nieuwe sp_players data
    if (isset($new_data[0])) {
        $new_data = $new_data[0];
    }

    $goal_scorers = [];
    $yellow_cards = [];
    $red_cards = [];
    $team_goals = [];
    $team_id = Waha_Options::get_team_id();

    foreach ($new_data as $team_id => $players) {
        $team_goals[$team_id] = 0;

        foreach ($players as $player_id => $stats) {
            if ((int) $player_id === 0 || !is_array($stats)) {
                continue;
            }

            $player_name = get_the_title($player_id);

            // Goals
            if (!empty($stats["goals"]) && is_numeric($stats["goals"])) {
                $goals = (int) $stats["goals"];
                $team_goals[$team_id] += $goals;

                if ($team_id == $team_id && $goals > 0) {
                    $goal_scorers[] = "$player_name ($goals)";
                }
            }

            // Gele kaarten
            if (
                !empty($stats["gelekaart"]) &&
                is_numeric($stats["gelekaart"])
            ) {
                $yellow_cards[] = "$player_name ({$stats["gelekaart"]} x)";
            }

            // Rode kaarten
            if (
                !empty($stats["rodekaart"]) &&
                is_numeric($stats["rodekaart"])
            ) {
                $red_cards[] = "$player_name ({$stats["rodekaart"]})";
            }
        }

        // Controleer placeholder [0] ook voor goals
        if (isset($players[0]["goals"]) && is_numeric($players[0]["goals"])) {
            $team_goals[$team_id] += (int) $players[0]["goals"];
        }
    }

    if (count($team_goals) !== 2) {
        Waha_Helpers::wedstrijd_debug("Invalid number of teams, returning.");
        return;
    }

    $team_ids = array_keys($team_goals);
    $home_team_id = $team_ids[0];
    $away_team_id = $team_ids[1];
    $home_goals = $team_goals[$home_team_id];
    $away_goals = $team_goals[$away_team_id];
    $home_team_name = html_entity_decode(
        get_the_title($home_team_id),
        ENT_QUOTES | ENT_HTML5,
        "UTF-8"
    );
    $away_team_name = html_entity_decode(
        get_the_title($away_team_id),
        ENT_QUOTES | ENT_HTML5,
        "UTF-8"
    );

    $event_title = html_entity_decode(
        get_the_title($post_ID),
        ENT_QUOTES | ENT_HTML5,
        "UTF-8"
    );

    $custom_text = "⚽ Score & update: LIVE! ⚽\n";
    $custom_text .= "$home_team_name  VS  $away_team_name\n";
    $custom_text .= "$home_goals  -  $away_goals\n";

    if (!empty($goal_scorers)) {
        $custom_text .=
            "🥅 Doelpuntenmakers:\n- " . implode("\n- ", $goal_scorers) . "\n\n";
    }
    if (!empty($yellow_cards)) {
        $custom_text .=
            "🟨 Gele kaarten:\n- " . implode("\n- ", $yellow_cards) . "\n\n";
    }

    if (!empty($red_cards)) {
        $custom_text .=
            "🟥 Rode kaarten:\n- " . implode("\n- ", $red_cards) . "\n";
    }

    $wa = new WA_Service();
    $chatId = get_option("whatsapp_chatId", "");
    $result = $wa->send_text($chatId, $custom_text);

    if (is_wp_error($result)) {
        Waha_Helpers::wedstrijd_debug(
            "Whatsapp send error: " . $result->get_error_message()
        );
    } else {
        Waha_Helpers::wedstrijd_debug(
            "Whatsapp send success: " . wp_remote_retrieve_body($result)
        );
    }

    update_post_meta($post_ID, "_sp_players_old", $new_data);
}
add_action("save_post_sp_event", "whatsapp_send_goals_on_change", 10, 3);
