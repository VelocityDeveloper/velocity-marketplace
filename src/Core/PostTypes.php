<?php

namespace VelocityMarketplace\Core;

class PostTypes
{
    public function register()
    {
        add_action('init', [$this, 'register_product_type']);
        add_action('init', [$this, 'register_order_type']);
    }

    public function register_product_type()
    {
        register_taxonomy('vmp_product_cat', ['vmp_product'], [
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
            'show_in_rest' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'produk'],
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_position' => 22,
            'menu_icon' => 'dashicons-cart',
        ];

        register_post_type('vmp_product', $args);
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

        register_post_type('vmp_order', $args);
    }
}
