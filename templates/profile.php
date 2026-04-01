<?php
use VelocityMarketplace\Modules\Account\Account;
use VelocityMarketplace\Modules\Message\MessageRepository;
use VelocityMarketplace\Modules\Notification\NotificationRepository;
use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Modules\Wishlist\WishlistRepository;
use VelocityMarketplace\Frontend\Template;

$notice = isset($_REQUEST['vmp_notice']) ? sanitize_text_field((string) wp_unslash($_REQUEST['vmp_notice'])) : '';
$error = isset($_REQUEST['vmp_error']) ? sanitize_text_field((string) wp_unslash($_REQUEST['vmp_error'])) : '';
$tab = isset($_REQUEST['tab']) ? sanitize_key((string) wp_unslash($_REQUEST['tab'])) : '';

if (!is_user_logged_in()) {
    $profile_redirect = \VelocityMarketplace\Support\Settings::profile_url();
    $login_url = wp_login_url($profile_redirect);
    $register_url = wp_registration_url();
    ?>
    <div class="container py-4 vmp-wrap">
        <?php if ($notice !== '') : ?><div class="alert alert-success py-2"><?php echo esc_html($notice); ?></div><?php endif; ?>
        <?php if ($error !== '') : ?><div class="alert alert-danger py-2"><?php echo esc_html($error); ?></div><?php endif; ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h3 class="h5 mb-2"><?php echo esc_html__('Akses Akun', 'velocity-marketplace'); ?></h3>
                <p class="text-muted mb-3"><?php echo esc_html__('Masuk atau daftar untuk mengakses pesanan, profil, pesan, dan pengelolaan toko dari satu dashboard.', 'velocity-marketplace'); ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo esc_url($login_url); ?>" class="btn btn-dark"><?php echo esc_html__('Masuk', 'velocity-marketplace'); ?></a>
                    <?php if (get_option('users_can_register')) : ?>
                        <a href="<?php echo esc_url($register_url); ?>" class="btn btn-primary"><?php echo esc_html__('Daftar Akun', 'velocity-marketplace'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php
    return;
}

$current_user_id = get_current_user_id();
$can_sell = Account::can_sell($current_user_id);
if ($tab === '') {
    $tab = 'orders';
}
if ($tab === 'seller') {
    $tab = 'seller_products';
}

$status_labels = OrderData::statuses();
$notification_repo = new NotificationRepository();
$notifications = $notification_repo->all($current_user_id);
$unread_count = $notification_repo->unread_count($current_user_id);
$message_repo = new MessageRepository();
$selected_message_to = isset($_GET['message_to']) ? (int) wp_unslash($_GET['message_to']) : 0;
$selected_message_order = isset($_GET['message_order']) ? (int) wp_unslash($_GET['message_order']) : 0;
$selected_message_invoice = $selected_message_order > 0 ? (string) get_post_meta($selected_message_order, 'vmp_invoice', true) : '';
$message_contacts = $message_repo->contacts($current_user_id);
$message_unread_count = $message_repo->unread_count($current_user_id);
$selected_contact_exists = false;
foreach ($message_contacts as $contact_row) {
    if ((int) ($contact_row['id'] ?? 0) === $selected_message_to) {
        $selected_contact_exists = true;
        break;
    }
}
if ($selected_message_to > 0 && !$selected_contact_exists) {
    $selected_user = get_userdata($selected_message_to);
    if ($selected_user && (current_user_can('manage_options') || $message_repo->can_contact($current_user_id, $selected_message_to))) {
        $message_contacts[] = [
            'id' => $selected_message_to,
            'name' => $selected_user->display_name !== '' ? $selected_user->display_name : $selected_user->user_login,
            'role' => Account::user_role_label($selected_message_to),
        ];
    }
}
if ($selected_message_to > 0) {
    $message_repo->mark_thread_read($selected_message_to, $current_user_id);
}
$message_contacts = $message_repo->contacts($current_user_id);
$message_thread = $selected_message_to > 0 ? $message_repo->thread($selected_message_to, $current_user_id, 200) : [];
$selected_message_contact = null;
foreach ($message_contacts as $contact_row) {
    if ((int) ($contact_row['id'] ?? 0) === $selected_message_to) {
        $selected_message_contact = $contact_row;
        break;
    }
}
if (!$selected_message_contact && $selected_message_to > 0) {
    $selected_user = get_userdata($selected_message_to);
    if ($selected_user && (current_user_can('manage_options') || $message_repo->can_contact($current_user_id, $selected_message_to))) {
        $selected_message_contact = [
            'id' => $selected_message_to,
            'name' => $selected_user->display_name !== '' ? $selected_user->display_name : $selected_user->user_login,
            'role' => Account::user_role_label($selected_message_to),
            'last_message' => '',
            'last_created_at' => '',
            'last_order_id' => 0,
            'last_order_invoice' => '',
            'unread_count' => 0,
        ];
        array_unshift($message_contacts, $selected_message_contact);
    }
}
$wishlist_repo = new WishlistRepository();
$wishlist_ids = $wishlist_repo->get_ids($current_user_id);
$wishlist_count = count($wishlist_ids);

$money = static function ($value) {
    return esc_html__('Rp', 'velocity-marketplace') . ' ' . number_format((float) $value, 0, ',', '.');
};

$nav_base_url = remove_query_arg([
    'vmp_notice',
    'vmp_error',
    'invoice',
    'message_to',
    'message_order',
    'tab',
]);

$logout_url = add_query_arg([
    'vmp_logout' => 1,
    'vmp_nonce' => wp_create_nonce('vmp_logout'),
], $nav_base_url);

$store_name = (string) get_user_meta($current_user_id, 'vmp_store_name', true);
$store_address = (string) get_user_meta($current_user_id, 'vmp_store_address', true);
$profile_complete = !$can_sell || ($store_name !== '' && $store_address !== '');
$is_star_seller = !empty(get_user_meta($current_user_id, 'vmp_is_star_seller', true));

$account_tabs = [
    ['key' => 'orders', 'label' => __('Pesanan Saya', 'velocity-marketplace')],
    ['key' => 'account_profile', 'label' => __('Profil Saya', 'velocity-marketplace')],
    ['key' => 'wishlist', 'label' => __('Wishlist', 'velocity-marketplace') . ($wishlist_count > 0 ? ' (' . $wishlist_count . ')' : '')],
    ['key' => 'tracking', 'label' => __('Tracking', 'velocity-marketplace')],
    ['key' => 'messages', 'label' => __('Pesan', 'velocity-marketplace') . ($message_unread_count > 0 ? ' (' . $message_unread_count . ')' : '')],
    ['key' => 'notifications', 'label' => __('Notifikasi', 'velocity-marketplace') . ($unread_count > 0 ? ' (' . $unread_count . ')' : '')],
    ['key' => 'wp_profile', 'label' => __('Pengaturan Akun', 'velocity-marketplace'), 'url' => admin_url('profile.php')],
];
$store_tabs = [];
if ($can_sell) {
    $store_tabs = [
        ['key' => 'seller_home', 'label' => __('Beranda Toko', 'velocity-marketplace')],
        ['key' => 'seller_report', 'label' => __('Laporan', 'velocity-marketplace')],
        ['key' => 'seller_products', 'label' => __('Produk', 'velocity-marketplace')],
        ['key' => 'seller_profile', 'label' => __('Profil Toko', 'velocity-marketplace')],
    ];
}

$active_menu = in_array($tab, array_column($store_tabs, 'key'), true) ? 'store' : 'account';
$menu_groups = [
    [
        'key' => 'account',
        'label' => __('Akun', 'velocity-marketplace'),
        'tab' => 'orders',
        'items' => $account_tabs,
    ],
];
if ($can_sell) {
    $menu_groups[] = [
        'key' => 'store',
        'label' => __('Toko', 'velocity-marketplace'),
        'tab' => 'seller_home',
        'items' => $store_tabs,
    ];
}

$active_submenu = $active_menu === 'store' ? $store_tabs : $account_tabs;
?>
<div class="container py-4 vmp-wrap">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0"><?php echo esc_html__('Akun Saya', 'velocity-marketplace'); ?></h2>
            <small class="text-muted"><?php echo esc_html__('Kelola pesanan, profil, pesan, dan aktivitas toko dari satu dashboard.', 'velocity-marketplace'); ?></small>
        </div>
        <a href="<?php echo esc_url($logout_url); ?>" class="btn btn-sm btn-outline-dark"><?php echo esc_html__('Log Out', 'velocity-marketplace'); ?></a>
    </div>

    <?php if ($notice !== '') : ?><div class="alert alert-success py-2"><?php echo esc_html($notice); ?></div><?php endif; ?>
    <?php if ($error !== '') : ?><div class="alert alert-danger py-2"><?php echo esc_html($error); ?></div><?php endif; ?>

    <div class="vmp-dashboard-menu mb-3">
        <div class="vmp-dashboard-nav">
            <?php foreach ($menu_groups as $group) : ?>
                <a class="vmp-dashboard-nav__link<?php echo $active_menu === $group['key'] ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['tab' => $group['tab']], $nav_base_url)); ?>"><?php echo esc_html($group['label']); ?></a>
            <?php endforeach; ?>
        </div>
        <div class="vmp-dashboard-submenu">
            <?php foreach ($active_submenu as $it) : ?>
                <?php
                $item_url = isset($it['url']) ? (string) $it['url'] : add_query_arg(['tab' => $it['key']], $nav_base_url);
                $is_active = !isset($it['url']) && $tab === $it['key'];
                ?>
                <a class="vmp-dashboard-submenu__link<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url($item_url); ?>"><?php echo esc_html($it['label']); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    $view_data = get_defined_vars();
    if ($tab === 'orders') {
        echo Template::render('account/orders', $view_data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } elseif ($tab === 'account_profile') {
        echo Template::render('account/profile', $view_data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } elseif ($tab === 'wishlist') {
        echo Template::render('account/wishlist', $view_data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } elseif ($tab === 'tracking') {
        echo Template::render('account/tracking', $view_data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } elseif ($tab === 'messages') {
        echo Template::render('account/messages', $view_data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } elseif ($tab === 'notifications') {
        echo Template::render('account/notifications', $view_data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } elseif ($tab === 'seller_home' && $can_sell) {
        echo Template::render('seller/home', $view_data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } elseif ($tab === 'seller_report' && $can_sell) {
        echo Template::render('seller/report', $view_data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } elseif ($tab === 'seller_products' && $can_sell) {
        echo Template::render('seller/products', $view_data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } elseif ($tab === 'seller_profile' && $can_sell) {
        echo Template::render('seller/profile', $view_data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } else {
        echo '<div class="alert alert-warning mb-0">' . esc_html__('The selected menu is not available.', 'velocity-marketplace') . '</div>';
    }
    ?>

    <div class="mt-4">
        <?php echo do_shortcode('[vmp_recently_viewed limit="4" exclude_current="false"]'); ?>
    </div>
</div>
