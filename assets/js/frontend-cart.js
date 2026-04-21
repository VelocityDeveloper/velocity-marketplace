/* Komponen keranjang untuk load item, ubah qty, hapus, dan kosongkan cart. */
(() => {
  const shared = () => window.VMPFrontend || null;
  const requireShared = () => {
    const current = shared();
    if (!current) {
      throw new Error('Frontend helper belum siap.');
    }

    return current;
  };
  const bootstrapApi = () => window.bootstrap || window.justg || null;
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

  const syncCartShortcutBadges = (count) => {
    document.querySelectorAll('[data-vmp-cart-trigger]').forEach((trigger) => {
      const badge = trigger.querySelector('.vmp-cart-shortcut__badge');
      const nextCount = Math.max(0, Number(count || 0));

      if (badge) {
        badge.textContent = String(nextCount);
        badge.style.display = nextCount > 0 ? '' : 'none';
      }

      trigger.setAttribute(
        'aria-label',
        nextCount > 0 ? `Keranjang (${nextCount})` : 'Keranjang',
      );
    });
  };

  // Menyediakan state Alpine untuk halaman keranjang dan drawer keranjang.
  const vmpCart = (config = {}) => {
    const current = shared();
    const cfg = current && current.cfg ? current.cfg : {};
    const cartHelpers = current && current.cartHelpers ? current.cartHelpers : defaultCartHelpers;

    return {
    ...cartHelpers,
    drawer: !!config.drawer,
    open: false,
    offcanvas: null,
    panel: null,
    fallbackBackdrop: null,
    loading: false,
    items: [],
    total: 0,
    count: 0,
    message: '',
    cartUrl: cfg.cartUrl || '/keranjang/',
    checkoutUrl: cfg.checkoutUrl || '/checkout/',
    catalogUrl: cfg.catalogUrl || '/katalog/',
    // Memuat isi keranjang awal dan mendengar update global cart.
    async init() {
      await this.fetchCart();
      window.addEventListener('vmp:cart-updated', () => {
        this.fetchCart();
      });
      this.initDrawer();

      if (this.drawer) {
        window.VMPCartDrawer = this;
        window.addEventListener('vmp:open-cart-drawer', () => {
          this.openDrawer();
        });
      }
    },
    // Menghubungkan drawer keranjang dengan komponen Offcanvas Bootstrap 5.
    initDrawer() {
      if (!this.drawer) return;

      const panel = this.$root ? this.$root.querySelector('.vmp-cart-drawer') : null;
      if (!panel) return;

      this.panel = panel;

      const bootstrap = bootstrapApi();
      if (bootstrap && bootstrap.Offcanvas) {
        this.offcanvas = bootstrap.Offcanvas.getOrCreateInstance(panel);
        panel.addEventListener('shown.bs.offcanvas', () => {
          this.open = true;
        });
        panel.addEventListener('hidden.bs.offcanvas', () => {
          this.open = false;
        });
      }
    },
    // Menyediakan backdrop sederhana jika Bootstrap JS tidak tersedia.
    ensureFallbackBackdrop() {
      if (this.fallbackBackdrop) return this.fallbackBackdrop;

      const backdrop = document.createElement('div');
      backdrop.className = 'offcanvas-backdrop fade show';
      backdrop.addEventListener('click', () => {
        this.closeDrawer();
      });
      this.fallbackBackdrop = backdrop;
      return backdrop;
    },
    // Membuka panel drawer dan mengunci scroll body bila mode drawer aktif.
    openDrawer() {
      if (!this.drawer || !this.panel) return;

      if (this.offcanvas) {
        this.offcanvas.show();
        return;
      }

      this.panel.style.visibility = 'visible';
      this.panel.classList.add('show');
      this.panel.setAttribute('aria-modal', 'true');
      this.panel.removeAttribute('aria-hidden');
      this.open = true;

      const backdrop = this.ensureFallbackBackdrop();
      if (!document.body.contains(backdrop)) {
        document.body.appendChild(backdrop);
      }
      document.body.classList.add('offcanvas-backdrop-open');
    },
    // Menutup panel drawer dan mengembalikan scroll body normal.
    closeDrawer() {
      if (!this.drawer || !this.panel) return;

      if (this.offcanvas) {
        this.offcanvas.hide();
        return;
      }

      this.panel.classList.remove('show');
      this.panel.setAttribute('aria-hidden', 'true');
      this.panel.style.visibility = 'hidden';
      this.open = false;
      document.body.classList.remove('offcanvas-backdrop-open');
      if (this.fallbackBackdrop && this.fallbackBackdrop.parentNode) {
        this.fallbackBackdrop.parentNode.removeChild(this.fallbackBackdrop);
      }
    },
    // Mengambil isi keranjang terbaru dari REST API.
    async fetchCart() {
      this.loading = true;
      this.message = '';
      try {
        const { request } = requireShared();
        const data = await request('cart', { method: 'GET' });
        this.items = Array.isArray(data.items) ? data.items : [];
        this.total = Number(data.total || 0);
        this.count = Number(data.count || 0);
        syncCartShortcutBadges(this.count);
      } catch (e) {
        this.items = [];
        this.total = 0;
        this.count = 0;
        this.message = e.message || 'Keranjang tidak dapat dimuat.';
        syncCartShortcutBadges(0);
      } finally {
        this.loading = false;
      }
    },
    // Menghitung subtotal semua item dari satu seller untuk tampilan group header.
    sellerSubtotal(sellerId) {
      return this.items.reduce((sum, item) => {
        return Number(item?.seller_id || 0) === Number(sellerId || 0)
          ? sum + Number(item?.subtotal || 0)
          : sum;
      }, 0);
    },
    minQty(item) {
      return Math.max(1, Number((item && (item.min_order || item.minOrder)) || 1));
    },
    canDecrease(item) {
      return Number((item && item.qty) || 0) > this.minQty(item);
    },
    // Memperbarui jumlah item tertentu di keranjang.
    async changeQty(item, qty) {
      if (qty < 0) return;
      const minimumQty = this.minQty(item);
      if (qty > 0 && qty < minimumQty) {
        qty = minimumQty;
      }
      try {
        const { request, emitCartUpdated } = requireShared();
        const data = await request('cart', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            id: item.id,
            qty,
            cart_key: item.cart_key || '',
            options: item.options || {},
          }),
        });
        emitCartUpdated(data);
      } catch (e) {
        this.message = e.message || 'Jumlah produk tidak dapat diperbarui.';
      }
    },
    // Menghapus item dari keranjang dengan qty nol.
    async remove(item) {
      await this.changeQty(item, 0);
    },
    // Mengosongkan seluruh isi keranjang user.
    async clearCart() {
      try {
        const { request, emitCartUpdated } = requireShared();
        const data = await request('cart', { method: 'DELETE' });
        emitCartUpdated(data);
      } catch (e) {
        this.message = e.message || 'Keranjang tidak dapat dikosongkan.';
      }
    },
  };
  };

  window.vmpCart = vmpCart;
  const registerAlpineCart = () => {
    if (!window.Alpine || typeof window.Alpine.data !== 'function') {
      return false;
    }

    Alpine.data('vmpCart', vmpCart);
    return true;
  };

  if (!registerAlpineCart()) {
    document.addEventListener('alpine:init', registerAlpineCart, { once: true });
  }

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-vmp-cart-trigger]');
    if (!trigger) {
      return;
    }

    if (window.VMPCartDrawer && typeof window.VMPCartDrawer.openDrawer === 'function') {
      event.preventDefault();
      window.dispatchEvent(new CustomEvent('vmp:open-cart-drawer'));
    }
  });
})();

