<?php

namespace VelocityMarketplace\Core;

use VelocityMarketplace\Modules\Email\EmailTemplateService;
use VelocityMarketplace\Support\Settings;

class SettingsService
{
    public function sanitize($input)
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

        $shipping_api_key = isset($input['shipping_api_key']) ? sanitize_text_field((string) $input['shipping_api_key']) : '';
        $email_defaults = EmailTemplateService::default_settings();
        $email_admin_recipient = isset($input['email_admin_recipient']) ? sanitize_email((string) $input['email_admin_recipient']) : '';
        $email_template_admin_order = isset($input['email_template_admin_order']) ? wp_kses_post((string) $input['email_template_admin_order']) : (string) $email_defaults['email_template_admin_order'];
        $email_template_customer_order = isset($input['email_template_customer_order']) ? wp_kses_post((string) $input['email_template_customer_order']) : (string) $email_defaults['email_template_customer_order'];
        $email_template_status_update = isset($input['email_template_status_update']) ? wp_kses_post((string) $input['email_template_status_update']) : (string) $email_defaults['email_template_status_update'];
        $bank_accounts = [];
        $popular_banks = Settings::popular_bank_labels();

        $raw_popular_bank_accounts = isset($input['popular_bank_accounts']) && is_array($input['popular_bank_accounts']) ? $input['popular_bank_accounts'] : [];
        foreach ($raw_popular_bank_accounts as $row) {
            if (!is_array($row)) {
                continue;
            }

            $bank_code = sanitize_key((string) ($row['bank_code'] ?? ''));
            $account_number = preg_replace('/[^0-9]/', '', (string) ($row['account_number'] ?? ''));
            $account_holder = sanitize_text_field((string) ($row['account_holder'] ?? ''));
            $bank_name = isset($popular_banks[$bank_code]) ? (string) $popular_banks[$bank_code] : '';

            if ($bank_name === '' && $account_number === '' && $account_holder === '') {
                continue;
            }
            if ($bank_name === '' || $account_number === '' || $account_holder === '') {
                continue;
            }

            $bank_accounts[] = [
                'bank_code' => $bank_code,
                'bank_name' => $bank_name,
                'account_number' => $account_number,
                'account_holder' => $account_holder,
            ];
        }

        $raw_custom_bank_accounts = isset($input['custom_bank_accounts']) && is_array($input['custom_bank_accounts']) ? $input['custom_bank_accounts'] : [];
        foreach ($raw_custom_bank_accounts as $row) {
            if (!is_array($row)) {
                continue;
            }

            $bank_name = sanitize_text_field((string) ($row['bank_name'] ?? ''));
            $account_number = preg_replace('/[^0-9]/', '', (string) ($row['account_number'] ?? ''));
            $account_holder = sanitize_text_field((string) ($row['account_holder'] ?? ''));

            if ($bank_name === '' && $account_number === '' && $account_holder === '') {
                continue;
            }
            if ($bank_name === '' || $account_number === '' || $account_holder === '') {
                continue;
            }

            $bank_accounts[] = [
                'bank_code' => '',
                'bank_name' => $bank_name,
                'account_number' => $account_number,
                'account_holder' => $account_holder,
            ];
        }

        return [
            'currency' => $currency,
            'currency_symbol' => $currency_symbol,
            'default_order_status' => $default_order_status,
            'payment_methods' => $payment_methods,
            'seller_product_status' => $seller_product_status,
            'shipping_api_key' => $shipping_api_key,
            'bank_accounts' => $bank_accounts,
            'email_admin_recipient' => $email_admin_recipient,
            'email_template_admin_order' => $email_template_admin_order,
            'email_template_customer_order' => $email_template_customer_order,
            'email_template_status_update' => $email_template_status_update,
        ];
    }

    public function get_settings_payload()
    {
        $settings = get_option(VMP_SETTINGS_OPTION, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $email_defaults = EmailTemplateService::default_settings();

        $popular_banks = Settings::popular_bank_labels();
        $all_bank_accounts = isset($settings['bank_accounts']) && is_array($settings['bank_accounts']) ? array_values($settings['bank_accounts']) : [];
        $popular_bank_accounts = [];
        $custom_bank_accounts = [];

        foreach ($all_bank_accounts as $row) {
            if (!is_array($row)) {
                continue;
            }

            $bank_code = sanitize_key((string) ($row['bank_code'] ?? ''));
            if ($bank_code !== '' && isset($popular_banks[$bank_code])) {
                $popular_bank_accounts[] = [
                    'bank_code' => $bank_code,
                    'account_number' => (string) ($row['account_number'] ?? ''),
                    'account_holder' => (string) ($row['account_holder'] ?? ''),
                ];
                continue;
            }

            $custom_bank_accounts[] = [
                'bank_name' => (string) ($row['bank_name'] ?? ''),
                'account_number' => (string) ($row['account_number'] ?? ''),
                'account_holder' => (string) ($row['account_holder'] ?? ''),
            ];
        }

        return [
            'currency' => isset($settings['currency']) ? (string) $settings['currency'] : 'IDR',
            'currency_symbol' => isset($settings['currency_symbol']) ? (string) $settings['currency_symbol'] : 'Rp',
            'default_order_status' => isset($settings['default_order_status']) ? (string) $settings['default_order_status'] : 'pending_payment',
            'payment_methods' => isset($settings['payment_methods']) && is_array($settings['payment_methods']) ? array_values($settings['payment_methods']) : ['bank'],
            'seller_product_status' => isset($settings['seller_product_status']) ? (string) $settings['seller_product_status'] : 'publish',
            'shipping_api_key' => isset($settings['shipping_api_key']) ? (string) $settings['shipping_api_key'] : '',
            'popular_bank_accounts' => array_values($popular_bank_accounts),
            'custom_bank_accounts' => array_values($custom_bank_accounts),
            'email_admin_recipient' => isset($settings['email_admin_recipient']) ? (string) $settings['email_admin_recipient'] : '',
            'email_template_admin_order' => isset($settings['email_template_admin_order']) && (string) $settings['email_template_admin_order'] !== ''
                ? (string) $settings['email_template_admin_order']
                : (string) $email_defaults['email_template_admin_order'],
            'email_template_customer_order' => isset($settings['email_template_customer_order']) && (string) $settings['email_template_customer_order'] !== ''
                ? (string) $settings['email_template_customer_order']
                : (string) $email_defaults['email_template_customer_order'],
            'email_template_status_update' => isset($settings['email_template_status_update']) && (string) $settings['email_template_status_update'] !== ''
                ? (string) $settings['email_template_status_update']
                : (string) $email_defaults['email_template_status_update'],
        ];
    }

    public function save_settings($input)
    {
        $sanitized = $this->sanitize($input);
        update_option(VMP_SETTINGS_OPTION, $sanitized);
        return $this->get_settings_payload();
    }
}
