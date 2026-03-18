<?php

namespace VelocityMarketplace\Frontend;

use VelocityMarketplace\Support\ProductData;
use VelocityMarketplace\Support\Settings;

class Shortcode
{
    public function register()
    {
        add_shortcode('velocity_marketplace_catalog', [$this, 'render_catalog']);
        add_shortcode('velocity_marketplace_products', [$this, 'render_products']);
        add_shortcode('velocity_marketplace_product_card', [$this, 'render_product_card']);
        add_shortcode('velocity_marketplace_thumbnail', [$this, 'render_thumbnail']);
        add_shortcode('velocity_marketplace_price', [$this, 'render_price']);
        add_shortcode('velocity_marketplace_add_to_cart', [$this, 'render_add_to_cart']);
        add_shortcode('velocity_marketplace_add_to_wishlist', [$this, 'render_add_to_wishlist']);
        add_shortcode('velocity_marketplace_cart', [$this, 'render_cart']);
        add_shortcode('velocity_marketplace_checkout', [$this, 'render_checkout']);
        add_shortcode('velocity_marketplace_profile', [$this, 'render_profile']);
        add_shortcode('velocity_marketplace_tracking', [$this, 'render_tracking']);

        add_shortcode('vm_catalog', [$this, 'render_catalog']);
        add_shortcode('vm_products', [$this, 'render_products']);
        add_shortcode('vm_product_card', [$this, 'render_product_card']);
        add_shortcode('vm_thumbnail', [$this, 'render_thumbnail']);
        add_shortcode('vm_price', [$this, 'render_price']);
        add_shortcode('vm_add_to_cart', [$this, 'render_add_to_cart']);
        add_shortcode('vm_add_to_wishlist', [$this, 'render_add_to_wishlist']);
        add_shortcode('vm_cart', [$this, 'render_cart']);
        add_shortcode('vm_checkout', [$this, 'render_checkout']);
        add_shortcode('vm_profile', [$this, 'render_profile']);
        add_shortcode('vm_tracking', [$this, 'render_tracking']);

        add_shortcode('store_cart', [$this, 'render_cart']);
        add_shortcode('store_checkout', [$this, 'render_checkout']);
    }

    public function render_catalog($atts = [])
    {
        $atts = shortcode_atts([
            'per_page' => 12,
        ], $atts);

        return Template::render('catalog', [
            'per_page' => (int) $atts['per_page'],
        ]);
    }

    public function render_products($atts = [])
    {
        $atts = shortcode_atts([
            'per_page' => 12,
            'columns' => 4,
            'cat' => 0,
            'author' => 0,
            'sort' => 'latest',
            'search' => '',
        ], $atts);

        $query = $this->query_products($atts);
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

        if (($atts['sort'] ?? 'latest') === 'latest') {
            usort($items, static function ($left, $right) {
                $left_premium = !empty($left['is_premium']) ? 1 : 0;
                $right_premium = !empty($right['is_premium']) ? 1 : 0;

                if ($left_premium !== $right_premium) {
                    return $right_premium <=> $left_premium;
                }

                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            });
        }

        return $this->render_product_grid_markup(
            $items,
            max(1, min(6, (int) $atts['columns'])),
            'Produk belum tersedia.'
        );
    }

    public function render_product_card($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        $item = ProductData::map_post($product_id);
        if (!$item) {
            return '';
        }

        return $this->render_product_card_markup($item);
    }

    public function render_thumbnail($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'size' => 'large',
            'class' => '',
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        $item = ProductData::map_post($product_id);
        if (!$item) {
            return '';
        }

        $image = get_the_post_thumbnail_url($product_id, sanitize_key((string) $atts['size']));
        if (!$image && !empty($item['gallery_ids'])) {
            $image = wp_get_attachment_image_url((int) $item['gallery_ids'][0], sanitize_key((string) $atts['size']));
        }

        return $this->render_thumbnail_markup(
            $item,
            $image,
            sanitize_text_field((string) $atts['class'])
        );
    }

    public function render_price($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'class' => '',
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        $item = ProductData::map_post($product_id);
        if (!$item) {
            return '';
        }

        return $this->render_price_markup(
            $item,
            sanitize_text_field((string) $atts['class']),
            Settings::currency_symbol()
        );
    }

