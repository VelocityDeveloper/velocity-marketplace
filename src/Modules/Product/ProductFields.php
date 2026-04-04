<?php

namespace VelocityMarketplace\Modules\Product;

/**
 * Registry schema field meta untuk CPT produk canonical perusahaan.
 *
 * Struktur data yang dipakai file ini:
 * - Section:
 *   - `id` string unik section
 *   - `title` judul section di form
 *   - `fields` array daftar field
 *
 * - Field:
 *   - `name` label field
 *   - `id` meta key post
 *   - `type` tipe field
 *   - `desc` deskripsi/helper text
 *   - `placeholder` placeholder input
 *   - `default` nilai default
 *   - `contexts` area tampil, contoh `frontend`, `admin`
 *   - `full_width` bool, pakai kolom penuh saat render
 *   - `rows` jumlah baris untuk textarea
 *   - `min` batas minimum untuk number
 *   - `step` step untuk number
 *   - `options` opsi untuk select/radio
 *   - `multiple` bool untuk field file multi-attachment
 *   - `media_library` bool untuk field file berbasis media library
 *   - `required` bool untuk field yang wajib diisi
 *
 * Tipe field yang didukung:
 * - `text`
 * - `number`
 * - `textarea`
 * - `select`
 * - `radio`
 * - `checkbox`
 * - `date`
 * - `email`
 * - `url`
 * - `editor`
 * - `file`
 *
 * Catatan:
 * - Tambahkan field baru lewat `get_sections()`.
 * - `id` field akan dipakai langsung sebagai post meta key.
 * - Jika field `type=file` dan `multiple=true`, value disimpan sebagai array ID attachment.
 * - Jika field butuh opsi tetap, gunakan `options` dengan key yang eksplisit.
 * - Field inti produk mengikuti canonical contract `_store_*`.
 * - Gambar utama produk tidak didefinisikan di schema ini karena memakai featured image
 *   bawaan WordPress (`_thumbnail_id`), bukan post meta custom schema produk.
 */
class ProductFields
{
    public function register()
    {
        add_action('init', [$this, 'register_post_meta']);
    }

