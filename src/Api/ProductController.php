<?php

namespace VelocityMarketplace\Api;

use VelocityMarketplace\Support\ProductData;
use WP_REST_Request;
use WP_REST_Response;

class ProductController
{
    public function register_routes()
    {
        register_rest_route('velocity-marketplace/v1', '/products', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_products'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('velocity-marketplace/v1', '/products/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_product'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function get_products(WP_REST_Request $request)
    {
        $page = max(1, (int) $request->get_param('page'));
        $per_page = (int) $request->get_param('per_page');
        if ($per_page <= 0) {
            $per_page = 12;
        }
        $per_page = min(36, $per_page);

        $search = sanitize_text_field((string) $request->get_param('search'));
        $sort = sanitize_key((string) $request->get_param('sort'));
        $cat = (int) $request->get_param('cat');
        $author = (int) $request->get_param('author');
        $min_price = $request->get_param('min_price');
        $max_price = $request->get_param('max_price');

        $args = [
            'post_type' => 'vmp_product',
            'post_status' => 'publish',
            'paged' => $page,
            'posts_per_page' => $per_page,
            's' => $search,
        ];

        if ($cat > 0) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'vmp_product_cat',
                    'field' => 'term_id',
                    'terms' => [$cat],
                ],
            ];
        }
        if ($author > 0) {
            $args['author'] = $author;
        }

        $meta_query = [];
        if ($min_price !== null && $min_price !== '') {
            $meta_query[] = [
                'key' => 'price',
                'value' => (float) $min_price,
                'type' => 'NUMERIC',
                'compare' => '>=',
            ];
        }
        if ($max_price !== null && $max_price !== '') {
            $meta_query[] = [
                'key' => 'price',
                'value' => (float) $max_price,
                'type' => 'NUMERIC',
                'compare' => '<=',
            ];
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_query);
        }

        if ($sort === 'price_asc') {
            $args['meta_key'] = 'price';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'ASC';
        } elseif ($sort === 'price_desc') {
            $args['meta_key'] = 'price';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
        } elseif ($sort === 'popular') {
            $args['meta_key'] = 'vmp_hits';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
        } else {
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
        }

        $query = new \WP_Query($args);
        $items = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $item = ProductData::map_post(get_the_ID());
                if ($item) {
                    $items[] = $item;
                }
            }
            wp_reset_postdata();
        }

        if ($sort === '' || $sort === 'latest') {
            usort($items, static function ($left, $right) {
                $left_premium = !empty($left['is_premium']) ? 1 : 0;
                $right_premium = !empty($right['is_premium']) ? 1 : 0;

                if ($left_premium !== $right_premium) {
                    return $right_premium <=> $left_premium;
                }

                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            });
        }

        return new WP_REST_Response([
            'items' => $items,
            'page' => $page,
            'pages' => (int) $query->max_num_pages,
            'total' => (int) $query->found_posts,
        ], 200);
    }

    public function get_product(WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');
        $item = ProductData::map_post($id);
        if (!$item) {
            return new WP_REST_Response([
                'message' => 'Produk tidak ditemukan.',
            ], 404);
        }

        $item['content'] = apply_filters('the_content', get_post_field('post_content', $id));
        return new WP_REST_Response($item, 200);
    }
}
