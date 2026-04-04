<?php

namespace VelocityMarketplace\Modules\Review;

class ReviewAdmin
{
    public function register()
    {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'handle_actions']);
    }

    public function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=store_product',
            'Ulasan Produk',
            'Ulasan Produk',
            'manage_options',
            'vmp-reviews',
            [$this, 'render_page']
        );
    }

    public function handle_actions()
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        $action = isset($_GET['vmp_review_action']) ? sanitize_key((string) wp_unslash($_GET['vmp_review_action'])) : '';
        $review_id = isset($_GET['review_id']) ? (int) $_GET['review_id'] : 0;

        if ($page !== 'vmp-reviews' || $action === '' || $review_id <= 0) {
            return;
        }

        $nonce = isset($_GET['_wpnonce']) ? (string) wp_unslash($_GET['_wpnonce']) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vmp_review_action_' . $review_id)) {
            return;
        }

        $repo = new ReviewRepository();
        $notice = '';

        if ($action === 'approve') {
            $repo->set_approved($review_id, true);
            $notice = 'Ulasan disetujui.';
        } elseif ($action === 'unapprove') {
            $repo->set_approved($review_id, false);
            $notice = 'Ulasan disembunyikan.';
        } elseif ($action === 'delete') {
            $repo->delete($review_id);
            $notice = 'Ulasan dihapus.';
        }

        if ($notice !== '') {
            wp_safe_redirect(add_query_arg([
                'post_type' => 'store_product',
                'page' => 'vmp-reviews',
                'vmp_notice' => $notice,
            ], admin_url('edit.php')));
            exit;
        }
    }

    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $repo = new ReviewRepository();
        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 20;
        $total_items = $repo->count_all();
        $reviews = $repo->admin_reviews(($page - 1) * $per_page, $per_page);
        $total_pages = max(1, (int) ceil($total_items / $per_page));
        $notice = isset($_GET['vmp_notice']) ? sanitize_text_field((string) wp_unslash($_GET['vmp_notice'])) : '';
        ?>
        <div class="wrap">
            <h1>Ulasan Produk</h1>
            <?php if ($notice !== '') : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if (empty($reviews)) : ?>
                <p>Belum ada ulasan.</p>
                <?php return; ?>
            <?php endif; ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Pembeli</th>
                        <th>Seller</th>
                        <th>Rating</th>
                        <th>Judul</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $review) : ?>
                        <?php
                        $product_title = get_the_title((int) $review['product_id']);
                        $buyer = get_userdata((int) $review['user_id']);
                        $seller = get_userdata((int) $review['seller_id']);
                        $approve_url = wp_nonce_url(add_query_arg([
                            'post_type' => 'store_product',
                            'page' => 'vmp-reviews',
                            'vmp_review_action' => $review['is_approved'] ? 'unapprove' : 'approve',
                            'review_id' => (int) $review['id'],
                        ], admin_url('edit.php')), 'vmp_review_action_' . (int) $review['id']);
                        $delete_url = wp_nonce_url(add_query_arg([
                            'post_type' => 'store_product',
                            'page' => 'vmp-reviews',
                            'vmp_review_action' => 'delete',
                            'review_id' => (int) $review['id'],
                        ], admin_url('edit.php')), 'vmp_review_action_' . (int) $review['id']);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($product_title !== '' ? $product_title : 'Produk #' . (int) $review['product_id']); ?></strong>
                                <div class="description"><?php echo esc_html(wp_trim_words((string) $review['content'], 18)); ?></div>
                                <?php if (!empty($review['image_ids']) && is_array($review['image_ids'])) : ?>
                                    <div class="description"><?php echo esc_html(count($review['image_ids']) . ' foto review'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($buyer && $buyer->display_name !== '' ? $buyer->display_name : 'Member #' . (int) $review['user_id']); ?></td>
                            <td><?php echo esc_html($seller && $seller->display_name !== '' ? $seller->display_name : 'Member #' . (int) $review['seller_id']); ?></td>
                            <td><?php echo wp_kses_post(str_repeat('&#9733;', (int) $review['rating']) . str_repeat('&#9734;', max(0, 5 - (int) $review['rating']))); ?></td>
                            <td><?php echo esc_html($review['title'] !== '' ? $review['title'] : '-'); ?></td>
                            <td><?php echo esc_html($review['is_approved'] ? 'Tayang' : 'Disembunyikan'); ?></td>
                            <td><?php echo esc_html($review['created_at']); ?></td>
                            <td>
                                <a href="<?php echo esc_url($approve_url); ?>"><?php echo esc_html($review['is_approved'] ? 'Sembunyikan' : 'Setujui'); ?></a>
                                |
                                <a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('Hapus ulasan ini?');">Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php echo wp_kses_post(paginate_links([
                            'base' => add_query_arg([
                                'post_type' => 'store_product',
                                'page' => 'vmp-reviews',
                                'paged' => '%#%',
                            ], admin_url('edit.php')),
                            'format' => '',
                            'current' => $page,
                            'total' => $total_pages,
                        ])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

