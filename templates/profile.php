<?php
use VelocityMarketplace\Frontend\Account;
use VelocityMarketplace\Support\NotificationRepository;
use VelocityMarketplace\Support\OrderData;
use VelocityMarketplace\Support\ProductFields;
use VelocityMarketplace\Support\WishlistRepository;

$notice = isset($_GET['vmp_notice']) ? sanitize_text_field((string) wp_unslash($_GET['vmp_notice'])) : '';
$error = isset($_GET['vmp_error']) ? sanitize_text_field((string) wp_unslash($_GET['vmp_error'])) : '';
$tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';

if (!is_user_logged_in()) {
    ?>
    <div class="container py-4 vmp-wrap">
        <?php if ($notice !== '') : ?><div class="alert alert-success py-2"><?php echo esc_html($notice); ?></div><?php endif; ?>
        <?php if ($error !== '') : ?><div class="alert alert-danger py-2"><?php echo esc_html($error); ?></div><?php endif; ?>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="h6 mb-3">Login</h3>
                        <form method="post">
                            <input type="hidden" name="vmp_action" value="login">
                            <?php wp_nonce_field('vmp_login', 'vmp_login_nonce'); ?>
                            <div class="mb-2"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
                            <div class="mb-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                            <button type="submit" class="btn btn-dark btn-sm">Login</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="h6 mb-3">Registrasi</h3>
                        <form method="post">
                            <input type="hidden" name="vmp_action" value="register">
                            <?php wp_nonce_field('vmp_register', 'vmp_register_nonce'); ?>
                            <div class="mb-2"><label class="form-label">Nama</label><input type="text" name="name" class="form-control"></div>
                            <div class="mb-2"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
                            <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                            <div class="mb-2"><label class="form-label">No HP</label><input type="text" name="phone" class="form-control"></div>
                            <div class="mb-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                            <div class="mb-2"><label class="form-label">Konfirmasi Password</label><input type="password" name="password_confirm" class="form-control" required></div>
                            <div class="mb-3"><label class="form-label">Daftar Sebagai</label><select name="role_type" class="form-select"><option value="customer">Customer</option><option value="seller">Seller</option></select></div>
                            <button type="submit" class="btn btn-primary btn-sm">Daftar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
    return;
}

$current_user_id = get_current_user_id();
$is_seller = Account::is_seller($current_user_id);
if ($tab === '') {
    $tab = $is_seller ? 'seller_home' : 'orders';
}
if ($tab === 'seller') {
    $tab = 'seller_products';
}

$status_labels = OrderData::statuses();
$notification_repo = new NotificationRepository();
$notifications = $notification_repo->all($current_user_id);
$unread_count = $notification_repo->unread_count($current_user_id);
$wishlist_repo = new WishlistRepository();
$wishlist_ids = $wishlist_repo->get_ids($current_user_id);

$money = static function ($value) {
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
};

$logout_url = add_query_arg([
    'vmp_logout' => 1,
    'vmp_nonce' => wp_create_nonce('vmp_logout'),
]);

$store_name = (string) get_user_meta($current_user_id, 'vmp_store_name', true);
$store_address = (string) get_user_meta($current_user_id, 'vmp_store_address', true);
$profile_complete = !$is_seller || ($store_name !== '' && $store_address !== '');
$is_star_seller = !empty(get_user_meta($current_user_id, 'vmp_is_star_seller', true));

$tabs = [
    ['key' => 'orders', 'label' => 'Riwayat Belanja'],
    ['key' => 'wishlist', 'label' => 'Wishlist'],
    ['key' => 'tracking', 'label' => 'Tracking'],
    ['key' => 'notifications', 'label' => 'Notifikasi (' . $unread_count . ')'],
];
if ($is_seller) {
    $tabs[] = ['key' => 'seller_home', 'label' => 'Beranda Toko'];
    $tabs[] = ['key' => 'seller_report', 'label' => 'Laporan'];
    $tabs[] = ['key' => 'seller_products', 'label' => 'Produk'];
    $tabs[] = ['key' => 'seller_profile', 'label' => 'Edit Profil'];
}
?>
<div class="container py-4 vmp-wrap">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0">Dashboard Marketplace</h2>
            <small class="text-muted">Order, produk, profil toko, wishlist, notifikasi, tracking</small>
        </div>
        <a href="<?php echo esc_url($logout_url); ?>" class="btn btn-sm btn-outline-dark">Logout</a>
    </div>

    <?php if ($notice !== '') : ?><div class="alert alert-success py-2"><?php echo esc_html($notice); ?></div><?php endif; ?>
    <?php if ($error !== '') : ?><div class="alert alert-danger py-2"><?php echo esc_html($error); ?></div><?php endif; ?>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ($tabs as $it) : ?>
            <a class="btn btn-sm <?php echo $tab === $it['key'] ? 'btn-dark' : 'btn-outline-dark'; ?>" href="<?php echo esc_url(add_query_arg(['tab' => $it['key']])); ?>"><?php echo esc_html($it['label']); ?></a>
        <?php endforeach; ?>
    </div>
