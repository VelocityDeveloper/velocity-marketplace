<?php
$title = isset($title) && is_string($title) && $title !== '' ? $title : __('Produk yang Baru Dilihat', 'velocity-marketplace');
$items = isset($items) && is_array($items) ? $items : [];

if (empty($items)) {
    return;
}
?>
<div class="vmp-recently-viewed">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <h2 class="h5 mb-0"><?php echo esc_html($title); ?></h2>
        <div class="small text-muted"><?php echo esc_html(sprintf(_n('%d produk', '%d produk', count($items), 'velocity-marketplace'), count($items))); ?></div>
    </div>
    <div class="row g-3">
        <?php foreach ($items as $item) : ?>
            <?php if (!is_array($item) || empty($item['id'])) { continue; } ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card h-100 shadow-sm border-0 vmp-product-card">
                    <?php echo do_shortcode('[vmp_thumbnail id="' . (int) $item['id'] . '"]'); ?>
                    <div class="card-body d-flex flex-column">
                        <h3 class="card-title h6 mb-1"><a href="<?php echo esc_url((string) ($item['link'] ?? '#')); ?>" class="text-decoration-none text-dark"><?php echo esc_html((string) ($item['title'] ?? '')); ?></a></h3>
                        <?php if (!empty($item['label'])) : ?>
                            <div class="small text-muted mb-2"><?php echo esc_html((string) $item['label']); ?></div>
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
                            if (($item['stock'] ?? null) === null || ($item['stock'] ?? '') === '') {
                                echo esc_html__('Stok tidak terbatas', 'velocity-marketplace');
                            } else {
                                echo esc_html((float) ($item['stock'] ?? 0) > 0 ? sprintf(__('Stok: %d', 'velocity-marketplace'), (int) ($item['stock'] ?? 0)) : __('Stok habis', 'velocity-marketplace'));
                            }
                            ?>
                        </div>
                        <?php if (!empty($item['rating_html'])) : ?>
                            <div class="mb-3"><?php echo $item['rating_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <?php else : ?>
                            <div class="small text-muted mb-3"><?php echo esc_html__('Belum ada ulasan', 'velocity-marketplace'); ?></div>
                        <?php endif; ?>
                        <div class="mt-auto d-flex gap-2 vmp-product-card__actions">
                            <?php echo do_shortcode('[vmp_add_to_cart id="' . (int) $item['id'] . '" class="btn btn-sm btn-dark flex-grow-1"]'); ?>
                            <?php echo do_shortcode('[vmp_add_to_wishlist id="' . (int) $item['id'] . '" class="btn btn-sm btn-outline-secondary vmp-wishlist-button"]'); ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
