<?php

namespace VelocityMarketplace\Modules\Account\Handlers;

use VelocityMarketplace\Modules\Account\Account;
use VelocityMarketplace\Modules\Captcha\CaptchaBridge;
use VelocityMarketplace\Modules\Email\EmailTemplateService;
use VelocityMarketplace\Modules\Notification\NotificationRepository;
use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Modules\Product\ProductData;
use VelocityMarketplace\Support\Settings;

class OrderActionHandler extends BaseActionHandler
{
    public function buyer_confirm_received()
    {
        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
        $seller_id = isset($_POST['seller_id']) ? (int) $_POST['seller_id'] : 0;
        $redirect_tab = isset($_POST['redirect_tab']) ? sanitize_key((string) wp_unslash($_POST['redirect_tab'])) : 'orders';
        $invoice = isset($_POST['invoice']) ? sanitize_text_field((string) wp_unslash($_POST['invoice'])) : '';
        $nonce = isset($_POST['vmp_confirm_received_nonce']) ? (string) wp_unslash($_POST['vmp_confirm_received_nonce']) : '';

        if (!in_array($redirect_tab, ['orders', 'tracking'], true)) {
            $redirect_tab = 'orders';
        }

        if ($order_id <= 0 || $seller_id <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'vmp_confirm_received_' . $order_id . '_' . $seller_id)) {
            $this->redirect_with([
                'vmp_error' => 'Konfirmasi pesanan tidak valid.',
                'tab' => $redirect_tab,
                'invoice' => $invoice,
            ]);
        }

        if (get_post_type($order_id) !== 'vmp_order') {
            $this->redirect_with([
                'vmp_error' => 'Pesanan tidak ditemukan.',
                'tab' => $redirect_tab,
                'invoice' => $invoice,
            ]);
        }

        $buyer_id = (int) get_post_meta($order_id, 'vmp_user_id', true);
        if ($buyer_id !== get_current_user_id() && !current_user_can('manage_options')) {
            $this->redirect_with([
                'vmp_error' => 'Pesanan bukan milik akun ini.',
                'tab' => $redirect_tab,
                'invoice' => $invoice,
            ]);
        }

        $previous_status = (string) get_post_meta($order_id, 'vmp_status', true);
        $shipping_groups = OrderData::shipping_groups($order_id);
        $seller_name = '';
        $updated = false;

        if (!empty($shipping_groups)) {
            foreach ($shipping_groups as &$shipping_group) {
                if ((int) ($shipping_group['seller_id'] ?? 0) !== $seller_id) {
                    continue;
                }

                $group_status = OrderData::shipping_group_status($shipping_group, $previous_status !== '' ? $previous_status : 'pending_payment');
                if ($group_status !== 'shipped') {
                    continue;
                }

                $shipping_group['status'] = 'completed';
                $shipping_group['received_at'] = current_time('mysql');
                if (empty($shipping_group['sold_count_recorded']) && !empty($shipping_group['items']) && is_array($shipping_group['items'])) {
                    foreach ($shipping_group['items'] as $group_item) {
                        $product_id = isset($group_item['product_id']) ? (int) $group_item['product_id'] : 0;
                        $qty = isset($group_item['qty']) ? (int) $group_item['qty'] : 0;
                        ProductData::increment_sold_count($product_id, $qty);
                    }
                    $shipping_group['sold_count_recorded'] = 1;
                }
                $seller_name = isset($shipping_group['seller_name']) ? (string) $shipping_group['seller_name'] : '';
                $updated = true;
                break;
            }
            unset($shipping_group);
        }

        if (!$updated) {
            $this->redirect_with([
                'vmp_error' => 'Toko ini belum bisa dikonfirmasi selesai.',
                'tab' => $redirect_tab,
                'invoice' => $invoice,
            ]);
        }

        update_post_meta($order_id, 'vmp_shipping_groups', array_values($shipping_groups));
        $summary_status = OrderData::summarize_shipping_statuses($shipping_groups, $previous_status !== '' ? $previous_status : 'pending_payment');
        update_post_meta($order_id, 'vmp_status', $summary_status);

        $profile_url = Settings::profile_url();
        $repo = new NotificationRepository();
        $repo->add(
            $seller_id,
            'order',
            'Pesanan Diterima Pembeli',
            'Pembeli mengonfirmasi pesanan dari toko ' . ($seller_name !== '' ? $seller_name : ('Seller #' . $seller_id)) . ' telah diterima.',
            add_query_arg(['tab' => 'seller_home'], $profile_url)
        );

