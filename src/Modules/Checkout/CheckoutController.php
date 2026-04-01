<?php

namespace VelocityMarketplace\Modules\Checkout;

use VelocityMarketplace\Modules\Captcha\CaptchaBridge;
use VelocityMarketplace\Modules\Cart\CartRepository;
use VelocityMarketplace\Modules\Coupon\CouponService;
use VelocityMarketplace\Modules\Email\EmailTemplateService;
use VelocityMarketplace\Modules\Notification\NotificationRepository;
use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Modules\Payment\DuitkuGateway;
use VelocityMarketplace\Modules\Shipping\ShippingController;
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
        $shipping_destination = [
            'province_destination_id' => sanitize_text_field($payload['destination_province_id'] ?? ''),
            'province_destination_name' => sanitize_text_field($payload['destination_province_name'] ?? ''),
            'city_destination_id' => sanitize_text_field($payload['destination_city_id'] ?? ''),
            'city_destination_name' => sanitize_text_field($payload['destination_city_name'] ?? ''),
            'subdistrict_destination_id' => sanitize_text_field($payload['destination_subdistrict_id'] ?? ''),
            'subdistrict_destination_name' => sanitize_text_field($payload['destination_subdistrict_name'] ?? ''),
        ];
        $submitted_shipping_groups = isset($payload['shipping_groups']) && is_array($payload['shipping_groups'])
            ? $payload['shipping_groups']
            : [];
        $active_payment_methods = Settings::payment_methods();

        $payment_method = sanitize_key($payload['payment_method'] ?? $active_payment_methods[0]);
        if (!in_array($payment_method, $active_payment_methods, true)) {
            return new WP_REST_Response(['message' => 'Metode pembayaran tidak tersedia.'], 400);
        }
        if ($payment_method === 'bank' && empty(Settings::bank_accounts())) {
            return new WP_REST_Response(['message' => 'Rekening tujuan transfer belum tersedia. Silakan hubungi admin marketplace.'], 400);
        }

        $default_order_status = OrderData::normalize_status(Settings::default_order_status());

        $notes = sanitize_textarea_field($payload['notes'] ?? '');
        $coupon_code = sanitize_text_field((string) ($payload['coupon_code'] ?? ''));

        if ($customer['name'] === '' || $customer['phone'] === '' || $customer['address'] === '') {
            return new WP_REST_Response(['message' => 'Nama, telepon, dan alamat wajib diisi.'], 400);
        }
        if ($customer['email'] !== '' && !is_email($customer['email'])) {
            return new WP_REST_Response(['message' => 'Email tidak valid.'], 400);
        }
        if ($shipping_destination['province_destination_id'] === '' || $shipping_destination['city_destination_id'] === '' || $shipping_destination['subdistrict_destination_id'] === '') {
            return new WP_REST_Response(['message' => 'Provinsi, kota, dan kecamatan tujuan wajib diisi.'], 400);
        }

        if (CaptchaBridge::is_active()) {
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
            if ($weight <= 0) {
                return new WP_REST_Response([
                    'message' => sprintf(__('Produk "%s" belum memiliki berat. Lengkapi berat produk sebelum checkout.', 'velocity-marketplace'), get_the_title($product_id)),
                ], 400);
            }
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

        $shipping_context = (new ShippingController())->get_checkout_context($request);
        $shipping_context_data = $shipping_context instanceof WP_REST_Response ? $shipping_context->get_data() : [];
        if (empty($shipping_context_data['success'])) {
            return new WP_REST_Response([
                'message' => isset($shipping_context_data['message']) ? (string) $shipping_context_data['message'] : 'Context ongkir tidak valid.',
            ], 400);
        }

        $order_status = $payment_method === 'cod' ? 'processing' : $default_order_status;
        $shipping_groups = $this->build_shipping_groups($submitted_shipping_groups, $shipping_destination, $shipping_context_data, $order_items, $payment_method, $order_status);
        if (is_wp_error($shipping_groups)) {
            return new WP_REST_Response([
                'message' => $shipping_groups->get_error_message(),
            ], 400);
        }

        $shipping_total = 0;
        foreach ($shipping_groups as $shipping_group) {
            $shipping_total += (float) ($shipping_group['cost'] ?? 0);
        }

        $coupon_discount = 0.0;
        $coupon_scope = '';
        $coupon_product_discount = 0.0;
        $coupon_shipping_discount = 0.0;
        $coupon_id = 0;
        $coupon_data = null;
        if ($coupon_code !== '') {
            $coupon_preview = (new CouponService())->preview($coupon_code, $subtotal, $shipping_total);
            if (is_wp_error($coupon_preview)) {
                return new WP_REST_Response([
                    'message' => $coupon_preview->get_error_message(),
                ], 400);
            }
            $coupon_data = $coupon_preview;
            $coupon_id = (int) ($coupon_preview['id'] ?? 0);
            $coupon_code = (string) ($coupon_preview['code'] ?? $coupon_code);
            $coupon_scope = (string) ($coupon_preview['scope'] ?? '');
            $coupon_product_discount = (float) ($coupon_preview['product_discount'] ?? 0);
            $coupon_shipping_discount = (float) ($coupon_preview['shipping_discount'] ?? 0);
            $coupon_discount = (float) ($coupon_preview['discount'] ?? 0);
        }

        $shipping = [
            'groups' => $shipping_groups,
            'cost' => (float) $shipping_total,
            'province_destination_id' => $shipping_destination['province_destination_id'],
            'province_destination_name' => $shipping_destination['province_destination_name'],
            'city_destination_id' => $shipping_destination['city_destination_id'],
            'city_destination_name' => $shipping_destination['city_destination_name'],
            'subdistrict_destination_id' => $shipping_destination['subdistrict_destination_id'],
            'subdistrict_destination_name' => $shipping_destination['subdistrict_destination_name'],
        ];

        if (!empty($shipping_groups[0])) {
            $shipping['courier'] = (string) ($shipping_groups[0]['courier'] ?? '');
            $shipping['service'] = (string) ($shipping_groups[0]['service'] ?? '');
        }

        $total = max(0, ($subtotal - $coupon_product_discount) + ($shipping_total - $coupon_shipping_discount));
        $invoice = $this->generate_invoice();
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;

        $order_id = wp_insert_post([
            'post_type' => 'vmp_order',
            'post_status' => 'publish',
            'post_title' => $invoice . ' - ' . $customer['name'],
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
        update_post_meta($order_id, 'vmp_shipping_groups', $shipping_groups);
        update_post_meta($order_id, 'vmp_shipping_total', (float) $shipping_total);
        update_post_meta($order_id, 'vmp_total', (float) $total);
        update_post_meta($order_id, 'vmp_total_weight', (float) $total_weight);
        update_post_meta($order_id, 'vmp_payment_method', $payment_method);
        update_post_meta($order_id, 'vmp_bank_accounts', $payment_method === 'bank' ? Settings::bank_accounts() : []);
        update_post_meta($order_id, 'vmp_status', $order_status);
        update_post_meta($order_id, 'vmp_notes', $notes);
        update_post_meta($order_id, 'vmp_created_at', current_time('mysql'));
        update_post_meta($order_id, 'vmp_coupon_id', $coupon_id);
        update_post_meta($order_id, 'vmp_coupon_code', $coupon_code);
        update_post_meta($order_id, 'vmp_coupon_scope', $coupon_scope);
        update_post_meta($order_id, 'vmp_coupon_product_discount', (float) $coupon_product_discount);
        update_post_meta($order_id, 'vmp_coupon_shipping_discount', (float) $coupon_shipping_discount);
        update_post_meta($order_id, 'vmp_coupon_discount', (float) $coupon_discount);

        $redirect = $this->resolve_profile_url($invoice);
        if ($payment_method === 'duitku') {
            $duitku_invoice = $this->create_duitku_invoice($order_id, $invoice, $customer, $order_items, $total);
            if (is_wp_error($duitku_invoice)) {
                wp_delete_post($order_id, true);

                return new WP_REST_Response([
                    'message' => $duitku_invoice->get_error_message(),
                ], 400);
            }

            update_post_meta($order_id, 'vmp_gateway_name', 'duitku');
            update_post_meta($order_id, 'vmp_gateway_reference', sanitize_text_field((string) ($duitku_invoice['reference'] ?? '')));
            update_post_meta($order_id, 'vmp_gateway_payment_url', esc_url_raw((string) ($duitku_invoice['paymentUrl'] ?? '')));
            update_post_meta($order_id, 'vmp_gateway_status', sanitize_text_field((string) ($duitku_invoice['statusCode'] ?? 'pending')));
            update_post_meta($order_id, 'vmp_status', 'pending_payment');

            $redirect = (string) ($duitku_invoice['paymentUrl'] ?? $redirect);
        }

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
        $email_templates = new EmailTemplateService();
        $email_templates->send_admin_new_order($order_id);
        $email_templates->send_customer_new_order($order_id);

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
                'Pesanan Baru',
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

        if ($coupon_data && $coupon_id > 0) {
            (new CouponService())->increment_usage($coupon_id);
        }

        $repo->clear();

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat.',
            'order_id' => (int) $order_id,
            'invoice' => $invoice,
            'total' => (float) $total,
            'coupon_discount' => (float) $coupon_discount,
            'coupon_scope' => (string) $coupon_scope,
            'coupon_product_discount' => (float) $coupon_product_discount,
            'coupon_shipping_discount' => (float) $coupon_shipping_discount,
            'redirect' => $redirect,
        ], 201);
    }

    private function generate_invoice()
    {
        return 'VMP-' . gmdate('Ymd') . '-' . wp_rand(100000, 999999);
    }

    private function resolve_profile_url($invoice)
    {
        return add_query_arg([
            'tab' => 'tracking',
            'invoice' => $invoice,
        ], Settings::profile_url());
    }

    private function create_duitku_invoice($order_id, $invoice, array $customer, array $order_items, $total)
    {
        if (!DuitkuGateway::is_available()) {
            return new \WP_Error('duitku_not_available', __('Gateway Duitku tidak tersedia. Pilih metode pembayaran lain.', 'velocity-marketplace'));
        }

        $email = $customer['email'] !== '' && is_email($customer['email'])
            ? $customer['email']
            : 'customer+' . strtolower($invoice) . '@example.com';

        $phone = preg_replace('/[^0-9]/', '', (string) ($customer['phone'] ?? ''));
        $customer_name = trim((string) ($customer['name'] ?? ''));
        $name_parts = preg_split('/\s+/', $customer_name);
        $first_name = !empty($name_parts[0]) ? (string) $name_parts[0] : 'Customer';
        $last_name = count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : '';

        $address = [
            'firstName' => $first_name,
            'lastName' => $last_name,
            'address' => (string) ($customer['address'] ?? ''),
            'city' => '',
            'postalCode' => (string) ($customer['postal_code'] ?? ''),
            'phone' => $phone,
            'countryCode' => 'ID',
        ];

        $item_details = [];
        foreach ($order_items as $line) {
            $item_details[] = [
                'name' => (string) ($line['title'] ?? 'Produk'),
                'price' => (int) round((float) ($line['price'] ?? 0)),
                'quantity' => max(1, (int) ($line['qty'] ?? 1)),
            ];
        }

        return DuitkuGateway::create_invoice([
            'paymentAmount' => (int) round((float) $total),
            'merchantOrderId' => (string) $invoice,
            'productDetails' => 'Pembayaran pesanan ' . $invoice,
            'additionalParam' => (string) $order_id,
            'merchantUserInfo' => (string) get_post_meta($order_id, 'vmp_user_id', true),
            'customerVaName' => $customer_name !== '' ? $customer_name : 'Customer',
            'email' => $email,
            'phoneNumber' => $phone,
            'itemDetails' => $item_details,
            'customerDetail' => [
                'firstName' => $first_name,
                'lastName' => $last_name,
                'email' => $email,
                'phoneNumber' => $phone,
                'billingAddress' => $address,
                'shippingAddress' => $address,
            ],
            'callbackUrl' => '',
            'returnUrl' => Settings::tracking_url((string) $invoice),
            'expiryPeriod' => 60,
        ]);
    }

    private function build_shipping_groups($submitted_groups, $destination, $context_data, $order_items, $payment_method, $initial_status = 'pending_payment')
    {
        $payment_method = sanitize_key((string) $payment_method);
        $initial_status = OrderData::normalize_status($initial_status);
        $context_groups = isset($context_data['data']['groups']) && is_array($context_data['data']['groups'])
            ? $context_data['data']['groups']
            : [];
        if (empty($context_groups)) {
            return new \WP_Error('missing_shipping_groups', 'Data ongkir per toko tidak tersedia.');
        }

        $items_by_seller = [];
        foreach ($order_items as $line) {
            $seller_id = isset($line['seller_id']) ? (int) $line['seller_id'] : 0;
            if ($seller_id <= 0) {
                continue;
            }
            if (!isset($items_by_seller[$seller_id])) {
                $items_by_seller[$seller_id] = [];
            }
            $items_by_seller[$seller_id][] = $line;
        }

        $submitted_map = [];
        foreach ((array) $submitted_groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $seller_id = isset($group['seller_id']) ? (int) $group['seller_id'] : 0;
            if ($seller_id <= 0) {
                continue;
            }
            $submitted_map[$seller_id] = [
                'seller_id' => $seller_id,
                'courier' => sanitize_key((string) ($group['courier'] ?? '')),
                'courier_name' => sanitize_text_field((string) ($group['courier_name'] ?? '')),
                'service' => sanitize_text_field((string) ($group['service'] ?? '')),
                'description' => sanitize_text_field((string) ($group['description'] ?? '')),
                'cost' => max(0, (float) ($group['cost'] ?? 0)),
                'etd' => sanitize_text_field((string) ($group['etd'] ?? '')),
            ];
        }

        $result = [];
        foreach ($context_groups as $context_group) {
            $seller_id = isset($context_group['seller_id']) ? (int) $context_group['seller_id'] : 0;
            if ($seller_id <= 0) {
                continue;
            }
            if (empty($submitted_map[$seller_id])) {
                if ($payment_method !== 'cod') {
                    return new \WP_Error('missing_shipping_selection', 'Pilihan ongkir untuk salah satu toko belum dipilih.');
                }
            }

            $selection = $submitted_map[$seller_id] ?? [];
            $cod_city_ids = isset($context_group['cod_city_ids']) && is_array($context_group['cod_city_ids'])
                ? array_values(array_filter(array_map('strval', $context_group['cod_city_ids'])))
                : [];
            $is_cod_available = !empty($context_group['cod_enabled'])
                && !empty($destination['city_destination_id'])
                && in_array((string) $destination['city_destination_id'], $cod_city_ids, true);

            if ($payment_method === 'cod') {
                if (!$is_cod_available) {
                    return new \WP_Error('cod_not_available', 'COD tidak tersedia untuk salah satu toko di kota tujuan yang dipilih.');
                }
                $selection = [
                    'seller_id' => $seller_id,
                    'courier' => 'cod',
                    'courier_name' => 'COD',
                    'service' => 'COD',
                    'description' => 'Bayar di tempat / temu langsung',
                    'cost' => 0,
                    'etd' => 'Sesuai kesepakatan',
                ];
            }

            if ($payment_method !== 'cod' && (($selection['courier'] ?? '') === '' || ($selection['service'] ?? '') === '')) {
                return new \WP_Error('invalid_shipping_selection', 'Pilihan ongkir per toko belum lengkap.');
            }

            $result[] = [
                'seller_id' => $seller_id,
                'seller_name' => isset($context_group['seller_name']) ? (string) $context_group['seller_name'] : '',
                'origin' => isset($context_group['origin']) && is_array($context_group['origin']) ? $context_group['origin'] : [],
                'courier' => $selection['courier'],
                'courier_name' => $selection['courier_name'],
                'service' => $selection['service'],
                'description' => $selection['description'],
                'cost' => (float) $selection['cost'],
                'etd' => $selection['etd'],
                'weight_grams' => isset($context_group['weight_grams']) ? (int) $context_group['weight_grams'] : 0,
                'subtotal' => isset($context_group['subtotal']) ? (float) $context_group['subtotal'] : 0,
                'items_count' => isset($context_group['items_count']) ? (int) $context_group['items_count'] : 0,
                'items' => array_values($items_by_seller[$seller_id] ?? []),
                'destination' => $destination,
                'cod_enabled' => !empty($context_group['cod_enabled']),
                'cod_city_ids' => $cod_city_ids,
                'cod_city_names' => isset($context_group['cod_city_names']) && is_array($context_group['cod_city_names']) ? array_values($context_group['cod_city_names']) : [],
                'status' => $initial_status,
                'receipt_no' => '',
                'receipt_courier' => '',
                'seller_note' => '',
            ];
        }

        return $result;
    }
}


