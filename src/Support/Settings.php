<?php

namespace VelocityMarketplace\Support;

class Settings
{
    public static function all()
    {
        $settings = get_option('velocity_marketplace_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $defaults = [
            'currency' => 'IDR',
            'currency_symbol' => 'Rp',
            'default_order_status' => 'pending_payment',
            'payment_methods' => ['bank'],
            'seller_product_status' => 'publish',
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
        $pages = get_option('velocity_marketplace_pages', []);
        if (is_array($pages) && !empty($pages['myaccount'])) {
            $url = get_permalink((int) $pages['myaccount']);
            if ($url) {
                return $url;
            }
        }
        return site_url('/myaccount/');
    }
}
