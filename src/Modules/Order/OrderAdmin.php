<?php

namespace VelocityMarketplace\Modules\Order;

use VelocityMarketplace\Modules\Email\EmailTemplateService;
use VelocityMarketplace\Modules\Review\StarSellerService;
use VelocityMarketplace\Modules\Shipping\ShippingController;

class OrderAdmin
{
    public function register()
    {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_store_order', [$this, 'save_meta_box'], 20);
        add_action('wp_store_order_status_updated', [$this, 'sync_from_core_status'], 10, 3);
        add_action('updated_post_meta', [$this, 'watch_core_status_meta'], 10, 4);
        add_action('added_post_meta', [$this, 'watch_core_status_meta'], 10, 4);
        add_action('admin_head-post.php', [$this, 'admin_styles']);
        add_action('admin_head-post-new.php', [$this, 'admin_styles']);
    }

    public function add_meta_boxes()
    {
        add_meta_box(
            'vmp-order-fulfillment',
            'Marketplace Fulfillment',
            [$this, 'render_fulfillment_meta_box'],
            'store_order',
            'normal',
            'default'
        );
    }

    public function render_fulfillment_meta_box($post)
    {
        wp_nonce_field('vmp_order_fulfillment_meta_box', 'vmp_order_fulfillment_meta_box_nonce');

        $order_id = (int) $post->ID;
        $invoice = (string) get_post_meta($order_id, 'vmp_invoice', true);
        $core_status = (string) get_post_meta($order_id, '_store_order_status', true);
        $marketplace_status = (string) get_post_meta($order_id, 'vmp_status', true);
        $payment = (string) get_post_meta($order_id, '_store_order_payment_method', true);
        $payment_url = (string) get_post_meta($order_id, '_store_order_payment_url', true);
        $payment_token = (string) get_post_meta($order_id, '_store_order_payment_token', true);
        $payment_extra = get_post_meta($order_id, '_store_order_payment_extra', true);
        $payment_extra = is_array($payment_extra) ? $payment_extra : [];
        $shipping_groups = OrderData::shipping_groups($order_id);
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">Invoice Marketplace</th>
                    <td><?php echo esc_html($invoice !== '' ? $invoice : '-'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Status Core</th>
                    <td><?php echo esc_html($core_status !== '' ? $core_status : '-'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Status Marketplace</th>
                    <td><?php echo esc_html(OrderData::status_label($marketplace_status)); ?></td>
                </tr>
                <tr>
                    <th scope="row">Payment Method Core</th>
                    <td><?php echo esc_html($payment !== '' ? strtoupper($payment) : '-'); ?></td>
                </tr>
                <?php if ($payment_url !== '') : ?>
                    <tr>
                        <th scope="row">Payment URL Core</th>
                        <td><a href="<?php echo esc_url($payment_url); ?>" target="_blank" rel="noopener">Buka</a></td>
                    </tr>
                <?php endif; ?>
                <?php if ($payment_token !== '') : ?>
                    <tr>
                        <th scope="row">Payment Token Core</th>
                        <td><?php echo esc_html($payment_token); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($payment_extra['gateway_status']) || !empty($payment_extra['reference'])) : ?>
                    <tr>
                        <th scope="row">Gateway Meta</th>
                        <td>
                            <?php if (!empty($payment_extra['gateway_status'])) : ?>
                                <div>Status: <?php echo esc_html((string) $payment_extra['gateway_status']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($payment_extra['reference'])) : ?>
                                <div>Reference: <?php echo esc_html((string) $payment_extra['reference']); ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (!empty($shipping_groups)) : ?>
            <h4>Shipping Group per Seller</h4>
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
                                <th scope="row"><label for="vmp_group_status_<?php echo esc_attr((string) $index); ?>">Status Seller</label></th>
                                <td>
                                    <select id="vmp_group_status_<?php echo esc_attr((string) $index); ?>" name="vmp_group_status[<?php echo esc_attr((string) $index); ?>]">
                                        <?php foreach (OrderData::statuses() as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected(OrderData::shipping_group_status($group, $marketplace_status), $key); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
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
        <?php else : ?>
            <p class="description">Belum ada shipping group marketplace di order ini.</p>
        <?php endif; ?>
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

        $nonce = isset($_POST['vmp_order_fulfillment_meta_box_nonce']) ? (string) wp_unslash($_POST['vmp_order_fulfillment_meta_box_nonce']) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vmp_order_fulfillment_meta_box')) {
            $this->sync_from_core_status($post_id, (string) get_post_meta($post_id, '_store_order_status', true), 'save_post');
            return;
        }

        $previous_status = (string) get_post_meta($post_id, 'vmp_status', true);
        $core_status = (string) get_post_meta($post_id, '_store_order_status', true);
        $fallback_status = $this->map_core_status_to_marketplace($core_status);

        $shipping_groups = OrderData::shipping_groups($post_id);
        if (!empty($shipping_groups)) {
            $posted_statuses = isset($_POST['vmp_group_status']) && is_array($_POST['vmp_group_status']) ? wp_unslash($_POST['vmp_group_status']) : [];
            $posted_receipts = isset($_POST['vmp_group_receipt_no']) && is_array($_POST['vmp_group_receipt_no']) ? wp_unslash($_POST['vmp_group_receipt_no']) : [];
            $posted_couriers = isset($_POST['vmp_group_receipt_courier']) && is_array($_POST['vmp_group_receipt_courier']) ? wp_unslash($_POST['vmp_group_receipt_courier']) : [];
            $posted_notes = isset($_POST['vmp_group_seller_note']) && is_array($_POST['vmp_group_seller_note']) ? wp_unslash($_POST['vmp_group_seller_note']) : [];

            foreach ($shipping_groups as $index => &$group) {
                $group_status = isset($posted_statuses[$index]) ? (string) $posted_statuses[$index] : (string) ($group['status'] ?? $fallback_status);
                $group['status'] = OrderData::normalize_status($group_status);
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
            $summary_status = OrderData::summarize_shipping_statuses($shipping_groups, $fallback_status);
            update_post_meta($post_id, 'vmp_status', $summary_status);
            OrderData::sync_core_status($post_id, $summary_status);

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
            update_post_meta($post_id, 'vmp_status', $fallback_status);
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

        $buyer_id = OrderData::buyer_id($post_id);
        $current_status = (string) get_post_meta($post_id, 'vmp_status', true);
        if ($buyer_id > 0 && $previous_status !== $current_status) {
            (new EmailTemplateService())->send_customer_status_update($post_id, $current_status);
        }
    }

    public function sync_from_core_status($order_id, $core_status, $context = '')
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0 || get_post_type($order_id) !== 'store_order') {
            return;
        }

        $mapped_status = $this->map_core_status_to_marketplace((string) $core_status);
        $previous_status = (string) get_post_meta($order_id, 'vmp_status', true);
        update_post_meta($order_id, 'vmp_status', $mapped_status);

        $shipping_groups = OrderData::shipping_groups($order_id);
        if (!empty($shipping_groups)) {
            foreach ($shipping_groups as $index => $group) {
                if (!is_array($group)) {
                    continue;
                }
                $shipping_groups[$index]['status'] = $mapped_status;
            }
            update_post_meta($order_id, 'vmp_shipping_groups', array_values($shipping_groups));
        }

        $buyer_id = OrderData::buyer_id($order_id);
        if ($buyer_id > 0 && $previous_status !== $mapped_status && $context !== 'save_post') {
            (new EmailTemplateService())->send_customer_status_update($order_id, $mapped_status);
        }
    }

    public function watch_core_status_meta($meta_id, $object_id, $meta_key, $meta_value)
    {
        if ((string) $meta_key !== '_store_order_status') {
            return;
        }

        $this->sync_from_core_status((int) $object_id, (string) $meta_value, 'meta_update');
    }

    private function map_core_status_to_marketplace($core_status)
    {
        $core_status = sanitize_key((string) $core_status);
        $map = [
            'pending' => 'pending_payment',
            'awaiting_payment' => 'pending_payment',
            'paid' => 'processing',
            'processing' => 'processing',
            'shipped' => 'shipped',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
        ];

        return isset($map[$core_status]) ? $map[$core_status] : 'pending_payment';
    }

    private function money($value)
    {
        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }

    public function admin_styles()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'store_order') {
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

