<?php
function whatsapp_paras_debug_page() {
$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'debug';

    // Tabs array
    $tabs = [
        'debug'    => 'Berichten',
        'webhook'  => 'Webhooks',
    ];

    ?>
    <div class="wrap">
        <h1>WhatsApp Paras - Debug</h1>

        <h2 class="nav-tab-wrapper">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="<?php echo esc_url(add_query_arg('tab', $key)); ?>" class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <div class="tab-content" style="margin-top:20px;">
            <?php
            // TAB 1: Debug & Test
            if ($tab === 'debug') {
// Velden ophalen

    $test_event_name        = get_option('whatsapp_test_event_name', 'Test Event Naam');
	$test_event_description = get_option('whatsapp_test_event_description', 'Dit is een test event beschrijving.');
	$test_event_starttime   = get_option('whatsapp_test_event_starttime', date('Y-m-d H:i', current_time('timestamp') + 3600));
	$start_timestamp = strtotime($test_event_starttime);
	$test_event_location    = get_option('whatsapp_test_event_location', 'Test Locatie');

// Verwerk de POST acties
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Testbericht verzenden
    if (isset($_POST['send_manual_message'])) {
        $text   = sanitize_text_field($_POST['text']);
        $chatId = get_option('whatsapp_chatId', '');

        $wa = new WA_Service();
        $response = $wa->send_text($chatId, $text);

        echo '<div class="notice notice-info"><pre>';
        if (is_wp_error($response)) {
            echo 'Fout: ' . esc_html($response->get_error_message());
        } elseif (is_array($response) && !empty($response['success'])) {
            echo '✅ Test bericht verzonden' . "\n";
            echo 'API message: ' . esc_html($response['message']) . "\n";
        } else {
            echo 'Onverwacht antwoord: ' . esc_html(json_encode($response, JSON_PRETTY_PRINT));
        }
        echo '</pre></div>';
    }
    
    // Testevent verzenden
    if (isset($_POST['send_test_event'])) {
        $chatId = get_option('whatsapp_chatId', '');
		$event_id = 5102;
        $wa = new WA_Service();
        $response = $wa->send_whatsapp_event(
        	$event_id,
        	$chatId,
        	$test_event_name,
        	$test_event_description,
        	$start_timestamp,
        	$test_event_location,
        	);

        echo '<div class="notice notice-info"><pre>';
        if (is_wp_error($response)) {
            echo 'Fout: ' . esc_html($response->get_error_message());
        } elseif (is_array($response) && !empty($response['success'])) {
            echo '✅ Test event verzonden' . "\n";
            echo 'API message: ' . esc_html($response['message']) . "\n";
        } else {
            echo 'Onverwacht antwoord: ' . esc_html(json_encode($response, JSON_PRETTY_PRINT));
        }
        echo '</pre></div>';
    }

    // Test SP Event bericht verzenden
    if (isset($_POST['send_test_event_message'])) {

        $event_id = intval($_POST['test_event_id']);
        if (!$event_id) $event_id = 5102;

        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'sp_event') {
            echo '<div class="notice notice-error is-dismissible"><p>⚠️ Ongeldig SP Event ID.</p></div>';
        } else {
            $chatId = get_option('whatsapp_5days_chatId', '');
            $event_link = get_permalink($event->ID);
            $caption = "Wedstrijd: {$event->post_title}\nBekijk details en volg live: {$event_link}";

            $file_payload = [];
            if (has_post_thumbnail($event->ID)) {
                $file_payload = [
                    "mimetype" => "image/jpeg",
                    "url"      => get_the_post_thumbnail_url($event->ID, 'full'),
                    "filename" => "event-image.jpeg"
                ];
            }

            $wa = new WA_Service();
            $response = $wa->send_image($chatId, $caption, $file_payload);

            echo '<div class="notice notice-info"><pre>';
            if (is_wp_error($response)) {
                echo 'Fout: ' . esc_html($response->get_error_message());
            } elseif (is_array($response) && !empty($response['success'])) {
                echo '✅ Test afbeelding verzonden' . "\n";
                echo 'API message: ' . esc_html($response['message']) . "\n";
            } else {
                echo 'Onverwacht antwoord: ' . esc_html(json_encode($response, JSON_PRETTY_PRINT));
            }
            echo '</pre></div>';
        }
    }
}


// Pagina HTML
?>
<div class="wrap">
    <h1>Debug & Test WhatsApp</h1>

    <h2>Testbericht Verzenden</h2>
    <form method="post">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="text">Bericht Tekst</label></th>
                <td><input name="text" type="text" id="text" value="Dit is een test vanop www.fc-deparas.be!" class="regular-text"></td>
            </tr>
        </table>
        <?php submit_button('Verstuur bericht', 'secondary', 'send_manual_message'); ?>
    </form>

    <hr>
    
    <h2>Test-event Verzenden</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="test_event_name">Event Naam</label></th>
                    <td><input name="test_event_name" type="text" id="test_event_name" value="<?php echo esc_attr($test_event_name); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="test_event_description">Event Beschrijving</label></th>
                    <td><textarea name="test_event_description" id="test_event_description" class="large-text"><?php echo esc_textarea($test_event_description); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="test_event_starttime">Starttijd (YYYY-MM-DD HH:MM)</label></th>
                    <td><input name="test_event_starttime" type="text" id="test_event_starttime" value="<?php echo esc_attr($test_event_starttime); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="test_event_location">Locatie</label></th>
                    <td><input name="test_event_location" type="text" id="test_event_location" value="<?php echo esc_attr($test_event_location); ?>" class="regular-text"></td>
                </tr>
            </table>
            <?php submit_button('Verstuur Test Event', 'secondary', 'send_test_event'); ?>
        </form>
        <hr>

    <h2>Test afbeelding Bericht Verzenden</h2>
    <form method="post">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="test_event_id">Wedstrijd-ID</label></th>
                <td><input name="test_event_id" type="number" id="test_event_id" value="5102" class="regular-text"></td>
            </tr>
        </table>
        <?php submit_button('Verstuur Event Bericht', 'secondary', 'send_test_event_message'); ?>
    </form>
</div><?php    } 
            // TAB 2: Webhook Test
            elseif ($tab === 'webhook') {
    // Zorg dat de functie beschikbaar is
    if (file_exists(__DIR__ . '/webhooks.php')) {
        require_once __DIR__ . '/webhooks.php';
    }

    // Controleer of functie bestaat en voer uit
    if (function_exists('whatsapp_paras_webhook_tab')) {
        whatsapp_paras_webhook_tab();
    } else {
        echo '<p>Webhook functie niet gevonden.</p>';
    }
}
            ?>
        </div>
    </div>
    <?php
}