    public function render_add_to_cart($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'text' => 'Tambah Keranjang',
            'class' => 'btn btn-sm btn-dark',
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        $item = ProductData::map_post($product_id);
        if (!$item) {
            return '';
        }

        $basic_default = !empty($item['basic_options'][0]) ? (string) $item['basic_options'][0] : '';
        $advanced_default = !empty($item['advanced_options'][0]['label']) ? (string) $item['advanced_options'][0]['label'] : '';

        return $this->render_add_to_cart_markup(
            $product_id,
            (string) $atts['text'],
            (string) $atts['class'],
            $basic_default,
            $advanced_default
        );
    }

    public function render_add_to_wishlist($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'text' => 'Wishlist',
            'class' => 'btn btn-sm btn-outline-secondary',
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        $active = false;
        if (is_user_logged_in()) {
            $wishlist_ids = get_user_meta(get_current_user_id(), 'vmp_wishlist', true);
            if (is_array($wishlist_ids)) {
                $active = in_array($product_id, array_map('intval', $wishlist_ids), true);
            }
        }

        return $this->render_add_to_wishlist_markup(
            $product_id,
            (string) $atts['text'],
            (string) $atts['class'],
            $active
        );
    }

    public function render_cart($atts = [])
    {
        return Template::render('cart', []);
    }

    public function render_checkout($atts = [])
    {
        return Template::render('checkout', []);
    }

    public function render_profile($atts = [])
    {
        return Template::render('profile', []);
    }

    public function render_tracking($atts = [])
    {
        return Template::render('tracking', []);
    }

    private function resolve_product_id($given_id = 0)
    {
        $product_id = (int) $given_id;
        if ($product_id <= 0) {
            $loop_id = get_the_ID();
            if ($loop_id && get_post_type($loop_id) === 'vmp_product') {
                $product_id = (int) $loop_id;
            }
        }

        if ($product_id > 0 && get_post_type($product_id) !== 'vmp_product') {
            return 0;
        }

        return $product_id > 0 ? $product_id : 0;
    }

    private function query_products($atts = [])
    {
        $per_page = max(1, min(48, (int) ($atts['per_page'] ?? 12)));
        $cat = (int) ($atts['cat'] ?? 0);
        $author = (int) ($atts['author'] ?? 0);
        $search = sanitize_text_field((string) ($atts['search'] ?? ''));
        $sort = sanitize_key((string) ($atts['sort'] ?? 'latest'));

        $args = [
            'post_type' => 'vmp_product',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            's' => $search,
            'orderby' => 'date',
            'order' => 'DESC',
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
        }

        return new \WP_Query($args);
    }

