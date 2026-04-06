<?php

use VelocityMarketplace\Modules\Product\ProductData;

if (empty($wishlist_ids)) :
    ?>
    <div class="alert alert-info mb-0"><?php echo esc_html__('Belum ada produk yang ditambahkan ke wishlist. Simpan produk dari katalog untuk melihatnya di sini.', 'velocity-marketplace'); ?></div>
<?php
else :
    $wishlist_query = new \WP_Query([
        'post_type' => 'store_product',
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
            <?php
            $card_extra_html = '';
            if (!empty($item['seller_city'])) {
                $card_extra_html .= '<div class="small text-muted mb-1">' . esc_html((string) $item['seller_city']) . '</div>';
            }
            if (!empty($item['rating_html'])) {
                $card_extra_html .= '<div class="mb-1">' . $item['rating_html'] . '</div>';
            }
            ?>
            <div class="col-6 col-md-4 col-xl-3">
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
                    'currency' => \VelocityMarketplace\Support\Settings::currency_symbol(),
                    'view_label' => __('Detail', 'velocity-marketplace'),
                    'extra_html' => $card_extra_html,
                    'card_class' => 'vmp-product-card',
                ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>
        <?php endwhile; ?>
    </div>
    <?php wp_reset_postdata(); ?>
<?php endif; ?>
