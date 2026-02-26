<?php

if (!defined("ABSPATH")) {
  exit(); // Directe toegang blokkeren
}

// Voeg een custom cron schedule toe: elke minuut

add_filter("cron_schedules", function ($schedules) {
  $schedules["every_minute"] = [
    "interval" => 60,
    "display" => __("Elke minuut"),
  ];
  return $schedules;
});


// Koppel de cron actie aan de handler
add_action(
  "whatsapp_paras_send_upcoming_events",
  "whatsapp_paras_check_and_send_events",
);

/**
 * Handler: events en lineups verzenden.
 */
function whatsapp_paras_check_and_send_events()
{
  $now = current_time("timestamp");

  /** === EVENTS VERZENDEN === */

  if (get_option("whatsapp_send_events_enabled", "1") === "1") {
    $window_start = $now - 15 * MINUTE_IN_SECONDS;
    $window_end = $now + 5 * DAY_IN_SECONDS;

    $args = [
      "post_type" => "sp_event",
      "post_status" => ["future", "publish"],
      "posts_per_page" => -1,
      "orderby" => "date",
      "order" => "ASC",
      "date_query" => [
        [
          "column" => "post_date",
          "after" => date("Y-m-d H:i:s", $window_start),
          "before" => date("Y-m-d H:i:s", $window_end),
          "inclusive" => true,
        ],
      ],
      "meta_query" => [
        ["key" => "_whatsapp_event_sent", "compare" => "NOT EXISTS"],
      ],
    ];

    $events = get_posts($args);

    $chatId =
      get_option("whatsapp_manual_event_chatId", "") ?:
      get_option("whatsapp_event_chatId", "");
    $description = get_option(
      "whatsapp_event_description_send",
      "Standaard event beschrijving",
    );

    foreach ($events as $event) {
      // Sla over als event private of wachtwoordbeveiligd is
      if ($event->post_status === "private" || !empty($event->post_password)) {
        error_log(
          "Event overgeslagen (private of wachtwoord): ID {$event->ID}",
        );
        continue;
      }
      if (!Waha_Helpers::is_event_active($event->ID)) {
        error_log("Event overgeslagen (sp_status ≠ ok): ID {$event->ID}");
        continue;
      }
      $name = html_entity_decode(
        $event->post_title,
        ENT_QUOTES | ENT_HTML5,
        "UTF-8",
      );
      $startTime = strtotime($event->post_date);

      $terms = get_the_terms($event->ID, "sp_venue");
      if ($terms && !is_wp_error($terms)) {
        $locations = [];
        $addresses = [];
        foreach ($terms as $term) {
          $locations[] = $term->name;
          $address = Waha_Helpers::get_sp_address($term->term_id);
          if ($address) {
            $addresses[] = $address;
          }
        }
        $location_names = implode(", ", $locations);
        $location_addresses = implode(", ", $addresses);
      } else {
        $location_names = "Locatie niet beschikbaar";
        $location_addresses = "";
      }

      if (!empty($location_addresses)) {
        $location = $location_names . " (" . $location_addresses . ")";
      } else {
        $location = $location_names;
      }

      $response = WA_Service::send_whatsapp_event(
        $event->ID,
        $chatId,
        $name,
        $description,
        $startTime,
        $location,
      );

      if (!is_wp_error($response)) {
        Waha_Helpers::update_meta($event->ID, "_whatsapp_event_sent", 1);
        error_log("✅ Event verzonden en gemarkeerd: ID {$event->ID}");
      } else {
        error_log(
          "❌ Event verzenden mislukt voor ID {$event->ID}: " .
            $response->get_error_message(),
        );
      }
    }
  }

  $now = current_time("timestamp");

  /** === LINEUPS VERZENDEN === */

  if (get_option("whatsapp_send_lineup_enabled", "1") === "1") {
    $lineup_args = [
      "post_type" => "sp_event",
      "post_status" => ["future", "publish"],
      "posts_per_page" => -1,
    ];

    $lineup_events = get_posts($lineup_args);

    foreach ($lineup_events as $event) {
      $event_date = strtotime($event->post_date);
      $seconds_to_event = $event_date - $now;

      // === Check sp_status van het event

      if (!Waha_Helpers::is_event_active($event->ID)) {
        continue;
      }

      // === 3 dagen vooraf: tussen 72u en 96u vooraf

      if (
        $seconds_to_event >= 3 * DAY_IN_SECONDS &&
        $seconds_to_event < 4 * DAY_IN_SECONDS
      ) {
        $already_sent = get_post_meta($event->ID, "_lineup_sent_3", true);
        if (!$already_sent) {
          $result = whatsapp_fc_paras_send_lineup_message($event->ID, 3);
          if (!is_wp_error($result)) {
            Waha_Helpers::update_meta($event->ID, "_lineup_sent_3", 1);
            error_log(
              "✅ Lineup verzonden voor event {$event->ID} (3 dagen vooraf).",
            );
          } else {
            error_log(
              "❌ Fout bij lineup verzenden voor event {$event->ID} (3 dagen): " .
                $result->get_error_message(),
            );
          }
        }
      }

      // === 1 dag vooraf: minder dan 24u maar nog in de toekomst

      if ($seconds_to_event > 0 && $seconds_to_event <= DAY_IN_SECONDS) {
        $already_sent = get_post_meta($event->ID, "_lineup_sent_1", true);
        if (empty($already_sent)) {
          $result = whatsapp_fc_paras_send_lineup_message($event->ID, 1);
          if (!is_wp_error($result)) {
            Waha_Helpers::update_meta($event->ID, "_lineup_sent_1", 1);
            error_log(
              "✅ Lineup verzonden voor event {$event->ID} (1 dag vooraf).",
            );
          } else {
            error_log(
              "❌ Fout bij lineup verzenden voor event {$event->ID} (1 dag): " .
                $result->get_error_message(),
            );
          }
        }
      }

      // === Markeer als te laat voor 3d als het moment voorbij is en er nog niets is verstuurd

      if (
        $seconds_to_event < 3 * DAY_IN_SECONDS &&
        !get_post_meta($event->ID, "_lineup_sent_3", true)
      ) {
        Waha_Helpers::update_meta($event->ID, "_lineup_sent_3", "too_late");
        error_log(
          "⚠️ Lineup voor {$event->ID} werd te laat verstuurd voor 3 dagen vooraf.",
        );
      }
    }
  }
}
/**
 * Verstuur automatisch event info 5 dagen vooraf naar een specifieke groep.
 */
