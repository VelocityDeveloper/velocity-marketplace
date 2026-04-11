<?php

namespace VelocityMarketplace\Modules\Coupon;

class CouponService
{
    public function find_by_code($code)
    {
        $code = strtoupper(sanitize_text_field((string) $code));
        if ($code === '') {
            return null;
        }

        $query = new \WP_Query([
            'post_type' => 'store_coupon',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_store_coupon_code',
                    'value' => $code,
                ],
            ],
            'fields' => 'ids',
        ]);

        if (!empty($query->posts[0])) {
            return $this->normalize((int) $query->posts[0]);
        }

        $query = new \WP_Query([
            'post_type' => 'store_coupon',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            's' => $code,
            'fields' => 'ids',
        ]);

        foreach ((array) $query->posts as $coupon_id) {
            $coupon = $this->normalize((int) $coupon_id);
            if ($coupon && strtoupper((string) ($coupon['code'] ?? '')) === $code) {
                return $coupon;
            }
        }

        return null;
    }

    public function preview($code, $subtotal, $shipping_total = 0)
    {
        $subtotal = (float) $subtotal;
        $shipping_total = (float) $shipping_total;
        $coupon = $this->find_by_code($code);
        if (!$coupon) {
            return new \WP_Error('coupon_not_found', __('Coupon code not found.', 'velocity-marketplace'));
        }

        if (empty($coupon['is_active'])) {
            return new \WP_Error('coupon_inactive', __('This coupon is currently inactive.', 'velocity-marketplace'));
        }

        $now = current_time('timestamp');
        if (!empty($coupon['starts_at']) && strtotime((string) $coupon['starts_at']) > $now) {
            return new \WP_Error('coupon_not_started', __('This coupon is not available yet.', 'velocity-marketplace'));
        }
        if (!empty($coupon['ends_at']) && strtotime((string) $coupon['ends_at']) < $now) {
            return new \WP_Error('coupon_expired', __('This coupon has expired.', 'velocity-marketplace'));
        }

        if ($subtotal <= 0) {
            return new \WP_Error('coupon_invalid_cart', __('Subtotal keranjang belum valid.', 'velocity-marketplace'));
        }

        if ($subtotal < (float) $coupon['min_purchase']) {
            return new \WP_Error(
                'coupon_min_purchase',
                sprintf(
                    __('The minimum purchase for this coupon is Rp %s.', 'velocity-marketplace'),
                    number_format((float) $coupon['min_purchase'], 0, ',', '.')
                )
            );
        }

        if ((int) $coupon['usage_limit'] > 0 && (int) $coupon['usage_count'] >= (int) $coupon['usage_limit']) {
            return new \WP_Error('coupon_usage_limit', __('This coupon has reached its usage limit.', 'velocity-marketplace'));
        }

        $product_discount = 0.0;
        $shipping_discount = 0.0;
        $scope = (string) ($coupon['scope'] ?? 'product');

        if ($scope === 'shipping') {
            if ($shipping_total <= 0) {
                return new \WP_Error('coupon_invalid_shipping', __('Kupon ongkir hanya bisa digunakan setelah ongkir tersedia.', 'velocity-marketplace'));
            }

            if ($coupon['type'] === 'percent') {
                $shipping_discount = $shipping_total * ((float) $coupon['amount'] / 100);
            } else {
                $shipping_discount = (float) $coupon['amount'];
            }
            $shipping_discount = min($shipping_total, max(0, $shipping_discount));
        } else {
            if ($coupon['type'] === 'percent') {
                $product_discount = $subtotal * ((float) $coupon['amount'] / 100);
            } else {
                $product_discount = (float) $coupon['amount'];
            }
            $product_discount = min($subtotal, max(0, $product_discount));
        }

        $discount = $product_discount + $shipping_discount;
        if ($discount <= 0.0) {
            return new \WP_Error('coupon_zero_discount', __('This coupon does not provide a discount for this transaction.', 'velocity-marketplace'));
        }

        $coupon['product_discount'] = round($product_discount, 2);
        $coupon['shipping_discount'] = round($shipping_discount, 2);
        $coupon['discount'] = round($discount, 2);
        return $coupon;
    }

    public function increment_usage($coupon_id)
    {
        $coupon_id = (int) $coupon_id;
        if ($coupon_id <= 0 || get_post_type($coupon_id) !== 'store_coupon') {
            return;
        }

        $usage_count = (int) get_post_meta($coupon_id, '_store_coupon_usage_count', true);
        update_post_meta($coupon_id, '_store_coupon_usage_count', $usage_count + 1);
    }

    public function normalize($coupon_id)
    {
        $coupon_id = (int) $coupon_id;
        if ($coupon_id <= 0 || get_post_type($coupon_id) !== 'store_coupon') {
            return null;
        }

        $stored_code = strtoupper((string) get_post_meta($coupon_id, '_store_coupon_code', true));
        if ($stored_code === '') {
            $stored_code = strtoupper((string) get_the_title($coupon_id));
        }

        return [
            'id' => $coupon_id,
            'code' => $stored_code,
            'scope' => (string) get_post_meta($coupon_id, '_store_coupon_scope', true) === 'shipping' ? 'shipping' : 'product',
            'type' => (string) get_post_meta($coupon_id, '_store_coupon_type', true) === 'percent' ? 'percent' : 'nominal',
            'amount' => (float) get_post_meta($coupon_id, '_store_coupon_value', true),
            'min_purchase' => (float) get_post_meta($coupon_id, '_store_coupon_min_purchase', true),
            'usage_limit' => (int) get_post_meta($coupon_id, '_store_coupon_usage_limit', true),
            'usage_count' => (int) get_post_meta($coupon_id, '_store_coupon_usage_count', true),
            'starts_at' => (string) get_post_meta($coupon_id, '_store_coupon_starts_at', true),
            'ends_at' => (string) get_post_meta($coupon_id, '_store_coupon_expires_at', true),
            'is_active' => get_post_status($coupon_id) === 'publish',
        ];
    }
}

