<?php
$captcha_html = \VelocityMarketplace\Support\CaptchaBridge::render('#vmp-checkout-form');
$settings = get_option('velocity_marketplace_settings', []);
if (!is_array($settings)) {
    $settings = [];
}
$active_payment_methods = isset($settings['payment_methods']) && is_array($settings['payment_methods'])
    ? array_values(array_unique(array_map('sanitize_key', $settings['payment_methods'])))
    : ['bank'];
if (empty($active_payment_methods)) {
    $active_payment_methods = ['bank'];
}
$payment_labels = [
    'bank' => 'Transfer Bank',
    'duitku' => 'Duitku',
    'paypal' => 'PayPal',
    'cod' => 'COD',
];
?>
<div class="container py-4 vmp-wrap" x-data="vmpCheckout()" x-init="init()">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-0">Checkout</h2>
            <small class="text-muted">Captcha dari plugin Velocity Addons</small>
        </div>
        <a class="btn btn-sm btn-outline-dark" :href="cartUrl">Kembali ke Keranjang</a>
    </div>

    <div class="alert alert-danger py-2" x-show="errorMessage" x-text="errorMessage"></div>
    <div class="alert alert-success py-2" x-show="successMessage" x-text="successMessage"></div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form id="vmp-checkout-form" @submit.prevent="submitOrder()">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nama</label>
                                <input type="text" class="form-control" x-model.trim="form.name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telepon</label>
                                <input type="text" class="form-control" x-model.trim="form.phone" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" x-model.trim="form.email" placeholder="opsional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kode Pos</label>
                                <input type="text" class="form-control" x-model.trim="form.postal_code">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Alamat</label>
                                <textarea class="form-control" rows="3" x-model.trim="form.address" required></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Kurir</label>
                                <input type="text" class="form-control" x-model.trim="form.shipping_courier" placeholder="JNE">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Layanan</label>
                                <input type="text" class="form-control" x-model.trim="form.shipping_service" placeholder="REG">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Biaya Ongkir</label>
                                <input type="number" min="0" step="1000" class="form-control" x-model.number="form.shipping_cost">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Pembayaran</label>
                                <select class="form-select" x-model="form.payment_method">
                                    <?php foreach ($active_payment_methods as $method) :
                                        $label = isset($payment_labels[$method]) ? $payment_labels[$method] : strtoupper($method);
                                        ?>
                                        <option value="<?php echo esc_attr($method); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kode Kecamatan (opsional)</label>
                                <input type="text" class="form-control" x-model.trim="form.subdistrict_destination">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Catatan</label>
                                <textarea class="form-control" rows="2" x-model.trim="form.notes"></textarea>
                            </div>
                        </div>

                        <?php if (!empty($captcha_html)) : ?>
                            <div class="mt-3">
                                <?php echo $captcha_html; ?>
                            </div>
                        <?php endif; ?>

                        <button class="btn btn-primary mt-4" type="submit" :disabled="submitting || items.length === 0">
                            <span x-show="!submitting">Konfirmasi Pesanan</span>
                            <span x-show="submitting">Memproses...</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="h6">Ringkasan Pesanan</h3>
                    <div class="small text-muted mb-3" x-text="items.length + ' produk di keranjang'"></div>
                    <template x-for="item in items" :key="item.id + '-' + optionKey(item)">
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <div class="pe-2">
                                <div class="fw-semibold small" x-text="item.title"></div>
                                <div class="text-muted vmp-xs" x-text="item.qty + ' x ' + formatPrice(item.price)"></div>
                            </div>
                            <div class="fw-semibold small" x-text="formatPrice(item.subtotal)"></div>
                        </div>
                    </template>
                    <div class="d-flex justify-content-between pt-3">
                        <span>Total</span>
                        <strong class="text-danger" x-text="formatPrice(total)"></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