    private function render_product_grid_markup($items, $columns = 4, $empty_message = '')
    {
        $items = is_array($items) ? $items : [];
        $columns = max(1, min(6, (int) $columns));
        $empty_message = $empty_message !== '' ? (string) $empty_message : 'Produk belum tersedia.';

        $col_map = [
            1 => 'col-12',
            2 => 'col-6',
            3 => 'col-12 col-md-6 col-lg-4',
            4 => 'col-6 col-md-4 col-lg-3',
            5 => 'col-6 col-md-4 col-lg',
            6 => 'col-6 col-md-4 col-lg-2',
        ];
        $col_class = isset($col_map[$columns]) ? $col_map[$columns] : $col_map[4];

        if (empty($items)) {
            return '<div class="py-4 text-center border rounded bg-light"><div class="h6 mb-1">Produk belum tersedia</div><div class="text-muted">' . esc_html($empty_message) . '</div></div>';
        }

        $html = '<div class="row g-3 vmp-builder-grid">';
        foreach ($items as $item) {
            $html .= '<div class="' . esc_attr($col_class) . '">';
            $html .= $this->render_product_card_markup($item);
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    private function render_product_card_markup($item)
    {
        $item = is_array($item) ? $item : [];
        $product_id = isset($item['id']) ? (int) $item['id'] : 0;
        if ($product_id <= 0) {
            return '';
        }

        $wishlist_active = false;
        if (is_user_logged_in()) {
            $wishlist_ids = get_user_meta(get_current_user_id(), 'vmp_wishlist', true);
            if (is_array($wishlist_ids)) {
                $wishlist_active = in_array($product_id, array_map('intval', $wishlist_ids), true);
            }
        }

        $basic_default = !empty($item['basic_options'][0]) ? (string) $item['basic_options'][0] : '';
        $advanced_default = !empty($item['advanced_options'][0]['label']) ? (string) $item['advanced_options'][0]['label'] : '';

        $stock_text = 'Stok tidak dibatasi';
        if (isset($item['stock']) && $item['stock'] !== '' && $item['stock'] !== null) {
            $stock_text = (float) $item['stock'] > 0 ? 'Stok: ' . (int) $item['stock'] : 'Stok habis';
        }

        $html = '<div class="card h-100 shadow-sm border-0 vmp-product-card">';
        $html .= $this->render_thumbnail_markup($item, isset($item['image']) ? (string) $item['image'] : '', '');
        $html .= '<div class="card-body d-flex flex-column">';
        $html .= '<h3 class="card-title h6 mb-1">' . esc_html((string) ($item['title'] ?? '')) . '</h3>';
        if (!empty($item['label'])) {
            $html .= '<div class="small text-muted mb-2">' . esc_html((string) $item['label']) . '</div>';
        }
        $html .= $this->render_price_markup($item, '', Settings::currency_symbol());
        $html .= '<div class="small text-muted mb-3">' . esc_html($stock_text) . '</div>';
        $html .= '<div class="mt-auto d-flex gap-2">';
        $html .= $this->render_add_to_cart_markup($product_id, 'Tambah Keranjang', 'btn btn-sm btn-dark flex-grow-1', $basic_default, $advanced_default);
        $html .= $this->render_add_to_wishlist_markup($product_id, 'Wishlist', 'btn btn-sm btn-outline-secondary', $wishlist_active);
        $html .= '</div></div></div>';

        return $html;
    }

    private function render_thumbnail_markup($item, $image = '', $class_name = '')
    {
        $item = is_array($item) ? $item : [];
        $link = isset($item['link']) ? (string) $item['link'] : '';
        $title = isset($item['title']) ? (string) $item['title'] : '';
        $class_name = trim((string) $class_name);

        $html = '<a href="' . esc_url($link) . '" class="vmp-thumb-wrap' . ($class_name !== '' ? ' ' . esc_attr($class_name) : '') . '">';
        if ($image !== '') {
            $html .= '<img src="' . esc_url((string) $image) . '" class="card-img-top vmp-thumb" alt="' . esc_attr($title) . '">';
        } else {
            $html .= '<div class="vmp-thumb vmp-thumb--empty d-flex align-items-center justify-content-center text-muted">No Image</div>';
        }
        $html .= '</a>';

        return $html;
    }

    private function render_price_markup($item, $price_html_class = '', $currency_symbol = 'Rp')
    {
        $item = is_array($item) ? $item : [];
        $price_html_class = trim((string) $price_html_class);
        $price = isset($item['price']) ? (float) $item['price'] : 0;

        return '<div class="vmp-price-wrap mb-1' . ($price_html_class !== '' ? ' ' . esc_attr($price_html_class) : '') . '"><div class="fw-semibold text-danger">' . esc_html((string) $currency_symbol . ' ' . number_format($price, 0, ',', '.')) . '</div></div>';
    }

    private function render_add_to_cart_markup($product_id, $text = 'Tambah Keranjang', $class_name = 'btn btn-sm btn-dark', $basic_default = '', $advanced_default = '')
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return '';
        }

        return '<button type="button" class="' . esc_attr(trim((string) $class_name)) . ' vmp-action-add-to-cart" data-product-id="' . esc_attr((string) $product_id) . '" data-basic="' . esc_attr((string) $basic_default) . '" data-advanced="' . esc_attr((string) $advanced_default) . '" data-default-label="' . esc_attr((string) $text) . '">' . esc_html((string) $text) . '</button>';
    }

    private function render_add_to_wishlist_markup($product_id, $text = 'Wishlist', $class_name = 'btn btn-sm btn-outline-secondary', $active = false)
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return '';
        }

        $active = !empty($active);

        return '<button type="button" class="' . esc_attr(trim((string) $class_name)) . ' vmp-action-toggle-wishlist' . ($active ? ' btn-danger' : '') . '" data-product-id="' . esc_attr((string) $product_id) . '" data-default-label="' . esc_attr((string) $text) . '" aria-pressed="' . ($active ? 'true' : 'false') . '" title="Wishlist">' . esc_html((string) $text) . '</button>';
    }
}
