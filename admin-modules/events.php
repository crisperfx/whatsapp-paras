<?php

function whatsapp_paras_events_page() {

	// Manueel lineup verzenden
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_lineup_event_id'])) {
		$post_id = intval($_POST['send_lineup_event_id']);
		$result  = whatsapp_fc_paras_send_lineup_message($post_id);

		echo '<div class="notice notice-info"><pre>';

    if (is_wp_error($result)) {
        echo 'Fout: ' . esc_html($response->get_error_message());
    } elseif (is_array($result) && !empty($result['success'])) {
        echo '✅ Lineup voor: ' . get_the_title($post_id) . " verzonden.\n";
        echo 'API message: ' . esc_html($result['message']) . "\n";
    } else {
        echo 'Onverwacht antwoord: ' . esc_html(json_encode($result, JSON_PRETTY_PRINT));
    }

    echo '</pre></div>';
	}

	// Verzenden van event met automatische beschrijving
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_event_id'])) {
		$post_id = intval($_POST['send_event_id']);
		$post    = get_post($post_id);

		if (!$post || $post->post_type !== 'sp_event') {
			echo '<div class="notice notice-error"><p>Ongeldig event ID.</p></div>';
		}
		else {
			if (get_option('whatsapp_send_events_enabled', '1') !== '1') {
				echo '<div class="notice notice-warning"><p>Verzenden van events is uitgeschakeld in de instellingen.</p></div>';
			}
			else {
				$manualChatId    = get_option('whatsapp_manual_event_chatId', '');
				$chatId          = !empty($manualChatId) ? $manualChatId : get_option('whatsapp_event_chatId', '32495675141:45@s.whatsapp.net');
				$name            = html_entity_decode($post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                $post_date = $post->post_date; // dit is al in WP tijdzone
                
				// Stel je tijdzone expliciet in
				$timezone_string = get_option('timezone_string') ? : 'Europe/Brussels';
				$timezone        = new DateTimeZone($timezone_string);

				// Haal datum/tijd op in WP tijdzone
				$datetime = new DateTime($post_date, $timezone);
                $startTime = $datetime->getTimestamp();

				// Maak een DateTime-object voor de aftrap
				$aftrap = clone $datetime;
                $aanwezig = clone $datetime;
                $aanwezig->modify('-1 hour');

				// Format
				$aftrap_str   = $aftrap->format('H\ui');
				$aanwezig_str = $aanwezig->format('H\ui');

				// Automatische beschrijving met extra vaste tekst
				$description  = "{$aanwezig_str} aanwezig | spelen {$aftrap_str}\n\n";
				$description .= "Identiteitskaart of rijbewijs mee!!\n";
				$description .= "Scheenlappen, zwarte kousen en schoenen mee!!\n";
				$description .= "Niks mee = geen wedstrijd ;)";

				function get_sp_venue_address($term_id) {
					// Probeer eerst normaal op te halen
					$address = get_term_meta($term_id, 'sp_address', true);

					// Als leeg, probeer optie te laden en deserializen
					if (empty($address)) {
						$option  = get_option('taxonomy_' . $term_id);
						if ($option) {
							$data    = maybe_unserialize($option);
							if (is_array($data) && isset($data['sp_address'])) {
								$address = $data['sp_address'];
							}
						}
					}

					return $address ? : '';
				}

				$terms              = get_the_terms($post_id, 'sp_venue');
				if ($terms && !is_wp_error($terms)) {
					$locations          = [];
					$addresses          = [];
					foreach ($terms as $term) {
						$locations[]                    = $term->name;
						$address            = get_sp_venue_address($term->term_id);
						if ($address) {
							$addresses[]                    = $address;
						}
					}
					$location_names     = implode(', ', $locations);
					$location_addresses = implode(', ', $addresses);
				}
				else {
					$location_names     = 'Locatie niet beschikbaar';
					$location_addresses = '';
				}

				if (!empty($location_addresses)) {
					$location           = $location_names . ' (' . $location_addresses . ')';
				}
				else {
					$location           = $location_names;
				}

				$response = WA_Service::send_whatsapp_event(
    $post_id,
    $chatId,
    $name,
    $description,
    $startTime,
    $location
);
				echo '<div class="notice notice-info"><pre>';

    if (is_wp_error($response)) {
        echo 'Fout: ' . esc_html($response->get_error_message());
    } elseif (is_array($response) && !empty($response['success'])) {
    	Waha_Helpers::update_meta($post_id, '_whatsapp_event_sent', 1);
        echo '✅ Event: ' . get_the_title($post_id) . " verzonden.\n";
        echo 'API message: ' . esc_html($response['message']) . "\n";
    } else {
        echo 'Onverwacht antwoord: ' . esc_html(json_encode($response, JSON_PRETTY_PRINT));
    }

    echo '</pre></div>';
			}
		}
	}

	// Reset verzendstatus events
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_send_event_id'])) {
		$reset_post_id = intval($_POST['reset_send_event_id']);
		if (current_user_can('manage_options') && get_post_type($reset_post_id) === 'sp_event') {
			delete_post_meta($reset_post_id, '_whatsapp_event_sent');
			echo '<div class="notice notice-success is-dismissible"><p>Verzendstatus voor event ID ' . esc_html($reset_post_id) . '  is gereset.</p></div>';
		}
	}
	// Reset verzendstatus lineup 3d en 1d
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_send_lineup_id'])) {
		$reset_post_id = intval($_POST['reset_send_lineup_id']);
		if (current_user_can('manage_options') && get_post_type($reset_post_id) === 'sp_event') {
			delete_post_meta($reset_post_id, '_lineup_sent_3');
			delete_post_meta($reset_post_id, '_lineup_sent_1');
			echo '<div class="notice notice-success is-dismissible"><p>Verzendstatus voor line-ups gereset. ' . esc_html($reset_post_id) . '  is gereset.</p></div>';
		}
	}
	// Opslaan manuele chatId
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_manual_event_chatId'])) {
		$chatId = sanitize_text_field($_POST['whatsapp_manual_event_chatId']);
		update_option('whatsapp_manual_event_chatId', $chatId);
		echo '<div class="notice notice-success is-dismissible"><p>Manuele chat ID succesvol opgeslagen!</p></div>';
	}

	// Events ophalen
	$args        = ['post_type'             => 'sp_event', 'posts_per_page'             => - 1, 'post_status'             => ['publish', 'future'], 'orderby'             => 'date', 'order'             => 'ASC', ];
	$all_events  = get_posts($args);

	$today_start = strtotime(date('Y-m-d 00:00:00', current_time('timestamp')));
	$events      = array_filter($all_events, function ($event) use ($today_start) {
		return strtotime($event->post_date) >= $today_start;
	});
