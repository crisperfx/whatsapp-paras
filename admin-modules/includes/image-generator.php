<?php
// Hulpfunctie om tekst gecentreerd te plaatsen
function add_centered_text($img, $font_size, $center_x, $y, $color, $font_path, $text) {
    $bbox = imagettfbbox($font_size, 0, $font_path, $text);
    $text_width = abs($bbox[4] - $bbox[0]);
    $x = $center_x - ($text_width / 2);
imagettftext(
    $img,
    (float) $font_size, // mag float blijven
    0,
    (int) round($x),    // altijd integer
    (int) round($y),    // altijd integer
    $color,
    $font_path,
    $text
);
}

// Hoofdfunctie afbeelding maken met overlays + tekstinstellingen

if ( ! function_exists( 'load_image_from_path' ) ) {
    function load_image_from_path( $path ) {
        if ( empty( $path ) || ! file_exists( $path ) ) {
            error_log( 'FOUT: Bestand bestaat niet: ' . $path );
            return false;
        }

        return imagecreatefromstring( file_get_contents( $path ) );
    }
}
function create_event_image($bg_path, $logo1_url, $logo2_url, $title, $date, $hour, $location, $output_path) {

    if (!file_exists($bg_path)) {
        return false;
    }

    $bg_ext = strtolower(pathinfo($bg_path, PATHINFO_EXTENSION));
    switch ($bg_ext) {
        case 'png': $img = @imagecreatefrompng($bg_path); imagealphablending($img,true); imagesavealpha($img,true); break;
        case 'gif': $img = @imagecreatefromgif($bg_path); break;
        default: $img = @imagecreatefromjpeg($bg_path);
    }
    if (!$img) { error_log("FOUT: Kan achtergrond niet laden."); return false; }

    // Crop & resize naar 1080x1080
    $width = imagesx($img); $height = imagesy($img);
    $min_side = min($width, $height);
    $img = imagecrop($img, ['x'=>($width-$min_side)/2, 'y'=>($height-$min_side)/2, 'width'=>$min_side, 'height'=>$min_side]) ?: $img;
    $resized = imagecreatetruecolor(1080,1080);
    imagealphablending($resized,true);
    imagesavealpha($resized,true);
    imagecopyresampled($resized,$img,0,0,0,0,1080,1080,imagesx($img),imagesy($img));
    imagedestroy($img);
    $img = $resized;

    // --- Overlay laden en instellingen ophalen ---
    $overlays = get_option('event_image_overlays', []);
    $ov = !empty($overlays) ? $overlays[array_rand($overlays)] : [];
    if (!empty($ov['id'])) {
        $overlay_path = get_attached_file($ov['id']);
        if (file_exists($overlay_path)) {
            $overlay = @imagecreatefrompng($overlay_path);
            if ($overlay) {
                imagealphablending($overlay,true);
                imagesavealpha($overlay,true);
                imagecopy($img,$overlay,0,0,0,0,imagesx($overlay),imagesy($overlay));
                imagedestroy($overlay);
            } else { error_log("FOUT: Overlay kon niet geladen worden: $overlay_path"); }
        } else { error_log("FOUT: Overlaybestand bestaat niet: $overlay_path"); }
    }

    // Logos laden
    $logo1_path = str_replace(site_url('/'), ABSPATH, $logo1_url);
    $logo2_path = str_replace(site_url('/'), ABSPATH, $logo2_url);
    $logo1 = load_image_from_path($logo1_path);
    $logo2 = load_image_from_path($logo2_path);
    if (!$logo1 || !$logo2) { error_log("FOUT: Logo's konden niet geladen worden."); return false; }

    // Logo posities en grootte van overlay-instellingen
    $center_x1 = intval($ov['logo1_x'] ?? 270);
    $center_y1 = intval($ov['logo1_y'] ?? 540);
    $center_x2 = intval($ov['logo2_x'] ?? 810);
    $center_y2 = intval($ov['logo2_y'] ?? 540);
    $logo_w = intval($ov['logo_w'] ?? 280);
    $logo_h = intval($ov['logo_h'] ?? 260);

    $logo1 = imagescale($logo1, $logo_w, $logo_h);
    $logo2 = imagescale($logo2, $logo_w, $logo_h);

    // Logo plaatsen
    imagecopy($img, $logo1, $center_x1 - imagesx($logo1)/2, $center_y1 - imagesy($logo1)/2, 0,0, imagesx($logo1), imagesy($logo1));
    imagecopy($img, $logo2, $center_x2 - imagesx($logo2)/2, $center_y2 - imagesy($logo2)/2, 0,0, imagesx($logo2), imagesy($logo2));
    imagedestroy($logo1);
    imagedestroy($logo2);

    // Tekst
    $white = imagecolorallocate($img,255,255,255);
    $font = __DIR__.'/fonts/Poppins-Black.ttf';
    if (!file_exists($font)) { error_log("FOUT: Fontbestand niet gevonden: $font"); return false; }

    $texts = [
        'TITLE'=>['x'=>intval($ov['title_x']??540),'y'=>intval($ov['title_y']??380),'font_size'=>intval($ov['title_size']??30)],
        'DATE'=>['x'=>intval($ov['date_x']??360),'y'=>intval($ov['date_y']??835),'font_size'=>intval($ov['date_size']??40)],
        'HOUR'=>['x'=>intval($ov['hour_x']??640),'y'=>intval($ov['hour_y']??835),'font_size'=>intval($ov['hour_size']??40)],
        'LOCATION'=>['x'=>intval($ov['location_x']??540),'y'=>intval($ov['location_y']??900),'font_size'=>intval($ov['location_size']??25)],
    ];

    foreach($texts as $key=>$conf){
        $txt = match($key){
            'TITLE'=>strtoupper($title),
            'DATE'=>$date,
            'HOUR'=>$hour,
            'LOCATION'=>strtoupper($location),
            default=>$key
        };
        add_centered_text($img,$conf['font_size'],$conf['x'],$conf['y'],$white,$font,$txt);
    }

    // Opslaan
    $out_ext = strtolower(pathinfo($output_path,PATHINFO_EXTENSION));
    $saved = false;
    switch($out_ext){
        case 'png': $saved=imagepng($img,$output_path); break;
        case 'gif': $saved=imagegif($img,$output_path); break;
        default: $saved=imagejpeg($img,$output_path,90);
    }
    imagedestroy($img);

    if ($saved) {  }
    else { error_log("FOUT: Kan afbeelding niet opslaan naar: $output_path"); }
    return $saved;
}