        if ($buyer_id > 0 && $previous_status !== $summary_status) {
            (new EmailTemplateService())->send_customer_status_update($order_id, $summary_status);
        }

        $this->refresh_star_seller_for_order($order_id);

        $this->redirect_with([
            'vmp_notice' => 'Pesanan untuk toko ini ditandai selesai.',
            'tab' => $redirect_tab,
            'invoice' => $invoice,
        ]);
    }

    public function seller_update_order()
    {
        if (!Account::can_sell()) {
            $this->redirect_with(['vmp_error' => 'Hanya member marketplace yang bisa mengubah order.', 'tab' => 'seller_home']);
        }

        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
        $nonce = isset($_POST['vmp_seller_order_nonce']) ? (string) wp_unslash($_POST['vmp_seller_order_nonce']) : '';
        if ($order_id <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'vmp_seller_order_' . $order_id)) {
            $this->redirect_with(['vmp_error' => 'Aksi order tidak valid.', 'tab' => 'seller_home']);
        }

        if (get_post_type($order_id) !== 'vmp_order') {
            $this->redirect_with(['vmp_error' => 'Order tidak ditemukan.', 'tab' => 'seller_home']);
        }

        $seller_id = get_current_user_id();
        if (!OrderData::has_seller($order_id, $seller_id) && !current_user_can('manage_options')) {
            $this->redirect_with(['vmp_error' => 'Order ini bukan milik toko kamu.', 'tab' => 'seller_home']);
        }

        $status = OrderData::normalize_status(isset($_POST['order_status']) ? (string) wp_unslash($_POST['order_status']) : '');
        $resi = sanitize_text_field((string) ($_POST['receipt_no'] ?? ''));
        $courier = sanitize_text_field((string) ($_POST['receipt_courier'] ?? ''));
        $seller_note = sanitize_textarea_field((string) ($_POST['seller_note'] ?? ''));

        $shipping_groups = OrderData::shipping_groups($order_id);
        $seller_name = '';
        if (is_array($shipping_groups) && !empty($shipping_groups)) {
            foreach ($shipping_groups as &$shipping_group) {
                if ((int) ($shipping_group['seller_id'] ?? 0) !== $seller_id) {
                    continue;
                }
                $shipping_group['status'] = $status;
                $seller_name = isset($shipping_group['seller_name']) ? (string) $shipping_group['seller_name'] : '';
                if ($resi !== '') {
                    $shipping_group['receipt_no'] = $resi;
                }
                if ($courier !== '') {
                    $shipping_group['receipt_courier'] = $courier;
                    $shipping_group['courier'] = $courier;
                    if (empty($shipping_group['courier_name'])) {
                        $shipping_group['courier_name'] = strtoupper($courier);
                    }
                }
                if ($seller_note !== '') {
                    $shipping_group['seller_note'] = $seller_note;
                }
            }
            unset($shipping_group);
            update_post_meta($order_id, 'vmp_shipping_groups', array_values($shipping_groups));
        }

        $previous_status = (string) get_post_meta($order_id, 'vmp_status', true);
        $summary_status = OrderData::summarize_shipping_statuses(is_array($shipping_groups) ? $shipping_groups : [], $previous_status !== '' ? $previous_status : 'pending_payment');
        update_post_meta($order_id, 'vmp_status', $summary_status);

        if ($resi !== '') {
            update_post_meta($order_id, 'vmp_receipt_no', $resi);
        }
        if ($courier !== '') {
            update_post_meta($order_id, 'vmp_receipt_courier', $courier);
        }
        if ($seller_note !== '') {
            update_post_meta($order_id, 'vmp_seller_note', $seller_note);
        }

        $buyer_id = (int) get_post_meta($order_id, 'vmp_user_id', true);
        $invoice = (string) get_post_meta($order_id, 'vmp_invoice', true);
        $profile_url = Settings::profile_url();
        if ($buyer_id > 0) {
            $repo = new NotificationRepository();
            $repo->add(
                $buyer_id,
                'order',
                'Update Pesanan',
                'Status pesanan dari toko ' . ($seller_name !== '' ? $seller_name : ('Seller #' . $seller_id)) . ' berubah menjadi ' . OrderData::status_label($status) . '.',
                add_query_arg(['tab' => 'orders', 'invoice' => $invoice], $profile_url)
            );
        }
        if ($buyer_id > 0 && $previous_status !== $summary_status) {
            (new EmailTemplateService())->send_customer_status_update($order_id, $summary_status);
        }

        $this->refresh_star_seller_for_order($order_id);

        $this->redirect_with([
            'vmp_notice' => 'Order berhasil diperbarui.',
            'tab' => 'seller_home',
        ]);
    }

    public function buyer_upload_transfer()
    {
        $redirect_tab = isset($_POST['redirect_tab']) ? sanitize_key((string) wp_unslash($_POST['redirect_tab'])) : 'orders';
        if (!in_array($redirect_tab, ['orders', 'tracking'], true)) {
            $redirect_tab = 'orders';
        }

        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
        $nonce = isset($_POST['vmp_transfer_nonce']) ? (string) wp_unslash($_POST['vmp_transfer_nonce']) : '';
        if ($order_id <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'vmp_upload_transfer_' . $order_id)) {
            $this->redirect_with(['vmp_error' => 'Upload bukti transfer tidak valid.', 'tab' => $redirect_tab]);
        }

        if (CaptchaBridge::is_active()) {
            $verify = CaptchaBridge::verify_request();
            if (empty($verify['success'])) {
                $this->redirect_with([
                    'vmp_error' => $verify['message'] !== '' ? $verify['message'] : 'Captcha tidak valid.',
                    'tab' => $redirect_tab,
                    'invoice' => (string) get_post_meta($order_id, 'vmp_invoice', true),
                ]);
            }
        }

        $buyer_id = (int) get_post_meta($order_id, 'vmp_user_id', true);
        if ($buyer_id !== get_current_user_id() && !current_user_can('manage_options')) {
            $this->redirect_with(['vmp_error' => 'Order bukan milik akun ini.', 'tab' => $redirect_tab]);
        }

        if (empty($_FILES['transfer_proof']) || empty($_FILES['transfer_proof']['tmp_name'])) {
            $this->redirect_with([
                'vmp_error' => 'File bukti transfer belum dipilih.',
                'tab' => $redirect_tab,
                'invoice' => (string) get_post_meta($order_id, 'vmp_invoice', true),
            ]);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_id = media_handle_upload('transfer_proof', $order_id);
        if (is_wp_error($attach_id) || !$attach_id) {
            $this->redirect_with([
                'vmp_error' => 'Gagal upload bukti transfer.',
                'tab' => $redirect_tab,
                'invoice' => (string) get_post_meta($order_id, 'vmp_invoice', true),
            ]);
        }

        update_post_meta($order_id, 'vmp_transfer_proof_id', (int) $attach_id);
        update_post_meta($order_id, 'vmp_transfer_uploaded_at', current_time('mysql'));

        $current_status = (string) get_post_meta($order_id, 'vmp_status', true);
        if (!in_array($current_status, ['cancelled', 'completed', 'refunded'], true)) {
            update_post_meta($order_id, 'vmp_status', 'pending_verification');
        }

        $shipping_groups = OrderData::shipping_groups($order_id);
        if (!empty($shipping_groups)) {
            foreach ($shipping_groups as &$shipping_group) {
                $group_status = OrderData::shipping_group_status($shipping_group, $current_status !== '' ? $current_status : 'pending_payment');
                if (in_array($group_status, ['cancelled', 'completed', 'refunded'], true)) {
                    continue;
                }
                $shipping_group['status'] = 'pending_verification';
            }
            unset($shipping_group);
            update_post_meta($order_id, 'vmp_shipping_groups', array_values($shipping_groups));
        }

        $invoice = (string) get_post_meta($order_id, 'vmp_invoice', true);
        $notif = new NotificationRepository();
        $seller_ids = [];
        foreach (OrderData::get_items($order_id) as $line) {
            $seller_id = isset($line['seller_id']) ? (int) $line['seller_id'] : 0;
            if ($seller_id > 0) {
                $seller_ids[] = $seller_id;
            }
        }
        $seller_ids = array_values(array_unique($seller_ids));
        foreach ($seller_ids as $seller_id) {
            $notif->add(
                $seller_id,
                'payment',
                'Konfirmasi Pembayaran',
                'Pembeli upload bukti transfer untuk invoice ' . $invoice . '.',
                add_query_arg(['tab' => 'seller_home'], Settings::profile_url())
            );
        }

        $this->redirect_with([
            'vmp_notice' => 'Bukti transfer berhasil diupload.',
            'tab' => $redirect_tab,
            'invoice' => $invoice,
        ]);
    }
}
