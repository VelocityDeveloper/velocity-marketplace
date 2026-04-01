<?php
use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Modules\Shipping\ShippingController;

$invoice = isset($_GET['invoice']) ? sanitize_text_field((string) wp_unslash($_GET['invoice'])) : '';
$order = null;
if ($invoice !== '') {
    $query = new \WP_Query([
        'post_type' => 'vmp_order',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => 'vmp_invoice',
                'value' => $invoice,
                'compare' => '=',
            ],
        ],
    ]);

    if ($query->have_posts()) {
        $query->the_post();
        $order = get_post(get_the_ID());
        wp_reset_postdata();
    }
}
?>
<div class="container py-4 vmp-wrap">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-0"><?php echo esc_html__('Lacak Pesanan', 'velocity-marketplace'); ?></h2>
            <small class="text-muted"><?php echo esc_html__('Masukkan kode invoice untuk melihat status pesanan dan pengiriman.', 'velocity-marketplace'); ?></small>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-md-8">
                    <label class="form-label"><?php echo esc_html__('Kode Invoice', 'velocity-marketplace'); ?></label>
                    <input type="text" name="invoice" class="form-control" value="<?php echo esc_attr($invoice); ?>" placeholder="<?php echo esc_attr__('Contoh: VMP-20260304-123456', 'velocity-marketplace'); ?>" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-dark w-100"><?php echo esc_html__('Lihat Status Pesanan', 'velocity-marketplace'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($invoice !== '' && !$order) : ?>
        <div class="alert alert-warning mb-0"><?php echo esc_html__('Invoice tidak ditemukan.', 'velocity-marketplace'); ?></div>
    <?php elseif ($order) : ?>
        <?php
        $order_id = (int) $order->ID;
        $status = (string) get_post_meta($order_id, 'vmp_status', true);
        $payment = (string) get_post_meta($order_id, 'vmp_payment_method', true);
        $shipping = get_post_meta($order_id, 'vmp_shipping', true);
        $shipping_groups = OrderData::shipping_groups($order_id);
        $subtotal = (float) get_post_meta($order_id, 'vmp_subtotal', true);
        $shipping_total = (float) get_post_meta($order_id, 'vmp_shipping_total', true);
        $total = (float) get_post_meta($order_id, 'vmp_total', true);
        $coupon_code = (string) get_post_meta($order_id, 'vmp_coupon_code', true);
        $coupon_scope = (string) get_post_meta($order_id, 'vmp_coupon_scope', true);
        $coupon_discount = (float) get_post_meta($order_id, 'vmp_coupon_discount', true);
        $coupon_product_discount = (float) get_post_meta($order_id, 'vmp_coupon_product_discount', true);
        $coupon_shipping_discount = (float) get_post_meta($order_id, 'vmp_coupon_shipping_discount', true);
        $gateway_payment_url = (string) get_post_meta($order_id, 'vmp_gateway_payment_url', true);
        $gateway_status = (string) get_post_meta($order_id, 'vmp_gateway_status', true);
        $bank_accounts = get_post_meta($order_id, 'vmp_bank_accounts', true);
        if (!is_array($bank_accounts)) {
            $bank_accounts = [];
        }
        if ($payment === 'bank' && empty($bank_accounts)) {
            $bank_accounts = \VelocityMarketplace\Support\Settings::bank_accounts();
        }
        if ($shipping_total <= 0) {
            $shipping_total = (float) ($shipping['cost'] ?? 0);
        }
        if (!is_array($shipping)) {
            $shipping = [];
        }
        $destination_label = trim((string) (($shipping['subdistrict_destination_name'] ?? '') . ', ' . ($shipping['city_destination_name'] ?? '') . ', ' . ($shipping['province_destination_name'] ?? '')), ', ');
        ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex flex-wrap align-items-center gap-2"><strong><?php echo esc_html__('Invoice:', 'velocity-marketplace'); ?></strong> <span><?php echo esc_html($invoice); ?></span><button type="button" class="btn btn-sm btn-outline-secondary" data-vmp-copy-text="<?php echo esc_attr($invoice); ?>" data-vmp-copy-success="<?php echo esc_attr__('Invoice Tersalin', 'velocity-marketplace'); ?>"><?php echo esc_html__('Salin Invoice', 'velocity-marketplace'); ?></button></div>
                        <div><strong><?php echo esc_html__('Metode Pembayaran:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($payment !== '' ? strtoupper($payment) : '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div><strong><?php echo esc_html__('Tujuan:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($destination_label !== '' ? $destination_label : '-'); ?></div>
                        <div><strong><?php echo esc_html__('Kupon:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($coupon_code !== '' ? $coupon_code : '-'); ?></div>
                        <?php if ($coupon_code !== '') : ?>
                            <div><strong><?php echo esc_html__('Cakupan Kupon:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($coupon_scope === 'shipping' ? __('Diskon Ongkir', 'velocity-marketplace') : __('Diskon Produk', 'velocity-marketplace')); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($payment === 'duitku' && $gateway_payment_url !== '' && $gateway_status !== 'paid') : ?>
                    <div class="mt-3">
                        <a href="<?php echo esc_url($gateway_payment_url); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-primary"><?php echo esc_html__('Lanjutkan Pembayaran Duitku', 'velocity-marketplace'); ?></a>
                    </div>
                <?php endif; ?>
                <?php if ($payment === 'bank' && !empty($bank_accounts)) : ?>
                    <div class="mt-4 border-top pt-3">
                        <h3 class="h6 mb-2"><?php echo esc_html__('Rekening Tujuan Transfer', 'velocity-marketplace'); ?></h3>
                        <div class="small text-muted mb-3"><?php echo esc_html__('Gunakan salah satu rekening berikut untuk membayar pesanan ini.', 'velocity-marketplace'); ?></div>
                        <div class="row g-3">
                            <?php foreach ($bank_accounts as $bank_account) : ?>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="fw-semibold"><?php echo esc_html((string) ($bank_account['bank_name'] ?? '-')); ?></div>
                                        <div class="small text-muted mt-2"><?php echo esc_html__('Nomor Rekening', 'velocity-marketplace'); ?></div>
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <div class="fw-semibold"><?php echo esc_html((string) ($bank_account['account_number'] ?? '-')); ?></div>
                                            <?php if (!empty($bank_account['account_number'])) : ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-vmp-copy-text="<?php echo esc_attr((string) $bank_account['account_number']); ?>" data-vmp-copy-success="<?php echo esc_attr__('Rekening Tersalin', 'velocity-marketplace'); ?>"><?php echo esc_html__('Salin Rekening', 'velocity-marketplace'); ?></button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted mt-2"><?php echo esc_html__('Atas Nama', 'velocity-marketplace'); ?></div>
                                        <div><?php echo esc_html((string) ($bank_account['account_holder'] ?? '-')); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($shipping_groups)) : ?>
                    <div class="mt-4">
                        <h3 class="h6 mb-3"><?php echo esc_html__('Pengiriman per Toko', 'velocity-marketplace'); ?></h3>
                        <div class="vmp-order-groups">
                        <?php foreach ($shipping_groups as $shipping_group) : ?>
                            <?php
                            $seller_name = (string) ($shipping_group['seller_name'] ?? __('Toko', 'velocity-marketplace'));
                            $group_status = OrderData::shipping_group_status($shipping_group, $status);
                            $group_badge_class = OrderData::status_badge_class($group_status);
                            $group_items = isset($shipping_group['items']) && is_array($shipping_group['items'])
                                ? array_values($shipping_group['items'])
                                : OrderData::seller_items($order_id, (int) ($shipping_group['seller_id'] ?? 0));
                            $group_subtotal = isset($shipping_group['subtotal']) ? (float) $shipping_group['subtotal'] : 0.0;
                            if ($group_subtotal <= 0) {
                                foreach ($group_items as $group_item) {
                                    $group_subtotal += isset($group_item['subtotal']) ? (float) $group_item['subtotal'] : ((float) ($group_item['price'] ?? 0) * (int) ($group_item['qty'] ?? 0));
                                }
                            }
                            $group_shipping_cost = (float) ($shipping_group['cost'] ?? 0);
                            $group_received_at = (string) ($shipping_group['received_at'] ?? '');
                            $group_timeline_steps = OrderData::timeline_steps($group_status, $group_received_at);
                            $receipt_no = (string) ($shipping_group['receipt_no'] ?? '');
                            $receipt_courier = (string) ($shipping_group['receipt_courier'] ?? ($shipping_group['courier'] ?? ''));
                            $waybill = null;
                            if ($receipt_no !== '' && $receipt_courier !== '') {
                                $maybe_waybill = ShippingController::fetch_waybill($receipt_no, $receipt_courier);
                                if (!is_wp_error($maybe_waybill) && is_array($maybe_waybill)) {
                                    $waybill = $maybe_waybill;
                                }
                            }
                            ?>
                            <?php
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
                                            <div class="fw-semibold"><?php echo esc_html($seller_name); ?></div>
                                            <div class="small text-muted mt-1"><strong><?php echo esc_html__('Kurir:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($receipt_courier !== '' ? $receipt_courier : '-'); ?></div>
                                            <div class="small text-muted"><strong><?php echo esc_html__('Layanan:', 'velocity-marketplace'); ?></strong> <?php echo esc_html((string) ($shipping_group['service'] ?? '-')); ?></div>
                                            <div class="small text-muted d-flex flex-wrap align-items-center gap-2"><strong><?php echo esc_html__('Nomor Resi:', 'velocity-marketplace'); ?></strong> <span><?php echo esc_html($receipt_no !== '' ? $receipt_no : '-'); ?></span><?php if ($receipt_no !== '') : ?><button type="button" class="btn btn-sm btn-outline-secondary" data-vmp-copy-text="<?php echo esc_attr($receipt_no); ?>" data-vmp-copy-success="<?php echo esc_attr__('Resi Tersalin', 'velocity-marketplace'); ?>"><?php echo esc_html__('Salin Resi', 'velocity-marketplace'); ?></button><?php endif; ?></div>
                                        </div>
                                        <span class="badge rounded-pill bg-<?php echo esc_attr($group_badge_class); ?> vmp-order-status-badge"><?php echo esc_html(OrderData::status_label($group_status)); ?></span>
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
                                                        <div class="fw-semibold"><?php echo esc_html((string) ($group_item['title'] ?? '-')); ?></div>
                                                        <?php if (!empty($line_options)) : ?><div class="small text-muted"><?php echo esc_html(implode(' • ', $line_options)); ?></div><?php endif; ?>
                                                        <div class="small text-muted"><?php echo esc_html(sprintf(__('Rp %1$s x %2$d', 'velocity-marketplace'), number_format($line_price, 0, ',', '.'), $line_qty)); ?></div>
                                                    </div>
                                                </div>
                                                <div class="text-end fw-semibold"><?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($line_subtotal, 0, ',', '.'))); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="vmp-order-group__summary">
                                        <div class="vmp-order-group__summary-row">
                                            <span><?php echo esc_html__('Subtotal Produk', 'velocity-marketplace'); ?></span>
                                            <strong><?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($group_subtotal, 0, ',', '.'))); ?></strong>
                                        </div>
                                        <div class="vmp-order-group__summary-row">
                                            <span><?php echo esc_html__('Ongkir', 'velocity-marketplace'); ?></span>
                                            <strong><?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($group_shipping_cost, 0, ',', '.'))); ?></strong>
                                        </div>
                                        <div class="vmp-order-group__summary-row vmp-order-group__summary-row--total">
                                            <span><?php echo esc_html__('Total Toko', 'velocity-marketplace'); ?></span>
                                            <strong><?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($group_subtotal + $group_shipping_cost, 0, ',', '.'))); ?></strong>
                                        </div>
                                    </div>
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
                        <div class="card bg-light-subtle mt-3">
                            <div class="card-body">
                                <div class="vmp-order-group__summary-row">
                                    <span><?php echo esc_html__('Subtotal Produk', 'velocity-marketplace'); ?></span>
                                    <strong><?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($subtotal, 0, ',', '.'))); ?></strong>
                                </div>
                                <div class="vmp-order-group__summary-row">
                                    <span><?php echo esc_html__('Total Ongkir', 'velocity-marketplace'); ?></span>
                                    <strong><?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($shipping_total, 0, ',', '.'))); ?></strong>
                                </div>
                                <?php if ($coupon_product_discount > 0) : ?>
                                    <div class="vmp-order-group__summary-row text-success">
                                        <span><?php echo esc_html__('Diskon Produk', 'velocity-marketplace'); ?></span>
                                        <strong>-<?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($coupon_product_discount, 0, ',', '.'))); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($coupon_shipping_discount > 0) : ?>
                                    <div class="vmp-order-group__summary-row text-success">
                                        <span><?php echo esc_html__('Diskon Ongkir', 'velocity-marketplace'); ?></span>
                                        <strong>-<?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($coupon_shipping_discount, 0, ',', '.'))); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($coupon_discount > 0 && $coupon_product_discount <= 0 && $coupon_shipping_discount <= 0) : ?>
                                    <div class="vmp-order-group__summary-row text-success">
                                        <span><?php echo esc_html__('Diskon Kupon', 'velocity-marketplace'); ?></span>
                                        <strong>-<?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($coupon_discount, 0, ',', '.'))); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <div class="vmp-order-group__summary-row vmp-order-group__summary-row--total">
                                    <span><?php echo esc_html__('Total Bayar', 'velocity-marketplace'); ?></span>
                                    <strong class="text-danger"><?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($total, 0, ',', '.'))); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

