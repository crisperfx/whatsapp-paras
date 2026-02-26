<?php

function sp_player_whatsapp_number_callback($post) {
    // Nonce voor beveiliging
    wp_nonce_field('sp_player_whatsapp_number_save', 'sp_player_whatsapp_number_nonce');

    // Huidige waarde ophalen via helper
    $value = Waha_Helpers::get_meta($post->ID, 'linkedin');
    ?>
    <p>
        <label for="player_whatsapp_number">WhatsApp Nummer:</label>
        <input type="text" name="player_whatsapp_number" id="player_whatsapp_number" value="<?php echo esc_attr($value); ?>" style="width:100%;" />
    </p>
    <?php
}

add_action('save_post_sp_player', function($post_id) {
    // Nonce check
    if (!isset($_POST['sp_player_whatsapp_number_nonce']) || 
        !wp_verify_nonce($_POST['sp_player_whatsapp_number_nonce'], 'sp_player_whatsapp_number_save')) {
        return;
    }

    // Autosave skippen
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;


    // Opslaan via helper
    $whatsapp_number = isset($_POST['player_whatsapp_number']) ? sanitize_text_field($_POST['player_whatsapp_number']) : '';
    Waha_Helpers::update_meta($post_id, 'linkedin', $whatsapp_number);
});