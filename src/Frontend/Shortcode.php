<?php

namespace VelocityMarketplace\Frontend;

use VelocityMarketplace\Modules\Cart\CartRepository;
use VelocityMarketplace\Modules\Message\MessageRepository;
use VelocityMarketplace\Modules\Notification\NotificationRepository;
use VelocityMarketplace\Modules\Product\ProductData;
use VelocityMarketplace\Modules\Product\ProductQuery;
use VelocityMarketplace\Modules\Product\RecentlyViewed;
use VelocityMarketplace\Modules\Review\RatingRenderer;
use VelocityMarketplace\Support\Settings;

class Shortcode
{
    private $needs_cart_drawer = false;

    public function register()
    {
        add_shortcode('vmp_catalog', [$this, 'render_catalog']);
        add_shortcode('vmp_products', [$this, 'render_products']);
        add_shortcode('vmp_product_card', [$this, 'render_product_card']);
        add_shortcode('vmp_product_gallery', [$this, 'render_product_gallery']);
        add_shortcode('vmp_product_reviews', [$this, 'render_product_reviews']);
        add_shortcode('vmp_product_seller_card', [$this, 'render_product_seller_card']);
        add_shortcode('vmp_product_description', [$this, 'render_product_description']);
        add_shortcode('vmp_recently_viewed', [$this, 'render_recently_viewed']);
        add_shortcode('vmp_thumbnail', [$this, 'render_thumbnail']);
        add_shortcode('vmp_price', [$this, 'render_price']);
        add_shortcode('vmp_add_to_cart', [$this, 'render_add_to_cart']);
        add_shortcode('vmp_add_to_wishlist', [$this, 'render_add_to_wishlist']);
        add_shortcode('vmp_rating', [$this, 'render_rating']);
        add_shortcode('vmp_review_count', [$this, 'render_review_count']);
        add_shortcode('vmp_sold_count', [$this, 'render_sold_count']);
        add_shortcode('vmp_cart', [$this, 'render_cart']);
        add_shortcode('vmp_checkout', [$this, 'render_checkout']);
        add_shortcode('vmp_profile', [$this, 'render_profile']);
        add_shortcode('vmp_tracking', [$this, 'render_tracking']);
        add_shortcode('vmp_store_profile', [$this, 'render_store_profile']);
        add_shortcode('vmp_product_filter', [$this, 'render_product_filter']);
        add_shortcode('vmp_messages_icon', [$this, 'render_messages_icon']);
        add_shortcode('vmp_notifications_icon', [$this, 'render_notifications_icon']);
        add_shortcode('vmp_profile_icon', [$this, 'render_profile_icon']);
        add_shortcode('vmp_cart_page', [$this, 'render_cart_page']);

        add_action('wp_footer', [$this, 'render_cart_drawer_footer'], 30);
    }

