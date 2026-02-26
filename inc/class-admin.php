<?php
/**
 * Class and Function List:
 * Function list:
 * - whatsapp_paras_plugin_menu()
 * - whatsapp_handle_save_settings()
 * - whatsapp_paras_plugin_page()
 */

add_action('admin_menu', 'whatsapp_paras_plugin_menu');
add_action('admin_post_save_whatsapp_settings', 'whatsapp_handle_save_settings');
add_action('admin_head', function () {

    global $post;

    if (!$post) {
        return;
    }

    if ($post->post_type === 'sp_event') {
        echo '<style>
            .postarea {
                display: none !important;
            }
        </style>';
    }
});
function whatsapp_paras_plugin_menu() {
    add_menu_page(
        'Core plugin',        // Pagina titel
        'Fc De Paras',        // Menu titel
        'manage_options',     // Capability
        'whatsapp-paras',     // Menu slug
        '',                   // Callback leeg, eerste submenu neemt over
        'dashicons-forms',                   // Icon (optioneel)
        2                     // Positie (optioneel)
    );

    // Submenu's
    add_submenu_page('whatsapp-paras', 'WhatsApp Instellingen', 'WhatsApp', 'manage_options', 'whatsapp-paras', 'whatsapp_paras_plugin_page');
    add_submenu_page('whatsapp-paras', 'Events Verzenden', 'Events Verzenden', 'manage_options', 'whatsapp-paras-events', 'whatsapp_paras_events_page');
    add_submenu_page('whatsapp-paras', 'Event Image Generator', 'Event Image', 'manage_options', 'whatsapp-paras-event-image', 'event_image_settings_page');
    add_submenu_page('whatsapp-paras', 'Aanwezigheden', 'Aanwezigheden', 'manage_options', 'whatsapp-paras-attendance', 'whatsapp_paras_attendance_page');
    add_submenu_page('whatsapp-paras', 'Spelers Profielen', 'Spelers Profielen', 'manage_options', 'whatsapp-paras-players', 'whatsapp_paras_players_details_page');
    add_submenu_page('whatsapp-paras', 'Changelog', 'Changelog', 'manage_options', 'whatsapp-paras-changelog', 'whatsapp_paras_changelog_page');
    add_submenu_page('whatsapp-paras', 'Debug', 'Debug', 'manage_options', 'whatsapp-paras-debug', 'whatsapp_paras_debug_page');
    add_submenu_page('whatsapp-paras', 'Logs', 'Logs', 'manage_options', 'whatsapp-paras-logs', 'whatsapp_paras_logs_page');
}

function whatsapp_handle_save_settings() {
 

    update_option('whatsapp_send_enabled', isset($_POST['send_whatsapp']) ? '1' : '0');
    update_option('whatsapp_send_events_enabled', isset($_POST['send_events']) ? '1' : '0');
    update_option('whatsapp_send_lineup_enabled', isset($_POST['send_lineup']) ? '1' : '0');
    update_option('whatsapp_chatId', sanitize_text_field($_POST['chatId']));
    update_option('whatsapp_lineup_chatId', sanitize_text_field($_POST['lineup_chatId']));
    update_option('whatsapp_manual_event_chatId', sanitize_text_field($_POST['manual_event_chatId']));
    update_option('whatsapp_5days_chatId', sanitize_text_field($_POST['5days_chatId']));
    update_option('whatsapp_webhook_enabled', isset($_POST['enable_webhook']) ? '1' : '0');
    if (isset($_POST['host'])) update_option('whatsapp_host', sanitize_text_field($_POST['host']));
    if (isset($_POST['api_key'])) update_option('whatsapp_api_key', sanitize_text_field($_POST['api_key']));
    if (isset($_POST['auth'])) update_option('whatsapp_auth', wp_unslash($_POST['auth']));

    wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
    exit;
}

