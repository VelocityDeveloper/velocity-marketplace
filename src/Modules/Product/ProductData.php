<?php

namespace VelocityMarketplace\Modules\Product;

use VelocityMarketplace\Modules\Review\ReviewRepository;
use VelocityMarketplace\Modules\Review\RatingRenderer;
use VelocityMarketplace\Support\Contract;

class ProductData
{
    public static function map_post($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || !Contract::is_product($post_id)) {
            return null;
        }

        $price = self::resolve_price($post_id);
        $sale_price = self::resolve_sale_price($post_id);
        $variant_name = ProductMeta::get_text($post_id, 'variant_name', 'Pilihan Varian');
        $variant_options = self::variant_options($post_id);
        $price_adjustment_name = ProductMeta::get_text($post_id, 'price_adjustment_name', 'Pilihan Harga');
        $price_adjustment_options = self::price_adjustment_options($post_id);
        $gallery_ids = self::gallery_ids($post_id);
        $image = self::image_url($post_id, 'large', $gallery_ids);
        $review_summary = (new ReviewRepository())->product_summary($post_id);
        $author_id = (int) get_post_field('post_author', $post_id);
        $seller_city = $author_id > 0 ? (string) get_user_meta($author_id, 'vmp_store_city', true) : '';
        $seller_last_active_at = $author_id > 0 ? (string) get_user_meta($author_id, 'vmp_last_active_at', true) : '';
        $seller_last_active_text = '';
        if ($seller_last_active_at !== '') {
            $seller_last_active_ts = strtotime($seller_last_active_at);
            if ($seller_last_active_ts) {
                $seller_last_active_text = sprintf(__('%s yang lalu', 'velocity-marketplace'), human_time_diff($seller_last_active_ts, current_time('timestamp')));
            }
        }
        $review_count = isset($review_summary['review_count']) ? (int) $review_summary['review_count'] : 0;
        $rating_average = isset($review_summary['rating_average']) ? (float) $review_summary['rating_average'] : 0.0;

        return [
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'link' => get_permalink($post_id),
            'image' => $image,
            'gallery_ids' => $gallery_ids,
            'gallery' => self::gallery_urls($gallery_ids),
            'excerpt' => get_the_excerpt($post_id),
            'author_id' => $author_id,
            'seller_city' => $seller_city,
            'seller_last_active_at' => $seller_last_active_at,
            'seller_last_active_text' => $seller_last_active_text,
            'price' => $price,
            'sale_price' => $sale_price,
            'sku' => ProductMeta::get_text($post_id, 'sku'),
            'stock' => self::meta_number($post_id, 'stock', null),
            'min_order' => max(1, (int) self::meta_number($post_id, 'min_order', 1)),
            'weight' => self::meta_number($post_id, 'weight', 0),
            'label' => ProductMeta::get_text($post_id, 'label'),
            'is_premium' => (int) self::meta_number($post_id, 'is_premium', 0) === 1,
            'variant_name' => $variant_name,
            'variant_options' => $variant_options,
            'price_adjustment_name' => $price_adjustment_name,
            'price_adjustment_options' => $price_adjustment_options,
            'review_count' => $review_count,
            'rating_average' => $rating_average,
            'rating_html' => $review_count > 0
                ? RatingRenderer::summary_html($rating_average, $review_count, [
                    'size' => 14,
                    'class' => 'small text-muted',
                    'value_class' => 'text-muted',
                    'count_class' => 'text-muted',
                ])
                : '',
            'sold_count' => max(0, (int) self::meta_number($post_id, 'vmp_sold_count', 0)),
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

    public static function price_adjustment_options($post_id)
    {
        return ProductMeta::get_price_adjustment_options($post_id);
    }

    public static function resolve_price_adjustment($post_id, $selected_label)
    {
        $selected_label = trim((string) $selected_label);
        if ($selected_label === '') {
            return 0.0;
        }

        $options = self::price_adjustment_options($post_id);
        foreach ($options as $opt) {
            if ((string) $opt['label'] === $selected_label) {
                return (float) ($opt['amount'] ?? 0);
            }
        }

        return 0.0;
    }

    public static function variant_options($post_id)
    {
        return ProductMeta::get_variant_options($post_id);
    }

    public static function normalize_options($post_id, $options = [])
    {
        $options = is_array($options) ? $options : [];
        $variant_name = self::meta_text($post_id, 'variant_name', 'Pilihan Varian');
        $price_adjustment_name = self::meta_text($post_id, 'price_adjustment_name', 'Pilihan Harga');

        $variant = isset($options['variant']) ? sanitize_text_field((string) $options['variant']) : '';
        $price_adjustment = isset($options['price_adjustment']) ? sanitize_text_field((string) $options['price_adjustment']) : '';

        if ($variant === '' && isset($options[$variant_name])) {
            $variant = sanitize_text_field((string) $options[$variant_name]);
        }
        if ($price_adjustment === '' && isset($options[$price_adjustment_name])) {
            $price_adjustment = sanitize_text_field((string) $options[$price_adjustment_name]);
        }

        $normalized = [
            'variant' => $variant,
            'price_adjustment' => $price_adjustment,
            $variant_name => $variant,
            $price_adjustment_name => $price_adjustment,
        ];

        return $normalized;
    }

    public static function meta_text($post_id, $key, $default = '')
    {
        return ProductMeta::get_text($post_id, $key, $default);
    }

    public static function meta_number($post_id, $key, $default = 0)
    {
        return ProductMeta::get_number($post_id, $key, $default);
    }

    public static function gallery_ids($post_id)
    {
        return ProductMeta::get_attachment_ids($post_id, 'gallery_ids');
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

    public static function image_url($post_id, $size = 'large', $gallery_ids = null)
    {
        $post_id = (int) $post_id;
        $size = sanitize_key((string) $size);
        if ($size === '') {
            $size = 'large';
        }

        $image = get_the_post_thumbnail_url($post_id, $size);
        if ($image) {
            return (string) $image;
        }

        if ($gallery_ids === null) {
            $gallery_ids = self::gallery_ids($post_id);
        }

        foreach ((array) $gallery_ids as $attachment_id) {
            $gallery_image = wp_get_attachment_image_url((int) $attachment_id, $size);
            if ($gallery_image) {
                return (string) $gallery_image;
            }
        }

        return self::no_image_url();
    }

    public static function no_image_url()
    {
        return VMP_URL . 'assets/img/no-image.webp';
    }

    public static function increment_sold_count($product_id, $qty = 1)
    {
        $product_id = (int) $product_id;
        $qty = (int) $qty;

        if ($product_id <= 0 || $qty <= 0 || !Contract::is_product($product_id)) {
            return;
        }

        $current = (int) get_post_meta($product_id, 'vmp_sold_count', true);
        update_post_meta($product_id, 'vmp_sold_count', max(0, $current + $qty));
    }

}

