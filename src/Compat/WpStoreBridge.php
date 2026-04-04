<?php

namespace VelocityMarketplace\Compat;

use VelocityMarketplace\Frontend\Shortcode;
use VelocityMarketplace\Frontend\Template;
use VelocityMarketplace\Modules\Captcha\CaptchaBridge;
use VelocityMarketplace\Modules\Product\ProductData;
use VelocityMarketplace\Modules\Product\ProductQuery;
use VelocityMarketplace\Modules\Wishlist\WishlistRepository;
use VelocityMarketplace\Support\Contract;
use VelocityMarketplace\Support\Settings;

class WpStoreBridge
{
    /** @var Shortcode */
    private $shortcode;

    public function __construct(Shortcode $shortcode)
    {
        $this->shortcode = $shortcode;
    }

    public static function is_wp_store_active()
    {
        return defined('WP_STORE_VERSION')
            || function_exists('wp_store_init')
            || class_exists('\\WpStore\\Core\\Plugin', false);
    }

    public function register()
    {
        if (self::is_wp_store_active()) {
            return;
        }

        add_shortcode('store_customer_profile', [$this, 'render_profile']);
        add_shortcode('wp_store_profile', [$this, 'render_profile']);

        add_shortcode('wp_store_shop', [$this, 'render_shop']);
        add_shortcode('wp_store_single', [$this, 'render_single']);
        add_shortcode('wp_store_related', [$this, 'render_related']);
        add_shortcode('wp_store_thumbnail', [$this, 'render_thumbnail']);
        add_shortcode('wp_store_price', [$this, 'render_price']);
        add_shortcode('wp_store_add_to_cart', [$this, 'render_add_to_cart']);
        add_shortcode('wp_store_detail', [$this, 'render_detail']);
        add_shortcode('wp_store_cart', [$this, 'render_cart']);
        add_shortcode('wp_store_cart_page', [$this, 'render_cart_page']);
        add_shortcode('store_cart', [$this, 'render_cart_page']);
        add_shortcode('wp_store_checkout', [$this, 'render_checkout']);
        add_shortcode('store_checkout', [$this, 'render_checkout']);
        add_shortcode('wp_store_thanks', [$this, 'render_thanks']);
        add_shortcode('store_thanks', [$this, 'render_thanks']);
        add_shortcode('wp_store_tracking', [$this, 'render_tracking']);
        add_shortcode('store_tracking', [$this, 'render_tracking']);
        add_shortcode('wp_store_wishlist', [$this, 'render_wishlist']);
        add_shortcode('wp_store_add_to_wishlist', [$this, 'render_add_to_wishlist']);
        add_shortcode('wp_store_link_profile', [$this, 'render_link_profile']);
        add_shortcode('wp_store_products_carousel', [$this, 'render_products_carousel']);
        add_shortcode('wp_store_shipping_checker', [$this, 'render_shipping_checker']);
        add_shortcode('wp_store_catalog', [$this, 'render_catalog']);
        add_shortcode('wp_store_filters', [$this, 'render_filters']);
        add_shortcode('wp_store_shop_with_filters', [$this, 'render_shop_with_filters']);
        add_shortcode('wp_store_captcha', [$this, 'render_captcha']);
        add_shortcode('wp-store-captcha', [$this, 'render_captcha']);
    }

    public function render_profile($atts = [])
    {
        return $this->shortcode->render_profile($atts);
    }

    public function render_shop($atts = [])
    {
        $atts = shortcode_atts([
            'per_page' => 12,
        ], $atts);

        $filters = $this->legacy_filters_from_request($_GET);

        return $this->shortcode->render_products([
            'per_page' => max(1, min(48, (int) $atts['per_page'])),
            'cat' => (int) $filters['cat'],
            'sort' => (string) $filters['sort'],
            'search' => (string) $filters['search'],
        ]);
    }

    public function render_single($atts = [])
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

        $content = apply_filters('the_content', get_post_field('post_content', $product_id));

        ob_start();
        ?>
        <div class="vmp-wps-single-compat">
            <div class="row g-4">
                <div class="col-lg-6">
                    <?php echo do_shortcode('[vmp_product_gallery id="' . (int) $product_id . '"]'); ?>
                </div>
                <div class="col-lg-6">
                    <h1 class="h3 mb-2"><?php echo esc_html((string) $item['title']); ?></h1>

