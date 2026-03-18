<?php
use VelocityMarketplace\Support\OrderData;

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
            <h2 class="h4 mb-0">Tracking Pesanan</h2>
            <small class="text-muted">Lacak order menggunakan kode invoice</small>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-md-8">
                    <label class="form-label">Kode Invoice</label>
                    <input type="text" name="invoice" class="form-control" value="<?php echo esc_attr($invoice); ?>" placeholder="Contoh: VMP-20260304-123456" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-dark w-100">Lacak Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($invoice !== '' && !$order) : ?>
        <div class="alert alert-warning mb-0">Invoice tidak ditemukan.</div>
    <?php elseif ($order) : ?>
        <?php
        $order_id = (int) $order->ID;
        $status = (string) get_post_meta($order_id, 'vmp_status', true);
        $payment = (string) get_post_meta($order_id, 'vmp_payment_method', true);
        $shipping = get_post_meta($order_id, 'vmp_shipping', true);
        $receipt_no = (string) get_post_meta($order_id, 'vmp_receipt_no', true);
        $receipt_courier = (string) get_post_meta($order_id, 'vmp_receipt_courier', true);
        if (!is_array($shipping)) {
            $shipping = [];
        }
        ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div><strong>Invoice:</strong> <?php echo esc_html($invoice); ?></div>
                        <div><strong>Status:</strong> <?php echo esc_html(OrderData::status_label($status)); ?></div>
                        <div><strong>Pembayaran:</strong> <?php echo esc_html($payment !== '' ? $payment : '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div><strong>Kurir:</strong> <?php echo esc_html($receipt_courier !== '' ? $receipt_courier : ($shipping['courier'] ?? '-')); ?></div>
                        <div><strong>Layanan:</strong> <?php echo esc_html($shipping['service'] ?? '-'); ?></div>
                        <div><strong>No Resi:</strong> <?php echo esc_html($receipt_no !== '' ? $receipt_no : '-'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
