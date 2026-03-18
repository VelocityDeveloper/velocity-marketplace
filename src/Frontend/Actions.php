<?php

namespace VelocityMarketplace\Frontend;

use VelocityMarketplace\Support\NotificationRepository;
use VelocityMarketplace\Support\OrderData;
use VelocityMarketplace\Support\ProductFields;
use VelocityMarketplace\Support\Settings;
use VelocityMarketplace\Support\WishlistRepository;

class Actions
{
    public function register()
    {
        add_action('init', [$this, 'handle_actions']);
    }

    public function handle_actions()
    {
        if (!is_user_logged_in()) {
            return;
        }

        if (isset($_GET['vmp_delete_product']) && Account::is_seller()) {
            $this->handle_delete_product();
            return;
        }

        if (!isset($_POST['vmp_action'])) {
            return;
        }

        $action = sanitize_key((string) wp_unslash($_POST['vmp_action']));

        if ($action === 'seller_save_product') {
            $this->handle_save_product();
            return;
        }

        if ($action === 'seller_update_order') {
            $this->handle_seller_update_order();
            return;
        }

        if ($action === 'buyer_upload_transfer') {
            $this->handle_buyer_upload_transfer();
            return;
        }

        if ($action === 'save_store_profile') {
            $this->handle_save_store_profile();
            return;
        }

        if ($action === 'wishlist_remove') {
            $this->handle_wishlist_remove();
            return;
        }

        if ($action === 'notification_mark_read') {
            $this->handle_notification_mark_read();
            return;
        }

        if ($action === 'notification_mark_all') {
            $this->handle_notification_mark_all();
            return;
        }

        if ($action === 'notification_delete') {
            $this->handle_notification_delete();
        }
    }

