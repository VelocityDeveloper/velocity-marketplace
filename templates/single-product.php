<?php
use VelocityMarketplace\Modules\Product\ProductData;
use VelocityMarketplace\Modules\Product\RecentlyViewed;
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

$categories = get_the_terms($product_id, 'store_product_cat');
$seller_id = (int) get_post_field('post_author', $product_id);
$rating_average = isset($item['rating_average']) ? (float) $item['rating_average'] : 0.0;
$review_count = isset($item['review_count']) ? (int) $item['review_count'] : 0;
$product_permalink = get_permalink($product_id);
$product_share_url = $product_permalink ? rawurlencode($product_permalink) : '';
$product_share_title = rawurlencode((string) $item['title']);

RecentlyViewed::track($product_id);

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
            <div class="mb-3"><?php echo do_shortcode('[wp_store_price id="' . (int) $item['id'] . '"]'); ?></div>

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

            <?php if ($product_permalink) : ?>
                <div class="vmp-share-links mb-4">
                    <div class="small text-muted mb-2"><?php echo esc_html__('Bagikan Produk', 'velocity-marketplace'); ?></div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?php echo esc_url('https://wa.me/?text=' . $product_share_title . '%20' . $product_share_url); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-whatsapp" viewBox="0 0 16 16" aria-hidden="true"><path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232"/></svg>
                            <span class="ms-1"><?php echo esc_html__('WhatsApp', 'velocity-marketplace'); ?></span>
                        </a>
                        <a href="<?php echo esc_url('https://www.facebook.com/sharer/sharer.php?u=' . $product_share_url); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-facebook" viewBox="0 0 16 16" aria-hidden="true"><path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951"/></svg>
                            <span class="ms-1"><?php echo esc_html__('Facebook', 'velocity-marketplace'); ?></span>
                        </a>
                        <a href="<?php echo esc_url('https://twitter.com/intent/tweet?url=' . $product_share_url . '&text=' . $product_share_title); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-twitter-x" viewBox="0 0 16 16" aria-hidden="true"><path d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.601.75Zm-.86 13.028h1.36L4.323 2.145H2.865z"/></svg>
                            <span class="ms-1">X</span>
                        </a>
                        <a href="<?php echo esc_url('https://t.me/share/url?url=' . $product_share_url . '&text=' . $product_share_title); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telegram" viewBox="0 0 16 16" aria-hidden="true"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M8.287 5.906q-1.168.486-4.666 2.01-.567.225-.595.442c-.03.243.275.339.69.47l.175.055c.408.133.958.288 1.243.294q.39.01.868-.32 3.269-2.206 3.374-2.23c.05-.012.12-.026.166.016s.042.12.037.141c-.03.129-1.227 1.241-1.846 1.817-.193.18-.33.307-.358.336a8 8 0 0 1-.188.186c-.38.366-.664.64.015 1.088.327.216.589.393.85.571.284.194.568.387.936.629q.14.092.27.187c.331.236.63.448.997.414.214-.02.435-.22.547-.82.265-1.417.786-4.486.906-5.751a1.4 1.4 0 0 0-.013-.315.34.34 0 0 0-.114-.217.53.53 0 0 0-.31-.093c-.3.005-.763.166-2.984 1.09"/></svg>
                            <span class="ms-1"><?php echo esc_html__('Telegram', 'velocity-marketplace'); ?></span>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-vmp-copy-text="<?php echo esc_attr($product_permalink); ?>" data-vmp-copy-success="<?php echo esc_attr__('Tautan Tersalin', 'velocity-marketplace'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-link-45deg" viewBox="0 0 16 16" aria-hidden="true"><path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z"/><path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243z"/></svg>
                            <span class="ms-1"><?php echo esc_html__('Salin Link', 'velocity-marketplace'); ?></span>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($seller_id > 0) : ?>
                <?php echo \VelocityMarketplace\Frontend\Template::render('product-seller-card', ['product_id' => $product_id]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $product_content = apply_filters('the_content', (string) get_post_field('post_content', $product_id));
    if (trim(wp_strip_all_tags($product_content)) !== '') :
    ?>
        <div class="mt-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3"><?php echo esc_html__('Deskripsi Produk', 'velocity-marketplace'); ?></h2>
                    <div class="entry-content"><?php echo $product_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-4">
        <?php echo \VelocityMarketplace\Frontend\Template::render('product-reviews', ['product_id' => $product_id, 'limit' => 20]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>

    <div class="mt-4">
        <?php echo do_shortcode('[wp_store_related per_page="4"]'); ?>
    </div>

</div>
<?php get_footer(); ?>
