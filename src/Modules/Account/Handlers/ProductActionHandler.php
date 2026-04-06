<?php

namespace VelocityMarketplace\Modules\Account\Handlers;

use WpStore\Domain\Product\ProductFields;
use VelocityMarketplace\Modules\Account\Account;
use VelocityMarketplace\Modules\Captcha\CaptchaBridge;
use VelocityMarketplace\Modules\Notification\NotificationRepository;
use VelocityMarketplace\Modules\Product\ProductMeta;
use VelocityMarketplace\Support\Contract;
use VelocityMarketplace\Support\Settings;

class ProductActionHandler extends BaseActionHandler
{
    public function save_product()
    {
        if (!Account::can_sell()) {
            $this->redirect_with(['vmp_error' => 'Hanya member marketplace yang bisa menyimpan produk.', 'tab' => 'seller_products']);
        }

        if (!isset($_POST['vmp_seller_product_nonce']) || !wp_verify_nonce($_POST['vmp_seller_product_nonce'], 'vmp_seller_product')) {
            $this->redirect_with(['vmp_error' => 'Nonce produk tidak valid.', 'tab' => 'seller_products']);
        }

        if (CaptchaBridge::is_active()) {
            $verify = CaptchaBridge::verify_request();
            if (empty($verify['success'])) {
                $this->redirect_with([
                    'vmp_error' => $verify['message'] !== '' ? $verify['message'] : 'Captcha tidak valid.',
                    'tab' => 'seller_products',
                ]);
            }
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
            if (!Contract::is_product($product_id)) {
                $this->redirect_with(['vmp_error' => 'Produk tidak ditemukan.', 'tab' => 'seller_products']);
            }
        }

        $title = sanitize_text_field((string) ($_POST['title'] ?? ''));
        $description = wp_kses_post((string) ($_POST['description'] ?? ''));
        $price = isset($_POST['_store_price']) && is_numeric($_POST['_store_price']) ? (float) $_POST['_store_price'] : 0;
        $cat_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $premium_requested = !empty($_POST['premium_request']);

        if ($title === '' || $price <= 0) {
            $this->redirect_with(['vmp_error' => 'Judul dan harga wajib diisi.', 'tab' => 'seller_products']);
        }

        $validation = ProductFields::validate_submission('frontend');
        if (is_wp_error($validation)) {
            $this->redirect_with([
                'vmp_error' => $validation->get_error_message(),
                'tab' => 'seller_products',
            ]);
        }

        $status = $is_edit ? get_post_status($product_id) : Settings::seller_product_status();
        if (!in_array($status, ['pending', 'publish'], true)) {
            $status = 'publish';
        }

        $postarr = [
            'post_type' => Contract::PRODUCT_POST_TYPE,
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
        ProductMeta::update_logical($saved_id, 'product_type', ProductMeta::get_text($saved_id, 'product_type', 'physical'));
        if (get_post_meta($saved_id, 'is_premium', true) === '') {
            update_post_meta($saved_id, 'is_premium', 0);
        }

        if ($cat_id > 0) {
            wp_set_object_terms($saved_id, [$cat_id], Contract::PRODUCT_TAXONOMY, false);
        }

        if (array_key_exists('featured_image_id', $_POST)) {
            $featured_image_id = isset($_POST['featured_image_id']) ? (int) wp_unslash($_POST['featured_image_id']) : 0;
            if ($this->attachment_allowed_for_current_user($featured_image_id)) {
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
                        admin_url('edit.php?post_type=' . Contract::PRODUCT_POST_TYPE)
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

    public function delete_product()
    {
        $product_id = isset($_GET['vmp_delete_product']) ? (int) $_GET['vmp_delete_product'] : 0;
        $nonce = isset($_GET['vmp_nonce']) ? sanitize_text_field((string) $_GET['vmp_nonce']) : '';
        if ($product_id <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'vmp_delete_product_' . $product_id)) {
            $this->redirect_with(['vmp_error' => 'Aksi hapus tidak valid.', 'tab' => 'seller_products']);
        }

        if (!Contract::is_product($product_id)) {
            $this->redirect_with(['vmp_error' => 'Produk tidak ditemukan.', 'tab' => 'seller_products']);
        }

        $author_id = (int) get_post_field('post_author', $product_id);
        if ($author_id !== get_current_user_id() && !current_user_can('manage_options')) {
            $this->redirect_with(['vmp_error' => 'Tidak punya izin hapus produk.', 'tab' => 'seller_products']);
        }

        wp_trash_post($product_id);
        $this->redirect_with(['vmp_notice' => 'Iklan dipindah ke trash.', 'tab' => 'seller_products']);
    }
}
