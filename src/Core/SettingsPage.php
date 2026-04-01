<?php

namespace VelocityMarketplace\Core;

use VelocityMarketplace\Support\Settings;

class SettingsPage
{
    private $page_hook = '';

    public function register()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_setting']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu()
    {
        $this->page_hook = add_submenu_page(
            'edit.php?post_type=vmp_product',
            __('Pengaturan Marketplace', 'velocity-marketplace'),
            __('Settings', 'velocity-marketplace'),
            'manage_options',
            'vmp-settings',
            [$this, 'render_page']
        );
    }

    public function register_setting()
    {
        register_setting(
            'vmp_settings_group',
            VMP_SETTINGS_OPTION,
            [$this, 'sanitize_settings']
        );
    }

    public function sanitize_settings($input)
    {
        $service = new SettingsService();
        return $service->sanitize($input);
    }

    public function enqueue_assets($hook)
    {
        if ($hook !== $this->page_hook) {
            return;
        }

        wp_enqueue_editor();

        wp_register_script(
            'alpinejs',
            'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js',
            [],
            null,
            true
        );

        wp_enqueue_script(
            'velocity-marketplace-admin-settings-js',
            VMP_URL . 'assets/js/admin-settings.js',
            [],
            VMP_VERSION,
            true
        );

        wp_enqueue_script('alpinejs');

        $service = new SettingsService();
        wp_localize_script('velocity-marketplace-admin-settings-js', 'vmpAdminSettings', [
            'restUrl' => esc_url_raw(rest_url('velocity-marketplace/v1/settings')),
            'nonce' => wp_create_nonce('wp_rest'),
            'initialSettings' => $service->get_settings_payload(),
            'popularBanks' => Settings::popular_bank_labels(),
        ]);
    }

    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $service = new SettingsService();
        $settings_payload = $service->get_settings_payload();
        $editor_settings = [
            'textarea_rows' => 14,
            'media_buttons' => false,
            'teeny' => false,
            'tinymce' => true,
            'quicktags' => true,
        ];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Pengaturan Velocity Marketplace', 'velocity-marketplace'); ?></h1>
            <style>
                .vmp-admin-settings {
                    max-width: 1180px;
                    margin-top: 20px;
                }
                .vmp-admin-settings__notice {
                    margin: 0 0 16px;
                }
                .vmp-settings-tabs {
                    display: flex;
                    gap: 8px;
                    margin: 0 0 16px;
                    flex-wrap: wrap;
                }
                .vmp-settings-tab {
                    border: 1px solid #dcdcde;
                    background: #fff;
                    color: #1d2327;
                    border-radius: 999px;
                    padding: 10px 16px;
                    font-size: 13px;
                    font-weight: 600;
                    line-height: 1;
                    cursor: pointer;
                    transition: all .18s ease;
                }
                .vmp-settings-tab:hover {
                    border-color: #2271b1;
                    color: #2271b1;
                }
                .vmp-settings-tab.is-active {
                    background: #2271b1;
                    border-color: #2271b1;
                    color: #fff;
                }
                .vmp-settings-panel {
                    display: none;
                }
                .vmp-settings-panel.is-active {
                    display: block;
                }
                .vmp-settings-card {
                    background: #fff;
                    border: 1px solid #dcdcde;
                    border-radius: 10px;
                    padding: 20px;
                }
                .vmp-settings-card + .vmp-settings-card {
                    margin-top: 16px;
                }
                .vmp-bank-settings__title {
                    margin: 0 0 6px;
                    font-size: 16px;
                    font-weight: 600;
                }
                .vmp-bank-settings__desc {
                    margin: 0 0 16px;
                    color: #50575e;
                }
                .vmp-bank-settings__section + .vmp-bank-settings__section {
                    margin-top: 24px;
                }
                .vmp-bank-settings__section-title {
                    margin: 0 0 12px;
                    font-size: 14px;
                    font-weight: 600;
                }
                .vmp-bank-settings__toolbar {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 12px;
                    flex-wrap: wrap;
                }
                .vmp-bank-settings__toolbar .description {
                    margin: 0;
                }
                .vmp-bank-settings__empty {
                    margin: 0 0 12px;
                    color: #50575e;
                    font-style: italic;
                }
                .vmp-bank-settings__rows {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                }
                .vmp-bank-settings__row {
                    display: grid;
                    grid-template-columns: minmax(220px, 260px) minmax(220px, 1fr) minmax(220px, 1fr);
                    gap: 12px;
                    align-items: end;
                    padding: 14px;
                    border: 1px solid #dcdcde;
                    border-radius: 8px;
                    background: #fff;
                }
                .vmp-bank-settings__field label {
                    display: block;
                    margin-bottom: 6px;
                    font-weight: 500;
                }
                .vmp-bank-settings__field input,
                .vmp-bank-settings__field select {
                    width: 100%;
                    max-width: none;
                }
                .vmp-bank-settings__row-actions {
                    display: flex;
                    justify-content: flex-end;
                    grid-column: 1 / -1;
                }
                .vmp-admin-settings__footer {
                    margin-top: 16px;
                }
                .vmp-email-settings__section + .vmp-email-settings__section {
                    margin-top: 24px;
                    padding-top: 24px;
                    border-top: 1px solid #dcdcde;
                }
                .vmp-email-settings__title {
                    margin: 0 0 6px;
                    font-size: 16px;
                    font-weight: 600;
                }
                .vmp-email-settings__desc {
                    margin: 0 0 16px;
                    color: #50575e;
                }
                .vmp-email-settings__grid {
                    display: grid;
                    grid-template-columns: minmax(240px, 280px) minmax(0, 1fr);
                    gap: 18px;
                    align-items: start;
                }
                .vmp-email-settings__help {
                    color: #50575e;
                    font-size: 13px;
                    line-height: 1.6;
                }
                .vmp-email-settings__help code {
                    font-size: 12px;
                }
                .vmp-email-settings__field {
                    margin-bottom: 16px;
                }
                .vmp-email-settings__field label {
                    display: block;
                    margin-bottom: 6px;
                    font-weight: 600;
                }
                .vmp-email-settings__editor .wp-editor-wrap {
                    width: 100%;
                }
                @media (max-width: 1100px) {
                    .vmp-bank-settings__row {
                        grid-template-columns: 1fr;
                    }
                    .vmp-email-settings__grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>
            <div class="vmp-admin-settings" x-data="vmpAdminSettingsPage()" x-init="init()">
                <div class="notice notice-success is-dismissible vmp-admin-settings__notice" x-show="saveMessage" style="display:none;">
                    <p x-text="saveMessage"></p>
                </div>
                <div class="notice notice-error is-dismissible vmp-admin-settings__notice" x-show="saveError" style="display:none;">
                    <p x-text="saveError"></p>
                </div>

                <div class="vmp-settings-tabs" role="tablist" aria-label="<?php echo esc_attr__('Pengaturan Marketplace', 'velocity-marketplace'); ?>">
                    <button type="button" class="vmp-settings-tab" :class="{ 'is-active': activeTab === 'general' }" @click="setTab('general')"><?php echo esc_html__('Pengaturan Umum', 'velocity-marketplace'); ?></button>
                    <button type="button" class="vmp-settings-tab" :class="{ 'is-active': activeTab === 'bank' }" @click="setTab('bank')"><?php echo esc_html__('Pengaturan Bank', 'velocity-marketplace'); ?></button>
                    <button type="button" class="vmp-settings-tab" :class="{ 'is-active': activeTab === 'email' }" @click="setTab('email')"><?php echo esc_html__('Template Email', 'velocity-marketplace'); ?></button>
                </div>

                <div class="vmp-settings-panel" :class="{ 'is-active': activeTab === 'general' }">
                    <div class="vmp-settings-card">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="vmp_currency"><?php echo esc_html__('Mata Uang', 'velocity-marketplace'); ?></label></th>
                                    <td>
                                        <select id="vmp_currency" x-model="form.currency">
                                            <option value="IDR">IDR</option>
                                            <option value="USD">USD</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="vmp_currency_symbol"><?php echo esc_html__('Simbol Mata Uang', 'velocity-marketplace'); ?></label></th>
                                    <td>
                                        <input id="vmp_currency_symbol" type="text" class="regular-text" x-model="form.currency_symbol">
                                        <p class="description"><?php echo esc_html__('Contoh: Rp, $, USD.', 'velocity-marketplace'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="vmp_default_order_status"><?php echo esc_html__('Status Pesanan Default', 'velocity-marketplace'); ?></label></th>
                                    <td>
                                        <select id="vmp_default_order_status" x-model="form.default_order_status">
                                            <option value="pending_payment"><?php echo esc_html__('Menunggu Pembayaran', 'velocity-marketplace'); ?></option>
                                            <option value="pending_verification"><?php echo esc_html__('Pending Verification', 'velocity-marketplace'); ?></option>
                                            <option value="processing"><?php echo esc_html__('Processing', 'velocity-marketplace'); ?></option>
                                            <option value="shipped"><?php echo esc_html__('Shipped', 'velocity-marketplace'); ?></option>
                                            <option value="completed"><?php echo esc_html__('Completed', 'velocity-marketplace'); ?></option>
                                            <option value="cancelled"><?php echo esc_html__('Cancelled', 'velocity-marketplace'); ?></option>
                                            <option value="refunded"><?php echo esc_html__('Refunded', 'velocity-marketplace'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Metode Pembayaran Aktif', 'velocity-marketplace'); ?></th>
                                    <td>
                                        <label><input type="checkbox" value="bank" x-model="form.payment_methods"> <?php echo esc_html__('Bank Transfer', 'velocity-marketplace'); ?></label><br>
                                        <template x-if="gateways.duitku">
                                            <label><input type="checkbox" value="duitku" x-model="form.payment_methods"> Duitku</label>
                                        </template>
                                        <template x-if="!gateways.duitku">
                                            <div class="description"><?php echo esc_html__('Duitku tidak tersedia karena plugin gateway belum aktif atau belum dikonfigurasi.', 'velocity-marketplace'); ?></div>
                                        </template>
                                        <br>
                                        <label><input type="checkbox" value="paypal" x-model="form.payment_methods"> PayPal</label><br>
                                        <label><input type="checkbox" value="cod" x-model="form.payment_methods"> COD</label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="vmp_seller_product_status"><?php echo esc_html__('Status Produk Member Baru', 'velocity-marketplace'); ?></label></th>
                                    <td>
                                        <select id="vmp_seller_product_status" x-model="form.seller_product_status">
                                            <option value="pending"><?php echo esc_html__('Menunggu Review', 'velocity-marketplace'); ?></option>
                                            <option value="publish"><?php echo esc_html__('Publish Immediately', 'velocity-marketplace'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="vmp_shipping_api_key"><?php echo esc_html__('API Key Pengiriman', 'velocity-marketplace'); ?></label></th>
                                    <td>
                                        <input id="vmp_shipping_api_key" type="text" class="regular-text" x-model="form.shipping_api_key">
                                        <p class="description"><?php echo esc_html__('Digunakan untuk memuat data lokasi, menghitung ongkir, dan mengambil informasi pelacakan pengiriman.', 'velocity-marketplace'); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="vmp-settings-panel" :class="{ 'is-active': activeTab === 'bank' }">
                    <div class="vmp-settings-card">
                        <h2 class="vmp-bank-settings__title"><?php echo esc_html__('Rekening Transfer Bank', 'velocity-marketplace'); ?></h2>
                        <p class="vmp-bank-settings__desc"><?php echo esc_html__('Rekening pada bagian ini digunakan sebagai tujuan pembayaran saat pembeli memilih transfer bank.', 'velocity-marketplace'); ?></p>

                        <div class="vmp-bank-settings__section">
                            <h3 class="vmp-bank-settings__section-title"><?php echo esc_html__('Rekening Bank Populer', 'velocity-marketplace'); ?></h3>
                            <div class="vmp-bank-settings__toolbar">
                                <p class="description"><?php echo esc_html__('Pilih bank dari daftar populer, lalu isi nomor rekening dan nama pemilik rekening.', 'velocity-marketplace'); ?></p>
                                <button type="button" class="button button-secondary" @click="addPopularBank()"><?php echo esc_html__('Tambah Rekening Populer', 'velocity-marketplace'); ?></button>
                            </div>
                            <p class="vmp-bank-settings__empty" x-show="!form.popular_bank_accounts.length"><?php echo esc_html__('Belum ada rekening bank populer yang ditambahkan.', 'velocity-marketplace'); ?></p>
                            <div class="vmp-bank-settings__rows">
                                <template x-for="(row, index) in form.popular_bank_accounts" :key="'popular-' + index">
                                    <div class="vmp-bank-settings__row">
                                        <div class="vmp-bank-settings__field">
                                            <label><?php echo esc_html__('Nama Bank', 'velocity-marketplace'); ?></label>
                                            <select :value="row.bank_code || ''" @change="row.bank_code = $event.target.value">
                                                <option value=""><?php echo esc_html__('Select bank', 'velocity-marketplace'); ?></option>
                                                <template x-for="bank in popularBankEntries" :key="bank.code">
                                                    <option :value="bank.code" :selected="(row.bank_code || '') === bank.code" x-text="bank.label"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="vmp-bank-settings__field">
                                            <label><?php echo esc_html__('Nomor Rekening', 'velocity-marketplace'); ?></label>
                                            <input type="text" class="regular-text" x-model="row.account_number" placeholder="<?php echo esc_attr__('Contoh: 1234567890', 'velocity-marketplace'); ?>">
                                        </div>
                                        <div class="vmp-bank-settings__field">
                                            <label><?php echo esc_html__('Atas Nama', 'velocity-marketplace'); ?></label>
                                            <input type="text" class="regular-text" x-model="row.account_holder" placeholder="<?php echo esc_attr__('Contoh: PT Velocity Marketplace', 'velocity-marketplace'); ?>">
                                        </div>
                                        <div class="vmp-bank-settings__row-actions">
                                            <button type="button" class="button-link-delete" @click="removePopularBank(index)"><?php echo esc_html__('Hapus', 'velocity-marketplace'); ?></button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="vmp-bank-settings__section">
                            <h3 class="vmp-bank-settings__section-title"><?php echo esc_html__('Rekening Bank Lainnya', 'velocity-marketplace'); ?></h3>
                            <div class="vmp-bank-settings__toolbar">
                                <p class="description"><?php echo esc_html__('Tambahkan rekening dari bank di luar daftar populer jika diperlukan.', 'velocity-marketplace'); ?></p>
                                <button type="button" class="button button-secondary" @click="addCustomBank()"><?php echo esc_html__('Tambah Rekening Lainnya', 'velocity-marketplace'); ?></button>
                            </div>
                            <p class="vmp-bank-settings__empty" x-show="!form.custom_bank_accounts.length"><?php echo esc_html__('Belum ada rekening bank lainnya yang ditambahkan.', 'velocity-marketplace'); ?></p>
                            <div class="vmp-bank-settings__rows">
                                <template x-for="(row, index) in form.custom_bank_accounts" :key="'custom-' + index">
                                    <div class="vmp-bank-settings__row">
                                        <div class="vmp-bank-settings__field">
                                            <label><?php echo esc_html__('Nama Bank', 'velocity-marketplace'); ?></label>
                                            <input type="text" class="regular-text" x-model="row.bank_name" placeholder="<?php echo esc_attr__('Masukkan nama bank lainnya', 'velocity-marketplace'); ?>">
                                        </div>
                                        <div class="vmp-bank-settings__field">
                                            <label><?php echo esc_html__('Nomor Rekening', 'velocity-marketplace'); ?></label>
                                            <input type="text" class="regular-text" x-model="row.account_number" placeholder="<?php echo esc_attr__('Contoh: 1234567890', 'velocity-marketplace'); ?>">
                                        </div>
                                        <div class="vmp-bank-settings__field">
                                            <label><?php echo esc_html__('Atas Nama', 'velocity-marketplace'); ?></label>
                                            <input type="text" class="regular-text" x-model="row.account_holder" placeholder="<?php echo esc_attr__('Contoh: PT Velocity Marketplace', 'velocity-marketplace'); ?>">
                                        </div>
                                        <div class="vmp-bank-settings__row-actions">
                                            <button type="button" class="button-link-delete" @click="removeCustomBank(index)"><?php echo esc_html__('Hapus', 'velocity-marketplace'); ?></button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="vmp-settings-panel" :class="{ 'is-active': activeTab === 'email' }">
                    <div class="vmp-settings-card">
                        <h2 class="vmp-email-settings__title"><?php echo esc_html__('Template Email', 'velocity-marketplace'); ?></h2>
                        <p class="vmp-email-settings__desc"><?php echo esc_html__('Anda dapat mengatur template email di sini.', 'velocity-marketplace'); ?></p>

                        <div class="vmp-email-settings__field">
                            <label for="vmp_email_admin_recipient"><?php echo esc_html__('Email Admin', 'velocity-marketplace'); ?></label>
                            <input id="vmp_email_admin_recipient" type="email" class="regular-text" x-model="form.email_admin_recipient" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                            <p class="description"><?php echo esc_html__('Email admin untuk menerima email pesanan. Jika dikosongkan, otomatis menggunakan email admin website.', 'velocity-marketplace'); ?></p>
                        </div>

                        <div class="vmp-email-settings__section">
                            <div class="vmp-email-settings__grid">
                                <div class="vmp-email-settings__help">
                                    <h3 class="vmp-email-settings__title"><?php echo esc_html__('Email Dikirim ke Admin', 'velocity-marketplace'); ?></h3>
                                    <div><?php echo esc_html__('Bisa menggunakan shortcode berikut:', 'velocity-marketplace'); ?></div>
                                    <div><code>[nama-toko]</code> <code>[kode-pesanan]</code> <code>[tanggal-order]</code></div>
                                    <div><code>[detail-pesanan]</code> <code>[data-pemesan]</code></div>
                                </div>
                                <div class="vmp-email-settings__editor">
                                    <?php
                                    wp_editor(
                                        (string) ($settings_payload['email_template_admin_order'] ?? ''),
                                        'vmp_email_template_admin',
                                        $editor_settings
                                    );
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="vmp-email-settings__section">
                            <div class="vmp-email-settings__grid">
                                <div class="vmp-email-settings__help">
                                    <h3 class="vmp-email-settings__title"><?php echo esc_html__('Email Dikirim ke Pembeli', 'velocity-marketplace'); ?></h3>
                                    <div><?php echo esc_html__('Bisa menggunakan shortcode berikut:', 'velocity-marketplace'); ?></div>
                                    <div><code>[nama-pemesan]</code> <code>[nama-toko]</code> <code>[kode-pesanan]</code></div>
                                    <div><code>[tanggal-order]</code> <code>[detail-pesanan]</code> <code>[total-order]</code></div>
                                    <div><code>[nomor-rekening]</code> <code>[alamat-toko]</code></div>
                                </div>
                                <div class="vmp-email-settings__editor">
                                    <?php
                                    wp_editor(
                                        (string) ($settings_payload['email_template_customer_order'] ?? ''),
                                        'vmp_email_template_customer',
                                        $editor_settings
                                    );
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="vmp-email-settings__section">
                            <div class="vmp-email-settings__grid">
                                <div class="vmp-email-settings__help">
                                    <h3 class="vmp-email-settings__title"><?php echo esc_html__('Email Perubahan Status', 'velocity-marketplace'); ?></h3>
                                    <div><?php echo esc_html__('Bisa menggunakan shortcode berikut:', 'velocity-marketplace'); ?></div>
                                    <div><code>[link]</code> <code>[nama-toko]</code> <code>[kode-pesanan]</code> <code>[status]</code></div>
                                    <div><code>[nama-pemesan]</code> <code>[alamat-toko]</code></div>
                                </div>
                                <div class="vmp-email-settings__editor">
                                    <?php
                                    wp_editor(
                                        (string) ($settings_payload['email_template_status_update'] ?? ''),
                                        'vmp_email_template_status',
                                        $editor_settings
                                    );
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="vmp-admin-settings__footer">
                    <button type="button" class="button button-primary button-large" @click="save()" :disabled="saving" x-text="saving ? '<?php echo esc_js(__('Menyimpan...', 'velocity-marketplace')); ?>' : '<?php echo esc_js(__('Simpan Pengaturan', 'velocity-marketplace')); ?>'"><?php echo esc_html__('Simpan Pengaturan', 'velocity-marketplace'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
}
