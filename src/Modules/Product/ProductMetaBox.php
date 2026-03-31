<?php

namespace VelocityMarketplace\Modules\Product;

use VelocityMarketplace\Modules\Product\ProductFields;

class ProductMetaBox
{
    private $drafting_for_validation = false;

    public function register()
    {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post_vmp_product', [$this, 'save_meta']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_notices', [$this, 'render_validation_notice']);
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
            .vmp-meta-section .d-flex{display:flex}
            .vmp-meta-section .flex-wrap{flex-wrap:wrap}
            .vmp-meta-section .gap-2{gap:8px}
            .vmp-meta-section .mb-2{margin-bottom:8px}
        </style>
        <?php
        echo ProductFields::render_sections((int) $post->ID, 'admin'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function enqueue_assets($hook)
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'vmp_product') {
            return;
        }

        wp_enqueue_style(
            'velocity-marketplace-admin-product-meta-css',
            VMP_URL . 'assets/css/dashboard.css',
            [],
            VMP_VERSION
        );

        wp_enqueue_script(
            'velocity-marketplace-admin-product-media-js',
            VMP_URL . 'assets/js/media.js',
            [],
            VMP_VERSION,
            true
        );

        wp_localize_script('velocity-marketplace-admin-product-media-js', 'vmpSettings', [
            'currentUserId' => get_current_user_id(),
            'canManageOptions' => current_user_can('manage_options'),
        ]);

        wp_enqueue_media();
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

        $validation = ProductFields::validate_submission('admin');
        if (is_wp_error($validation)) {
            $this->queue_validation_notice($validation->get_error_message());

            if (!$this->drafting_for_validation && in_array(get_post_status($post_id), ['publish', 'pending'], true)) {
                $this->drafting_for_validation = true;
                remove_action('save_post_vmp_product', [$this, 'save_meta']);
                wp_update_post([
                    'ID' => (int) $post_id,
                    'post_status' => 'draft',
                ]);
                add_action('save_post_vmp_product', [$this, 'save_meta']);
                $this->drafting_for_validation = false;
            }

            return;
        }

        ProductFields::save((int) $post_id, 'admin');
    }

    public function render_validation_notice()
    {
        if (!is_admin()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'vmp_product') {
            return;
        }

        $message = isset($_GET['vmp_product_error']) ? sanitize_text_field((string) wp_unslash($_GET['vmp_product_error'])) : '';
        if ($message === '') {
            return;
        }

        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function queue_validation_notice($message)
    {
        add_filter('redirect_post_location', static function ($location) use ($message) {
            return add_query_arg('vmp_product_error', rawurlencode((string) $message), $location);
        });
    }
}


