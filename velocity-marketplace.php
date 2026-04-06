<?php
/**
 * Plugin Name: VD Marketplace
 * Description: Addon marketplace untuk VD Store.
 * Version: 1.0.0
 * Author: Velocity Developer
 * Author URI: https://velocitydeveloper.com/
 * Text Domain: velocity-marketplace
 */

if (!defined('ABSPATH')) {
    exit;
}

define('VMP_VERSION', '1.0.0');
define('VMP_PATH', plugin_dir_path(__FILE__));
define('VMP_URL', plugin_dir_url(__FILE__));
define('VMP_SETTINGS_OPTION', 'vmp_settings');
define('VMP_PAGES_OPTION', 'vmp_pages');
define('VMP_DB_VERSION_OPTION', 'vmp_db_version');

function vmp_has_vd_store_dependency()
{
    return defined('WP_STORE_VERSION')
        || defined('WP_STORE_PATH')
        || function_exists('wp_store_init')
        || class_exists('\WpStore\Core\Plugin');
}

function vmp_dependency_error_message()
{
    return __('VD Marketplace membutuhkan plugin VD Store yang aktif. Aktifkan VD Store terlebih dahulu karena VD Marketplace sekarang hanya berjalan sebagai addon marketplace di atas core commerce VD Store.', 'velocity-marketplace');
}

function vmp_render_dependency_notice()
{
    if (!current_user_can('activate_plugins')) {
        return;
    }

    echo '<div class="notice notice-error"><p>' . esc_html(vmp_dependency_error_message()) . '</p></div>';
}

spl_autoload_register(function ($class) {
    $prefix = 'VelocityMarketplace\\';
    $base_dir = VMP_PATH . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

function vmp_init_plugin()
{
    if (!vmp_has_vd_store_dependency()) {
        add_action('admin_notices', 'vmp_render_dependency_notice');
        add_action('network_admin_notices', 'vmp_render_dependency_notice');
        return;
    }

    $plugin = new \VelocityMarketplace\Core\Plugin();
    $plugin->run();
}
add_action('plugins_loaded', 'vmp_init_plugin');

register_activation_hook(__FILE__, function () {
    if (!vmp_has_vd_store_dependency()) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        deactivate_plugins(plugin_basename(__FILE__));

        wp_die(
            esc_html(vmp_dependency_error_message()),
            esc_html__('VD Marketplace membutuhkan VD Store', 'velocity-marketplace'),
            [
                'back_link' => true,
            ]
        );
    }

    $upgrade = new \VelocityMarketplace\Core\Upgrade();
    $upgrade->activate();

    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
