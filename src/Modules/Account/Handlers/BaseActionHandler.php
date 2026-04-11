<?php

namespace VelocityMarketplace\Modules\Account\Handlers;

use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Modules\Review\StarSellerService;
use VelocityMarketplace\Support\Settings;

abstract class BaseActionHandler
{
    protected function redirect_with($params = [])
    {
        $target = wp_get_referer();
        if (!$target) {
            $target = Settings::profile_url();
        }

        $target = remove_query_arg([
            'vmp_notice',
            'vmp_error',
            'vmp_error_field',
            'invoice',
            'message_to',
            'message_order',
        ], $target);

        $url = add_query_arg($params, $target);
        wp_safe_redirect($url);
        exit;
    }

    protected function stay_with($params = [])
    {
        foreach (['vmp_notice', 'vmp_error', 'vmp_error_field', 'invoice', 'message_to', 'message_order'] as $key) {
            unset($_GET[$key], $_POST[$key], $_REQUEST[$key]);
        }

        foreach ((array) $params as $key => $value) {
            $_GET[$key] = $value;
            $_REQUEST[$key] = $value;
        }
    }

    protected function attachment_allowed_for_current_user($attachment_id)
    {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        return (int) get_post_field('post_author', $attachment_id) === get_current_user_id();
    }

    protected function refresh_star_seller_for_order($order_id)
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return;
        }

        $service = new StarSellerService();
        $seller_ids = [];
        foreach (OrderData::get_items($order_id) as $item) {
            $seller_id = isset($item['seller_id']) ? (int) $item['seller_id'] : 0;
            if ($seller_id > 0) {
                $seller_ids[$seller_id] = $seller_id;
            }
        }

        foreach ($seller_ids as $seller_id) {
            $service->recalculate($seller_id);
        }
    }
}
