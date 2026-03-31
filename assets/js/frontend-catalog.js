/* Komponen katalog produk dan aksi wishlist/cart di halaman listing. */
(() => {
  const shared = window.VMPFrontend;
  if (!shared) {
    return;
  }

  const {
    cfg,
    api,
    request,
    money,
    placeholder,
    wishlistIconSvg,
    fetchShippingList,
    mapProvince,
    mapCity,
    mapSubdistrict,
  } = shared;

  // Menyediakan state filter lokasi toko bertingkat untuk form katalog dan archive.
  const createLocationState = (initial = {}) => ({
    provinces: [],
    cities: [],
    subdistricts: [],
    locationError: '',
    isLoadingProvinces: false,
    isLoadingCities: false,
    isLoadingSubdistricts: false,
    storeProvinceId: String(initial.storeProvinceId || ''),
    storeCityId: String(initial.storeCityId || ''),
    storeSubdistrictId: String(initial.storeSubdistrictId || ''),
    async loadProvinces() {
      if (this.provinces.length > 0 || this.isLoadingProvinces) {
        return;
      }

      this.isLoadingProvinces = true;
      this.locationError = '';
      try {
        const rows = await fetchShippingList('shipping/provinces');
        this.provinces = rows.map(mapProvince);
      } catch (e) {
        this.locationError = e.message || 'Daftar provinsi tidak dapat dimuat.';
      } finally {
        this.isLoadingProvinces = false;
      }
    },
    async loadCities(provinceId) {
      if (!provinceId) {
        this.cities = [];
        return;
      }

      this.isLoadingCities = true;
      this.locationError = '';
      try {
        const rows = await fetchShippingList(`shipping/cities?province=${encodeURIComponent(provinceId)}`);
        this.cities = rows.map(mapCity);
      } catch (e) {
        this.cities = [];
        this.locationError = e.message || 'Daftar kota tidak dapat dimuat.';
      } finally {
        this.isLoadingCities = false;
      }
    },
    async loadSubdistricts(cityId) {
      if (!cityId) {
        this.subdistricts = [];
        return;
      }

      this.isLoadingSubdistricts = true;
      this.locationError = '';
      try {
        const rows = await fetchShippingList(`shipping/subdistricts?city=${encodeURIComponent(cityId)}`);
        this.subdistricts = rows.map(mapSubdistrict);
      } catch (e) {
        this.subdistricts = [];
        this.locationError = e.message || 'Daftar kecamatan tidak dapat dimuat.';
      } finally {
        this.isLoadingSubdistricts = false;
      }
    },
    async initLocation() {
      await this.loadProvinces();

      if (this.storeProvinceId) {
        await this.loadCities(this.storeProvinceId);
      }

      if (this.storeCityId) {
        await this.loadSubdistricts(this.storeCityId);
      }
    },
    async onStoreProvinceChange() {
      this.storeCityId = '';
      this.storeSubdistrictId = '';
      this.cities = [];
      this.subdistricts = [];
      await this.loadCities(this.storeProvinceId);
      if (typeof this.onLocationFilterChange === 'function') {
        this.onLocationFilterChange();
      }
    },
    async onStoreCityChange() {
      this.storeSubdistrictId = '';
      this.subdistricts = [];
      await this.loadSubdistricts(this.storeCityId);
      if (typeof this.onLocationFilterChange === 'function') {
        this.onLocationFilterChange();
      }
    },
    onStoreSubdistrictChange() {
      if (typeof this.onLocationFilterChange === 'function') {
        this.onLocationFilterChange();
      }
    },
    resetLocationFilters() {
      this.storeProvinceId = '';
      this.storeCityId = '';
      this.storeSubdistrictId = '';
      this.cities = [];
      this.subdistricts = [];
      this.locationError = '';
    },
  });

  // Menyediakan state Alpine untuk filter, pagination, dan aksi katalog.
  const vmpCatalog = (perPage = 12) => ({
    ...createLocationState(),
    loading: false,
    items: [],
    wishlistIds: [],
    currentPage: 1,
    totalPages: 1,
    total: 0,
    search: '',
    sort: 'latest',
    cat: '',
    label: '',
    storeType: '',
    minPrice: '',
    maxPrice: '',
    message: '',
    placeholder,
    perPage,
    // Memuat wishlist user dan halaman katalog pertama saat komponen aktif.
    async init() {
      await this.initLocation();
      if (cfg.isLoggedIn) {
        await this.fetchWishlist();
      }
      await this.fetchProducts(1);
    },
    // Mengambil daftar favorit agar tombol wishlist sinkron dengan akun login.
    async fetchWishlist() {
      try {
        const data = await request('wishlist', { method: 'GET' });
        this.wishlistIds = Array.isArray(data.items)
          ? data.items.map((id) => Number(id))
          : [];
      } catch (e) {
        this.wishlistIds = [];
      }
    },
    // Mengambil daftar produk berdasarkan filter, sort, dan halaman aktif.
    async fetchProducts(nextPage = 1) {
      this.loading = true;
      this.message = '';
      try {
        const url = new URL(api('products'));
        url.searchParams.set('page', String(nextPage));
        url.searchParams.set('per_page', String(this.perPage));
        if (this.search) url.searchParams.set('search', this.search);
        if (this.sort) url.searchParams.set('sort', this.sort);
        if (this.cat) url.searchParams.set('cat', this.cat);
        if (this.label) url.searchParams.set('label', this.label);
        if (this.storeType) url.searchParams.set('store_type', this.storeType);
        if (this.storeProvinceId) url.searchParams.set('store_province_id', this.storeProvinceId);
        if (this.storeCityId) url.searchParams.set('store_city_id', this.storeCityId);
        if (this.storeSubdistrictId) url.searchParams.set('store_subdistrict_id', this.storeSubdistrictId);
        if (this.minPrice !== '' && this.minPrice !== null) {
          url.searchParams.set('min_price', String(this.minPrice));
        }
        if (this.maxPrice !== '' && this.maxPrice !== null) {
          url.searchParams.set('max_price', String(this.maxPrice));
        }

        const res = await fetch(url.toString(), { credentials: 'same-origin' });
        const data = await res.json();
        if (!res.ok) {
          throw new Error(data.message || 'Katalog produk tidak dapat dimuat.');
        }

        this.items = Array.isArray(data.items) ? data.items : [];
        this.currentPage = Number(data.page || nextPage || 1);
        this.totalPages = Number(data.pages || 1);
        this.total = Number(data.total || this.items.length);
      } catch (e) {
        this.items = [];
        this.currentPage = 1;
        this.totalPages = 1;
        this.message = e.message || 'Terjadi kendala saat memuat produk.';
      } finally {
        this.loading = false;
      }
    },
    // Memformat harga produk untuk tampilan kartu katalog.
    formatPrice(value) {
      return money(value);
    },
    // Mengubah nilai stok menjadi label yang mudah dibaca user.
    stockText(stock) {
      if (stock === null || stock === undefined || stock === '') {
        return 'Stok tidak dibatasi';
      }
      const n = Number(stock || 0);
      return n > 0 ? `Stok: ${n}` : 'Stok habis';
    },
    // Menyusun ringkasan rating produk dari nilai rata-rata dan jumlah ulasan.
    ratingText(item) {
      const avg = Number(item?.rating_average || 0);
      const count = Number(item?.review_count || 0);
      if (count <= 0) {
        return 'Belum ada ulasan';
      }
      return `${avg.toFixed(1)} / 5 dari ${count} ulasan`;
    },
    // Mengembalikan semua filter katalog ke kondisi awal.
    resetFilters() {
      this.search = '';
      this.sort = 'latest';
      this.cat = '';
      this.label = '';
      this.storeType = '';
      this.minPrice = '';
      this.maxPrice = '';
      this.resetLocationFilters();
      this.fetchProducts(1);
    },
    // Menambahkan produk dari katalog ke keranjang dengan opsi default teraman.
    async addToCart(item) {
      const options = {};
      if (Array.isArray(item.variant_options) && item.variant_options.length > 0) {
        options.variant = item.variant_options[0];
      }
      if (
        Array.isArray(item.price_adjustment_options) &&
        item.price_adjustment_options.length > 0 &&
        item.price_adjustment_options[0].label
      ) {
        options.price_adjustment = item.price_adjustment_options[0].label;
      }

      try {
        await request('cart', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            id: item.id,
            qty: 1,
            options,
          }),
        });
        this.message = 'Produk berhasil ditambahkan ke keranjang.';
        window.dispatchEvent(new CustomEvent('vmp:cart-updated'));
      } catch (e) {
        this.message = e.message || 'Produk tidak dapat ditambahkan ke keranjang.';
      }
    },
    // Mengecek apakah produk sudah ada di daftar favorit user.
    isWishlisted(productId) {
      return this.wishlistIds.includes(Number(productId));
    },
    // Menghasilkan ikon hati outline/fill untuk tombol wishlist katalog.
    wishlistIcon(active) {
      return wishlistIconSvg(!!active);
    },
    // Menambah atau menghapus produk dari daftar favorit user login.
    async toggleWishlist(item) {
      if (!cfg.isLoggedIn) {
        this.message = 'Masuk terlebih dahulu untuk menggunakan daftar favorit.';
        return;
      }

      try {
        const data = await request('wishlist', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ product_id: item.id }),
        });
        this.wishlistIds = Array.isArray(data.items)
          ? data.items.map((id) => Number(id))
          : this.wishlistIds;
        this.message = data.active
          ? 'Produk berhasil disimpan ke daftar favorit.'
          : 'Produk dihapus dari daftar favorit.';
      } catch (e) {
        this.message = e.message || 'Daftar favorit tidak dapat diperbarui.';
      }
    },
  });

  // Menyediakan state Alpine untuk form filter lokasi pada archive native.
  const vmpArchiveFilter = (initial = {}) => ({
    ...createLocationState(initial),
    async init() {
      await this.initLocation();
    },
  });

  window.vmpCatalog = vmpCatalog;
  window.vmpArchiveFilter = vmpArchiveFilter;
  document.addEventListener('alpine:init', () => {
    Alpine.data('vmpCatalog', vmpCatalog);
    Alpine.data('vmpArchiveFilter', vmpArchiveFilter);
  });
})();

