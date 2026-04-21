<?php

namespace VelocityMarketplace\Modules\Cart;

use VelocityMarketplace\Modules\Product\ProductData;
use VelocityMarketplace\Modules\Product\ProductMeta;
use VelocityMarketplace\Support\Contract;
use VelocityMarketplace\Support\Settings;

class CartRepository
{
    public function get_cart_data()
    {
        $core = $this->core_service();
        $raw_items = $core->get_raw_items();
        $normalized_raw_items = $this->normalize_raw_items($raw_items);
        if (wp_json_encode(array_values((array) $raw_items)) !== wp_json_encode($normalized_raw_items)) {
            $core->write_raw_items($normalized_raw_items);
        }
        $raw_items = $normalized_raw_items;
        $items = $this->hydrate_items($raw_items);
        $snapshot = $this->resolve_marketplace_snapshot($core, $raw_items, $items);

        $total = 0;
        $count = 0;
        foreach ($items as $item) {
            $total += (float) $item['subtotal'];
            $count += (int) $item['qty'];
        }

        return [
            'items' => array_values($items),
            'total' => (float) $total,
            'count' => (int) $count,
            'seller_groups' => $this->format_seller_groups($snapshot),
            'marketplace_snapshot' => $snapshot,
        ];
    }

    public function upsert_item($product_id, $qty, $options = [], $cart_key = '', $add_qty = null)
    {
        $product_id = (int) $product_id;
        $qty = (int) $qty;
        $cart_key = is_string($cart_key) ? trim($cart_key) : '';
        $add_qty = $add_qty !== null ? max(0, (int) $add_qty) : null;

        if ($product_id <= 0 || !Contract::is_product($product_id)) {
            return new \WP_Error('invalid_product', 'Produk tidak valid');
        }

        $is_digital = \WpStore\Domain\Product\ProductData::is_digital($product_id);
        $weight = (float) ProductMeta::get_number($product_id, 'weight', 0);
        if (!$is_digital && $weight <= 0) {
            return new \WP_Error(
                'missing_weight',
                sprintf('Produk "%s" belum memiliki berat. Lengkapi berat produk sebelum dimasukkan ke keranjang.', get_the_title($product_id))
            );
        }

        $core = $this->core_service();
        $core->upsert_item($product_id, $qty, ProductData::normalize_options($product_id, $options), $cart_key, $add_qty);

        return true;
    }

    public function clear()
    {
        $this->core_service()->clear();
    }

    private function cart_key($product_id, $options = [])
    {
        $options = $this->core_service()->normalize_options(is_array($options) ? $options : []);
        return md5((string) $product_id . '|' . wp_json_encode($options));
    }

