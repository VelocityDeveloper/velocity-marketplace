<?php
use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Modules\Review\ReviewRepository;
use VelocityMarketplace\Modules\Shipping\ShippingController;

$transfer_captcha_html = \VelocityMarketplace\Modules\Captcha\CaptchaBridge::render();
$payment_labels = [
    'bank' => __('Bank Transfer', 'velocity-marketplace'),
    'duitku' => 'Duitku',
    'paypal' => 'PayPal',
    'cod' => 'COD',
];
$invoice = isset($_GET['invoice']) ? sanitize_text_field((string) wp_unslash($_GET['invoice'])) : '';
$tracking_order = null;

if ($invoice !== '') {
    $tracking_query = new \WP_Query([
        'post_type' => 'vmp_order',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => [
            ['key' => 'vmp_invoice', 'value' => $invoice, 'compare' => '='],
        ],
    ]);

    if ($tracking_query->have_posts()) {
        $tracking_query->the_post();
        $candidate_id = get_the_ID();
        $owner_id = (int) get_post_meta($candidate_id, 'vmp_user_id', true);
        $can_view = $owner_id === $current_user_id || current_user_can('manage_options') || OrderData::has_seller($candidate_id, $current_user_id);
        if ($can_view) {
            $tracking_order = get_post($candidate_id);
        }
        wp_reset_postdata();
    }
}
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" class="row g-2">
            <input type="hidden" name="tab" value="tracking">
            <div class="col-md-8">
                <label class="form-label"><?php echo esc_html__('Kode Invoice', 'velocity-marketplace'); ?></label>
                <input type="text" name="invoice" class="form-control" value="<?php echo esc_attr($invoice); ?>" placeholder="<?php echo esc_attr__('Contoh: VMP-20260304-123456', 'velocity-marketplace'); ?>" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-dark w-100" type="submit"><?php echo esc_html__('Lihat Detail Pesanan', 'velocity-marketplace'); ?></button>
            </div>
        </form>
    </div>
</div>

<?php if ($invoice !== '' && !$tracking_order) : ?>
    <div class="alert alert-warning mb-0"><?php echo esc_html__('Invoice tidak ditemukan atau Anda tidak memiliki akses ke pesanan ini.', 'velocity-marketplace'); ?></div>
