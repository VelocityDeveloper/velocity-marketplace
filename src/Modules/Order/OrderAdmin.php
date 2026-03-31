<?php

namespace VelocityMarketplace\Modules\Order;

use VelocityMarketplace\Modules\Email\EmailTemplateService;
use VelocityMarketplace\Modules\Review\StarSellerService;
use VelocityMarketplace\Modules\Shipping\ShippingController;

class OrderAdmin
{
    public function register()
    {
        add_filter('manage_edit-vmp_order_columns', [$this, 'columns']);
        add_action('manage_vmp_order_posts_custom_column', [$this, 'column_content'], 10, 2);
        add_filter('manage_edit-vmp_order_sortable_columns', [$this, 'sortable_columns']);
        add_action('pre_get_posts', [$this, 'handle_sorting']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_vmp_order', [$this, 'save_meta_box']);
        add_action('admin_head-post.php', [$this, 'admin_styles']);
        add_action('admin_head-post-new.php', [$this, 'admin_styles']);
    }

    public function columns($columns)
    {
        return [
            'cb' => $columns['cb'] ?? '<input type="checkbox" />',
            'title' => 'Judul',
            'invoice' => 'Invoice',
            'customer' => 'Pembeli',
            'payment' => 'Pembayaran',
            'total' => 'Total',
            'status' => 'Status',
            'date' => 'Tanggal',
        ];
    }

    public function column_content($column, $post_id)
    {
        if ($column === 'invoice') {
            $invoice = (string) get_post_meta($post_id, 'vmp_invoice', true);
            echo esc_html($invoice !== '' ? $invoice : '-');
            return;
        }

        if ($column === 'customer') {
            $customer = get_post_meta($post_id, 'vmp_customer', true);
            $customer = is_array($customer) ? $customer : [];
            $name = (string) ($customer['name'] ?? '');
            $phone = (string) ($customer['phone'] ?? '');
            $email = (string) ($customer['email'] ?? '');

            echo esc_html($name !== '' ? $name : '-');
            if ($phone !== '') {
                echo '<br><small>' . esc_html($phone) . '</small>';
            }
            if ($email !== '') {
                echo '<br><small>' . esc_html($email) . '</small>';
            }
            return;
        }

        if ($column === 'payment') {
            $payment = (string) get_post_meta($post_id, 'vmp_payment_method', true);
            echo esc_html($payment !== '' ? strtoupper($payment) : '-');
            return;
        }

        if ($column === 'total') {
            echo esc_html($this->money((float) get_post_meta($post_id, 'vmp_total', true)));
            return;
        }

        if ($column === 'status') {
            $status = (string) get_post_meta($post_id, 'vmp_status', true);
            echo esc_html(OrderData::status_label($status));
        }
    }

    public function sortable_columns($columns)
    {
        $columns['invoice'] = 'invoice';
        $columns['total'] = 'total';
        $columns['status'] = 'status';
        return $columns;
    }

    public function handle_sorting($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if (($query->get('post_type') ?? '') !== 'vmp_order') {
            return;
        }

        $orderby = (string) $query->get('orderby');
        if ($orderby === 'invoice') {
            $query->set('meta_key', 'vmp_invoice');
            $query->set('orderby', 'meta_value');
        } elseif ($orderby === 'total') {
            $query->set('meta_key', 'vmp_total');
            $query->set('orderby', 'meta_value_num');
        } elseif ($orderby === 'status') {
            $query->set('meta_key', 'vmp_status');
            $query->set('orderby', 'meta_value');
        }
    }

    public function add_meta_boxes()
    {
        add_meta_box(
            'vmp-order-summary',
            'Detail Pesanan',
            [$this, 'render_summary_meta_box'],
            'vmp_order',
            'normal',
            'high'
        );

        add_meta_box(
            'vmp-order-items',
            'Item Pesanan',
            [$this, 'render_items_meta_box'],
            'vmp_order',
            'normal',
            'default'
        );
    }

    public function render_summary_meta_box($post)
    {
        wp_nonce_field('vmp_order_admin_meta_box', 'vmp_order_admin_meta_box_nonce');

        $order_id = (int) $post->ID;
        $invoice = (string) get_post_meta($order_id, 'vmp_invoice', true);
        $status = (string) get_post_meta($order_id, 'vmp_status', true);
        $payment = (string) get_post_meta($order_id, 'vmp_payment_method', true);
        $subtotal = (float) get_post_meta($order_id, 'vmp_subtotal', true);
        $shipping_total = (float) get_post_meta($order_id, 'vmp_shipping_total', true);
        $total = (float) get_post_meta($order_id, 'vmp_total', true);
        $coupon_code = (string) get_post_meta($order_id, 'vmp_coupon_code', true);
        $coupon_discount = (float) get_post_meta($order_id, 'vmp_coupon_discount', true);
        $weight = (float) get_post_meta($order_id, 'vmp_total_weight', true);
        $created_at = (string) get_post_meta($order_id, 'vmp_created_at', true);
        $notes = (string) get_post_meta($order_id, 'vmp_notes', true);
        $customer = get_post_meta($order_id, 'vmp_customer', true);
        $customer = is_array($customer) ? $customer : [];
        $shipping_groups = OrderData::shipping_groups($order_id);
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">Invoice</th>
                    <td><?php echo esc_html($invoice !== '' ? $invoice : '-'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><label for="vmp_order_status">Status Order</label></th>
                    <td>
                        <select id="vmp_order_status" name="vmp_order_status">
                            <?php foreach (OrderData::statuses() as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected(OrderData::normalize_status($status), $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Pembeli</th>
                    <td>
                        <strong><?php echo esc_html((string) ($customer['name'] ?? '-')); ?></strong><br>
                        <span><?php echo esc_html((string) ($customer['phone'] ?? '-')); ?></span><br>
                        <span><?php echo esc_html((string) ($customer['email'] ?? '-')); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Alamat Pembeli</th>
                    <td><?php echo nl2br(esc_html((string) ($customer['address'] ?? '-'))); ?></td>
                </tr>
                <tr>
                    <th scope="row">Pembayaran</th>
                    <td><?php echo esc_html($payment !== '' ? strtoupper($payment) : '-'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Subtotal</th>
                    <td><?php echo esc_html($this->money($subtotal)); ?></td>
                </tr>
                <tr>
                    <th scope="row">Ongkir</th>
                    <td><?php echo esc_html($this->money($shipping_total)); ?></td>
                </tr>
                <tr>
                    <th scope="row">Total</th>
                    <td><strong><?php echo esc_html($this->money($total)); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">Kupon</th>
                    <td><?php echo esc_html($coupon_code !== '' ? $coupon_code : '-'); ?></td>
                </tr>
                <?php if ($coupon_discount > 0) : ?>
                    <tr>
                        <th scope="row">Diskon Kupon</th>
                        <td>-<?php echo esc_html($this->money($coupon_discount)); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th scope="row">Berat Total</th>
                    <td><?php echo esc_html(number_format($weight, 2, ',', '.') . ' kg'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Dibuat</th>
                    <td><?php echo esc_html($created_at !== '' ? $created_at : '-'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Catatan Checkout</th>
                    <td><?php echo $notes !== '' ? nl2br(esc_html($notes)) : '-'; ?></td>
                </tr>
            </tbody>
        </table>

        <?php if (!empty($shipping_groups)) : ?>
            <h4>Pengiriman per Toko</h4>
            <?php foreach ($shipping_groups as $index => $group) : ?>
                <?php
                $destination = isset($group['destination']) && is_array($group['destination']) ? $group['destination'] : [];
                $destination_text = trim(
                    implode(', ', array_filter([
                        (string) ($destination['subdistrict_destination_name'] ?? ''),
                        (string) ($destination['city_destination_name'] ?? ''),
                        (string) ($destination['province_destination_name'] ?? ''),
                    ]))
                );
                $group_courier = (string) (($group['receipt_courier'] ?? '') ?: ($group['courier'] ?? ''));
                $group_receipt = (string) ($group['receipt_no'] ?? '');
                $group_note = (string) ($group['seller_note'] ?? '');
                $tracking_rows = [];
                if ($group_receipt !== '' && $group_courier !== '') {
                    $waybill_data = ShippingController::fetch_waybill($group_receipt, $group_courier);
                    if (!is_wp_error($waybill_data) && is_array($waybill_data)) {
                        if (isset($waybill_data['data']['manifest']) && is_array($waybill_data['data']['manifest'])) {
                            $tracking_rows = $waybill_data['data']['manifest'];
                        } elseif (isset($waybill_data['data']['history']) && is_array($waybill_data['data']['history'])) {
                            $tracking_rows = $waybill_data['data']['history'];
                        }
                    }
                }
                ?>
                <div class="vmp-order-admin-card">
                    <h5 class="vmp-order-admin-card__title"><?php echo esc_html((string) ($group['seller_name'] ?? '-')); ?></h5>
                    <table class="form-table" role="presentation" style="margin:0;">
                        <tbody>
                            <tr>
                                <th scope="row" style="width:180px;">Kurir Default</th>
                                <td><?php echo esc_html((string) ($group['courier_name'] ?? $group['courier'] ?? '-')); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Layanan</th>
                                <td><?php echo esc_html((string) ($group['service'] ?? '-')); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Ongkir</th>
                                <td><?php echo esc_html($this->money((float) ($group['cost'] ?? 0))); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Tujuan</th>
                                <td><?php echo esc_html($destination_text !== '' ? $destination_text : '-'); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="vmp_group_receipt_courier_<?php echo esc_attr((string) $index); ?>">Kurir Aktual</label></th>
                                <td><input type="text" class="regular-text" id="vmp_group_receipt_courier_<?php echo esc_attr((string) $index); ?>" name="vmp_group_receipt_courier[<?php echo esc_attr((string) $index); ?>]" value="<?php echo esc_attr($group_courier); ?>" placeholder="JNE/SICEPAT/JNT"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="vmp_group_receipt_no_<?php echo esc_attr((string) $index); ?>">No. Resi</label></th>
                                <td><input type="text" class="regular-text" id="vmp_group_receipt_no_<?php echo esc_attr((string) $index); ?>" name="vmp_group_receipt_no[<?php echo esc_attr((string) $index); ?>]" value="<?php echo esc_attr($group_receipt); ?>" placeholder="Nomor resi"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="vmp_group_seller_note_<?php echo esc_attr((string) $index); ?>">Catatan Seller</label></th>
                                <td><textarea class="large-text" rows="3" id="vmp_group_seller_note_<?php echo esc_attr((string) $index); ?>" name="vmp_group_seller_note[<?php echo esc_attr((string) $index); ?>]" placeholder="Catatan untuk pembeli"><?php echo esc_textarea($group_note); ?></textarea></td>
                            </tr>
                        </tbody>
                    </table>
                    <?php if (!empty($tracking_rows)) : ?>
                        <div class="vmp-order-admin-tracking">
                            <div class="vmp-order-admin-tracking__title">Tracking Resi</div>
                            <?php foreach ($tracking_rows as $tracking_row) : ?>
                                <div class="vmp-order-admin-tracking__item">
                                    <div><strong><?php echo esc_html((string) ($tracking_row['manifest_description'] ?? $tracking_row['description'] ?? '-')); ?></strong></div>
                                    <div class="description"><?php echo esc_html(trim((string) (($tracking_row['manifest_date'] ?? '') . ' ' . ($tracking_row['manifest_time'] ?? $tracking_row['date'] ?? '')))); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php
    }

    public function render_items_meta_box($post)
    {
        $items = OrderData::get_items((int) $post->ID);

        if (empty($items)) {
            echo '<p>Tidak ada item di pesanan ini.</p>';
            return;
        }
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Toko</th>
                    <th>Qty</th>
                    <th>Harga</th>
                    <th>Subtotal</th>
                    <th>Opsi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item) : ?>
                    <?php
                    $seller_id = isset($item['seller_id']) ? (int) $item['seller_id'] : 0;
                    $seller = $seller_id > 0 ? get_userdata($seller_id) : null;
                    $seller_name = $seller && $seller->display_name !== '' ? $seller->display_name : ($seller_id > 0 ? 'Seller #' . $seller_id : '-');
                    $options = isset($item['options']) && is_array($item['options']) ? $item['options'] : [];
                    ?>
                    <tr>
                        <td>
                            <?php echo esc_html((string) ($item['title'] ?? '-')); ?>
                            <?php if (!empty($item['id'])) : ?>
                                <br><small>ID Produk: <?php echo esc_html((string) $item['id']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($seller_name); ?></td>
                        <td><?php echo esc_html((string) ((int) ($item['qty'] ?? 0))); ?></td>
                        <td><?php echo esc_html($this->money((float) ($item['price'] ?? 0))); ?></td>
                        <td><?php echo esc_html($this->money((float) ($item['subtotal'] ?? 0))); ?></td>
                        <td>
                            <?php if (!empty($options)) : ?>
                                <?php foreach ($options as $key => $value) : ?>
                                    <div><small><?php echo esc_html(ucfirst((string) $key) . ': ' . (string) $value); ?></small></div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <small>-</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
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

        $nonce = isset($_POST['vmp_order_admin_meta_box_nonce']) ? (string) wp_unslash($_POST['vmp_order_admin_meta_box_nonce']) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vmp_order_admin_meta_box')) {
            return;
        }

        $previous_status = (string) get_post_meta($post_id, 'vmp_status', true);
        $status = isset($_POST['vmp_order_status']) ? (string) wp_unslash($_POST['vmp_order_status']) : '';
        $normalized_status = OrderData::normalize_status($status);
        update_post_meta($post_id, 'vmp_status', $normalized_status);

        $shipping_groups = OrderData::shipping_groups($post_id);
        if (!empty($shipping_groups)) {
            $posted_receipts = isset($_POST['vmp_group_receipt_no']) && is_array($_POST['vmp_group_receipt_no']) ? wp_unslash($_POST['vmp_group_receipt_no']) : [];
            $posted_couriers = isset($_POST['vmp_group_receipt_courier']) && is_array($_POST['vmp_group_receipt_courier']) ? wp_unslash($_POST['vmp_group_receipt_courier']) : [];
            $posted_notes = isset($_POST['vmp_group_seller_note']) && is_array($_POST['vmp_group_seller_note']) ? wp_unslash($_POST['vmp_group_seller_note']) : [];

            foreach ($shipping_groups as $index => &$group) {
                $group['status'] = $normalized_status;
                $receipt_no = sanitize_text_field((string) ($posted_receipts[$index] ?? ''));
                $receipt_courier = sanitize_text_field((string) ($posted_couriers[$index] ?? ''));
                $seller_note = sanitize_textarea_field((string) ($posted_notes[$index] ?? ''));

                if ($receipt_no !== '') {
                    $group['receipt_no'] = $receipt_no;
                } else {
                    unset($group['receipt_no']);
                }

                if ($receipt_courier !== '') {
                    $group['receipt_courier'] = $receipt_courier;
                    $group['courier'] = $receipt_courier;
                    if (empty($group['courier_name'])) {
                        $group['courier_name'] = strtoupper($receipt_courier);
                    }
                } else {
                    unset($group['receipt_courier']);
                }

                if ($seller_note !== '') {
                    $group['seller_note'] = $seller_note;
                } else {
                    unset($group['seller_note']);
                }
            }
            unset($group);
            update_post_meta($post_id, 'vmp_shipping_groups', array_values($shipping_groups));

            $first_group = $shipping_groups[0] ?? [];
            $first_receipt = (string) ($first_group['receipt_no'] ?? '');
            $first_courier = (string) (($first_group['receipt_courier'] ?? '') ?: ($first_group['courier'] ?? ''));
            $first_note = (string) ($first_group['seller_note'] ?? '');

            if ($first_receipt !== '') {
                update_post_meta($post_id, 'vmp_receipt_no', $first_receipt);
            } else {
                delete_post_meta($post_id, 'vmp_receipt_no');
            }

            if ($first_courier !== '') {
                update_post_meta($post_id, 'vmp_receipt_courier', $first_courier);
            } else {
                delete_post_meta($post_id, 'vmp_receipt_courier');
            }

            if ($first_note !== '') {
                update_post_meta($post_id, 'vmp_seller_note', $first_note);
            } else {
                delete_post_meta($post_id, 'vmp_seller_note');
            }
        } else {
            delete_post_meta($post_id, 'vmp_receipt_no');
            delete_post_meta($post_id, 'vmp_receipt_courier');
            delete_post_meta($post_id, 'vmp_seller_note');
        }

        $service = new StarSellerService();
        $seller_ids = [];
        foreach (OrderData::get_items((int) $post_id) as $item) {
            $seller_id = isset($item['seller_id']) ? (int) $item['seller_id'] : 0;
            if ($seller_id > 0) {
                $seller_ids[$seller_id] = $seller_id;
            }
        }
        foreach ($seller_ids as $seller_id) {
            $service->recalculate($seller_id);
        }

        $buyer_id = (int) get_post_meta($post_id, 'vmp_user_id', true);
        if ($buyer_id > 0 && $previous_status !== $normalized_status) {
            (new EmailTemplateService())->send_customer_status_update($post_id, $normalized_status);
        }
    }

    private function money($value)
    {
        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }

    public function admin_styles()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'vmp_order') {
            return;
        }
        ?>
        <style>
            .vmp-order-admin-card {
                border: 1px solid #dcdcde;
                background: #fff;
                padding: 16px;
                margin-top: 12px;
            }
            .vmp-order-admin-card__title {
                margin: 0 0 12px;
                font-size: 14px;
            }
            .vmp-order-admin-tracking {
                margin-top: 12px;
                border-top: 1px solid #dcdcde;
                padding-top: 12px;
            }
            .vmp-order-admin-tracking__title {
                font-weight: 600;
                margin-bottom: 8px;
            }
            .vmp-order-admin-tracking__item {
                border-top: 1px solid #f0f0f1;
                padding: 8px 0;
            }
            .vmp-order-admin-tracking__item:first-child {
                border-top: 0;
                padding-top: 0;
            }
        </style>
        <?php
    }
}
