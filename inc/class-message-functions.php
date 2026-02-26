<?php

if (!defined("ABSPATH")) {
    exit();
}

class WA_Service
{
    private string $host;
    private string $session;
    private string $api_key;
    private string $basic_auth;

    public function __construct()
    {
        $this->host = rtrim(get_option("whatsapp_host"), "/");
        $this->session = get_option("whatsapp_session");
        $this->api_key = get_option("whatsapp_api_key");
        $this->basic_auth = get_option("whatsapp_auth");
    }

    /**
     * Verstuur een tekstbericht
     */
    public function send_text(string $chat_id, string $text)
    {
        return $this->request("/api/sendText", [
            "chatId" => $chat_id,
            "text" => $text,
            "linkPreview" => true,
        ]);
    }

    /**
         * Verstuur een WhatsApp event en sla meta op
         */
        public static function send_whatsapp_event(
            int $post_id,
            string $chatId,
            string $name,
            string $description,
            string $startTime,
            string $locationName
        ): array|WP_Error {
			$image_url = paras_generate_event_image($post_id);

    if (!$image_url) {
        Waha_Helpers::event_log("Afbeelding kon niet gegenereerd worden voor post $post_id.");
        return new WP_Error('image_failed', 'Afbeelding kon niet gegenereerd worden.');
    }
			
            // Nieuwe WA_Service instantie
            $wa = new WA_Service();

            $event_data = [
                "chatId" => $chatId,
                "reply_to" => null,
                "event" => [
                    "name" => $name,
                    "description" => $description,
                    "startTime" => $startTime,
                    "endTime" => null,
                    "location" => [
                        "name" => $locationName,
                    ],
                    "extraGuestsAllowed" => false,
                ],
            ];

            // Verstuur via WA_Service
            $response = $wa->request('/api/' . $wa->session . '/events', $event_data);

            // Response controleren en meta opslaan
            if (!is_wp_error($response) && isset($response['id'])) {
                $fullId = $response['id'];
                $parts = explode('_', $fullId);
                if (count($parts) >= 3) {
                    $whatsappId = $parts[2];
                    self::update_meta($post_id, '_whatsapp_event_id', $whatsappId);
                    self::update_meta($post_id, '_whatsapp_event_sent', 1);
                    Waha_Helpers::event_log("WhatsApp Event succesvol verstuurd: Event ID $whatsappId");
                }
            } elseif (is_wp_error($response)) {
                Waha_Helpers::event_log("WhatsApp Event Error: " . $response->get_error_message());
            }

            return $response;
        }

    /**
     * Verstuur afbeelding met caption
     */
    public function send_image(
        string $chat_id,
        string $caption,
        array $file_payload
    ) {
        if (empty($file_payload)) {
            return new WP_Error("no_file", "Geen afbeelding opgegeven.");
        }

        return $this->request("/api/sendFile", [
            "chatId" => $chat_id,
            "caption" => $caption,
            "file" => $file_payload,
        ]);
    }

    /**
     * Centrale request handler
     */
    private function request(string $endpoint, array $body)
    {
        // Controleer configuratie en log ontbrekende velden
    $missing = [];
    if (empty($this->host))      $missing[] = 'host';
    if (empty($this->session))   $missing[] = 'session';
    if (empty($this->api_key))   $missing[] = 'api_key';
    if (empty($this->basic_auth)) $missing[] = 'basic_auth';

    if (!empty($missing)) {
        $msg = 'WA_Service configuratie incompleet, ontbrekende velden: ' . implode(', ', $missing);
        error_log($msg);
        return new WP_Error("wa_config_missing", $msg);
    }
		$credentials = base64_encode(get_option('whatsapp_auth'));
        $response = wp_remote_post($this->host . $endpoint, [
            "headers" => [
                "Authorization" => "Basic " . $credentials,
                "Accept" => "application/json",
                "Content-Type" => "application/json",
                "X-Api-Key" => $this->api_key,
            ],
            "body" => wp_json_encode(
                array_merge($body, [
                    "session" => $this->session,
                ])
            ),
            "timeout" => 20,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
    	$body_response = wp_remote_retrieve_body($response);

        if ($code === 200 || $code === 201) {
        return ["success" => true, "message" => "no errors", "data" => json_decode($body_response, true)];
    }

    return new WP_Error(
        "wa_http_error",
        "WhatsApp API fout (" . $code . ") - Response: " . $body_response
    );

    }
}
