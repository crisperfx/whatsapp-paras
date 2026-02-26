<?php
class Paras_Loader {

    private static $instance = null;
    private array $options;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Haal opties uit je class-options.php
        $this->options = Waha_Options::get_options(); 
    }

    public function init() {
    if (!class_exists('\Elementor\Plugin') || !class_exists('SportsPress')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo 'Core plugin FC De Paras: Vereiste plugins niet actief. Zorg dat <strong>Elementor</strong> en <strong>SportsPress</strong> geïnstalleerd en geactiveerd zijn.';
            echo '</p></div>';
        });
        return; // stop verdere module loading
    }
        $this->load_modules();
    }

    private function load_modules() {
        // 1️Algemene modules laden
        $module_files = glob(plugin_dir_path(__DIR__) . 'modules/*.php');
        foreach ($module_files as $file) {
            $module_name = basename($file, '.php');

            // Check of module enabled is in opties
            if (!isset($this->options['enable_' . $module_name]) || $this->options['enable_' . $module_name]) {
    			require_once $file;
			}
        }
        
        // register elementor widgets
		add_action('elementor/widgets/register', function($widgets_manager) {
        	require_once plugin_dir_path(__DIR__) . 'modules/elementor-widgets/elementor-countdown-widget.php';
        	$widgets_manager->register(new \Elementor_Sportspress_Countdown_Widget());
   		});
   		
        // 2️Admin-only modules laden
        if (is_admin()) {
            $admin_files = glob(plugin_dir_path(__DIR__) . 'admin-modules/*.php');
            foreach ($admin_files as $file) {
                $module_name = basename($file, '.php');
           	 	if (!isset($this->options['enable_' . $module_name]) || $this->options['enable_' . $module_name]) {
    				require_once $file;
				}
            }
        }
    }
}