<?php

namespace VelocityMarketplace\Api;

use VelocityMarketplace\Support\CaptchaBridge;
use VelocityMarketplace\Support\CartRepository;
use VelocityMarketplace\Support\NotificationRepository;
use VelocityMarketplace\Support\OrderData;
use VelocityMarketplace\Support\Settings;
use WP_REST_Request;
use WP_REST_Response;

class CheckoutController
{
    public function register_routes()
    {
        register_rest_route('velocity-marketplace/v1', '/checkout', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_order'],
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

    public function create_order(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $repo = new CartRepository();
        $cart = $repo->get_cart_data();
        $items = isset($cart['items']) && is_array($cart['items']) ? $cart['items'] : [];
        if (empty($items)) {
            return new WP_REST_Response(['message' => 'Keranjang kosong.'], 400);
        }

        $customer = [
            'name' => sanitize_text_field($payload['name'] ?? ''),
            'email' => sanitize_email($payload['email'] ?? ''),
            'phone' => sanitize_text_field($payload['phone'] ?? ''),
            'address' => sanitize_textarea_field($payload['address'] ?? ''),
            'postal_code' => sanitize_text_field($payload['postal_code'] ?? ''),
        ];
        $shipping = [
            'courier' => sanitize_text_field($payload['shipping_courier'] ?? ''),
            'service' => sanitize_text_field($payload['shipping_service'] ?? ''),
            'cost' => isset($payload['shipping_cost']) ? (float) $payload['shipping_cost'] : 0,
            'subdistrict_destination' => sanitize_text_field($payload['subdistrict_destination'] ?? ''),
        ];
        $active_payment_methods = Settings::payment_methods();

        $payment_method = sanitize_key($payload['payment_method'] ?? $active_payment_methods[0]);
        if (!in_array($payment_method, $active_payment_methods, true)) {
            return new WP_REST_Response(['message' => 'Metode pembayaran tidak tersedia.'], 400);
        }

        $default_order_status = OrderData::normalize_status(Settings::default_order_status());

        $notes = sanitize_textarea_field($payload['notes'] ?? '');

        if ($customer['name'] === '' || $customer['phone'] === '' || $customer['address'] === '') {
            return new WP_REST_Response(['message' => 'Nama, telepon, dan alamat wajib diisi.'], 400);
        }
        if ($customer['email'] !== '' && !is_email($customer['email'])) {
            return new WP_REST_Response(['message' => 'Email tidak valid.'], 400);
        }

        if (!is_user_logged_in() && CaptchaBridge::is_active()) {
            $verify = CaptchaBridge::verify_payload($payload);
            if (empty($verify['success'])) {
                return new WP_REST_Response([
                    'message' => $verify['message'] !== '' ? $verify['message'] : 'Captcha tidak valid.',
                ], 400);
            }
        }

        $subtotal = 0;
        $total_weight = 0;
        $order_items = [];

        foreach ($items as $item) {
            $product_id = isset($item['id']) ? (int) $item['id'] : 0;
            $qty = isset($item['qty']) ? (int) $item['qty'] : 0;
            $price = isset($item['price']) ? (float) $item['price'] : 0;
            if ($product_id <= 0 || $qty <= 0) {
                continue;
            }

            $weight = (float) get_post_meta($product_id, 'weight', true);
            $line_subtotal = $price * $qty;
            $subtotal += $line_subtotal;
            $total_weight += ($weight * $qty);

            $order_items[] = [
                'product_id' => $product_id,
                'title' => isset($item['title']) ? sanitize_text_field($item['title']) : get_the_title($product_id),
                'qty' => $qty,
                'price' => $price,
                'subtotal' => $line_subtotal,
                'weight' => $weight,
                'seller_id' => isset($item['penjual']) ? (int) $item['penjual'] : (int) get_post_field('post_author', $product_id),
                'options' => isset($item['options']) && is_array($item['options']) ? $item['options'] : [],
            ];
        }

        if (empty($order_items)) {
            return new WP_REST_Response(['message' => 'Keranjang tidak valid.'], 400);
        }

        $total = $subtotal + max(0, (float) $shipping['cost']);
        $invoice = $this->generate_invoice();
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;

        $order_id = wp_insert_post([
            'post_type' => 'vmp_order',
            'post_status' => 'publish',
            'post_title' => 'Order ' . $invoice . ' - ' . $customer['name'],
        ]);

        if (is_wp_error($order_id) || !$order_id) {
            return new WP_REST_Response(['message' => 'Gagal menyimpan pesanan.'], 500);
        }

        update_post_meta($order_id, 'vmp_invoice', $invoice);
        update_post_meta($order_id, 'vmp_user_id', (int) $user_id);
        update_post_meta($order_id, 'vmp_customer', $customer);
        update_post_meta($order_id, 'vmp_items', $order_items);
        update_post_meta($order_id, 'vmp_subtotal', (float) $subtotal);
        update_post_meta($order_id, 'vmp_shipping', $shipping);
        update_post_meta($order_id, 'vmp_total', (float) $total);
        update_post_meta($order_id, 'vmp_total_weight', (float) $total_weight);
        update_post_meta($order_id, 'vmp_payment_method', $payment_method);
        update_post_meta($order_id, 'vmp_status', $default_order_status);
        update_post_meta($order_id, 'vmp_notes', $notes);
        update_post_meta($order_id, 'vmp_created_at', current_time('mysql'));

        foreach ($order_items as $line) {
            $product_id = (int) $line['product_id'];
            $qty = (int) $line['qty'];
            $stock = get_post_meta($product_id, 'stock', true);
            if ($stock !== '' && is_numeric($stock)) {
                $new_stock = max(0, ((int) $stock) - $qty);
                update_post_meta($product_id, 'stock', $new_stock);
            }
        }

        $profile_url = Settings::profile_url();
        $notif = new NotificationRepository();

        if ($user_id > 0) {
            $notif->add(
                $user_id,
                'order',
                'Pesanan Berhasil Dibuat',
                'Invoice ' . $invoice . ' berhasil dibuat dengan total ' . number_format((float) $total, 0, ',', '.') . '.',
                add_query_arg(['tab' => 'orders', 'invoice' => $invoice], $profile_url)
            );
        }
        if (!empty($customer['email']) && is_email($customer['email'])) {
            wp_mail(
                $customer['email'],
                'Invoice ' . $invoice . ' berhasil dibuat',
                'Pesanan kamu sudah diterima dengan total ' . number_format((float) $total, 0, ',', '.') . '.'
            );
        }

        $seller_ids = [];
        foreach ($order_items as $line) {
            $sid = isset($line['seller_id']) ? (int) $line['seller_id'] : 0;
            if ($sid > 0) {
                $seller_ids[] = $sid;
            }
        }
        $seller_ids = array_values(array_unique($seller_ids));
        foreach ($seller_ids as $seller_id) {
            $notif->add(
                $seller_id,
                'order',
                'Order Masuk',
                'Ada order baru ' . $invoice . ' yang perlu diproses.',
                add_query_arg(['tab' => 'seller_home'], $profile_url)
            );

            $seller = get_userdata($seller_id);
            if ($seller && !empty($seller->user_email) && is_email($seller->user_email)) {
                wp_mail(
                    $seller->user_email,
                    'Order masuk ' . $invoice,
                    'Ada order baru yang masuk ke toko kamu. Silakan cek dashboard seller.'
                );
            }
        }

        $repo->clear();

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat.',
            'order_id' => (int) $order_id,
            'invoice' => $invoice,
            'total' => (float) $total,
            'redirect' => $this->resolve_profile_url($invoice),
        ], 201);
    }

    private function generate_invoice()
    {
        return 'VMP-' . gmdate('Ymd') . '-' . wp_rand(100000, 999999);
    }

    private function resolve_profile_url($invoice)
    {
        $pages = get_option('velocity_marketplace_pages', []);
        $pid = isset($pages['myaccount']) ? (int) $pages['myaccount'] : 0;
        if ($pid > 0) {
            $url = get_permalink($pid);
            if ($url) {
                return add_query_arg(['invoice' => $invoice], $url);
            }
        }

        return add_query_arg(['invoice' => $invoice], site_url('/myaccount/'));
    }
}
