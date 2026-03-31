<?php

namespace VelocityMarketplace\Support;

class Settings
{
    public static function all()
    {
        $settings = get_option(VMP_SETTINGS_OPTION, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $defaults = [
            'currency' => 'IDR',
            'currency_symbol' => 'Rp',
            'default_order_status' => 'pending_payment',
            'payment_methods' => ['bank'],
            'seller_product_status' => 'publish',
            'shipping_api_key' => '',
            'bank_accounts' => [],
            'email_admin_recipient' => '',
            'email_template_admin_order' => '',
            'email_template_customer_order' => '',
            'email_template_status_update' => '',
        ];

        return array_merge($defaults, $settings);
    }

    public static function currency()
    {
        $settings = self::all();
        $currency = strtoupper((string) $settings['currency']);
        if (!in_array($currency, ['IDR', 'USD'], true)) {
            $currency = 'IDR';
        }
        return $currency;
    }

    public static function currency_symbol()
    {
        $settings = self::all();
        $symbol = trim((string) $settings['currency_symbol']);
        if ($symbol !== '') {
            return $symbol;
        }
        return self::currency() === 'USD' ? '$' : 'Rp';
    }

    public static function payment_methods()
    {
        $settings = self::all();
        $methods = isset($settings['payment_methods']) && is_array($settings['payment_methods'])
            ? array_values(array_unique(array_map('sanitize_key', $settings['payment_methods'])))
            : ['bank'];

        $allowed = ['bank', 'duitku', 'paypal', 'cod'];
        $filtered = [];
        foreach ($methods as $m) {
            if (in_array($m, $allowed, true)) {
                $filtered[] = $m;
            }
        }

        return !empty($filtered) ? $filtered : ['bank'];
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
        $pages = get_option(VMP_PAGES_OPTION, []);
        if (is_array($pages) && !empty($pages['myaccount'])) {
            $url = get_permalink((int) $pages['myaccount']);
            if ($url) {
                return $url;
            }
        }
        return site_url('/account/');
    }

    public static function store_profile_url($seller_id = 0)
    {
        $pages = get_option(VMP_PAGES_OPTION, []);
        $base = '';
        if (is_array($pages) && !empty($pages['toko'])) {
            $base = get_permalink((int) $pages['toko']);
        }
        if (!$base) {
            $base = site_url('/store/');
        }

        $seller_id = (int) $seller_id;
        if ($seller_id > 0) {
            return add_query_arg(['seller' => $seller_id], $base);
        }

        return $base;
    }

    public static function tracking_url($invoice = '')
    {
        $pages = get_option(VMP_PAGES_OPTION, []);
        $base = '';
        if (is_array($pages) && !empty($pages['tracking'])) {
            $base = get_permalink((int) $pages['tracking']);
        }
        if (!$base) {
            $base = site_url('/order-tracking/');
        }

        $invoice = trim((string) $invoice);
        if ($invoice !== '') {
            return add_query_arg(['invoice' => $invoice], $base);
        }

        return $base;
    }

    public static function shipping_api_key()
    {
        $settings = self::all();
        return trim((string) ($settings['shipping_api_key'] ?? ''));
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
        $settings = self::all();
        $rows = isset($settings['bank_accounts']) && is_array($settings['bank_accounts'])
            ? $settings['bank_accounts']
            : [];

        $accounts = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $bank_name = trim((string) ($row['bank_name'] ?? ''));
            $account_number = preg_replace('/[^0-9]/', '', (string) ($row['account_number'] ?? ''));
            $account_holder = trim((string) ($row['account_holder'] ?? ''));

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
}
