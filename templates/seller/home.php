<?php
use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Modules\Review\StarSellerService;
use VelocityMarketplace\Modules\Shipping\ShippingController;
?>
<?php $seller_order_ids = OrderData::seller_orders_query($current_user_id, 120); ?>
<?php $seller_summary = (new StarSellerService())->summary($current_user_id); ?>
<?php
$status_badge_class = static function ($status) {
    $status = (string) $status;
    if ($status === 'completed') {
        return 'bg-success';
    }
    if ($status === 'shipped') {
        return 'bg-primary';
    }
    if ($status === 'processing' || $status === 'pending_verification') {
        return 'bg-warning text-dark';
    }
    if ($status === 'cancelled' || $status === 'refunded') {
        return 'bg-danger';
    }
    return 'bg-secondary';
};
$status_labels = OrderData::statuses();
?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <h3 class="h6 mb-2"><?php echo esc_html__('Ringkasan Toko', 'velocity-marketplace'); ?></h3>
                <div class="mb-2"><?php echo esc_html__('Label:', 'velocity-marketplace'); ?> <?php echo !empty($seller_summary['is_star_seller']) ? '<span class="badge bg-warning text-dark">' . esc_html__('Star Seller', 'velocity-marketplace') . '</span>' : '<span class="badge bg-secondary">' . esc_html__('Toko Aktif', 'velocity-marketplace') . '</span>'; ?></div>
                <div class="small text-muted"><?php echo esc_html__('Pesanan masuk:', 'velocity-marketplace'); ?> <strong><?php echo esc_html(count($seller_order_ids)); ?></strong></div>
                <div class="small text-muted"><?php echo esc_html__('Rating toko:', 'velocity-marketplace'); ?> <strong><?php echo esc_html(number_format((float) ($seller_summary['rating_average'] ?? 0), 1, ',', '.') . '/5'); ?></strong> <?php echo esc_html(sprintf(__('dari %d ulasan', 'velocity-marketplace'), (int) ($seller_summary['rating_count'] ?? 0))); ?></div>
                <div class="small text-muted"><?php echo esc_html__('Pesanan selesai:', 'velocity-marketplace'); ?> <strong><?php echo esc_html((string) (int) ($seller_summary['completed_orders'] ?? 0)); ?></strong></div>
                <?php if (!$profile_complete) : ?><div class="alert alert-warning py-2 mt-2 mb-0"><?php echo esc_html__('Lengkapi profil toko sebelum menambahkan produk baru.', 'velocity-marketplace'); ?></div><?php endif; ?>
            </div></div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <h3 class="h6 mb-2"><?php echo esc_html__('Pesanan Masuk', 'velocity-marketplace'); ?></h3>
                <?php if (empty($seller_order_ids)) : ?>
                    <div class="small text-muted"><?php echo esc_html__('Belum ada pesanan masuk.', 'velocity-marketplace'); ?></div>
                <?php else : ?>
                    <div class="accordion" id="vmpSellerOrders">
                        <?php foreach ($seller_order_ids as $idx => $order_id) :
                            $invoice_meta = (string) get_post_meta($order_id, 'vmp_invoice', true);
                            $status = (string) get_post_meta($order_id, 'vmp_status', true);
                            $customer = get_post_meta($order_id, 'vmp_customer', true);
                            $seller_items = OrderData::seller_items($order_id, $current_user_id);
                            $seller_total = OrderData::seller_total($order_id, $current_user_id);
                            $seller_shipping = OrderData::seller_shipping_group($order_id, $current_user_id);
                            $transfer_proof_id = (int) get_post_meta($order_id, 'vmp_transfer_proof_id', true);
                            $transfer_proof_url = $transfer_proof_id > 0 ? wp_get_attachment_url($transfer_proof_id) : '';
                            $receipt_no = (string) (($seller_shipping['receipt_no'] ?? '') ?: get_post_meta($order_id, 'vmp_receipt_no', true));
                            $receipt_courier = (string) (($seller_shipping['receipt_courier'] ?? '') ?: ($seller_shipping['courier'] ?? get_post_meta($order_id, 'vmp_receipt_courier', true)));
                            $seller_note = (string) (($seller_shipping['seller_note'] ?? '') ?: get_post_meta($order_id, 'vmp_seller_note', true));
                            $seller_status = OrderData::shipping_group_status(is_array($seller_shipping) ? $seller_shipping : [], (string) get_post_meta($order_id, 'vmp_status', true));
                            $seller_requires_shipping = OrderData::seller_requires_shipping($order_id, $current_user_id);
                            $seller_destination = isset($seller_shipping['destination']) && is_array($seller_shipping['destination']) ? $seller_shipping['destination'] : [];
                            $seller_destination_text = trim(implode(', ', array_filter([
                                (string) ($seller_destination['subdistrict_destination_name'] ?? ''),
                                (string) ($seller_destination['city_destination_name'] ?? ''),
                                (string) ($seller_destination['province_destination_name'] ?? ''),
                            ])));
                            $waybill_data = null;
                            if ($receipt_no !== '' && $receipt_courier !== '') {
                                $maybe_waybill = ShippingController::fetch_waybill($receipt_no, $receipt_courier);
                                if (!is_wp_error($maybe_waybill) && is_array($maybe_waybill)) {
                                    $waybill_data = $maybe_waybill;
                                }
                            }
                            if (!is_array($customer)) {
                                $customer = [];
                            }
                            ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="vmpOrderHeading<?php echo esc_attr($order_id); ?>">
                                    <button class="accordion-button <?php echo $idx > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#vmpOrderCollapse<?php echo esc_attr($order_id); ?>" aria-expanded="<?php echo $idx === 0 ? 'true' : 'false'; ?>">
                                        <span class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="fw-semibold"><?php echo esc_html($invoice_meta); ?></span>
                                            <span class="badge <?php echo esc_attr($status_badge_class($seller_status)); ?>"><?php echo esc_html(OrderData::status_label($seller_status)); ?></span>
                                            <span class="text-muted small"><?php echo esc_html($money($seller_total)); ?></span>
                                        </span>
                                    </button>
                                </h2>
                                <div id="vmpOrderCollapse<?php echo esc_attr($order_id); ?>" class="accordion-collapse collapse <?php echo $idx === 0 ? 'show' : ''; ?>" data-bs-parent="#vmpSellerOrders">
                                    <div class="accordion-body">
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <div><strong><?php echo esc_html__('Pembeli:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($customer['name'] ?? '-'); ?></div>
                                                <div><strong><?php echo esc_html__('Telepon:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($customer['phone'] ?? '-'); ?></div>
                                                <div><strong><?php echo esc_html__('Alamat:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($customer['address'] ?? '-'); ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <?php if ($seller_requires_shipping) : ?>
                                                    <div><strong><?php echo esc_html__('Kurir:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($receipt_courier !== '' ? $receipt_courier : '-'); ?></div>
                                                    <div><strong><?php echo esc_html__('Nomor Resi:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($receipt_no !== '' ? $receipt_no : '-'); ?></div>
                                                    <div><strong><?php echo esc_html__('Pengiriman untuk Toko Ini:', 'velocity-marketplace'); ?></strong> <?php echo esc_html($money((float) ($seller_shipping['cost'] ?? 0))); ?></div>
                                                <?php else : ?>
                                                    <div><strong><?php echo esc_html__('Jenis Pesanan:', 'velocity-marketplace'); ?></strong> <?php echo esc_html__('Produk Digital', 'velocity-marketplace'); ?></div>
                                                    <div><strong><?php echo esc_html__('Pemenuhan untuk Toko Ini:', 'velocity-marketplace'); ?></strong> <?php echo esc_html__('Tidak memerlukan pengiriman.', 'velocity-marketplace'); ?></div>
                                                <?php endif; ?>
                                                <?php if ($transfer_proof_url) : ?>
                                                    <a href="<?php echo esc_url($transfer_proof_url); ?>" class="btn btn-sm btn-outline-primary mt-2" target="_blank"><?php echo esc_html__('Lihat Bukti Pembayaran', 'velocity-marketplace'); ?></a>
                                                <?php else : ?>
                                                    <div class="small text-muted mt-1"><?php echo esc_html__('Bukti pembayaran belum diunggah.', 'velocity-marketplace'); ?></div>
                                                <?php endif; ?>
                                                <?php $buyer_contact_id = isset($customer['user_id']) ? (int) $customer['user_id'] : \VelocityMarketplace\Modules\Order\OrderData::buyer_id($order_id); ?>
                                                <?php if ($buyer_contact_id > 0) : ?>
                                                    <div class="mt-2">
                                                        <a href="<?php echo esc_url(add_query_arg(['tab' => 'messages', 'message_to' => $buyer_contact_id, 'message_order' => $order_id])); ?>" class="btn btn-sm btn-outline-dark"><?php echo esc_html__('Kirim Pesan ke Pembeli', 'velocity-marketplace'); ?></a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php
                                        $tracking_rows = [];
                                        if ($waybill_data && isset($waybill_data['data']['manifest']) && is_array($waybill_data['data']['manifest'])) {
                                            $tracking_rows = $waybill_data['data']['manifest'];
                                        } elseif ($waybill_data && isset($waybill_data['data']['history']) && is_array($waybill_data['data']['history'])) {
                                            $tracking_rows = $waybill_data['data']['history'];
                                        }
                                        ?>
                                        <?php if (!empty($tracking_rows)) : ?>
                                            <div class="border rounded p-3 mb-3 vmp-tracking-box">
                                                <div class="fw-semibold mb-2"><?php echo esc_html__('Pelacakan Resi', 'velocity-marketplace'); ?></div>
                                                <?php foreach ($tracking_rows as $tracking_row) : ?>
                                                    <div class="border-top py-2 vmp-tracking-box__item">
                                                        <div class="fw-semibold"><?php echo esc_html((string) ($tracking_row['manifest_description'] ?? $tracking_row['description'] ?? '-')); ?></div>
                                                        <div class="small text-muted"><?php echo esc_html(trim((string) (($tracking_row['manifest_date'] ?? '') . ' ' . ($tracking_row['manifest_time'] ?? $tracking_row['date'] ?? '')))); ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="table-responsive mb-3"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th><?php echo esc_html__('Produk', 'velocity-marketplace'); ?></th><th class="text-center"><?php echo esc_html__('Qty', 'velocity-marketplace'); ?></th><th class="text-end"><?php echo esc_html__('Subtotal', 'velocity-marketplace'); ?></th></tr></thead><tbody>
                                        <?php foreach ($seller_items as $line) : ?>
                                            <tr><td><?php echo esc_html(isset($line['title']) ? (string) $line['title'] : '-'); ?></td><td class="text-center"><?php echo esc_html((string) ((int) ($line['qty'] ?? 0))); ?></td><td class="text-end"><?php echo esc_html($money((float) ($line['subtotal'] ?? 0))); ?></td></tr>
                                        <?php endforeach; ?>
                                        </tbody></table></div>

                                        <div class="border rounded p-3 bg-light-subtle vmp-shipping-card">
                                            <div class="fw-semibold mb-3"><?php echo esc_html($seller_requires_shipping ? __('Pengiriman untuk Toko Ini', 'velocity-marketplace') : __('Pemenuhan untuk Produk Digital', 'velocity-marketplace')); ?></div>
                                            <form method="post" class="row g-3">
                                                <input type="hidden" name="vmp_action" value="seller_update_order">
                                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                                                <?php wp_nonce_field('vmp_seller_order_' . $order_id, 'vmp_seller_order_nonce'); ?>
                                                <div class="col-md-4">
                                                    <label class="form-label"><?php echo esc_html__('Status Pesanan', 'velocity-marketplace'); ?></label>
                                                    <select name="order_status" class="form-select form-select-sm">
                                                        <?php foreach ($status_labels as $status_key => $status_text) : ?>
                                                            <option value="<?php echo esc_attr($status_key); ?>" <?php selected($seller_status, $status_key); ?>><?php echo esc_html($status_text); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <?php if ($seller_requires_shipping) : ?>
                                                    <div class="col-md-4">
                                                        <label class="form-label"><?php echo esc_html__('Kurir', 'velocity-marketplace'); ?></label>
                                                        <input type="text" name="receipt_courier" class="form-control form-control-sm" value="<?php echo esc_attr($receipt_courier); ?>" placeholder="<?php echo esc_attr__('JNE/SICEPAT/JNT', 'velocity-marketplace'); ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label"><?php echo esc_html__('Nomor Resi', 'velocity-marketplace'); ?></label>
                                                        <input type="text" name="receipt_no" class="form-control form-control-sm" value="<?php echo esc_attr($receipt_no); ?>" placeholder="<?php echo esc_attr__('Masukkan nomor resi', 'velocity-marketplace'); ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label"><?php echo esc_html__('Layanan', 'velocity-marketplace'); ?></label>
                                                        <input type="text" class="form-control form-control-sm" value="<?php echo esc_attr((string) ($seller_shipping['service'] ?? '-')); ?>" readonly>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label"><?php echo esc_html__('Biaya Pengiriman', 'velocity-marketplace'); ?></label>
                                                        <input type="text" class="form-control form-control-sm" value="<?php echo esc_attr($money((float) ($seller_shipping['cost'] ?? 0))); ?>" readonly>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label"><?php echo esc_html__('Tujuan', 'velocity-marketplace'); ?></label>
                                                        <input type="text" class="form-control form-control-sm" value="<?php echo esc_attr($seller_destination_text !== '' ? $seller_destination_text : '-'); ?>" readonly>
                                                    </div>
                                                <?php else : ?>
                                                    <div class="col-md-8">
                                                        <div class="small text-muted pt-4"><?php echo esc_html__('Pesanan ini hanya berisi produk digital, jadi seller cukup mengubah status pesanan dan memberi catatan bila perlu.', 'velocity-marketplace'); ?></div>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="col-12">
                                                    <label class="form-label"><?php echo esc_html__('Catatan Penjual', 'velocity-marketplace'); ?></label>
                                                    <textarea name="seller_note" class="form-control form-control-sm" rows="2" placeholder="<?php echo esc_attr__('Tambahkan catatan untuk pembeli', 'velocity-marketplace'); ?>"><?php echo esc_textarea($seller_note); ?></textarea>
                                                </div>
                                                <div class="col-12 text-end">
                                                    <button type="submit" class="btn btn-sm btn-dark"><?php echo esc_html__('Simpan Perubahan Pesanan', 'velocity-marketplace'); ?></button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div></div>
        </div>
    </div>