<?php if ($tab === 'orders') : ?>
    <?php
    $invoice = isset($_GET['invoice']) ? sanitize_text_field((string) wp_unslash($_GET['invoice'])) : '';
    if ($invoice !== '') {
        $query = new \WP_Query([
            'post_type' => 'vmp_order',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'vmp_user_id', 'value' => (string) $current_user_id, 'compare' => '='],
                ['key' => 'vmp_invoice', 'value' => $invoice, 'compare' => '='],
            ],
        ]);
    } else {
        $query = new \WP_Query([
            'post_type' => 'vmp_order',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'meta_key' => 'vmp_user_id',
            'meta_value' => (string) $current_user_id,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
    }
    ?>
    <?php if (!$query->have_posts()) : ?>
        <div class="alert alert-info mb-0">Belum ada riwayat belanja.</div>
    <?php elseif ($invoice !== '') : ?>
        <?php
        $query->the_post();
        $order_id = get_the_ID();
        $invoice_meta = (string) get_post_meta($order_id, 'vmp_invoice', true);
        $items = get_post_meta($order_id, 'vmp_items', true);
        $total = (float) get_post_meta($order_id, 'vmp_total', true);
        $status = (string) get_post_meta($order_id, 'vmp_status', true);
        $payment = (string) get_post_meta($order_id, 'vmp_payment_method', true);
        $shipping = get_post_meta($order_id, 'vmp_shipping', true);
        $notes = (string) get_post_meta($order_id, 'vmp_notes', true);
        $transfer_proof_id = (int) get_post_meta($order_id, 'vmp_transfer_proof_id', true);
        $transfer_proof_url = $transfer_proof_id > 0 ? wp_get_attachment_url($transfer_proof_id) : '';
        $receipt_no = (string) get_post_meta($order_id, 'vmp_receipt_no', true);
        $receipt_courier = (string) get_post_meta($order_id, 'vmp_receipt_courier', true);
        if (!is_array($items)) {
            $items = [];
        }
        if (!is_array($shipping)) {
            $shipping = [];
        }
        ?>
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div><strong>Invoice:</strong> <?php echo esc_html($invoice_meta); ?></div>
                    <div><strong>Tanggal:</strong> <?php echo esc_html(get_the_date('d-m-Y H:i', $order_id)); ?></div>
                    <div><strong>Status:</strong> <?php echo esc_html(OrderData::status_label($status)); ?></div>
                    <div><strong>Pembayaran:</strong> <?php echo esc_html($payment !== '' ? $payment : '-'); ?></div>
                </div>
                <div class="col-md-6">
                    <div><strong>Kurir:</strong> <?php echo esc_html($receipt_courier !== '' ? $receipt_courier : ($shipping['courier'] ?? '-')); ?></div>
                    <div><strong>No Resi:</strong> <?php echo esc_html($receipt_no !== '' ? $receipt_no : '-'); ?></div>
                </div>
            </div>
            <hr>
            <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Produk</th><th class="text-end">Harga</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th></tr></thead><tbody>
            <?php foreach ($items as $item) : $price = (float) ($item['price'] ?? 0); $qty = (int) ($item['qty'] ?? 0); $line_subtotal = isset($item['subtotal']) ? (float) $item['subtotal'] : ($price * $qty); ?>
                <tr><td><?php echo esc_html((string) ($item['title'] ?? '-')); ?></td><td class="text-end"><?php echo esc_html(number_format($price, 0, ',', '.')); ?></td><td class="text-center"><?php echo esc_html($qty); ?></td><td class="text-end"><?php echo esc_html(number_format($line_subtotal, 0, ',', '.')); ?></td></tr>
            <?php endforeach; ?>
            </tbody><tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end text-danger"><?php echo esc_html(number_format($total, 0, ',', '.')); ?></th></tr></tfoot></table></div>
            <div class="small text-muted mt-2">Catatan: <?php echo esc_html($notes !== '' ? $notes : '-'); ?></div>
            <?php if ($transfer_proof_url) : ?><a href="<?php echo esc_url($transfer_proof_url); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">Lihat Bukti Transfer</a><?php endif; ?>
        </div></div>

        <div class="card border-0 shadow-sm"><div class="card-body">
            <h3 class="h6 mb-2">Upload Bukti Transfer</h3>
            <form method="post" enctype="multipart/form-data" class="row g-2">
                <input type="hidden" name="vmp_action" value="buyer_upload_transfer">
                <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                <?php wp_nonce_field('vmp_upload_transfer_' . $order_id, 'vmp_transfer_nonce'); ?>
                <div class="col-md-8"><input type="file" name="transfer_proof" class="form-control" accept="image/*,.pdf" required></div>
                <div class="col-md-4"><button type="submit" class="btn btn-dark w-100">Kirim Bukti Transfer</button></div>
            </form>
        </div></div>
        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <div class="table-responsive border rounded"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Invoice</th><th>Tanggal</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Aksi</th></tr></thead><tbody>
        <?php while ($query->have_posts()) : $query->the_post(); $order_id = get_the_ID(); $invoice_meta = (string) get_post_meta($order_id, 'vmp_invoice', true); $status = (string) get_post_meta($order_id, 'vmp_status', true); $total = (float) get_post_meta($order_id, 'vmp_total', true); ?>
            <tr><td><?php echo esc_html($invoice_meta); ?></td><td><?php echo esc_html(get_the_date('d-m-Y H:i', $order_id)); ?></td><td><?php echo esc_html(OrderData::status_label($status)); ?></td><td class="text-end"><?php echo esc_html(number_format($total, 0, ',', '.')); ?></td><td class="text-end"><a class="btn btn-sm btn-outline-dark" href="<?php echo esc_url(add_query_arg(['tab' => 'orders', 'invoice' => $invoice_meta])); ?>">Detail</a></td></tr>
        <?php endwhile; wp_reset_postdata(); ?>
        </tbody></table></div>
    <?php endif; ?>

<?php elseif ($tab === 'wishlist') : ?>
    <?php if (empty($wishlist_ids)) : ?>
        <div class="alert alert-info mb-0">Wishlist masih kosong. Klik ikon hati di katalog untuk menambah produk.</div>
    <?php else : ?>
        <?php $wishlist_query = new \WP_Query(['post_type' => 'vmp_product','post_status' => 'publish','posts_per_page' => 100,'post__in' => $wishlist_ids,'orderby' => 'post__in']); ?>
        <div class="table-responsive border rounded"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Produk</th><th class="text-end">Harga</th><th class="text-end">Aksi</th></tr></thead><tbody>
        <?php while ($wishlist_query->have_posts()) : $wishlist_query->the_post(); $pid = get_the_ID(); $price = (float) get_post_meta($pid, 'price', true); ?>
            <tr>
                <td><a href="<?php echo esc_url(get_permalink($pid)); ?>" target="_blank"><?php the_title(); ?></a></td>
                <td class="text-end"><?php echo esc_html($money($price)); ?></td>
                <td class="text-end"><form method="post" class="d-inline"><input type="hidden" name="vmp_action" value="wishlist_remove"><input type="hidden" name="product_id" value="<?php echo esc_attr($pid); ?>"><?php wp_nonce_field('vmp_wishlist_remove_' . $pid, 'vmp_wishlist_nonce'); ?><button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form></td>
            </tr>
        <?php endwhile; wp_reset_postdata(); ?>
        </tbody></table></div>
    <?php endif; ?>
<?php elseif ($tab === 'tracking') : ?>
    <?php
    $invoice = isset($_GET['invoice']) ? sanitize_text_field((string) wp_unslash($_GET['invoice'])) : '';
    $tracking_order = null;
    if ($invoice !== '') {
        $tracking_query = new \WP_Query([
            'post_type' => 'vmp_order',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                ['key' => 'vmp_invoice', 'value' => $invoice, 'compare' => '='],
            ],
        ]);

        if ($tracking_query->have_posts()) {
            $tracking_query->the_post();
            $candidate_id = get_the_ID();
            $owner_id = (int) get_post_meta($candidate_id, 'vmp_user_id', true);
            $can_view = $owner_id === $current_user_id || current_user_can('manage_options') || OrderData::has_seller($candidate_id, $current_user_id);
            if ($can_view) {
                $tracking_order = get_post($candidate_id);
            }
            wp_reset_postdata();
        }
    }
    ?>
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <form method="get" class="row g-2">
            <input type="hidden" name="tab" value="tracking">
            <div class="col-md-8"><label class="form-label">Kode Invoice</label><input type="text" name="invoice" class="form-control" value="<?php echo esc_attr($invoice); ?>" placeholder="Contoh: VMP-20260304-123456" required></div>
            <div class="col-md-4 d-flex align-items-end"><button class="btn btn-dark w-100" type="submit">Lacak</button></div>
        </form>
    </div></div>

    <?php if ($invoice !== '' && !$tracking_order) : ?>
        <div class="alert alert-warning mb-0">Invoice tidak ditemukan atau kamu tidak punya akses.</div>
    <?php elseif ($tracking_order) : ?>
        <?php
        $tracking_id = (int) $tracking_order->ID;
        $tracking_status = (string) get_post_meta($tracking_id, 'vmp_status', true);
        $tracking_payment = (string) get_post_meta($tracking_id, 'vmp_payment_method', true);
        $tracking_receipt = (string) get_post_meta($tracking_id, 'vmp_receipt_no', true);
        $tracking_courier = (string) get_post_meta($tracking_id, 'vmp_receipt_courier', true);
        ?>
        <div class="card border-0 shadow-sm"><div class="card-body"><div class="row g-3"><div class="col-md-6"><div><strong>Invoice:</strong> <?php echo esc_html($invoice); ?></div><div><strong>Status:</strong> <?php echo esc_html(OrderData::status_label($tracking_status)); ?></div><div><strong>Metode Bayar:</strong> <?php echo esc_html($tracking_payment !== '' ? $tracking_payment : '-'); ?></div></div><div class="col-md-6"><div><strong>Kurir:</strong> <?php echo esc_html($tracking_courier !== '' ? $tracking_courier : '-'); ?></div><div><strong>No Resi:</strong> <?php echo esc_html($tracking_receipt !== '' ? $tracking_receipt : '-'); ?></div></div></div></div></div>
    <?php endif; ?>

<?php elseif ($tab === 'notifications') : ?>
    <div class="card border-0 shadow-sm"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h6 mb-0">Notifikasi</h3>
            <form method="post"><input type="hidden" name="vmp_action" value="notification_mark_all"><?php wp_nonce_field('vmp_notification_mark_all', 'vmp_notification_nonce'); ?><button type="submit" class="btn btn-sm btn-outline-dark">Tandai Semua Dibaca</button></form>
        </div>
        <?php if (empty($notifications)) : ?>
            <div class="small text-muted">Belum ada notifikasi.</div>
        <?php else : ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $row) : ?>
                    <div class="list-group-item px-0 <?php echo empty($row['is_read']) ? 'bg-light' : ''; ?>">
                        <div class="d-flex justify-content-between gap-2 flex-wrap">
                            <div>
                                <div class="fw-semibold"><?php echo esc_html($row['title']); ?></div>
                                <div class="small text-muted"><?php echo esc_html($row['message']); ?></div>
                                <div class="small text-muted"><?php echo esc_html(mysql2date('d-m-Y H:i', (string) $row['created_at'])); ?></div>
                                <?php if (!empty($row['url'])) : ?><a class="small" href="<?php echo esc_url($row['url']); ?>">Buka detail</a><?php endif; ?>
                            </div>
                            <div class="d-flex gap-1 align-items-start">
                                <?php if (empty($row['is_read'])) : ?>
                                    <form method="post"><input type="hidden" name="vmp_action" value="notification_mark_read"><input type="hidden" name="notification_id" value="<?php echo esc_attr($row['id']); ?>"><?php wp_nonce_field('vmp_notification_action_' . $row['id'], 'vmp_notification_nonce'); ?><button class="btn btn-sm btn-outline-success" type="submit">Dibaca</button></form>
                                <?php endif; ?>
                                <form method="post"><input type="hidden" name="vmp_action" value="notification_delete"><input type="hidden" name="notification_id" value="<?php echo esc_attr($row['id']); ?>"><?php wp_nonce_field('vmp_notification_action_' . $row['id'], 'vmp_notification_nonce'); ?><button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div></div>
