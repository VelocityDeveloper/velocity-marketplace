<?php

use VelocityMarketplace\Modules\Product\ProductData;

if (empty($wishlist_ids)) :
    ?>
    <div class="alert alert-info mb-0"><?php echo esc_html__('Belum ada produk yang ditambahkan ke wishlist. Simpan produk dari katalog untuk melihatnya di sini.', 'velocity-marketplace'); ?></div>
<?php
else :
    $wishlist_query = new \WP_Query([
        'post_type' => 'vmp_product',
        'post_status' => 'publish',
        'posts_per_page' => 100,
        'post__in' => $wishlist_ids,
        'orderby' => 'post__in',
    ]);
    ?>
    <div class="row g-3">
        <?php while ($wishlist_query->have_posts()) : $wishlist_query->the_post(); ?>
            <?php $item = ProductData::map_post(get_the_ID()); ?>
            <?php if (!$item) : continue; endif; ?>
            <div class="col-6 col-md-4 col-xl-3">
                <div class="card h-100 shadow-sm border-0 vmp-product-card">
                    <?php echo do_shortcode('[vmp_thumbnail id="' . (int) $item['id'] . '"]'); ?>
                    <div class="card-body d-flex flex-column">
                        <h3 class="card-title h6 mb-1">
                            <a href="<?php echo esc_url($item['link']); ?>" class="text-decoration-none text-dark">
                                <?php echo esc_html($item['title']); ?>
                            </a>
                        </h3>

                        <?php if (!empty($item['label'])) : ?>
                            <div class="small text-muted mb-2"><?php echo esc_html((string) $item['label']); ?></div>
                        <?php endif; ?>

                        <?php echo do_shortcode('[vmp_price id="' . (int) $item['id'] . '"]'); ?>

                        <?php if (!empty($item['seller_city'])) : ?>
                            <div class="small text-muted mb-1"><?php echo esc_html((string) $item['seller_city']); ?></div>
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

                        <div class="mt-auto d-flex gap-2">
                            <?php echo do_shortcode('[vmp_add_to_cart id="' . (int) $item['id'] . '" class="btn btn-sm btn-dark flex-grow-1"]'); ?>
                            <?php echo do_shortcode('[vmp_add_to_wishlist id="' . (int) $item['id'] . '" class="btn btn-sm btn-outline-secondary vmp-wishlist-button"]'); ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
    <?php wp_reset_postdata(); ?>
<?php endif; ?>
