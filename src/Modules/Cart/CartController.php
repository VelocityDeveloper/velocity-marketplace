<?php

namespace VelocityMarketplace\Modules\Cart;

use VelocityMarketplace\Modules\Cart\CartRepository;
use WP_REST_Request;
use WP_REST_Response;

class CartController
{
    public function register_routes()
    {
        register_rest_route('velocity-marketplace/v1', '/cart', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_cart'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_cart'],
                'permission_callback' => [$this, 'check_rest_nonce'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'clear_cart'],
                'permission_callback' => [$this, 'check_rest_nonce'],
            ],
        ]);
    }

    public function check_rest_nonce(WP_REST_Request $request)
    {
        $nonce = $request->get_header('x_wp_nonce');
        if (!$nonce) {
            $nonce = $request->get_header('x-wp-nonce');
        }

        return is_string($nonce) && wp_verify_nonce($nonce, 'wp_rest');
    }

    public function get_cart(WP_REST_Request $request)
    {
        $repo = new CartRepository();
        return new WP_REST_Response($repo->get_cart_data(), 200);
    }

    public function update_cart(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $product_id = isset($body['id']) ? (int) $body['id'] : 0;
        $qty = isset($body['qty']) ? (int) $body['qty'] : 0;
        $add_qty = isset($body['add_qty']) ? max(0, (int) $body['add_qty']) : null;
        $options = isset($body['options']) && is_array($body['options']) ? $body['options'] : [];
        $cart_key = isset($body['cart_key']) ? sanitize_text_field((string) $body['cart_key']) : '';

        if ($product_id <= 0) {
            return new WP_REST_Response(['message' => 'Produk tidak valid.'], 400);
        }

        $repo = new CartRepository();
        $result = $repo->upsert_item($product_id, $qty, $options, $cart_key, $add_qty);
        if (is_wp_error($result)) {
            return new WP_REST_Response(['message' => $result->get_error_message()], 400);
        }

        return new WP_REST_Response($repo->get_cart_data(), 200);
    }

    public function clear_cart(WP_REST_Request $request)
    {
        $repo = new CartRepository();
        $repo->clear();
        return new WP_REST_Response([
            'items' => [],
            'total' => 0,
            'count' => 0,
            'message' => 'Keranjang dikosongkan.',
        ], 200);
    }
}



