<?php

namespace VelocityMarketplace\Frontend;

use VelocityMarketplace\Modules\Cart\CartRepository;
use VelocityMarketplace\Modules\Message\MessageRepository;
use VelocityMarketplace\Modules\Notification\NotificationRepository;
use VelocityMarketplace\Modules\Product\PremiumBadge;
use VelocityMarketplace\Modules\Product\ProductData;
use VelocityMarketplace\Modules\Product\ProductMeta;
use VelocityMarketplace\Modules\Product\ProductQuery;
use VelocityMarketplace\Modules\Review\RatingRenderer;
use VelocityMarketplace\Modules\Wishlist\WishlistRepository;
use VelocityMarketplace\Support\Contract;
use VelocityMarketplace\Support\Settings;

class Shortcode
{
    private $needs_cart_drawer = false;

    public function register()
    {
        $this->register_shortcode_aliases(['vmp_products'], 'render_products');
        $this->register_shortcode_aliases(['vmp_product_card'], 'render_product_card');
        $this->register_shortcode_aliases(['vmp_add_to_cart'], 'render_add_to_cart');
        $this->register_shortcode_aliases(['vmp_add_to_wishlist'], 'render_add_to_wishlist');
        $this->register_shortcode_aliases(['vmp_recently_viewed'], 'render_recently_viewed');
        $this->register_shortcode_aliases(['vmp_product_gallery'], 'render_product_gallery');
        $this->register_shortcode_aliases(['vmp_product_reviews'], 'render_product_reviews');
        $this->register_shortcode_aliases(['vmp_product_seller_card'], 'render_product_seller_card');
        add_shortcode('vmp_rating', [$this, 'render_rating']);
        add_shortcode('vmp_review_count', [$this, 'render_review_count']);
        add_shortcode('vmp_sold_count', [$this, 'render_sold_count']);
        add_shortcode('vmp_premium_badge', [$this, 'render_premium_badge']);
        $this->register_shortcode_aliases(['vmp_cart', 'wp_store_cart'], 'render_cart');
        $this->register_shortcode_aliases(['vmp_cart_page', 'wp_store_cart_page', 'store_cart'], 'render_cart_page');
        $this->register_shortcode_aliases(['vmp_checkout', 'wp_store_checkout', 'store_checkout'], 'render_checkout');
        $this->register_shortcode_aliases(['vmp_profile'], 'render_profile');
        $this->register_shortcode_aliases(['vmp_tracking'], 'render_tracking');
        add_shortcode('vmp_store_profile', [$this, 'render_store_profile']);
        $this->register_shortcode_aliases(['vmp_product_filter'], 'render_product_filter');
        add_shortcode('vmp_messages_icon', [$this, 'render_messages_icon']);
        add_shortcode('vmp_notifications_icon', [$this, 'render_notifications_icon']);
        $this->register_shortcode_aliases(['vmp_profile_icon'], 'render_profile_icon');

        add_action('wp_footer', [$this, 'render_cart_drawer_footer'], 30);
        add_action('wp_store_single_after_summary', [$this, 'render_core_single_marketplace_extension'], 20, 2);
        add_filter('the_content', [$this, 'filter_managed_core_page_content'], 30);
        add_filter('template_include', [$this, 'override_managed_page_template'], 120);
    }

    private function register_shortcode_aliases($tags, $method)
    {
        foreach ((array) $tags as $tag) {
            add_shortcode((string) $tag, [$this, $method]);
        }
    }

    private function render_core_shortcode($tag, $atts = [], $map = [])
    {
        $tag = sanitize_key((string) $tag);
        if ($tag === '' || !shortcode_exists($tag)) {
            return '';
        }

        $normalized = [];
        foreach ((array) $atts as $key => $value) {
            $key = (string) $key;
            if (isset($map[$key])) {
                $key = (string) $map[$key];
            }

            if ($key === '' || is_array($value) || is_object($value) || $value === null) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $normalized[$key] = (string) $value;
        }

        $parts = [];
        foreach ($normalized as $key => $value) {
            $parts[] = sprintf('%s="%s"', $key, esc_attr($value));
        }

        $shortcode = '[' . $tag . (!empty($parts) ? ' ' . implode(' ', $parts) : '') . ']';
        return do_shortcode($shortcode);
    }

    private function render_core_product_card($item, $extra_html = '', $card_class = '')
    {
        if (!class_exists('\WpStore\Frontend\Template')) {
            return '';
        }

        $item = is_array($item) ? $item : [];
        $product_id = isset($item['id']) ? (int) $item['id'] : 0;
        if ($product_id <= 0) {
            return '';
        }

        $core_item = [
            'id' => $product_id,
            'title' => (string) ($item['title'] ?? ''),
            'link' => (string) ($item['link'] ?? get_permalink($product_id)),
            'image' => (string) ($item['image'] ?? ''),
            'price' => isset($item['price']) ? (float) $item['price'] : null,
            'stock' => $item['stock'] ?? null,
        ];

        return \WpStore\Frontend\Template::render('components/product-card', [
            'item' => $core_item,
            'currency' => Settings::currency_symbol(),
            'view_label' => __('Detail', 'velocity-marketplace'),
            'extra_html' => $extra_html,
            'card_class' => $card_class,
        ]);
    }

