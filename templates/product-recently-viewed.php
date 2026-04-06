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
            <?php
            $card_extra_html = '';
            if (!empty($item['seller_city'])) {
                $card_extra_html .= '<div class="small text-muted mb-1">' . esc_html((string) $item['seller_city']) . '</div>';
            }
            if (!empty($item['sold_count'])) {
                $card_extra_html .= '<div class="small text-muted mb-1">' . esc_html(sprintf(__('%d terjual', 'velocity-marketplace'), (int) $item['sold_count'])) . '</div>';
            }
            if (!empty($item['rating_html'])) {
                $card_extra_html .= '<div class="mb-1">' . $item['rating_html'] . '</div>';
            } else {
                $card_extra_html .= '<div class="small text-muted mb-1">' . esc_html__('Belum ada ulasan', 'velocity-marketplace') . '</div>';
            }
            ?>
            <div class="col-6 col-md-4 col-lg-3">
                <?php
                echo \WpStore\Frontend\Template::render('components/product-card', [
                    'item' => [
                        'id' => (int) $item['id'],
                        'title' => (string) ($item['title'] ?? ''),
                        'link' => (string) ($item['link'] ?? '#'),
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
        <?php endforeach; ?>
    </div>
</div>