function whatsapp_paras_plugin_page() {

    // Haal opties op
    $host        = get_option('whatsapp_host', '');
    $api_key     = get_option('whatsapp_api_key', '');
    $session     = get_option('whatsapp_session', 'default');
    $basic_auth  = get_option('whatsapp_auth', '');
    $send_whatsapp = get_option('whatsapp_send_enabled', '0');
    $send_events   = get_option('whatsapp_send_events_enabled', '0');
    $send_lineup   = get_option('whatsapp_send_lineup_enabled', '0');

    $current_chatId        = get_option('whatsapp_chatId', '');
    $current_lineup_chatId = get_option('whatsapp_lineup_chatId', '');
    $current_event_global_message_chatId = get_option('whatsapp_5days_chatId', '');
    $current_manual_chatId = get_option('whatsapp_manual_event_chatId', '');
    $chat_options = array(
        '120363150944235207@g.us' => 'Spelersgroep',
        '120363348593759502@g.us' => 'Aankomende wedstrijden',
        '179323524931746@lid'     => 'Mezelf',
        '120363347125185707@g.us' => 'Community',
    );
    $webhook_enabled = get_option('whatsapp_webhook_enabled', '0');

    ?>
    <div class="wrap">
        <h1>WhatsApp Hoofd-instellingen</h1>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="save_whatsapp_settings">
            <table class="form-table">
            	<tr>
    				<th colspan="2"><h2 style="margin:0;">API settings</h2></th>
				</tr>
                <tr>
                    <th scope="row"><label for="host">API Host URL</label></th>
                    <td><input name="host" type="text" id="host" value="<?php echo esc_attr($host); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="auth">Basic-auth</label></th>
                    <td><input name="auth" type="text" id="auth" value="<?php echo esc_attr($basic_auth); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="api_key">API sleutel</label></th>
                    <td><input name="api_key" type="text" id="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="session">Waha-sessie</label></th>
                    <td><input name="session" type="text" id="session" value="<?php echo esc_attr($session); ?>" class="regular-text"></td>
                </tr>
             	<tr>
    				<th colspan="2"><h2 style="margin:0;">Nummers instellen</h2></th>
				</tr>
                <tr>
                    <th scope="row"><label for="chatId">Nummer berichten</label></th>
                    <td>
                        <select name="chatId" id="chatId">
                            <?php foreach ($chat_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_chatId, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="5days_chatId">Nummer eventinfo</label></th>
                    <td>
                        <select name="5days_chatId" id="5days_chatId">
                            <?php foreach ($chat_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_event_global_message_chatId, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="lineup_chatId">Nummer Lineup</label></th>
                    <td>
                        <select name="lineup_chatId" id="lineup_chatId">
                            <?php foreach ($chat_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_lineup_chatId, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="manual_event_chatId">Nummer Events</label></th>
                    <td>
                        <select name="manual_event_chatId" id="manual_event_chatId">
                            <?php foreach ($chat_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_manual_chatId, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="enable_webhook">Webhook testen</label></th>
                    <td>
                        <input name="enable_webhook" type="checkbox" id="enable_webhook" value="1" <?php checked($webhook_enabled, '1'); ?> />
                        <label for="enable_webhook">Webhook in- of uitschakelen</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="send_whatsapp">LIVE! Scores</label></th>
                    <td>
                        <input name="send_whatsapp" type="checkbox" id="send_whatsapp" value="1" <?php checked($send_whatsapp, '1'); ?> />
                        <label for="send_whatsapp">Verzend WhatsApp-berichten bij score-updates.</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="send_events">Events Verzenden</label></th>
                    <td>
                        <input name="send_events" type="checkbox" id="send_events" value="1" <?php checked($send_events, '1'); ?> />
                        <label for="send_events">Verzend WhatsApp-events via het events-overzicht.</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="send_lineup">Spelerslijst Verzenden</label></th>
                    <td>
                        <input name="send_lineup" type="checkbox" id="send_lineup" value="1" <?php checked($send_lineup, '1'); ?> />
                        <label for="send_lineup">Verzend WhatsApp-spelerslijsten.</label>
                    </td>
                </tr>
            </table>
            <?php submit_button('Instellingen opslaan'); ?>
        </form>

        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') : ?>
            <div class="notice notice-success is-dismissible">
                <p>Instellingen succesvol opgeslagen!</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}