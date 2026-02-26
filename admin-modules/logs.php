<?php
function whatsapp_paras_logs_page() {

    $log_dir = WP_CONTENT_DIR . '/waha-logs';
    $logs = [
        'Event Logs'     => $log_dir . '/event_debug.log',
        'Lineup Logs'    => $log_dir . '/lineup_debug.log',
        'Wedstrijd Logs' => $log_dir . '/wedstrijd_debug.log',
    ];

    // Wis logs indien gevraagd
    if (isset($_POST['clear_log']) && isset($logs[$_POST['clear_log']])) {
        file_put_contents($logs[$_POST['clear_log']], '');
        echo '<div class="updated"><p>' . esc_html($_POST['clear_log']) . ' gewist.</p></div>';
    }

    ?>

    <div class="wrap">
        <h1>WhatsApp Paras Logs</h1>

        <?php foreach ($logs as $label => $file_path) : 
            $content = '';
            if (file_exists($file_path)) {
                $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $lines = array_reverse($lines); // laatste regel bovenaan
                $content = implode("\n", $lines);
            }
        ?>
            <h2><?php echo esc_html($label); ?></h2>

            <form method="post" style="margin-bottom:10px;">
                <input type="hidden" name="clear_log" value="<?php echo esc_attr($label); ?>">
                <input type="submit" class="button button-secondary" value="Wis <?php echo esc_html($label); ?>">
            </form>

            <textarea id="log-<?php echo sanitize_title($label); ?>" style="width:100%; height:300px; font-family: monospace;" readonly><?php
                echo esc_textarea($content);
            ?></textarea>
        <?php endforeach; ?>
    </div>

    <script>
        const logs = <?php echo wp_json_encode(array_map('sanitize_title', array_keys($logs))); ?>;

        setInterval(function() {
            logs.forEach(function(logId) {
                fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=whatsapp_paras_get_logs&log=' + logId)
                    .then(response => response.text())
                    .then(data => {
                        const textarea = document.getElementById('log-' + logId);
                        if (textarea) textarea.value = data;
                    });
            });
        }, 5000);
    </script>

<?php
}

// AJAX handler voor refresh per log
add_action('wp_ajax_whatsapp_paras_get_logs', function() {

    $log_dir = WP_CONTENT_DIR . '/waha-logs';
    $logs = [
        'event-logs'     => $log_dir . '/event_debug.log',
        'lineup-logs'    => $log_dir . '/lineup_debug.log',
        'wedstrijd-logs' => $log_dir . '/wedstrijd_debug.log',
    ];

    $log_key = sanitize_key($_GET['log']);
    if (!isset($logs[$log_key]) || !file_exists($logs[$log_key])) {
        wp_die('');
    }

    $lines = file($logs[$log_key], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_reverse($lines);
    echo implode("\n", $lines);
    wp_die();
});