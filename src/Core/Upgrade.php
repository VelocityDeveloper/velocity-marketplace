<?php

namespace VelocityMarketplace\Core;

use VelocityMarketplace\Modules\Message\MessageTable;
use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Modules\Product\ProductMeta;
use VelocityMarketplace\Modules\Review\ReviewTable;
use VelocityMarketplace\Support\Contract;

class Upgrade
{
    const DB_VERSION = '2.0.0';

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

        $this->migrate_shared_contract();
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

    private function migrate_shared_contract()
    {
        $this->migrate_post_type(Contract::LEGACY_PRODUCT_POST_TYPE, Contract::PRODUCT_POST_TYPE);
        $this->migrate_post_type(Contract::LEGACY_ORDER_POST_TYPE, Contract::ORDER_POST_TYPE);
        $this->migrate_post_type(Contract::LEGACY_COUPON_POST_TYPE, Contract::COUPON_POST_TYPE);
        $this->migrate_product_terms();
        $this->migrate_product_meta_schema();
    }

    private function migrate_post_type($from, $to)
    {
        global $wpdb;

        $from = (string) $from;
        $to = (string) $to;
        if ($from === '' || $to === '' || $from === $to) {
            return;
        }

        $wpdb->update(
            $wpdb->posts,
            ['post_type' => $to],
            ['post_type' => $from]
        );
    }

    private function migrate_product_terms()
    {
        $legacy_terms = get_terms([
            'taxonomy' => Contract::LEGACY_PRODUCT_TAXONOMY,
            'hide_empty' => false,
        ]);

        if (is_wp_error($legacy_terms) || empty($legacy_terms)) {
            return;
        }

        $legacy_terms = is_array($legacy_terms) ? $legacy_terms : [];
        usort($legacy_terms, static function ($left, $right) {
            return ((int) $left->parent) <=> ((int) $right->parent);
        });

        $term_map = [];
        foreach ($legacy_terms as $legacy_term) {
            if (!($legacy_term instanceof \WP_Term)) {
                continue;
            }

            $args = [
                'slug' => $legacy_term->slug,
                'description' => $legacy_term->description,
            ];

            $legacy_parent = (int) $legacy_term->parent;
            if ($legacy_parent > 0 && isset($term_map[$legacy_parent])) {
                $args['parent'] = (int) $term_map[$legacy_parent];
            }

            $existing = get_term_by('slug', $legacy_term->slug, Contract::PRODUCT_TAXONOMY);
            if ($existing && !is_wp_error($existing)) {
                $term_map[(int) $legacy_term->term_id] = (int) $existing->term_id;
                continue;
            }

            $created = wp_insert_term($legacy_term->name, Contract::PRODUCT_TAXONOMY, $args);
            if (!is_wp_error($created) && !empty($created['term_id'])) {
                $term_map[(int) $legacy_term->term_id] = (int) $created['term_id'];
            }
        }

        if (empty($term_map)) {
            return;
        }

        $product_ids = get_posts([
            'post_type' => Contract::PRODUCT_POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        foreach ((array) $product_ids as $product_id) {
            $product_id = (int) $product_id;
            if ($product_id <= 0) {
                continue;
            }

            $legacy_term_ids = wp_get_object_terms($product_id, Contract::LEGACY_PRODUCT_TAXONOMY, ['fields' => 'ids']);
            if (is_wp_error($legacy_term_ids) || empty($legacy_term_ids)) {
                continue;
            }

            $canonical_term_ids = [];
            foreach ((array) $legacy_term_ids as $legacy_term_id) {
                $legacy_term_id = (int) $legacy_term_id;
                if ($legacy_term_id > 0 && isset($term_map[$legacy_term_id])) {
                    $canonical_term_ids[] = (int) $term_map[$legacy_term_id];
                }
            }

            if (!empty($canonical_term_ids)) {
                wp_set_object_terms($product_id, array_values(array_unique($canonical_term_ids)), Contract::PRODUCT_TAXONOMY, false);
            }
        }
    }

    private function migrate_product_meta_schema()
    {
        $product_ids = get_posts([
            'post_type' => Contract::PRODUCT_POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        foreach ((array) $product_ids as $product_id) {
            $product_id = (int) $product_id;
            if ($product_id <= 0) {
                continue;
            }

            ProductMeta::update_logical($product_id, 'product_type', ProductMeta::get_text($product_id, 'product_type', 'physical'));
            ProductMeta::update_logical($product_id, 'price', ProductMeta::get_number($product_id, 'price', 0));

            $sale_price = ProductMeta::get_number($product_id, 'sale_price', '');
            if ($sale_price !== '') {
                ProductMeta::update_logical($product_id, 'sale_price', $sale_price);
            }

            $sale_until = ProductMeta::get_text($product_id, 'sale_until', '');
            if ($sale_until !== '') {
                ProductMeta::update_logical($product_id, 'sale_until', $sale_until);
            }

            $sku = ProductMeta::get_text($product_id, 'sku', '');
            if ($sku !== '') {
                ProductMeta::update_logical($product_id, 'sku', $sku);
            }

            $stock = ProductMeta::get_number($product_id, 'stock', '');
            if ($stock !== '') {
                ProductMeta::update_logical($product_id, 'stock', $stock);
            }

            $min_order = ProductMeta::get_number($product_id, 'min_order', 1);
            ProductMeta::update_logical($product_id, 'min_order', max(1, (int) $min_order));

            $weight = ProductMeta::get_number($product_id, 'weight', 0);
            if ($weight > 0) {
                ProductMeta::update_logical($product_id, 'weight', $weight);
            }

            $label = ProductMeta::get_text($product_id, 'label', '');
            if ($label !== '') {
                ProductMeta::update_logical($product_id, 'label', $label);
            }

            $gallery_ids = ProductMeta::get_attachment_ids($product_id, 'gallery_ids');
            if (!empty($gallery_ids)) {
                ProductMeta::update_logical($product_id, 'gallery_ids', $gallery_ids);
            }

            $variant_name = ProductMeta::get_text($product_id, 'variant_name', '');
            if ($variant_name !== '') {
                ProductMeta::update_logical($product_id, 'variant_name', $variant_name);
            }

            $variant_options = ProductMeta::get_variant_options($product_id);
            if (!empty($variant_options)) {
                ProductMeta::update_logical($product_id, 'variant_options', $variant_options);
            }

            $price_adjustment_name = ProductMeta::get_text($product_id, 'price_adjustment_name', '');
            if ($price_adjustment_name !== '') {
                ProductMeta::update_logical($product_id, 'price_adjustment_name', $price_adjustment_name);
            }

            $price_adjustment_options = ProductMeta::get_price_adjustment_options($product_id);
            if (!empty($price_adjustment_options)) {
                $rows = [];
                foreach ($price_adjustment_options as $row) {
                    $rows[] = [
                        'label' => (string) ($row['label'] ?? ''),
                        'price' => (float) ($row['amount'] ?? 0),
                    ];
                }
                ProductMeta::update_logical($product_id, 'price_adjustment_options', $rows);
            }
        }
    }

    private function backfill_sold_count()
    {
        $product_ids = get_posts([
            'post_type' => Contract::product_post_types(),
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
            'post_type' => Contract::order_post_types(),
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
                        if ($product_id <= 0 || $qty <= 0 || !Contract::is_product($product_id)) {
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
                if ($product_id <= 0 || $qty <= 0 || !Contract::is_product($product_id)) {
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
