<?php
require_once plugin_dir_path(__FILE__) . 'includes/image-generator.php';
add_action('admin_enqueue_scripts', function($hook){

    if ($hook !== 'fc-de-paras_page_whatsapp-paras-event-image') {
        return;
    }

    wp_enqueue_media();

    wp_enqueue_script(
        'event-image-admin',
        plugin_dir_url(__FILE__) . 'js/admin-event-image.js',
        ['jquery'],
        '1.0',
        true
    );
});
function event_image_settings_page() {

    // === Opslaan ===
    if (isset($_POST['background_ids']) || isset($_POST['overlays'])) {

        // Achtergronden
        $bg_ids = !empty($_POST['background_ids'])
            ? array_filter(array_map('intval', explode(',', $_POST['background_ids'])))
            : [];
        update_option('event_image_backgrounds', $bg_ids);

        // Overlays
        $overlays_clean = [];
if (!empty($_POST['overlays']) && is_array($_POST['overlays'])) {
    foreach ($_POST['overlays'] as $row) {
        $id = intval($row['id'] ?? 0);
        if ($id <= 0) continue; // geen overlay zonder ID

        $overlay = [
            'id' => $id,
            'logo1_x' => max(1,intval($row['logo1_x'] ?? 270)),
            'logo1_y' => max(1,intval($row['logo1_y'] ?? 540)),
            'logo2_x' => max(1,intval($row['logo2_x'] ?? 810)),
            'logo2_y' => max(1,intval($row['logo2_y'] ?? 540)),
            'logo_w' => max(1,intval($row['logo_w'] ?? 100)),
            'logo_h' => max(1,intval($row['logo_h'] ?? 100)),
        ];

        foreach (['title','date','hour','location'] as $txt) {
            $overlay[$txt.'_x'] = max(1,intval($row[$txt.'_x'] ?? 540));
            $overlay[$txt.'_y'] = max(1,intval($row[$txt.'_y'] ?? 540));
            $overlay[$txt.'_font'] = $row[$txt.'_font'] ?? 'Poppins-Black';
            $overlay[$txt.'_size'] = max(1,intval($row[$txt.'_size'] ?? 30));
        }

        // Controleer dat het bestand bestaat
        $file = get_attached_file($id);
        if (!$file || !file_exists($file)) continue;

        $overlays_clean[] = $overlay;
    }
}
update_option('event_image_overlays', $overlays_clean);

        echo "<div class='updated'><p>Instellingen opgeslagen.</p></div>";
    }

    // === Ophalen ===
$background_ids = get_option('event_image_backgrounds', []);
$overlays = get_option('event_image_overlays', []);

    ?>
    <div class="wrap">
        <h1>Instellingen voor Event Afbeeldingen</h1>

        <form method="post">

        <!-- Achtergronden -->
        <h2>Achtergronden</h2>
        <input type="hidden" name="background_ids" id="background_ids" value="<?php echo esc_attr(implode(',', $background_ids)); ?>">

        <div id="bg_preview" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:10px;">
        <?php foreach ($background_ids as $id):
            $url = wp_get_attachment_image_url($id, 'thumbnail');
            if (!$url) continue;
        ?>
            <div class="bg-item" data-id="<?php echo esc_attr($id); ?>" style="position:relative; display:inline-block;">
                <img src="<?php echo esc_url($url); ?>" style="max-width:150px; display:block; border:1px solid #ccd0d4; padding:2px; background:#fff;">
                <button class="remove-bg" style="position:absolute; top:2px; right:2px; background:#dc3232; color:#fff; border:none; border-radius:50%; width:20px; height:20px; cursor:pointer;">x</button>
            </div>
        <?php endforeach; ?>
        </div>

        <p><button type="button" class="button" id="upload_bg">+ Achtergronden toevoegen</button></p>

        <hr>

        <!-- Overlays -->
        <h2>Overlays</h2>
        <table class="widefat fixed striped" id="overlay_table" style="max-width:980px;">
            <thead>
                <tr>
                    <th>Preview</th>
                    <th>Overlay ID</th>
                    <th>Logo 1</th>
                    <th>Logo 2</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
            <?php if(!empty($overlays)): ?>
                <?php foreach($overlays as $i => $ov):
                    $id = intval($ov['id']);
                    $url = $id ? wp_get_attachment_image_url($id,'medium') : '';
                ?>
                <tr class="overlay-row-main">
    <td class="overlay-preview-cell" style="width:130px;">
        <?php if($url): ?>
            <img src="<?php echo esc_url($url); ?>" style="max-width:120px; border:1px solid #ccc;">
        <?php endif; ?>
    </td>

    <td>
        <input type="number" name="overlays[<?php echo $i; ?>][id]" 
               class="overlay-id" value="<?php echo esc_attr($id); ?>" style="width:80px;">
        <button type="button" class="button select-overlay">Kies PNG</button>
    </td>

    <td>
        X:<input type="number" name="overlays[<?php echo $i; ?>][logo1_x]" value="<?php echo esc_attr($ov['logo1_x']); ?>" style="width:50px;">
        Y:<input type="number" name="overlays[<?php echo $i; ?>][logo1_y]" value="<?php echo esc_attr($ov['logo1_y']); ?>" style="width:50px;">
        W:<input type="number" name="overlays[<?php echo $i; ?>][logo_w]" value="<?php echo esc_attr($ov['logo_w']); ?>" style="width:50px;">
        H:<input type="number" name="overlays[<?php echo $i; ?>][logo_h]" value="<?php echo esc_attr($ov['logo_h']); ?>" style="width:50px;">
    </td>

    <td>
        X:<input type="number" name="overlays[<?php echo $i; ?>][logo2_x]" value="<?php echo esc_attr($ov['logo2_x']); ?>" style="width:50px;">
        Y:<input type="number" name="overlays[<?php echo $i; ?>][logo2_y]" value="<?php echo esc_attr($ov['logo2_y']); ?>" style="width:50px;">
    </td>

    <td>
        <button type="button" class="button button-link-delete remove-overlay">Verwijderen</button>
    </td>
</tr>

<tr class="overlay-row-text">
    <td colspan="5" style="background:#f9f9f9;">
        <strong>Teksten</strong>
        <div style="display:flex; gap:20px; margin-top:10px; flex-wrap:wrap;">

            <?php foreach(['title','date','hour','location'] as $txt):
                $x = $ov[$txt.'_x'];
                $y = $ov[$txt.'_y'];
                $font = $ov[$txt.'_font'];
                $size = $ov[$txt.'_size'];
            ?>

            <div style="border:1px solid #ddd; padding:10px; min-width:200px;">
                <strong><?php echo strtoupper($txt); ?></strong><br>

                X:<input type="number" name="overlays[<?php echo $i; ?>][<?php echo $txt; ?>_x]" value="<?php echo esc_attr($x); ?>" style="width:50px;">
                Y:<input type="number" name="overlays[<?php echo $i; ?>][<?php echo $txt; ?>_y]" value="<?php echo esc_attr($y); ?>" style="width:50px;"><br><br>

                Font:
                <select name="overlays[<?php echo $i; ?>][<?php echo $txt; ?>_font]">
                    <option value="Poppins-Black" <?php selected($font,'Poppins-Black'); ?>>Black</option>
                    <option value="Poppins-Bold" <?php selected($font,'Poppins-Bold'); ?>>Bold</option>
                    <option value="Poppins-Regular" <?php selected($font,'Poppins-Regular'); ?>>Regular</option>
                </select>
                <br><br>

                Grootte:
                <input type="number" name="overlays[<?php echo $i; ?>][<?php echo $txt; ?>_size]" value="<?php echo esc_attr($size); ?>" style="width:60px;">
            </div>

            <?php endforeach; ?>

        </div>
    </td>
</tr>                <?php endforeach; ?>
            <?php else: ?>
                <tr class="overlay-row">
                    <td class="overlay-preview-cell"></td>
                    <td><input type="number" name="overlays[0][id]" class="overlay-id" placeholder="Media ID"><button class="button select-overlay">Kies PNG</button></td>
                    <td>Logo1 X/Y/W/H</td>
                    <td>Logo2 X/Y</td>
                    <td>Teksten</td>
                    <td><button class="button remove-overlay">Verwijderen</button></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <p><button class="button" id="add_overlay_row">+ Overlay toevoegen</button></p>

        <?php submit_button(); ?>
        </form>

        <!-- Preview -->
        <h2>Live Preview</h2>
        <button class="button" id="preview_image">Preview Afbeelding</button>
        <div id="preview_container" style="margin-top:20px;">
            <img id="preview_img" src="" style="max-width:300px; display:none; border:1px solid #ccc;">
        </div>

    </div>
<?php
}
add_action('wp_ajax_event_image_preview', function(){
    $bg_ids   = !empty($_POST['background_ids']) ? array_map('intval', explode(',', $_POST['background_ids'])) : [];
    $overlays = get_option('event_image_overlays', []);

    if(empty($bg_ids) || empty($overlays)){
        wp_send_json_error('Geen achtergrond of overlays beschikbaar');
    }

    // Random achtergrond
    $bg_path = get_attached_file($bg_ids[array_rand($bg_ids)]);

    // Random overlay
    $overlay = $overlays[array_rand($overlays)];

    // Logo's
    $logo1 = plugin_dir_path(__FILE__).'assets/logo1.png';
    $logo2 = plugin_dir_path(__FILE__).'assets/logo2.png';

    $tmp_file = tempnam(sys_get_temp_dir(),'preview').'.png';

    create_event_image(
        $bg_path,
        $logo1,
        $logo2,
        'VISSERSHOVEKE VS FC DE PARAS',
        '25-02-2025',
        '14u50',
        'BEUKSTRAAT 6, OOSTROZEBEKE',
        $tmp_file,
        $overlay
    );

    if(file_exists($tmp_file)){
        $img = base64_encode(file_get_contents($tmp_file));
        @unlink($tmp_file);
        wp_send_json_success(['img'=>$img]);
    } else {
        wp_send_json_error('Preview kon niet worden gemaakt');
    }
});

