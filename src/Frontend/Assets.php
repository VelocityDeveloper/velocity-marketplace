<?php

namespace VelocityMarketplace\Frontend;

use VelocityMarketplace\Support\Settings;

class Assets
{
    public function register()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue()
    {
        if (!$this->should_enqueue()) {
            return;
        }

        wp_register_script(
            'alpinejs',
            'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js',
            [],
            null,
            true
        );
        wp_add_inline_script('alpinejs', 'window.deferLoadingAlpineJs = true;', 'before');

        wp_enqueue_style(
            'velocity-marketplace-css',
            VMP_URL . 'assets/css/style.css',
            [],
            VMP_VERSION
        );

        wp_enqueue_script(
            'velocity-marketplace-js',
            VMP_URL . 'assets/js/marketplace.js',
            ['alpinejs'],
            VMP_VERSION,
            true
        );

        wp_enqueue_media();
        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }

        $pages = get_option('velocity_marketplace_pages', []);
        $catalog_url = $this->resolve_page_url($pages, 'katalog', '/katalog/');
        $cart_url = $this->resolve_page_url($pages, 'keranjang', '/keranjang/');
        $checkout_url = $this->resolve_page_url($pages, 'checkout', '/checkout/');
        $profile_url = $this->resolve_page_url($pages, 'myaccount', '/myaccount/');
        $currency = Settings::currency();
        $currency_symbol = Settings::currency_symbol();
        $payment_methods = Settings::payment_methods();

        wp_localize_script('velocity-marketplace-js', 'vmpSettings', [
            'restUrl' => esc_url_raw(rest_url('velocity-marketplace/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'catalogUrl' => esc_url_raw($catalog_url),
            'cartUrl' => esc_url_raw($cart_url),
            'checkoutUrl' => esc_url_raw($checkout_url),
            'profileUrl' => esc_url_raw($profile_url),
            'currency' => $currency,
            'currencySymbol' => $currency_symbol,
            'paymentMethods' => $payment_methods,
            'isLoggedIn' => is_user_logged_in(),
        ]);
    }

    private function should_enqueue()
    {
        if (is_admin()) {
            return false;
        }

        if (is_page()) {
            global $post;
            if ($post && isset($post->post_content)) {
                $content = (string) $post->post_content;
                $shortcodes = [
                    'velocity_marketplace_catalog',
                    'velocity_marketplace_products',
                    'velocity_marketplace_product_card',
                    'velocity_marketplace_thumbnail',
                    'velocity_marketplace_price',
                    'velocity_marketplace_add_to_cart',
                    'velocity_marketplace_add_to_wishlist',
                    'velocity_marketplace_cart',
                    'velocity_marketplace_checkout',
                    'velocity_marketplace_profile',
                    'velocity_marketplace_tracking',
                    'vm_catalog',
                    'vm_products',
                    'vm_product_card',
                    'vm_thumbnail',
                    'vm_price',
                    'vm_add_to_cart',
                    'vm_add_to_wishlist',
                    'vm_cart',
                    'vm_checkout',
                    'vm_profile',
                    'vm_tracking',
                ];

                foreach ($shortcodes as $shortcode) {
                    if (has_shortcode($content, $shortcode)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function resolve_page_url($pages, $key, $fallback)
    {
        if (is_array($pages) && isset($pages[$key])) {
            $url = get_permalink((int) $pages[$key]);
            if ($url) {
                return $url;
            }
        }

        return site_url($fallback);
    }
}
