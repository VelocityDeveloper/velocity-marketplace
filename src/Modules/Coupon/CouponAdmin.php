<?php

namespace VelocityMarketplace\Modules\Coupon;

class CouponAdmin
{
    public function register()
    {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_store_coupon', [$this, 'save_meta_box']);
        add_filter('manage_edit-store_coupon_columns', [$this, 'columns']);
        add_action('manage_store_coupon_posts_custom_column', [$this, 'column_content'], 10, 2);
    }

    public function add_meta_boxes()
    {
        add_meta_box(
            'vmp-coupon-settings',
            'Pengaturan Kupon',
            [$this, 'render_meta_box'],
            'store_coupon',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post)
    {
        wp_nonce_field('store_coupon_meta_box', 'store_coupon_meta_box_nonce');
        $scope = (string) get_post_meta($post->ID, 'store_coupon_scope', true);
        $scope = $scope === 'shipping' ? 'shipping' : 'product';
        $type = (string) get_post_meta($post->ID, 'store_coupon_type', true);
        $type = $type === 'percent' ? 'percent' : 'fixed';
        $amount = (float) get_post_meta($post->ID, 'store_coupon_amount', true);
        $min_purchase = (float) get_post_meta($post->ID, 'store_coupon_min_purchase', true);
        $usage_limit = (int) get_post_meta($post->ID, 'store_coupon_usage_limit', true);
        $usage_count = (int) get_post_meta($post->ID, 'store_coupon_usage_count', true);
        $starts_at = (string) get_post_meta($post->ID, 'store_coupon_starts_at', true);
        $ends_at = (string) get_post_meta($post->ID, 'store_coupon_ends_at', true);
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">Kode Kupon</th>
                    <td>
                        <p><strong><?php echo esc_html(get_the_title($post)); ?></strong></p>
                        <p class="description">Gunakan judul post sebagai kode kupon, misalnya `HEMAT10`.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="store_coupon_scope">Cakupan Kupon</label></th>
                    <td>
                        <select id="store_coupon_scope" name="store_coupon_scope">
                            <option value="product" <?php selected($scope, 'product'); ?>>Diskon Produk</option>
                            <option value="shipping" <?php selected($scope, 'shipping'); ?>>Diskon Ongkir</option>
                        </select>
                        <p class="description">
                            `Diskon Produk` memotong subtotal produk. `Diskon Ongkir` memotong total ongkir dan baru bisa dipakai setelah pembeli memilih layanan pengiriman.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="store_coupon_type">Jenis Potongan</label></th>
                    <td>
                        <select id="store_coupon_type" name="store_coupon_type">
                            <option value="fixed" <?php selected($type, 'fixed'); ?>>Nominal Tetap</option>
                            <option value="percent" <?php selected($type, 'percent'); ?>>Persentase</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="store_coupon_amount">Nilai Potongan</label></th>
                    <td><input type="number" min="0" step="0.01" class="regular-text" id="store_coupon_amount" name="store_coupon_amount" value="<?php echo esc_attr((string) $amount); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="store_coupon_min_purchase">Minimal Belanja</label></th>
                    <td><input type="number" min="0" step="0.01" class="regular-text" id="store_coupon_min_purchase" name="store_coupon_min_purchase" value="<?php echo esc_attr((string) $min_purchase); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="store_coupon_usage_limit">Batas Penggunaan</label></th>
                    <td><input type="number" min="0" step="1" class="regular-text" id="store_coupon_usage_limit" name="store_coupon_usage_limit" value="<?php echo esc_attr((string) $usage_limit); ?>"><p class="description">Kosongkan atau isi 0 jika tidak dibatasi.</p></td>
                </tr>
                <tr>
                    <th scope="row">Penggunaan Saat Ini</th>
                    <td><?php echo esc_html((string) $usage_count); ?></td>
                </tr>
                <tr>
                    <th scope="row"><label for="store_coupon_starts_at">Mulai Berlaku</label></th>
                    <td><input type="datetime-local" class="regular-text" id="store_coupon_starts_at" name="store_coupon_starts_at" value="<?php echo esc_attr($this->datetime_local_value($starts_at)); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="store_coupon_ends_at">Berakhir</label></th>
                    <td><input type="datetime-local" class="regular-text" id="store_coupon_ends_at" name="store_coupon_ends_at" value="<?php echo esc_attr($this->datetime_local_value($ends_at)); ?>"></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function save_meta_box($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $nonce = isset($_POST['store_coupon_meta_box_nonce']) ? (string) wp_unslash($_POST['store_coupon_meta_box_nonce']) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'store_coupon_meta_box')) {
            return;
        }

        $scope = isset($_POST['store_coupon_scope']) && wp_unslash($_POST['store_coupon_scope']) === 'shipping' ? 'shipping' : 'product';
        $type = isset($_POST['store_coupon_type']) && wp_unslash($_POST['store_coupon_type']) === 'percent' ? 'percent' : 'fixed';
        update_post_meta($post_id, 'store_coupon_scope', $scope);
        update_post_meta($post_id, 'store_coupon_type', $type);
        update_post_meta($post_id, 'store_coupon_amount', max(0, (float) ($_POST['store_coupon_amount'] ?? 0)));
        update_post_meta($post_id, 'store_coupon_min_purchase', max(0, (float) ($_POST['store_coupon_min_purchase'] ?? 0)));
        update_post_meta($post_id, 'store_coupon_usage_limit', max(0, (int) ($_POST['store_coupon_usage_limit'] ?? 0)));

        $starts_at = $this->normalize_datetime(isset($_POST['store_coupon_starts_at']) ? (string) wp_unslash($_POST['store_coupon_starts_at']) : '');
        $ends_at = $this->normalize_datetime(isset($_POST['store_coupon_ends_at']) ? (string) wp_unslash($_POST['store_coupon_ends_at']) : '');

        if ($starts_at !== '') {
            update_post_meta($post_id, 'store_coupon_starts_at', $starts_at);
        } else {
            delete_post_meta($post_id, 'store_coupon_starts_at');
        }

        if ($ends_at !== '') {
            update_post_meta($post_id, 'store_coupon_ends_at', $ends_at);
        } else {
            delete_post_meta($post_id, 'store_coupon_ends_at');
        }
    }

