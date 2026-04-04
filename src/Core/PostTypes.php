<?php

namespace VelocityMarketplace\Core;

use VelocityMarketplace\Support\Contract;

class PostTypes
{
    public function register()
    {
        add_action('init', [$this, 'register_product_type']);
        add_action('init', [$this, 'register_order_type']);
        add_action('init', [$this, 'register_coupon_type']);
    }

    public function register_product_type()
    {
        register_taxonomy(Contract::PRODUCT_TAXONOMY, Contract::product_post_types(), [
            'hierarchical' => true,
            'labels' => [
                'name' => 'Kategori Produk',
                'singular_name' => 'Kategori Produk',
                'search_items' => 'Cari Kategori',
                'all_items' => 'Semua Kategori',
                'parent_item' => 'Induk Kategori',
                'parent_item_colon' => 'Induk Kategori:',
                'edit_item' => 'Edit Kategori',
                'update_item' => 'Update Kategori',
                'add_new_item' => 'Tambah Kategori Baru',
                'new_item_name' => 'Nama Kategori Baru',
                'menu_name' => 'Kategori Produk',
            ],
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'kategori-produk'],
            'show_in_rest' => true,
        ]);

        register_taxonomy(Contract::LEGACY_PRODUCT_TAXONOMY, [Contract::LEGACY_PRODUCT_POST_TYPE], [
            'hierarchical' => true,
            'labels' => [
                'name' => 'Kategori Produk Legacy',
                'singular_name' => 'Kategori Produk Legacy',
            ],
            'show_ui' => false,
            'show_admin_column' => false,
            'query_var' => false,
            'rewrite' => false,
            'show_in_rest' => false,
        ]);

        $labels = [
            'name' => 'Produk Marketplace',
            'singular_name' => 'Produk Marketplace',
            'menu_name' => 'Marketplace',
            'name_admin_bar' => 'Produk Marketplace',
            'add_new' => 'Tambah Baru',
            'add_new_item' => 'Tambah Produk',
            'new_item' => 'Produk Baru',
            'edit_item' => 'Edit Produk',
            'view_item' => 'Lihat Produk',
            'all_items' => 'Semua Produk',
            'search_items' => 'Cari Produk',
            'not_found' => 'Produk tidak ditemukan.',
            'not_found_in_trash' => 'Produk tidak ditemukan di trash.',
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_in_rest' => false,
            'has_archive' => true,
            'rewrite' => ['slug' => 'produk'],
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_position' => 22,
            'menu_icon' => 'dashicons-cart',
        ];

        register_post_type(Contract::PRODUCT_POST_TYPE, $args);

        register_post_type(Contract::LEGACY_PRODUCT_POST_TYPE, array_merge($args, [
            'labels' => array_merge($labels, [
                'name' => 'Produk Marketplace Legacy',
                'menu_name' => 'Produk Marketplace Legacy',
            ]),
            'show_ui' => false,
            'show_in_menu' => false,
            'show_admin_bar' => false,
            'show_in_nav_menus' => false,
            'exclude_from_search' => true,
        ]));
    }

    public function register_order_type()
    {
        $labels = [
            'name' => 'Pesanan Marketplace',
            'singular_name' => 'Pesanan Marketplace',
            'menu_name' => 'Pesanan',
            'name_admin_bar' => 'Pesanan Marketplace',
            'add_new' => 'Tambah Baru',
            'add_new_item' => 'Tambah Pesanan',
            'new_item' => 'Pesanan Baru',
            'edit_item' => 'Edit Pesanan',
            'view_item' => 'Lihat Pesanan',
            'all_items' => 'Semua Pesanan',
            'search_items' => 'Cari Pesanan',
            'not_found' => 'Pesanan tidak ditemukan.',
            'not_found_in_trash' => 'Pesanan tidak ditemukan di trash.',
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => false,
            'has_archive' => false,
            'rewrite' => false,
            'supports' => ['title'],
            'menu_position' => 23,
            'menu_icon' => 'dashicons-clipboard',
        ];

        register_post_type(Contract::ORDER_POST_TYPE, $args);

        register_post_type(Contract::LEGACY_ORDER_POST_TYPE, array_merge($args, [
            'labels' => array_merge($labels, [
                'name' => 'Pesanan Marketplace Legacy',
                'menu_name' => 'Pesanan Marketplace Legacy',
            ]),
            'show_ui' => false,
            'show_in_menu' => false,
            'show_admin_bar' => false,
            'show_in_nav_menus' => false,
            'exclude_from_search' => true,
        ]));
    }

    public function register_coupon_type()
    {
        $labels = [
            'name' => 'Kupon Marketplace',
            'singular_name' => 'Kupon Marketplace',
            'menu_name' => 'Kupon',
            'name_admin_bar' => 'Kupon Marketplace',
            'add_new' => 'Tambah Baru',
            'add_new_item' => 'Tambah Kupon',
            'new_item' => 'Kupon Baru',
            'edit_item' => 'Edit Kupon',
            'view_item' => 'Lihat Kupon',
            'all_items' => 'Semua Kupon',
            'search_items' => 'Cari Kupon',
            'not_found' => 'Kupon tidak ditemukan.',
            'not_found_in_trash' => 'Kupon tidak ditemukan di trash.',
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => false,
            'has_archive' => false,
            'rewrite' => false,
            'supports' => ['title'],
            'menu_position' => 24,
            'menu_icon' => 'dashicons-tickets-alt',
        ];

        register_post_type(Contract::COUPON_POST_TYPE, $args);

        register_post_type(Contract::LEGACY_COUPON_POST_TYPE, array_merge($args, [
            'labels' => array_merge($labels, [
                'name' => 'Kupon Marketplace Legacy',
                'menu_name' => 'Kupon Marketplace Legacy',
            ]),
            'show_ui' => false,
            'show_in_menu' => false,
            'show_admin_bar' => false,
            'show_in_nav_menus' => false,
            'exclude_from_search' => true,
        ]));
    }
}
