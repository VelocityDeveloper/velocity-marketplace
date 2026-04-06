<?php

namespace VelocityMarketplace\Core;

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
            'edit.php?post_type=store_product',
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
        ]);
    }

    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $service = new SettingsService();
        $settings_payload = $service->get_settings_payload();
        $core_settings_url = admin_url('edit.php?post_type=store_product&page=wp-store-settings');
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

                <div class="notice notice-info inline">
                    <p>
                        <?php echo esc_html__('Pengaturan inti toko sekarang mengikuti VD Store.', 'velocity-marketplace'); ?>
                        <a href="<?php echo esc_url($core_settings_url); ?>"><?php echo esc_html__('Buka Pengaturan Toko', 'velocity-marketplace'); ?></a>
                    </p>
                </div>

                <div class="vmp-settings-tabs" role="tablist" aria-label="<?php echo esc_attr__('Pengaturan Marketplace', 'velocity-marketplace'); ?>">
                    <button type="button" class="vmp-settings-tab" :class="{ 'is-active': activeTab === 'general' }" @click="setTab('general')"><?php echo esc_html__('Pengaturan Umum', 'velocity-marketplace'); ?></button>
                    <button type="button" class="vmp-settings-tab" :class="{ 'is-active': activeTab === 'email' }" @click="setTab('email')"><?php echo esc_html__('Template Email', 'velocity-marketplace'); ?></button>
                </div>

                <div class="vmp-settings-panel" :class="{ 'is-active': activeTab === 'general' }">
                    <div class="vmp-settings-card">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="vmp_seller_product_status"><?php echo esc_html__('Status Produk Member Baru', 'velocity-marketplace'); ?></label></th>
                                    <td>
                                        <select id="vmp_seller_product_status" x-model="form.seller_product_status">
                                            <option value="pending"><?php echo esc_html__('Menunggu Review', 'velocity-marketplace'); ?></option>
                                            <option value="publish"><?php echo esc_html__('Publish Immediately', 'velocity-marketplace'); ?></option>
                                        </select>
                                        <p class="description"><?php echo esc_html__('Pengaturan ini khusus alur seller marketplace.', 'velocity-marketplace'); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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

