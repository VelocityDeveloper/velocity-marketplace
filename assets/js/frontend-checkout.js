/* Komponen checkout untuk alamat tujuan, ongkir, kupon, dan submit pesanan. */
(() => {
  const shared = () => window.VMPFrontend || null;
  const requireShared = () => {
    const current = shared();
    if (!current) {
      throw new Error('Frontend helper belum siap.');
    }

    return current;
  };
  const defaultCartHelpers = {
    placeholder: '',
    optionKey(item) {
      try {
        return JSON.stringify(item && item.options ? item.options : {});
      } catch (e) {
        return '';
      }
    },
    optionText(options) {
      if (!options || typeof options !== 'object') return '';
      const lines = [];
      Object.entries(options).forEach(([key, value]) => {
        if (value === null || value === undefined) return;

        const label = String(key || '').trim();
        const text = String(value || '').trim();
        if (!label || !text) return;

        lines.push(`${label}: ${text}`);
      });
      return lines.join(' | ');
    },
    formatPrice(value) {
      const num = Number(value || 0);
      return `Rp ${num.toLocaleString('id-ID')}`;
    },
  };

  // Menyediakan state Alpine utama untuk alur checkout marketplace.
  const vmpCheckout = () => {
    const current = shared();
    const cfg = current && current.cfg ? current.cfg : {};
    const cartHelpers = current && current.cartHelpers ? current.cartHelpers : defaultCartHelpers;
    const defaultPaymentMethod =
      current && typeof current.defaultPaymentMethod === 'string'
        ? current.defaultPaymentMethod
        : 'bank';

    return {
    ...cartHelpers,
    loading: false,
    isLoadingProvinces: false,
    isLoadingCities: false,
    isLoadingSubdistricts: false,
    submitting: false,
    items: [],
    subtotal: 0,
    total: 0,
    provinces: [],
    cities: [],
    subdistricts: [],
    shippingGroups: [],
    coupon: {
      code: '',
      applied: null,
      loading: false,
      message: '',
    },
    shippingContextMessage: '',
    errorMessage: '',
    successMessage: '',
    cartUrl: cfg.cartUrl || '/keranjang/',
    form: {
      name: '',
      email: '',
      phone: '',
      address: '',
      postal_code: '',
      notes: '',
      payment_method: defaultPaymentMethod,
      destination_province_id: '',
      destination_province_name: '',
      destination_city_id: '',
      destination_city_name: '',
      destination_subdistrict_id: '',
      destination_subdistrict_name: '',
      shipping_cost: 0,
    },
    // Menyiapkan profil default, cart, daftar wilayah, dan context pengiriman awal.
    async init() {
      this.applyCustomerProfileDefaults();
      await this.fetchCart();
      await this.loadCheckoutContext();
      if (this.requiresShipping()) {
        await this.loadProvinces();
        this.syncProvinceSelection();
        if (this.form.destination_province_id) {
          await this.loadCities(this.form.destination_province_id);
          this.syncCitySelection();
        }
        if (this.form.destination_city_id) {
          await this.loadSubdistricts(this.form.destination_city_id);
          this.syncSubdistrictSelection();
        }
      }
      if (this.requiresShipping() && this.form.destination_subdistrict_id) {
        await Promise.all(
          this.shippingGroups.map((group) => this.loadShippingOptions(group)),
        );
      }
    },
    // Mengecek apakah metode pembayaran aktif saat ini adalah COD.
    isCodPayment() {
      return String(this.form.payment_method || '') === 'cod';
    },
    // Mengembalikan true jika keranjang masih punya item fisik yang butuh ongkir.
    requiresShipping() {
      return Array.isArray(this.shippingGroups) && this.shippingGroups.length > 0;
    },
    // Mengisi form checkout dari profil member jika field masih kosong.
    applyCustomerProfileDefaults() {
      const current = shared();
      const customerProfile =
        current && current.customerProfile && typeof current.customerProfile === 'object'
          ? current.customerProfile
          : {};
      const pick = (key, fallback = '') =>
        typeof customerProfile[key] === 'string' ? customerProfile[key] : fallback;

      if (!this.form.name) this.form.name = pick('name');
      if (!this.form.email) this.form.email = pick('email');
      if (!this.form.phone) this.form.phone = pick('phone');
      if (!this.form.address) this.form.address = pick('address');
      if (!this.form.postal_code) this.form.postal_code = pick('postal_code');
      if (!this.form.destination_province_id) {
        this.form.destination_province_id = pick('destination_province_id');
      }
      if (!this.form.destination_province_name) {
        this.form.destination_province_name = pick('destination_province_name');
      }
      if (!this.form.destination_city_id) {
        this.form.destination_city_id = pick('destination_city_id');
      }
      if (!this.form.destination_city_name) {
        this.form.destination_city_name = pick('destination_city_name');
      }
      if (!this.form.destination_subdistrict_id) {
        this.form.destination_subdistrict_id = pick('destination_subdistrict_id');
      }
      if (!this.form.destination_subdistrict_name) {
        this.form.destination_subdistrict_name = pick('destination_subdistrict_name');
      }
    },
    // Memaksa nilai select sinkron setelah option di-render ulang.
    applySelectValue(refName, value) {
      this.$nextTick(() => {
        const field = this.$refs && this.$refs[refName] ? this.$refs[refName] : null;
        if (!field) return;
        field.value = String(value || '');
      });
    },
    // Menyinkronkan provinsi tersimpan dengan daftar provinsi hasil API.
    syncProvinceSelection() {
      if (!Array.isArray(this.provinces) || this.provinces.length === 0) return;

      const selected = this.provinces.find(
        (row) => row.province_id === String(this.form.destination_province_id || ''),
      );

      this.form.destination_province_id = selected ? selected.province_id : '';
      this.form.destination_province_name = selected ? selected.province : '';
      this.applySelectValue('provinceSelect', this.form.destination_province_id);

      if (!selected) {
        this.form.destination_city_id = '';
        this.form.destination_city_name = '';
        this.form.destination_subdistrict_id = '';
        this.form.destination_subdistrict_name = '';
        this.applySelectValue('citySelect', '');
        this.applySelectValue('subdistrictSelect', '');
      }
    },
    // Menyinkronkan kota tersimpan dan ikut mengisi kode pos bila tersedia.
    syncCitySelection() {
      if (!Array.isArray(this.cities) || this.cities.length === 0) return;

      const selected = this.cities.find(
        (row) => row.city_id === String(this.form.destination_city_id || ''),
      );

      this.form.destination_city_id = selected ? selected.city_id : '';
      this.form.destination_city_name = selected
        ? `${selected.type ? `${selected.type} ` : ''}${selected.city_name}`
        : '';
      if (!this.form.postal_code) {
        this.form.postal_code = selected ? selected.postal_code || '' : '';
      }
      this.applySelectValue('citySelect', this.form.destination_city_id);

      if (!selected) {
        this.form.destination_subdistrict_id = '';
        this.form.destination_subdistrict_name = '';
        this.applySelectValue('subdistrictSelect', '');
      }
    },
    // Menyinkronkan kecamatan tersimpan dengan daftar kecamatan hasil API.
    syncSubdistrictSelection() {
      if (!Array.isArray(this.subdistricts) || this.subdistricts.length === 0) return;

      const selected = this.subdistricts.find(
        (row) => row.subdistrict_id === String(this.form.destination_subdistrict_id || ''),
      );

      this.form.destination_subdistrict_id = selected ? selected.subdistrict_id : '';
      this.form.destination_subdistrict_name = selected ? selected.subdistrict_name : '';
      this.applySelectValue('subdistrictSelect', this.form.destination_subdistrict_id);
    },
    // Memuat item checkout dari cart dan menghitung subtotal awal.
    async fetchCart() {
      this.loading = true;
      try {
        const { request } = requireShared();
        const data = await request('cart', { method: 'GET' });
        this.items = Array.isArray(data.items) ? data.items : [];
        this.subtotal = Number(data.total || 0);
        this.recalculateTotal();
      } catch (e) {
        this.items = [];
        this.subtotal = 0;
        this.total = 0;
        this.errorMessage = e.message || 'Keranjang tidak dapat dimuat.';
      } finally {
        this.loading = false;
      }
    },
    // Mengambil daftar provinsi untuk dropdown tujuan pengiriman.
    async loadProvinces() {
      if (this.provinces.length > 0) return;

      this.isLoadingProvinces = true;
      try {
        const { fetchShippingList, mapProvince } = requireShared();
        const rows = await fetchShippingList('shipping/provinces');
        this.provinces = rows.map(mapProvince);
        this.syncProvinceSelection();
      } catch (e) {
        this.shippingContextMessage = e.message || 'Gagal memuat provinsi.';
      } finally {
        this.isLoadingProvinces = false;
      }
    },
    // Mengambil daftar kota/kabupaten berdasarkan provinsi terpilih.
    async loadCities(provinceId) {
      if (!provinceId) {
        this.cities = [];
        return;
      }

      this.isLoadingCities = true;
      try {
        const { fetchShippingList, mapCity } = requireShared();
        const rows = await fetchShippingList(
          `shipping/cities?province=${encodeURIComponent(provinceId)}`,
        );
        this.cities = rows.map(mapCity);
        this.syncCitySelection();
      } catch (e) {
        this.cities = [];
        this.shippingContextMessage = e.message || 'Gagal memuat kota.';
      } finally {
        this.isLoadingCities = false;
      }
    },
    // Mengambil daftar kecamatan berdasarkan kota/kabupaten terpilih.
    async loadSubdistricts(cityId) {
      if (!cityId) {
        this.subdistricts = [];
        return;
      }

      this.isLoadingSubdistricts = true;
      try {
        const { fetchShippingList, mapSubdistrict } = requireShared();
        const rows = await fetchShippingList(
          `shipping/subdistricts?city=${encodeURIComponent(cityId)}`,
        );
        this.subdistricts = rows.map(mapSubdistrict);
        this.syncSubdistrictSelection();
      } catch (e) {
        this.subdistricts = [];
        this.shippingContextMessage = e.message || 'Gagal memuat kecamatan.';
      } finally {
        this.isLoadingSubdistricts = false;
      }
    },
    // Mengambil context pengiriman per toko dari isi keranjang saat ini.
    async loadCheckoutContext() {
      try {
        const { request } = requireShared();
        const data = await request('shipping/checkout-context', { method: 'GET' });
        this.shippingGroups = Array.isArray(data.data?.groups)
          ? data.data.groups.map((group) => ({
              ...group,
              services: [],
              selectedKey: '',
              selected: null,
              loading: false,
              message: '',
              cod_enabled: !!group.cod_enabled,
              cod_city_ids: Array.isArray(group.cod_city_ids)
                ? group.cod_city_ids.map((id) => String(id || ''))
                : [],
              cod_city_names: Array.isArray(group.cod_city_names)
                ? group.cod_city_names
                : [],
            }))
          : [];
        if (!this.requiresShipping()) {
          this.form.shipping_cost = 0;
        }
        this.shippingContextMessage = '';
      } catch (e) {
        this.shippingGroups = [];
        this.shippingContextMessage = e.message || 'Data pengiriman belum siap.';
      }
    },
    // Mereset turunan lokasi dan ongkir saat provinsi tujuan berubah.
    async onProvinceChange() {
      const selected = this.provinces.find(
        (row) => row.province_id === String(this.form.destination_province_id || ''),
      );

      this.form.destination_province_name = selected ? selected.province : '';
      this.form.destination_city_id = '';
      this.form.destination_city_name = '';
      this.form.destination_subdistrict_id = '';
      this.form.destination_subdistrict_name = '';
      this.form.shipping_cost = 0;
      this.form.postal_code = '';
      this.subdistricts = [];
      this.resetSellerShippingSelections();
      if (!this.requiresShipping()) {
        return;
      }
      await this.loadCities(this.form.destination_province_id);
      this.recalculateTotal();
    },
    // Mereset kecamatan dan ongkir saat kota/kabupaten tujuan berubah.
    async onCityChange() {
      const selected = this.cities.find(
        (row) => row.city_id === String(this.form.destination_city_id || ''),
      );

      this.form.destination_city_name = selected
        ? `${selected.type ? `${selected.type} ` : ''}${selected.city_name}`
        : '';
      this.form.postal_code = selected ? selected.postal_code || '' : '';
      this.form.destination_subdistrict_id = '';
      this.form.destination_subdistrict_name = '';
      this.form.shipping_cost = 0;
      this.resetSellerShippingSelections();
      if (!this.requiresShipping()) {
        return;
      }
      await this.loadSubdistricts(this.form.destination_city_id);
      this.recalculateTotal();
    },
    // Menghitung ulang opsi pengiriman saat kecamatan tujuan berubah.
    async onSubdistrictChange() {
      const selected = this.subdistricts.find(
        (row) => row.subdistrict_id === String(this.form.destination_subdistrict_id || ''),
      );

      this.form.destination_subdistrict_name = selected ? selected.subdistrict_name : '';
      this.form.shipping_cost = 0;
      this.resetSellerShippingSelections();
      this.recalculateTotal();

      if (this.requiresShipping() && this.form.destination_subdistrict_id) {
        await Promise.all(
          this.shippingGroups.map((group) => this.loadShippingOptions(group)),
        );
      }
    },
    // Memuat ulang opsi pengiriman saat metode pembayaran mempengaruhi ongkir, seperti COD.
    async onPaymentMethodChange() {
      this.form.shipping_cost = 0;
      this.resetSellerShippingSelections();
      this.recalculateTotal();
      if (this.requiresShipping() && this.form.destination_subdistrict_id) {
        await Promise.all(
          this.shippingGroups.map((group) => this.loadShippingOptions(group)),
        );
      }
    },
    // Mengosongkan pilihan layanan kirim semua toko sebelum dihitung ulang.
    resetSellerShippingSelections() {
      this.shippingGroups = this.shippingGroups.map((group) => ({
        ...group,
        services: [],
        selectedKey: '',
        selected: null,
        loading: false,
        message: '',
      }));
    },
    // Mengambil daftar layanan kirim untuk satu toko pada tujuan aktif.
    async loadShippingOptions(group) {
      if (!this.form.destination_subdistrict_id || !group?.seller_id) return;

      group.loading = true;
      group.message = '';
      try {
        const { request } = requireShared();
        if (this.isCodPayment()) {
          const canCod =
            !!group.cod_enabled &&
            group.cod_city_ids.includes(String(this.form.destination_city_id || ''));

          if (!canCod) {
            group.services = [];
            group.selectedKey = '';
            group.selected = null;
            group.message = 'Layanan COD tidak tersedia untuk kota tujuan ini.';
            return;
          }

          const codOption = {
            code: 'cod',
            name: 'COD',
            service: 'COD',
            description: 'Bayar di tempat / temu langsung',
            cost: 0,
            etd: 'Sesuai kesepakatan',
          };
          group.services = [codOption];
          group.message = '';
          this.selectShipping(group, codOption);
          return;
        }

        const data = await request('shipping/calculate', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            seller_id: group.seller_id,
            destination_subdistrict: this.form.destination_subdistrict_id,
          }),
        });

        group.services = Array.isArray(data.data?.services)
          ? data.data.services
          : [];
        if (group.services.length === 0) {
          group.message = 'Belum ada layanan pengiriman yang tersedia.';
        }
      } catch (e) {
        group.services = [];
        group.message = e.message || 'Ongkir tidak dapat dihitung.';
      } finally {
        group.loading = false;
      }
    },
    // Menyimpan layanan kirim terpilih untuk satu toko dan hitung total baru.
    selectShipping(group, opt) {
      group.selectedKey = `${opt.code}:${opt.service}`;
      group.selected = {
        seller_id: Number(group.seller_id || 0),
        courier: String(opt.code || ''),
        courier_name: String(opt.name || ''),
        service: String(opt.service || ''),
        description: String(opt.description || ''),
        cost: Number(opt.cost || 0),
        etd: String(opt.etd || ''),
      };
      this.recalculateTotal();
      this.refreshCouponAfterShippingChange();
    },
    // Menghitung ulang total checkout dari subtotal, ongkir, dan diskon aktif.
    recalculateTotal() {
      const shippingTotal = this.shippingGroups.reduce((sum, group) => {
        return sum + Number(group?.selected?.cost || 0);
      }, 0);
      const couponDiscount = Number(this.coupon?.applied?.discount || 0);
      this.form.shipping_cost = shippingTotal;
      this.total = Math.max(
        0,
        Number(this.subtotal || 0) + shippingTotal - couponDiscount,
      );
    },
    // Menentukan label diskon berdasarkan scope kupon yang aktif.
    couponLabel() {
      return this.coupon?.applied?.scope === 'shipping'
        ? 'Diskon Ongkir'
        : 'Diskon Kupon';
    },
    // Memvalidasi dan menerapkan kupon terhadap subtotal atau ongkir.
    async applyCoupon(silent = false) {
      if (!silent) {
        this.coupon.message = '';
      }

      if (!this.coupon.code) {
        this.coupon.applied = null;
        if (!silent) {
          this.coupon.message = 'Masukkan kode kupon terlebih dahulu.';
        }
        this.recalculateTotal();
        return;
      }

      this.coupon.loading = true;
      try {
        const { request } = requireShared();
        const data = await request('coupon/preview', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            code: this.coupon.code,
            shipping_total: Number(this.form.shipping_cost || 0),
          }),
        });

        this.coupon.applied = data.data || null;
        this.coupon.code = String(data.data?.code || this.coupon.code);
        if (
          (data.data?.scope || '') === 'shipping' &&
          (this.shippingGroups.length === 0 || this.shippingGroups.some((group) => !group.selected))
        ) {
          this.coupon.applied = null;
          this.coupon.message = this.shippingGroups.length === 0
            ? 'Voucher ongkir tidak bisa dipakai karena keranjang tidak memerlukan pengiriman.'
            : 'Voucher ongkir baru dapat diterapkan setelah layanan pengiriman dipilih untuk setiap toko.';
          this.recalculateTotal();
          return;
        }
        if (!silent) {
          this.coupon.message = this.coupon.applied
            ? 'Kupon berhasil diterapkan.'
            : '';
        }
      } catch (e) {
        this.coupon.applied = null;
        this.coupon.message = e.message || 'Kupon tidak dapat digunakan.';
      } finally {
        this.coupon.loading = false;
        this.recalculateTotal();
      }
    },
    // Memuat ulang kupon ongkir saat pilihan layanan kirim berubah.
    async refreshCouponAfterShippingChange() {
      if (!this.coupon?.applied || this.coupon.applied.scope !== 'shipping') {
        return;
      }

      await this.applyCoupon(true);
    },
    // Menghapus kupon aktif dari state checkout.
    removeCoupon() {
      this.coupon.code = '';
      this.coupon.applied = null;
      this.coupon.message = '';
      this.recalculateTotal();
    },
    // Memvalidasi form dan mengirim pesanan ke endpoint checkout.
    async submitOrder() {
      this.errorMessage = '';
      this.successMessage = '';

      if (!this.form.name || !this.form.phone) {
        this.errorMessage = 'Nama dan telepon wajib diisi.';
        return;
      }
      if (!Array.isArray(this.items) || this.items.length === 0) {
        this.errorMessage = 'Keranjang kosong.';
        return;
      }
      if (this.requiresShipping() && !this.form.address) {
        this.errorMessage = 'Alamat wajib diisi.';
        return;
      }
      if (
        this.requiresShipping() &&
        (!this.form.destination_province_id ||
          !this.form.destination_city_id ||
          !this.form.destination_subdistrict_id)
      ) {
        this.errorMessage = 'Provinsi, kota, dan kecamatan tujuan wajib dipilih.';
        return;
      }
      if (this.requiresShipping() && this.shippingGroups.some((group) => !group.selected)) {
        this.errorMessage = 'Pilih layanan pengiriman untuk setiap toko.';
        return;
      }

      this.submitting = true;
      try {
        const { gatherCaptcha, request, emitCartUpdated } = requireShared();
        const formNode = document.getElementById('vmp-checkout-form');
        const captchaFields = gatherCaptcha(formNode);
        const payload = Object.assign(
          {},
          this.form,
          {
            shipping_groups: this.shippingGroups.map((group) => group.selected),
            coupon_code: this.coupon.applied?.code || this.coupon.code || '',
          },
          captchaFields,
        );

        const data = await request('checkout', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });

        this.successMessage = data.message || 'Pesanan berhasil dibuat.';
        this.items = [];
        this.subtotal = 0;
        this.total = 0;
        this.shippingGroups = [];
        emitCartUpdated({
          items: [],
          total: 0,
          count: 0,
        });

        if (data.redirect) {
          setTimeout(() => {
            window.location.href = data.redirect;
          }, 1200);
        }
      } catch (e) {
        this.errorMessage = e.message || 'Pesanan tidak dapat dibuat.';
      } finally {
        this.submitting = false;
      }
    },
  };
  };

  window.vmpCheckout = vmpCheckout;
  const registerAlpineCheckout = () => {
    if (!window.Alpine || typeof window.Alpine.data !== 'function') {
      return false;
    }

    Alpine.data('vmpCheckout', vmpCheckout);
    return true;
  };

  if (!registerAlpineCheckout()) {
    document.addEventListener('alpine:init', registerAlpineCheckout, { once: true });
  }
})();
