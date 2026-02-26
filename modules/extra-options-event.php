<?php

function sp_event_extra_options_metabox_callback($post) {
    // Nonce voor beveiliging
    wp_nonce_field('sp_event_extra_options_save', 'sp_event_extra_options_nonce');

    // Huidige waarden ophalen
    $vriendschappelijk = Waha_Helpers::get_meta($post->ID, '_sp_event_vriendschappelijk');
	$type_voetbal      = Waha_Helpers::get_meta($post->ID, '_sp_event_type_voetbal');

    ?>
    <p>
        <label>
            <input type="checkbox" name="sp_event_vriendschappelijk" value="1" <?php checked($vriendschappelijk, '1'); ?>>
            Vriendschappelijk
        </label>
    </p>

    <p>
        <label for="sp_event_type_voetbal">Type voetbal:</label><br>
        <select name="sp_event_type_voetbal" id="sp_event_type_voetbal">
            <option value="">-- Kies een optie --</option>
            <option value="Zaalvoetbal" <?php selected($type_voetbal, 'Zaalvoetbal'); ?>>Zaalvoetbal</option>
            <option value="Veldvoetbal" <?php selected($type_voetbal, 'Veldvoetbal'); ?>>Veldvoetbal</option>
        </select>
    </p>
    <?php
}

function render_whatsapp_status_box( $post ) {
    $sent = Waha_Helpers::get_meta($post->ID, '_whatsapp_5days_sent');
    ?>
    <label>
        <input type="checkbox" name="whatsapp_5days_sent" value="1" <?php checked( $sent, '1' ); ?>>
        Algemeen bericht al verzonden?
    </label>
    <?php
    wp_nonce_field( 'save_whatsapp_status', 'whatsapp_status_nonce' );
}
add_action('save_post_sp_event', function($post_id) {
    // Nonce checks
    if (
        !isset($_POST['sp_event_extra_options_nonce']) || 
        !wp_verify_nonce($_POST['sp_event_extra_options_nonce'], 'sp_event_extra_options_save')
    ) {
        return;
    }
    if (
        !isset($_POST['whatsapp_status_nonce']) || 
        !wp_verify_nonce($_POST['whatsapp_status_nonce'], 'save_whatsapp_status')
    ) {
        return;
    }

    // Autosave skippen
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // 1 Vriendschappelijk checkbox
    $vriendschappelijk = isset($_POST['sp_event_vriendschappelijk']) && $_POST['sp_event_vriendschappelijk'] === '1' ? '1' : '';
    Waha_Helpers::update_meta($post_id, '_sp_event_vriendschappelijk', $vriendschappelijk);

    // 2 Type voetbal dropdown
    $type_voetbal = isset($_POST['sp_event_type_voetbal']) ? sanitize_text_field($_POST['sp_event_type_voetbal']) : '';
    if (!in_array($type_voetbal, ['Zaalvoetbal', 'Veldvoetbal', ''])) {
        $type_voetbal = '';
    }
    Waha_Helpers::update_meta($post_id, '_sp_event_type_voetbal', $type_voetbal);

    // 3️WhatsApp status checkbox
    $whatsapp_sent = isset($_POST['whatsapp_5days_sent']) && $_POST['whatsapp_5days_sent'] === '1' ? '1' : '';
    Waha_Helpers::update_meta($post_id, '_whatsapp_5days_sent', $whatsapp_sent);
});
