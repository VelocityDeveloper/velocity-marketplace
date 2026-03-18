<?php

namespace VelocityMarketplace\Api;

use VelocityMarketplace\Support\WishlistRepository;
use WP_REST_Request;
use WP_REST_Response;

class WishlistController
{
    public function register_routes()
    {
        register_rest_route('velocity-marketplace/v1', '/wishlist', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_wishlist'],
                'permission_callback' => [$this, 'check_logged_in'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'toggle_wishlist'],
                'permission_callback' => [$this, 'check_rest_nonce'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'remove_wishlist'],
                'permission_callback' => [$this, 'check_rest_nonce'],
            ],
        ]);
    }

    public function check_logged_in()
    {
        return is_user_logged_in();
    }

    public function check_rest_nonce(WP_REST_Request $request)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $nonce = $request->get_header('x_wp_nonce');
        if (!$nonce) {
            $nonce = $request->get_header('x-wp-nonce');
        }

        return is_string($nonce) && wp_verify_nonce($nonce, 'wp_rest');
    }

    public function get_wishlist(WP_REST_Request $request)
    {
        $repo = new WishlistRepository();
        $ids = $repo->get_ids(get_current_user_id());

        return new WP_REST_Response([
            'items' => $ids,
            'count' => count($ids),
        ], 200);
    }

    public function toggle_wishlist(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $product_id = isset($body['product_id']) ? (int) $body['product_id'] : 0;
        if ($product_id <= 0 || get_post_type($product_id) !== 'vmp_product') {
            return new WP_REST_Response(['message' => 'Produk tidak valid.'], 400);
        }

        $repo = new WishlistRepository();
        $active = $repo->toggle($product_id, get_current_user_id());

        return new WP_REST_Response([
            'product_id' => $product_id,
            'active' => (bool) $active,
            'items' => $repo->get_ids(get_current_user_id()),
        ], 200);
    }

    public function remove_wishlist(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $product_id = isset($body['product_id']) ? (int) $body['product_id'] : 0;
        if ($product_id <= 0) {
            return new WP_REST_Response(['message' => 'Produk tidak valid.'], 400);
        }

        $repo = new WishlistRepository();
        $repo->remove($product_id, get_current_user_id());

        return new WP_REST_Response([
            'product_id' => $product_id,
            'active' => false,
            'items' => $repo->get_ids(get_current_user_id()),
        ], 200);
    }
}

