<?php

namespace VelocityMarketplace\Support;

use WpStore\Domain\Payment\DuitkuGateway;
use WpStore\Domain\Payment\PaymentMethodRegistry;

class Settings
{
    private static function core_settings()
    {
        $settings = get_option('wp_store_settings', []);
        return is_array($settings) ? $settings : [];
    }

    private static function core_page_id($key)
    {
        $settings = self::core_settings();
        return !empty($settings[$key]) ? (int) $settings[$key] : 0;
    }

    private static function core_page_url($key, $fallback = '/')
    {
        $page_id = self::core_page_id($key);
        if ($page_id > 0) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }

        return site_url($fallback);
    }

    public static function all()
    {
        $settings = get_option(VMP_SETTINGS_OPTION, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $defaults = [
            'default_order_status' => 'pending_payment',
            'seller_product_status' => 'publish',
            'email_admin_recipient' => '',
            'email_template_admin_order' => '',
            'email_template_customer_order' => '',
            'email_template_status_update' => '',
        ];

        return array_merge($defaults, $settings);
    }

    public static function currency()
    {
        $settings = self::core_settings();
        $currency = strtoupper((string) ($settings['currency'] ?? ''));
        if (!in_array($currency, ['IDR', 'USD'], true)) {
            $symbol = trim((string) ($settings['currency_symbol'] ?? 'Rp'));
            $currency = in_array($symbol, ['$', 'USD'], true) ? 'USD' : 'IDR';
        }

        return $currency;
    }

    public static function currency_symbol()
    {
        $settings = self::core_settings();
        $symbol = trim((string) ($settings['currency_symbol'] ?? ''));
        if ($symbol !== '') {
            return $symbol;
        }

        return self::currency() === 'USD' ? '$' : 'Rp';
    }

    public static function payment_methods()
    {
        $settings = self::core_settings();
        $methods = isset($settings['payment_methods']) && is_array($settings['payment_methods'])
            ? array_values(array_unique(array_map('sanitize_key', $settings['payment_methods'])))
            : [];

        if (empty($methods)) {
            $methods = (new PaymentMethodRegistry())->available_methods();
        }

        $map = [
            'bank_transfer' => 'bank',
            'bank' => 'bank',
            'duitku' => 'duitku',
            'paypal' => 'paypal',
            'cod' => 'cod',
            'qris' => 'qris',
        ];

        $filtered = [];
        foreach ($methods as $method) {
            $normalized = isset($map[$method]) ? $map[$method] : '';
            if ($normalized === '') {
                continue;
            }
            if ($normalized === 'duitku' && !DuitkuGateway::is_available()) {
                continue;
            }
            $filtered[] = $normalized;
        }

        $filtered = array_values(array_unique($filtered));

        return !empty($filtered) ? $filtered : ['bank'];
    }

    public static function gateway_flags()
    {
        return [
            'duitku' => DuitkuGateway::is_available(),
        ];
    }

    public static function default_order_status()
    {
        $settings = self::all();
        $status = sanitize_key((string) $settings['default_order_status']);
        $allowed = ['pending_payment', 'pending_verification', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];
        if (!in_array($status, $allowed, true)) {
            return 'pending_payment';
        }

        return $status;
    }

    public static function seller_product_status()
    {
        $settings = self::all();
        $status = sanitize_key((string) $settings['seller_product_status']);
        if (!in_array($status, ['pending', 'publish'], true)) {
            $status = 'publish';
        }

        return $status;
    }

    public static function profile_url()
    {
        return self::core_page_url('page_profile', '/account/');
    }

    public static function catalog_url()
    {
        return self::core_page_url('page_catalog', '/catalog/');
    }

    public static function cart_url()
    {
        return self::core_page_url('page_cart', '/cart/');
    }

    public static function cart_page_id()
    {
        return self::core_page_id('page_cart');
    }

    public static function checkout_url()
    {
        return self::core_page_url('page_checkout', '/checkout/');
    }

    public static function checkout_page_id()
    {
        return self::core_page_id('page_checkout');
    }

    public static function store_profile_page_id()
    {
        $pages = get_option(VMP_PAGES_OPTION, []);
        if (is_array($pages) && !empty($pages['toko'])) {
            return (int) $pages['toko'];
        }

        return 0;
    }

    public static function store_profile_base_url()
    {
        $page_id = self::store_profile_page_id();
        if ($page_id > 0) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }

        return site_url('/store/');
    }

    public static function store_profile_base_path()
    {
        $page_id = self::store_profile_page_id();
        if ($page_id > 0) {
            $uri = trim((string) get_page_uri($page_id), '/');
            if ($uri !== '') {
                return $uri;
            }
        }

        return 'store';
    }

    public static function store_profile_url($seller_id = 0)
    {
        $base = self::store_profile_base_url();

        $seller_id = (int) $seller_id;
        if ($seller_id > 0) {
            $seller = get_userdata($seller_id);
            if ($seller && $seller->user_login !== '') {
                return trailingslashit($base) . rawurlencode($seller->user_login) . '/';
            }
        }

        return $base;
    }

    public static function tracking_url($invoice = '')
    {
        $base = self::core_page_url('page_tracking', '/order-tracking/');

        $invoice = trim((string) $invoice);
        if ($invoice !== '') {
            return add_query_arg(['order' => $invoice], $base);
        }

        return $base;
    }

    public static function customer_order_url($invoice = '', $fragment = '')
    {
        $url = self::tracking_url($invoice);
        $fragment = trim((string) $fragment);
        if ($fragment !== '') {
            $url .= '#' . ltrim($fragment, '#');
        }

        return $url;
    }

    public static function shipping_api_key()
    {
        $settings = self::core_settings();
        return trim((string) ($settings['rajaongkir_api_key'] ?? ''));
    }

    public static function popular_bank_labels()
    {
        return [
            'bca' => 'BCA',
            'mandiri' => 'Mandiri',
            'bni' => 'BNI',
            'bri' => 'BRI',
            'btn' => 'BTN',
            'cimb_niaga' => 'CIMB Niaga',
            'permata' => 'Permata Bank',
            'danamon' => 'Danamon',
            'ocbc_nisp' => 'OCBC NISP',
            'maybank' => 'Maybank',
            'mega' => 'Bank Mega',
            'panin' => 'Panin Bank',
            'bsi' => 'Bank Syariah Indonesia',
            'jago' => 'Bank Jago',
            'jenius' => 'Jenius / BTPN',
            'seabank' => 'SeaBank',
            'neocommerce' => 'Bank Neo Commerce',
            'uob' => 'UOB Indonesia',
            'hsbc' => 'HSBC Indonesia',
            'dbs' => 'DBS Indonesia',
        ];
    }

    public static function bank_accounts()
    {
        $settings = self::core_settings();
        $rows = isset($settings['store_bank_accounts']) && is_array($settings['store_bank_accounts'])
            ? $settings['store_bank_accounts']
            : [];

        $accounts = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $bank_name = trim((string) ($row['bank_name'] ?? ''));
            $account_number = preg_replace('/[^0-9]/', '', (string) ($row['account_number'] ?? $row['bank_account'] ?? ''));
            $account_holder = trim((string) ($row['account_holder'] ?? $row['bank_holder'] ?? ''));

            if ($bank_name === '' || $account_number === '' || $account_holder === '') {
                continue;
            }

            $accounts[] = [
                'bank_code' => sanitize_key((string) ($row['bank_code'] ?? '')),
                'bank_name' => $bank_name,
                'account_number' => $account_number,
                'account_holder' => $account_holder,
            ];
        }

        return $accounts;
    }

    public static function qris_details()
    {
        $settings = self::core_settings();
        $image_id = isset($settings['qris_image_id']) ? absint($settings['qris_image_id']) : 0;
        $image_url = $image_id > 0 ? wp_get_attachment_image_url($image_id, 'large') : '';
        $label = trim((string) ($settings['qris_label'] ?? ''));

        return [
            'image_id' => $image_id,
            'image_url' => $image_url ?: '',
            'label' => $label !== '' ? $label : __('Scan QRIS untuk menyelesaikan pembayaran.', 'velocity-marketplace'),
        ];
    }

    public static function courier_labels()
    {
        return [
            'jne' => 'JNE',
            'pos' => 'POS Indonesia',
            'tiki' => 'TIKI',
            'sicepat' => 'SiCepat',
            'jnt' => 'J&T',
            'ninja' => 'Ninja Xpress',
            'wahana' => 'Wahana',
            'lion' => 'Lion Parcel',
            'sap' => 'SAP Express',
            'rex' => 'REX',
            'ide' => 'IDExpress',
        ];
    }

    public static function managed_page_ids()
    {
        $pages = get_option(VMP_PAGES_OPTION, []);
        if (!is_array($pages)) {
            $pages = [];
        }

        $ids = [
            self::core_page_id('page_catalog'),
            self::core_page_id('page_profile'),
            self::core_page_id('page_cart'),
            self::core_page_id('page_checkout'),
            self::core_page_id('page_tracking'),
            self::store_profile_page_id(),
        ];

        return array_values(array_filter(array_unique(array_map('intval', $ids))));
    }

    public static function profile_page_id()
    {
        return self::core_page_id('page_profile');
    }
}