<?php elseif ($tracking_order) : ?>
    <?php
    $tracking_id = (int) $tracking_order->ID;
    $tracking_status = (string) get_post_meta($tracking_id, 'vmp_status', true);
    $tracking_payment = (string) get_post_meta($tracking_id, 'vmp_payment_method', true);
    $tracking_shipping = get_post_meta($tracking_id, 'vmp_shipping', true);
    $tracking_groups = OrderData::shipping_groups($tracking_id);
    $tracking_subtotal = (float) get_post_meta($tracking_id, 'vmp_subtotal', true);
    $tracking_total = (float) get_post_meta($tracking_id, 'vmp_total', true);
    $tracking_shipping_total = (float) get_post_meta($tracking_id, 'vmp_shipping_total', true);
    $tracking_coupon_code = (string) get_post_meta($tracking_id, 'vmp_coupon_code', true);
    $tracking_coupon_scope = (string) get_post_meta($tracking_id, 'vmp_coupon_scope', true);
    $tracking_coupon_discount = (float) get_post_meta($tracking_id, 'vmp_coupon_discount', true);
    $tracking_coupon_product_discount = (float) get_post_meta($tracking_id, 'vmp_coupon_product_discount', true);
    $tracking_coupon_shipping_discount = (float) get_post_meta($tracking_id, 'vmp_coupon_shipping_discount', true);
    $tracking_notes = (string) get_post_meta($tracking_id, 'vmp_notes', true);
    $tracking_transfer_proof_id = (int) get_post_meta($tracking_id, 'vmp_transfer_proof_id', true);
    $tracking_transfer_proof_url = $tracking_transfer_proof_id > 0 ? wp_get_attachment_url($tracking_transfer_proof_id) : '';
    $tracking_gateway_payment_url = (string) get_post_meta($tracking_id, 'vmp_gateway_payment_url', true);
    $tracking_gateway_status = (string) get_post_meta($tracking_id, 'vmp_gateway_status', true);
    $tracking_bank_accounts = get_post_meta($tracking_id, 'vmp_bank_accounts', true);
    if (!is_array($tracking_bank_accounts)) {
        $tracking_bank_accounts = [];
    }
    if ($tracking_payment === 'bank' && empty($tracking_bank_accounts)) {
        $tracking_bank_accounts = \VelocityMarketplace\Support\Settings::bank_accounts();
    }
    $tracking_transfer_uploaded_at = (string) get_post_meta($tracking_id, 'vmp_transfer_uploaded_at', true);
    $tracking_created_at = (string) get_post_meta($tracking_id, 'vmp_created_at', true);
    $tracking_owner_id = (int) get_post_meta($tracking_id, 'vmp_user_id', true);
    $is_tracking_owner = $tracking_owner_id === $current_user_id || current_user_can('manage_options');
    $is_tracking_buyer = $tracking_owner_id === $current_user_id;
    if (!is_array($tracking_shipping)) {
        $tracking_shipping = [];
    }
    if ($tracking_shipping_total <= 0) {
        $tracking_shipping_total = (float) ($tracking_shipping['cost'] ?? 0);
    }
    $tracking_destination = trim((string) (($tracking_shipping['subdistrict_destination_name'] ?? '') . ', ' . ($tracking_shipping['city_destination_name'] ?? '') . ', ' . ($tracking_shipping['province_destination_name'] ?? '')), ', ');
    $tracking_review_map = $is_tracking_buyer ? (new ReviewRepository())->reviews_for_order_user($tracking_id, $current_user_id) : [];
    $payment_status_text = $tracking_transfer_proof_url !== ''
        ? __('Bukti pembayaran sudah diunggah.', 'velocity-marketplace')
        : (in_array($tracking_payment, ['duitku', 'paypal'], true) ? __('Menunggu konfirmasi pembayaran dari gateway.', 'velocity-marketplace') : __('Menunggu pembayaran.', 'velocity-marketplace'));
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-6">
                    <h3 class="h6 mb-3"><?php echo esc_html__('Informasi Pesanan', 'velocity-marketplace'); ?></h3>
                    <div class="small mb-1 d-flex flex-wrap align-items-center gap-2"><strong><?php echo esc_html__('Invoice:', 'velocity-marketplace'); ?></strong> <span><?php echo esc_html($invoice); ?></span><button type="button" class="btn btn-sm btn-outline-secondary" data-vmp-copy-text="<?php echo esc_attr($invoice); ?>" data-vmp-copy-success="<?php echo esc_attr__('Invoice Tersalin', 'velocity-marketplace'); ?>"><?php echo esc_html__('Salin Invoice', 'velocity-marketplace'); ?></button></div>
                    <div class="small mb-1"><strong><?php echo esc_html__('Metode Pembayaran:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($tracking_payment !== '' ? ($payment_labels[$tracking_payment] ?? strtoupper($tracking_payment)) : '-'); ?></div>
                    <div class="small mb-1"><strong><?php echo esc_html__('Tanggal:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($tracking_created_at !== '' ? $tracking_created_at : get_the_date('d-m-Y H:i', $tracking_id)); ?></div>
                    <div class="small mb-1"><strong><?php echo esc_html__('Tujuan:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($tracking_destination !== '' ? $tracking_destination : '-'); ?></div>
                    <div class="small text-muted mt-2"><?php echo esc_html__('Catatan Pesanan:', 'velocity-marketplace'); ?> <?php echo esc_html($tracking_notes !== '' ? $tracking_notes : '-'); ?></div>
                </div>
                <div class="col-lg-6">
                    <h3 class="h6 mb-3"><?php echo esc_html__('Detail Pembayaran', 'velocity-marketplace'); ?></h3>
                    <div class="small mb-1"><strong><?php echo esc_html__('Kupon:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($tracking_coupon_code !== '' ? $tracking_coupon_code : '-'); ?></div>
                    <?php if ($tracking_coupon_code !== '') : ?>
                        <div class="small mb-1"><strong><?php echo esc_html__('Cakupan Kupon:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($tracking_coupon_scope === 'shipping' ? __('Diskon Ongkir', 'velocity-marketplace') : __('Diskon Produk', 'velocity-marketplace')); ?></div>
                    <?php endif; ?>
                    <div class="small mb-1"><strong><?php echo esc_html__('Status Pembayaran:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($payment_status_text); ?></div>
                    <div class="small mb-1"><strong><?php echo esc_html__('Bukti Pembayaran:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($tracking_transfer_proof_url !== '' ? __('Sudah Diunggah', 'velocity-marketplace') : __('Belum tersedia', 'velocity-marketplace')); ?></div>
                    <?php if ($tracking_transfer_uploaded_at !== '') : ?>
                        <div class="small mb-1"><strong><?php echo esc_html__('Waktu Upload:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($tracking_transfer_uploaded_at); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($tracking_transfer_proof_url) : ?>
                <div class="mt-3">
                    <a href="<?php echo esc_url($tracking_transfer_proof_url); ?>" class="btn btn-sm btn-outline-primary" target="_blank"><?php echo esc_html__('Lihat Bukti Pembayaran', 'velocity-marketplace'); ?></a>
                </div>
            <?php endif; ?>
            <?php if ($tracking_payment === 'duitku' && $tracking_gateway_payment_url !== '' && $tracking_gateway_status !== 'paid') : ?>
                <div class="mt-3">
                    <a href="<?php echo esc_url($tracking_gateway_payment_url); ?>" class="btn btn-sm btn-primary" target="_blank" rel="noopener"><?php echo esc_html__('Lanjutkan Pembayaran Duitku', 'velocity-marketplace'); ?></a>
                </div>
            <?php endif; ?>

            <?php if ($tracking_payment === 'bank' && !empty($tracking_bank_accounts)) : ?>
                <div class="mt-4 border-top pt-3">
                    <h3 class="h6 mb-2"><?php echo esc_html__('Rekening Tujuan Transfer', 'velocity-marketplace'); ?></h3>
                    <div class="small text-muted mb-3"><?php echo esc_html__('Gunakan salah satu rekening berikut untuk membayar pesanan ini.', 'velocity-marketplace'); ?></div>
                    <div class="row g-3">
                        <?php foreach ($tracking_bank_accounts as $bank_account) : ?>
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

            <?php if ($is_tracking_owner && !in_array($tracking_status, ['completed', 'cancelled', 'refunded'], true)) : ?>
                <div class="mt-4 border-top pt-3">
                    <h3 class="h6 mb-2"><?php echo esc_html__('Unggah Bukti Transfer', 'velocity-marketplace'); ?></h3>
                    <form method="post" enctype="multipart/form-data" class="row g-2">
                        <input type="hidden" name="vmp_action" value="buyer_upload_transfer">
                        <input type="hidden" name="order_id" value="<?php echo esc_attr($tracking_id); ?>">
                        <input type="hidden" name="redirect_tab" value="tracking">
                        <?php wp_nonce_field('vmp_upload_transfer_' . $tracking_id, 'vmp_transfer_nonce'); ?>
                        <div class="col-md-8">
                            <input type="file" name="transfer_proof" class="form-control" accept="image/*,.pdf" required>
                        </div>
                        <?php if ($transfer_captcha_html !== '') : ?><div class="col-12"><?php echo $transfer_captcha_html; ?></div><?php endif; ?>
                        <div class="col-md-4">
                            <button class="btn btn-dark w-100" type="submit"><?php echo esc_html__('Unggah Bukti Transfer', 'velocity-marketplace'); ?></button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (!empty($tracking_groups)) : ?>
                <div class="mt-4">
                    <h3 class="h6 mb-3"><?php echo esc_html__('Pengiriman per Toko', 'velocity-marketplace'); ?></h3>
                    <div class="vmp-order-groups">
                    <?php foreach ($tracking_groups as $group) : ?>
                        <?php
                        $group_status = OrderData::shipping_group_status($group, $tracking_status);
                        $group_badge_class = OrderData::status_badge_class($group_status);
                        $group_items = isset($group['items']) && is_array($group['items'])
                            ? array_values($group['items'])
                            : OrderData::seller_items($tracking_id, (int) ($group['seller_id'] ?? 0));
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
                            <?php if ($is_tracking_buyer && $group_status === 'shipped') : ?>
                                <div class="mt-3 pt-3 border-top text-end">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="vmp_action" value="buyer_confirm_received">
                                        <input type="hidden" name="order_id" value="<?php echo esc_attr($tracking_id); ?>">
                                        <input type="hidden" name="seller_id" value="<?php echo esc_attr((int) ($group['seller_id'] ?? 0)); ?>">
                                        <input type="hidden" name="redirect_tab" value="tracking">
                                        <input type="hidden" name="invoice" value="<?php echo esc_attr($invoice); ?>">
                                        <?php wp_nonce_field('vmp_confirm_received_' . $tracking_id . '_' . (int) ($group['seller_id'] ?? 0), 'vmp_confirm_received_nonce'); ?>
                                        <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('<?php echo esc_js(__('Konfirmasi bahwa pesanan dari toko ini sudah diterima?', 'velocity-marketplace')); ?>');">
                                            <?php echo esc_html__('Pesanan Diterima', 'velocity-marketplace'); ?>
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($is_tracking_buyer && $group_status === 'completed' && $group_has_pending_review) : ?>
                                <div class="mt-3 pt-3 border-top text-end">
                                    <a href="<?php echo esc_url(add_query_arg(['tab' => 'orders', 'invoice' => $invoice], \VelocityMarketplace\Support\Settings::profile_url()) . '#vmp-order-reviews'); ?>" class="btn btn-sm btn-outline-primary"><?php echo esc_html__('Berikan Penilaian', 'velocity-marketplace'); ?></a>
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
                    <div class="card bg-light-subtle mt-3">
                        <div class="card-body">
                            <div class="vmp-order-group__summary-row">
                                <span><?php echo esc_html__('Subtotal Produk', 'velocity-marketplace'); ?></span>
                                <strong><?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($tracking_subtotal, 0, ',', '.'))); ?></strong>
                            </div>
                            <div class="vmp-order-group__summary-row">
                                <span><?php echo esc_html__('Total Ongkir', 'velocity-marketplace'); ?></span>
                                <strong><?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($tracking_shipping_total, 0, ',', '.'))); ?></strong>
                            </div>
                            <?php if ($tracking_coupon_product_discount > 0) : ?>
                                <div class="vmp-order-group__summary-row text-success">
                                    <span><?php echo esc_html__('Diskon Produk', 'velocity-marketplace'); ?></span>
                                    <strong>-<?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($tracking_coupon_product_discount, 0, ',', '.'))); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if ($tracking_coupon_shipping_discount > 0) : ?>
                                <div class="vmp-order-group__summary-row text-success">
                                    <span><?php echo esc_html__('Diskon Ongkir', 'velocity-marketplace'); ?></span>
                                    <strong>-<?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($tracking_coupon_shipping_discount, 0, ',', '.'))); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if ($tracking_coupon_discount > 0 && $tracking_coupon_product_discount <= 0 && $tracking_coupon_shipping_discount <= 0) : ?>
                                <div class="vmp-order-group__summary-row text-success">
                                    <span><?php echo esc_html__('Diskon Kupon', 'velocity-marketplace'); ?></span>
                                    <strong>-<?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($tracking_coupon_discount, 0, ',', '.'))); ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="vmp-order-group__summary-row vmp-order-group__summary-row--total">
                                <span><?php echo esc_html__('Total Bayar', 'velocity-marketplace'); ?></span>
                                <strong class="text-danger"><?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($tracking_total, 0, ',', '.'))); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
