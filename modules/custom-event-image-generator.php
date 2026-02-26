<?php
/**
 * Genereer een event-afbeelding en zet als featured image
 */
function paras_generate_event_image($post_id, $force = true) {

    // ✅ Controleer post type
    if (get_post_type($post_id) !== 'sp_event') {
        error_log("FOUT: Post $post_id is geen sp_event");
        return false;
    }

    $title = get_the_title($post_id);
    $date  = get_the_date('d-m-Y', $post_id);
    $hour  = get_the_date('H\ui', $post_id);

    // === Locatie ophalen ===
    $terms = get_the_terms($post_id, 'sp_venue');
    $locations = [];
    $addresses = [];

    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $locations[] = $term->name;
            $option_key = 'taxonomy_' . $term->term_id;
            $venue_data = get_option($option_key);
            if (!empty($venue_data['sp_address'])) {
                $addresses[] = $venue_data['sp_address'];
            }
        }
    }

    $location = !empty($addresses)
        ? implode(', ', $addresses)
        : (!empty($locations) ? implode(', ', $locations) : 'Locatie niet beschikbaar');

    // === Teams ophalen ===
    $sp_team_raw = get_post_meta($post_id, 'sp_team', false);
    $team_ids = is_array($sp_team_raw) ? $sp_team_raw : maybe_unserialize($sp_team_raw);
    if (!is_array($team_ids)) {
        $team_ids = explode(',', (string)$sp_team_raw);
    }

    $team1_id = isset($team_ids[0]) ? intval($team_ids[0]) : 0;
    $team2_id = isset($team_ids[1]) ? intval($team_ids[1]) : 0;

    $logo1_url = $team1_id ? get_the_post_thumbnail_url($team1_id, 'full') : null;
    $logo2_url = $team2_id ? get_the_post_thumbnail_url($team2_id, 'full') : null;

    // === Achtergrond ===
    $bg_ids = get_option('event_image_backgrounds');
    if (!empty($bg_ids) && is_array($bg_ids)) {
        $bg_id = $bg_ids[array_rand($bg_ids)];
        $bg_path = get_attached_file($bg_id);
        if (!file_exists($bg_path)) {
            error_log("FOUT: Achtergrondbestand bestaat niet: $bg_path");
            $bg_path = plugin_dir_path(__FILE__) . 'assets/default.jpg';
        }
    } else {
        $bg_path = plugin_dir_path(__FILE__) . 'assets/default.jpg';
    }

    // === Output pad ===
    $upload_dir = wp_upload_dir();
    $folder = $upload_dir['basedir'] . '/wedstrijden/';
    if (!file_exists($folder)) {
        wp_mkdir_p($folder);
        error_log("Map gemaakt: $folder");
    }

    $output_path = $folder . "event-image-{$post_id}.jpg";

    // === Genereer afbeelding ===
    $image_created = create_event_image(
        $bg_path,
        $logo1_url,
        $logo2_url,
        $title,
        $date,
        $hour,
        $location,
        $output_path
    );

    if (!$image_created || !file_exists($output_path)) {
        error_log("FOUT: create_event_image faalde of bestand niet aangemaakt: $output_path");
        return false;
    }

    // ✅ Zet als featured image
    $set_featured = set_generated_event_image_as_featured($post_id, $output_path);
    if (!$set_featured) {
        error_log("FOUT: set_generated_event_image_as_featured faalde voor post $post_id");
    }

    $image_url = $upload_dir['baseurl'] . "/wedstrijden/event-image-{$post_id}.jpg";

    update_post_meta($post_id, 'generated_event_image', $image_url);
    update_post_meta($post_id, '_event_image_generated', 1);

    return $image_url;
}

/**
 * Zet een gegenereerde afbeelding als featured image
 */
function set_generated_event_image_as_featured($post_id, $image_path) {
    if (!file_exists($image_path)) {
        error_log("FOUT: Bestand bestaat niet: $image_path");
        return false;
    }

    $upload_dir = wp_upload_dir();
    $image_url = $upload_dir['baseurl'] . '/wedstrijden/' . basename($image_path);

    // Check of er al een attachment bestaat voor deze afbeelding
    $attachment_exists = attachment_url_to_postid($image_url);
    if ($attachment_exists) {
        set_post_thumbnail($post_id, $attachment_exists);
        error_log("Bestaand attachment hergebruikt voor event {$post_id} (attachment ID: {$attachment_exists}).");
        return true;
    }

    // Oude featured image verwijderen als die er is
    $old_thumbnail_id = get_post_thumbnail_id($post_id);
    if ($old_thumbnail_id) {
        wp_delete_attachment($old_thumbnail_id, true);
    }

    // Nieuwe attachment maken
    $filetype = wp_check_filetype(basename($image_path), null);
    $attachment = array(
        'guid'           => $image_url,
        'post_mime_type' => $filetype['type'],
        'post_title'     => preg_replace('/\.[^.]+$/', '', basename($image_path)),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );

    $attach_id = wp_insert_attachment($attachment, $image_path, $post_id);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $image_path);
    wp_update_attachment_metadata($attach_id, $attach_data);

    set_post_thumbnail($post_id, $attach_id);

	Waha_Helpers::event_log("Featured image ingesteld voor event '" . get_the_title($post_id) . "' (attachment ID: {$attach_id}).");
	
    return true;
}