                    <?php if (!empty($item['label'])) : ?>
                        <div class="text-muted mb-2"><?php echo esc_html((string) $item['label']); ?></div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <?php echo do_shortcode('[vmp_rating type="product" id="' . (int) $product_id . '" size="16"]'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo do_shortcode('[vmp_price id="' . (int) $product_id . '" class="h5"]'); ?>
                    </div>

                    <div class="small text-muted mb-3">
                        <?php
                        if ($item['stock'] === null || $item['stock'] === '') {
                            echo esc_html__('Stok tidak terbatas', 'velocity-marketplace');
                        } else {
                            echo esc_html((float) $item['stock'] > 0 ? sprintf(__('Stok: %d', 'velocity-marketplace'), (int) $item['stock']) : __('Stok habis', 'velocity-marketplace'));
                        }
                        ?>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <?php echo do_shortcode('[vmp_add_to_cart id="' . (int) $product_id . '" text="' . esc_attr__('Tambah Keranjang', 'velocity-marketplace') . '" class="btn btn-dark"]'); ?>
                        <?php echo do_shortcode('[vmp_add_to_wishlist id="' . (int) $product_id . '" text="' . esc_attr__('Wishlist', 'velocity-marketplace') . '" class="btn btn-outline-secondary vmp-wishlist-button"]'); ?>
                    </div>

                    <?php echo do_shortcode('[vmp_product_seller_card id="' . (int) $product_id . '"]'); ?>
                </div>
            </div>

            <?php if ($content !== '') : ?>
                <div class="mt-4">
                    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <?php echo do_shortcode('[vmp_product_reviews id="' . (int) $product_id . '"]'); ?>
            </div>

            <div class="mt-4">
                <?php echo do_shortcode('[vmp_related_products id="' . (int) $product_id . '"]'); ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function render_related($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'per_page' => 4,
        ], $atts);

