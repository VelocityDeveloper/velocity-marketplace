<?php
/**
 * Plugin Name: Velocity Marketplace
 * Description: Plugin Marketplace oleh Velocity Developer.
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
    $plugin = new \VelocityMarketplace\Core\Plugin();
    $plugin->run();
}
add_action('plugins_loaded', 'vmp_init_plugin');

function vmp_register_wp_store_function_aliases()
{
    if (defined('WP_STORE_VERSION') || function_exists('wp_store_init')) {
        return;
    }

    $compat_file = VMP_PATH . 'compat/wp-store-functions.php';
    if (file_exists($compat_file)) {
        require_once $compat_file;
    }
}
add_action('plugins_loaded', 'vmp_register_wp_store_function_aliases', 20);

register_activation_hook(__FILE__, function () {
    $upgrade = new \VelocityMarketplace\Core\Upgrade();
    $upgrade->activate();

    $post_types = new \VelocityMarketplace\Core\PostTypes();
    $post_types->register_product_type();
    $post_types->register_order_type();

    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
