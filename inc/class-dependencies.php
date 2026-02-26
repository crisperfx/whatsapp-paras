<?php
namespace ParasCore;

if (!defined('ABSPATH')) {
    exit;
}

class Dependencies
{
    private static array $required = [
        [
            'name'  => 'Voetbal-core',
            'slug'  => 'sportspress-pro/sportspress-pro.php',
            'class' => null,
            'zip'   => 'lib/plugins/sportspress-pro.zip', // bundled
        ],
        [
            'name'  => 'Opstellingen',
            'slug'  => 'visual-football-formation-ve-1/init.php',
            'class' => null,
            'zip'   => 'lib/plugins/visual-football-formation-ve-1.zip',
        ],
        [
            'name'  => 'Elementor',
            'slug'  => 'elementor/elementor.php',
            'class' => 'Elementor\\Plugin',
            'zip'   => null, // via WP repo
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | CHECK
    |--------------------------------------------------------------------------
    */

    public static function check(): bool
    {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        foreach (self::$required as $plugin) {

            if (!is_plugin_active($plugin['slug'])) {
                return false;
            }

            if (!empty($plugin['class']) && !class_exists($plugin['class'])) {
                return false;
            }
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN NOTICE
    |--------------------------------------------------------------------------
    */

    public static function admin_notice(): void
    {
        add_action('admin_notices', function () {

            if (self::check()) {
                return;
            }

            include_once ABSPATH . 'wp-admin/includes/plugin.php';

            echo '<div class="notice notice-error">';
            echo '<p><strong>Core plugin FC De Paras is vergrendeld.</strong></p>';
            echo '<p>De volgende plugins zijn vereist:</p>';
            echo '<ul>';

            foreach (self::$required as $plugin) {

                $installed = file_exists(WP_PLUGIN_DIR . '/' . dirname($plugin['slug']));
                $active    = is_plugin_active($plugin['slug']);

                echo '<li><strong>' . esc_html($plugin['name']) . '</strong> - ';

                // NIET GEÏNSTALLEERD
                if (!$installed) {

                    if (!empty($plugin['zip']) && file_exists(plugin_dir_path(__DIR__) . $plugin['zip'])) {

    $slug = basename(dirname($plugin['slug'])); // sportspress-pro, visual-football-formation-ve-1

    $install_url = wp_nonce_url(
        admin_url('admin-post.php?action=paras_install_plugin&plugin=' . $slug),
        'paras_install_plugin'
    );

    echo '<a href="' . esc_url($install_url) . '">Installeren</a>';
} else {

                        $repo_url = admin_url('plugin-install.php?s=' . urlencode($plugin['name']) . '&tab=search&type=term');
                        echo '<a href="' . esc_url($repo_url) . '">Installeren</a>';
                    }

                }
                // GEÏNSTALLEERD MAAR NIET ACTIEF
                elseif (!$active) {

                    $activate_url = wp_nonce_url(
                        admin_url('plugins.php?action=activate&plugin=' . $plugin['slug']),
                        'activate-plugin_' . $plugin['slug']
                    );

                    echo '<a href="' . esc_url($activate_url) . '">Activeren</a>';
                }
                // ACTIEF MAAR CLASS PROBLEEM
                elseif (!empty($plugin['class']) && !class_exists($plugin['class'])) {

                    echo 'Geïnstalleerd maar fout bij laden.';
                }
				if ($installed && $active) {
    echo ' ✅ Geïnstalleerd & Actief';
}
                echo '</li>';
            }

            echo '</ul>';
            echo '</div>';
        });
    }
}