    private function hydrate_items($rows)
    {
        $seller_groups = [];
        $seller_order = [];

        foreach ($rows as $row_key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $product_id = isset($row['id']) ? (int) $row['id'] : 0;
            $qty = isset($row['qty']) ? (int) $row['qty'] : 0;
            $options = [];
            if (isset($row['options']) && is_array($row['options'])) {
                $options = $row['options'];
            } elseif (isset($row['opts']) && is_array($row['opts'])) {
                $options = $row['opts'];
            }

            if ($product_id <= 0 || $qty <= 0) {
                continue;
            }

            $product = ProductData::map_post($product_id);
            if (!$product) {
                continue;
            }
            $min_order = isset($product['min_order']) ? max(1, (int) $product['min_order']) : 1;
            $qty = max($min_order, $qty);

            $seller_id = isset($product['author_id']) ? (int) $product['author_id'] : 0;
            $seller_name = $this->seller_name($seller_id);
            $seller_url = $seller_id > 0 ? Settings::store_profile_url($seller_id) : '';

            $options = ProductData::normalize_options($product_id, $options);
            $price_adjustment_name = isset($product['price_adjustment_name']) ? (string) $product['price_adjustment_name'] : '';
            $price_adjustment_label = $price_adjustment_name !== '' && isset($options[$price_adjustment_name])
                ? (string) $options[$price_adjustment_name]
                : '';
            $price_adjustment = ProductData::resolve_price_adjustment($product_id, $price_adjustment_label);
            $price = (float) $product['price'] + (float) $price_adjustment;
            $subtotal = $price * $qty;

            $item = [
                'cart_key' => $this->cart_key($product_id, $options),
                'id' => $product_id,
                'title' => $product['title'],
                'link' => $product['link'],
                'image' => $product['image'],
                'qty' => $qty,
                'price' => (float) $price,
                'subtotal' => (float) $subtotal,
                'options' => $options,
                'penjual' => $seller_id,
                'seller_id' => $seller_id,
                'seller_name' => $seller_name,
                'seller_url' => $seller_url,
                'stock' => $product['stock'],
                'min_order' => $min_order,
                'weight' => isset($product['weight']) ? (float) $product['weight'] : 0,
                'is_digital' => !empty($product['is_digital']),
            ];

            if (!isset($seller_groups[$seller_id])) {
                $seller_groups[$seller_id] = [];
                $seller_order[] = $seller_id;
            }

            $seller_groups[$seller_id][] = $item;
        }

        $items = [];
        foreach ($seller_order as $seller_id) {
            foreach ($seller_groups[$seller_id] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function seller_name($seller_id)
    {
        $seller_id = (int) $seller_id;
        if ($seller_id <= 0) {
            return 'Toko';
        }

        $store_name = (string) get_user_meta($seller_id, 'vmp_store_name', true);
        if ($store_name !== '') {
            return $store_name;
        }

        $seller = get_userdata($seller_id);
        if ($seller && $seller->display_name !== '') {
            return (string) $seller->display_name;
        }

        return 'Toko #' . $seller_id;
    }

    private function core_service()
    {
        return new \WpStore\Domain\Cart\CartService();
    }

    private function normalize_raw_items($rows)
    {
        $rows = is_array($rows) ? array_values($rows) : [];
        $normalized = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $product_id = isset($row['id']) ? (int) $row['id'] : 0;
            $qty = isset($row['qty']) ? (int) $row['qty'] : 0;
            $options = [];
            if (isset($row['opts']) && is_array($row['opts'])) {
                $options = $row['opts'];
            } elseif (isset($row['options']) && is_array($row['options'])) {
                $options = $row['options'];
            }

            if ($product_id <= 0 || $qty <= 0 || !Contract::is_product($product_id)) {
                continue;
            }

            $options = ProductData::normalize_options($product_id, $options);
            $key = $this->cart_key($product_id, $options);

            if (!isset($normalized[$key])) {
                $normalized[$key] = [
                    'id' => $product_id,
                    'qty' => 0,
                    'opts' => $options,
                ];
            }

            $normalized[$key]['qty'] += $qty;
        }

        return array_values($normalized);
    }

    private function resolve_marketplace_snapshot($core, $raw_items, $items)
    {
        $raw_items = is_array($raw_items) ? array_values($raw_items) : [];
        $hash = $core->raw_cart_hash($raw_items);
        $snapshot = $core->get_marketplace_snapshot();

        if (
            is_array($snapshot)
            && !empty($snapshot)
            && isset($snapshot['cart_hash'])
            && (string) $snapshot['cart_hash'] === $hash
        ) {
            return $snapshot;
        }

        $snapshot = $this->build_marketplace_snapshot($raw_items, $items);
        $snapshot['cart_hash'] = $hash;
        $core->write_marketplace_snapshot($snapshot);

        return $snapshot;
    }

    private function build_marketplace_snapshot($raw_items, $items)
    {
        $groups = [];
        $order = [];

        foreach ((array) $items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $seller_id = isset($item['seller_id']) ? (int) $item['seller_id'] : 0;
            if (!isset($groups[$seller_id])) {
                $groups[$seller_id] = [
                    'seller_id' => $seller_id,
                    'subtotal' => 0.0,
                    'items_count' => 0,
                    'shippable_items_count' => 0,
                    'weight_kg' => 0.0,
                    'weight_grams' => 0,
                    'item_keys' => [],
                ];
                $order[] = $seller_id;
            }

            $qty = (int) ($item['qty'] ?? 0);
            $weight = (float) ($item['weight'] ?? 0);
            $subtotal = (float) ($item['subtotal'] ?? 0);
            $is_digital = !empty($item['is_digital']);
            $groups[$seller_id]['subtotal'] += $subtotal;
            $groups[$seller_id]['items_count'] += $qty;
            if (!$is_digital) {
                $groups[$seller_id]['shippable_items_count'] += $qty;
                $groups[$seller_id]['weight_kg'] += ($weight * $qty);
                $groups[$seller_id]['weight_grams'] += (int) round(($weight * $qty) * 1000);
                $item_key = (string) ($item['cart_key'] ?? '');
                if ($item_key !== '') {
                    $groups[$seller_id]['item_keys'][] = $item_key;
                }
            }
        }

        $normalized_groups = [];
        foreach ($order as $seller_id) {
            if (!isset($groups[$seller_id])) {
                continue;
            }
            if (empty($groups[$seller_id]['item_keys']) || (int) $groups[$seller_id]['weight_grams'] < 1) {
                continue;
            }
            $groups[$seller_id]['subtotal'] = (float) $groups[$seller_id]['subtotal'];
            $groups[$seller_id]['weight_kg'] = (float) $groups[$seller_id]['weight_kg'];
            $normalized_groups[] = $groups[$seller_id];
        }

        return [
            'schema_version' => 1,
            'generated_at' => current_time('mysql'),
            'source' => [
                'core' => 'vd-store',
                'addon' => 'vd-marketplace',
            ],
            'groups' => array_values($normalized_groups),
            'group_count' => count($normalized_groups),
            'cart_hash' => md5(wp_json_encode(array_values((array) $raw_items))),
        ];
    }

    private function format_seller_groups($snapshot)
    {
        $groups = isset($snapshot['groups']) && is_array($snapshot['groups']) ? $snapshot['groups'] : [];
        $formatted = [];

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $seller_id = isset($group['seller_id']) ? (int) $group['seller_id'] : 0;
            $group['seller_name'] = $this->seller_name($seller_id);
            $group['seller_url'] = $seller_id > 0 ? Settings::store_profile_url($seller_id) : '';
            $formatted[] = $group;
        }

        return array_values($formatted);
    }
}

