<?php

namespace VelocityMarketplace\Modules\Product;

class ProductMeta
{
    public static function canonical_map()
    {
        return [
            'product_type' => '_store_product_type',
            'price' => '_store_price',
            'sale_price' => '_store_sale_price',
            'sale_until' => '_store_flashsale_until',
            'digital_file' => '_store_digital_file',
            'sku' => '_store_sku',
            'stock' => '_store_stock',
            'min_order' => '_store_min_order',
            'weight' => '_store_weight_kg',
            'label' => '_store_label',
            'gallery_ids' => '_store_gallery_ids',
            'variant_name' => '_store_option_name',
            'variant_options' => '_store_options',
            'price_adjustment_name' => '_store_option2_name',
            'price_adjustment_options' => '_store_advanced_options',
        ];
    }

    public static function legacy_map()
    {
        return [
            'price' => ['price'],
            'sale_price' => ['sale_price'],
            'sale_until' => ['sale_until'],
            'sku' => ['sku'],
            'stock' => ['stock'],
            'min_order' => ['min_order'],
            'weight' => ['weight'],
            'label' => ['label'],
            'gallery_ids' => ['gallery_ids'],
            'variant_name' => ['variant_name'],
            'variant_options' => ['variant_options'],
            'price_adjustment_name' => ['price_adjustment_name'],
            'price_adjustment_options' => ['price_adjustment_options'],
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
        $legacy = self::legacy_map();
        $keys = [];

        if ($canonical !== '') {
            $keys[] = $canonical;
        }

        foreach ((array) ($legacy[$logical_key] ?? []) as $key) {
            $key = (string) $key;
            if ($key !== '' && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
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
        $value = self::get_raw($post_id, 'variant_options', []);
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', array_map('strval', $value)), static function ($item) {
                return $item !== '';
            }));
        }

        if (is_string($value) && $value !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $value)), static function ($item) {
                return $item !== '';
            }));
        }

        return [];
    }

    public static function get_price_adjustment_options($post_id)
    {
        $value = self::get_raw($post_id, 'price_adjustment_options', []);

        if (is_array($value)) {
            $items = [];
            foreach ($value as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $label = isset($row['label']) ? trim((string) $row['label']) : '';
                if ($label === '') {
                    continue;
                }

                $items[] = [
                    'label' => $label,
                    'amount' => isset($row['price']) && is_numeric($row['price']) ? (float) $row['price'] : 0.0,
                ];
            }

            if (!empty($items)) {
                return $items;
            }
        }

        if (is_string($value) && $value !== '') {
            $rows = preg_split('/\r\n|\r|\n/', $value);
            $items = [];
            foreach ((array) $rows as $row) {
                $line = trim((string) $row);
                if ($line === '') {
                    continue;
                }

                $parts = strpos($line, '=') !== false ? array_map('trim', explode('=', $line, 2)) : [$line, 0];
                $label = isset($parts[0]) ? (string) $parts[0] : '';
                if ($label === '') {
                    continue;
                }

                $items[] = [
                    'label' => $label,
                    'amount' => isset($parts[1]) && is_numeric($parts[1]) ? (float) $parts[1] : 0.0,
                ];
            }

            return $items;
        }

        return [];
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
        $value = self::normalize_label($value);
        $map = [
            'best' => 'label-best',
            'limited' => 'label-limited',
            'new' => 'label-new',
        ];

        return isset($map[$value]) ? $map[$value] : '';
    }
}
