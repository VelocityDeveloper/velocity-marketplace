<?php

namespace VelocityMarketplace\Modules\Profile;

class StoreBankAdmin
{
    public function register()
    {
        add_action('show_user_profile', [$this, 'render_fields']);
        add_action('edit_user_profile', [$this, 'render_fields']);
    }

    public function render_fields($user)
    {
        if (!$user instanceof \WP_User || !current_user_can('manage_options')) {
            return;
        }

        $bank_details = (string) get_user_meta((int) $user->ID, 'vmp_store_bank_details', true);
        ?>
        <h2><?php echo esc_html__('Velocity Marketplace: Rekening Seller', 'velocity-marketplace'); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="vmp_store_bank_details"><?php echo esc_html__('Rekening Pencairan', 'velocity-marketplace'); ?></label>
                    </th>
                    <td>
                        <textarea id="vmp_store_bank_details" class="large-text" rows="4" readonly><?php echo esc_textarea($bank_details); ?></textarea>
                        <p class="description">
                            <?php echo esc_html__('Data ini diisi seller dari halaman Profil Toko dan hanya ditampilkan untuk admin.', 'velocity-marketplace'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }
}
