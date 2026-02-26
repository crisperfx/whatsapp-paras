<?php
function whatsapp_paras_webhook_tab() {
    $enabled = get_option('whatsapp_webhook_enabled', '0') === '1';
    $response_output = '';
    $log_file = WP_CONTENT_DIR . '/waha-logs/webhook_debug.log';

    // Alleen actie bij POST uitvoeren en webhook enabled
    if ($enabled && isset($_POST['send_webhook_test'])) {

        $url = home_url('/wp-json/waha-webhook/v1/handler');

        $payload = [
            'id' => 'evt_test_001',
            'timestamp' => round(microtime(true) * 1000),
            'event' => 'event.response',
            'session' => 'default',
            'me' => [
                'id' => '32495675141@c.us',
                'pushName' => 'Dempsy',
                'jid' => '32495675141:45@s.whatsapp.net',
            ],
            'payload' => [
            'id' => 'false_120363348593759502@g.us_testwebhook_179323524931746@lid',
            'timestamp' => 1753160687,
            'from' => '120363348593759502@g.us',
            'fromMe' => false,
            'source' => 'app',
            'to' => '32472654409@c.us',
            'participant' => '32499236332@s.whatsapp.net',
            'eventCreationKey' => [
                'id' => 'false_120363348593759502@g.us_testwebhook_179323524931746@lid',
                'to' => '120363348593759502@g.us',
                'from' => '32499236332@s.whatsapp.net',  
                'fromMe' => false,
            ],
            'eventResponse' => [
                'response' => 'GOING',
                'timestampMs' => 1753160686357,
                'extraGuestCount' => 0,
                ],
            ],
        ];

        $response = wp_remote_post($url, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            $response_output = '<div class="notice notice-error"><p>Fout: ' . esc_html($response->get_error_message()) . '</p></div>';
        } else {
            $body = json_decode($response['body'], true);
            $response_output = '<div class=""><p>Webhook test verzonden!</p><pre>' . esc_html(print_r($body, true)) . '</pre></div>';
            if (file_exists($log_file)) {
            $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $last_lines = array_slice($lines, -6); // laatste 15 regels
            $response_output .= '<pre style="background:#f7f7f7; padding:10px; border:1px solid #ddd;">' . esc_html(implode("\n", $last_lines)) . '</pre>';
        }
        }
    }

    ?>
    <div>
        <?php if (!$enabled): ?>
            <div class=""><p>⚠️ Webhook test is uitgeschakeld.</p></div>
        <?php else: ?>
            <form method="post">
                <?php submit_button('Voer Webhook Test uit', 'secondary', 'send_webhook_test'); ?>
            </form>
        <?php endif; ?>

        <div style="margin-top:20px;">
            <?php echo $response_output; ?>
        </div>
    </div>
    <?php
}