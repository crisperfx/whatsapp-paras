<?php

add_action('add_meta_boxes', function() {
    add_meta_box(
        'sp_player_whatsapp_number',
        'WhatsApp Nummer',
        'sp_player_whatsapp_number_callback',
        'sp_player',
        'side',
        'default'
    );
    add_meta_box(
        'formation_visibility',
        __('Opstelling zichtbaarheid', 'textdomain'),
        'render_formation_visibility_box',
        'sp_event',
        'side',
        'default'
    );
    add_meta_box(
        'sp_event_extra_options',
        'Extra Event Opties',
        'sp_event_extra_options_metabox_callback',
        'sp_event',
        'side',
        'default'
    );
    add_meta_box(
        'whatsapp_status_box',
        'WhatsApp Status',
        'render_whatsapp_status_box',
        'sp_event',
        'side',
        'default'
    );
});
