<?php
class Waha_Options {

    // Standaard optiesinitialiseren
    public static function init_defaults() {
        if (get_option('waha_webhook_enabled') === false) {
            update_option('waha_webhook_enabled', 1); 
        }
        if (get_option('waha_auto_user_scores') === false) {
            update_option('waha_auto_user_scores', 1);
        }
        if (get_option('waha_timer_enabled') === false) {
            update_option('waha_timer_enabled', 1);
        }
        if (get_option('enable_sportspress_tabs') === false) {
            update_option('enable_sportspress_tabs', 1); 
        }
        if (get_option('waha_our_team_id') === false) {
            update_option('waha_our_team_id', 3733); 
        }
        if (get_option('waha_lineup_instructions') === false) {
            update_option('waha_lineup_instructions', 
                "Identiteitskaart of rijbewijs mee!!\nScheenlappen, zwarte kousen en schoenen mee!!\nNiks mee = geen wedstrijd ;)\nDit kan nog elk moment wijzigen..."
            );
        }
        if (get_option('waha_whatsapp_send_enabled') === false) {
		    update_option('whatsapp_send_enabled', 1);
		}
        
    }

    // Webhook options
    public static function is_webhook_enabled(): bool {
        return (bool) get_option('waha_webhook_enabled', 1); 
    }
    public static function set_webhook_enabled(bool $enabled) {
        update_option('waha_webhook_enabled', $enabled ? 1 : 0);
    }
    
    // Auto score options
    public static function is_auto_user_scores_enabled() {
        $enabled = get_option('waha_auto_user_scores', 1);
        return (bool) $enabled;
    }
    public static function set_auto_user_scores_enabled(bool $enabled) {
        update_option('waha_auto_user_scores', $enabled ? 1 : 0);
    }
    
    // Real time timer options
    public static function is_timer_enabled(): bool {
        return (bool) get_option('waha_timer_enabled', 1);
    }

    public static function set_timer_enabled(bool $enabled) {
        update_option('waha_timer_enabled', $enabled ? 1 : 0);
    }
    
    // CSV import optie
	public static function is_csv_import_enabled(): bool {
    	return (bool) get_option('waha_csv_import_players', 0);
	}

	public static function set_csv_import_enabled(bool $enabled) {
    	update_option('waha_csv_import_players', $enabled ? 1 : 0);
	}
    
    // Module options
    public static function is_module_enabled(string $module_name): bool {
        return (bool) get_option('enable_' . $module_name, 1);
    }

    public static function set_module_enabled(string $module_name, bool $enabled) {
        update_option('enable_' . $module_name, $enabled ? 1 : 0);
    }
    
    public static function get_options(): array {
        return [
            'enable_sportspress-tabs' => self::is_module_enabled('sportspress-tabs'),
            'enable_csv_import_players' => self::is_csv_import_enabled(),
            'enable_opstelling_ffve' => self::is_module_enabled('opstelling-ffve'),
        ];
    }
    
    // Onze team-ID centraal
    public static function get_team_id(): int {
        return intval(get_option('waha_our_team_id', 3733));
    }
    public static function set_team_id(int $team_id) {
        update_option('waha_our_team_id', $team_id);
    }
    
    // Lineup-standaard text
    public static function get_lineup_instructions(): string {
        return get_option('waha_lineup_instructions', 
            "Identiteitskaart of rijbewijs mee!!\nScheenlappen, zwarte kousen en schoenen mee!!\nNiks mee = geen wedstrijd ;)\nDit kan nog elk moment wijzigen..."
        );
    }
    public static function set_lineup_instructions(string $text) {
        update_option('waha_lineup_instructions', $text);
    }
    // WhatsApp verzenden opties
	public static function is_whatsapp_send_enabled(): bool {
	    return (bool) get_option('whatsapp_send_enabled', 1);
	}

	public static function set_whatsapp_send_enabled(bool $enabled) {
	    update_option('whatsapp_send_enabled', $enabled ? 1 : 0);
	}
}

// Init default waarde bij plugin load
Waha_Options::init_defaults();