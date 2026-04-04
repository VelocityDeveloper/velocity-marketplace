<?php

namespace VelocityMarketplace\Frontend;

use VelocityMarketplace\Support\Contract;
use VelocityMarketplace\Support\Settings;
use VelocityMarketplace\Modules\Product\ProductData;

class Assets
{
    private static $frontend_enqueued = false;

    public function register()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 30);
    }

    public function enqueue()
    {
        $this->enqueue_assets(false);
    }

    public function enqueue_forced()
    {
        $this->enqueue_assets(true);
    }

    private function enqueue_assets($force = false)
    {
        if (self::$frontend_enqueued) {
            return;
        }

        $context = $this->get_enqueue_context();
        if (!$force && empty($context['enabled'])) {
            return;
        }

        self::$frontend_enqueued = true;

        wp_register_script(
            'alpinejs',
            'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js',
            [],
            null,
            true
        );

        wp_enqueue_style(
            'velocity-marketplace-frontend-css',
            VMP_URL . 'assets/css/frontend.css',
            [],
            VMP_VERSION
        );

        wp_enqueue_script(
            'velocity-marketplace-frontend-shared-js',
            VMP_URL . 'assets/js/frontend-shared.js',
            [],
            VMP_VERSION,
            true
        );

        wp_enqueue_script(
            'velocity-marketplace-frontend-catalog-js',
            VMP_URL . 'assets/js/frontend-catalog.js',
            ['velocity-marketplace-frontend-shared-js'],
            VMP_VERSION,
            true
        );

        wp_enqueue_script(
            'velocity-marketplace-frontend-cart-js',
            VMP_URL . 'assets/js/frontend-cart.js',
            $this->frontend_script_dependencies(['velocity-marketplace-frontend-shared-js'], true),
            VMP_VERSION,
            true
        );

        wp_enqueue_script(
            'velocity-marketplace-frontend-checkout-js',
            VMP_URL . 'assets/js/frontend-checkout.js',
            ['velocity-marketplace-frontend-shared-js'],
            VMP_VERSION,
            true
        );

        wp_enqueue_script(
            'velocity-marketplace-frontend-profile-js',
            VMP_URL . 'assets/js/frontend-profile.js',
            ['velocity-marketplace-frontend-shared-js'],
            VMP_VERSION,
            true
        );

        wp_enqueue_script(
            'velocity-marketplace-frontend-ui-js',
            VMP_URL . 'assets/js/frontend-ui.js',
            $this->frontend_script_dependencies(['velocity-marketplace-frontend-shared-js'], true),
            VMP_VERSION,
            true
        );

        wp_enqueue_script('alpinejs');

        if (!empty($context['profile'])) {
            wp_enqueue_style(
                'velocity-marketplace-dashboard-css',
                VMP_URL . 'assets/css/dashboard.css',
                ['velocity-marketplace-frontend-css'],
                VMP_VERSION
            );

            wp_enqueue_script(
                'velocity-marketplace-dashboard-js',
                VMP_URL . 'assets/js/dashboard.js',
                [],
                VMP_VERSION,
                true
            );

            wp_enqueue_script(
                'velocity-marketplace-media-js',
                VMP_URL . 'assets/js/media.js',
                [],
                VMP_VERSION,
                true
            );

            wp_enqueue_media();
            if (function_exists('wp_enqueue_editor')) {
                wp_enqueue_editor();
            }
        }

        $pages = get_option(VMP_PAGES_OPTION, []);
        $catalog_url = $this->resolve_page_url($pages, 'katalog', '/catalog/');
        $cart_url = $this->resolve_page_url($pages, 'keranjang', '/cart/');
        $checkout_url = $this->resolve_page_url($pages, 'checkout', '/checkout/');
        $profile_url = $this->resolve_page_url($pages, 'myaccount', '/account/');
        $currency = Settings::currency();
        $currency_symbol = Settings::currency_symbol();
        $payment_methods = Settings::payment_methods();
        $customer_profile = $this->customer_profile_payload();

        wp_localize_script('velocity-marketplace-frontend-shared-js', 'vmpSettings', [
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
            'currentUserId' => get_current_user_id(),
            'canManageOptions' => current_user_can('manage_options'),
            'noImageUrl' => esc_url_raw(ProductData::no_image_url()),
            'customerProfile' => $customer_profile,
        ]);
    }

    private function customer_profile_payload()
    {
        if (!is_user_logged_in()) {
            return [
                'name' => '',
                'email' => '',
                'phone' => '',
                'address' => '',
                'postal_code' => '',
                'destination_province_id' => '',
                'destination_province_name' => '',
                'destination_city_id' => '',
                'destination_city_name' => '',
                'destination_subdistrict_id' => '',
                'destination_subdistrict_name' => '',
            ];
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        return [
            'name' => (string) get_user_meta($user_id, 'first_name', true) ?: ($user && $user->display_name !== '' ? (string) $user->display_name : ''),
            'email' => $user ? (string) $user->user_email : '',
            'phone' => (string) get_user_meta($user_id, 'vmp_member_phone', true),
            'address' => (string) get_user_meta($user_id, 'vmp_member_address', true),
            'postal_code' => (string) get_user_meta($user_id, 'vmp_member_postcode', true),
            'destination_province_id' => (string) get_user_meta($user_id, 'vmp_member_province_id', true),
            'destination_province_name' => (string) get_user_meta($user_id, 'vmp_member_province', true),
            'destination_city_id' => (string) get_user_meta($user_id, 'vmp_member_city_id', true),
            'destination_city_name' => (string) get_user_meta($user_id, 'vmp_member_city', true),
            'destination_subdistrict_id' => (string) get_user_meta($user_id, 'vmp_member_subdistrict_id', true),
            'destination_subdistrict_name' => (string) get_user_meta($user_id, 'vmp_member_subdistrict', true),
        ];
    }

    private function get_enqueue_context()
    {
        if (is_admin()) {
            return [
                'enabled' => false,
                'profile' => false,
            ];
        }

        if (is_post_type_archive(Contract::PRODUCT_POST_TYPE) || is_post_type_archive(Contract::LEGACY_PRODUCT_POST_TYPE) || is_singular(Contract::PRODUCT_POST_TYPE) || is_singular(Contract::LEGACY_PRODUCT_POST_TYPE)) {
            return [
                'enabled' => true,
                'profile' => false,
            ];
        }

        if (is_page()) {
            global $post;
            $pages = get_option(VMP_PAGES_OPTION, []);
            if ($post && is_array($pages) && in_array((int) $post->ID, array_map('intval', $pages), true)) {
                $profile_page_id = !empty($pages['myaccount']) ? (int) $pages['myaccount'] : 0;

                return [
                    'enabled' => true,
                    'profile' => $profile_page_id > 0 && (int) $post->ID === $profile_page_id,
                ];
            }

            if ($post && isset($post->post_content)) {
                $content = (string) $post->post_content;
                $enabled = $this->content_has_any_shortcode($content, [
                    'vmp_catalog',
                    'wp_store_catalog',
                    'wp_store_shop',
                    'vmp_products',
                    'vmp_product_card',
                    'wp_store_thumbnail',
                    'vmp_thumbnail',
                    'wp_store_price',
                    'vmp_price',
                    'wp_store_add_to_cart',
                    'vmp_add_to_cart',
                    'wp_store_add_to_wishlist',
                    'vmp_add_to_wishlist',
                    'wp_store_cart',
                    'vmp_cart',
                    'wp_store_cart_page',
                    'store_cart',
                    'vmp_cart_page',
                    'wp_store_checkout',
                    'store_checkout',
                    'vmp_checkout',
                    'wp_store_profile',
                    'store_customer_profile',
                    'vmp_profile',
                    'wp_store_tracking',
                    'store_tracking',
                    'vmp_tracking',
                    'vmp_store_profile',
                    'vmp_messages_icon',
                    'vmp_notifications_icon',
                    'vmp_profile_icon',
                ]);
                $profile = $this->content_has_any_shortcode($content, [
                    'wp_store_profile',
                    'store_customer_profile',
                    'vmp_profile',
                ]);

                return [
                    'enabled' => $enabled,
                    'profile' => $profile,
                ];
            }
        }

        return [
            'enabled' => false,
            'profile' => false,
        ];
    }

    private function content_has_any_shortcode($content, $shortcodes)
    {
        foreach ((array) $shortcodes as $shortcode) {
            if (has_shortcode($content, (string) $shortcode)) {
                return true;
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

    private function frontend_script_dependencies($base = [], $needs_theme_bootstrap = false)
    {
        $deps = is_array($base) ? $base : [];

        if ($needs_theme_bootstrap && wp_script_is('justg-scripts', 'registered')) {
            $deps[] = 'justg-scripts';
        }

        return array_values(array_unique($deps));
    }
}

