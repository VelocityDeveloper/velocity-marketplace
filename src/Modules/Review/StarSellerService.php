<?php

namespace VelocityMarketplace\Modules\Review;

use VelocityMarketplace\Modules\Order\OrderData;

class StarSellerService
{
    const MIN_COMPLETED_ORDERS = 10;
    const MIN_RATING_AVERAGE = 4.7;
    const MIN_RATING_COUNT = 5;
    const MAX_CANCEL_RATE = 5.0;

    public function recalculate($seller_id)
    {
        $seller_id = (int) $seller_id;
        if ($seller_id <= 0) {
            return [
                'completed_orders' => 0,
                'cancelled_orders' => 0,
                'cancel_rate' => 0.0,
                'rating_average' => 0.0,
                'rating_count' => 0,
                'is_star_seller' => false,
            ];
        }

        $completed_orders = 0;
        $cancelled_orders = 0;

        $query = new \WP_Query([
            'post_type' => 'store_order',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_key' => 'vmp_created_at',
            'orderby' => 'meta_value',
            'order' => 'DESC',
        ]);

        if (!empty($query->posts) && is_array($query->posts)) {
            foreach ($query->posts as $order_id) {
                $order_id = (int) $order_id;
                if ($order_id <= 0 || !OrderData::has_seller($order_id, $seller_id)) {
                    continue;
                }

                $status = (string) get_post_meta($order_id, 'vmp_status', true);
                if ($status === 'completed') {
                    $completed_orders++;
                } elseif (in_array($status, ['cancelled', 'refunded'], true)) {
                    $cancelled_orders++;
                }
            }
        }

        $final_orders = $completed_orders + $cancelled_orders;
        $cancel_rate = $final_orders > 0 ? round(($cancelled_orders / $final_orders) * 100, 2) : 0.0;

        $rating_stats = (new ReviewRepository())->seller_rating_stats($seller_id);
        $rating_average = isset($rating_stats['rating_average']) ? (float) $rating_stats['rating_average'] : 0.0;
        $rating_count = isset($rating_stats['rating_count']) ? (int) $rating_stats['rating_count'] : 0;

        $is_star_seller = $completed_orders >= self::MIN_COMPLETED_ORDERS
            && $rating_count >= self::MIN_RATING_COUNT
            && $rating_average >= self::MIN_RATING_AVERAGE
            && $cancel_rate <= self::MAX_CANCEL_RATE;

        $override = (string) get_user_meta($seller_id, 'vmp_star_seller_override', true);
        if ($override === '' || !in_array($override, ['auto', 'force_on', 'force_off'], true)) {
            $override = 'auto';
        }
        $effective_star_seller = $is_star_seller;
        if ($override === 'force_on') {
            $effective_star_seller = true;
        } elseif ($override === 'force_off') {
            $effective_star_seller = false;
        }

        update_user_meta($seller_id, 'vmp_completed_order_count', $completed_orders);
        update_user_meta($seller_id, 'vmp_cancelled_order_count', $cancelled_orders);
        update_user_meta($seller_id, 'vmp_cancel_rate', $cancel_rate);
        update_user_meta($seller_id, 'vmp_rating_average', $rating_average);
        update_user_meta($seller_id, 'vmp_rating_count', $rating_count);
        update_user_meta($seller_id, 'vmp_star_seller_auto', $is_star_seller ? 1 : 0);
        update_user_meta($seller_id, 'vmp_is_star_seller', $effective_star_seller ? 1 : 0);

        return [
            'completed_orders' => $completed_orders,
            'cancelled_orders' => $cancelled_orders,
            'cancel_rate' => $cancel_rate,
            'rating_average' => $rating_average,
            'rating_count' => $rating_count,
            'auto_star_seller' => $is_star_seller,
            'is_star_seller' => $effective_star_seller,
            'override' => $override,
        ];
    }

    public function summary($seller_id)
    {
        $seller_id = (int) $seller_id;
        if ($seller_id <= 0) {
            return $this->recalculate($seller_id);
        }

        if (
            !metadata_exists('user', $seller_id, 'vmp_completed_order_count')
            || !metadata_exists('user', $seller_id, 'vmp_cancel_rate')
            || !metadata_exists('user', $seller_id, 'vmp_rating_average')
            || !metadata_exists('user', $seller_id, 'vmp_rating_count')
            || !metadata_exists('user', $seller_id, 'vmp_star_seller_auto')
            || !metadata_exists('user', $seller_id, 'vmp_is_star_seller')
        ) {
            return $this->recalculate($seller_id);
        }

        $completed_orders = (int) get_user_meta($seller_id, 'vmp_completed_order_count', true);
        $cancelled_orders = (int) get_user_meta($seller_id, 'vmp_cancelled_order_count', true);
        $cancel_rate = (float) get_user_meta($seller_id, 'vmp_cancel_rate', true);
        $rating_average = (float) get_user_meta($seller_id, 'vmp_rating_average', true);
        $rating_count = (int) get_user_meta($seller_id, 'vmp_rating_count', true);
        $auto_star_seller = !empty(get_user_meta($seller_id, 'vmp_star_seller_auto', true));
        $is_star_seller = !empty(get_user_meta($seller_id, 'vmp_is_star_seller', true));
        $override = (string) get_user_meta($seller_id, 'vmp_star_seller_override', true);
        if ($override === '' || !in_array($override, ['auto', 'force_on', 'force_off'], true)) {
            $override = 'auto';
        }

        return [
            'completed_orders' => $completed_orders,
            'cancelled_orders' => $cancelled_orders,
            'cancel_rate' => $cancel_rate,
            'rating_average' => $rating_average,
            'rating_count' => $rating_count,
            'auto_star_seller' => $auto_star_seller,
            'is_star_seller' => $is_star_seller,
            'override' => $override,
        ];
    }
}