function whatsapp_paras_send_event_5days_before($force_test = false)
{
  $force_test = false;
  $now = current_time("timestamp");

  $args = [
    "post_type" => "sp_event",
    "post_status" => ["future", "publish"],
    "posts_per_page" => -1,
    "orderby" => "date",
    "order" => "ASC",
  ];

  $events = get_posts($args);

  $chatId = get_option("whatsapp_5days_chatId", ""); // admin-optie

  foreach ($events as $event) {
    $event_time = strtotime($event->post_date);
    $seconds_to_event = $event_time - $now;

    // ±1 uur marge voor 5 dagen vooraf
    if (
      $force_test ||
      ($seconds_to_event <= 5 * DAY_IN_SECONDS && $seconds_to_event > 0)
    ) {
      // Check of bericht al verzonden
      if (get_post_meta($event->ID, "_whatsapp_5days_sent", true)) {
        continue;
      }

      // Check status
      if (!Waha_Helpers::is_event_active($event->ID)) {
        continue;
      }

      // Titel & tijd
      $name = html_entity_decode(
        $event->post_title,
        ENT_QUOTES | ENT_HTML5,
        "UTF-8",
      );
      $startTime = $event_time;
      $date_event = date_i18n("d-m-Y", $startTime); // mooi geformatteerd

      // Locatie en adres
      $terms = get_the_terms($event->ID, "sp_venue");
      if ($terms && !is_wp_error($terms)) {
        $locations = [];
        $addresses = [];

        foreach ($terms as $term) {
          $locations[] = $term->name;

          // Gebruik function_exists() check of fallback om fatal errors te vermijden
          if (function_exists("get_sp_address")) {
            $address = get_sp_address($term->term_id);
          } else {
            $option_key = "taxonomy_" . $term->term_id;
            $venue_data = get_option($option_key);
            $address = !empty($venue_data["sp_address"])
              ? $venue_data["sp_address"]
              : "";
          }

          if ($address) {
            $addresses[] = $address;
          }
        }

        $location_names = implode(", ", $locations);
        $location_addresses = implode(", ", $addresses);

        // Één keer $location instellen
        $location = !empty($location_addresses)
          ? $location_names . " (" . $location_addresses . ")"
          : $location_names;
      } else {
        $location = "Locatie niet beschikbaar";
      }

      // Starttijd formatteren
      $starttime_formatted = date_i18n("H:i", $event_time);

      // Link naar event
      $event_link = get_permalink($event->ID);

      // Beschrijving
      $text = "Aankomende wedstrijd: {$name}\n";
      $text .= "Datum: {$date_event}\n";
      $text .= "Start van de match: {$starttime_formatted}\n";
      $text .= "Voetballocatie: {$location}\n";
      $text .= "Volg live via: {$event_link}";

      // Verstuur bericht via bestaande functie
      $wa = new WA_Service();
      $response = $wa->send_text($chatId, $text);

      if (!is_wp_error($response)) {
        Waha_Helpers::update_meta($event->ID, "_whatsapp_5days_sent", 1);
        error_log(
          "✅ Automatisch 5 dagen vooraf eventbericht verzonden voor ID {$event->ID}",
        );
      }
      break; // STOP na eerste match
    }
  }
}


// Voeg nieuwe cron actie toe
add_action('init', function() {
    if (!wp_next_scheduled("whatsapp_paras_send_event_5days")) {
        wp_schedule_event(time(), "every_minute", "whatsapp_paras_send_event_5days");
    }
    if (!wp_next_scheduled("whatsapp_paras_send_upcoming_events")) {
        wp_schedule_event(time(), "every_minute", "whatsapp_paras_send_upcoming_events");
    }
    if (!wp_next_scheduled('paras_check_halftime_cron')) {
        wp_schedule_event(time(), 'every_minute', 'paras_check_halftime_cron');
    }
    if (!wp_next_scheduled('paras_check_second_half_cron')) {
        wp_schedule_event(time(), 'every_minute', 'paras_check_second_half_cron');
    }
});
