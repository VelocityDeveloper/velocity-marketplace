<?php

namespace VelocityMarketplace\Modules\Product;

class RelatedProducts
{
    public static function items($product_id, $limit = 4)
    {
        $product_id = (int) $product_id;
        $limit = max(1, min(12, (int) $limit));

        if ($product_id <= 0 || get_post_type($product_id) !== 'vmp_product') {
            return [];
        }

        $term_ids = wp_get_post_terms($product_id, 'vmp_product_cat', ['fields' => 'ids']);
        if (is_wp_error($term_ids) || empty($term_ids)) {
            return [];
        }

        $query = new \WP_Query([
            'post_type' => 'vmp_product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'post__not_in' => [$product_id],
            'ignore_sticky_posts' => true,
            'tax_query' => [
                [
                    'taxonomy' => 'vmp_product_cat',
                    'field' => 'term_id',
                    'terms' => array_map('intval', (array) $term_ids),
                ],
            ],
            'meta_key' => 'vmp_sold_count',
            'orderby' => [
                'meta_value_num' => 'DESC',
                'date' => 'DESC',
            ],
        ]);

        if (!$query->have_posts()) {
            return [];
        }

        $items = [];
        while ($query->have_posts()) {
            $query->the_post();
            $item = ProductData::map_post(get_the_ID());
            if ($item) {
                $items[] = $item;
            }
        }
        wp_reset_postdata();

        return $items;
    }
}
