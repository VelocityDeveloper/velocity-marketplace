<?php

namespace VelocityMarketplace\Modules\Order;

use WpStore\Domain\Order\OrderService;

class OrderData
{
    public static function core_service()
    {
        return new OrderService();
    }

    public static function statuses()
    {
        return [
            'pending_payment' => 'Menunggu Pembayaran',
            'pending_verification' => 'Menunggu Verifikasi',
            'processing' => 'Diproses',
            'shipped' => 'Dikirim',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'refunded' => 'Refund',
        ];
    }

    public static function normalize_status($status)
    {
        $status = sanitize_key((string) $status);
        $statuses = self::statuses();
        if (!isset($statuses[$status])) {
            return 'pending_payment';
        }
        return $status;
    }

    public static function status_label($status)
    {
        $statuses = self::statuses();
        $status = self::normalize_status($status);
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }

    public static function status_badge_class($status)
    {
        $status = self::normalize_status($status);

        $map = [
            'pending_payment' => 'secondary',
            'pending_verification' => 'warning text-dark',
            'processing' => 'info text-dark',
            'shipped' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
            'refunded' => 'dark',
        ];

        return isset($map[$status]) ? $map[$status] : 'secondary';
    }

    public static function timeline_steps($status, $received_at = '')
    {
        $status = self::normalize_status($status);
        $received_at = is_string($received_at) ? trim($received_at) : '';

        if (in_array($status, ['cancelled', 'refunded'], true)) {
            return [];
        }

        $current_step = 1;
        if (in_array($status, ['processing'], true)) {
            $current_step = 2;
        } elseif (in_array($status, ['shipped'], true)) {
            $current_step = 3;
        } elseif (in_array($status, ['completed'], true)) {
            $current_step = 4;
        }

        $steps = [
            [
                'key' => 'paid',
                'label' => __('Pembayaran', 'velocity-marketplace'),
                'description' => in_array($status, ['pending_payment', 'pending_verification'], true)
                    ? __('Menunggu pembayaran dikonfirmasi.', 'velocity-marketplace')
                    : __('Pembayaran sudah dikonfirmasi.', 'velocity-marketplace'),
            ],
            [
                'key' => 'processed',
                'label' => __('Diproses', 'velocity-marketplace'),
                'description' => __('Pesanan sedang disiapkan oleh toko.', 'velocity-marketplace'),
            ],
            [
                'key' => 'shipped',
                'label' => __('Dikirim', 'velocity-marketplace'),
                'description' => __('Pesanan sudah diserahkan ke kurir.', 'velocity-marketplace'),
            ],
            [
                'key' => 'completed',
                'label' => __('Selesai', 'velocity-marketplace'),
                'description' => $received_at !== ''
                    ? sprintf(__('Diterima pada %s.', 'velocity-marketplace'), $received_at)
                    : __('Pesanan sudah diterima pembeli.', 'velocity-marketplace'),
            ],
        ];

        foreach ($steps as $index => $step) {
            $position = $index + 1;
            $steps[$index]['is_complete'] = $position < $current_step;
            $steps[$index]['is_current'] = $position === $current_step;
            $steps[$index]['is_pending'] = $position > $current_step;
        }

        return $steps;
    }

    public static function get_items($order_id)
    {
        $order_id = (int) $order_id;
        $items = get_post_meta($order_id, '_store_order_items', true);
        return is_array($items) ? $items : [];
    }

    public static function buyer_id($order_id)
    {
        return (int) get_post_meta((int) $order_id, '_store_order_user_id', true);
    }

    public static function core_status($status)
    {
        return self::core_service()->map_external_status($status);
    }

    public static function core_payment_method($payment_method)
    {
        return self::core_service()->normalize_payment_method($payment_method);
    }

    public static function sync_core_status($order_id, $status)
    {
        self::core_service()->update_status((int) $order_id, self::core_status($status));
    }

    public static function sync_core_payment($order_id, array $payment_info)
    {
        self::core_service()->update_payment_data((int) $order_id, $payment_info);
    }

    public static function seller_items($order_id, $seller_id)
    {
        $seller_id = (int) $seller_id;
        if ($seller_id <= 0) {
            return [];
        }

        $items = self::get_items($order_id);
        $owned = [];
        foreach ($items as $line) {
            $line_seller = isset($line['seller_id']) ? (int) $line['seller_id'] : 0;
            if ($line_seller === $seller_id) {
                $owned[] = $line;
            }
        }
        return $owned;
    }

    public static function seller_total($order_id, $seller_id)
    {
        $total = 0;
        foreach (self::seller_items($order_id, $seller_id) as $line) {
            $total += isset($line['subtotal']) ? (float) $line['subtotal'] : 0;
        }
        return (float) $total;
    }

    public static function has_seller($order_id, $seller_id)
    {
        return !empty(self::seller_items($order_id, $seller_id));
    }