    private function handle_save_product()
    {
        if (!Account::is_seller()) {
            $this->redirect_with(['vmp_error' => 'Hanya seller yang bisa menyimpan produk.', 'tab' => 'seller_products']);
        }

        if (!isset($_POST['vmp_seller_product_nonce']) || !wp_verify_nonce($_POST['vmp_seller_product_nonce'], 'vmp_seller_product')) {
            $this->redirect_with(['vmp_error' => 'Nonce produk tidak valid.', 'tab' => 'seller_products']);
        }

        $user_id = get_current_user_id();
        $store_name = (string) get_user_meta($user_id, 'vmp_store_name', true);
        $store_address = (string) get_user_meta($user_id, 'vmp_store_address', true);
        if ($store_name === '' || $store_address === '') {
            $this->redirect_with([
                'vmp_error' => 'Lengkapi profil toko dulu sebelum menambah produk.',
                'tab' => 'seller_profile',
            ]);
        }

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $is_edit = $product_id > 0;

        if ($is_edit) {
            $author_id = (int) get_post_field('post_author', $product_id);
            if ($author_id !== $user_id && !current_user_can('manage_options')) {
                $this->redirect_with(['vmp_error' => 'Tidak punya izin edit produk ini.', 'tab' => 'seller_products']);
            }
            if (get_post_type($product_id) !== 'vmp_product') {
                $this->redirect_with(['vmp_error' => 'Produk tidak ditemukan.', 'tab' => 'seller_products']);
            }
        }

        $title = sanitize_text_field((string) ($_POST['title'] ?? ''));
        $description = wp_kses_post((string) ($_POST['description'] ?? ''));
        $price = isset($_POST['price']) && is_numeric($_POST['price']) ? (float) $_POST['price'] : 0;
        $cat_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $premium_requested = !empty($_POST['premium_request']);

        if ($title === '' || $price <= 0) {
            $this->redirect_with(['vmp_error' => 'Judul dan harga wajib diisi.', 'tab' => 'seller_products']);
        }

        $status = $is_edit ? get_post_status($product_id) : Settings::seller_product_status();
        if (!in_array($status, ['pending', 'publish'], true)) {
            $status = 'publish';
        }

        $postarr = [
            'post_type' => 'vmp_product',
            'post_title' => $title,
            'post_content' => $description,
            'post_excerpt' => wp_trim_words(wp_strip_all_tags($description), 25),
            'post_status' => $status,
            'post_author' => $user_id,
        ];
        if ($is_edit) {
            $postarr['ID'] = $product_id;
        }

        $saved_id = wp_insert_post($postarr, true);
        if (is_wp_error($saved_id) || !$saved_id) {
            $this->redirect_with(['vmp_error' => 'Gagal menyimpan produk.', 'tab' => 'seller_products']);
        }

        ProductFields::save($saved_id, 'frontend');
        if (get_post_meta($saved_id, 'is_premium', true) === '') {
            update_post_meta($saved_id, 'is_premium', 0);
        }

        if ($cat_id > 0) {
            wp_set_object_terms($saved_id, [$cat_id], 'vmp_product_cat', false);
        }

        if (array_key_exists('featured_image_id', $_POST)) {
            $featured_image_id = isset($_POST['featured_image_id']) ? (int) wp_unslash($_POST['featured_image_id']) : 0;
            if ($featured_image_id > 0 && get_post_type($featured_image_id) === 'attachment') {
                set_post_thumbnail($saved_id, $featured_image_id);
            } else {
                delete_post_thumbnail($saved_id);
            }
        } elseif (!empty($_FILES['featured_image']) && !empty($_FILES['featured_image']['tmp_name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $attach_id = media_handle_upload('featured_image', $saved_id);
            if (!is_wp_error($attach_id) && $attach_id) {
                set_post_thumbnail($saved_id, $attach_id);
            }
        }

        if ($premium_requested) {
            $notif = new NotificationRepository();
            $admins = get_users([
                'role' => 'administrator',
                'fields' => ['ID', 'user_email'],
            ]);

            foreach ($admins as $admin) {
                if (!is_object($admin)) {
                    continue;
                }

                $admin_id = isset($admin->ID) ? (int) $admin->ID : 0;
                if ($admin_id > 0) {
                    $notif->add(
                        $admin_id,
                        'premium',
                        'Pengajuan Iklan Premium',
                        'Ada pengajuan iklan premium baru untuk produk: ' . $title . '.',
                        admin_url('edit.php?post_type=vmp_product')
                    );
                }

                $email = isset($admin->user_email) ? (string) $admin->user_email : '';
                if ($email !== '' && is_email($email)) {
                    wp_mail(
                        $email,
                        'Pengajuan iklan premium baru',
                        'Seller mengajukan iklan premium untuk produk: ' . $title
                    );
                }
            }
        }

        $this->redirect_with([
            'vmp_notice' => $is_edit ? 'Iklan berhasil diperbarui.' : 'Iklan berhasil dikirim.',
            'tab' => 'seller_products',
        ]);
    }

    private function handle_delete_product()
    {
        $product_id = isset($_GET['vmp_delete_product']) ? (int) $_GET['vmp_delete_product'] : 0;
        $nonce = isset($_GET['vmp_nonce']) ? sanitize_text_field((string) $_GET['vmp_nonce']) : '';
        if ($product_id <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'vmp_delete_product_' . $product_id)) {
            $this->redirect_with(['vmp_error' => 'Aksi hapus tidak valid.', 'tab' => 'seller_products']);
        }

        if (get_post_type($product_id) !== 'vmp_product') {
            $this->redirect_with(['vmp_error' => 'Produk tidak ditemukan.', 'tab' => 'seller_products']);
        }

        $author_id = (int) get_post_field('post_author', $product_id);
        if ($author_id !== get_current_user_id() && !current_user_can('manage_options')) {
            $this->redirect_with(['vmp_error' => 'Tidak punya izin hapus produk.', 'tab' => 'seller_products']);
        }

        wp_trash_post($product_id);
        $this->redirect_with(['vmp_notice' => 'Iklan dipindah ke trash.', 'tab' => 'seller_products']);
    }

    private function handle_seller_update_order()
    {
        if (!Account::is_seller()) {
            $this->redirect_with(['vmp_error' => 'Hanya seller yang bisa mengubah order.', 'tab' => 'seller_home']);
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

        update_post_meta($order_id, 'vmp_status', $status);
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
                'Status order ' . $invoice . ' berubah menjadi ' . OrderData::status_label($status) . '.',
                add_query_arg(['tab' => 'orders', 'invoice' => $invoice], $profile_url)
            );
        }

        $this->redirect_with([
            'vmp_notice' => 'Order berhasil diperbarui.',
            'tab' => 'seller_home',
        ]);
    }