    /**
     * Mendefinisikan schema field produk per section.
     *
     * Context yang valid saat ini:
     * - `frontend`
     * - `admin`
     */
    public static function get_sections($context = 'frontend')
    {
        $sections = [
            [
                'id' => 'media',
                'title' => 'Media Produk',
                'fields' => [
                    [
                        'name' => 'Galeri Produk',
                        'id' => '_store_gallery_ids',
                        'type' => 'file',
                        'desc' => 'Pilih beberapa gambar dari media library untuk galeri produk.',
                        'contexts' => ['frontend', 'admin'],
                        'full_width' => true,
                        'multiple' => true,
                        'media_library' => true,
                    ],
                ],
            ],
            [
                'id' => 'pricing',
                'title' => 'Harga & Inventory',
                'fields' => [
                    [
                        'name' => 'Tipe Produk',
                        'id' => '_store_product_type',
                        'type' => 'select',
                        'desc' => 'Produk fisik atau digital.',
                        'default' => 'physical',
                        'options' => [
                            'physical' => 'Produk Fisik',
                            'digital' => 'Produk Digital',
                        ],
                    ],
                    [
                        'name' => 'SKU',
                        'id' => '_store_sku',
                        'type' => 'text',
                        'placeholder' => 'SKU produk',
                        'desc' => 'Kode unik produk.',
                    ],
                    [
                        'name' => 'Label Produk',
                        'id' => '_store_label',
                        'type' => 'select',
                        'placeholder' => '',
                        'desc' => 'Label badge pada kartu produk.',
                        'options' => [
                            '' => '-',
                            'label-new' => 'New',
                            'label-limited' => 'Terbatas',
                            'label-best' => 'Best Seller',
                        ],
                    ],
                    [
                        'name' => 'Harga Regular',
                        'id' => '_store_price',
                        'type' => 'number',
                        'placeholder' => '0',
                        'desc' => 'Harga utama produk.',
                        'min' => 0,
                        'step' => 0.01,
                    ],
                    [
                        'name' => 'Harga Promo',
                        'id' => '_store_sale_price',
                        'type' => 'number',
                        'placeholder' => '0',
                        'desc' => 'Kosongkan jika tidak ada promo.',
                        'min' => 0,
                        'step' => 0.01,
                    ],
                    [
                        'name' => 'Promo Sampai',
                        'id' => '_store_flashsale_until',
                        'type' => 'date',
                        'placeholder' => '',
                        'desc' => 'Tanggal akhir harga promo.',
                    ],
                    [
                        'name' => 'Stok',
                        'id' => '_store_stock',
                        'type' => 'number',
                        'placeholder' => '0',
                        'desc' => 'Kosongkan jika stok tidak dibatasi.',
                        'min' => 0,
                        'step' => 1,
                    ],
                    [
                        'name' => 'Berat (kg)',
                        'id' => '_store_weight_kg',
                        'type' => 'number',
                        'placeholder' => '0',
                        'desc' => 'Wajib diisi untuk perhitungan ongkir. Gunakan angka lebih dari 0.',
                        'min' => 0.001,
                        'step' => 0.001,
                        'required' => true,
                    ],
                    [
                        'name' => 'Minimal Order',
                        'id' => '_store_min_order',
                        'type' => 'number',
                        'placeholder' => '1',
                        'desc' => 'Jumlah minimum pembelian.',
                        'min' => 1,
                        'step' => 1,
                    ],
                ],
            ],
            [
                'id' => 'options',
                'title' => 'Opsi Produk',
                'fields' => [
                    [
                        'name' => 'Nama Opsi Varian',
                        'id' => '_store_option_name',
                        'type' => 'text',
                        'placeholder' => 'Warna',
                        'desc' => 'Nama pilihan yang tidak mengubah harga, misalnya Warna atau Motif.',
                        'default' => 'Pilihan Varian',
                    ],
                    [
                        'name' => 'Pilihan Varian',
                        'id' => '_store_options',
                        'type' => 'textarea',
                        'placeholder' => 'Merah, Biru, Hijau',
                        'desc' => 'Pisahkan dengan koma. Pilihan ini tidak mengubah harga produk.',
                        'rows' => 2,
                        'full_width' => true,
                    ],
                    [
                        'name' => 'Nama Opsi Harga',
                        'id' => '_store_option2_name',
                        'type' => 'text',
                        'placeholder' => 'Ukuran',
                        'desc' => 'Nama pilihan yang dapat menambah harga dari harga dasar produk.',
                        'default' => 'Pilihan Harga',
                    ],
                    [
                        'name' => 'Pilihan Harga',
                        'id' => '_store_advanced_options',
                        'type' => 'textarea',
                        'placeholder' => "Small=0\nMedium=10000\nLarge=20000",
                        'desc' => '1 baris = label=tambahan_harga. Gunakan 0 jika tidak ada tambahan harga.',
                        'rows' => 4,
                        'full_width' => true,
                    ],
                    [
                        'name' => 'Ajukan Iklan Premium',
                        'id' => 'premium_request',
                        'type' => 'checkbox',
                        'placeholder' => '',
                        'desc' => 'Produk akan masuk antrian review premium.',
                        'contexts' => ['frontend', 'admin'],
                    ],
                    [
                        'name' => 'Produk Premium',
                        'id' => 'is_premium',
                        'type' => 'checkbox',
                        'placeholder' => '',
                        'desc' => 'Tampilkan produk di urutan atas.',
                        'contexts' => ['admin'],
                    ],
                ],
            ],
        ];

        $filtered = [];
        foreach ($sections as $section) {
            $fields = [];
            foreach ((array) $section['fields'] as $field) {
                if (!self::field_matches_context($field, $context)) {
                    continue;
                }
                $fields[] = $field;
            }
            if (!empty($fields)) {
                $section['fields'] = $fields;
                $filtered[] = $section;
            }
        }

        return $filtered;
    }

    public function register_post_meta()
    {
        $registered = [];
        $fields = array_merge(self::get_fields('admin'), self::get_fields('frontend'));

        foreach ($fields as $field) {
            $meta_key = (string) $field['id'];
            if ($meta_key === '' || isset($registered[$meta_key])) {
                continue;
            }

            $registered[$meta_key] = true;

            $show_in_rest = true;
            if (($field['type'] ?? '') === 'file' && !empty($field['multiple'])) {
                $show_in_rest = [
                    'schema' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'integer',
                        ],
                    ],
                ];
            } elseif (in_array($meta_key, ['_store_options', '_store_advanced_options'], true)) {
                $show_in_rest = [
                    'schema' => [
                        'type' => 'array',
                    ],
                ];
            }

