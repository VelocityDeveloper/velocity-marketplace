<?php
use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Modules\Review\StarSellerService;
    $published_products_query = new \WP_Query([
        'post_type' => 'store_product',
        'post_status' => 'publish',
        'author' => $current_user_id,
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);
    $published_products = (int) $published_products_query->found_posts;

    $seller_order_ids = OrderData::seller_orders_query($current_user_id, 365);
    $products_sold_qty = 0;
    $products_paid_qty = 0;
    $cancelled_count = 0;
    $omzet_total = 0;
    $daily = [];

    foreach ($seller_order_ids as $order_id) {
        $status = (string) get_post_meta($order_id, 'vmp_status', true);
        $items = OrderData::seller_items($order_id, $current_user_id);
        $line_qty = 0;
        $line_total = 0;
        foreach ($items as $line) {
            $line_qty += (int) ($line['qty'] ?? 0);
            $line_total += (float) ($line['subtotal'] ?? 0);
        }

        if (!in_array($status, ['cancelled', 'refunded'], true)) {
            $products_sold_qty += $line_qty;
            $omzet_total += $line_total;
        }
        if (in_array($status, ['pending_verification', 'processing', 'shipped', 'completed'], true)) {
            $products_paid_qty += $line_qty;
        }
        if (in_array($status, ['cancelled', 'refunded'], true)) {
            $cancelled_count++;
        }

        $day = get_post_time('Y-m-d', false, $order_id);
        if (!isset($daily[$day])) {
            $daily[$day] = ['orders' => 0, 'omzet' => 0, 'statuses' => []];
        }
        $daily[$day]['orders']++;
        $daily[$day]['omzet'] += $line_total;
        if (!isset($daily[$day]['statuses'][$status])) {
            $daily[$day]['statuses'][$status] = 0;
        }
        $daily[$day]['statuses'][$status]++;
    }

    krsort($daily);
    $daily = array_slice($daily, 0, 14, true);
    $max_omzet = 0;
    foreach ($daily as $row) {
        if ((float) $row['omzet'] > $max_omzet) {
            $max_omzet = (float) $row['omzet'];
        }
    }
    $seller_summary = (new StarSellerService())->summary($current_user_id);
    ?>
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted"><?php echo esc_html__('Produk Aktif', 'velocity-marketplace'); ?></div><div class="h5 mb-0"><?php echo esc_html($published_products); ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted"><?php echo esc_html__('Produk Terjual', 'velocity-marketplace'); ?></div><div class="h5 mb-0"><?php echo esc_html($products_sold_qty); ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted"><?php echo esc_html__('Produk Dibayar', 'velocity-marketplace'); ?></div><div class="h5 mb-0"><?php echo esc_html($products_paid_qty); ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted"><?php echo esc_html__('Pesanan Dibatalkan', 'velocity-marketplace'); ?></div><div class="h5 mb-0"><?php echo esc_html($cancelled_count); ?></div></div></div></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted"><?php echo esc_html__('Rating Toko', 'velocity-marketplace'); ?></div><div class="h5 mb-0"><?php echo esc_html(number_format((float) ($seller_summary['rating_average'] ?? 0), 1, ',', '.') . '/5'); ?></div></div></div></div>
        <div class="col-6 col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted"><?php echo esc_html__('Jumlah Ulasan', 'velocity-marketplace'); ?></div><div class="h5 mb-0"><?php echo esc_html((string) (int) ($seller_summary['rating_count'] ?? 0)); ?></div></div></div></div>
        <div class="col-12 col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted"><?php echo esc_html__('Cancel Rate', 'velocity-marketplace'); ?></div><div class="h5 mb-0"><?php echo esc_html(number_format((float) ($seller_summary['cancel_rate'] ?? 0), 2, ',', '.') . '%'); ?></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3"><div class="card-body"><h3 class="h6 mb-2"><?php echo esc_html__('Total Revenue', 'velocity-marketplace'); ?></h3><div class="h5 text-danger mb-0"><?php echo esc_html($money($omzet_total)); ?></div></div></div>

    <div class="card border-0 shadow-sm"><div class="card-body"><h3 class="h6 mb-3"><?php echo esc_html__('Daily Summary for the Last 14 Days', 'velocity-marketplace'); ?></h3>
        <?php if (empty($daily)) : ?>
            <div class="small text-muted"><?php echo esc_html__('There is no order data to display yet.', 'velocity-marketplace'); ?></div>
        <?php else : ?>
            <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th><?php echo esc_html__('Tanggal', 'velocity-marketplace'); ?></th><th class="text-center"><?php echo esc_html__('Orders', 'velocity-marketplace'); ?></th><th><?php echo esc_html__('Revenue', 'velocity-marketplace'); ?></th><th><?php echo esc_html__('Status', 'velocity-marketplace'); ?></th></tr></thead><tbody>
            <?php foreach ($daily as $day => $row) : $percent = $max_omzet > 0 ? (int) round((((float) $row['omzet']) / $max_omzet) * 100) : 0; $status_parts = []; foreach ((array) $row['statuses'] as $skey => $svalue) { $status_parts[] = OrderData::status_label($skey) . ': ' . (int) $svalue; } ?>
                <tr><td><?php echo esc_html($day); ?></td><td class="text-center"><?php echo esc_html((string) ((int) $row['orders'])); ?></td><td><div class="small mb-1"><?php echo esc_html($money($row['omzet'])); ?></div><div class="progress" style="height:6px;"><div class="progress-bar bg-dark" style="width: <?php echo esc_attr((string) $percent); ?>%;"></div></div></td><td class="small text-muted"><?php echo esc_html(implode(' | ', $status_parts)); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></div>