<?php elseif ($tab === 'seller_home' && $is_seller) : ?>
    <?php $seller_order_ids = OrderData::seller_orders_query($current_user_id, 120); ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <h3 class="h6 mb-2">Status Seller</h3>
                <div class="mb-2">Label: <?php echo $is_star_seller ? '<span class="badge bg-warning text-dark">Star Seller</span>' : '<span class="badge bg-secondary">Seller Biasa</span>'; ?></div>
                <div class="small text-muted">Order masuk: <strong><?php echo esc_html(count($seller_order_ids)); ?></strong></div>
                <?php if (!$profile_complete) : ?><div class="alert alert-warning py-2 mt-2 mb-0">Lengkapi profil toko dulu sebelum tambah produk baru.</div><?php endif; ?>
            </div></div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <h3 class="h6 mb-2">Order Masuk</h3>
                <?php if (empty($seller_order_ids)) : ?>
                    <div class="small text-muted">Belum ada order masuk.</div>
                <?php else : ?>
                    <div class="accordion" id="vmpSellerOrders">
                        <?php foreach ($seller_order_ids as $idx => $order_id) :
                            $invoice_meta = (string) get_post_meta($order_id, 'vmp_invoice', true);
                            $status = (string) get_post_meta($order_id, 'vmp_status', true);
                            $customer = get_post_meta($order_id, 'vmp_customer', true);
                            $seller_items = OrderData::seller_items($order_id, $current_user_id);
                            $seller_total = OrderData::seller_total($order_id, $current_user_id);
                            $transfer_proof_id = (int) get_post_meta($order_id, 'vmp_transfer_proof_id', true);
                            $transfer_proof_url = $transfer_proof_id > 0 ? wp_get_attachment_url($transfer_proof_id) : '';
                            $receipt_no = (string) get_post_meta($order_id, 'vmp_receipt_no', true);
                            $receipt_courier = (string) get_post_meta($order_id, 'vmp_receipt_courier', true);
                            if (!is_array($customer)) {
                                $customer = [];
                            }
                            ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="vmpOrderHeading<?php echo esc_attr($order_id); ?>">
                                    <button class="accordion-button <?php echo $idx > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#vmpOrderCollapse<?php echo esc_attr($order_id); ?>" aria-expanded="<?php echo $idx === 0 ? 'true' : 'false'; ?>">
                                        <?php echo esc_html($invoice_meta); ?> | <?php echo esc_html(OrderData::status_label($status)); ?> | <?php echo esc_html($money($seller_total)); ?>
                                    </button>
                                </h2>
                                <div id="vmpOrderCollapse<?php echo esc_attr($order_id); ?>" class="accordion-collapse collapse <?php echo $idx === 0 ? 'show' : ''; ?>" data-bs-parent="#vmpSellerOrders">
                                    <div class="accordion-body">
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <div><strong>Pembeli:</strong> <?php echo esc_html($customer['name'] ?? '-'); ?></div>
                                                <div><strong>Telepon:</strong> <?php echo esc_html($customer['phone'] ?? '-'); ?></div>
                                                <div><strong>Alamat:</strong> <?php echo esc_html($customer['address'] ?? '-'); ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div><strong>Kurir:</strong> <?php echo esc_html($receipt_courier !== '' ? $receipt_courier : '-'); ?></div>
                                                <div><strong>No Resi:</strong> <?php echo esc_html($receipt_no !== '' ? $receipt_no : '-'); ?></div>
                                                <?php if ($transfer_proof_url) : ?>
                                                    <a href="<?php echo esc_url($transfer_proof_url); ?>" class="btn btn-sm btn-outline-primary mt-2" target="_blank">Lihat Bukti Transfer</a>
                                                <?php else : ?>
                                                    <div class="small text-muted mt-1">Bukti transfer belum diupload.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="table-responsive mb-3"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Produk</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th></tr></thead><tbody>
                                        <?php foreach ($seller_items as $line) : ?>
                                            <tr><td><?php echo esc_html(isset($line['title']) ? (string) $line['title'] : '-'); ?></td><td class="text-center"><?php echo esc_html((string) ((int) ($line['qty'] ?? 0))); ?></td><td class="text-end"><?php echo esc_html($money((float) ($line['subtotal'] ?? 0))); ?></td></tr>
                                        <?php endforeach; ?>
                                        </tbody></table></div>

                                        <form method="post" class="row g-2">
                                            <input type="hidden" name="vmp_action" value="seller_update_order">
                                            <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                                            <?php wp_nonce_field('vmp_seller_order_' . $order_id, 'vmp_seller_order_nonce'); ?>
                                            <div class="col-md-3"><label class="form-label">Status</label><select name="order_status" class="form-select form-select-sm"><?php foreach ($status_labels as $status_key => $status_text) : ?><option value="<?php echo esc_attr($status_key); ?>" <?php selected($status, $status_key); ?>><?php echo esc_html($status_text); ?></option><?php endforeach; ?></select></div>
                                            <div class="col-md-3"><label class="form-label">Kurir</label><input type="text" name="receipt_courier" class="form-control form-control-sm" value="<?php echo esc_attr($receipt_courier); ?>" placeholder="JNE/SICEPAT/JNT"></div>
                                            <div class="col-md-3"><label class="form-label">No Resi</label><input type="text" name="receipt_no" class="form-control form-control-sm" value="<?php echo esc_attr($receipt_no); ?>" placeholder="Nomor resi"></div>
                                            <div class="col-md-3"><label class="form-label">Catatan</label><input type="text" name="seller_note" class="form-control form-control-sm" placeholder="Catatan untuk pembeli"></div>
                                            <div class="col-12 text-end"><button type="submit" class="btn btn-sm btn-dark">Simpan Update Order</button></div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div></div>
        </div>
    </div>
