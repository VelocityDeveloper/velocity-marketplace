<?php

namespace VelocityMarketplace\Support;

class ProductData
{
    public static function map_post($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || get_post_type($post_id) !== 'vmp_product') {
            return null;
        }

        $price = self::resolve_price($post_id);
        $sale_price = self::resolve_sale_price($post_id);
        $basic_name = self::meta_text($post_id, 'basic_name', 'Pilihan Warna');
        $basic_options = self::basic_options($post_id);
        $advanced_name = self::meta_text($post_id, 'advanced_name', 'Pilihan Ukuran');
        $advanced_options = self::advanced_options($post_id);
        $gallery_ids = self::gallery_ids($post_id);
        $image = get_the_post_thumbnail_url($post_id, 'large');
        if (!$image && !empty($gallery_ids)) {
            $image = wp_get_attachment_image_url($gallery_ids[0], 'large');
        }

        return [
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'link' => get_permalink($post_id),
            'image' => $image,
            'gallery_ids' => $gallery_ids,
            'gallery' => self::gallery_urls($gallery_ids),
            'excerpt' => get_the_excerpt($post_id),
            'author_id' => (int) get_post_field('post_author', $post_id),
            'price' => $price,
            'sale_price' => $sale_price,
            'sku' => self::meta_text($post_id, 'sku'),
            'stock' => self::meta_number($post_id, 'stock', null),
            'min_order' => max(1, (int) self::meta_number($post_id, 'min_order', 1)),
            'weight' => self::meta_number($post_id, 'weight', 0),
            'label' => self::meta_text($post_id, 'label'),
            'is_premium' => (int) self::meta_number($post_id, 'is_premium', 0) === 1,
            'basic_name' => $basic_name,
            'basic_options' => $basic_options,
            'advanced_name' => $advanced_name,
            'advanced_options' => $advanced_options,
        ];
    }

    public static function resolve_price($post_id)
    {
        $regular = self::meta_number($post_id, 'price', 0);
        $sale = self::resolve_sale_price($post_id);

        if ($sale !== null && $sale > 0 && ($regular <= 0 || $sale < $regular)) {
            return $sale;
        }

        return (float) $regular;
    }

    public static function resolve_sale_price($post_id)
    {
        $sale = self::meta_number($post_id, 'sale_price', 0);
        if ($sale <= 0) {
            return null;
        }

        $sale_until = self::meta_text($post_id, 'sale_until');
        if ($sale_until !== '') {
            $until = strtotime($sale_until);
            if ($until && $until < current_time('timestamp')) {
                return null;
            }
        }

        return (float) $sale;
    }

    public static function advanced_options($post_id)
    {
        $rows = preg_split('/\r\n|\r|\n/', self::meta_text($post_id, 'advanced_options'));
        $result = [];

        foreach ((array) $rows as $row) {
            $line = trim((string) $row);
            if ($line === '') {
                continue;
            }
            if (strpos($line, '=') !== false) {
                $parts = array_map('trim', explode('=', $line, 2));
                $label = isset($parts[0]) ? $parts[0] : '';
                $price = isset($parts[1]) && is_numeric($parts[1]) ? (float) $parts[1] : 0;
            } else {
                $label = $line;
                $price = 0;
            }

            if ($label === '') {
                continue;
            }

            $result[] = [
                'label' => $label,
                'price' => (float) $price,
            ];
        }

        return $result;
    }

    public static function resolve_advanced_price($post_id, $selected_label)
    {
        $selected_label = trim((string) $selected_label);
        if ($selected_label === '') {
            return null;
        }

        $options = self::advanced_options($post_id);
        foreach ($options as $opt) {
            if ((string) $opt['label'] === $selected_label && (float) $opt['price'] > 0) {
                return (float) $opt['price'];
            }
        }

        return null;
    }

    public static function basic_options($post_id)
    {
        $raw = self::meta_text($post_id, 'basic_options');
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), function ($value) {
            return $value !== '';
        }));
    }

    public static function normalize_options($post_id, $options = [])
    {
        $options = is_array($options) ? $options : [];
        $basic_name = self::meta_text($post_id, 'basic_name', 'Pilihan Warna');
        $advanced_name = self::meta_text($post_id, 'advanced_name', 'Pilihan Ukuran');

        $basic = isset($options['basic']) ? sanitize_text_field((string) $options['basic']) : '';
        $advanced = isset($options['advanced']) ? sanitize_text_field((string) $options['advanced']) : '';

        if ($basic === '' && isset($options[$basic_name])) {
            $basic = sanitize_text_field((string) $options[$basic_name]);
        }
        if ($advanced === '' && isset($options[$advanced_name])) {
            $advanced = sanitize_text_field((string) $options[$advanced_name]);
        }

        $normalized = [
            'basic' => $basic,
            'advanced' => $advanced,
            $basic_name => $basic,
            $advanced_name => $advanced,
        ];

        return $normalized;
    }

    public static function meta_text($post_id, $key, $default = '')
    {
        $value = get_post_meta($post_id, $key, true);
        if ($value === '' || $value === null) {
            return (string) $default;
        }
        return (string) $value;
    }

    public static function meta_number($post_id, $key, $default = 0)
    {
        $value = get_post_meta($post_id, $key, true);
        if ($value === '' || !is_numeric($value)) {
            return $default;
        }
        return (float) $value;
    }

    public static function gallery_ids($post_id)
    {
        $value = get_post_meta($post_id, 'gallery_ids', true);
        if (is_string($value) && $value !== '') {
            $value = array_map('trim', explode(',', $value));
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $value)));
    }

    public static function gallery_urls($gallery_ids)
    {
        $items = [];
        foreach ((array) $gallery_ids as $attachment_id) {
            $url = wp_get_attachment_image_url((int) $attachment_id, 'large');
            if ($url) {
                $items[] = $url;
            }
        }

        return $items;
    }

}
