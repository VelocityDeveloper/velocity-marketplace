<?php

namespace VelocityMarketplace\Admin;

class SettingsPage
{
    public function register()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_setting']);
    }

    public function add_menu()
    {
        add_submenu_page(
            'edit.php?post_type=vmp_product',
            'Pengaturan Marketplace',
            'Pengaturan',
            'manage_options',
            'vmp-settings',
            [$this, 'render_page']
        );
    }

    public function register_setting()
    {
        register_setting(
            'vmp_settings_group',
            'velocity_marketplace_settings',
            [$this, 'sanitize_settings']
        );
    }

    public function sanitize_settings($input)
    {
        $input = is_array($input) ? $input : [];

        $currency = isset($input['currency']) ? sanitize_text_field((string) $input['currency']) : 'IDR';
        $supported_currency = ['IDR', 'USD'];
        if (!in_array($currency, $supported_currency, true)) {
            $currency = 'IDR';
        }

        $currency_symbol = isset($input['currency_symbol']) ? sanitize_text_field((string) $input['currency_symbol']) : 'Rp';
        if ($currency_symbol === '') {
            $currency_symbol = $currency === 'USD' ? '$' : 'Rp';
        }

        $default_order_status = isset($input['default_order_status']) ? sanitize_key((string) $input['default_order_status']) : 'pending_payment';
        $allowed_status = ['pending_payment', 'pending_verification', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];
        if (!in_array($default_order_status, $allowed_status, true)) {
            $default_order_status = 'pending_payment';
        }

        $seller_product_status = isset($input['seller_product_status']) ? sanitize_key((string) $input['seller_product_status']) : 'publish';
        if (!in_array($seller_product_status, ['pending', 'publish'], true)) {
            $seller_product_status = 'publish';
        }

        $raw_methods = isset($input['payment_methods']) && is_array($input['payment_methods']) ? $input['payment_methods'] : [];
        $allowed_methods = ['bank', 'duitku', 'paypal', 'cod'];
        $payment_methods = [];
        foreach ($raw_methods as $method) {
            $m = sanitize_key((string) $method);
            if (in_array($m, $allowed_methods, true)) {
                $payment_methods[] = $m;
            }
        }
        $payment_methods = array_values(array_unique($payment_methods));
        if (empty($payment_methods)) {
            $payment_methods = ['bank'];
        }

        return [
            'currency' => $currency,
            'currency_symbol' => $currency_symbol,
            'default_order_status' => $default_order_status,
            'payment_methods' => $payment_methods,
            'seller_product_status' => $seller_product_status,
        ];
    }

    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = get_option('velocity_marketplace_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $currency = isset($settings['currency']) ? (string) $settings['currency'] : 'IDR';
        $currency_symbol = isset($settings['currency_symbol']) ? (string) $settings['currency_symbol'] : 'Rp';
        $default_order_status = isset($settings['default_order_status']) ? (string) $settings['default_order_status'] : 'pending_payment';
        $payment_methods = isset($settings['payment_methods']) && is_array($settings['payment_methods']) ? $settings['payment_methods'] : ['bank'];
        $seller_product_status = isset($settings['seller_product_status']) ? (string) $settings['seller_product_status'] : 'publish';
        ?>
        <div class="wrap">
            <h1>Pengaturan Velocity Marketplace</h1>
            <form method="post" action="options.php">
                <?php settings_fields('vmp_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="vmp_currency">Mata Uang</label></th>
                            <td>
                                <select id="vmp_currency" name="velocity_marketplace_settings[currency]">
                                    <option value="IDR" <?php selected($currency, 'IDR'); ?>>IDR</option>
                                    <option value="USD" <?php selected($currency, 'USD'); ?>>USD</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vmp_currency_symbol">Simbol Mata Uang</label></th>
                            <td>
                                <input id="vmp_currency_symbol" type="text" class="regular-text" name="velocity_marketplace_settings[currency_symbol]" value="<?php echo esc_attr($currency_symbol); ?>">
                                <p class="description">Contoh: Rp, $, USD.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vmp_default_order_status">Status Order Default</label></th>
                            <td>
                                <select id="vmp_default_order_status" name="velocity_marketplace_settings[default_order_status]">
                                    <option value="pending_payment" <?php selected($default_order_status, 'pending_payment'); ?>>Pending Payment</option>
                                    <option value="pending_verification" <?php selected($default_order_status, 'pending_verification'); ?>>Pending Verification</option>
                                    <option value="processing" <?php selected($default_order_status, 'processing'); ?>>Processing</option>
                                    <option value="shipped" <?php selected($default_order_status, 'shipped'); ?>>Shipped</option>
                                    <option value="completed" <?php selected($default_order_status, 'completed'); ?>>Completed</option>
                                    <option value="cancelled" <?php selected($default_order_status, 'cancelled'); ?>>Cancelled</option>
                                    <option value="refunded" <?php selected($default_order_status, 'refunded'); ?>>Refunded</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Metode Pembayaran Aktif</th>
                            <td>
                                <label><input type="checkbox" name="velocity_marketplace_settings[payment_methods][]" value="bank" <?php checked(in_array('bank', $payment_methods, true)); ?>> Transfer Bank</label><br>
                                <label><input type="checkbox" name="velocity_marketplace_settings[payment_methods][]" value="duitku" <?php checked(in_array('duitku', $payment_methods, true)); ?>> Duitku</label><br>
                                <label><input type="checkbox" name="velocity_marketplace_settings[payment_methods][]" value="paypal" <?php checked(in_array('paypal', $payment_methods, true)); ?>> PayPal</label><br>
                                <label><input type="checkbox" name="velocity_marketplace_settings[payment_methods][]" value="cod" <?php checked(in_array('cod', $payment_methods, true)); ?>> COD</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vmp_seller_product_status">Status Iklan Seller Baru</label></th>
                            <td>
                                <select id="vmp_seller_product_status" name="velocity_marketplace_settings[seller_product_status]">
                                    <option value="pending" <?php selected($seller_product_status, 'pending'); ?>>Pending Review</option>
                                    <option value="publish" <?php selected($seller_product_status, 'publish'); ?>>Langsung Publish</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button('Simpan Pengaturan'); ?>
            </form>
        </div>
        <?php
    }
}