<?php elseif ($tab === 'seller_report' && $is_seller) : ?>
    <?php
    $published_products_query = new \WP_Query([
        'post_type' => 'vmp_product',
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
    ?>
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Produk Publish</div><div class="h5 mb-0"><?php echo esc_html($published_products); ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Produk Terjual</div><div class="h5 mb-0"><?php echo esc_html($products_sold_qty); ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Produk Dibayar</div><div class="h5 mb-0"><?php echo esc_html($products_paid_qty); ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Pembelian Dibatalkan</div><div class="h5 mb-0"><?php echo esc_html($cancelled_count); ?></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3"><div class="card-body"><h3 class="h6 mb-2">Omzet Total</h3><div class="h5 text-danger mb-0"><?php echo esc_html($money($omzet_total)); ?></div></div></div>

    <div class="card border-0 shadow-sm"><div class="card-body"><h3 class="h6 mb-3">Grafik Harian (14 Hari Terakhir)</h3>
        <?php if (empty($daily)) : ?>
            <div class="small text-muted">Belum ada data order untuk ditampilkan.</div>
        <?php else : ?>
            <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Tanggal</th><th class="text-center">Order</th><th>Omzet</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($daily as $day => $row) : $percent = $max_omzet > 0 ? (int) round((((float) $row['omzet']) / $max_omzet) * 100) : 0; $status_parts = []; foreach ((array) $row['statuses'] as $skey => $svalue) { $status_parts[] = OrderData::status_label($skey) . ': ' . (int) $svalue; } ?>
                <tr><td><?php echo esc_html($day); ?></td><td class="text-center"><?php echo esc_html((string) ((int) $row['orders'])); ?></td><td><div class="small mb-1"><?php echo esc_html($money($row['omzet'])); ?></div><div class="progress" style="height:6px;"><div class="progress-bar bg-dark" style="width: <?php echo esc_attr((string) $percent); ?>%;"></div></div></td><td class="small text-muted"><?php echo esc_html(implode(' | ', $status_parts)); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></div>
<?php elseif ($tab === 'seller_products' && $is_seller) : ?>
    <?php
    $edit_product_id = isset($_GET['edit_product']) ? (int) $_GET['edit_product'] : 0;
    $edit_product = null;
    if ($edit_product_id > 0 && get_post_type($edit_product_id) === 'vmp_product') {
        $author_id = (int) get_post_field('post_author', $edit_product_id);
        if ($author_id === $current_user_id || current_user_can('manage_options')) {
            $edit_product = get_post($edit_product_id);
        }
    }

    $defaults = [
        'title' => $edit_product ? $edit_product->post_title : '',
        'description' => $edit_product ? $edit_product->post_content : '',
        'category_id' => 0,
    ];

    if ($edit_product) {
        $terms = wp_get_post_terms($edit_product_id, 'vmp_product_cat', ['fields' => 'ids']);
        if (!is_wp_error($terms) && !empty($terms)) {
            $defaults['category_id'] = (int) $terms[0];
        }
    }

    $featured_image_id = $edit_product ? (int) get_post_thumbnail_id($edit_product_id) : 0;
    $featured_image_url = $featured_image_id > 0 ? wp_get_attachment_image_url($featured_image_id, 'medium') : '';
    $cats = get_terms(['taxonomy' => 'vmp_product_cat','hide_empty' => false]);
    $products_query = new \WP_Query(['post_type' => 'vmp_product','post_status' => ['publish', 'pending', 'draft'],'posts_per_page' => 30,'author' => $current_user_id,'orderby' => 'date','order' => 'DESC']);
    ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <?php if (!$profile_complete) : ?><div class="alert alert-warning">Profil toko wajib diisi dulu di tab <strong>Edit Profil</strong> sebelum tambah produk.</div><?php endif; ?>
            <div class="card border-0 shadow-sm"><div class="card-body">
                <h3 class="h6 mb-3"><?php echo $edit_product ? 'Edit Iklan Produk' : 'Pasang Iklan Produk'; ?></h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="vmp_action" value="seller_save_product">
                    <input type="hidden" name="product_id" value="<?php echo esc_attr($edit_product ? $edit_product_id : 0); ?>">
                    <?php wp_nonce_field('vmp_seller_product', 'vmp_seller_product_nonce'); ?>
                    <div class="row g-2">
                        <div class="col-md-8"><label class="form-label">Judul Iklan</label><input type="text" name="title" class="form-control" required value="<?php echo esc_attr($defaults['title']); ?>"></div>
                        <div class="col-md-4"><label class="form-label">Kategori</label><select name="category_id" class="form-select"><option value="0">- Tanpa Kategori -</option><?php if (!is_wp_error($cats)) : foreach ($cats as $cat) : ?><option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected((int) $defaults['category_id'], (int) $cat->term_id); ?>><?php echo esc_html($cat->name); ?></option><?php endforeach; endif; ?></select></div>
                        <div class="col-12">
                            <label class="form-label">Deskripsi</label>
                            <div class="vmp-editor-wrap">
                                <?php
                                wp_editor($defaults['description'], 'vmp_product_description_' . ($edit_product ? $edit_product_id : 'new'), [
                                    'textarea_name' => 'description',
                                    'textarea_rows' => 10,
                                    'media_buttons' => false,
                                    'teeny' => false,
                                    'quicktags' => true,
                                ]);
                                ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Gambar Utama</label>
                            <div class="vmp-media-field" data-multiple="0">
                                <input type="hidden" id="featured_image_id" name="featured_image_id" class="vmp-media-field__input" value="<?php echo esc_attr($featured_image_id); ?>">
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <button type="button" class="btn btn-outline-dark btn-sm vmp-media-field__open" data-title="Gambar Utama" data-button="Gunakan gambar ini">Pilih dari Media Library</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm vmp-media-field__clear" <?php disabled($featured_image_id <= 0); ?>>Hapus Gambar</button>
                                </div>
                                <div class="vmp-media-field__preview" data-placeholder="Belum ada gambar utama.">
                                    <?php if ($featured_image_url) : ?>
                                        <div class="vmp-media-field__grid vmp-media-field__grid--single">
                                            <div class="vmp-media-field__item" data-id="<?php echo esc_attr((string) $featured_image_id); ?>">
                                                <img src="<?php echo esc_url($featured_image_url); ?>" alt="Gambar utama produk" class="vmp-media-field__image">
                                                <button type="button" class="btn-close vmp-media-field__remove" aria-label="Hapus gambar"></button>
                                            </div>
                                        </div>
                                    <?php else : ?>
                                        <div class="vmp-media-field__empty text-muted small">Belum ada gambar dipilih.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-text">Pilih atau upload gambar utama langsung dari media library WordPress.</div>
                            </div>
                        </div>
                        <?php echo ProductFields::render_sections($edit_product ? $edit_product_id : 0, 'frontend'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <div class="mt-3 d-flex gap-2"><button class="btn btn-primary btn-sm" type="submit" <?php disabled(!$profile_complete); ?>><?php echo $edit_product ? 'Update Iklan' : 'Kirim Iklan'; ?></button><?php if ($edit_product) : ?><a href="<?php echo esc_url(add_query_arg(['tab' => 'seller_products'], remove_query_arg('edit_product'))); ?>" class="btn btn-outline-secondary btn-sm">Batal Edit</a><?php endif; ?></div>
                </form>
            </div></div>
        </div>
        <div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-body"><h3 class="h6 mb-2">Produk Saya</h3>
            <?php if ($products_query->have_posts()) : ?>
                <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Judul</th><th>Status</th><th></th></tr></thead><tbody>
                <?php while ($products_query->have_posts()) : $products_query->the_post(); $pid = get_the_ID(); $delete_url = add_query_arg(['tab' => 'seller_products','vmp_delete_product' => $pid,'vmp_nonce' => wp_create_nonce('vmp_delete_product_' . $pid)]); $premium = !empty(get_post_meta($pid, 'premium_request', true)); ?>
                    <tr><td><a href="<?php echo esc_url(get_permalink($pid)); ?>" target="_blank"><?php the_title(); ?></a><div class="small text-muted"><?php echo esc_html($money((float) get_post_meta($pid, 'price', true))); ?></div><?php if ($premium) : ?><div class="small text-muted">Premium: Menunggu review</div><?php endif; ?></td><td><?php echo esc_html(get_post_status($pid)); ?></td><td class="text-end"><a class="btn btn-outline-dark btn-sm" href="<?php echo esc_url(add_query_arg(['tab' => 'seller_products', 'edit_product' => $pid])); ?>">Edit</a> <a class="btn btn-outline-danger btn-sm" href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('Hapus iklan ini?')">Hapus</a></td></tr>
                <?php endwhile; wp_reset_postdata(); ?>
                </tbody></table></div>
            <?php else : ?>
                <div class="small text-muted">Belum ada iklan produk.</div>
            <?php endif; ?>
        </div></div></div>
    </div>
<?php elseif ($tab === 'seller_profile' && $is_seller) : ?>
    <?php
    $store_phone = (string) get_user_meta($current_user_id, 'vmp_store_phone', true);
    $store_whatsapp = (string) get_user_meta($current_user_id, 'vmp_store_whatsapp', true);
    $store_description = (string) get_user_meta($current_user_id, 'vmp_store_description', true);
    $store_subdistrict = (string) get_user_meta($current_user_id, 'vmp_store_subdistrict', true);
    $store_city = (string) get_user_meta($current_user_id, 'vmp_store_city', true);
    $store_province = (string) get_user_meta($current_user_id, 'vmp_store_province', true);
    $store_postcode = (string) get_user_meta($current_user_id, 'vmp_store_postcode', true);
    $store_couriers = get_user_meta($current_user_id, 'vmp_store_couriers', true);
    if (!is_array($store_couriers)) {
        $store_couriers = [];
    }
    $avatar_id = (int) get_user_meta($current_user_id, 'vmp_store_avatar_id', true);
    $avatar_url = $avatar_id > 0 ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : '';
    $courier_options = [
        'jne' => 'JNE',
        'pos' => 'POS Indonesia',
        'tiki' => 'TIKI',
        'sicepat' => 'SiCepat',
        'jnt' => 'J&T',
        'ninja' => 'Ninja Xpress',
        'wahana' => 'Wahana',
        'lion' => 'Lion Parcel',
    ];
    ?>
    <div class="card border-0 shadow-sm"><div class="card-body">
        <h3 class="h6 mb-3">Edit Profil Toko</h3>
        <form method="post" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="vmp_action" value="save_store_profile">
            <?php wp_nonce_field('vmp_store_profile', 'vmp_store_profile_nonce'); ?>
            <div class="col-md-6"><label class="form-label">Nama Toko</label><input type="text" name="store_name" class="form-control" value="<?php echo esc_attr($store_name); ?>" required></div>
            <div class="col-md-6"><label class="form-label">Foto Profil Toko</label><input type="file" name="store_avatar" class="form-control" accept="image/*"><?php if ($avatar_url) : ?><img src="<?php echo esc_url($avatar_url); ?>" alt="Avatar toko" class="mt-2 rounded" style="width:56px;height:56px;object-fit:cover;"><?php endif; ?></div>
            <div class="col-md-6"><label class="form-label">Kontak Telepon</label><input type="text" name="store_phone" class="form-control" value="<?php echo esc_attr($store_phone); ?>"></div>
            <div class="col-md-6"><label class="form-label">WhatsApp</label><input type="text" name="store_whatsapp" class="form-control" value="<?php echo esc_attr($store_whatsapp); ?>"></div>
            <div class="col-12"><label class="form-label">Alamat Toko</label><textarea name="store_address" class="form-control" rows="3" required><?php echo esc_textarea($store_address); ?></textarea></div>
            <div class="col-md-3"><label class="form-label">Kel/Kec</label><input type="text" name="store_subdistrict" class="form-control" value="<?php echo esc_attr($store_subdistrict); ?>"></div>
            <div class="col-md-3"><label class="form-label">Kota</label><input type="text" name="store_city" class="form-control" value="<?php echo esc_attr($store_city); ?>"></div>
            <div class="col-md-3"><label class="form-label">Provinsi</label><input type="text" name="store_province" class="form-control" value="<?php echo esc_attr($store_province); ?>"></div>
            <div class="col-md-3"><label class="form-label">Kode Pos</label><input type="text" name="store_postcode" class="form-control" value="<?php echo esc_attr($store_postcode); ?>"></div>
            <div class="col-12"><label class="form-label">Deskripsi Toko</label><textarea name="store_description" class="form-control" rows="3"><?php echo esc_textarea($store_description); ?></textarea></div>
            <div class="col-12"><label class="form-label">Kurir yang Digunakan</label><div class="d-flex flex-wrap gap-3"><?php foreach ($courier_options as $key => $label) : ?><label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="store_couriers[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $store_couriers, true)); ?>><?php echo esc_html($label); ?></label><?php endforeach; ?></div></div>
            <div class="col-12 d-flex justify-content-between align-items-center"><div><?php if ($is_star_seller) : ?><span class="badge bg-warning text-dark">Star Seller Aktif</span><?php else : ?><span class="badge bg-secondary">Star Seller Belum Aktif</span><?php endif; ?></div><button type="submit" class="btn btn-dark">Simpan Profil Toko</button></div>
        </form>
    </div></div>

<?php else : ?>
    <div class="alert alert-warning mb-0">Menu tidak tersedia.</div>
<?php endif; ?>
</div>
