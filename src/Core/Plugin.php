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
        $upgrade = new Upgrade();
        $upgrade->register();

        $post_types = new PostTypes();
        $post_types->register();

        $product_fields = new \VelocityMarketplace\Modules\Product\ProductFields();
        $product_fields->register();

        $duitku_callback_listener = new \VelocityMarketplace\Modules\Payment\DuitkuCallbackListener();
        $duitku_callback_listener->register();

        if (is_admin()) {
            $meta_box = new \VelocityMarketplace\Modules\Product\ProductMetaBox();
            $meta_box->register();

            $order_admin = new \VelocityMarketplace\Modules\Order\OrderAdmin();
            $order_admin->register();

            $review_admin = new \VelocityMarketplace\Modules\Review\ReviewAdmin();
            $review_admin->register();

            $star_seller_admin = new \VelocityMarketplace\Modules\Review\StarSellerAdmin();
            $star_seller_admin->register();

            $store_bank_admin = new \VelocityMarketplace\Modules\Profile\StoreBankAdmin();
            $store_bank_admin->register();

            $coupon_admin = new \VelocityMarketplace\Modules\Coupon\CouponAdmin();
            $coupon_admin->register();

            $settings_page = new SettingsPage();
            $settings_page->register();
        }
    }

    private function load_api()
    {
        $products = new \VelocityMarketplace\Modules\Product\ProductController();
        add_action('rest_api_init', [$products, 'register_routes']);

        $cart = new \VelocityMarketplace\Modules\Cart\CartController();
        add_action('rest_api_init', [$cart, 'register_routes']);

        $checkout = new \VelocityMarketplace\Modules\Checkout\CheckoutController();
        add_action('rest_api_init', [$checkout, 'register_routes']);

        $captcha = new \VelocityMarketplace\Modules\Captcha\CaptchaController();
        add_action('rest_api_init', [$captcha, 'register_routes']);

        $wishlist = new \VelocityMarketplace\Modules\Wishlist\WishlistController();
        add_action('rest_api_init', [$wishlist, 'register_routes']);

        $shipping = new \VelocityMarketplace\Modules\Shipping\ShippingController();
        add_action('rest_api_init', [$shipping, 'register_routes']);

        $coupon = new \VelocityMarketplace\Modules\Coupon\CouponController();
        add_action('rest_api_init', [$coupon, 'register_routes']);

        $profile = new \VelocityMarketplace\Modules\Profile\ProfileController();
        add_action('rest_api_init', [$profile, 'register_routes']);

        $settings = new \VelocityMarketplace\Core\SettingsController();
        add_action('rest_api_init', [$settings, 'register_routes']);
    }

    private function load_frontend()
    {
        $product_archive = new \VelocityMarketplace\Modules\Product\ProductArchive();
        $product_archive->register();

        $template = new \VelocityMarketplace\Frontend\Template();
        $template->register();

        $assets = new \VelocityMarketplace\Frontend\Assets();
        $assets->register();

        $shortcode = new \VelocityMarketplace\Frontend\Shortcode();
        $shortcode->register();

        $account = new \VelocityMarketplace\Modules\Account\Account();
        $account->register();

        $actions = new \VelocityMarketplace\Modules\Account\Actions();
        $actions->register();
    }
}

