<?php
use WpStore\Domain\Product\ProductFields;
use VelocityMarketplace\Support\Settings;
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
    $field_error = ProductFields::request_error_field('frontend');
    $field_error_message = ProductFields::request_error_message('frontend');
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
                        <div class="col-md-8" data-field-required="1">
                            <label class="form-label" for="vmp_product_title"><?php echo esc_html__('Nama Produk', 'velocity-marketplace'); ?> <span class="text-danger">*</span></label>
                            <input type="text" id="vmp_product_title" name="title" class="form-control<?php echo $field_error === 'title' ? ' is-invalid' : ''; ?>" data-required="1" value="<?php echo esc_attr($defaults['title']); ?>">
                            <?php if ($field_error === 'title' && $field_error_message !== '') : ?><div class="invalid-feedback d-block"><?php echo esc_html($field_error_message); ?></div><?php endif; ?>
                        </div>
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
                            <div class="vmp-media-field" data-multiple="0" data-overlay-remove="0">
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
        <div class="col-lg-5"><div class="card border-0 shadow-sm sticky-top"><div class="card-body"><h3 class="h6 mb-2"><?php echo esc_html__('Daftar Produk', 'velocity-marketplace'); ?></h3>
            <?php if ($products_query->have_posts()) : ?>

                <div class="table-responsive">
                    <table class="table table-sm mb-0">

                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Judul', 'velocity-marketplace'); ?></th>
                                <th><?php echo esc_html__('Status', 'velocity-marketplace'); ?></th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php while ($products_query->have_posts()) : ?>
                                
                                <?php
                                    $products_query->the_post();

                                    $pid = get_the_ID();

                                    $delete_url = add_query_arg([
                                        'tab' => 'seller_products',
                                        'vmp_delete_product' => $pid,
                                        'vmp_nonce' => wp_create_nonce('vmp_delete_product_' . $pid)
                                    ]);

                                    $premium = !empty(get_post_meta($pid, 'premium_request', true));
                                ?>

                                <tr>

                                    <td>

                                        <a href="<?php echo esc_url(get_permalink($pid)); ?>" target="_blank">
                                            <?php the_title(); ?>
                                        </a>

                                        <?php
                                            echo wps_product_price_html(
                                                $pid,
                                                [
                                                    'wrapper_class'     => 'small text-muted mt-1',
                                                    'sale_group_class'  => 'd-flex align-items-baseline gap-2',
                                                    'sale_class'        => 'fw-semibold text-dark',
                                                    'regular_class'     => 'text-muted small',
                                                    'price_class'       => 'fw-semibold text-dark',
                                                    'show_empty'        => false
                                                ]
                                            );
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        ?>

                                        <?php if ($premium) : ?>
                                            <div class="small text-muted">
                                                <?php echo esc_html__('Pengajuan premium sedang ditinjau.', 'velocity-marketplace'); ?>
                                            </div>
                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?php echo esc_html(get_post_status($pid)); ?>
                                    </td>

                                    <td class="text-end">

                                        <a
                                            class="btn btn-outline-dark btn-sm m-1"
                                            href="<?php echo esc_url(add_query_arg([
                                                'tab' => 'seller_products',
                                                'edit_product' => $pid
                                            ])); ?>"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-gear" viewBox="0 0 16 16"> <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492M5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0"/> <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115z"/> </svg>
                                        </a>

                                        <a
                                            class="btn btn-outline-danger btn-sm m-1"
                                            href="<?php echo esc_url($delete_url); ?>"
                                            onclick="return confirm('<?php echo esc_js(__('Hapus produk ini?', 'velocity-marketplace')); ?>')"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16"> <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/> <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/> </svg>
                                        </a>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                            <?php wp_reset_postdata(); ?>

                        </tbody>

                    </table>
                </div>

            <?php else : ?>

                <div class="small text-muted">
                    <?php echo esc_html__('No products have been added yet.', 'velocity-marketplace'); ?>
                </div>

            <?php endif; ?>
        </div></div></div>
    </div>