    public function columns($columns)
    {
        return [
            'cb' => $columns['cb'] ?? '<input type="checkbox" />',
            'title' => 'Kode',
            'scope' => 'Cakupan',
            'type' => 'Jenis',
            'amount' => 'Potongan',
            'min_purchase' => 'Min. Belanja',
            'usage' => 'Penggunaan',
            'period' => 'Periode',
            'date' => 'Tanggal',
        ];
    }

    public function column_content($column, $post_id)
    {
        if ($column === 'scope') {
            echo esc_html((string) get_post_meta($post_id, 'store_coupon_scope', true) === 'shipping' ? 'Ongkir' : 'Produk');
            return;
        }
        if ($column === 'type') {
            echo esc_html((string) get_post_meta($post_id, 'store_coupon_type', true) === 'percent' ? 'Persentase' : 'Nominal');
            return;
        }
        if ($column === 'amount') {
            $type = (string) get_post_meta($post_id, 'store_coupon_type', true);
            $amount = (float) get_post_meta($post_id, 'store_coupon_amount', true);
            echo esc_html($type === 'percent' ? number_format($amount, 0, ',', '.') . '%' : 'Rp ' . number_format($amount, 0, ',', '.'));
            return;
        }
        if ($column === 'min_purchase') {
            echo esc_html('Rp ' . number_format((float) get_post_meta($post_id, 'store_coupon_min_purchase', true), 0, ',', '.'));
            return;
        }
        if ($column === 'usage') {
            $usage_count = (int) get_post_meta($post_id, 'store_coupon_usage_count', true);
            $usage_limit = (int) get_post_meta($post_id, 'store_coupon_usage_limit', true);
            echo esc_html($usage_count . ($usage_limit > 0 ? ' / ' . $usage_limit : ' / âˆž'));
            return;
        }
        if ($column === 'period') {
            $starts_at = (string) get_post_meta($post_id, 'store_coupon_starts_at', true);
            $ends_at = (string) get_post_meta($post_id, 'store_coupon_ends_at', true);
            $text = trim(($starts_at !== '' ? mysql2date('d-m-Y H:i', $starts_at) : '-') . ' s/d ' . ($ends_at !== '' ? mysql2date('d-m-Y H:i', $ends_at) : '-'));
            echo esc_html($text);
        }
    }

    private function normalize_datetime($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        if (!$timestamp) {
            return '';
        }
        return wp_date('Y-m-d H:i:s', $timestamp);
    }

    private function datetime_local_value($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        if (!$timestamp) {
            return '';
        }
        return wp_date('Y-m-d\TH:i', $timestamp);
    }
}

