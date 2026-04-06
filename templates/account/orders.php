<?php
use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Modules\Review\RatingRenderer;
use VelocityMarketplace\Modules\Review\ReviewRepository;
use VelocityMarketplace\Modules\Shipping\ShippingController;
    $payment_labels = [
        'bank' => __('Bank Transfer', 'velocity-marketplace'),
        'qris' => 'QRIS',
        'duitku' => 'Duitku',
        'paypal' => 'PayPal',
        'cod' => 'COD',
    ];
    $transfer_captcha_html = \VelocityMarketplace\Modules\Captcha\CaptchaBridge::render();
    $invoice = isset($_GET['invoice']) ? sanitize_text_field((string) wp_unslash($_GET['invoice'])) : '';
    if ($invoice !== '') {
        $query = new \WP_Query([
            'post_type' => 'store_order',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_store_order_user_id', 'value' => (string) $current_user_id, 'compare' => '='],
                ['key' => 'vmp_invoice', 'value' => $invoice, 'compare' => '='],
            ],
        ]);
    } else {
        $query = new \WP_Query([
            'post_type' => 'store_order',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'meta_key' => '_store_order_user_id',
            'meta_value' => (string) $current_user_id,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
    }
    ?>
    <?php if (!$query->have_posts()) : ?>
        <div class="alert alert-info mb-0"><?php echo esc_html__('There is no order history yet.', 'velocity-marketplace'); ?></div>
    <?php elseif ($invoice !== '') : ?>
        <?php
        $query->the_post();
        $order_id = get_the_ID();
        $invoice_meta = (string) get_post_meta($order_id, 'vmp_invoice', true);
        $items = OrderData::get_items($order_id);
        $total = (float) get_post_meta($order_id, 'vmp_total', true);
        $status = (string) get_post_meta($order_id, 'vmp_status', true);
        $payment = (string) get_post_meta($order_id, 'vmp_payment_method', true);
        $shipping = get_post_meta($order_id, 'vmp_shipping', true);
        $shipping_groups = OrderData::shipping_groups($order_id);
        $shipping_total = (float) get_post_meta($order_id, 'vmp_shipping_total', true);
        $subtotal = (float) get_post_meta($order_id, 'vmp_subtotal', true);
        $coupon_code = (string) get_post_meta($order_id, 'vmp_coupon_code', true);
        $coupon_scope = (string) get_post_meta($order_id, 'vmp_coupon_scope', true);
        $coupon_discount = (float) get_post_meta($order_id, 'vmp_coupon_discount', true);
        $coupon_product_discount = (float) get_post_meta($order_id, 'vmp_coupon_product_discount', true);
        $coupon_shipping_discount = (float) get_post_meta($order_id, 'vmp_coupon_shipping_discount', true);
        $notes = (string) get_post_meta($order_id, 'vmp_notes', true);
        $transfer_proof_id = (int) get_post_meta($order_id, 'vmp_transfer_proof_id', true);
        $transfer_proof_url = $transfer_proof_id > 0 ? wp_get_attachment_url($transfer_proof_id) : '';
        $gateway_payment_url = (string) get_post_meta($order_id, 'vmp_gateway_payment_url', true);
        $gateway_status = (string) get_post_meta($order_id, 'vmp_gateway_status', true);
        $qris = \VelocityMarketplace\Support\Settings::qris_details();
        $bank_accounts = get_post_meta($order_id, 'vmp_bank_accounts', true);
        if (!is_array($bank_accounts)) {
            $bank_accounts = [];
        }
        if ($payment === 'bank' && empty($bank_accounts)) {
            $bank_accounts = \VelocityMarketplace\Support\Settings::bank_accounts();
        }
        $receipt_no = (string) get_post_meta($order_id, 'vmp_receipt_no', true);
        $receipt_courier = (string) get_post_meta($order_id, 'vmp_receipt_courier', true);
        $review_repo = new ReviewRepository();
        $review_map = $review_repo->reviews_for_order_user($order_id, $current_user_id);
        if (!is_array($items)) {
            $items = [];
        }
        if (!is_array($shipping)) {
            $shipping = [];
        }
        $completed_seller_ids = [];
        foreach ($shipping_groups as $shipping_group) {
            $group_seller_id = isset($shipping_group['seller_id']) ? (int) $shipping_group['seller_id'] : 0;
            if ($group_seller_id <= 0) {
                continue;
            }
            if (OrderData::shipping_group_status($shipping_group, $status) === 'completed') {
                $completed_seller_ids[] = $group_seller_id;
            }
        }
        $completed_seller_ids = array_values(array_unique($completed_seller_ids));
        $destination_label = trim((string) (($shipping['subdistrict_destination_name'] ?? '') . ', ' . ($shipping['city_destination_name'] ?? '') . ', ' . ($shipping['province_destination_name'] ?? '')), ', ');
        ?>
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="d-flex flex-wrap align-items-center gap-2"><strong><?php echo esc_html__('Invoice:', 'velocity-marketplace'); ?></strong> <span><?php echo esc_html($invoice_meta); ?></span><button type="button" class="btn btn-sm btn-outline-secondary" data-vmp-copy-text="<?php echo esc_attr($invoice_meta); ?>" data-vmp-copy-success="<?php echo esc_attr__('Invoice Tersalin', 'velocity-marketplace'); ?>"><?php echo esc_html__('Salin Invoice', 'velocity-marketplace'); ?></button></div>
                    <div><strong><?php echo esc_html__('Tanggal:', 'velocity-marketplace'); ?></strong> <?php echo esc_html(get_the_date('d-m-Y H:i', $order_id)); ?></div>
                    <div><strong><?php echo esc_html__('Metode Pembayaran:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($payment !== '' ? ($payment_labels[$payment] ?? strtoupper($payment)) : '-'); ?></div>
                </div>
                <div class="col-md-6">
                    <div><strong><?php echo esc_html__('Tujuan:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($destination_label !== '' ? $destination_label : '-'); ?></div>
                    <div><strong><?php echo esc_html__('Kupon:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($coupon_code !== '' ? $coupon_code : '-'); ?></div>
                    <?php if ($coupon_code !== '') : ?><div><strong><?php echo esc_html__('Cakupan Kupon:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($coupon_scope === 'shipping' ? __('Diskon Ongkir', 'velocity-marketplace') : __('Diskon Produk', 'velocity-marketplace')); ?></div><?php endif; ?>
                </div>
            </div>
            <div class="small text-muted mt-2"><?php echo esc_html__('Catatan Pesanan:', 'velocity-marketplace'); ?> <?php echo esc_html($notes !== '' ? $notes : '-'); ?></div>
            <?php if ($payment === 'duitku' && $gateway_payment_url !== '' && $gateway_status !== 'paid') : ?>
                <div class="mt-3">
                    <a href="<?php echo esc_url($gateway_payment_url); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-primary"><?php echo esc_html__('Lanjutkan Pembayaran Duitku', 'velocity-marketplace'); ?></a>
                </div>
            <?php endif; ?>
            <?php if ($payment === 'qris') : ?>
                <div class="mt-4 border-top pt-3">
                    <h3 class="h6 mb-2"><?php echo esc_html__('Pembayaran QRIS', 'velocity-marketplace'); ?></h3>
                    <div class="small text-muted mb-3"><?php echo esc_html($qris['label']); ?></div>
                    <?php if (!empty($qris['image_url'])) : ?>
                        <img src="<?php echo esc_url($qris['image_url']); ?>" alt="<?php echo esc_attr__('QRIS', 'velocity-marketplace'); ?>" class="img-fluid rounded border" style="max-width:280px;">
                    <?php else : ?>
                        <div class="alert alert-warning mb-0"><?php echo esc_html__('QRIS belum dikonfigurasi di Pengaturan Toko VD Store.', 'velocity-marketplace'); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($shipping_groups)) : ?>
                <div class="mt-4">
                    <h3 class="h6 mb-3"><?php echo esc_html__('Pengiriman per Toko', 'velocity-marketplace'); ?></h3>
                    <div class="vmp-order-groups">
                        <?php foreach ($shipping_groups as $shipping_group) : ?>
                            <?php
                            $group_seller_id = (int) ($shipping_group['seller_id'] ?? 0);
                            $group_seller = $group_seller_id > 0 ? get_userdata($group_seller_id) : null;
                            $group_seller_name = (string) ($shipping_group['seller_name'] ?? '');
                            if ($group_seller_name === '' && $group_seller && $group_seller->display_name !== '') {
                                $group_seller_name = $group_seller->display_name;
                            }
                            $group_status = OrderData::shipping_group_status($shipping_group, $status);
                            $group_badge_class = OrderData::status_badge_class($group_status);
                            $group_receipt_no = (string) ($shipping_group['receipt_no'] ?? '');
                            $group_receipt_courier = (string) ($shipping_group['receipt_courier'] ?? ($shipping_group['courier'] ?? ''));
                            $group_items = isset($shipping_group['items']) && is_array($shipping_group['items'])
                                ? array_values($shipping_group['items'])
                                : OrderData::seller_items($order_id, $group_seller_id);
                            $group_subtotal = isset($shipping_group['subtotal']) ? (float) $shipping_group['subtotal'] : 0.0;
                            if ($group_subtotal <= 0) {
                                foreach ($group_items as $group_item) {
                                    $group_subtotal += isset($group_item['subtotal']) ? (float) $group_item['subtotal'] : ((float) ($group_item['price'] ?? 0) * (int) ($group_item['qty'] ?? 0));
                                }
                            }
                            $group_has_pending_review = false;
                            $group_has_reviewable_items = !empty($group_items);
                            foreach ($group_items as $group_item) {
                                $group_product_id = (int) ($group_item['product_id'] ?? 0);
                                if ($group_product_id > 0 && !isset($review_map[$group_product_id])) {
                                    $group_has_pending_review = true;
                                    break;
                                }
                            }
                            $group_shipping_cost = (float) ($shipping_group['cost'] ?? 0);
                            $group_total = $group_subtotal + $group_shipping_cost;
                            $group_received_at = (string) ($shipping_group['received_at'] ?? '');
                            $group_timeline_steps = OrderData::timeline_steps($group_status, $group_received_at);
                            $waybill_data = null;
                            if ($group_receipt_no !== '' && $group_receipt_courier !== '') {
                                $maybe_waybill = ShippingController::fetch_waybill($group_receipt_no, $group_receipt_courier);
                                if (!is_wp_error($maybe_waybill) && is_array($maybe_waybill)) {
                                    $waybill_data = $maybe_waybill;
                                }
                            }
                            ?>
                            <div class="vmp-order-group card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                        <div>
                                            <div class="fw-semibold"><?php echo esc_html($group_seller_name !== '' ? $group_seller_name : __('Toko', 'velocity-marketplace')); ?></div>
                                            <div class="small text-muted mt-1">
                                                <?php echo esc_html((string) ($shipping_group['courier_name'] ?? strtoupper((string) ($shipping_group['courier'] ?? '-')))); ?>
                                                <?php if (!empty($shipping_group['service'])) : ?>
                                                    <?php echo esc_html(' - ' . (string) $shipping_group['service']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted d-flex flex-wrap align-items-center gap-2">
                                                <span><?php echo esc_html(sprintf(__('Resi: %s', 'velocity-marketplace'), ($group_receipt_no !== '' ? $group_receipt_no : '-'))); ?></span>
                                                <?php if ($group_receipt_no !== '') : ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-vmp-copy-text="<?php echo esc_attr($group_receipt_no); ?>" data-vmp-copy-success="<?php echo esc_attr__('Resi Tersalin', 'velocity-marketplace'); ?>"><?php echo esc_html__('Salin Resi', 'velocity-marketplace'); ?></button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="vmp-order-group__actions">
                                            <?php if ($group_seller_id > 0) : ?>
                                                <a href="<?php echo esc_url(add_query_arg(['tab' => 'messages', 'message_to' => $group_seller_id, 'message_order' => $order_id])); ?>" class="btn btn-sm btn-outline-dark vmp-order-group__message-btn">
                                                    <span class="vmp-order-group__message-icon" aria-hidden="true">
                                                        <svg viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M8 1C4.134 1 1 3.762 1 7.167c0 1.57.67 3 1.77 4.097-.116.93-.534 1.89-1.23 2.736a.5.5 0 0 0 .521.8c1.18-.273 2.278-.756 3.23-1.42A8.29 8.29 0 0 0 8 13.333c3.866 0 7-2.761 7-6.166C15 3.761 11.866 1 8 1Zm0 1c3.334 0 6 2.332 6 5.167 0 2.834-2.666 5.166-6 5.166-.847 0-1.667-.151-2.421-.438a.5.5 0 0 0-.472.055 8.012 8.012 0 0 1-2.108 1.1c.378-.723.59-1.465.66-2.182a.5.5 0 0 0-.159-.42C2.506 9.547 2 8.395 2 7.167 2 4.332 4.666 2 8 2Z"/>
                                                        </svg>
                                                    </span>
                                                    <span><?php echo esc_html__('Pesan', 'velocity-marketplace'); ?></span>
                                                </a>
                                            <?php endif; ?>
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
                                            <strong><?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($group_total, 0, ',', '.'))); ?></strong>
                                        </div>
                                    </div>
                                    <?php if ($group_status === 'shipped') : ?>
                                        <div class="mt-3 pt-3 border-top text-end">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="vmp_action" value="buyer_confirm_received">
                                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                                                <input type="hidden" name="seller_id" value="<?php echo esc_attr($group_seller_id); ?>">
                                                <input type="hidden" name="redirect_tab" value="orders">
                                                <input type="hidden" name="invoice" value="<?php echo esc_attr($invoice_meta); ?>">
                                                <?php wp_nonce_field('vmp_confirm_received_' . $order_id . '_' . $group_seller_id, 'vmp_confirm_received_nonce'); ?>
                                                <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('<?php echo esc_js(__('Konfirmasi bahwa pesanan dari toko ini sudah diterima?', 'velocity-marketplace')); ?>');">
                                                    <?php echo esc_html__('Pesanan Diterima', 'velocity-marketplace'); ?>
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif ($group_status === 'completed' && $group_has_pending_review) : ?>
                                        <div class="mt-3 pt-3 border-top text-end">
                                            <a href="#vmp-order-reviews" class="btn btn-sm btn-outline-primary"><?php echo esc_html__('Berikan Penilaian', 'velocity-marketplace'); ?></a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($waybill_data && !empty($waybill_data['data'])) : ?>
                                        <div class="mt-3 pt-3 border-top">
                                            <div class="small fw-semibold mb-2"><?php echo esc_html__('Pelacakan Resi', 'velocity-marketplace'); ?></div>
                                            <?php
                                            $tracking_rows = [];
                                            if (isset($waybill_data['data']['manifest']) && is_array($waybill_data['data']['manifest'])) {
                                                $tracking_rows = $waybill_data['data']['manifest'];
                                            } elseif (isset($waybill_data['data']['history']) && is_array($waybill_data['data']['history'])) {
                                                $tracking_rows = $waybill_data['data']['history'];
                                            }
                                            ?>
                                            <?php if (!empty($tracking_rows)) : ?>
                                                <div class="small">
                                                    <?php foreach ($tracking_rows as $tracking_row) : ?>
                                                        <div class="border-top py-2">
                                                            <div class="fw-semibold"><?php echo esc_html((string) ($tracking_row['manifest_description'] ?? $tracking_row['description'] ?? '-')); ?></div>
                                                            <div class="text-muted"><?php echo esc_html(trim((string) (($tracking_row['manifest_date'] ?? '') . ' ' . ($tracking_row['manifest_time'] ?? $tracking_row['date'] ?? '')))); ?></div>
                                                            <?php if (!empty($tracking_row['city_name'])) : ?><div class="text-muted"><?php echo esc_html((string) $tracking_row['city_name']); ?></div><?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else : ?>
                                                <div class="small text-muted"><?php echo esc_html__('Data pelacakan belum tersedia.', 'velocity-marketplace'); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="card bg-light-subtle mt-4">
                <div class="card-body">
                    <div class="vmp-order-group__summary-row">
                        <span><?php echo esc_html__('Subtotal Produk', 'velocity-marketplace'); ?></span>
                        <strong><?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($subtotal, 0, ',', '.'))); ?></strong>
                    </div>
                    <div class="vmp-order-group__summary-row">
                        <span><?php echo esc_html__('Total Ongkir', 'velocity-marketplace'); ?></span>
                        <strong><?php echo esc_html(sprintf(__('Rp %s', 'velocity-marketplace'), number_format($shipping_total > 0 ? $shipping_total : (float) ($shipping['cost'] ?? 0), 0, ',', '.'))); ?></strong>
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
            <?php if ($payment === 'bank' && !empty($bank_accounts)) : ?>
                <div class="border rounded p-3 mt-3 bg-light-subtle">
                    <div class="fw-semibold mb-2"><?php echo esc_html__('Rekening Tujuan Transfer', 'velocity-marketplace'); ?></div>
                    <div class="small text-muted mb-3"><?php echo esc_html__('Transfer pembayaran ke salah satu rekening berikut sebelum mengunggah bukti pembayaran.', 'velocity-marketplace'); ?></div>
                    <div class="row g-3">
                        <?php foreach ($bank_accounts as $bank_account) : ?>
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100 bg-white">
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
            <?php if ($transfer_proof_url) : ?><a href="<?php echo esc_url($transfer_proof_url); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><?php echo esc_html__('Lihat Bukti Pembayaran', 'velocity-marketplace'); ?></a><?php endif; ?>
            <?php if (!empty($completed_seller_ids) && !empty($items)) : ?>
                <div class="mt-4" id="vmp-order-reviews">
                    <h3 class="h6 mb-3"><?php echo esc_html__('Ulasan Produk', 'velocity-marketplace'); ?></h3>
                    <div class="row g-3">
                        <?php foreach ($items as $item) : ?>
                            <?php
                            $product_id = (int) ($item['product_id'] ?? 0);
                            $item_seller_id = isset($item['seller_id']) ? (int) $item['seller_id'] : 0;
                            if ($product_id <= 0) {
                                continue;
                            }
                            if ($item_seller_id <= 0 || !in_array($item_seller_id, $completed_seller_ids, true)) {
                                continue;
                            }
                            $existing_review = $review_map[$product_id] ?? null;
                            $existing_rating = $existing_review ? (int) ($existing_review['rating'] ?? 0) : 5;
                            $existing_title = $existing_review ? (string) ($existing_review['title'] ?? '') : '';
                            $existing_content = $existing_review ? (string) ($existing_review['content'] ?? '') : '';
                            $existing_image_urls = $existing_review && !empty($existing_review['image_ids'])
                                ? array_values(array_filter(array_map(function ($attachment_id) {
                                    return wp_get_attachment_image_url((int) $attachment_id, 'thumbnail');
                                }, (array) $existing_review['image_ids'])))
                                : [];
                            ?>
                            <div class="col-12">
                                <div class="border rounded p-3">
                                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                                        <div>
                                            <div class="fw-semibold"><?php echo esc_html((string) ($item['title'] ?? __('Produk', 'velocity-marketplace'))); ?></div>
                                            <div class="small text-muted">
                                                <?php echo esc_html(sprintf(__('Jumlah %d', 'velocity-marketplace'), (int) ($item['qty'] ?? 0))); ?>
                                                <?php if ($existing_review) : ?>
                                                    <?php echo esc_html(' | ' . __('Ulasan tersimpan', 'velocity-marketplace')); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($product_id > 0 && get_permalink($product_id)) : ?>
                                            <a href="<?php echo esc_url(get_permalink($product_id)); ?>" class="btn btn-sm btn-outline-dark"><?php echo esc_html__('Lihat Produk', 'velocity-marketplace'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($existing_review) : ?>
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <div class="small text-muted mb-1"><?php echo esc_html__('Rating', 'velocity-marketplace'); ?></div>
                                                <?php echo RatingRenderer::summary_html($existing_rating, null, ['size' => 16, 'show_count' => false, 'show_value' => false]); ?>
                                            </div>
                                            <?php if ($existing_title !== '') : ?>
                                                <div class="col-md-9">
                                                    <div class="small text-muted mb-1"><?php echo esc_html__('Judul', 'velocity-marketplace'); ?></div>
                                                    <div class="fw-semibold"><?php echo esc_html($existing_title); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <div class="col-12">
                                                <div class="small text-muted mb-1"><?php echo esc_html__('Ulasan', 'velocity-marketplace'); ?></div>
                                                <div><?php echo nl2br(esc_html($existing_content)); ?></div>
                                            </div>
                                            <?php if (!empty($existing_image_urls)) : ?>
                                                <div class="col-12">
                                                    <div class="small text-muted mb-2"><?php echo esc_html__('Foto Ulasan', 'velocity-marketplace'); ?></div>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <?php foreach ($existing_image_urls as $image_url) : ?>
                                                            <img src="<?php echo esc_url((string) $image_url); ?>" alt="<?php echo esc_attr__('Foto ulasan', 'velocity-marketplace'); ?>" class="border rounded" style="width:72px; height:72px; object-fit:cover;">
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else : ?>
                                        <form method="post" enctype="multipart/form-data" class="row g-2">
                                            <input type="hidden" name="vmp_action" value="review_submit">
                                            <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                                            <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
                                            <?php wp_nonce_field('vmp_review_' . $order_id . '_' . $product_id, 'vmp_review_nonce'); ?>
                                            <div class="col-md-3">
                                                <label class="form-label"><?php echo esc_html__('Rating', 'velocity-marketplace'); ?></label>
                                                <select name="rating" class="form-select form-select-sm">
                                                    <?php for ($star = 5; $star >= 1; $star--) : ?>
                                                        <option value="<?php echo esc_attr((string) $star); ?>" <?php selected($existing_rating, $star); ?>>
                                                            <?php echo esc_html(sprintf(__('%d Bintang', 'velocity-marketplace'), $star)); ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-9">
                                                <label class="form-label"><?php echo esc_html__('Judul', 'velocity-marketplace'); ?></label>
                                                <input type="text" name="review_title" class="form-control form-control-sm" value="<?php echo esc_attr($existing_title); ?>" placeholder="<?php echo esc_attr__('Judul ulasan (opsional)', 'velocity-marketplace'); ?>">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label"><?php echo esc_html__('Ulasan', 'velocity-marketplace'); ?></label>
                                                <textarea name="review_content" class="form-control form-control-sm" rows="3" placeholder="<?php echo esc_attr__('Tulis pengalaman belanja dan ulasan kualitas produk Anda', 'velocity-marketplace'); ?>"><?php echo esc_textarea($existing_content); ?></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label"><?php echo esc_html__('Foto Ulasan', 'velocity-marketplace'); ?></label>
                                                <input type="file" name="review_images[]" class="form-control form-control-sm" accept="image/*" multiple>
                                                <div class="form-text"><?php echo esc_html__('Maksimal 3 foto. Mengunggah foto baru akan menggantikan foto ulasan sebelumnya.', 'velocity-marketplace'); ?></div>
                                            </div>
                                            <div class="col-12 text-end">
                                                <button type="submit" class="btn btn-sm btn-dark"><?php echo esc_html__('Kirim Ulasan', 'velocity-marketplace'); ?></button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div></div>

        <div class="card border-0 shadow-sm"><div class="card-body">
            <h3 class="h6 mb-2"><?php echo esc_html__('Unggah Bukti Transfer', 'velocity-marketplace'); ?></h3>
            <form method="post" enctype="multipart/form-data" class="row g-2">
                <input type="hidden" name="vmp_action" value="buyer_upload_transfer">
                <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                <input type="hidden" name="redirect_tab" value="orders">
                <?php wp_nonce_field('vmp_upload_transfer_' . $order_id, 'vmp_transfer_nonce'); ?>
                <div class="col-md-8"><input type="file" name="transfer_proof" class="form-control" accept="image/*,.pdf" required></div>
                <?php if ($transfer_captcha_html !== '') : ?><div class="col-12"><?php echo $transfer_captcha_html; ?></div><?php endif; ?>
                <div class="col-md-4"><button type="submit" class="btn btn-dark w-100"><?php echo esc_html__('Unggah Bukti Transfer', 'velocity-marketplace'); ?></button></div>
            </form>
        </div></div>
        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <div class="table-responsive border rounded"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th><?php echo esc_html__('Invoice', 'velocity-marketplace'); ?></th><th><?php echo esc_html__('Tanggal', 'velocity-marketplace'); ?></th><th><?php echo esc_html__('Status', 'velocity-marketplace'); ?></th><th class="text-end"><?php echo esc_html__('Total', 'velocity-marketplace'); ?></th><th class="text-end"><?php echo esc_html__('Aksi', 'velocity-marketplace'); ?></th></tr></thead><tbody>
        <?php while ($query->have_posts()) : $query->the_post(); $order_id = get_the_ID(); $invoice_meta = (string) get_post_meta($order_id, 'vmp_invoice', true); $status = (string) get_post_meta($order_id, 'vmp_status', true); $total = (float) get_post_meta($order_id, 'vmp_total', true); ?>
            <tr><td><?php echo esc_html($invoice_meta); ?></td><td><?php echo esc_html(get_the_date('d-m-Y H:i', $order_id)); ?></td><td><?php echo esc_html(OrderData::status_label($status)); ?></td><td class="text-end"><?php echo esc_html(number_format($total, 0, ',', '.')); ?></td><td class="text-end"><a class="btn btn-sm btn-outline-dark" href="<?php echo esc_url(add_query_arg(['tab' => 'orders', 'invoice' => $invoice_meta])); ?>"><?php echo esc_html__('Lihat Detail', 'velocity-marketplace'); ?></a></td></tr>
        <?php endwhile; wp_reset_postdata(); ?>
        </tbody></table></div>
    <?php endif; ?>


