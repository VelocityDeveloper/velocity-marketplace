<?php
/**
 * Plugin Name: Velocity Marketplace
 * Description: Marketplace plugin berbasis REST API + Alpine.js untuk migrasi vmplace.
 * Version: 1.3.0
 * Author: Velocity Developer
 * Text Domain: velocity-marketplace
 */

if (!defined('ABSPATH')) {
    exit;
}

define('VMP_VERSION', '1.3.0');
define('VMP_PATH', plugin_dir_path(__FILE__));
define('VMP_URL', plugin_dir_url(__FILE__));

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

register_activation_hook(__FILE__, function () {
    $installer = new \VelocityMarketplace\Core\Installer();
    $installer->activate();

    $post_types = new \VelocityMarketplace\Core\PostTypes();
    $post_types->register_product_type();
    $post_types->register_order_type();

    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
