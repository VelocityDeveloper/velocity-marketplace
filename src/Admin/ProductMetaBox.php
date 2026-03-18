<?php

namespace VelocityMarketplace\Admin;

use VelocityMarketplace\Support\ProductFields;

class ProductMetaBox
{
    public function register()
    {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post_vmp_product', [$this, 'save_meta']);
    }

    public function add_meta_box()
    {
        add_meta_box(
            'vmp_product_meta',
            'Data Produk Marketplace',
            [$this, 'render_meta_box'],
            'vmp_product',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post)
    {
        wp_nonce_field('vmp_product_meta_save', 'vmp_product_meta_nonce');
        ?>
        <style>
            .vmp-meta-section{margin-bottom:16px}
            .vmp-meta-section__title{margin:0 0 10px;font-size:13px}
            .vmp-meta-section .row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
            .vmp-meta-section .col-12{grid-column:1 / -1}
            .vmp-meta-section input,
            .vmp-meta-section select,
            .vmp-meta-section textarea{width:100%}
            .vmp-meta-section .form-check{padding-top:20px}
            .vmp-meta-section .form-text{margin-top:4px;color:#646970}
        </style>
        <?php
        echo ProductFields::render_sections((int) $post->ID, 'admin'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function save_meta($post_id)
    {
        if (!isset($_POST['vmp_product_meta_nonce']) || !wp_verify_nonce($_POST['vmp_product_meta_nonce'], 'vmp_product_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        ProductFields::save((int) $post_id, 'admin');
    }
}
