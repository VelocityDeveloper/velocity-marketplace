<?php
use VelocityMarketplace\Modules\Review\RatingRenderer;
use VelocityMarketplace\Modules\Review\ReviewRepository;

$product_id = isset($product_id) ? (int) $product_id : 0;
$limit = isset($limit) ? max(1, min(100, (int) $limit)) : 20;

if ($product_id <= 0 || get_post_type($product_id) !== 'store_product') {
    return;
}

$review_repo = new ReviewRepository();
$product_reviews = $review_repo->product_reviews($product_id, $limit);
$review_summary = $review_repo->product_summary($product_id);
$rating_average = isset($review_summary['rating_average']) ? (float) $review_summary['rating_average'] : 0.0;
$review_count = isset($review_summary['review_count']) ? (int) $review_summary['review_count'] : 0;
?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1"><?php echo esc_html__('Ulasan Produk', 'velocity-marketplace'); ?></h2>
                <div class="small text-muted"><?php echo esc_html__('Ulasan hanya dapat dikirim dari pesanan yang sudah selesai.', 'velocity-marketplace'); ?></div>
            </div>
            <div class="text-end">
                <?php echo RatingRenderer::summary_html($rating_average, null, ['size' => 16, 'show_count' => false, 'show_value' => false]); ?>
                <div class="small text-muted"><?php echo esc_html(number_format($rating_average, 1, ',', '.') . '/5 ' . sprintf(__('dari %d ulasan', 'velocity-marketplace'), $review_count)); ?></div>
            </div>
        </div>
        <?php if (empty($product_reviews)) : ?>
            <div class="text-muted small"><?php echo esc_html__('Belum ada ulasan untuk produk ini.', 'velocity-marketplace'); ?></div>
        <?php else : ?>
            <div class="vmp-review-list">
                <?php foreach ($product_reviews as $review) : ?>
                    <?php $item_rating = max(0, min(5, (int) ($review['rating'] ?? 0))); ?>
                    <div class="vmp-review-item">
                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <div>
                                <div class="fw-semibold"><?php echo esc_html((string) ($review['user_name'] ?? __('Member', 'velocity-marketplace'))); ?></div>
                                <?php echo RatingRenderer::summary_html($item_rating, null, ['size' => 16, 'show_count' => false, 'show_value' => false, 'class' => 'small']); ?>
                            </div>
                            <div class="small text-muted"><?php echo esc_html(mysql2date('d-m-Y H:i', (string) ($review['created_at'] ?? ''))); ?></div>
                        </div>
                        <?php if (!empty($review['title'])) : ?>
                            <div class="fw-semibold mt-2"><?php echo esc_html((string) $review['title']); ?></div>
                        <?php endif; ?>
                        <div class="mt-1 text-muted"><?php echo nl2br(esc_html((string) ($review['content'] ?? ''))); ?></div>
                        <?php if (!empty($review['image_urls']) && is_array($review['image_urls'])) : ?>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <?php foreach ($review['image_urls'] as $review_image_url) : ?>
                                    <a href="<?php echo esc_url((string) $review_image_url); ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                        <img src="<?php echo esc_url((string) $review_image_url); ?>" alt="<?php echo esc_attr__('Foto ulasan', 'velocity-marketplace'); ?>" class="border rounded" style="width:88px; height:88px; object-fit:cover;">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
