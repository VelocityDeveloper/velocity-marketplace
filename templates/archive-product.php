<?php
use VelocityMarketplace\Modules\Product\ProductData;
use VelocityMarketplace\Modules\Product\ProductQuery;

$product_query = new ProductQuery();
$filters = $product_query->normalize_filters($_GET);
$categories = get_terms([
    'taxonomy' => 'vmp_product_cat',
    'hide_empty' => false,
]);
$label_options = $product_query->label_options();
$archive_url = get_post_type_archive_link('vmp_product');
$pages = get_option(VMP_PAGES_OPTION, []);
$catalog_url = site_url('/catalog/');
if (is_array($pages) && !empty($pages['katalog'])) {
    $maybe_catalog_url = get_permalink((int) $pages['katalog']);
    if ($maybe_catalog_url) {
        $catalog_url = $maybe_catalog_url;
    }
}
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
            <div class="text-muted"><?php echo esc_html__('Browse products available in the marketplace.', 'velocity-marketplace'); ?></div>
        </div>
        <a href="<?php echo esc_url($catalog_url); ?>" class="btn btn-sm btn-outline-dark"><?php echo esc_html__('Buka Katalog Interaktif', 'velocity-marketplace'); ?></a>
    </div>

    <div class="d-flex d-lg-none justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">
            <?php echo esc_html(sprintf(_n('%d product found', '%d products found', (int) $wp_query->found_posts, 'velocity-marketplace'), (int) $wp_query->found_posts)); ?>
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
                        'action_url' => $archive_url,
                    ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        </aside>

        <div class="col-12 col-lg-9">
            <div class="d-none d-lg-flex justify-content-between align-items-center gap-3 mb-3">
                <div class="small text-muted"><?php echo esc_html(sprintf(_n('%d product found', '%d products found', (int) $wp_query->found_posts, 'velocity-marketplace'), (int) $wp_query->found_posts)); ?></div>
            </div>

            <?php if (have_posts()) : ?>
                <div class="row g-3">
                    <?php while (have_posts()) : the_post(); ?>
                        <?php $item = ProductData::map_post(get_the_ID()); ?>
                        <?php if (!$item) : continue; endif; ?>
                        <div class="col-6 col-md-4 col-xxl-3">
                            <div class="card h-100 shadow-sm border-0 vmp-product-card">
                                <?php echo do_shortcode('[vmp_thumbnail id="' . (int) $item['id'] . '"]'); ?>
                                <div class="card-body d-flex flex-column">
                                    <h2 class="card-title h6 mb-1"><a href="<?php echo esc_url($item['link']); ?>" class="text-decoration-none text-dark"><?php echo esc_html($item['title']); ?></a></h2>
                                    <?php if (!empty($item['label'])) : ?>
                                        <div class="small text-muted mb-2"><?php echo esc_html($item['label']); ?></div>
                                    <?php endif; ?>
                                    <?php echo do_shortcode('[vmp_price id="' . (int) $item['id'] . '"]'); ?>
                                    <?php if (!empty($item['seller_city'])) : ?>
                                        <div class="small text-muted mb-1"><?php echo esc_html((string) $item['seller_city']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['sold_count'])) : ?>
                                        <div class="small text-muted mb-1"><?php echo esc_html(sprintf(__('%d terjual', 'velocity-marketplace'), (int) $item['sold_count'])); ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted mb-3">
                                        <?php
                                        if ($item['stock'] === null || $item['stock'] === '') {
                                            echo esc_html__('Stok tidak terbatas', 'velocity-marketplace');
                                        } else {
                                            echo esc_html((float) $item['stock'] > 0 ? sprintf(__('Stok: %d', 'velocity-marketplace'), (int) $item['stock']) : __('Stok habis', 'velocity-marketplace'));
                                        }
                                        ?>
                                    </div>
                                    <?php if (!empty($item['rating_html'])) : ?>
                                        <div class="mb-3"><?php echo $item['rating_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                    <?php else : ?>
                                        <div class="small text-muted mb-3"><?php echo esc_html__('Belum ada ulasan', 'velocity-marketplace'); ?></div>
                                    <?php endif; ?>
                                    <div class="mt-auto d-flex gap-2">
                                        <?php echo do_shortcode('[vmp_add_to_cart id="' . (int) $item['id'] . '" class="btn btn-sm btn-dark flex-grow-1"]'); ?>
                                        <?php echo do_shortcode('[vmp_add_to_wishlist id="' . (int) $item['id'] . '" class="btn btn-sm btn-outline-secondary vmp-wishlist-button"]'); ?>
                                    </div>
                                </div>
                            </div>
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
                <div class="alert alert-info mb-0"><?php echo esc_html__('No products are available right now.', 'velocity-marketplace'); ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-start vmp-archive-filter-canvas" tabindex="-1" id="vmpArchiveFilterCanvas" aria-labelledby="vmpArchiveFilterCanvasLabel">
    <div class="offcanvas-header">
        <div>
            <h2 class="offcanvas-title h5 mb-0" id="vmpArchiveFilterCanvasLabel"><?php echo esc_html__('Filter Produk', 'velocity-marketplace'); ?></h2>
            <div class="small text-muted"><?php echo esc_html(sprintf(_n('%d product found', '%d products found', (int) $wp_query->found_posts, 'velocity-marketplace'), (int) $wp_query->found_posts)); ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo esc_attr__('Close', 'velocity-marketplace'); ?>"></button>
    </div>
    <div class="offcanvas-body">
        <?php echo \VelocityMarketplace\Frontend\Template::render('product-filter-form', [
            'filters' => $filters,
            'categories' => $categories,
            'label_options' => $label_options,
            'action_url' => $archive_url,
            'form_class' => 'vmp-archive-filter-form--mobile',
        ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</div>
<?php get_footer(); ?>
