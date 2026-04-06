<?php
$captcha_html = \VelocityMarketplace\Modules\Captcha\CaptchaBridge::render('#vmp-checkout-form');
$active_payment_methods = \VelocityMarketplace\Support\Settings::payment_methods();
$payment_labels = [
    'bank' => __('Transfer Bank', 'velocity-marketplace'),
    'qris' => 'QRIS',
    'duitku' => 'Duitku',
    'paypal' => 'PayPal',
    'cod' => 'COD',
];
$bank_accounts = \VelocityMarketplace\Support\Settings::bank_accounts();
$qris = \VelocityMarketplace\Support\Settings::qris_details();
?>
<div class="container py-4 vmp-wrap" x-data="vmpCheckout()" x-init="init()">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-0"><?php echo esc_html__('Checkout', 'velocity-marketplace'); ?></h2>
            <small class="text-muted"><?php echo esc_html__('Lengkapi detail pengiriman dan pembayaran untuk membuat pesanan.', 'velocity-marketplace'); ?></small>
        </div>
        <a class="btn btn-sm btn-outline-dark" :href="cartUrl"><?php echo esc_html__('Kembali ke Keranjang', 'velocity-marketplace'); ?></a>
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
                                <label class="form-label"><?php echo esc_html__('Nama Penerima', 'velocity-marketplace'); ?></label>
                                <input type="text" class="form-control" x-model.trim="form.name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php echo esc_html__('Nomor Telepon', 'velocity-marketplace'); ?></label>
                                <input type="text" class="form-control" x-model.trim="form.phone" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php echo esc_html__('Email', 'velocity-marketplace'); ?></label>
                                <input type="email" class="form-control" x-model.trim="form.email" placeholder="<?php echo esc_attr__('Opsional', 'velocity-marketplace'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php echo esc_html__('Kode Pos', 'velocity-marketplace'); ?></label>
                                <input type="text" class="form-control" x-model.trim="form.postal_code">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?php echo esc_html__('Alamat', 'velocity-marketplace'); ?></label>
                                <textarea class="form-control" rows="3" x-model.trim="form.address" required></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo esc_html__('Provinsi', 'velocity-marketplace'); ?></label>
                                <select class="form-select" x-ref="provinceSelect" x-model="form.destination_province_id" @change="onProvinceChange()" :disabled="isLoadingProvinces">
                                    <option value=""><?php echo esc_html__('Pilih provinsi', 'velocity-marketplace'); ?></option>
                                    <template x-for="prov in provinces" :key="prov.province_id">
                                        <option :value="prov.province_id" x-text="prov.province"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo esc_html__('Kota/Kabupaten', 'velocity-marketplace'); ?></label>
                                <select class="form-select" x-ref="citySelect" x-model="form.destination_city_id" @change="onCityChange()" :disabled="!form.destination_province_id || isLoadingCities">
                                    <option value=""><?php echo esc_html__('Pilih kota atau kabupaten', 'velocity-marketplace'); ?></option>
                                    <template x-for="city in cities" :key="city.city_id">
                                        <option :value="city.city_id" x-text="(city.type ? city.type + ' ' : '') + city.city_name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo esc_html__('Kecamatan', 'velocity-marketplace'); ?></label>
                                <select class="form-select" x-ref="subdistrictSelect" x-model="form.destination_subdistrict_id" @change="onSubdistrictChange()" :disabled="!form.destination_city_id || isLoadingSubdistricts">
                                    <option value=""><?php echo esc_html__('Pilih kecamatan', 'velocity-marketplace'); ?></option>
                                    <template x-for="subdistrict in subdistricts" :key="subdistrict.subdistrict_id">
                                        <option :value="subdistrict.subdistrict_id" x-text="subdistrict.subdistrict_name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php echo esc_html__('Pembayaran', 'velocity-marketplace'); ?></label>
                                <select class="form-select" x-model="form.payment_method" @change="onPaymentMethodChange()">
                                    <?php foreach ($active_payment_methods as $method) :
                                        $label = isset($payment_labels[$method]) ? $payment_labels[$method] : strtoupper($method);
                                        ?>
                                        <option value="<?php echo esc_attr($method); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12" x-show="form.payment_method === 'bank'">
                                <?php if (empty($bank_accounts)) : ?>
                                    <div class="alert alert-warning mb-0">
                                        <?php echo esc_html__('Rekening tujuan transfer belum tersedia. Silakan hubungi admin marketplace atau pilih metode pembayaran lain.', 'velocity-marketplace'); ?>
                                    </div>
                                <?php else : ?>
                                    <div class="border rounded p-3 bg-light-subtle">
                                        <div class="fw-semibold mb-2"><?php echo esc_html__('Rekening Tujuan Transfer', 'velocity-marketplace'); ?></div>
                                        <div class="small text-muted mb-3"><?php echo esc_html__('Silakan transfer ke salah satu rekening berikut, lalu unggah bukti pembayaran setelah pesanan dibuat.', 'velocity-marketplace'); ?></div>
                                        <div class="row g-3">
                                            <?php foreach ($bank_accounts as $bank_account) : ?>
                                                <div class="col-md-6">
                                                    <div class="border rounded p-3 h-100 bg-white">
                                                        <div class="fw-semibold"><?php echo esc_html((string) ($bank_account['bank_name'] ?? '-')); ?></div>
                                                        <div class="small text-muted mt-2"><?php echo esc_html__('Nomor Rekening', 'velocity-marketplace'); ?></div>
                                                        <div class="fw-semibold"><?php echo esc_html((string) ($bank_account['account_number'] ?? '-')); ?></div>
                                                        <div class="small text-muted mt-2"><?php echo esc_html__('Atas Nama', 'velocity-marketplace'); ?></div>
                                                        <div><?php echo esc_html((string) ($bank_account['account_holder'] ?? '-')); ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12" x-show="form.payment_method === 'qris'">
                                <div class="border rounded p-3 bg-light-subtle">
                                    <div class="fw-semibold mb-2"><?php echo esc_html__('Pembayaran QRIS', 'velocity-marketplace'); ?></div>
                                    <div class="small text-muted mb-3"><?php echo esc_html($qris['label']); ?></div>
                                    <?php if (!empty($qris['image_url'])) : ?>
                                        <div class="text-center">
                                            <img src="<?php echo esc_url($qris['image_url']); ?>" alt="<?php echo esc_attr__('QRIS', 'velocity-marketplace'); ?>" class="img-fluid rounded border" style="max-width:280px;">
                                        </div>
                                    <?php else : ?>
                                        <div class="alert alert-warning mb-0">
                                            <?php echo esc_html__('QRIS belum dikonfigurasi di Pengaturan Toko VD Store.', 'velocity-marketplace'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-12" x-show="shippingContextMessage">
                                <div class="alert alert-warning py-2 mb-0" x-text="shippingContextMessage"></div>
                            </div>
                            <div class="col-12" x-show="shippingGroups.length > 0">
                                <label class="form-label"><?php echo esc_html__('Pilih Layanan Pengiriman per Toko', 'velocity-marketplace'); ?></label>
                                <div class="row g-3">
                                    <template x-for="group in shippingGroups" :key="group.seller_id">
                                        <div class="col-12">
                                            <div class="border rounded p-3">
                                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                                    <div>
                                                        <div class="fw-semibold" x-text="group.seller_name"></div>
                                                        <div class="small text-muted">
                                                            <span x-text="group.items_count + ' item'"></span>
                                                            <span> | </span>
                                                            <span x-text="formatPrice(group.subtotal)"></span>
                                                            <span> | </span>
                                                            <span x-text="(group.weight_grams / 1000).toFixed(2) + ' kg'"></span>
                                                        </div>
                                                    </div>
                                                    <div class="small text-muted d-flex flex-wrap gap-2">
                                                        <template x-for="courier in group.couriers" :key="courier.code">
                                                            <span class="badge bg-secondary" x-text="courier.name"></span>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div class="small text-muted mb-2" x-show="group.loading"><?php echo esc_html__('Memuat opsi pengiriman...', 'velocity-marketplace'); ?></div>
                                                <div class="small text-danger mb-2" x-show="group.message" x-text="group.message"></div>
                                                <div class="small text-muted mb-2" x-show="form.payment_method === 'cod' && group.cod_enabled">
                                                    <?php echo esc_html__('COD tersedia untuk tujuan:', 'velocity-marketplace'); ?>
                                                    <span x-text="group.cod_city_names.length ? group.cod_city_names.join(', ') : '-'"></span>
                                                </div>
                                                <div class="row g-2" x-show="group.services.length > 0">
                                                    <template x-for="opt in group.services" :key="group.seller_id + ':' + opt.code + ':' + opt.service">
                                                        <div class="col-md-6">
                                                            <button type="button" class="btn btn-outline-dark w-100 text-start" @click="selectShipping(group, opt)" :class="group.selectedKey === (opt.code + ':' + opt.service) ? 'active' : ''">
                                                                <div class="fw-semibold" x-text="opt.name + ' ' + opt.service"></div>
                                                                <div class="small text-muted" x-text="opt.description || '-'"></div>
                                                                <div class="small text-muted" x-text="opt.etd ? ('<?php echo esc_attr__('Estimasi', 'velocity-marketplace'); ?> ' + opt.etd) : ''"></div>
                                                                <div class="fw-semibold text-danger" x-text="formatPrice(opt.cost)"></div>
                                                            </button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?php echo esc_html__('Kupon atau Voucher', 'velocity-marketplace'); ?></label>
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control" x-model.trim="coupon.code" placeholder="<?php echo esc_attr__('Masukkan kode promo', 'velocity-marketplace'); ?>">
                                    <button class="btn btn-outline-dark" type="button" @click="applyCoupon()" :disabled="coupon.loading">
                                        <span x-show="!coupon.loading"><?php echo esc_html__('Terapkan', 'velocity-marketplace'); ?></span>
                                        <span x-show="coupon.loading"><?php echo esc_html__('Memeriksa...', 'velocity-marketplace'); ?></span>
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" @click="removeCoupon()" x-show="coupon.applied"><?php echo esc_html__('Hapus', 'velocity-marketplace'); ?></button>
                                </div>
                                <div class="small mt-2" :class="coupon.applied ? 'text-success' : 'text-muted'" x-show="coupon.message" x-text="coupon.message"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?php echo esc_html__('Catatan Pesanan', 'velocity-marketplace'); ?></label>
                                <textarea class="form-control" rows="2" x-model.trim="form.notes"></textarea>
                            </div>
                        </div>

                        <?php if (!empty($captcha_html)) : ?>
                            <div class="mt-3">
                                <?php echo $captcha_html; ?>
                            </div>
                        <?php endif; ?>

                        <button class="btn btn-primary mt-4" type="submit" :disabled="submitting || items.length === 0">
                            <span x-show="!submitting"><?php echo esc_html__('Buat Pesanan', 'velocity-marketplace'); ?></span>
                            <span x-show="submitting"><?php echo esc_html__('Memproses Pesanan...', 'velocity-marketplace'); ?></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="h6"><?php echo esc_html__('Ringkasan Pesanan', 'velocity-marketplace'); ?></h3>
                    <div class="small text-muted mb-3" x-text="items.length + ' <?php echo esc_attr__('produk di keranjang', 'velocity-marketplace'); ?>'"></div>
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
                        <span><?php echo esc_html__('Subtotal Produk', 'velocity-marketplace'); ?></span>
                        <strong x-text="formatPrice(subtotal)"></strong>
                    </div>
                    <div class="d-flex justify-content-between pt-3">
                        <span><?php echo esc_html__('Pengiriman', 'velocity-marketplace'); ?></span>
                        <strong x-text="formatPrice(form.shipping_cost || 0)"></strong>
                    </div>
                    <div class="d-flex justify-content-between pt-2" x-show="coupon.applied">
                        <span x-text="couponLabel()"></span>
                        <strong class="text-success" x-text="'- ' + formatPrice(coupon.applied ? coupon.applied.discount : 0)"></strong>
                    </div>
                    <div class="d-flex justify-content-between pt-2">
                        <span><?php echo esc_html__('Total Bayar', 'velocity-marketplace'); ?></span>
                        <strong class="text-danger" x-text="formatPrice(total)"></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

