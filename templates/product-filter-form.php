<?php
use VelocityMarketplace\Modules\Product\ProductQuery;

$filters = isset($filters) && is_array($filters) ? $filters : (new ProductQuery())->normalize_filters($_GET);
$categories = isset($categories) && is_array($categories) ? $categories : get_terms([
    'taxonomy' => 'vmp_product_cat',
    'hide_empty' => false,
]);
$product_query = new ProductQuery();
$sort_options = isset($sort_options) && is_array($sort_options) ? $sort_options : $product_query->sort_options();
$action_url = isset($action_url) && is_string($action_url) && $action_url !== '' ? $action_url : get_post_type_archive_link('vmp_product');
$form_class = isset($form_class) ? (string) $form_class : '';
?>
<form
    method="get"
    action="<?php echo esc_url($action_url); ?>"
    class="<?php echo esc_attr(trim('vmp-archive-filter-form ' . $form_class)); ?>"
    x-data='vmpArchiveFilter(<?php echo wp_json_encode([
        'storeProvinceId' => (string) ($filters['store_province_id'] ?? ''),
        'storeCityId' => (string) ($filters['store_city_id'] ?? ''),
        'storeSubdistrictId' => (string) ($filters['store_subdistrict_id'] ?? ''),
    ]); ?>)'
    x-init="init()"
>
    <div class="vmp-archive-filter-group">
        <label class="form-label small mb-1"><?php echo esc_html__('Nama Produk', 'velocity-marketplace'); ?></label>
        <input type="search" name="search" class="form-control form-control-sm" placeholder="<?php echo esc_attr__('Cari nama produk', 'velocity-marketplace'); ?>" value="<?php echo esc_attr((string) ($filters['search'] ?? '')); ?>">
    </div>

    <div class="vmp-archive-filter-group">
        <label class="form-label small mb-1"><?php echo esc_html__('Kategori', 'velocity-marketplace'); ?></label>
        <select name="product_cat" class="form-select form-select-sm">
            <option value=""><?php echo esc_html__('Semua Kategori', 'velocity-marketplace'); ?></option>
            <?php foreach ((array) $categories as $category) : ?>
                <?php if (!is_object($category) || empty($category->term_id)) { continue; } ?>
                <option value="<?php echo esc_attr((string) $category->term_id); ?>" <?php selected((int) ($filters['cat'] ?? 0), (int) $category->term_id); ?>><?php echo esc_html((string) $category->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="vmp-archive-filter-group">
        <label class="form-label small mb-1"><?php echo esc_html__('Jenis Toko', 'velocity-marketplace'); ?></label>
        <select name="store_type" class="form-select form-select-sm">
            <option value=""><?php echo esc_html__('Semua Toko', 'velocity-marketplace'); ?></option>
            <option value="star_seller" <?php selected((string) ($filters['store_type'] ?? ''), 'star_seller'); ?>><?php echo esc_html__('Star Seller', 'velocity-marketplace'); ?></option>
            <option value="regular" <?php selected((string) ($filters['store_type'] ?? ''), 'regular'); ?>><?php echo esc_html__('Toko Biasa', 'velocity-marketplace'); ?></option>
        </select>
    </div>

    <div class="vmp-archive-filter-group">
        <label class="form-label small mb-1"><?php echo esc_html__('Provinsi Toko', 'velocity-marketplace'); ?></label>
        <select name="store_province_id" class="form-select form-select-sm" x-model="storeProvinceId" x-effect="$el.value = storeProvinceId || ''" @change="onStoreProvinceChange()" :disabled="isLoadingProvinces">
            <option value=""><?php echo esc_html__('Semua Provinsi', 'velocity-marketplace'); ?></option>
            <template x-for="prov in provinces" :key="'filter-prov-' + prov.province_id">
                <option :value="prov.province_id" :selected="(storeProvinceId || '') === String(prov.province_id || '')" x-text="prov.province"></option>
            </template>
        </select>
    </div>

    <div class="vmp-archive-filter-group">
        <label class="form-label small mb-1"><?php echo esc_html__('Kota/Kabupaten Toko', 'velocity-marketplace'); ?></label>
        <select name="store_city_id" class="form-select form-select-sm" x-model="storeCityId" x-effect="$el.value = storeCityId || ''" @change="onStoreCityChange()" :disabled="!storeProvinceId || isLoadingCities">
            <option value=""><?php echo esc_html__('Semua Kota/Kabupaten', 'velocity-marketplace'); ?></option>
            <template x-for="city in cities" :key="'filter-city-' + city.city_id">
                <option :value="city.city_id" :selected="(storeCityId || '') === String(city.city_id || '')" x-text="(city.type ? city.type + ' ' : '') + city.city_name"></option>
            </template>
        </select>
    </div>

    <div class="vmp-archive-filter-group">
        <label class="form-label small mb-1"><?php echo esc_html__('Kecamatan Toko', 'velocity-marketplace'); ?></label>
        <select name="store_subdistrict_id" class="form-select form-select-sm" x-model="storeSubdistrictId" x-effect="$el.value = storeSubdistrictId || ''" @change="onStoreSubdistrictChange()" :disabled="!storeCityId || isLoadingSubdistricts">
            <option value=""><?php echo esc_html__('Semua Kecamatan', 'velocity-marketplace'); ?></option>
            <template x-for="subdistrict in subdistricts" :key="'filter-subdistrict-' + subdistrict.subdistrict_id">
                <option :value="subdistrict.subdistrict_id" :selected="(storeSubdistrictId || '') === String(subdistrict.subdistrict_id || '')" x-text="subdistrict.subdistrict_name"></option>
            </template>
        </select>
    </div>

    <div class="vmp-archive-filter-group">
        <label class="form-label small mb-1"><?php echo esc_html__('Harga Minimum', 'velocity-marketplace'); ?></label>
        <input type="number" name="min_price" min="0" step="1000" class="form-control form-control-sm" value="<?php echo esc_attr($filters['min_price'] !== '' ? (string) $filters['min_price'] : ''); ?>">
    </div>

    <div class="vmp-archive-filter-group">
        <label class="form-label small mb-1"><?php echo esc_html__('Harga Maksimum', 'velocity-marketplace'); ?></label>
        <input type="number" name="max_price" min="0" step="1000" class="form-control form-control-sm" value="<?php echo esc_attr($filters['max_price'] !== '' ? (string) $filters['max_price'] : ''); ?>">
    </div>

    <div class="vmp-archive-filter-group">
        <label class="form-label small mb-1"><?php echo esc_html__('Urutkan', 'velocity-marketplace'); ?></label>
        <select name="sort" class="form-select form-select-sm">
            <?php foreach ($sort_options as $sort_key => $sort_label) : ?>
                <option value="<?php echo esc_attr((string) $sort_key); ?>" <?php selected((string) ($filters['sort'] ?? 'latest'), (string) $sort_key); ?>><?php echo esc_html((string) $sort_label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="d-grid gap-2 pt-2">
        <button type="submit" class="btn btn-sm btn-dark"><?php echo esc_html__('Terapkan Filter', 'velocity-marketplace'); ?></button>
        <a href="<?php echo esc_url($action_url); ?>" class="btn btn-sm btn-outline-secondary"><?php echo esc_html__('Atur Ulang', 'velocity-marketplace'); ?></a>
    </div>
    <div class="small text-muted pt-2" x-show="locationError" x-text="locationError"></div>
</form>
