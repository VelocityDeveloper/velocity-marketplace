<?php

namespace VelocityMarketplace\Core;

class Installer
{
    const DB_VERSION = '1.3.0';

    public function activate()
    {
        $this->ensure_roles();
        $this->create_default_pages();
        $this->seed_default_settings();
        update_option('velocity_marketplace_db_version', self::DB_VERSION);
    }

    public function maybe_upgrade()
    {
        $version = (string) get_option('velocity_marketplace_db_version', '');
        if ($version !== self::DB_VERSION) {
            $this->activate();
        }
    }

    private function create_default_pages()
    {
        $pages = [
            'katalog' => [
                'title' => 'Katalog',
                'content' => '[velocity_marketplace_catalog]',
            ],
            'keranjang' => [
                'title' => 'Keranjang',
                'content' => '[velocity_marketplace_cart]',
            ],
            'checkout' => [
                'title' => 'Checkout',
                'content' => '[velocity_marketplace_checkout]',
            ],
            'myaccount' => [
                'title' => 'My Account',
                'content' => '[velocity_marketplace_profile]',
            ],
            'tracking' => [
                'title' => 'Tracking Pesanan',
                'content' => '[velocity_marketplace_tracking]',
            ],
        ];

        $stored = get_option('velocity_marketplace_pages', []);
        if (!is_array($stored)) {
            $stored = [];
        }

        foreach ($pages as $slug => $page) {
            $existing = get_page_by_path($slug);
            if ($existing && isset($existing->ID)) {
                $stored[$slug] = (int) $existing->ID;
                continue;
            }

            $post_id = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $page['title'],
                'post_name' => $slug,
                'post_content' => $page['content'],
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ]);

            if (!is_wp_error($post_id) && $post_id) {
                $stored[$slug] = (int) $post_id;
            }
        }

        update_option('velocity_marketplace_pages', $stored);
    }

    private function seed_default_settings()
    {
        $current = get_option('velocity_marketplace_settings', []);
        if (!is_array($current)) {
            $current = [];
        }

        $defaults = [
            'currency' => 'IDR',
            'currency_symbol' => 'Rp',
            'default_order_status' => 'pending_payment',
            'payment_methods' => ['bank', 'duitku', 'paypal'],
            'seller_product_status' => 'publish',
        ];

        update_option('velocity_marketplace_settings', array_merge($defaults, $current));
    }

    private function ensure_roles()
    {
        add_role('vmp_customer', 'Marketplace Customer', ['read' => true]);
        add_role('vmp_seller', 'Marketplace Seller', [
            'read' => true,
            'upload_files' => true,
        ]);
    }
}