    public function render_catalog($atts = [])
    {
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'per_page' => 12,
        ], $atts);

        return Template::render('catalog', [
            'per_page' => (int) $atts['per_page'],
        ]);
    }

    public function render_products($atts = [])
    {
        $this->ensure_frontend_assets();

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
            __('Produk belum tersedia.', 'velocity-marketplace')
        );
    }

    public function render_product_card($atts = [])
    {
        $this->ensure_frontend_assets();

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

    public function render_product_gallery($atts = [])
    {
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        return Template::render('product-gallery', [
            'product_id' => $product_id,
        ]);
    }

    public function render_product_reviews($atts = [])
    {
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'id' => 0,
            'limit' => 20,
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        return Template::render('product-reviews', [
            'product_id' => $product_id,
            'limit' => max(1, min(100, (int) $atts['limit'])),
        ]);
    }

    public function render_product_seller_card($atts = [])
    {
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        return Template::render('product-seller-card', [
            'product_id' => $product_id,
        ]);
    }

    public function render_product_description($atts = [])
    {
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        return Template::render('product-description', [
            'product_id' => $product_id,
        ]);
    }

    public function render_recently_viewed($atts = [])
    {
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'limit' => 4,
            'exclude_current' => 'true',
            'title' => __('Produk yang Baru Dilihat', 'velocity-marketplace'),
        ], $atts);

        $exclude_id = filter_var($atts['exclude_current'], FILTER_VALIDATE_BOOLEAN) ? $this->resolve_product_id(0) : 0;
        $items = RecentlyViewed::items($exclude_id, (int) $atts['limit']);
        if (empty($items)) {
            return '';
        }

        return Template::render('product-recently-viewed', [
            'title' => (string) $atts['title'],
            'items' => $items,
        ]);
    }

    public function render_thumbnail($atts = [])
    {
        $this->ensure_frontend_assets();

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

        $image = ProductData::image_url($product_id, sanitize_key((string) $atts['size']), $item['gallery_ids']);

        return $this->render_thumbnail_markup(
            $item,
            $image,
            sanitize_text_field((string) $atts['class'])
        );
    }

    public function render_price($atts = [])
    {
        $this->ensure_frontend_assets();

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
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'id' => 0,
            'text' => __('Tambah Keranjang', 'velocity-marketplace'),
            'class' => 'btn btn-sm btn-dark',
            'style' => 'popup',
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        $item = ProductData::map_post($product_id);
        if (!$item) {
            return '';
        }

        return $this->render_add_to_cart_markup(
            $item,
            (string) $atts['text'],
            (string) $atts['class'],
            (string) $atts['style']
        );
    }

    public function render_add_to_wishlist($atts = [])
    {
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'id' => 0,
            'text' => __('Wishlist', 'velocity-marketplace'),
            'class' => 'btn btn-sm btn-outline-secondary vmp-wishlist-button',
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

    public function render_rating($atts = [])
    {
        $atts = shortcode_atts([
            'type' => 'value',
            'id' => 0,
            'value' => 0,
            'count' => '',
            'size' => 16,
            'show_value' => 'true',
            'show_count' => 'true',
            'class' => '',
            'stars_class' => '',
            'value_class' => '',
            'count_class' => '',
            'count_text' => __('ulasan', 'velocity-marketplace'),
        ], $atts);

        $type = sanitize_key((string) $atts['type']);
        $id = (int) $atts['id'];
        $args = [
            'size' => max(10, (int) $atts['size']),
            'show_value' => filter_var($atts['show_value'], FILTER_VALIDATE_BOOLEAN),
            'show_count' => filter_var($atts['show_count'], FILTER_VALIDATE_BOOLEAN),
            'class' => sanitize_text_field((string) $atts['class']),
            'stars_class' => sanitize_text_field((string) $atts['stars_class']),
            'value_class' => sanitize_text_field((string) $atts['value_class']),
            'count_class' => sanitize_text_field((string) $atts['count_class']),
            'count_text' => sanitize_text_field((string) $atts['count_text']),
        ];

        if ($type === 'product' && $id > 0) {
            return RatingRenderer::product_summary_html($id, $args);
        }

        if ($type === 'seller' && $id > 0) {
            return RatingRenderer::seller_summary_html($id, $args);
        }

        $count = $atts['count'] === '' ? null : (int) $atts['count'];
        return RatingRenderer::summary_html((float) $atts['value'], $count, $args);
    }

    public function render_review_count($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'class' => '',
            'suffix' => __('ulasan', 'velocity-marketplace'),
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        $summary = (new \VelocityMarketplace\Modules\Review\ReviewRepository())->product_summary($product_id);
        $count = (int) ($summary['review_count'] ?? 0);
        $class = trim((string) $atts['class']);

        return sprintf(
            '<span class="%1$s">%2$s</span>',
            esc_attr($class),
            esc_html(sprintf(__('%1$d %2$s', 'velocity-marketplace'), $count, (string) $atts['suffix']))
        );
    }

    public function render_sold_count($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'class' => '',
            'suffix' => __('terjual', 'velocity-marketplace'),
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        $count = (int) get_post_meta($product_id, 'vmp_sold_count', true);
        $class = trim((string) $atts['class']);

        return sprintf(
            '<span class="%1$s">%2$s</span>',
            esc_attr($class),
            esc_html(sprintf(__('%1$d %2$s', 'velocity-marketplace'), max(0, $count), (string) $atts['suffix']))
        );
    }

    public function render_cart($atts = [])
    {
        $this->ensure_frontend_assets();
        $this->needs_cart_drawer = true;

        $count = (new CartRepository())->get_cart_data()['count'] ?? 0;
        $url = $this->cart_page_url();

        return $this->render_shortcut_icon_markup(
            'cart',
            $url,
            (int) $count,
            __('Keranjang', 'velocity-marketplace'),
            '',
            false,
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cart2" viewBox="0 0 16 16"> <path d="M0 2.5A.5.5 0 0 1 .5 2H2a.5.5 0 0 1 .485.379L2.89 4H14.5a.5.5 0 0 1 .485.621l-1.5 6A.5.5 0 0 1 13 11H4a.5.5 0 0 1-.485-.379L1.61 3H.5a.5.5 0 0 1-.5-.5M3.14 5l1.25 5h8.22l1.25-5zM5 13a1 1 0 1 0 0 2 1 1 0 0 0 0-2m-2 1a2 2 0 1 1 4 0 2 2 0 0 1-4 0m9-1a1 1 0 1 0 0 2 1 1 0 0 0 0-2m-2 1a2 2 0 1 1 4 0 2 2 0 0 1-4 0"/> </svg>',
            [
                'data-vmp-cart-trigger' => 'true',
                'data-vmp-cart-url' => $url,
                'aria-controls' => 'vmp-cart-drawer-panel',
            ],
            false
        );
    }

    public function render_cart_page($atts = [])
    {
        $this->ensure_frontend_assets();
        return Template::render('cart', []);
    }

    public function render_checkout($atts = [])
    {
        $this->ensure_frontend_assets();
        return Template::render('checkout', []);
    }

    public function render_profile($atts = [])
    {
        $this->ensure_frontend_assets();
        return Template::render('profile', []);
    }

    public function render_tracking($atts = [])
    {
        $this->ensure_frontend_assets();
        return Template::render('tracking', []);
    }

    public function render_store_profile($atts = [])
    {
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'seller' => 0,
        ], $atts);

        $seller_id = (int) $atts['seller'];
        if ($seller_id <= 0) {
            $seller_id = isset($_GET['seller']) ? (int) wp_unslash($_GET['seller']) : 0;
        }

        return Template::render('store-profile', [
            'seller_id' => $seller_id,
        ]);
    }

    public function render_product_filter($atts = [])
    {
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'action' => '',
            'class' => '',
        ], $atts);

        $action_url = trim((string) $atts['action']);
        if ($action_url === '') {
            if (is_post_type_archive('vmp_product')) {
                $action_url = get_post_type_archive_link('vmp_product');
            } else {
                $pages = get_option(VMP_PAGES_OPTION, []);
                if (is_array($pages) && !empty($pages['katalog'])) {
                    $page_url = get_permalink((int) $pages['katalog']);
                    $action_url = $page_url ? $page_url : site_url('/catalog/');
                } else {
                    $action_url = site_url('/catalog/');
                }
            }
        }

        $product_query = new ProductQuery();

        return Template::render('product-filter-form', [
            'filters' => $product_query->normalize_filters($_GET),
            'categories' => get_terms([
                'taxonomy' => 'vmp_product_cat',
                'hide_empty' => false,
            ]),
            'label_options' => $product_query->label_options(),
            'action_url' => $action_url,
            'form_class' => sanitize_text_field((string) $atts['class']),
        ]);
    }

    public function render_messages_icon($atts = [])
    {
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'class' => '',
            'show_zero' => 'false',
        ], $atts);

        $count = is_user_logged_in() ? (new MessageRepository())->unread_count() : 0;
        $url = add_query_arg(['tab' => 'messages'], Settings::profile_url());

        return $this->render_shortcut_icon_markup(
            'message',
            $url,
            $count,
            __('Pesan', 'velocity-marketplace'),
            (string) $atts['class'],
            filter_var($atts['show_zero'], FILTER_VALIDATE_BOOLEAN),
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chat" viewBox="0 0 16 16"> <path d="M2.678 11.894a1 1 0 0 1 .287.801 11 11 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8 8 0 0 0 8 14c3.996 0 7-2.807 7-6s-3.004-6-7-6-7 2.808-7 6c0 1.468.617 2.83 1.678 3.894m-.493 3.905a22 22 0 0 1-.713.129c-.2.032-.352-.176-.273-.362a10 10 0 0 0 .244-.637l.003-.01c.248-.72.45-1.548.524-2.319C.743 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7-3.582 7-8 7a9 9 0 0 1-2.347-.306c-.52.263-1.639.742-3.468 1.105"/> </svg>'
        );
    }

    public function render_notifications_icon($atts = [])
    {
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'class' => '',
            'show_zero' => 'false',
        ], $atts);

        $count = is_user_logged_in() ? (new NotificationRepository())->unread_count() : 0;
        $url = add_query_arg(['tab' => 'notifications'], Settings::profile_url());

        return $this->render_shortcut_icon_markup(
            'notification',
            $url,
            $count,
            __('Notifikasi', 'velocity-marketplace'),
            (string) $atts['class'],
            filter_var($atts['show_zero'], FILTER_VALIDATE_BOOLEAN),
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bell" viewBox="0 0 16 16"> <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2M8 1.918l-.797.161A4 4 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4 4 0 0 0-3.203-3.92zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5 5 0 0 1 13 6c0 .88.32 4.2 1.22 6"/> </svg>'
        );
    }

    public function render_profile_icon($atts = [])
    {
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'class' => '',
        ], $atts);

        $url = Settings::profile_url();
        $icon_html = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person" viewBox="0 0 16 16"> <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664z"/> </svg>';

        return $this->render_shortcut_icon_markup(
            'profile',
            $url,
            0,
            __('Profil', 'velocity-marketplace'),
            (string) $atts['class'],
            false,
            $icon_html
        );
    }

    public function render_cart_drawer_footer()
    {
        if (!$this->needs_cart_drawer || is_admin()) {
            return;
        }

        echo Template::render('cart-drawer', []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
        $empty_message = $empty_message !== '' ? (string) $empty_message : __('Produk belum tersedia.', 'velocity-marketplace');

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
            return '<div class="py-4 text-center border rounded bg-light"><div class="h6 mb-1">' . esc_html__('Produk belum tersedia.', 'velocity-marketplace') . '</div><div class="text-muted">' . esc_html($empty_message) . '</div></div>';
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

        $stock_text = __('Stok tidak terbatas', 'velocity-marketplace');
        if (isset($item['stock']) && $item['stock'] !== '' && $item['stock'] !== null) {
            $stock_text = (float) $item['stock'] > 0
                ? sprintf(__('Stok: %d', 'velocity-marketplace'), (int) $item['stock'])
                : __('Stok habis', 'velocity-marketplace');
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
        $html .= '<div class="mt-auto d-flex gap-2 vmp-product-card__actions">';
        $html .= $this->render_add_to_cart_markup($item, __('Tambah Keranjang', 'velocity-marketplace'), 'btn btn-sm btn-dark flex-grow-1');
        $html .= $this->render_add_to_wishlist_markup($product_id, __('Wishlist', 'velocity-marketplace'), 'btn btn-sm btn-outline-secondary vmp-wishlist-button', $wishlist_active);
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
            $html .= '<div class="vmp-thumb vmp-thumb--empty d-flex align-items-center justify-content-center text-muted">' . esc_html__('Tidak ada gambar', 'velocity-marketplace') . '</div>';
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

    private function render_add_to_cart_markup($item, $text = '', $class_name = 'btn btn-sm btn-dark', $style = 'popup')
    {
        $item = is_array($item) ? $item : [];
        $product_id = isset($item['id']) ? (int) $item['id'] : 0;
        if ($product_id <= 0) {
            return '';
        }

        $text = $text !== '' ? (string) $text : __('Tambah Keranjang', 'velocity-marketplace');
        $style = sanitize_key((string) $style);
        if (!in_array($style, ['popup', 'inline'], true)) {
            $style = 'popup';
        }

        $payload = [
            'title' => (string) ($item['title'] ?? ''),
            'variant_name' => (string) ($item['variant_name'] ?? ''),
            'variant_options' => array_values((array) ($item['variant_options'] ?? [])),
            'price_adjustment_name' => (string) ($item['price_adjustment_name'] ?? ''),
            'price_adjustment_options' => array_values((array) ($item['price_adjustment_options'] ?? [])),
        ];

        $html = '<div class="vmp-add-to-cart-block" data-vmp-add-to-cart-style="' . esc_attr($style) . '">';

        if ($style === 'inline') {
            $html .= $this->render_inline_add_to_cart_options($payload);
        }

        $html .= '<button type="button" class="' . esc_attr(trim((string) $class_name)) . ' vmp-action-add-to-cart" data-product-id="' . esc_attr((string) $product_id) . '" data-product-options="' . esc_attr(wp_json_encode($payload)) . '" data-option-style="' . esc_attr($style) . '" data-default-label="' . esc_attr((string) $text) . '">' . esc_html((string) $text) . '</button>';
        $html .= '</div>';

        return $html;
    }

    private function render_inline_add_to_cart_options($payload = [])
    {
        $payload = is_array($payload) ? $payload : [];
        $variant_name = isset($payload['variant_name']) ? (string) $payload['variant_name'] : '';
        $variant_options = isset($payload['variant_options']) && is_array($payload['variant_options'])
            ? array_values(array_filter(array_map('strval', $payload['variant_options'])))
            : [];
        $price_adjustment_name = isset($payload['price_adjustment_name']) ? (string) $payload['price_adjustment_name'] : '';
        $price_adjustment_options = isset($payload['price_adjustment_options']) && is_array($payload['price_adjustment_options'])
            ? array_values($payload['price_adjustment_options'])
            : [];

        if (empty($variant_options) && empty($price_adjustment_options)) {
            return '';
        }

        $html = '<div class="vmp-inline-product-options d-grid gap-2 mb-2">';

        if (!empty($variant_options)) {
            $html .= '<div class="vmp-inline-product-options__group">';
            $html .= '<label class="form-label small mb-1">' . esc_html($variant_name !== '' ? $variant_name : __('Pilihan Varian', 'velocity-marketplace')) . '</label>';
            $html .= '<select class="form-select form-select-sm" data-vmp-inline-option="variant">';
            foreach ($variant_options as $index => $option_label) {
                $html .= '<option value="' . esc_attr($option_label) . '"' . selected($index, 0, false) . '>' . esc_html($option_label) . '</option>';
            }
            $html .= '</select></div>';
        }

        if (!empty($price_adjustment_options)) {
            $html .= '<div class="vmp-inline-product-options__group">';
            $html .= '<label class="form-label small mb-1">' . esc_html($price_adjustment_name !== '' ? $price_adjustment_name : __('Pilihan Harga', 'velocity-marketplace')) . '</label>';
            $html .= '<select class="form-select form-select-sm" data-vmp-inline-option="price_adjustment">';
            foreach ($price_adjustment_options as $index => $option_row) {
                $option_label = isset($option_row['label']) ? (string) $option_row['label'] : '';
                if ($option_label === '') {
                    continue;
                }
                $amount = isset($option_row['amount']) ? (float) $option_row['amount'] : 0.0;
                $suffix = $amount > 0 ? ' (+' . Settings::currency_symbol() . ' ' . number_format($amount, 0, ',', '.') . ')' : '';
                $html .= '<option value="' . esc_attr($option_label) . '"' . selected($index, 0, false) . '>' . esc_html($option_label . $suffix) . '</option>';
            }
            $html .= '</select></div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function render_add_to_wishlist_markup($product_id, $text = '', $class_name = 'btn btn-sm btn-outline-secondary vmp-wishlist-button', $active = false)
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return '';
        }

        $active = !empty($active);
        $text = $text !== '' ? (string) $text : __('Wishlist', 'velocity-marketplace');
        $classes = trim((string) $class_name) . ' vmp-action-toggle-wishlist' . ($active ? ' is-active' : '');

        return '<button type="button" class="' . esc_attr(trim($classes)) . '" data-product-id="' . esc_attr((string) $product_id) . '" data-default-label="' . esc_attr((string) $text) . '" aria-pressed="' . ($active ? 'true' : 'false') . '" title="' . esc_attr((string) $text) . '" aria-label="' . esc_attr((string) $text) . '">'
            . $this->wishlist_icon_svg($active)
            . '</button>';
    }

    private function wishlist_icon_svg($active = false)
    {
        if ($active) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-heart-fill" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M8 1.314C12.438-3.248 23.534 4.735 8 15C-7.534 4.736 3.562-3.248 8 1.314"/></svg>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-heart" viewBox="0 0 16 16" aria-hidden="true"><path d="m8 2.748-.717-.737C5.6.281 2.514.878 1.4 3.053c-.523 1.023-.641 2.5.314 4.385.92 1.815 2.834 3.989 6.286 6.357 3.452-2.368 5.365-4.542 6.286-6.357.955-1.886.838-3.362.314-4.385C13.486.878 10.4.28 8.717 2.01zM8 15C-7.333 4.868 3.279-3.04 7.824 1.143c.06.055.119.112.176.171a3 3 0 0 1 .176-.17C12.72-3.042 23.333 4.867 8 15"/></svg>';
    }

    private function render_shortcut_icon_markup($type, $url, $count, $label, $class_name = '', $show_zero = false, $icon_svg = '', $attributes = [], $requires_login = true)
    {
        $type = sanitize_key((string) $type);
        $count = max(0, (int) $count);
        $label = sanitize_text_field((string) $label);
        $class_name = trim((string) $class_name);
        $url = (!$requires_login || is_user_logged_in()) ? (string) $url : wp_login_url((string) $url);
        $should_show_badge = $show_zero || $count > 0;

        $classes = trim('vmp-cart-shortcut vmp-shortcut vmp-shortcut--' . $type . ' ' . $class_name);
        $badge_style = $should_show_badge ? '' : ' style="display:none"';
        $badge_html = '<span class="vmp-cart-shortcut__badge" aria-hidden="true"' . $badge_style . '>' . esc_html((string) $count) . '</span>';
        $attribute_html = '';

        foreach ((array) $attributes as $name => $value) {
            $name = strtolower(trim((string) $name));
            if ($name === '') {
                continue;
            }
            $attribute_html .= ' ' . esc_attr($name) . '="' . esc_attr((string) $value) . '"';
        }

        return '<a href="' . esc_url($url) . '" class="' . esc_attr($classes) . '" aria-label="' . esc_attr($label) . '"' . $attribute_html . '>'
            . '<span class="vmp-cart-shortcut__toggle" aria-hidden="true">'
            . '<span class="vmp-cart-shortcut__icon">' . $icon_svg . '</span>'
            . $badge_html
            . '</span>'
            . '</a>';
    }

    private function cart_page_url()
    {
        $pages = get_option(VMP_PAGES_OPTION, []);
        if (is_array($pages) && !empty($pages['keranjang'])) {
            $url = get_permalink((int) $pages['keranjang']);
            if ($url) {
                return $url;
            }
        }

        return site_url('/cart/');
    }

    private function ensure_frontend_assets()
    {
        if (is_admin()) {
            return;
        }

        (new Assets())->enqueue_forced();
    }
}