        return $this->shortcode->render_related_products([
            'id' => (int) $atts['id'],
            'limit' => max(1, min(12, (int) $atts['per_page'])),
        ]);
    }

    public function render_thumbnail($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'width' => 600,
            'height' => 600,
            'crop' => 'true',
            'alt' => '',
            'label' => 'true',
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        $item = ProductData::map_post($product_id);
        if (!$item) {
            return '';
        }

        $width = max(1, (int) $atts['width']);
        $height = max(1, (int) $atts['height']);
        $aspect = ($height / $width) * 100;
        $fit = filter_var($atts['crop'], FILTER_VALIDATE_BOOLEAN) ? 'cover' : 'contain';
        $image = ProductData::image_url($product_id, 'large', $item['gallery_ids']);
        $alt = (string) $atts['alt'] !== '' ? (string) $atts['alt'] : (string) $item['title'];
        $show_label = filter_var($atts['label'], FILTER_VALIDATE_BOOLEAN);

        $html = '<a href="' . esc_url((string) $item['link']) . '" class="d-block text-decoration-none position-relative">';
        $html .= '<div class="ratio rounded overflow-hidden bg-light" style="--bs-aspect-ratio:' . esc_attr((string) $aspect) . '%;">';
        $html .= '<img src="' . esc_url($image) . '" alt="' . esc_attr($alt) . '" class="w-100 h-100" style="object-fit:' . esc_attr($fit) . ';">';
        $html .= '</div>';
        if ($show_label) {
            $html .= self::label_badge_html($product_id);
            $html .= self::discount_badge_html($product_id);
        }
        $html .= '</a>';

        return $html;
    }

    public function render_price($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'countdown' => false,
        ], $atts);

        return $this->shortcode->render_price([
            'id' => (int) $atts['id'],
        ]);
    }

    public function render_add_to_cart($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'label' => '+',
            'text' => '',
            'class' => 'btn btn-dark',
            'qty' => 0,
        ], $atts);

        $text = (string) $atts['text'] !== '' ? (string) $atts['text'] : (string) $atts['label'];

        return $this->shortcode->render_add_to_cart([
            'id' => (int) $atts['id'],
            'text' => $text,
            'class' => (string) $atts['class'],
            'style' => 'popup',
        ]);
    }

    public function render_detail($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'text' => 'Detail',
            'class' => 'btn btn-outline-secondary',
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        return sprintf(
            '<a href="%1$s" class="%2$s">%3$s</a>',
            esc_url((string) get_permalink($product_id)),
            esc_attr(trim((string) $atts['class'])),
            esc_html((string) $atts['text'])
        );
    }

    public function render_cart($atts = [])
    {
        return $this->shortcode->render_cart($atts);
    }

    public function render_cart_page($atts = [])
    {
        return $this->shortcode->render_cart_page($atts);
    }

    public function render_checkout($atts = [])
    {
        return $this->shortcode->render_checkout($atts);
    }

    public function render_thanks($atts = [])
    {
        $html = '<div class="alert alert-success mb-3">' . esc_html__('Pesanan berhasil dibuat.', 'velocity-marketplace') . '</div>';
        return $html . $this->render_tracking($atts);
    }

    public function render_tracking($atts = [])
    {
        return $this->with_legacy_request(function () use ($atts) {
            return $this->shortcode->render_tracking($atts);
        });
    }

    public function render_wishlist($atts = [])
    {
        $wishlist_ids = (new WishlistRepository())->get_ids(get_current_user_id());
        return Template::render('account/wishlist', [
            'wishlist_ids' => $wishlist_ids,
        ]);
    }

    public function render_add_to_wishlist($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'size' => '',
            'label_add' => 'Wishlist',
            'label_remove' => 'Hapus',
            'icon_only' => '0',
        ], $atts);

        $class_name = 'btn btn-outline-secondary vmp-wishlist-button';
        if (sanitize_key((string) $atts['size']) === 'sm') {
            $class_name = 'btn btn-sm btn-outline-secondary vmp-wishlist-button';
        }

        $text = ((string) $atts['icon_only'] === '1') ? '' : (string) $atts['label_add'];

        return $this->shortcode->render_add_to_wishlist([
            'id' => (int) $atts['id'],
            'text' => $text,
            'class' => $class_name,
        ]);
    }

    public function render_link_profile($atts = [])
    {
        $profile_url = Settings::profile_url();
        $avatar_url = '';

        if (is_user_logged_in()) {
            $avatar_url = get_avatar_url(get_current_user_id());
        }

        if ($avatar_url === '') {
            $avatar_url = get_avatar_url(0);
        }

        return '<a href="' . esc_url($profile_url) . '" class="d-inline-flex align-items-center text-decoration-none">'
            . '<img src="' . esc_url($avatar_url) . '" alt="' . esc_attr__('Profil', 'velocity-marketplace') . '" style="width:32px;height:32px;border-radius:9999px;object-fit:cover;">'
            . '</a>';
    }

    public function render_products_carousel($atts = [])
    {
        $atts = shortcode_atts([
            'label' => '',
            'per_page' => 10,
        ], $atts);

        $product_query = new ProductQuery();
        $query = new \WP_Query($product_query->build_query_args([
            'sort' => 'latest',
        ], [
            'posts_per_page' => max(1, min(20, (int) $atts['per_page'])),
        ]));

        if (!$query->have_posts()) {
            return '';
        }

        ob_start();
        ?>
        <div class="vmp-wps-compat-carousel">
            <?php if ((string) $atts['label'] !== '') : ?>
                <h3 class="h5 mb-3"><?php echo esc_html((string) $atts['label']); ?></h3>
            <?php endif; ?>
            <div class="d-flex gap-3 overflow-auto pb-2">
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <div class="flex-shrink-0" style="width:min(260px,80vw);">
                        <?php echo do_shortcode('[vmp_product_card id="' . (int) get_the_ID() . '"]'); ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php
        wp_reset_postdata();

        return (string) ob_get_clean();
    }

    public function render_shipping_checker($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        $item = $product_id > 0 ? ProductData::map_post($product_id) : null;

        ob_start();
        ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h3 class="h6 mb-2"><?php echo esc_html__('Informasi Ongkir', 'velocity-marketplace'); ?></h3>
                <p class="text-muted small mb-0"><?php echo esc_html__('Perhitungan ongkir dilakukan otomatis saat checkout per toko.', 'velocity-marketplace'); ?></p>
                <?php if ($item) : ?>
                    <div class="small text-muted mt-2">
                        <?php echo esc_html(sprintf(__('Berat produk: %s gr', 'velocity-marketplace'), number_format((float) ($item['weight'] ?? 0), 0, ',', '.'))); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public function render_catalog($atts = [])
    {
        $atts = shortcode_atts([
            'per_page' => 12,
        ], $atts);

        return $this->with_legacy_request(function () use ($atts) {
            return $this->shortcode->render_catalog([
                'per_page' => max(1, min(48, (int) $atts['per_page'])),
            ]);
        });
    }

    public function render_filters($atts = [])
    {
        return $this->with_legacy_request(function () use ($atts) {
            return $this->shortcode->render_product_filter($atts);
        });
    }

    public function render_shop_with_filters($atts = [])
    {
        $atts = shortcode_atts([
            'per_page' => 12,
        ], $atts);

        return $this->with_legacy_request(function () use ($atts) {
            return $this->shortcode->render_catalog([
                'per_page' => max(1, min(48, (int) $atts['per_page'])),
            ]);
        });
    }

    public function render_captcha($atts = [])
    {
        $atts = shortcode_atts([
            'target-button' => '',
            'target_button' => '',
        ], $atts);

        $selector = trim((string) $atts['target-button']);
        if ($selector === '') {
            $selector = trim((string) $atts['target_button']);
        }

        return CaptchaBridge::render($selector);
    }

    public static function icon_html($args = [])
    {
        if (is_string($args)) {
            $args = ['name' => $args];
        }

        if (!is_array($args)) {
            return '';
        }

        $name = sanitize_key((string) ($args['name'] ?? 'circle'));
        $size = max(10, (int) ($args['size'] ?? 16));
        $class = trim((string) ($args['class'] ?? ''));
        $color = '';

        foreach (['stroke_color', 'stroke-color', 'stroke', 'color', 'border-color', 'border_color', 'fill_color', 'fill-color', 'fill'] as $key) {
            if (!empty($args[$key]) && is_string($args[$key])) {
                $color = trim((string) $args[$key]);
                break;
            }
        }

        $icon_class_map = [
            'heart' => 'heart',
            'cart' => 'cart2',
            'cloud-download' => 'cloud-arrow-down',
            'whatsapp' => 'whatsapp',
            'x' => 'twitter-x',
            'facebook' => 'facebook',
            'email' => 'envelope',
            'spinner' => 'arrow-repeat',
            'sliders2' => 'sliders2',
            'map-pin' => 'geo-alt',
            'coupon' => 'ticket-perforated',
            'bank-transfer' => 'bank',
            'qr' => 'qr-code',
            'credit-card' => 'credit-card',
            'filetype-pdf' => 'filetype-pdf',
            'close' => 'x-lg',
            'trash' => 'trash',
            'arrow-repeat' => 'arrow-repeat',
            'check' => 'check-lg',
            'eye' => 'eye',
            'user' => 'person',
            'settings' => 'gear',
            'logout' => 'box-arrow-right',
        ];

        if (!isset($icon_class_map[$name])) {
            return '';
        }

        $style = 'font-size:' . $size . 'px;';
        if ($color !== '') {
            $style .= 'color:' . esc_attr($color) . ';';
        }

        return '<i class="bi bi-' . esc_attr($icon_class_map[$name]) . ' ' . esc_attr($class) . '" style="' . esc_attr($style) . '"></i>';
    }

    public static function label_badge_html($product_id)
    {
        $label = self::product_meta_text($product_id, ['_store_label', 'label']);
        if ($label === '') {
            return '';
        }

        $label = sanitize_key($label);
        $normalized = str_replace('label-', '', $label);
        $text_map = [
            'best' => __('Best Seller', 'velocity-marketplace'),
            'limited' => __('Limited', 'velocity-marketplace'),
            'new' => __('New', 'velocity-marketplace'),
        ];

        if (!isset($text_map[$normalized])) {
            return '';
        }

        $class_map = [
            'best' => 'bg-success',
            'limited' => 'bg-primary',
            'new' => 'bg-danger',
        ];

        return '<span class="position-absolute top-0 start-0 m-2 badge ' . esc_attr($class_map[$normalized]) . '">'
            . esc_html($text_map[$normalized])
            . '</span>';
    }

    public static function discount_badge_html($product_id)
    {
        $price = self::product_meta_number($product_id, ['_store_price', 'price']);
        $sale = self::product_meta_number($product_id, ['_store_sale_price', 'sale_price']);

        if ($price <= 0 || $sale <= 0 || $sale >= $price) {
            return '';
        }

        $until = self::product_meta_text($product_id, ['_store_flashsale_until', 'sale_until']);
        if ($until !== '') {
            $until_ts = strtotime($until);
            if ($until_ts && $until_ts <= current_time('timestamp')) {
                return '';
            }
        }

        $percent = (int) round((($price - $sale) / $price) * 100);
        if ($percent <= 0) {
            return '';
        }

        return '<span class="position-absolute bottom-0 end-0 m-2 badge rounded-pill bg-danger">'
            . esc_html($percent . '%')
            . '</span>';
    }

    private function with_legacy_request(callable $callback)
    {
        $original = $_GET;
        $_GET = $this->legacy_request($_GET);

        if (!isset($_GET['invoice']) && !empty($original['order'])) {
            $_GET['invoice'] = sanitize_text_field((string) $original['order']);
        }

        try {
            return $callback();
        } finally {
            $_GET = $original;
        }
    }

    private function legacy_request($source = [])
    {
        $source = is_array($source) ? $source : [];
        $mapped = $source;

        if (empty($mapped['search']) && !empty($mapped['s'])) {
            $mapped['search'] = sanitize_text_field((string) $mapped['s']);
        }

        if (empty($mapped['cat']) && empty($mapped['product_cat']) && !empty($mapped['cats'])) {
            $cats = is_array($mapped['cats']) ? $mapped['cats'] : [$mapped['cats']];
            $first = reset($cats);
            $cat = absint($first);
            if ($cat > 0) {
                $mapped['cat'] = $cat;
                $mapped['product_cat'] = $cat;
            }
        }

        if (empty($mapped['label']) && empty($mapped['product_label']) && !empty($mapped['labels'])) {
            $labels = is_array($mapped['labels']) ? $mapped['labels'] : [$mapped['labels']];
            $label = sanitize_key((string) reset($labels));
            if ($label !== '') {
                $mapped['label'] = str_replace('label-', '', $label);
                $mapped['product_label'] = str_replace('label-', '', $label);
            }
        }

        if (!empty($mapped['sort'])) {
            $mapped['sort'] = $this->map_sort((string) $mapped['sort']);
        }

        return $mapped;
    }

    private function legacy_filters_from_request($source = [])
    {
        $mapped = $this->legacy_request($source);

        return [
            'search' => sanitize_text_field((string) ($mapped['search'] ?? '')),
            'sort' => $this->map_sort((string) ($mapped['sort'] ?? 'latest')),
            'cat' => (int) ($mapped['cat'] ?? 0),
            'label' => sanitize_key((string) ($mapped['label'] ?? '')),
        ];
    }

    private function map_sort($sort)
    {
        $sort = sanitize_key((string) $sort);

        $map = [
            'az' => 'name_asc',
            'za' => 'name_desc',
            'cheap' => 'price_asc',
            'expensive' => 'price_desc',
            'popular' => 'popular',
            'latest' => 'latest',
            'name_asc' => 'name_asc',
            'name_desc' => 'name_desc',
            'price_asc' => 'price_asc',
            'price_desc' => 'price_desc',
            'sold_desc' => 'sold_desc',
            'rating_desc' => 'rating_desc',
        ];

        return $map[$sort] ?? 'latest';
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

        return ($product_id > 0 && Contract::is_product($product_id)) ? $product_id : 0;
    }

    private static function product_meta_text($product_id, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            $value = get_post_meta((int) $product_id, $key, true);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return (string) $default;
    }

    private static function product_meta_number($product_id, array $keys, $default = 0)
    {
        foreach ($keys as $key) {
            $value = get_post_meta((int) $product_id, $key, true);
            if ($value !== '' && $value !== null && is_numeric($value)) {
                return (float) $value;
            }
        }

        return (float) $default;
    }
}
