<?php

namespace VelocityMarketplace\Support;

class OrderData
{
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

    public static function get_items($order_id)
    {
        $items = get_post_meta((int) $order_id, 'vmp_items', true);
        return is_array($items) ? $items : [];
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

    public static function seller_orders_query($seller_id, $limit = 100)
    {
        $seller_id = (int) $seller_id;
        if ($seller_id <= 0) {
            return [];
        }

        $limit = max(1, min(300, (int) $limit));
        $query = new \WP_Query([
            'post_type' => 'vmp_order',
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

