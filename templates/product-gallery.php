<?php
use VelocityMarketplace\Modules\Product\ProductData;

$product_id = isset($product_id) ? (int) $product_id : 0;
$item = ProductData::map_post($product_id);

if (!$item) {
    return;
}

$main_image = (string) ($item['image'] ?? '');
$gallery = is_array($item['gallery']) ? $item['gallery'] : [];
if ($main_image !== '' && !in_array($main_image, $gallery, true)) {
    array_unshift($gallery, $main_image);
}
if (empty($gallery) && $main_image !== '') {
    $gallery[] = $main_image;
}
?>
<div class="vmp-product-gallery" data-gallery-title="<?php echo esc_attr((string) ($item['title'] ?? '')); ?>">
    <div class="card border-0 shadow-sm overflow-hidden">
        <?php if (!empty($gallery)) : ?>
            <button type="button" class="vmp-gallery-stage" data-gallery-open aria-label="<?php echo esc_attr__('Lihat gambar penuh', 'velocity-marketplace'); ?>">
                <img
                    src="<?php echo esc_url((string) $gallery[0]); ?>"
                    alt="<?php echo esc_attr((string) ($item['title'] ?? '')); ?>"
                    class="vmp-single-image"
                    data-gallery-main
                >
            </button>
        <?php else : ?>
            <div class="vmp-single-image vmp-single-image--empty d-flex align-items-center justify-content-center text-muted"><?php echo esc_html__('Tidak ada gambar', 'velocity-marketplace'); ?></div>
        <?php endif; ?>
    </div>

    <?php if (count($gallery) > 1) : ?>
        <div class="vmp-gallery-thumbs-wrap mt-3">
            <button type="button" class="vmp-gallery-arrow vmp-gallery-arrow--prev" data-gallery-prev aria-label="<?php echo esc_attr__('Thumbnail sebelumnya', 'velocity-marketplace'); ?>">
                <span aria-hidden="true">&#8249;</span>
            </button>
            <div class="vmp-gallery-thumbs" data-gallery-track>
                <?php foreach ($gallery as $index => $image_url) : ?>
                    <button
                        type="button"
                        class="vmp-gallery-thumb<?php echo $index === 0 ? ' is-active' : ''; ?>"
                        data-gallery-thumb
                        data-index="<?php echo esc_attr((string) $index); ?>"
                        data-image="<?php echo esc_url((string) $image_url); ?>"
                        aria-label="<?php echo esc_attr(sprintf(__('%1$s image %2$d', 'velocity-marketplace'), (string) ($item['title'] ?? ''), ($index + 1))); ?>"
                    >
                        <img src="<?php echo esc_url((string) $image_url); ?>" alt="<?php echo esc_attr((string) ($item['title'] ?? '')); ?>" class="vmp-single-thumb">
                    </button>
                <?php endforeach; ?>
            </div>
            <button type="button" class="vmp-gallery-arrow vmp-gallery-arrow--next" data-gallery-next aria-label="<?php echo esc_attr__('Thumbnail berikutnya', 'velocity-marketplace'); ?>">
                <span aria-hidden="true">&#8250;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="d-none" data-gallery-links>
        <?php foreach ($gallery as $index => $image_url) : ?>
            <a href="<?php echo esc_url((string) $image_url); ?>" data-gallery-link data-index="<?php echo esc_attr((string) $index); ?>">
                <?php echo esc_html((string) ($item['title'] ?? '') . ' ' . ($index + 1)); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