    private function handle_buyer_upload_transfer()
    {
        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
        $nonce = isset($_POST['vmp_transfer_nonce']) ? (string) wp_unslash($_POST['vmp_transfer_nonce']) : '';
        if ($order_id <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'vmp_upload_transfer_' . $order_id)) {
            $this->redirect_with(['vmp_error' => 'Upload bukti transfer tidak valid.', 'tab' => 'orders']);
        }

        $buyer_id = (int) get_post_meta($order_id, 'vmp_user_id', true);
        if ($buyer_id !== get_current_user_id() && !current_user_can('manage_options')) {
            $this->redirect_with(['vmp_error' => 'Order bukan milik akun ini.', 'tab' => 'orders']);
        }

        if (empty($_FILES['transfer_proof']) || empty($_FILES['transfer_proof']['tmp_name'])) {
            $this->redirect_with([
                'vmp_error' => 'File bukti transfer belum dipilih.',
                'tab' => 'orders',
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
                'tab' => 'orders',
                'invoice' => (string) get_post_meta($order_id, 'vmp_invoice', true),
            ]);
        }

        update_post_meta($order_id, 'vmp_transfer_proof_id', (int) $attach_id);
        update_post_meta($order_id, 'vmp_transfer_uploaded_at', current_time('mysql'));

