<?php
// Telefoonnummer formatteren + vergelijking via upload csv of LID kan gekoppeld worden
function whatsapp_paras_format_phone_to_chatid($phone_number) {
    $phone_number = trim($phone_number);
    if (substr($phone_number, 0, 1) === '+') {
        $phone_number = substr($phone_number, 1);
    }
    $clean = preg_replace('/[^0-9]/', '', $phone_number);
    if (substr($clean, 0, 1) === '0') {
        $clean = '32' . substr($clean, 1);
    }
    return $clean . '@s.whatsapp.net';
}

function whatsapp_paras_players_details_page() {

    // Haal opties
    $options = Waha_Options::get_options();
    $csv_enabled = $options['enable_csv_import_players'] ?? true;

    echo '<div class="wrap">';
    echo '<h1>Spelers-nummers Whatsapp</h1>';

    // --- CSV Upload (alleen als enabled) ---
    if ($csv_enabled) {

        // Verwerking van CSV upload
        if (isset($_POST['import_csv']) && !empty($_FILES['csv_file']['tmp_name'])) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');

            if ($handle) {
                $count = 0;
                while (($data = fgetcsv($handle, 10000, ',')) !== false) {
                    $lid = trim($data[0]);
                    $chat_id = trim($data[1]);

                    $normalized_chat_ids = [$chat_id];
                    if (str_ends_with($chat_id, '@c.us')) {
                        $normalized_chat_ids[] = str_replace('@c.us', '@s.whatsapp.net', $chat_id);
                    } elseif (str_ends_with($chat_id, '@s.whatsapp.net')) {
                        $normalized_chat_ids[] = str_replace('@s.whatsapp.net', '@c.us', $chat_id);
                    }

                    $player_id = null;
                    foreach ($normalized_chat_ids as $variant) {
                        $players = get_posts([
                            'post_type' => 'sp_player',
                            'meta_key' => 'whatsapp_chat_id',
                            'meta_value' => $variant,
                            'posts_per_page' => 1,
                            'fields' => 'ids'
                        ]);
                        if (!empty($players)) {
                            $player_id = $players[0];
                            break;
                        }
                    }

                    if ($player_id) {
                        update_post_meta($player_id, 'LIDwhatsapp', $lid);
                        $count++;
                    }
                }
                fclose($handle);
                echo '<div class="notice notice-success is-dismissible"><p>' . $count . ' spelers bijgewerkt met LIDwhatsapp.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>CSV kon niet worden gelezen.</p></div>';
            }
        }

        // CSV-uploadformulier tonen
        echo '<h2>CSV importeren (LID + WhatsApp nummer)</h2>';
        echo '<form method="post" enctype="multipart/form-data">';
        echo '<input type="file" name="csv_file" accept=".csv" required> ';
        submit_button('Importeren', 'secondary', 'import_csv', false);
        echo '</form><hr>';
    }

    // --- Opslaan van telefoonnummers (altijd mogelijk) ---
    if (isset($_POST['save_players_details'])) {
        if (isset($_POST['phone_number']) && is_array($_POST['phone_number'])) {
            foreach ($_POST['phone_number'] as $player_id => $phone_value) {
                $phone_clean = sanitize_text_field($phone_value);
                $formatted_chat_id = whatsapp_paras_format_phone_to_chatid($phone_clean);

                update_post_meta((int)$player_id, 'linkedin', $formatted_chat_id);
                update_post_meta((int)$player_id, 'whatsapp_chat_id', $formatted_chat_id);
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>Spelersgegevens succesvol opgeslagen!</p></div>';
    }

    // --- Spelers ophalen en tabel tonen (altijd) ---
    $team_id = Waha_Options::get_team_id();
    $players = get_posts([
        'post_type'      => 'sp_player',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => 'sp_current_team',
                'value'   => $team_id,
                'compare' => 'LIKE',
            ],
        ],
    ]);

    echo '<form method="post">';
    echo '<table class="widefat striped">';
    echo '<thead><tr><th>Naam</th><th>Telefoonnummer (LinkedIn)</th><th>WhatsApp Chat ID</th><th>LID WhatsApp</th></tr></thead><tbody>';

    foreach ($players as $player) {
        $phone = get_post_meta($player->ID, 'linkedin', true);
        $chatId = get_post_meta($player->ID, 'whatsapp_chat_id', true);
        $lid = get_post_meta($player->ID, 'LIDwhatsapp', true);

        echo '<tr>';
        echo '<td>' . esc_html($player->post_title) . '</td>';
        echo '<td><input type="text" name="phone_number[' . esc_attr($player->ID) . ']" value="' . esc_attr($phone) . '" class="regular-text" placeholder="none"></td>';
        echo '<td><code>' . esc_html($chatId) . '</code></td>';
        echo '<td><code>' . esc_html($lid ?: '-') . '</code></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    submit_button('Opslaan', 'primary', 'save_players_details');
    echo '</form>';

    echo '</div>';
}