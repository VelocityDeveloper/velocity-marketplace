<?php
use VelocityMarketplace\Modules\Product\ProductQuery;

$pp = isset($per_page) ? (int) $per_page : 12;
if ($pp <= 0) {
    $pp = 12;
}

$categories = get_terms([
    'taxonomy' => 'vmp_product_cat',
    'hide_empty' => false,
]);
$product_query = new ProductQuery();
$sort_options = $product_query->sort_options();
?>
<div class="container py-4 vmp-wrap" x-data="vmpCatalog(<?php echo esc_attr($pp); ?>)" x-init="init()">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0"><?php echo esc_html__('Katalog Produk', 'velocity-marketplace'); ?></h2>
            <small class="text-muted"><?php echo esc_html__('Cari produk berdasarkan nama, kategori, label, toko, lokasi, dan rentang harga.', 'velocity-marketplace'); ?></small>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small mb-1"><?php echo esc_html__('Nama Produk', 'velocity-marketplace'); ?></label>
                    <input type="search" class="form-control form-control-sm" placeholder="<?php echo esc_attr__('Cari nama produk', 'velocity-marketplace'); ?>" x-model="search" @keydown.enter.prevent="fetchProducts(1)">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1"><?php echo esc_html__('Kategori', 'velocity-marketplace'); ?></label>
                    <select class="form-select form-select-sm" x-model="cat" @change="fetchProducts(1)">
                        <option value=""><?php echo esc_html__('Semua Kategori', 'velocity-marketplace'); ?></option>
                        <?php foreach ((array) $categories as $category) : ?>
                            <?php if (!is_object($category) || empty($category->term_id)) { continue; } ?>
                            <option value="<?php echo esc_attr((string) $category->term_id); ?>"><?php echo esc_html((string) $category->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1"><?php echo esc_html__('Jenis Toko', 'velocity-marketplace'); ?></label>
                    <select class="form-select form-select-sm" x-model="storeType" @change="fetchProducts(1)">
                        <option value=""><?php echo esc_html__('Semua Toko', 'velocity-marketplace'); ?></option>
                        <option value="star_seller"><?php echo esc_html__('Star Seller', 'velocity-marketplace'); ?></option>
                        <option value="regular"><?php echo esc_html__('Toko Biasa', 'velocity-marketplace'); ?></option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1"><?php echo esc_html__('Provinsi Toko', 'velocity-marketplace'); ?></label>
                    <select class="form-select form-select-sm" x-model="storeProvinceId" x-effect="$el.value = storeProvinceId || ''" @change="onStoreProvinceChange()" :disabled="isLoadingProvinces">
                        <option value=""><?php echo esc_html__('Semua Provinsi', 'velocity-marketplace'); ?></option>
                        <template x-for="prov in provinces" :key="'catalog-prov-' + prov.province_id">
                            <option :value="prov.province_id" :selected="(storeProvinceId || '') === String(prov.province_id || '')" x-text="prov.province"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1"><?php echo esc_html__('Kota/Kabupaten Toko', 'velocity-marketplace'); ?></label>
                    <select class="form-select form-select-sm" x-model="storeCityId" x-effect="$el.value = storeCityId || ''" @change="onStoreCityChange()" :disabled="!storeProvinceId || isLoadingCities">
                        <option value=""><?php echo esc_html__('Semua Kota/Kabupaten', 'velocity-marketplace'); ?></option>
                        <template x-for="city in cities" :key="'catalog-city-' + city.city_id">
                            <option :value="city.city_id" :selected="(storeCityId || '') === String(city.city_id || '')" x-text="(city.type ? city.type + ' ' : '') + city.city_name"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1"><?php echo esc_html__('Kecamatan Toko', 'velocity-marketplace'); ?></label>
                    <select class="form-select form-select-sm" x-model="storeSubdistrictId" x-effect="$el.value = storeSubdistrictId || ''" @change="onStoreSubdistrictChange()" :disabled="!storeCityId || isLoadingSubdistricts">
                        <option value=""><?php echo esc_html__('Semua Kecamatan', 'velocity-marketplace'); ?></option>
                        <template x-for="subdistrict in subdistricts" :key="'catalog-subdistrict-' + subdistrict.subdistrict_id">
                            <option :value="subdistrict.subdistrict_id" :selected="(storeSubdistrictId || '') === String(subdistrict.subdistrict_id || '')" x-text="subdistrict.subdistrict_name"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1"><?php echo esc_html__('Harga Minimum', 'velocity-marketplace'); ?></label>
                    <input type="number" min="0" step="1000" class="form-control form-control-sm" x-model="minPrice" @keydown.enter.prevent="fetchProducts(1)">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1"><?php echo esc_html__('Harga Maksimum', 'velocity-marketplace'); ?></label>
                    <input type="number" min="0" step="1000" class="form-control form-control-sm" x-model="maxPrice" @keydown.enter.prevent="fetchProducts(1)">
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1"><?php echo esc_html__('Urutkan', 'velocity-marketplace'); ?></label>
                    <select class="form-select form-select-sm" x-model="sort" @change="fetchProducts(1)">
                        <?php foreach ($sort_options as $sort_key => $sort_label) : ?>
                            <option value="<?php echo esc_attr((string) $sort_key); ?>"><?php echo esc_html((string) $sort_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button class="btn btn-sm btn-primary" type="button" @click="fetchProducts(1)"><?php echo esc_html__('Terapkan Filter', 'velocity-marketplace'); ?></button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="resetFilters()"><?php echo esc_html__('Atur Ulang', 'velocity-marketplace'); ?></button>
                </div>
                <div class="col-12" x-show="locationError">
                    <div class="small text-muted" x-text="locationError"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3" x-show="activeFilterChips().length > 0">
        <template x-for="chip in activeFilterChips()" :key="chip.key">
            <span class="badge rounded-pill text-bg-light border" x-text="chip.label"></span>
        </template>
        <button class="btn btn-sm btn-link text-decoration-none px-0" type="button" @click="resetFilters()"><?php echo esc_html__('Reset Filter', 'velocity-marketplace'); ?></button>
    </div>

    <div class="alert alert-info py-2" x-show="message" x-text="message"></div>
    <div class="row g-3" x-show="!loading && items.length > 0">
        <template x-for="item in items" :key="item.id">
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card h-100 shadow-sm border-0">
                    <a :href="item.link" class="vmp-thumb-wrap">
                        <img :src="item.image || placeholder" class="card-img-top vmp-thumb" :alt="item.title">
                    </a>
                    <div class="card-body d-flex flex-column">
                        <h3 class="card-title h6 mb-1" x-text="item.title"></h3>
                        <div class="small text-muted mb-2" x-show="item.label" x-text="item.label"></div>
                        <div class="fw-semibold text-danger mb-1" x-text="formatPrice(item.price)"></div>
                        <div class="small text-muted mb-1" x-show="item.seller_city" x-text="item.seller_city"></div>
                        <div class="small text-muted mb-1" x-show="Number(item.sold_count || 0) > 0" x-text="(item.sold_count || 0) + ' <?php echo esc_js(__('terjual', 'velocity-marketplace')); ?>'"></div>
                        <div class="small text-muted mb-1" x-text="stockText(item.stock)"></div>
                        <template x-if="item.rating_html">
                            <div class="mb-3" x-html="item.rating_html"></div>
                        </template>
                        <template x-if="!item.rating_html">
                            <div class="small text-muted mb-3"><?php echo esc_html__('Belum ada ulasan', 'velocity-marketplace'); ?></div>
                        </template>
                        <div class="mt-auto d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-dark flex-grow-1" @click="addToCart(item)"><?php echo esc_html__('Tambah Keranjang', 'velocity-marketplace'); ?></button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary vmp-wishlist-button"
                                :class="{ 'is-active': isWishlisted(item.id) }"
                                @click="toggleWishlist(item)"
                                title="<?php echo esc_attr__('Wishlist', 'velocity-marketplace'); ?>"
                                :aria-pressed="isWishlisted(item.id) ? 'true' : 'false'"
                                aria-label="<?php echo esc_attr__('Wishlist', 'velocity-marketplace'); ?>"
                                x-html="wishlistIcon(isWishlisted(item.id))"
                            ></button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <div class="py-5 text-center" x-show="loading">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="small text-muted mt-2"><?php echo esc_html__('Memuat produk...', 'velocity-marketplace'); ?></div>
    </div>

    <div class="py-5 text-center border rounded bg-light" x-show="!loading && items.length === 0">
        <div class="h5 mb-1"><?php echo esc_html__('Produk tidak ditemukan', 'velocity-marketplace'); ?></div>
        <div class="text-muted"><?php echo esc_html__('Ubah kata kunci atau filter untuk melihat hasil lainnya.', 'velocity-marketplace'); ?></div>
    </div>

    <div class="d-flex justify-content-center align-items-center gap-2 mt-4" x-show="totalPages > 1">
        <button class="btn btn-outline-secondary btn-sm" type="button" :disabled="currentPage <= 1" @click="fetchProducts(currentPage - 1)"><?php echo esc_html__('Sebelumnya', 'velocity-marketplace'); ?></button>
        <span class="small text-muted"><?php echo esc_html__('Halaman', 'velocity-marketplace'); ?> <span x-text="currentPage"></span> / <span x-text="totalPages"></span></span>
        <button class="btn btn-outline-secondary btn-sm" type="button" :disabled="currentPage >= totalPages" @click="fetchProducts(currentPage + 1)"><?php echo esc_html__('Berikutnya', 'velocity-marketplace'); ?></button>
    </div>
</div>