?>

    <div class="wrap">
        <h1>Events Verzenden</h1>

    <form method="post" style="margin-bottom: 2em;">
        <h2>Nummer event</h2>
        <?php
        $chat_choices = array(
            '120363150944235207@g.us' => 'Spelersgroep',
            '120363348593759502@g.us' => 'Aankomende wedstrijden',
            '179323524931746@lid'     => 'Mezelf',
        );

        $manual_chatId = get_option('whatsapp_manual_event_chatId', '');
        ?>
        <select name="whatsapp_manual_event_chatId" id="whatsapp_manual_event_chatId">
            <?php foreach ($chat_choices as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($manual_chatId, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php submit_button('Opslaan', 'primary', 'save_manual_event_chatId'); ?>
    </form>

        <h2>Beschikbare Events</h2>
        <?php if (empty($events)): ?>
            <p>Geen events gevonden.</p>
        <?php
	else: ?>
            <table class="widefat fixed striped">
                <thead>
    <tr>
        <th>Titel</th>
        <th>Startdatum</th>
        <th>Locatie</th>
        <th style="text-align:center;">Event verzonden?</th>
        <th style="text-align:center;">Lineup 3d?</th>
        <th style="text-align:center;">Lineup 1d?</th>
        <th>Verzend event</th>
        <th>Spelerslijst</th>
        <th>Reset verzendstatus</th>
        <th>Reset lineup</th>
    </tr>
</thead>
<tbody>
<?php foreach ($events as $event):
			$is_sent_event    = get_post_meta($event->ID, '_whatsapp_event_sent', true) ? true : false;
			$is_sent_lineup_3 = get_post_meta($event->ID, '_lineup_sent_3', true) ? true : false;
			$is_sent_lineup_1 = get_post_meta($event->ID, '_lineup_sent_1', true) ? true : false;
?>
<tr>
    <td><?php echo esc_html($event->post_title); ?></td>
    <td><?php echo esc_html($event->post_date); ?></td>
    <td>
        <?php
			$terms     = get_the_terms($event->ID, 'sp_venue');
			if ($terms && !is_wp_error($terms)) {
				$locations = wp_list_pluck($terms, 'name');
				echo esc_html(implode(', ', $locations));
			}
			else {
				echo 'Locatie niet beschikbaar';
			}
?>
    </td>
    <td style="text-align:center;">
        <input type="checkbox" disabled <?php checked($is_sent_event); ?> />
    </td>
    <td style="text-align:center;">
    <?php
    if ($is_sent_lineup_3 === 'too_late') {
        echo '⏱️ Te laat';
    } else {
        echo '<input type="checkbox" disabled ' . checked($is_sent_lineup_3, true, false) . ' />';
    }
    ?>
</td>
    <td style="text-align:center;">
        <input type="checkbox" disabled <?php checked($is_sent_lineup_1); ?> />
    </td>
    <td>
        <form method="post" style="margin:0;">
            <input type="hidden" name="send_event_id" value="<?php echo esc_attr($event->ID); ?>">
            <?php submit_button('Send Event', 'secondary', '', false); ?>
        </form>
    </td>
    <td>
        <form method="post" style="margin:0;">
            <input type="hidden" name="send_lineup_event_id" value="<?php echo esc_attr($event->ID); ?>">
            <?php submit_button('Stuur spelerslijst', 'secondary', '', false); ?>
        </form>
    </td>
    <td>
        <form method="post" style="margin:0;">
            <input type="hidden" name="reset_send_event_id" value="<?php echo esc_attr($event->ID); ?>">
            <?php submit_button('Reset status', 'small', '', false); ?>
        </form>
    </td>
    <td>
        <form method="post" style="margin:0;">
            <input type="hidden" name="reset_send_lineup_id" value="<?php echo esc_attr($event->ID); ?>">
            <?php submit_button('Reset lineup', 'small', '', false); ?>
        </form>
    </td>
</tr>
<?php
		endforeach; ?>
</tbody>
            </table>
        <?php
	endif; ?>
    </div>
<?php
}
