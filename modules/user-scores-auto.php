<?php
/**
 * Auto approve user scores:
 * Auto invulles goals first en second half
 * - Optellen bij sp_players
 * - Actie toevoegen aan sp_timeline met minuten vanaf aftrap
 * - sp_user_scores cleanen & POST leeghouden zodat de template niks opslaat
 */

if (!defined('ABSPATH')) exit;

add_action('template_redirect', function() {
	    global $post;

    // --- Admin & optie check ---
    if (!$post) return; // geen post, skip

    // Check of auto user scores aanstaan
    if (!Waha_Options::is_auto_user_scores_enabled()) {
        Waha_Helpers::wedstrijd_debug("Auto user scores zijn uitgeschakeld via opties. Event {$post->ID} skipped.");
        return;
    }
  if (
    isset($_POST['sp_user_scores']) &&
    wp_verify_nonce($_POST['sp_user_scores'], 'submit_score') &&
    isset($_POST['sp_scores'])
  ) {
    $post_id = get_the_ID();

    $sp_players = (array) get_post_meta($post_id, 'sp_players', true);
    $sp_timeline = (array) get_post_meta($post_id, 'sp_timeline', true);
    $user_scores = (array) get_post_meta($post_id, 'sp_user_scores', true);

$match_time = Waha_Helpers::get_match_time($post_id);
$minuut = $match_time['minutes']; // of met seconden

Waha_Helpers::wedstrijd_debug("DEBUG Event $post_id: minuut=$minuut (wedstrijdklok)");

    if (isset($_POST['sp_scores']) && is_array($_POST['sp_scores'])) {
  foreach ($_POST['sp_scores'] as $player_id => $stats) {
      // Zoek het juiste team_id voor deze speler
      $team_id = null;
      foreach ($sp_players as $tid => $players) {
        if (array_key_exists($player_id, $players)) {
          $team_id = $tid;
          break;
        }
      }

      if (!$team_id) {
        Waha_Helpers::wedstrijd_debug("Speler $player_id niet gevonden in sp_players. Overslaan.");
        continue;
      }

      if (!isset($sp_players[$team_id][$player_id])) {
        $sp_players[$team_id][$player_id] = [];
      }
      if (!isset($sp_timeline[$team_id][$player_id])) {
        $sp_timeline[$team_id][$player_id] = [];
      }

      foreach (['goals', 'gelekaart', 'rodekaart'] as $stat_key) {
        $new_val = isset($stats[$stat_key]) ? trim($stats[$stat_key]) : '0';

        if ($new_val === '' || intval($new_val) === 0) continue;

        $old_val = isset($sp_players[$team_id][$player_id][$stat_key]) && is_numeric($sp_players[$team_id][$player_id][$stat_key])
          ? intval($sp_players[$team_id][$player_id][$stat_key])
          : 0;
        $sp_players[$team_id][$player_id][$stat_key] = $old_val + intval($new_val);

        if (!isset($sp_timeline[$team_id][$player_id][$stat_key])) {
          $sp_timeline[$team_id][$player_id][$stat_key] = [];
        }

        for ($i = 0; $i < intval($new_val); $i++) {
          $sp_timeline[$team_id][$player_id][$stat_key][] = (string) $minuut;
        }

        Waha_Helpers::wedstrijd_debug(" [$player_id] $stat_key +$new_val => totaal: {$sp_players[$team_id][$player_id][$stat_key]} | timeline: " . print_r($sp_timeline[$team_id][$player_id][$stat_key], true));
      }
    }

    update_post_meta($post_id, 'sp_players', $sp_players);
    update_post_meta($post_id, 'sp_timeline', $sp_timeline);

    // Wis user_scores meta
    delete_post_meta($post_id, 'sp_user_scores');

    // Wis POST om template tegen te houden
    unset($_POST['sp_scores'], $_POST['sp_user_scores']);

    wp_update_post(['ID' => $post_id]);

    Waha_Helpers::wedstrijd_debug("[AUTO] Geslaagd: sp_players & sp_timeline bijgewerkt, POST gewist.");
    
    wp_safe_redirect(get_permalink($post_id));
exit;
  }
}});

