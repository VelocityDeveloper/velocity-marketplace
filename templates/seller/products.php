<?php
use WpStore\Domain\Product\ProductFields;
    $product_captcha_html = \VelocityMarketplace\Modules\Captcha\CaptchaBridge::render();
    $edit_product_id = isset($_GET['edit_product']) ? (int) $_GET['edit_product'] : 0;
    $edit_product = null;
    if ($edit_product_id > 0 && get_post_type($edit_product_id) === 'store_product') {
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
        $terms = wp_get_post_terms($edit_product_id, 'store_product_cat', ['fields' => 'ids']);
        if (!is_wp_error($terms) && !empty($terms)) {
            $defaults['category_id'] = (int) $terms[0];
        }
    }

    $featured_image_id = $edit_product ? (int) get_post_thumbnail_id($edit_product_id) : 0;
    $featured_image_url = $featured_image_id > 0 ? wp_get_attachment_image_url($featured_image_id, 'medium') : '';
    $cats = get_terms(['taxonomy' => 'store_product_cat','hide_empty' => false]);
    $products_query = new \WP_Query(['post_type' => 'store_product','post_status' => ['publish', 'pending', 'draft'],'posts_per_page' => 30,'author' => $current_user_id,'orderby' => 'date','order' => 'DESC']);
    ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <?php if (!$profile_complete) : ?><div class="alert alert-warning"><?php echo wp_kses_post(__('Lengkapi <strong>Profil Toko</strong> sebelum menambahkan produk baru.', 'velocity-marketplace')); ?></div><?php endif; ?>
            <div class="card border-0 shadow-sm"><div class="card-body">
                <h3 class="h6 mb-3"><?php echo esc_html($edit_product ? __('Edit Produk', 'velocity-marketplace') : __('Tambah Produk', 'velocity-marketplace')); ?></h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="vmp_action" value="seller_save_product">
                    <input type="hidden" name="product_id" value="<?php echo esc_attr($edit_product ? $edit_product_id : 0); ?>">
                    <?php wp_nonce_field('vmp_seller_product', 'vmp_seller_product_nonce'); ?>
                    <div class="row g-2">
                        <div class="col-md-8"><label class="form-label"><?php echo esc_html__('Nama Produk', 'velocity-marketplace'); ?></label><input type="text" name="title" class="form-control" required value="<?php echo esc_attr($defaults['title']); ?>"></div>
                        <div class="col-md-4"><label class="form-label"><?php echo esc_html__('Kategori', 'velocity-marketplace'); ?></label><select name="category_id" class="form-select"><option value="0"><?php echo esc_html__('Pilih kategori', 'velocity-marketplace'); ?></option><?php if (!is_wp_error($cats)) : foreach ($cats as $cat) : ?><option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected((int) $defaults['category_id'], (int) $cat->term_id); ?>><?php echo esc_html($cat->name); ?></option><?php endforeach; endif; ?></select></div>
                        <div class="col-12">
                            <label class="form-label"><?php echo esc_html__('Deskripsi', 'velocity-marketplace'); ?></label>
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
                            <label class="form-label"><?php echo esc_html__('Gambar Utama', 'velocity-marketplace'); ?></label>
                            <div class="vmp-media-field" data-multiple="0">
                                <input type="hidden" id="featured_image_id" name="featured_image_id" class="vmp-media-field__input" value="<?php echo esc_attr($featured_image_id); ?>">
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <button type="button" class="btn btn-outline-dark btn-sm vmp-media-field__open" data-title="<?php echo esc_attr__('Gambar Utama', 'velocity-marketplace'); ?>" data-button="<?php echo esc_attr__('Gunakan gambar ini', 'velocity-marketplace'); ?>"><?php echo esc_html__('Pilih dari Media Library', 'velocity-marketplace'); ?></button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm vmp-media-field__clear" <?php disabled($featured_image_id <= 0); ?>><?php echo esc_html__('Remove Image', 'velocity-marketplace'); ?></button>
                                </div>
                                <div class="vmp-media-field__preview" data-placeholder="<?php echo esc_attr__('No featured image yet.', 'velocity-marketplace'); ?>">
                                    <?php if ($featured_image_url) : ?>
                                        <div class="vmp-media-field__grid vmp-media-field__grid--single">
                                            <div class="vmp-media-field__item" data-id="<?php echo esc_attr((string) $featured_image_id); ?>">
                                                <img src="<?php echo esc_url($featured_image_url); ?>" alt="<?php echo esc_attr__('Featured product image', 'velocity-marketplace'); ?>" class="vmp-media-field__image">
                                                <button type="button" class="btn-close vmp-media-field__remove" aria-label="<?php echo esc_attr__('Remove image', 'velocity-marketplace'); ?>"></button>
                                            </div>
                                        </div>
                                    <?php else : ?>
                                        <div class="vmp-media-field__empty text-muted small"><?php echo esc_html__('No image selected.', 'velocity-marketplace'); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-text"><?php echo esc_html__('Pilih atau unggah gambar utama melalui media library WordPress.', 'velocity-marketplace'); ?></div>
                            </div>
                        </div>
                        <?php echo ProductFields::render_sections($edit_product ? $edit_product_id : 0, 'frontend'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <?php if ($product_captcha_html !== '') : ?><div class="mt-3"><?php echo $product_captcha_html; ?></div><?php endif; ?>
                    <div class="mt-3 d-flex gap-2"><button class="btn btn-primary btn-sm" type="submit" <?php disabled(!$profile_complete); ?>><?php echo esc_html($edit_product ? __('Save Changes', 'velocity-marketplace') : __('Simpan Produk', 'velocity-marketplace')); ?></button><?php if ($edit_product) : ?><a href="<?php echo esc_url(add_query_arg(['tab' => 'seller_products'], remove_query_arg('edit_product'))); ?>" class="btn btn-outline-secondary btn-sm"><?php echo esc_html__('Batal', 'velocity-marketplace'); ?></a><?php endif; ?></div>
                </form>
            </div></div>
        </div>
        <div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-body"><h3 class="h6 mb-2"><?php echo esc_html__('Daftar Produk', 'velocity-marketplace'); ?></h3>
            <?php if ($products_query->have_posts()) : ?>
                <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th><?php echo esc_html__('Judul', 'velocity-marketplace'); ?></th><th><?php echo esc_html__('Status', 'velocity-marketplace'); ?></th><th></th></tr></thead><tbody>
                <?php while ($products_query->have_posts()) : $products_query->the_post(); $pid = get_the_ID(); $delete_url = add_query_arg(['tab' => 'seller_products','vmp_delete_product' => $pid,'vmp_nonce' => wp_create_nonce('vmp_delete_product_' . $pid)]); $premium = !empty(get_post_meta($pid, 'premium_request', true)); ?>
                    <tr><td><a href="<?php echo esc_url(get_permalink($pid)); ?>" target="_blank"><?php the_title(); ?></a><div class="small text-muted"><?php echo esc_html($money((float) get_post_meta($pid, 'price', true))); ?></div><?php if ($premium) : ?><div class="small text-muted"><?php echo esc_html__('Pengajuan premium sedang ditinjau.', 'velocity-marketplace'); ?></div><?php endif; ?></td><td><?php echo esc_html(get_post_status($pid)); ?></td><td class="text-end"><a class="btn btn-outline-dark btn-sm" href="<?php echo esc_url(add_query_arg(['tab' => 'seller_products', 'edit_product' => $pid])); ?>"><?php echo esc_html__('Edit', 'velocity-marketplace'); ?></a> <a class="btn btn-outline-danger btn-sm" href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('<?php echo esc_js(__('Hapus produk ini?', 'velocity-marketplace')); ?>')"><?php echo esc_html__('Hapus', 'velocity-marketplace'); ?></a></td></tr>
                <?php endwhile; wp_reset_postdata(); ?>
                </tbody></table></div>
            <?php else : ?>
                <div class="small text-muted"><?php echo esc_html__('No products have been added yet.', 'velocity-marketplace'); ?></div>
            <?php endif; ?>
        </div></div></div>
    </div>


