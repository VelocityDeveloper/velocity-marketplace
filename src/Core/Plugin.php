<?php

namespace VelocityMarketplace\Core;

class Plugin
{
    public function run()
    {
        $this->load_core();
        $this->load_api();
        $this->load_frontend();
    }

    private function load_core()
    {
        $installer = new Installer();
        add_action('init', [$installer, 'maybe_upgrade']);

        $post_types = new PostTypes();
        $post_types->register();

        $product_fields = new \VelocityMarketplace\Support\ProductFields();
        $product_fields->register();

        if (is_admin()) {
            $meta_box = new \VelocityMarketplace\Admin\ProductMetaBox();
            $meta_box->register();

            $settings_page = new \VelocityMarketplace\Admin\SettingsPage();
            $settings_page->register();
        }
    }

    private function load_api()
    {
        $products = new \VelocityMarketplace\Api\ProductController();
        add_action('rest_api_init', [$products, 'register_routes']);

        $cart = new \VelocityMarketplace\Api\CartController();
        add_action('rest_api_init', [$cart, 'register_routes']);

        $checkout = new \VelocityMarketplace\Api\CheckoutController();
        add_action('rest_api_init', [$checkout, 'register_routes']);

        $captcha = new \VelocityMarketplace\Api\CaptchaController();
        add_action('rest_api_init', [$captcha, 'register_routes']);

        $wishlist = new \VelocityMarketplace\Api\WishlistController();
        add_action('rest_api_init', [$wishlist, 'register_routes']);
    }

    private function load_frontend()
    {
        $assets = new \VelocityMarketplace\Frontend\Assets();
        $assets->register();

        $shortcode = new \VelocityMarketplace\Frontend\Shortcode();
        $shortcode->register();

        $account = new \VelocityMarketplace\Frontend\Account();
        $account->register();

        $actions = new \VelocityMarketplace\Frontend\Actions();
        $actions->register();
    }
}