    public function render_core_single_marketplace_extension($product_id, $context = [])
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0 || get_post_type($product_id) !== Contract::PRODUCT_POST_TYPE) {
            return;
        }

        echo Template::render('product-seller-card', [
            'product_id' => $product_id,
            'context' => is_array($context) ? $context : [],
        ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

        $extra_html = $this->product_card_extra_html($item);
        $core = $this->render_core_product_card($item, $extra_html, 'vmp-product-card');
        if ($core !== '') {
            return $core;
        }

        return $this->render_product_card_markup($item);
    }

    public function render_product_gallery($atts = [])
    {
        $this->ensure_frontend_assets();
        return $this->render_core_shortcode('wp_store_gallery', $atts);
    }

    public function render_product_reviews($atts = [])
    {
        $this->ensure_frontend_assets();
        return $this->render_core_shortcode('wp_store_product_reviews', $atts);
    }

    public function render_premium_badge($atts = [])
    {
        $atts = shortcode_atts([
            'post_id' => 0,
            'text' => __('Premium', 'velocity-marketplace'),
            'class' => 'badge bg-warning text-dark',
        ], $atts);

        return PremiumBadge::render([
            'post_id' => (int) $atts['post_id'],
            'text' => (string) $atts['text'],
            'class' => (string) $atts['class'],
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

    public function render_recently_viewed($atts = [])
    {
        $this->ensure_frontend_assets();
        return $this->render_core_shortcode('wp_store_recently_viewed', $atts);
    }

    public function render_add_to_cart($atts = [])
    {
        $this->ensure_frontend_assets();
        $raw_atts = is_array($atts) ? $atts : [];

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

        $text = array_key_exists('text', $raw_atts)
            ? (string) $atts['text']
            : __('Tambah Keranjang', 'velocity-marketplace');

        return $this->render_add_to_cart_markup(
            $item,
            $text,
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
            $active = (new WishlistRepository())->has($product_id, get_current_user_id());
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

        $count = (int) get_post_meta($product_id, '_store_sold_count', true);
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
        $core = $this->render_core_shortcode('wp_store_profile', $atts);
        if ($core !== '') {
            return $core;
        }

        return '';
    }

    public function render_tracking($atts = [])
    {
        $this->ensure_frontend_assets();
        $core = $this->render_core_shortcode('wp_store_tracking', $atts);
        if ($core !== '') {
            return $core;
        }

        return '';
    }

    public function filter_managed_core_page_content($content)
    {
        if (is_admin() || !is_singular('page') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $page_id = get_queried_object_id();
        if (!$page_id) {
            return $content;
        }

        if ((int) $page_id === Settings::cart_page_id()) {
            $this->ensure_frontend_assets();
            return Template::render('cart', []);
        }

        if ((int) $page_id === Settings::checkout_page_id()) {
            $this->ensure_frontend_assets();
            return Template::render('checkout', []);
        }

        return $content;
    }

    public function override_managed_page_template($template)
    {
        if (is_admin() || !is_singular('page')) {
            return $template;
        }

        $page_id = get_queried_object_id();
        if (!$page_id) {
            return $template;
        }

        if ((int) $page_id === Settings::cart_page_id()) {
            $path = VMP_PATH . 'templates/page-templates/cart.php';
            if (file_exists($path)) {
                return $path;
            }
        }

        if ((int) $page_id === Settings::checkout_page_id()) {
            $path = VMP_PATH . 'templates/page-templates/checkout.php';
            if (file_exists($path)) {
                return $path;
            }
        }

        return $template;
    }

    public function render_store_profile($atts = [])
    {
        $this->ensure_frontend_assets();

        $atts = shortcode_atts([
            'seller' => '',
        ], $atts);

        $seller_id = 0;
        $seller_attr = trim((string) $atts['seller']);
        if ($seller_attr !== '') {
            if (ctype_digit($seller_attr)) {
                $seller_id = (int) $seller_attr;
            } else {
                $seller = get_user_by('login', sanitize_user($seller_attr, true));
                $seller_id = $seller ? (int) $seller->ID : 0;
            }
        }

        if ($seller_id <= 0) {
            $seller_login = trim((string) get_query_var('vmp_store_user'));
            if ($seller_login !== '') {
                $seller = get_user_by('login', sanitize_user($seller_login, true));
                $seller_id = $seller ? (int) $seller->ID : 0;
            }
        }

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

        $core = $this->render_core_shortcode('wp_store_filters', $atts);
        if ($core !== '') {
            return $core;
        }

        $atts = shortcode_atts([
            'action' => '',
            'class' => '',
        ], $atts);

        $action_url = trim((string) $atts['action']);
        if ($action_url === '') {
            if (is_post_type_archive(Contract::PRODUCT_POST_TYPE)) {
                $action_url = get_post_type_archive_link(Contract::PRODUCT_POST_TYPE);
            } else {
                $action_url = Settings::catalog_url();
            }
        }

        $product_query = new ProductQuery();

        return Template::render('product-filter-form', [
            'filters' => $product_query->normalize_filters($_GET),
            'categories' => get_terms([
                'taxonomy' => Contract::PRODUCT_TAXONOMY,
                'hide_empty' => false,
            ]),
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
            if ($loop_id && Contract::is_product($loop_id)) {
                $product_id = (int) $loop_id;
            }
        }

        if ($product_id > 0 && !Contract::is_product($product_id)) {
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
            'post_type' => Contract::PRODUCT_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            's' => $search,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if ($cat > 0) {
            $args['tax_query'] = [
                [
                    'taxonomy' => Contract::PRODUCT_TAXONOMY,
                    'field' => 'term_id',
                    'terms' => [$cat],
                ],
            ];
        }

        if ($author > 0) {
            $args['author'] = $author;
        }

        if ($sort === 'price_asc') {
            $args['meta_key'] = ProductMeta::canonical_key('price');
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'ASC';
        } elseif ($sort === 'price_desc') {
            $args['meta_key'] = ProductMeta::canonical_key('price');
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
            $extra_html = $this->product_card_extra_html($item);
            $card_html = $this->render_core_product_card($item, $extra_html, 'vmp-product-card');
            $html .= $card_html !== '' ? $card_html : $this->render_product_card_markup($item);
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    private function product_card_extra_html($item)
    {
        $item = is_array($item) ? $item : [];
        $parts = [];

        if (!empty($item['seller_city'])) {
            $parts[] = '<div class="small text-muted mb-1">' . esc_html((string) $item['seller_city']) . '</div>';
        }

        if (!empty($item['sold_count'])) {
            $parts[] = '<div class="small text-muted mb-1">' . esc_html(sprintf(__('%d terjual', 'velocity-marketplace'), (int) $item['sold_count'])) . '</div>';
        }

        if (!empty($item['rating_html'])) {
            $parts[] = '<div class="mb-1">' . $item['rating_html'] . '</div>';
        } else {
            $parts[] = '<div class="small text-muted mb-1">' . esc_html__('Belum ada ulasan', 'velocity-marketplace') . '</div>';
        }

        return implode('', $parts);
    }

    private function render_product_card_markup($item)
    {
        $item = is_array($item) ? $item : [];
        $product_id = isset($item['id']) ? (int) $item['id'] : 0;
        if ($product_id <= 0) {
            return '';
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
        $html .= '<div class="mt-auto vmp-product-card__actions">';
        $html .= $this->render_add_to_cart_markup($item, __('Tambah Keranjang', 'velocity-marketplace'), 'btn btn-sm btn-dark w-100');
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

        if (function_exists('wp_store_add_to_cart_button')) {
            return wp_store_add_to_cart_button($product_id, [
                'text' => (string) $text,
                'class' => (string) $class_name,
                'qty' => 0,
            ]);
        }

        $text = (string) $text;
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
            'base_price' => isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : 0.0,
        ];
        $min_order = isset($item['min_order']) ? max(1, (int) $item['min_order']) : 1;

        $html = '<div class="vmp-add-to-cart-block" data-vmp-add-to-cart-style="' . esc_attr($style) . '">';

        if ($style === 'inline') {
            $html .= $this->render_inline_add_to_cart_options($payload);
        }

        $button_class = trim((string) $class_name . ' d-inline-flex align-items-center justify-content-center gap-2');
        $button_label = $text !== '' ? $text : __('Tambah Keranjang', 'velocity-marketplace');
        $html .= '<button type="button" class="' . esc_attr($button_class) . ' vmp-action-add-to-cart" data-product-id="' . esc_attr((string) $product_id) . '" data-product-options="' . esc_attr(wp_json_encode($payload)) . '" data-option-style="' . esc_attr($style) . '" data-min-order="' . esc_attr((string) $min_order) . '" data-default-label="' . esc_attr((string) $button_label) . '" aria-label="' . esc_attr((string) $button_label) . '">';
        $html .= '<span class="vmp-action-add-to-cart__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h2l2.4 10.5a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 7H7"/><circle cx="10" cy="19" r="1.5" fill="currentColor"/><circle cx="17" cy="19" r="1.5" fill="currentColor"/></svg></span>';
        if ($text !== '') {
            $html .= '<span class="vmp-action-add-to-cart__text">' . esc_html($text) . '</span>';
        }
        $html .= '</button>';
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
        return Settings::cart_url();
    }

    private function ensure_frontend_assets()
    {
        if (is_admin()) {
            return;
        }

        (new Assets())->enqueue_forced();
    }
}

