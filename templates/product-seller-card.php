<?php
use VelocityMarketplace\Modules\Review\RatingRenderer;
use VelocityMarketplace\Modules\Review\StarSellerService;
use VelocityMarketplace\Support\Settings;

$product_id = isset($product_id) ? (int) $product_id : 0;
if ($product_id <= 0 || get_post_type($product_id) !== 'vmp_product') {
    return;
}

$seller_id = (int) get_post_field('post_author', $product_id);
if ($seller_id <= 0) {
    return;
}

$seller_user = get_userdata($seller_id);
$seller_store_name = (string) get_user_meta($seller_id, 'vmp_store_name', true);
$seller_city = (string) get_user_meta($seller_id, 'vmp_store_city', true);
$seller_last_active_at = (string) get_user_meta($seller_id, 'vmp_last_active_at', true);
$seller_last_active_text = '';
if ($seller_last_active_at !== '') {
    $seller_last_active_ts = strtotime($seller_last_active_at);
    if ($seller_last_active_ts) {
        $seller_last_active_text = sprintf(__('%s yang lalu', 'velocity-marketplace'), human_time_diff($seller_last_active_ts, current_time('timestamp')));
    }
}
$seller_name = $seller_store_name !== ''
    ? $seller_store_name
    : ($seller_user && $seller_user->display_name !== '' ? $seller_user->display_name : ($seller_user ? $seller_user->user_login : __('Penjual', 'velocity-marketplace')));
$seller_summary = (new StarSellerService())->summary($seller_id);
$seller_rating_average = isset($seller_summary['rating_average']) ? (float) $seller_summary['rating_average'] : 0.0;
$store_profile_url = Settings::store_profile_url($seller_id);
$message_url = is_user_logged_in()
    ? add_query_arg(['tab' => 'messages', 'message_to' => $seller_id], Settings::profile_url())
    : wp_login_url(add_query_arg(['tab' => 'messages', 'message_to' => $seller_id], Settings::profile_url()));
?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="small text-muted mb-1"><?php echo esc_html__('Toko', 'velocity-marketplace'); ?></div>
        <div class="fw-semibold mb-1"><?php echo esc_html($seller_name); ?></div>
        <?php if ($seller_city !== '') : ?>
            <div class="small text-muted mb-2"><?php echo esc_html($seller_city); ?></div>
        <?php endif; ?>
        <?php if ($seller_last_active_text !== '') : ?>
            <div class="small text-muted mb-2"><?php echo esc_html(sprintf(__('Terakhir aktif %s', 'velocity-marketplace'), $seller_last_active_text)); ?></div>
        <?php endif; ?>
        <div class="small text-muted mb-3">
            <?php if (!empty($seller_summary['is_star_seller'])) : ?>
                <span class="badge bg-warning text-dark me-1"><?php echo esc_html__('Star Seller', 'velocity-marketplace'); ?></span>
            <?php endif; ?>
            <?php echo RatingRenderer::summary_html($seller_rating_average, null, ['size' => 14, 'show_count' => false, 'show_value' => false, 'class' => 'me-1']); ?>
            <?php echo esc_html(number_format($seller_rating_average, 1, ',', '.')); ?>/5
            <?php echo esc_html(sprintf(__(' dari %d ulasan', 'velocity-marketplace'), (int) ($seller_summary['rating_count'] ?? 0))); ?>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo esc_url($store_profile_url); ?>" class="btn btn-outline-dark btn-sm"><?php echo esc_html__('Kunjungi Toko', 'velocity-marketplace'); ?></a>
            <a href="<?php echo esc_url($message_url); ?>" class="btn btn-dark btn-sm"><?php echo esc_html__('Hubungi Toko', 'velocity-marketplace'); ?></a>
        </div>
    </div>
</div>
