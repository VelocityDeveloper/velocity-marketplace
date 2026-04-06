<?php

namespace VelocityMarketplace\Modules\Product;

class ProductMeta
{
    public static function canonical_map()
    {
        return [
            'product_type' => \WpStore\Domain\Product\ProductMeta::meta_key('product_type'),
            'price' => \WpStore\Domain\Product\ProductMeta::meta_key('price'),
            'sale_price' => \WpStore\Domain\Product\ProductMeta::meta_key('sale_price'),
            'sale_until' => \WpStore\Domain\Product\ProductMeta::meta_key('sale_until'),
            'digital_file' => \WpStore\Domain\Product\ProductMeta::meta_key('digital_file'),
            'sku' => \WpStore\Domain\Product\ProductMeta::meta_key('sku'),
            'stock' => \WpStore\Domain\Product\ProductMeta::meta_key('stock'),
            'min_order' => \WpStore\Domain\Product\ProductMeta::meta_key('min_order'),
            'weight' => \WpStore\Domain\Product\ProductMeta::meta_key('weight'),
            'label' => \WpStore\Domain\Product\ProductMeta::meta_key('label'),
            'gallery_ids' => \WpStore\Domain\Product\ProductMeta::meta_key('gallery_ids'),
            'variant_name' => \WpStore\Domain\Product\ProductMeta::meta_key('variant_name'),
            'variant_options' => \WpStore\Domain\Product\ProductMeta::meta_key('variant_options'),
            'price_adjustment_name' => \WpStore\Domain\Product\ProductMeta::meta_key('price_adjustment_name'),
            'price_adjustment_options' => \WpStore\Domain\Product\ProductMeta::meta_key('price_adjustment_options'),
        ];
    }

    public static function canonical_key($logical_key)
    {
        $map = self::canonical_map();
        return isset($map[$logical_key]) ? (string) $map[$logical_key] : '';
    }

    public static function keys($logical_key)
    {
        $canonical = self::canonical_key($logical_key);
        $keys = [];

        if ($canonical !== '') {
            $keys[] = $canonical;
        }

        return $keys;
    }

    public static function get_raw($post_id, $logical_key, $default = '')
    {
        foreach (self::keys($logical_key) as $meta_key) {
            $value = get_post_meta((int) $post_id, $meta_key, true);
            if ($value !== '' && $value !== null && $value !== []) {
                return $value;
            }
        }

        return $default;
    }

    public static function get_text($post_id, $logical_key, $default = '')
    {
        $value = self::get_raw($post_id, $logical_key, $default);
        if ($logical_key === 'label') {
            return self::normalize_label((string) $value);
        }
        return is_scalar($value) ? (string) $value : (string) $default;
    }

    public static function get_number($post_id, $logical_key, $default = 0)
    {
        $value = self::get_raw($post_id, $logical_key, $default);
        if ($value === '' || !is_numeric($value)) {
            return $default;
        }

        return (float) $value;
    }

    public static function get_attachment_ids($post_id, $logical_key)
    {
        if ($logical_key === 'gallery_ids') {
            return \WpStore\Domain\Product\ProductMeta::gallery_ids((int) $post_id);
        }

        $value = self::get_raw($post_id, $logical_key, []);
        if (is_array($value)) {
            $ids = array_values(array_filter(array_map('intval', $value)));
            if (!empty($ids)) {
                return $ids;
            }
        }

        if (is_string($value) && $value !== '') {
            return array_values(array_filter(array_map('intval', array_map('trim', explode(',', $value)))));
        }

        return [];
    }

    public static function get_variant_options($post_id)
    {
        return \WpStore\Domain\Product\ProductMeta::get_list((int) $post_id, 'variant_options');
    }

    public static function get_price_adjustment_options($post_id)
    {
        $core_value = \WpStore\Domain\Product\ProductMeta::get_list((int) $post_id, 'price_adjustment_options');
        $items = [];
        foreach ($core_value as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = isset($row['label']) ? trim((string) $row['label']) : '';
            if ($label === '') {
                continue;
            }

            $items[] = [
                'label' => $label,
                'amount' => isset($row['price']) && is_numeric($row['price']) ? (float) $row['price'] : (isset($row['amount']) && is_numeric($row['amount']) ? (float) $row['amount'] : 0.0),
            ];
        }

        return $items;
    }

    public static function update_logical($post_id, $logical_key, $value)
    {
        $canonical_key = self::canonical_key($logical_key);
        if ($canonical_key === '') {
            return;
        }

        if ($value === '' || $value === null || $value === []) {
            delete_post_meta((int) $post_id, $canonical_key);
            return;
        }

        if ($logical_key === 'label') {
            $value = self::canonical_label((string) $value);
        }

        update_post_meta((int) $post_id, $canonical_key, $value);
    }

    public static function normalize_label($value)
    {
        $canonical = \WpStore\Domain\Product\ProductMeta::canonical_label($value);
        if ($canonical !== '') {
            $map = [
                'label-best' => 'best',
                'label-limited' => 'limited',
                'label-new' => 'new',
            ];

            return isset($map[$canonical]) ? $map[$canonical] : sanitize_key((string) $value);
        }

        $value = sanitize_key((string) $value);
        $map = [
            'label-best' => 'best',
            'label-limited' => 'limited',
            'label-new' => 'new',
        ];

        return isset($map[$value]) ? $map[$value] : $value;
    }

    public static function canonical_label($value)
    {
        return (string) \WpStore\Domain\Product\ProductMeta::canonical_label($value);
    }
}
