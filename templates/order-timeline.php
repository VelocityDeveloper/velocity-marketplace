<?php
$steps = isset($steps) && is_array($steps) ? array_values($steps) : [];
if (empty($steps)) {
    return;
}
?>
<div class="vmp-order-timeline" aria-label="<?php echo esc_attr__('Timeline pesanan', 'velocity-marketplace'); ?>">
    <?php foreach ($steps as $index => $step) : ?>
        <?php
        $item_classes = ['vmp-order-timeline__item'];
        if (!empty($step['is_complete'])) {
            $item_classes[] = 'is-complete';
        } elseif (!empty($step['is_current'])) {
            $item_classes[] = 'is-current';
        } else {
            $item_classes[] = 'is-pending';
        }
        ?>
        <div class="<?php echo esc_attr(implode(' ', $item_classes)); ?>">
            <div class="vmp-order-timeline__dot" aria-hidden="true"></div>
            <?php if ($index < count($steps) - 1) : ?>
                <div class="vmp-order-timeline__line" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="vmp-order-timeline__content">
                <div class="vmp-order-timeline__label"><?php echo esc_html((string) ($step['label'] ?? '')); ?></div>
                <?php if (!empty($step['description'])) : ?>
                    <div class="vmp-order-timeline__description"><?php echo esc_html((string) $step['description']); ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