//** Goals invullen in veld 'firsthalf'

// Eerste helft
function paras_update_halftime_score_func($event_id) {

    $sp_players = get_post_meta($event_id, 'sp_players', true);
    $sp_results = get_post_meta($event_id, 'sp_results', true);

    if (!is_array($sp_players) || empty($sp_players)) return;
    if (!is_array($sp_results)) $sp_results = [];

    foreach ($sp_players as $team_id => $players) {

        // ✅ Als firsthalf al bestaat (ook al is het "0") → NIET overschrijven
        if (
            isset($sp_results[$team_id]['firsthalf']) &&
            $sp_results[$team_id]['firsthalf'] !== '' &&
            $sp_results[$team_id]['firsthalf'] !== null
        ) {
            Waha_Helpers::wedstrijd_debug("[Halftime] Event $event_id: Team $team_id heeft al firsthalf waarde ({$sp_results[$team_id]['firsthalf']}). Skip.");
            continue;
        }

        $goals = 0;
        foreach ($players as $player) {
            if (isset($player['goals']) && is_numeric($player['goals'])) {
                $goals += intval($player['goals']);
            }
        }

        if (!isset($sp_results[$team_id])) {
            $sp_results[$team_id] = [
                'firsthalf' => '',
                'secondhalf' => '',
                'goals' => '',
            ];
        }

        $sp_results[$team_id]['firsthalf'] = (string)$goals;

        Waha_Helpers::wedstrijd_debug("[Halftime Goals] Event $event_id: Team $team_id => firsthalf: $goals");
    }

    update_post_meta($event_id, 'sp_results', $sp_results);
}

add_action('paras_update_halftime_score', 'paras_update_halftime_score_func');

// 2de helft + outcome
if (!function_exists('paras_update_second_half_score_func')) {
    function paras_update_second_half_score_func($event_id) {
        $sp_players = get_post_meta($event_id, 'sp_players', true);
        $sp_results = get_post_meta($event_id, 'sp_results', true);

        if (!is_array($sp_players) || empty($sp_players)) return;
        if (!is_array($sp_results)) $sp_results = [];

        $totals = [];

        foreach ($sp_players as $team_id => $players) {
            if (!is_array($players)) continue;

            $total_goals = 0;
            foreach ($players as $player) {
                if (isset($player['goals']) && is_numeric($player['goals'])) {
                    $total_goals += intval($player['goals']);
                }
            }

            if (!isset($sp_results[$team_id])) {
                $sp_results[$team_id] = [
                    'firsthalf' => '',
                    'secondhalf' => '',
                    'goals' => '',
                ];
            }

            $first_half_goals = isset($sp_results[$team_id]['firsthalf']) && is_numeric($sp_results[$team_id]['firsthalf'])
                ? intval($sp_results[$team_id]['firsthalf'])
                : 0;

            $second_half_goals = $total_goals - $first_half_goals;

            $sp_results[$team_id]['secondhalf'] = (string)$second_half_goals;
            $sp_results[$team_id]['goals'] = (string)$total_goals;

            Waha_Helpers::wedstrijd_debug("[Second Half Goals] Event $event_id: Team $team_id => secondhalf: $second_half_goals, totaal: $total_goals");

            $totals[$team_id] = $total_goals;
        }

        // --- outcome berekenen ---
        if (!empty($totals)) {
            arsort($totals); // hoogste score eerst
            $top_score = reset($totals);
            $winners = array_keys($totals, $top_score);

            foreach ($totals as $team_id => $goals) {
                if (in_array($team_id, $winners) && count($winners) === 1) {
                    $sp_results[$team_id]['outcome'] = ['win'];
                } elseif (in_array($team_id, $winners) && count($winners) > 1) {
                    $sp_results[$team_id]['outcome'] = ['draw'];
                } else {
                    $sp_results[$team_id]['outcome'] = ['loss'];
                }
            }
        }

        update_post_meta($event_id, 'sp_results', $sp_results);

        // Trigger eventueel hooks van SP
        wp_update_post(['ID' => $event_id]);
        do_action('save_post', $event_id, get_post($event_id), true);
    }
}

add_action('paras_update_second_half_score', 'paras_update_second_half_score_func');