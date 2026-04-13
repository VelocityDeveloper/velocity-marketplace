<?php

namespace VelocityMarketplace\Modules\Product;

use VelocityMarketplace\Modules\Product\ProductData;
use VelocityMarketplace\Support\Settings;
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
        $product_query = new ProductQuery();
        $page = max(1, (int) $request->get_param('page'));
        $per_page = (int) $request->get_param('per_page');
        if ($per_page <= 0) {
            $per_page = 12;
        }
        $per_page = min(36, $per_page);

        $filters = $product_query->normalize_filters($request->get_params());
        $sort = (string) ($filters['sort'] ?? 'latest');
        $args = $product_query->build_query_args($filters, [
            'paged' => $page,
            'posts_per_page' => $per_page,
        ]);

        $query = new \WP_Query($args);
        $items = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $item = ProductData::map_post(get_the_ID());
                if ($item) {
                    $item['card_html'] = $this->product_card_html($item);
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
            'source' => [
                'core' => 'vd-store',
                'addon' => 'vd-marketplace',
                'type' => 'marketplace_superset',
            ],
            'items' => $items,
            'page' => $page,
            'pages' => (int) $query->max_num_pages,
            'total' => (int) $query->found_posts,
            'filters' => $filters,
            'sort_options' => $product_query->sort_options(),
            'marketplace' => [
                'supports_store_type' => true,
                'supports_store_location' => true,
                'supports_sold_sort' => true,
                'supports_rating_sort' => true,
                'supports_popular_sort' => true,
            ],
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
        $item['source'] = [
            'core' => 'vd-store',
            'addon' => 'vd-marketplace',
            'type' => 'marketplace_superset',
        ];
        $item['marketplace'] = [
            'seller_id' => (int) ($item['author_id'] ?? 0),
            'seller_city' => (string) ($item['seller_city'] ?? ''),
            'seller_last_active_at' => (string) ($item['seller_last_active_at'] ?? ''),
            'seller_last_active_text' => (string) ($item['seller_last_active_text'] ?? ''),
            'review_count' => (int) ($item['review_count'] ?? 0),
            'rating_average' => (float) ($item['rating_average'] ?? 0),
            'sold_count' => (int) ($item['sold_count'] ?? 0),
            'is_premium' => !empty($item['is_premium']),
        ];
        return new WP_REST_Response($item, 200);
    }

    private function product_card_html(array $item)
    {
        if (!class_exists('\WpStore\Frontend\Template')) {
            return '';
        }

        $product_id = isset($item['id']) ? (int) $item['id'] : 0;
        if ($product_id <= 0) {
            return '';
        }

        $extra_html = '';
        if (!empty($item['seller_city'])) {
            $extra_html .= '<div class="small text-muted mb-1">' . esc_html((string) $item['seller_city']) . '</div>';
        }
        if (!empty($item['sold_count'])) {
            $extra_html .= '<div class="small text-muted mb-1">' . esc_html(sprintf(__('%d terjual', 'velocity-marketplace'), (int) $item['sold_count'])) . '</div>';
        }
        if (!empty($item['rating_html'])) {
            $extra_html .= '<div class="mb-1">' . $item['rating_html'] . '</div>';
        } else {
            $extra_html .= '<div class="small text-muted mb-1">' . esc_html__('Belum ada ulasan', 'velocity-marketplace') . '</div>';
        }

        $actions_html = '<div>'
            . '<button type="button" class="btn btn-sm btn-dark flex-grow-1" data-vmp-catalog-add-to-cart="1" data-product-id="' . esc_attr((string) $product_id) . '">' . esc_html__('Tambah Keranjang', 'velocity-marketplace') . '</button>'
            . '</div>';

        return \WpStore\Frontend\Template::render('components/product-card', [
            'item' => [
                'id' => $product_id,
                'title' => (string) ($item['title'] ?? ''),
                'link' => (string) ($item['link'] ?? get_permalink($product_id)),
                'image' => (string) ($item['image'] ?? ''),
                'price' => $item['price'] ?? null,
                'stock' => $item['stock'] ?? null,
            ],
            'currency' => Settings::currency_symbol(),
            'extra_html' => $extra_html,
            'actions_html' => $actions_html,
            'card_class' => 'vmp-product-card',
        ]);
    }

    private function wishlist_icon_svg($active = false)
    {
        if ($active) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-heart-fill" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M8 1.314C12.438-3.248 23.534 4.735 8 15C-7.534 4.736 3.562-3.248 8 1.314"/></svg>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-heart" viewBox="0 0 16 16" aria-hidden="true"><path d="m8 2.748-.717-.737C5.6.281 2.514.878 1.4 3.053c-.523 1.023-.641 2.5.314 4.385.92 1.815 2.834 3.989 6.286 6.357 3.452-2.368 5.365-4.542 6.286-6.357.955-1.886.838-3.362.314-4.385C13.486.878 10.4.28 8.717 2.01zM8 15C-7.333 4.868 3.279-3.04 7.824 1.143c.06.055.119.112.176.171a3 3 0 0 1 .176-.17C12.72-3.042 23.333 4.867 8 15"/></svg>';
    }
}