        $current_status = (string) get_post_meta($order_id, 'vmp_status', true);
        if (!in_array($current_status, ['cancelled', 'completed', 'refunded'], true)) {
            update_post_meta($order_id, 'vmp_status', 'pending_verification');
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
            'tab' => 'orders',
            'invoice' => $invoice,
        ]);
    }

    private function handle_save_store_profile()
    {
        if (!Account::is_seller()) {
            $this->redirect_with(['vmp_error' => 'Fitur ini hanya untuk seller.', 'tab' => 'orders']);
        }

        $nonce = isset($_POST['vmp_store_profile_nonce']) ? (string) wp_unslash($_POST['vmp_store_profile_nonce']) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vmp_store_profile')) {
            $this->redirect_with(['vmp_error' => 'Nonce profil toko tidak valid.', 'tab' => 'seller_profile']);
        }

        $user_id = get_current_user_id();
        $map = [
            'vmp_store_name' => sanitize_text_field((string) ($_POST['store_name'] ?? '')),
            'vmp_store_phone' => sanitize_text_field((string) ($_POST['store_phone'] ?? '')),
            'vmp_store_whatsapp' => sanitize_text_field((string) ($_POST['store_whatsapp'] ?? '')),
            'vmp_store_address' => sanitize_textarea_field((string) ($_POST['store_address'] ?? '')),
            'vmp_store_subdistrict' => sanitize_text_field((string) ($_POST['store_subdistrict'] ?? '')),
            'vmp_store_city' => sanitize_text_field((string) ($_POST['store_city'] ?? '')),
            'vmp_store_province' => sanitize_text_field((string) ($_POST['store_province'] ?? '')),
            'vmp_store_postcode' => sanitize_text_field((string) ($_POST['store_postcode'] ?? '')),
            'vmp_store_description' => sanitize_textarea_field((string) ($_POST['store_description'] ?? '')),
        ];
        foreach ($map as $meta_key => $value) {
            update_user_meta($user_id, $meta_key, $value);
        }

        $couriers = isset($_POST['store_couriers']) && is_array($_POST['store_couriers']) ? $_POST['store_couriers'] : [];
        $couriers = array_values(array_unique(array_filter(array_map('sanitize_key', $couriers), function ($code) {
            return $code !== '';
        })));
        update_user_meta($user_id, 'vmp_store_couriers', $couriers);

        if (!empty($_FILES['store_avatar']) && !empty($_FILES['store_avatar']['tmp_name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $attach_id = media_handle_upload('store_avatar', 0);
            if (!is_wp_error($attach_id) && $attach_id) {
                update_user_meta($user_id, 'vmp_store_avatar_id', (int) $attach_id);
            }
        }

        $this->redirect_with([
            'vmp_notice' => 'Profil toko berhasil diperbarui.',
            'tab' => 'seller_profile',
        ]);
    }

    private function handle_wishlist_remove()
    {
        $nonce = isset($_POST['vmp_wishlist_nonce']) ? (string) wp_unslash($_POST['vmp_wishlist_nonce']) : '';
        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        if ($product_id <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'vmp_wishlist_remove_' . $product_id)) {
            $this->redirect_with(['vmp_error' => 'Aksi wishlist tidak valid.', 'tab' => 'wishlist']);
        }

        $repo = new WishlistRepository();
        $repo->remove($product_id);

        $this->redirect_with([
            'vmp_notice' => 'Produk dihapus dari wishlist.',
            'tab' => 'wishlist',
        ]);
    }

    private function handle_notification_mark_read()
    {
        $nonce = isset($_POST['vmp_notification_nonce']) ? (string) wp_unslash($_POST['vmp_notification_nonce']) : '';
        $id = isset($_POST['notification_id']) ? sanitize_text_field((string) wp_unslash($_POST['notification_id'])) : '';
        if ($id === '' || $nonce === '' || !wp_verify_nonce($nonce, 'vmp_notification_action_' . $id)) {
            $this->redirect_with(['vmp_error' => 'Aksi notifikasi tidak valid.', 'tab' => 'notifications']);
        }

        $repo = new NotificationRepository();
        $repo->mark_read($id);

        $this->redirect_with([
            'vmp_notice' => 'Notifikasi ditandai sudah dibaca.',
            'tab' => 'notifications',
        ]);
    }

    private function handle_notification_mark_all()
    {
        $nonce = isset($_POST['vmp_notification_nonce']) ? (string) wp_unslash($_POST['vmp_notification_nonce']) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vmp_notification_mark_all')) {
            $this->redirect_with(['vmp_error' => 'Aksi notifikasi tidak valid.', 'tab' => 'notifications']);
        }

        $repo = new NotificationRepository();
        $repo->mark_all_read();

        $this->redirect_with([
            'vmp_notice' => 'Semua notifikasi ditandai sudah dibaca.',
            'tab' => 'notifications',
        ]);
    }

    private function handle_notification_delete()
    {
        $nonce = isset($_POST['vmp_notification_nonce']) ? (string) wp_unslash($_POST['vmp_notification_nonce']) : '';
        $id = isset($_POST['notification_id']) ? sanitize_text_field((string) wp_unslash($_POST['notification_id'])) : '';
        if ($id === '' || $nonce === '' || !wp_verify_nonce($nonce, 'vmp_notification_action_' . $id)) {
            $this->redirect_with(['vmp_error' => 'Aksi notifikasi tidak valid.', 'tab' => 'notifications']);
        }

        $repo = new NotificationRepository();
        $repo->delete($id);

        $this->redirect_with([
            'vmp_notice' => 'Notifikasi dihapus.',
            'tab' => 'notifications',
        ]);
    }

    private function redirect_with($params = [])
    {
        $target = wp_get_referer();
        if (!$target) {
            $target = Settings::profile_url();
        }

        $url = add_query_arg($params, $target);
        wp_safe_redirect($url);
        exit;
    }
}