            register_post_meta('store_product', $meta_key, [
                'single' => true,
                'show_in_rest' => $show_in_rest,
                'type' => self::register_type($field),
                'sanitize_callback' => function ($value) use ($field) {
                    return self::sanitize_value($field, $value);
                },
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]);

            register_post_meta('vmp_product', $meta_key, [
                'single' => true,
                'show_in_rest' => false,
                'type' => self::register_type($field),
                'sanitize_callback' => function ($value) use ($field) {
                    return self::sanitize_value($field, $value);
                },
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]);
        }
    }

    public static function get_fields($context = 'frontend')
    {
        $fields = [];
        foreach (self::get_sections($context) as $section) {
            foreach ((array) $section['fields'] as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public static function get_value($post_id, $field)
    {
        $meta_key = (string) ($field['id'] ?? '');
        $default = isset($field['default']) ? $field['default'] : '';
        $type = isset($field['type']) ? (string) $field['type'] : 'text';
        $value = $meta_key !== '' ? get_post_meta((int) $post_id, $meta_key, true) : '';

        if (($value === '' || $value === null) && $default !== '') {
            return (string) $default;
        }

        if ($meta_key === '_store_options') {
            if (is_array($value)) {
                return implode(', ', array_values(array_filter(array_map('trim', array_map('strval', $value)))));
            }
        }

        if ($meta_key === '_store_advanced_options') {
            if (is_array($value)) {
                $rows = [];
                foreach ($value as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $label = isset($row['label']) ? trim((string) $row['label']) : '';
                    if ($label === '') {
                        continue;
                    }
                    $price = isset($row['price']) && is_numeric($row['price']) ? (float) $row['price'] : 0;
                    $rows[] = $label . '=' . $price;
                }
                return implode("\n", $rows);
            }
        }

        if ($type === 'checkbox') {
            return !empty($value) ? '1' : '0';
        }

        if ($type === 'file' && !empty($field['multiple'])) {
            return self::normalize_attachment_ids($value);
        }

        if ($type === 'file') {
            return is_numeric($value) ? (int) $value : 0;
        }

        return is_scalar($value) ? (string) $value : '';
    }

    public static function render_sections($post_id = 0, $context = 'frontend')
    {
        $html = '';
        foreach (self::get_sections($context) as $section) {
            $html .= '<div class="vmp-meta-section">';
            $html .= '<h4 class="vmp-meta-section__title">' . esc_html((string) $section['title']) . '</h4>';
            $html .= '<div class="row g-2">';
            foreach ((array) $section['fields'] as $field) {
                $html .= self::render_field($field, $post_id, $context);
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    public static function render_field($field, $post_id = 0, $context = 'frontend')
    {
        $field = is_array($field) ? $field : [];
        $meta_key = isset($field['id']) ? (string) $field['id'] : '';
        if ($meta_key === '') {
            return '';
        }

        $type = isset($field['type']) ? (string) $field['type'] : 'text';
        $value = self::get_value($post_id, $field);
        $label = isset($field['name']) ? (string) $field['name'] : $meta_key;
        $placeholder = isset($field['placeholder']) ? (string) $field['placeholder'] : '';
        $desc = isset($field['desc']) ? (string) $field['desc'] : '';
        $required = !empty($field['required']);
        $min = isset($field['min']) && $field['min'] !== '' ? ' min="' . esc_attr((string) $field['min']) . '"' : '';
        $step = isset($field['step']) && $field['step'] !== '' ? ' step="' . esc_attr((string) $field['step']) . '"' : '';
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr($placeholder) . '"' : '';
        $required_attr = $required ? ' required' : '';
        $col_class = !empty($field['full_width']) ? 'col-12' : 'col-md-6';

        ob_start();
        ?>
        <div class="<?php echo esc_attr($col_class); ?>">
            <?php if ($type === 'checkbox') : ?>
                <div class="form-check mt-4">
                    <input type="hidden" name="<?php echo esc_attr($meta_key); ?>" value="0">
                    <input class="form-check-input" type="checkbox" id="<?php echo esc_attr($meta_key); ?>" name="<?php echo esc_attr($meta_key); ?>" value="1" <?php checked($value, '1'); ?>>
                    <label class="form-check-label" for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($label); ?></label>
                    <?php if ($desc !== '') : ?><div class="form-text"><?php echo esc_html($desc); ?></div><?php endif; ?>
                </div>
            <?php else : ?>
                <label class="form-label" for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($label); ?></label>
                <?php if ($type === 'textarea') : ?>
                    <textarea id="<?php echo esc_attr($meta_key); ?>" name="<?php echo esc_attr($meta_key); ?>" class="form-control" rows="<?php echo esc_attr((string) ($field['rows'] ?? 4)); ?>"<?php echo $placeholder_attr; ?><?php echo $required_attr; ?>><?php echo esc_textarea($value); ?></textarea>
                <?php elseif ($type === 'select') : ?>
                    <select id="<?php echo esc_attr($meta_key); ?>" name="<?php echo esc_attr($meta_key); ?>" class="form-select"<?php echo $required_attr; ?>>
                        <?php foreach ((array) ($field['options'] ?? []) as $option_value => $option_label) : ?>
                            <option value="<?php echo esc_attr((string) $option_value); ?>" <?php selected($value, (string) $option_value); ?>><?php echo esc_html((string) $option_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($type === 'radio') : ?>
                    <div>
                        <?php foreach ((array) ($field['options'] ?? []) as $option_value => $option_label) : ?>
                            <label class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr((string) $option_value); ?>" <?php checked($value, (string) $option_value); ?>>
                                <span class="form-check-label"><?php echo esc_html((string) $option_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($type === 'editor') : ?>
                    <?php
                    wp_editor($value, 'editor_' . $meta_key . '_' . $context, [
                        'textarea_name' => $meta_key,
                        'textarea_rows' => (int) ($field['rows'] ?? 6),
                        'teeny' => true,
                        'media_buttons' => false,
                    ]);
                    ?>
                <?php elseif ($type === 'file' && !empty($field['media_library'])) : ?>
                    <?php
                    $open_button_class = $context === 'admin' ? 'button button-secondary vmp-media-field__open' : 'btn btn-outline-dark btn-sm vmp-media-field__open';
                    $clear_button_class = $context === 'admin' ? 'button button-secondary vmp-media-field__clear' : 'btn btn-outline-secondary btn-sm vmp-media-field__clear';
                    $preview_items = self::build_media_preview_items($value, !empty($field['multiple']));
                    $input_value = '';
                    if (!empty($field['multiple'])) {
                        $input_value = implode(',', array_map('intval', (array) $value));
                    } elseif (!empty($value)) {
                        $input_value = (string) ((int) $value);
                    }
                    ?>
                    <div class="vmp-media-field" data-multiple="<?php echo !empty($field['multiple']) ? '1' : '0'; ?>">
                        <input id="<?php echo esc_attr($meta_key); ?>" type="hidden" name="<?php echo esc_attr($meta_key); ?>" class="vmp-media-field__input" value="<?php echo esc_attr($input_value); ?>">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <button type="button" class="<?php echo esc_attr($open_button_class); ?>" data-title="<?php echo esc_attr($label); ?>" data-button="<?php echo esc_attr(!empty($field['multiple']) ? 'Gunakan gambar terpilih' : 'Gunakan gambar ini'); ?>">
                                <?php echo esc_html(!empty($field['multiple']) ? 'Pilih Galeri' : 'Pilih File'); ?>
                            </button>
                            <button type="button" class="<?php echo esc_attr($clear_button_class); ?>" <?php disabled(empty($preview_items)); ?>>Hapus Pilihan</button>
                        </div>
                        <div class="vmp-media-field__preview" data-placeholder="<?php echo esc_attr(!empty($field['multiple']) ? 'Belum ada gambar galeri.' : 'Belum ada file dipilih.'); ?>">
                            <?php echo self::render_media_preview_html($preview_items, !empty($field['multiple'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </div>
                <?php elseif ($type === 'file') : ?>
                    <input id="<?php echo esc_attr($meta_key); ?>" type="file" name="<?php echo esc_attr($meta_key); ?>" class="form-control">
                    <?php if (!empty($value)) : ?><div class="form-text"><?php echo esc_html((string) $value); ?></div><?php endif; ?>
                <?php else : ?>
                    <?php
                    $html_type = $type;
                    if (!in_array($html_type, ['text', 'number', 'email', 'url', 'date'], true)) {
                        $html_type = 'text';
                    }
                    ?>
                    <input id="<?php echo esc_attr($meta_key); ?>" type="<?php echo esc_attr($html_type); ?>" name="<?php echo esc_attr($meta_key); ?>" class="form-control" value="<?php echo esc_attr($value); ?>"<?php echo $placeholder_attr; ?><?php echo $min; ?><?php echo $step; ?><?php echo $required_attr; ?>>
                <?php endif; ?>
                <?php if ($desc !== '' && $type !== 'checkbox') : ?><div class="form-text"><?php echo esc_html($desc); ?></div><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function save($post_id, $context = 'frontend')
    {
        foreach (self::get_fields($context) as $field) {
            $meta_key = (string) ($field['id'] ?? '');
            if ($meta_key === '') {
                continue;
            }

            if (($field['type'] ?? 'text') === 'file') {
                self::save_file_field($post_id, $field);
                continue;
            }

            $raw = isset($_POST[$meta_key]) ? wp_unslash($_POST[$meta_key]) : '';
            $value = self::sanitize_value($field, $raw);
            update_post_meta($post_id, $meta_key, $value);
        }
    }

    public static function validate_submission($context = 'frontend')
    {
        foreach (self::get_fields($context) as $field) {
            $meta_key = (string) ($field['id'] ?? '');
            if ($meta_key === '' || empty($field['required'])) {
                continue;
            }

            $raw = isset($_POST[$meta_key]) ? wp_unslash($_POST[$meta_key]) : '';
            $label = isset($field['name']) ? (string) $field['name'] : $meta_key;
            $type = isset($field['type']) ? (string) $field['type'] : 'text';

            if ($type === 'number') {
                if ($raw === '' || !is_numeric($raw)) {
                    return new \WP_Error('required_field', sprintf(__('%s wajib diisi.', 'velocity-marketplace'), $label));
                }

                $value = (float) $raw;
                $min = isset($field['min']) && is_numeric($field['min']) ? (float) $field['min'] : null;
                if ($min !== null && $value < $min) {
                    return new \WP_Error('min_field', sprintf(__('%s harus lebih besar dari 0 untuk menghitung ongkir.', 'velocity-marketplace'), $label));
                }

                continue;
            }

            if (is_string($raw)) {
                $raw = trim($raw);
            }

            if ($raw === '' || $raw === null || $raw === []) {
                return new \WP_Error('required_field', sprintf(__('%s wajib diisi.', 'velocity-marketplace'), $label));
            }
        }

        return true;
    }

    public static function sanitize_value($field, $raw)
    {
        $type = isset($field['type']) ? (string) $field['type'] : 'text';

        if ($type === 'number') {
            return ($raw === '' || !is_numeric($raw)) ? '' : $raw + 0;
        }

        if ($type === 'email') {
            return sanitize_email((string) $raw);
        }

        if ($type === 'url') {
            return esc_url_raw((string) $raw);
        }

        if ($type === 'checkbox') {
            return !empty($raw) ? '1' : '0';
        }

        if ($type === 'select' || $type === 'radio') {
            $value = sanitize_text_field((string) $raw);
            $options = isset($field['options']) && is_array($field['options']) ? array_map('strval', array_keys($field['options'])) : [];
            if (!in_array($value, $options, true)) {
                return isset($field['default']) ? (string) $field['default'] : '';
            }
            return $value;
        }

        if ($type === 'textarea' || $type === 'editor') {
            $meta_key = isset($field['id']) ? (string) $field['id'] : '';
            if ($meta_key === '_store_options') {
                $parts = array_map('trim', explode(',', (string) $raw));
                return array_values(array_filter($parts, static function ($item) {
                    return $item !== '';
                }));
            }

            if ($meta_key === '_store_advanced_options') {
                $rows = preg_split('/\r\n|\r|\n/', (string) $raw);
                $items = [];
                foreach ((array) $rows as $row) {
                    $line = trim((string) $row);
                    if ($line === '') {
                        continue;
                    }

                    $parts = strpos($line, '=') !== false ? array_map('trim', explode('=', $line, 2)) : [$line, 0];
                    $label = isset($parts[0]) ? (string) $parts[0] : '';
                    if ($label === '') {
                        continue;
                    }

                    $items[] = [
                        'label' => $label,
                        'price' => isset($parts[1]) && is_numeric($parts[1]) ? (float) $parts[1] : 0.0,
                    ];
                }

                return $items;
            }

            return trim((string) wp_kses_post($raw));
        }

        if ($type === 'file') {
            if (!empty($field['multiple'])) {
                return self::filter_attachment_ids_for_current_user(self::normalize_attachment_ids($raw));
            }

            $attachment_id = is_numeric($raw) ? (int) $raw : 0;
            return self::attachment_allowed_for_current_user($attachment_id) ? $attachment_id : 0;
        }

        if ($type === 'date') {
            return sanitize_text_field((string) $raw);
        }

        return sanitize_text_field((string) $raw);
    }

    private static function save_file_field($post_id, $field)
    {
        $meta_key = (string) ($field['id'] ?? '');
        if ($meta_key === '') {
            return;
        }

        if (!empty($field['media_library']) && array_key_exists($meta_key, $_POST)) {
            $raw = wp_unslash($_POST[$meta_key]);
            $value = self::sanitize_value($field, $raw);

            if ((is_array($value) && empty($value)) || (!is_array($value) && empty($value))) {
                delete_post_meta($post_id, $meta_key);
                return;
            }

            update_post_meta($post_id, $meta_key, $value);
            return;
        }

        if (empty($_FILES[$meta_key]) || empty($_FILES[$meta_key]['tmp_name'])) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_id = media_handle_upload($meta_key, $post_id);
        if (!is_wp_error($attach_id) && $attach_id) {
            update_post_meta($post_id, $meta_key, (int) $attach_id);
        }
    }

    private static function register_type($field)
    {
        $type = isset($field['type']) ? (string) $field['type'] : 'text';
        if ($type === 'number') {
            return 'number';
        }
        if ($type === 'checkbox') {
            return 'boolean';
        }
        if (in_array((string) ($field['id'] ?? ''), ['_store_options', '_store_advanced_options'], true)) {
            return 'array';
        }
        if ($type === 'file' && !empty($field['multiple'])) {
            return 'array';
        }
        if ($type === 'file') {
            return 'integer';
        }

        return 'string';
    }

    private static function field_matches_context($field, $context)
    {
        $contexts = isset($field['contexts']) && is_array($field['contexts']) ? $field['contexts'] : ['frontend', 'admin'];
        return in_array($context, $contexts, true);
    }

    private static function normalize_attachment_ids($raw)
    {
        if (is_string($raw)) {
            $raw = array_map('trim', explode(',', $raw));
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $raw)));
    }

    private static function filter_attachment_ids_for_current_user($ids)
    {
        $ids = is_array($ids) ? $ids : [];
        return array_values(array_filter($ids, [self::class, 'attachment_allowed_for_current_user']));
    }

    private static function attachment_allowed_for_current_user($attachment_id)
    {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        return (int) get_post_field('post_author', $attachment_id) === get_current_user_id();
    }

    private static function build_media_preview_items($value, $multiple = false)
    {
        $ids = $multiple ? self::normalize_attachment_ids($value) : [is_numeric($value) ? (int) $value : 0];
        $items = [];

        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }

            $thumb = wp_get_attachment_image_url($id, 'medium');
            $full = wp_get_attachment_url($id);
            if (!$thumb && !$full) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'thumb' => $thumb ? $thumb : $full,
                'full' => $full ? $full : $thumb,
                'title' => get_the_title($id),
            ];
        }

        return $items;
    }

    private static function render_media_preview_html($items, $multiple = false)
    {
        if (empty($items)) {
            return '<div class="vmp-media-field__empty text-muted small">Belum ada gambar dipilih.</div>';
        }

        ob_start();
        ?>
        <div class="vmp-media-field__grid<?php echo $multiple ? '' : ' vmp-media-field__grid--single'; ?>">
            <?php foreach ((array) $items as $item) : ?>
                <div class="vmp-media-field__item" data-id="<?php echo esc_attr((string) $item['id']); ?>">
                    <img src="<?php echo esc_url((string) $item['thumb']); ?>" alt="<?php echo esc_attr((string) $item['title']); ?>" class="vmp-media-field__image">
                    <button type="button" class="btn-close vmp-media-field__remove" aria-label="Hapus gambar"></button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}