    public static function seller_ids($order_id)
    {
        $seller_ids = [];
        foreach (self::get_items($order_id) as $line) {
            $seller_id = isset($line['seller_id']) ? (int) $line['seller_id'] : 0;
            if ($seller_id > 0) {
                $seller_ids[$seller_id] = $seller_id;
            }
        }

        return array_values($seller_ids);
    }

    public static function seller_requires_shipping($order_id, $seller_id)
    {
        foreach (self::seller_items($order_id, $seller_id) as $line) {
            $product_id = isset($line['product_id']) ? (int) $line['product_id'] : 0;
            $is_digital = array_key_exists('is_digital', $line)
                ? !empty($line['is_digital'])
                : ($product_id > 0 && \WpStore\Domain\Product\ProductData::is_digital($product_id));

            if (!$is_digital) {
                return true;
            }
        }

        return false;
    }

    public static function seller_can_update_global_status($order_id, $seller_id)
    {
        $seller_ids = self::seller_ids($order_id);
        return self::has_seller($order_id, $seller_id) && count($seller_ids) === 1;
    }

    public static function shipping_groups($order_id)
    {
        $groups = get_post_meta((int) $order_id, 'vmp_shipping_groups', true);
        if (is_array($groups) && !empty($groups)) {
            $normalized_groups = self::normalize_shipping_groups((int) $order_id, $groups);
            if ($normalized_groups !== $groups) {
                update_post_meta((int) $order_id, 'vmp_shipping_groups', array_values($normalized_groups));
            }
            return array_values($normalized_groups);
        }

        return [];
    }

    public static function shipping_group_status(array $group, $fallback = 'pending_payment')
    {
        $status = isset($group['status']) ? (string) $group['status'] : '';
        if ($status === '') {
            $status = (string) $fallback;
        }

        return self::normalize_status($status);
    }

    public static function summarize_shipping_statuses(array $groups, $fallback = 'pending_payment')
    {
        if (empty($groups)) {
            return self::normalize_status($fallback);
        }

        $statuses = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $statuses[] = self::shipping_group_status($group, $fallback);
        }

        $statuses = array_values(array_unique(array_filter($statuses)));
        if (empty($statuses)) {
            return self::normalize_status($fallback);
        }

        if (count($statuses) === 1) {
            return self::normalize_status($statuses[0]);
        }

        return 'processing';
    }

    public static function infer_missing_group_status(array $group, $global_status = 'pending_payment', $payment_method = '', $has_transfer_proof = false)
    {
        $global_status = self::normalize_status($global_status);
        $payment_method = sanitize_key((string) $payment_method);
        $has_transfer_proof = (bool) $has_transfer_proof;

        if (in_array($global_status, ['completed', 'cancelled', 'refunded'], true)) {
            return $global_status;
        }

        if (!empty($group['receipt_no'])) {
            return 'shipped';
        }

        if ($has_transfer_proof || $global_status === 'pending_verification') {
            return 'pending_verification';
        }

        if ($payment_method === 'cod') {
            return 'processing';
        }

        if ($global_status === 'pending_payment') {
            return 'pending_payment';
        }

        return 'processing';
    }

    private static function normalize_shipping_groups($order_id, array $groups)
    {
        $order_id = (int) $order_id;
        $global_status = (string) get_post_meta($order_id, 'vmp_status', true);
        $payment_method = (string) get_post_meta($order_id, 'vmp_payment_method', true);
        $has_transfer_proof = (int) get_post_meta($order_id, 'vmp_transfer_proof_id', true) > 0;

        foreach ($groups as $index => $group) {
            if (!is_array($group)) {
                continue;
            }

            $status = isset($group['status']) ? (string) $group['status'] : '';
            if ($status === '') {
                $groups[$index]['status'] = self::infer_missing_group_status($group, $global_status, $payment_method, $has_transfer_proof);
                continue;
            }

            $normalized_status = self::normalize_status($status);
            if ($normalized_status !== $status) {
                $groups[$index]['status'] = $normalized_status;
            }
        }

        return $groups;
    }

    public static function seller_shipping_group($order_id, $seller_id)
    {
        $seller_id = (int) $seller_id;
        foreach (self::shipping_groups($order_id) as $group) {
            if ((int) ($group['seller_id'] ?? 0) === $seller_id) {
                return $group;
            }
        }

        return null;
    }

    public static function seller_orders_query($seller_id, $limit = 100)
    {
        $seller_id = (int) $seller_id;
        if ($seller_id <= 0) {
            return [];
        }

        $limit = max(1, min(300, (int) $limit));
        $query = new \WP_Query([
            'post_type' => 'store_order',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => 'vmp_created_at',
            'orderby' => 'meta_value',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);

        if (empty($query->posts) || !is_array($query->posts)) {
            return [];
        }

        $result = [];
        foreach ($query->posts as $order_id) {
            $order_id = (int) $order_id;
            if ($order_id > 0 && self::has_seller($order_id, $seller_id)) {
                $result[] = $order_id;
            }
        }

        return $result;
    }
}



