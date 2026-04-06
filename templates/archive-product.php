<?php
use VelocityMarketplace\Modules\Product\ProductData;
use VelocityMarketplace\Modules\Product\ProductQuery;
use VelocityMarketplace\Support\Settings;

$product_query = new ProductQuery();
$filters = $product_query->normalize_filters($_GET);
$categories = get_terms([
    'taxonomy' => 'store_product_cat',
    'hide_empty' => false,
]);
$label_options = $product_query->label_options();
$sort_options = $product_query->sort_options();
$active_filter_chips = $product_query->describe_active_filters($filters);
$archive_url = get_post_type_archive_link('store_product');
$catalog_url = Settings::catalog_url();
$current_args = array_filter([
    'search' => (string) ($filters['search'] ?? ''),
    'product_cat' => (int) ($filters['cat'] ?? 0),
    'product_label' => (string) ($filters['label'] ?? ''),
    'store_type' => (string) ($filters['store_type'] ?? ''),
    'store_province_id' => (string) ($filters['store_province_id'] ?? ''),
    'store_city_id' => (string) ($filters['store_city_id'] ?? ''),
    'store_subdistrict_id' => (string) ($filters['store_subdistrict_id'] ?? ''),
    'min_price' => $filters['min_price'] !== '' ? (string) $filters['min_price'] : '',
    'max_price' => $filters['max_price'] !== '' ? (string) $filters['max_price'] : '',
    'sort' => (string) ($filters['sort'] ?? ''),
], static function ($value) {
    return $value !== '' && $value !== 0;
});

