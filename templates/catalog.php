<?php
$pp = isset($per_page) ? (int) $per_page : 12;
if ($pp <= 0) {
    $pp = 12;
}
?>
<div class="container py-4 vmp-wrap" x-data="vmpCatalog(<?php echo esc_attr($pp); ?>)" x-init="init()">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0">Katalog Produk</h2>
            <small class="text-muted">Marketplace berbasis REST API</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <input type="search" class="form-control form-control-sm vmp-search" placeholder="Cari produk..." x-model="search" @keydown.enter.prevent="fetchProducts(1)">
            <select class="form-select form-select-sm vmp-sort" x-model="sort" @change="fetchProducts(1)">
                <option value="latest">Terbaru</option>
                <option value="price_asc">Harga Terendah</option>
                <option value="price_desc">Harga Tertinggi</option>
                <option value="popular">Terpopuler</option>
            </select>
            <button class="btn btn-sm btn-primary" type="button" @click="fetchProducts(1)">Filter</button>
        </div>
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
                        <div class="small text-muted mb-3" x-text="stockText(item.stock)"></div>
                        <div class="mt-auto d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-dark flex-grow-1" @click="addToCart(item)">Tambah Keranjang</button>
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="isWishlisted(item.id) ? 'btn-danger' : 'btn-outline-secondary'"
                                @click="toggleWishlist(item)"
                                title="Wishlist"
                            >❤</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <div class="py-5 text-center" x-show="loading">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="small text-muted mt-2">Memuat produk...</div>
    </div>

    <div class="py-5 text-center border rounded bg-light" x-show="!loading && items.length === 0">
        <div class="h5 mb-1">Produk belum tersedia</div>
        <div class="text-muted">Coba ubah kata kunci atau filter. Pastikan produk sudah berstatus publish.</div>
    </div>

    <div class="d-flex justify-content-center align-items-center gap-2 mt-4" x-show="totalPages > 1">
        <button class="btn btn-outline-secondary btn-sm" type="button" :disabled="currentPage <= 1" @click="fetchProducts(currentPage - 1)">Prev</button>
        <span class="small text-muted">Halaman <span x-text="currentPage"></span> / <span x-text="totalPages"></span></span>
        <button class="btn btn-outline-secondary btn-sm" type="button" :disabled="currentPage >= totalPages" @click="fetchProducts(currentPage + 1)">Next</button>
    </div>
</div>
