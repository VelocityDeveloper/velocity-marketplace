<?php
use VelocityMarketplace\Modules\Product\ProductData;
use VelocityMarketplace\Modules\Review\RatingRenderer;

$product_id = get_the_ID();
$item = ProductData::map_post($product_id);
if (!$item) {
    status_header(404);
    nocache_headers();
    get_header();
    echo '<div class="container py-4 vmp-wrap"><div class="alert alert-warning mb-0">' . esc_html__('Produk tidak ditemukan.', 'velocity-marketplace') . '</div></div>';
    get_footer();
    return;
}

$categories = get_the_terms($product_id, 'vmp_product_cat');
$seller_id = (int) get_post_field('post_author', $product_id);
$rating_average = isset($item['rating_average']) ? (float) $item['rating_average'] : 0.0;
$review_count = isset($item['review_count']) ? (int) $item['review_count'] : 0;

get_header();
?>
<div class="container py-4 vmp-wrap">
    <div class="row g-4">
        <div class="col-lg-6">
            <?php echo \VelocityMarketplace\Frontend\Template::render('product-gallery', ['product_id' => $product_id]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <div class="col-lg-6">
            <div class="mb-2">
                <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
                    <?php foreach ($categories as $term) : ?>
                        <span class="badge bg-light text-dark border"><?php echo esc_html($term->name); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($item['is_premium'])) : ?>
                    <span class="badge bg-warning text-dark"><?php echo esc_html__('Premium', 'velocity-marketplace'); ?></span>
                <?php endif; ?>
            </div>
            <h1 class="h3 mb-2"><?php echo esc_html($item['title']); ?></h1>
            <?php if (!empty($item['label'])) : ?>
                <div class="text-muted mb-2"><?php echo esc_html($item['label']); ?></div>
            <?php endif; ?>
            <div class="d-flex flex-wrap align-items-center gap-2 small text-muted mb-2">
                <?php echo RatingRenderer::summary_html($rating_average, null, ['size' => 16, 'show_count' => false, 'show_value' => false]); ?>
                <span><?php echo esc_html(number_format($rating_average, 1, ',', '.')); ?>/5</span>
                <span><?php echo esc_html(sprintf(__('(%d ulasan)', 'velocity-marketplace'), $review_count)); ?></span>
                <?php if (!empty($item['sold_count'])) : ?>
                    <span>&bull;</span>
                    <span><?php echo esc_html(sprintf(__('%d terjual', 'velocity-marketplace'), (int) $item['sold_count'])); ?></span>
                <?php endif; ?>
            </div>
            <div class="mb-3"><?php echo do_shortcode('[vmp_price id="' . (int) $item['id'] . '" class="h5"]'); ?></div>

            <div class="row g-2 small mb-3">
                <div class="col-sm-6"><strong><?php echo esc_html__('SKU:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($item['sku'] !== '' ? $item['sku'] : '-'); ?></div>
                <div class="col-sm-6"><strong><?php echo esc_html__('Minimal Pesanan:', 'velocity-marketplace'); ?></strong> <?php echo esc_html((string) (int) ($item['min_order'] ?? 1)); ?></div>
                <div class="col-sm-6"><strong><?php echo esc_html__('Berat:', 'velocity-marketplace'); ?></strong> <?php echo esc_html(number_format((float) ($item['weight'] ?? 0), 0, ',', '.')); ?> gr</div>
                <div class="col-sm-6"><strong><?php echo esc_html__('Stok:', 'velocity-marketplace'); ?></strong>
                    <?php
                    if ($item['stock'] === null || $item['stock'] === '') {
                        echo esc_html__('Tidak terbatas', 'velocity-marketplace');
                    } else {
                        echo esc_html((float) $item['stock'] > 0 ? (string) (int) $item['stock'] : __('Stok habis', 'velocity-marketplace'));
                    }
                    ?>
                </div>
            </div>

            <div class="vmp-single-product-actions mb-4">
                <div class="vmp-single-product-actions__primary">
                    <?php echo do_shortcode('[vmp_add_to_cart id="' . (int) $item['id'] . '" text="' . esc_attr__('Tambah Keranjang', 'velocity-marketplace') . '" class="btn btn-dark" style="inline"]'); ?>
                </div>
                <div class="vmp-single-product-actions__wishlist">
                    <?php echo do_shortcode('[vmp_add_to_wishlist id="' . (int) $item['id'] . '" text="' . esc_attr__('Wishlist', 'velocity-marketplace') . '" class="btn btn-outline-secondary py-2 vmp-wishlist-button"]'); ?>
                </div>
            </div>

            <?php if ($seller_id > 0) : ?>
                <?php echo \VelocityMarketplace\Frontend\Template::render('product-seller-card', ['product_id' => $product_id]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-4">
        <?php echo \VelocityMarketplace\Frontend\Template::render('product-description', ['product_id' => $product_id]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>

    <div class="mt-4">
        <?php echo \VelocityMarketplace\Frontend\Template::render('product-reviews', ['product_id' => $product_id, 'limit' => 20]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</div>
<?php get_footer(); ?>
