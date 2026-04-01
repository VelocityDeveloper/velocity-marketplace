<?php

namespace VelocityMarketplace\Core;

use VelocityMarketplace\Modules\Message\MessageTable;
use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Modules\Product\ProductData;
use VelocityMarketplace\Modules\Review\ReviewTable;

class Upgrade
{
    const DB_VERSION = '1.9.0';

    public function register()
    {
        add_action('init', [$this, 'maybe_upgrade']);
    }

    public function activate()
    {
        $installer = new Installer();
        $installer->activate();

        $messages = new MessageTable();
        $messages->create_table();

        $reviews = new ReviewTable();
        $reviews->create_table();

        $this->backfill_sold_count();

        update_option(VMP_DB_VERSION_OPTION, self::DB_VERSION);
    }

    public function maybe_upgrade()
    {
        $version = (string) get_option(VMP_DB_VERSION_OPTION, '');
        if ($version === self::DB_VERSION) {
            return;
        }

        $this->activate();
    }

    private function backfill_sold_count()
    {
        $product_ids = get_posts([
            'post_type' => 'vmp_product',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        if (!empty($product_ids) && is_array($product_ids)) {
            foreach ($product_ids as $product_id) {
                update_post_meta((int) $product_id, 'vmp_sold_count', 0);
            }
        }

        $order_ids = get_posts([
            'post_type' => 'vmp_order',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        if (empty($order_ids) || !is_array($order_ids)) {
            return;
        }

        $totals = [];

        foreach ($order_ids as $order_id) {
            $order_id = (int) $order_id;
            if ($order_id <= 0) {
                continue;
            }

            $global_status = (string) get_post_meta($order_id, 'vmp_status', true);
            $shipping_groups = OrderData::shipping_groups($order_id);

            if (!empty($shipping_groups)) {
                $dirty = false;

                foreach ($shipping_groups as $index => $shipping_group) {
                    if (!is_array($shipping_group)) {
                        continue;
                    }

                    $group_status = OrderData::shipping_group_status($shipping_group, $global_status !== '' ? $global_status : 'pending_payment');
                    if ($group_status !== 'completed') {
                        continue;
                    }

                    $group_items = isset($shipping_group['items']) && is_array($shipping_group['items'])
                        ? $shipping_group['items']
                        : OrderData::seller_items($order_id, (int) ($shipping_group['seller_id'] ?? 0));

                    foreach ($group_items as $group_item) {
                        $product_id = isset($group_item['product_id']) ? (int) $group_item['product_id'] : 0;
                        $qty = isset($group_item['qty']) ? (int) $group_item['qty'] : 0;
                        if ($product_id <= 0 || $qty <= 0 || get_post_type($product_id) !== 'vmp_product') {
                            continue;
                        }

                        if (!isset($totals[$product_id])) {
                            $totals[$product_id] = 0;
                        }
                        $totals[$product_id] += $qty;
                    }

                    if (empty($shipping_groups[$index]['sold_count_recorded'])) {
                        $shipping_groups[$index]['sold_count_recorded'] = 1;
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    update_post_meta($order_id, 'vmp_shipping_groups', array_values($shipping_groups));
                }

                continue;
            }

            if (OrderData::normalize_status($global_status) !== 'completed') {
                continue;
            }

            $items = get_post_meta($order_id, 'vmp_items', true);
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $product_id = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                $qty = isset($item['qty']) ? (int) $item['qty'] : 0;
                if ($product_id <= 0 || $qty <= 0 || get_post_type($product_id) !== 'vmp_product') {
                    continue;
                }

                if (!isset($totals[$product_id])) {
                    $totals[$product_id] = 0;
                }
                $totals[$product_id] += $qty;
            }
        }

        foreach ($totals as $product_id => $qty) {
            update_post_meta((int) $product_id, 'vmp_sold_count', max(0, (int) $qty));
        }
    }
}
