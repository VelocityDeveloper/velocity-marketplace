<?php
use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Modules\Review\ReviewRepository;
use VelocityMarketplace\Modules\Shipping\ShippingController;

$order_id = isset($order_id) ? (int) $order_id : 0;
$current_user_id = isset($current_user_id) ? (int) $current_user_id : get_current_user_id();
$currency = isset($currency) ? (string) $currency : 'Rp';

if ($order_id <= 0 || get_post_type($order_id) !== 'store_order') {
    return;
}

$tracking_status = (string) get_post_meta($order_id, 'vmp_status', true);
$tracking_groups = OrderData::shipping_groups($order_id);
$tracking_invoice = (string) get_post_meta($order_id, 'vmp_invoice', true);
$tracking_owner_id = OrderData::buyer_id($order_id);
$is_tracking_buyer = $tracking_owner_id === $current_user_id;
$tracking_review_map = $is_tracking_buyer ? (new ReviewRepository())->reviews_for_order_user($order_id, $current_user_id) : [];

if (empty($tracking_groups) || !is_array($tracking_groups)) {
    return;
}
?>
<div class="wps-divider wps-mt-6 wps-mb-4"></div>
<div class="wps-text-lg wps-font-medium wps-text-gray-900">Pengiriman per Toko</div>
<div class="vmp-order-groups" style="margin-top:16px;">
    <?php foreach ($tracking_groups as $group) : ?>
        <?php
        $group_status = OrderData::shipping_group_status($group, $tracking_status);
        $group_badge_class = OrderData::status_badge_class($group_status);
        $group_items = isset($group['items']) && is_array($group['items'])
            ? array_values($group['items'])
            : OrderData::seller_items($order_id, (int) ($group['seller_id'] ?? 0));
        $group_subtotal = isset($group['subtotal']) ? (float) $group['subtotal'] : 0.0;
        if ($group_subtotal <= 0) {
            foreach ($group_items as $group_item) {
                $group_subtotal += isset($group_item['subtotal']) ? (float) $group_item['subtotal'] : ((float) ($group_item['price'] ?? 0) * (int) ($group_item['qty'] ?? 0));
            }
        }
        $group_has_pending_review = false;
        $group_has_reviewable_items = !empty($group_items);
        foreach ($group_items as $group_item) {
            $group_product_id = (int) ($group_item['product_id'] ?? 0);
            if ($group_product_id > 0 && !isset($tracking_review_map[$group_product_id])) {
                $group_has_pending_review = true;
                break;
            }
        }
        $group_shipping_cost = (float) ($group['cost'] ?? 0);
        $group_received_at = (string) ($group['received_at'] ?? '');
        $group_timeline_steps = OrderData::timeline_steps($group_status, $group_received_at);
        $receipt = (string) ($group['receipt_no'] ?? '');
        $courier = (string) ($group['receipt_courier'] ?? ($group['courier'] ?? ''));
        $waybill = null;
        if ($receipt !== '' && $courier !== '') {
            $maybe_waybill = ShippingController::fetch_waybill($receipt, $courier);
            if (!is_wp_error($maybe_waybill) && is_array($maybe_waybill)) {
                $waybill = $maybe_waybill;
            }
        }
        $tracking_rows = [];
        if ($waybill && isset($waybill['data']['manifest']) && is_array($waybill['data']['manifest'])) {
            $tracking_rows = $waybill['data']['manifest'];
        } elseif ($waybill && isset($waybill['data']['history']) && is_array($waybill['data']['history'])) {
            $tracking_rows = $waybill['data']['history'];
        }
        ?>
        <div class="vmp-order-group card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <div class="fw-semibold"><?php echo esc_html((string) ($group['seller_name'] ?? __('Toko', 'velocity-marketplace'))); ?></div>
                        <div class="small text-muted mt-1"><strong><?php echo esc_html__('Kurir:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($courier !== '' ? $courier : '-'); ?></div>
                        <div class="small text-muted"><strong><?php echo esc_html__('Layanan:', 'velocity-marketplace'); ?></strong> <?php echo esc_html((string) ($group['service'] ?? '-')); ?></div>
                        <div class="small text-muted d-flex flex-wrap align-items-center gap-2"><strong><?php echo esc_html__('Nomor Resi:', 'velocity-marketplace'); ?></strong> <span><?php echo esc_html($receipt !== '' ? $receipt : '-'); ?></span><?php if ($receipt !== '') : ?><button type="button" class="btn btn-sm btn-outline-secondary" data-vmp-copy-text="<?php echo esc_attr($receipt); ?>" data-vmp-copy-success="<?php echo esc_attr__('Resi Tersalin', 'velocity-marketplace'); ?>"><?php echo esc_html__('Salin Resi', 'velocity-marketplace'); ?></button><?php endif; ?></div>
                    </div>
                    <div class="vmp-order-group__actions">
                        <span class="badge rounded-pill bg-<?php echo esc_attr($group_badge_class); ?> vmp-order-status-badge"><?php echo esc_html(OrderData::status_label($group_status)); ?></span>
                        <?php if ($group_status === 'completed' && $group_has_reviewable_items && !$group_has_pending_review) : ?>
                            <span class="badge rounded-pill text-success border border-success-subtle bg-success-subtle vmp-order-review-badge"><?php echo esc_html__('Sudah Dinilai', 'velocity-marketplace'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($group_timeline_steps)) : ?>
                    <div class="mt-3 pt-3 border-top">
                        <?php echo \VelocityMarketplace\Frontend\Template::render('order-timeline', ['steps' => $group_timeline_steps]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>
                <div class="vmp-order-group__lines">
                    <?php foreach ($group_items as $group_item) : ?>
                        <?php
                        $line_product_id = (int) ($group_item['product_id'] ?? 0);
                        $line_price = (float) ($group_item['price'] ?? 0);
                        $line_qty = (int) ($group_item['qty'] ?? 0);
                        $line_subtotal = isset($group_item['subtotal']) ? (float) $group_item['subtotal'] : ($line_price * $line_qty);
                        $line_thumb = $line_product_id > 0 ? get_the_post_thumbnail_url($line_product_id, 'thumbnail') : '';
                        $line_options = [];
                        if (!empty($group_item['options']) && is_array($group_item['options'])) {
                            foreach ($group_item['options'] as $option_label => $option_value) {
                                $option_label = is_string($option_label) ? trim($option_label) : '';
                                $option_value = is_scalar($option_value) ? trim((string) $option_value) : '';
                                if ($option_value === '') {
                                    continue;
                                }
                                $line_options[] = $option_label !== '' ? ($option_label . ': ' . $option_value) : $option_value;
                            }
                        }
                        ?>
                        <div class="vmp-order-group__line">
                            <div class="vmp-order-group__line-main">
                                <div class="vmp-order-group__thumb-wrap">
                                    <?php if ($line_thumb !== '') : ?>
                                        <img src="<?php echo esc_url($line_thumb); ?>" alt="<?php echo esc_attr((string) ($group_item['title'] ?? '-')); ?>" class="vmp-order-group__thumb">
                                    <?php else : ?>
                                        <div class="vmp-order-group__thumb vmp-order-group__thumb--empty"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="vmp-order-group__line-copy">
                                    <?php $line_permalink = $line_product_id > 0 ? get_permalink($line_product_id) : ''; ?>
                                    <?php if ($line_permalink) : ?>
                                        <a href="<?php echo esc_url($line_permalink); ?>" class="fw-semibold text-decoration-none text-body"><?php echo esc_html((string) ($group_item['title'] ?? '-')); ?></a>
                                    <?php else : ?>
                                        <div class="fw-semibold"><?php echo esc_html((string) ($group_item['title'] ?? '-')); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($line_options)) : ?><div class="small text-muted"><?php echo esc_html(implode(' • ', $line_options)); ?></div><?php endif; ?>
                                    <div class="small text-muted"><?php echo esc_html(sprintf(__('%1$s %2$s x %3$d', 'velocity-marketplace'), $currency, number_format($line_price, 0, ',', '.'), $line_qty)); ?></div>
                                </div>
                            </div>
                            <div class="text-end fw-semibold"><?php echo esc_html(sprintf(__('%1$s %2$s', 'velocity-marketplace'), $currency, number_format($line_subtotal, 0, ',', '.'))); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="vmp-order-group__summary">
                    <div class="vmp-order-group__summary-row">
                        <span><?php echo esc_html__('Subtotal Produk', 'velocity-marketplace'); ?></span>
                        <strong><?php echo esc_html(sprintf(__('%1$s %2$s', 'velocity-marketplace'), $currency, number_format($group_subtotal, 0, ',', '.'))); ?></strong>
                    </div>
                    <div class="vmp-order-group__summary-row">
                        <span><?php echo esc_html__('Ongkir', 'velocity-marketplace'); ?></span>
                        <strong><?php echo esc_html(sprintf(__('%1$s %2$s', 'velocity-marketplace'), $currency, number_format($group_shipping_cost, 0, ',', '.'))); ?></strong>
                    </div>
                    <div class="vmp-order-group__summary-row vmp-order-group__summary-row--total">
                        <span><?php echo esc_html__('Total Toko', 'velocity-marketplace'); ?></span>
                        <strong><?php echo esc_html(sprintf(__('%1$s %2$s', 'velocity-marketplace'), $currency, number_format($group_subtotal + $group_shipping_cost, 0, ',', '.'))); ?></strong>
                    </div>
                </div>
                <?php if ($is_tracking_buyer && $group_status === 'shipped') : ?>
                    <div class="mt-3 pt-3 border-top text-end">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="vmp_action" value="buyer_confirm_received">
                            <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                            <input type="hidden" name="seller_id" value="<?php echo esc_attr((int) ($group['seller_id'] ?? 0)); ?>">
                            <input type="hidden" name="redirect_tab" value="tracking">
                            <input type="hidden" name="invoice" value="<?php echo esc_attr($tracking_invoice); ?>">
                            <?php wp_nonce_field('vmp_confirm_received_' . $order_id . '_' . (int) ($group['seller_id'] ?? 0), 'vmp_confirm_received_nonce'); ?>
                            <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('<?php echo esc_js(__('Konfirmasi bahwa pesanan dari toko ini sudah diterima?', 'velocity-marketplace')); ?>');">
                                <?php echo esc_html__('Pesanan Diterima', 'velocity-marketplace'); ?>
                            </button>
                        </form>
                    </div>
                <?php elseif ($is_tracking_buyer && $group_status === 'completed' && $group_has_pending_review) : ?>
                    <div class="mt-3 pt-3 border-top text-end">
                        <a href="<?php echo esc_url(add_query_arg(['tab' => 'orders', 'invoice' => $tracking_invoice], \VelocityMarketplace\Support\Settings::profile_url()) . '#vmp-order-reviews'); ?>" class="btn btn-sm btn-outline-primary"><?php echo esc_html__('Berikan Penilaian', 'velocity-marketplace'); ?></a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($tracking_rows)) : ?>
                    <div class="mt-3 pt-3 border-top">
                        <?php foreach ($tracking_rows as $row) : ?>
                            <div class="border-top py-2">
                                <div class="fw-semibold"><?php echo esc_html((string) ($row['manifest_description'] ?? $row['description'] ?? '-')); ?></div>
                                <div class="small text-muted"><?php echo esc_html(trim((string) (($row['manifest_date'] ?? '') . ' ' . ($row['manifest_time'] ?? $row['date'] ?? '')))); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