get_header();
?>
<div class="container py-4 vmp-wrap">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?php post_type_archive_title(); ?></h1>
            <div class="text-muted"><?php echo esc_html__('Jelajahi produk yang tersedia di marketplace.', 'velocity-marketplace'); ?></div>
        </div>
        <a href="<?php echo esc_url($catalog_url); ?>" class="btn btn-sm btn-outline-dark"><?php echo esc_html__('Buka Katalog Interaktif', 'velocity-marketplace'); ?></a>
    </div>

    <div class="d-flex d-lg-none justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">
            <?php echo esc_html(sprintf(_n('%d produk ditemukan', '%d produk ditemukan', (int) $wp_query->found_posts, 'velocity-marketplace'), (int) $wp_query->found_posts)); ?>
        </div>
        <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="offcanvas" data-bs-target="#vmpArchiveFilterCanvas" aria-controls="vmpArchiveFilterCanvas">
            <?php echo esc_html__('Filter Produk', 'velocity-marketplace'); ?>
        </button>
    </div>

    <div class="row g-4 vmp-archive-layout">
        <aside class="col-lg-3 d-none d-lg-block">
            <div class="card border-0 shadow-sm vmp-archive-filter-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <h2 class="h6 mb-0"><?php echo esc_html__('Filter', 'velocity-marketplace'); ?></h2>
                        <span class="small text-muted"><?php echo esc_html(sprintf(_n('%d product', '%d products', (int) $wp_query->found_posts, 'velocity-marketplace'), (int) $wp_query->found_posts)); ?></span>
                    </div>
                    <?php echo \VelocityMarketplace\Frontend\Template::render('product-filter-form', [
                        'filters' => $filters,
                        'categories' => $categories,
                        'label_options' => $label_options,
                        'sort_options' => $sort_options,
                        'action_url' => $archive_url,
                    ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        </aside>

        <div class="col-12 col-lg-9">
            <div class="d-none d-lg-flex justify-content-between align-items-center gap-3 mb-3">
                <div class="small text-muted"><?php echo esc_html(sprintf(_n('%d produk ditemukan', '%d produk ditemukan', (int) $wp_query->found_posts, 'velocity-marketplace'), (int) $wp_query->found_posts)); ?></div>
            </div>

            <?php if (!empty($active_filter_chips)) : ?>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <?php foreach ($active_filter_chips as $chip) : ?>
                        <span class="badge rounded-pill text-bg-light border"><?php echo esc_html((string) ($chip['label'] ?? '') . ': ' . (string) ($chip['value'] ?? '')); ?></span>
                    <?php endforeach; ?>
                    <a href="<?php echo esc_url($archive_url); ?>" class="btn btn-sm btn-link text-decoration-none px-0"><?php echo esc_html__('Reset Filter', 'velocity-marketplace'); ?></a>
                </div>
            <?php endif; ?>

            <?php if (have_posts()) : ?>
                <div class="row g-3">
<?php while (have_posts()) : the_post(); ?>
    <?php $item = ProductData::map_post(get_the_ID()); ?>
    <?php if (!$item) : continue; endif; ?>
    <?php
    $card_extra_html = '';
    if (!empty($item['seller_city'])) {
        $card_extra_html .= '<div class="small text-muted mb-1">' . esc_html((string) $item['seller_city']) . '</div>';
    }
    if (!empty($item['sold_count'])) {
        $card_extra_html .= '<div class="small text-muted mb-1">' . esc_html(sprintf(__('%d terjual', 'velocity-marketplace'), (int) $item['sold_count'])) . '</div>';
    }
    if (!empty($item['rating_html'])) {
        $card_extra_html .= '<div class="mb-1">' . $item['rating_html'] . '</div>';
    } else {
        $card_extra_html .= '<div class="small text-muted mb-1">' . esc_html__('Belum ada ulasan', 'velocity-marketplace') . '</div>';
    }
    ?>
    <div class="col-6 col-md-4 col-xxl-3">
        <?php
        echo \WpStore\Frontend\Template::render('components/product-card', [
            'item' => [
                'id' => (int) $item['id'],
                'title' => (string) $item['title'],
                'link' => (string) $item['link'],
                'image' => (string) ($item['image'] ?? ''),
                'price' => $item['price'] ?? null,
                'stock' => $item['stock'] ?? null,
            ],
            'currency' => Settings::currency_symbol(),
            'view_label' => __('Detail', 'velocity-marketplace'),
            'extra_html' => $card_extra_html,
            'card_class' => 'vmp-product-card',
        ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </div>
<?php endwhile; ?>
                </div>

                <div class="mt-4">
                    <?php
                    echo wp_kses_post(paginate_links([
                        'prev_text' => __('Previous', 'velocity-marketplace'),
                        'next_text' => __('Next', 'velocity-marketplace'),
                        'add_args' => $current_args,
                        'type' => 'list',
                    ]));
                    ?>
                </div>
            <?php else : ?>
                <div class="alert alert-info mb-0"><?php echo esc_html__('Belum ada produk yang tersedia saat ini.', 'velocity-marketplace'); ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-start vmp-archive-filter-canvas" tabindex="-1" id="vmpArchiveFilterCanvas" aria-labelledby="vmpArchiveFilterCanvasLabel">
    <div class="offcanvas-header">
        <div>
            <h2 class="offcanvas-title h5 mb-0" id="vmpArchiveFilterCanvasLabel"><?php echo esc_html__('Filter Produk', 'velocity-marketplace'); ?></h2>
            <div class="small text-muted"><?php echo esc_html(sprintf(_n('%d produk ditemukan', '%d produk ditemukan', (int) $wp_query->found_posts, 'velocity-marketplace'), (int) $wp_query->found_posts)); ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo esc_attr__('Close', 'velocity-marketplace'); ?>"></button>
    </div>
    <div class="offcanvas-body">
        <?php echo \VelocityMarketplace\Frontend\Template::render('product-filter-form', [
            'filters' => $filters,
            'categories' => $categories,
            'label_options' => $label_options,
            'sort_options' => $sort_options,
            'action_url' => $archive_url,
            'form_class' => 'vmp-archive-filter-form--mobile',
        ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</div>
<?php get_footer(); ?>
