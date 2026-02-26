<?php
/**
 * Plugin Name: Core plugin FC De Paras
 * Description: addons website: Whatsapp, Opstellingen, aangepaste menuknoppen etc...
 * Version: 2.1
 * Author: Dempsy Degrande
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| DEPENDENCIES
|--------------------------------------------------------------------------
*/

require_once plugin_dir_path(__FILE__) . 'inc/class-dependencies.php';

use ParasCore\Dependencies;

require_once plugin_dir_path(__FILE__) . 'lib/plugin-update-checker/plugin-update-checker.php';
require_once plugin_dir_path(__FILE__) . 'admin-modules/includes/image-generator.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

add_action('admin_post_paras_install_plugin', function() {

    if (!current_user_can('install_plugins')) {
        wp_die('Geen rechten om plugins te installeren.');
    }

    if (!isset($_GET['plugin']) || !wp_verify_nonce($_GET['_wpnonce'], 'paras_install_plugin')) {
        wp_die('Nonce mismatch.');
    }

    $plugin_slug = sanitize_text_field($_GET['plugin']);

    // Map plugin slug naar ZIP pad
    $plugins = [
        'sportspress-pro' => plugin_dir_path(__FILE__) . 'lib/plugins/sportspress-pro.zip',
        'visual-football-formation-ve-1' => plugin_dir_path(__FILE__) . 'lib/plugins/visual-football-formation-ve-1.zip',
    ];

    if (!isset($plugins[$plugin_slug]) || !file_exists($plugins[$plugin_slug])) {
        wp_die('Plugin ZIP niet gevonden.');
    }

    include_once ABSPATH . 'wp-admin/includes/file.php';
    include_once ABSPATH . 'wp-admin/includes/misc.php';
    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $upgrader = new \Plugin_Upgrader(new \WP_Ajax_Upgrader_Skin());
    $result = $upgrader->install($plugins[$plugin_slug]);

    if (is_wp_error($result)) {
        wp_die('Installatie mislukt: ' . $result->get_error_message());
    }

    wp_redirect(admin_url('plugins.php?plugin-installed=' . $plugin_slug));
    exit;
});

/*
|--------------------------------------------------------------------------
| PLUGIN INIT
|--------------------------------------------------------------------------
*/

add_action('plugins_loaded', function () {

    // Stop hier als vereiste plugins ontbreken
    if (!Dependencies::check()) {
        Dependencies::admin_notice();
        return;
    }

    // Modules pas laden als alles OK is
    require_once plugin_dir_path(__FILE__) . 'inc/class-paras-loader.php';
    require_once plugin_dir_path(__FILE__) . 'inc/class-options.php';
    require_once plugin_dir_path(__FILE__) . 'inc/class-helpers.php';
    require_once plugin_dir_path(__FILE__) . 'inc/class-message-functions.php';
    require_once plugin_dir_path(__FILE__) . 'inc/class-admin.php';

    Paras_Loader::instance()->init();
});


/*
|--------------------------------------------------------------------------
| ACTIVATION HOOK (GEEN DEPENDENCY BLOCK)
|--------------------------------------------------------------------------
*/

register_activation_hook(__FILE__, 'whatsapp_paras_activate');

function whatsapp_paras_activate() {
    whatsapp_paras_plugin_activate();
}

function whatsapp_paras_plugin_activate() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'daextvffve_formation';

    for ($i = 1; $i <= 15; $i++) {

        $number_column = 'player_number_' . $i;
        $name_column   = 'player_name_' . $i;

        if (!$wpdb->get_var("SHOW COLUMNS FROM `$table_name` LIKE '$number_column'")) {
            $wpdb->query("ALTER TABLE `$table_name` ADD `$number_column` VARCHAR(255) DEFAULT NULL;");
        }

        if (!$wpdb->get_var("SHOW COLUMNS FROM `$table_name` LIKE '$name_column'")) {
            $wpdb->query("ALTER TABLE `$table_name` ADD `$name_column` VARCHAR(255) DEFAULT NULL;");
        }
    }
}

register_activation_hook(__FILE__, function() {

    // Laad cron-module zodat schedule bekend is
    require_once plugin_dir_path(__FILE__) . 'modules/cron-tasks.php';

    if (!wp_next_scheduled('whatsapp_paras_send_upcoming_events')) {
        wp_schedule_event(time(), 'every_minute', 'whatsapp_paras_send_upcoming_events');
    }

    if (!wp_next_scheduled('whatsapp_paras_send_event_5days')) {
        wp_schedule_event(time(), 'every_minute', 'whatsapp_paras_send_event_5days');
    }
});

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('whatsapp_paras_send_upcoming_events');
    wp_clear_scheduled_hook('whatsapp_paras_send_event_5days');
});

/*
|--------------------------------------------------------------------------
| CHANGELOG LINK
|--------------------------------------------------------------------------
*/

add_filter('plugin_row_meta', 'whatsapp_paras_row_meta', 10, 2);

function whatsapp_paras_row_meta($links, $file) {

    if ($file === plugin_basename(__FILE__)) {
        $links[] = '<a href="' . admin_url('admin.php?page=whatsapp-paras-changelog') . '">Bekijk changelog</a>';
    }

    return $links;
}


/*
|--------------------------------------------------------------------------
| OG TAGS (alleen als dependencies OK)
|--------------------------------------------------------------------------
*/

add_action('wp_head', function () {

    if (!class_exists('\ParasCore\Dependencies') || !Dependencies::check()) {
        return;
    }

    if (is_singular('sp_event')) {

        global $post;

        $img   = get_the_post_thumbnail_url($post->ID, 'full');
        $title = get_the_title($post->ID);
        $event_date = date_i18n('j F Y, H:i', strtotime($post->post_date));

        if ($title) {
            echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        }

        if ($event_date) {
            echo '<meta property="og:description" content="Wedstrijd op ' . esc_attr($event_date) . '" />' . "\n";
        }

        if ($img) {
            echo '<meta property="og:image" content="' . esc_url($img) . '" />' . "\n";
        }

        echo '<meta property="og:type" content="website" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink($post->ID)) . '" />' . "\n";
    }
});

// Update checker

$updateChecker = PucFactory::buildUpdateChecker(
    'https://updates.crisperfx.myds.me/whatsapp_paras.json', // JSON endpoint
    __FILE__, // hoofdbestand plugin
    'core-plugin-fc-de-paras' // unieke plugin slug
);