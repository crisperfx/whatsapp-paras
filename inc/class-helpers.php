<?php
if (!class_exists('Waha_Helpers')) {
    class Waha_Helpers {

        // Algemene functie om een logbestand te schrijven
        private static function write_log($filename, $message) {
            $log_dir = WP_CONTENT_DIR . '/waha-logs';
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }

            if (is_array($message) || is_object($message)) {
                $message = print_r($message, true);
            }

            $time = date('Y-m-d H:i:s');
            $log_file = $log_dir . '/' . $filename;
            file_put_contents($log_file, "[$time] $message\n", FILE_APPEND);
        }

        // Webhook-specifieke log
        public static function waha_log($message) {
            self::write_log('webhook_debug.log', $message);
        }

        // Wedstrijd-specifieke log
        public static function wedstrijd_debug($message) {
            self::write_log('wedstrijd_debug.log', $message);
        }

        // Line-up log
        public static function lineup_log($message) {
            self::write_log('lineup_debug.log', $message);
        }
        
        // Event verzenden log
        public static function event_log($message) {
            self::write_log('event_debug.log', $message);
        }
        
        // Matchtijd berekenen
        public static function get_match_time(int $event_id): array {
        $started_at   = (int) get_post_meta($event_id, '_match_started_at', true);
        $paused_at    = (int) get_post_meta($event_id, '_match_paused_at', true);
        $pause_total  = (int) get_post_meta($event_id, '_match_pause_total', true);
        $stopped_at   = (int) get_post_meta($event_id, '_match_stopped_at', true);

        if (!$started_at) return ['minutes' => 0, 'seconds' => 0];

        $now = $stopped_at ?: time();
        if ($paused_at) $now = $paused_at;

        $elapsed = $now - $started_at - $pause_total;

        return [
            'minutes' => floor($elapsed / 60),
            'seconds' => $elapsed % 60
        		];
    	}
    	
    	// tijden vastleggen match
    	public static function set_match_meta(int $event_id, array $meta) {
        foreach ($meta as $key => $val) {
            update_post_meta($event_id, $key, $val);
        	}
    	}
    	// Postmeta ophalen met default
        public static function get_meta(int $post_id, string $key, $default = '') {
            $val = get_post_meta($post_id, $key, true);
            return ($val !== '' && $val !== false) ? $val : $default;
        }

        // Postmeta bijwerken of verwijderen bij lege waarde
        public static function update_meta(int $post_id, string $key, $value) {
            if ($value === null || $value === false || $value === '') {
                delete_post_meta($post_id, $key);
            } else {
                update_post_meta($post_id, $key, $value);
            }
        }
        // event status ok
        public static function is_event_active($event_id): bool {
    		return true; // pas aan naar echte check
		}
		// helper adres van wedstrijd
		public static function get_sp_address($term_id) {
        	$option_key = 'taxonomy_' . $term_id;
        	$venue_data = get_option($option_key);
        	return !empty($venue_data['sp_address'])
            ? $venue_data['sp_address']
            : '';
    	}
	}
};