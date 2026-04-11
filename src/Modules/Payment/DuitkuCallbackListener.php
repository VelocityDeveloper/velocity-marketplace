<?php

namespace VelocityMarketplace\Modules\Payment;

use VelocityMarketplace\Modules\Email\EmailTemplateService;
use VelocityMarketplace\Modules\Notification\NotificationRepository;
use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Support\Settings;

class DuitkuCallbackListener
{
    public function register()
    {
        add_action('wp_store_payment_callback_received', [$this, 'sync_gateway_meta'], 10, 4);
        add_action('wp_store_payment_completed', [$this, 'handle_completed'], 10, 4);
        add_action('wp_store_payment_failed', [$this, 'handle_failed'], 10, 4);
    }

    public function sync_gateway_meta($order_id, $gateway, $payment_data, $payload)
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0 || sanitize_key((string) $gateway) !== 'duitku') {
            return;
        }
        if (!is_array($payload) || !is_array($payment_data)) {
            return;
        }

        update_post_meta($order_id, 'vmp_gateway_name', 'duitku');
        update_post_meta($order_id, 'vmp_gateway_reference', sanitize_text_field((string) ($payment_data['reference'] ?? '')));
        update_post_meta($order_id, 'vmp_gateway_payment_code', sanitize_text_field((string) ($payment_data['payment_code'] ?? '')));
        update_post_meta($order_id, 'vmp_gateway_amount', (float) ($payment_data['amount'] ?? 0));
        update_post_meta($order_id, 'vmp_gateway_status', sanitize_text_field((string) ($payment_data['gateway_status'] ?? 'pending')));
        update_post_meta($order_id, 'vmp_gateway_callback_payload', $payload);
        update_post_meta($order_id, 'vmp_gateway_callback_at', current_time('mysql'));
    }

    public function handle_completed($order_id, $gateway, $payment_data, $payload)
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0 || sanitize_key((string) $gateway) !== 'duitku') {
            return;
        }
        $this->sync_gateway_meta($order_id, $gateway, $payment_data, $payload);

        $current_status = sanitize_key((string) get_post_meta($order_id, 'vmp_status', true));
        if (in_array($current_status, ['processing', 'shipped', 'completed'], true)) {
            return;
        }

        $invoice = sanitize_text_field((string) get_post_meta($order_id, 'vmp_invoice', true));
        update_post_meta($order_id, 'vmp_status', 'processing');
        OrderData::sync_core_status($order_id, 'processing');

        $shipping_groups = get_post_meta($order_id, 'vmp_shipping_groups', true);
        if (is_array($shipping_groups)) {
            foreach ($shipping_groups as $index => $shipping_group) {
                if (!is_array($shipping_group)) {
                    continue;
                }
                $shipping_groups[$index]['status'] = 'processing';
            }
            update_post_meta($order_id, 'vmp_shipping_groups', $shipping_groups);
        }

        $user_id = OrderData::buyer_id($order_id);
        if ($user_id > 0) {
            (new NotificationRepository())->add(
                $user_id,
                'payment',
                'Pembayaran Berhasil',
                'Pembayaran untuk invoice ' . $invoice . ' berhasil dikonfirmasi oleh Duitku.',
                Settings::customer_order_url($invoice)
            );
        }

        (new EmailTemplateService())->send_customer_status_update($order_id, 'processing');
    }

    public function handle_failed($order_id, $gateway, $payment_data, $payload)
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0 || sanitize_key((string) $gateway) !== 'duitku') {
            return;
        }

        $this->sync_gateway_meta($order_id, $gateway, $payment_data, $payload);
    }
}

