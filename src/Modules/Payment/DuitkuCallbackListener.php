<?php

namespace VelocityMarketplace\Modules\Payment;

use VelocityMarketplace\Modules\Email\EmailTemplateService;
use VelocityMarketplace\Modules\Notification\NotificationRepository;
use VelocityMarketplace\Support\Settings;

class DuitkuCallbackListener
{
    public function register()
    {
        add_action('velocity_duitku_callback', [$this, 'handle_callback']);
    }

    public function handle_callback($payload)
    {
        if (!is_array($payload)) {
            return;
        }

        $invoice = sanitize_text_field((string) ($payload['merchantOrderId'] ?? ''));
        if ($invoice === '') {
            return;
        }

        $orders = get_posts([
            'post_type' => 'vmp_order',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'vmp_invoice',
                    'value' => $invoice,
                ],
            ],
            'fields' => 'ids',
        ]);

        $order_id = !empty($orders[0]) ? (int) $orders[0] : 0;
        if ($order_id <= 0) {
            return;
        }

        $result_code = sanitize_text_field((string) ($payload['resultCode'] ?? ''));
        $reference = sanitize_text_field((string) ($payload['reference'] ?? ''));
        $payment_code = sanitize_text_field((string) ($payload['paymentCode'] ?? ''));
        $amount = (float) ($payload['amount'] ?? 0);
        $gateway_status = $result_code === '00' ? 'paid' : 'failed';

        update_post_meta($order_id, 'vmp_gateway_name', 'duitku');
        update_post_meta($order_id, 'vmp_gateway_reference', $reference);
        update_post_meta($order_id, 'vmp_gateway_payment_code', $payment_code);
        update_post_meta($order_id, 'vmp_gateway_amount', $amount);
        update_post_meta($order_id, 'vmp_gateway_status', $gateway_status);
        update_post_meta($order_id, 'vmp_gateway_callback_payload', $payload);
        update_post_meta($order_id, 'vmp_gateway_callback_at', current_time('mysql'));

        if ($result_code !== '00') {
            return;
        }

        $current_status = sanitize_key((string) get_post_meta($order_id, 'vmp_status', true));
        if (in_array($current_status, ['processing', 'shipped', 'completed'], true)) {
            return;
        }

        update_post_meta($order_id, 'vmp_status', 'processing');

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

        $user_id = (int) get_post_meta($order_id, 'vmp_user_id', true);
        if ($user_id > 0) {
            (new NotificationRepository())->add(
                $user_id,
                'payment',
                'Pembayaran Berhasil',
                'Pembayaran untuk invoice ' . $invoice . ' berhasil dikonfirmasi oleh Duitku.',
                add_query_arg(['tab' => 'tracking', 'invoice' => $invoice], Settings::profile_url())
            );
        }

        (new EmailTemplateService())->send_customer_status_update($order_id, 'processing');
    }
}
