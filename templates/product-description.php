<?php
$product_id = isset($product_id) ? (int) $product_id : 0;
if ($product_id <= 0 || get_post_type($product_id) !== 'vmp_product') {
    return;
}

$content = get_post_field('post_content', $product_id);
?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3"><?php echo esc_html__('Deskripsi Produk', 'velocity-marketplace'); ?></h2>
        <div class="vmp-content">
            <?php echo wp_kses_post(apply_filters('the_content', $content)); ?>
        </div>
    </div>
</div>
