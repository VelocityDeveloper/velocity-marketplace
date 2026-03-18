(() => {
  const cfg = window.vmpSettings || {};
  const currencyCode =
    cfg.currency && String(cfg.currency).toUpperCase() === "USD" ? "USD" : "IDR";
  const currencySymbol =
    typeof cfg.currencySymbol === "string" && cfg.currencySymbol.trim() !== ""
      ? cfg.currencySymbol.trim()
      : currencyCode === "USD"
        ? "$"
        : "Rp";
  const paymentMethods = Array.isArray(cfg.paymentMethods)
    ? cfg.paymentMethods
    : ["bank"];
  const placeholder =
    "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0nNDAwJyBoZWlnaHQ9JzMwMCcgdmlld0JveD0nMCAwIDQwMCAzMDAnIHhtbG5zPSdodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2Zyc+PHJlY3Qgd2lkdGg9JzQwMCcgaGVpZ2h0PSczMDAnIGZpbGw9JyNlZWVmZjEnLz48dGV4dCB4PSc1MCUnIHk9JzUwJScgZG9taW5hbnQtYmFzZWxpbmU9J21pZGRsZScgdGV4dC1hbmNob3I9J21pZGRsZScgZmlsbD0nIzYwNzA4MCcgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnIGZvbnQtc2l6ZT0nMTYnPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg==";

  const api = (path) => `${cfg.restUrl || ""}${path || ""}`;

  const request = async (path, options = {}) => {
    const opt = Object.assign(
      {
        credentials: "same-origin",
        headers: {},
      },
      options,
    );
    if (cfg.nonce) {
      opt.headers["X-WP-Nonce"] = cfg.nonce;
    }
    const res = await fetch(api(path), opt);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(data.message || "Request gagal");
    }
    return data;
  };

  const flashButtonLabel = (button, nextLabel) => {
    if (!button) return;
    const original =
      button.dataset.defaultLabel || button.textContent.trim() || "Proses";
    button.dataset.defaultLabel = original;
    button.textContent = nextLabel;
    window.setTimeout(() => {
      button.textContent = button.dataset.defaultLabel || original;
    }, 1400);
  };

  const money = (value) => {
    const num = Number(value || 0);
    try {
      return new Intl.NumberFormat(currencyCode === "USD" ? "en-US" : "id-ID", {
        style: "currency",
        currency: currencyCode,
        minimumFractionDigits: 0,
      }).format(num);
    } catch (e) {
      return `${currencySymbol} ${num.toLocaleString("id-ID")}`;
    }
  };

  const optionKey = (item) => {
    try {
      return JSON.stringify(item && item.options ? item.options : {});
    } catch (e) {
      return "";
    }
  };

  const gatherCaptcha = (formNode) => {
    const out = {};
    if (!formNode) return out;

    const token = formNode.querySelector("input[name='vd_captcha_token']");
    const input = formNode.querySelector("input[name='vd_captcha_input']");
    const grecaptchaInput = formNode.querySelector(
      "textarea[name='g-recaptcha-response'], input[name='g-recaptcha-response']",
    );

    if (token && token.value) out.vd_captcha_token = token.value;
    if (input && input.value) out.vd_captcha_input = input.value;
    if (grecaptchaInput && grecaptchaInput.value) {
      out["g-recaptcha-response"] = grecaptchaInput.value;
      out.g_recaptcha_response = grecaptchaInput.value;
    }
    return out;
  };

  const defaultPaymentMethod =
    paymentMethods.length > 0 && typeof paymentMethods[0] === "string"
      ? paymentMethods[0]
      : "bank";

  const cartHelpers = {
    placeholder,
    optionKey,
    optionText(options) {
      if (!options || typeof options !== "object") return "";
      const lines = [];
      if (options.basic) lines.push(`Basic: ${options.basic}`);
      if (options.advanced) lines.push(`Advanced: ${options.advanced}`);
      return lines.join(" | ");
    },
    formatPrice(value) {
      return money(value);
    },
  };

  const vmpCatalog = (perPage = 12) => ({
    loading: false,
    items: [],
    wishlistIds: [],
    currentPage: 1,
    totalPages: 1,
    total: 0,
    search: "",
    sort: "latest",
    message: "",
    placeholder,
    perPage,
    async init() {
      if (cfg.isLoggedIn) {
        await this.fetchWishlist();
      }
      await this.fetchProducts(1);
    },
    async fetchWishlist() {
      try {
        const data = await request("wishlist", { method: "GET" });
        this.wishlistIds = Array.isArray(data.items)
          ? data.items.map((id) => Number(id))
          : [];
      } catch (e) {
        this.wishlistIds = [];
      }
    },
    async fetchProducts(nextPage = 1) {
      this.loading = true;
      this.message = "";
      try {
        const url = new URL(api("products"));
        url.searchParams.set("page", String(nextPage));
        url.searchParams.set("per_page", String(this.perPage));
        if (this.search) url.searchParams.set("search", this.search);
        if (this.sort) url.searchParams.set("sort", this.sort);

        const res = await fetch(url.toString(), { credentials: "same-origin" });
        const data = await res.json();
        if (!res.ok) {
          throw new Error(data.message || "Gagal memuat katalog");
        }

        this.items = Array.isArray(data.items) ? data.items : [];
        this.currentPage = Number(data.page || nextPage || 1);
        this.totalPages = Number(data.pages || 1);
        this.total = Number(data.total || this.items.length);
      } catch (e) {
        this.items = [];
        this.currentPage = 1;
        this.totalPages = 1;
        this.message = e.message || "Terjadi kesalahan saat memuat produk.";
      } finally {
        this.loading = false;
      }
    },
    formatPrice(value) {
      return money(value);
    },
    stockText(stock) {
      if (stock === null || stock === undefined || stock === "") {
        return "Stok tidak dibatasi";
      }
      const n = Number(stock || 0);
      return n > 0 ? `Stok: ${n}` : "Stok habis";
    },
    async addToCart(item) {
      const options = {};
      if (Array.isArray(item.basic_options) && item.basic_options.length > 0) {
        options.basic = item.basic_options[0];
      }
      if (
        Array.isArray(item.advanced_options) &&
        item.advanced_options.length > 0 &&
        item.advanced_options[0].label
      ) {
        options.advanced = item.advanced_options[0].label;
      }

      try {
        await request("cart", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            id: item.id,
            qty: 1,
            options,
          }),
        });
        this.message = "Produk ditambahkan ke keranjang.";
        window.dispatchEvent(new CustomEvent("vmp:cart-updated"));
      } catch (e) {
        this.message = e.message || "Gagal menambahkan ke keranjang.";
      }
    },
    isWishlisted(productId) {
      return this.wishlistIds.includes(Number(productId));
    },
    async toggleWishlist(item) {
      if (!cfg.isLoggedIn) {
        this.message = "Login dulu untuk pakai wishlist.";
        return;
      }

      try {
        const data = await request("wishlist", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ product_id: item.id }),
        });
        this.wishlistIds = Array.isArray(data.items)
          ? data.items.map((id) => Number(id))
          : this.wishlistIds;
        this.message = data.active
          ? "Produk ditambahkan ke wishlist."
          : "Produk dihapus dari wishlist.";
      } catch (e) {
        this.message = e.message || "Gagal update wishlist.";
      }
    },
  });

  const vmpCart = () => ({
    ...cartHelpers,
    loading: false,
    items: [],
    total: 0,
    count: 0,
    message: "",
    cartUrl: cfg.cartUrl || "/keranjang/",
    checkoutUrl: cfg.checkoutUrl || "/checkout/",
    catalogUrl: cfg.catalogUrl || "/katalog/",
    async init() {
      await this.fetchCart();
      window.addEventListener("vmp:cart-updated", () => {
        this.fetchCart();
      });
    },
    async fetchCart() {
      this.loading = true;
      this.message = "";
      try {
        const data = await request("cart", { method: "GET" });
        this.items = Array.isArray(data.items) ? data.items : [];
        this.total = Number(data.total || 0);
        this.count = Number(data.count || 0);
      } catch (e) {
        this.items = [];
        this.total = 0;
        this.count = 0;
        this.message = e.message || "Gagal mengambil keranjang.";
      } finally {
        this.loading = false;
      }
    },
    async changeQty(item, qty) {
      if (qty < 0) return;
      try {
        await request("cart", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            id: item.id,
            qty,
            options: item.options || {},
          }),
        });
        await this.fetchCart();
      } catch (e) {
        this.message = e.message || "Gagal memperbarui qty.";
      }
    },
    async remove(item) {
      await this.changeQty(item, 0);
    },
    async clearCart() {
      try {
        await request("cart", { method: "DELETE" });
        await this.fetchCart();
      } catch (e) {
        this.message = e.message || "Gagal mengosongkan keranjang.";
      }
    },
  });

  const vmpCheckout = () => ({
    ...cartHelpers,
    loading: false,
    submitting: false,
    items: [],
    total: 0,
    errorMessage: "",
    successMessage: "",
    cartUrl: cfg.cartUrl || "/keranjang/",
    form: {
      name: "",
      email: "",
      phone: "",
      address: "",
      postal_code: "",
      notes: "",
      payment_method: defaultPaymentMethod,
      shipping_courier: "",
      shipping_service: "",
      shipping_cost: 0,
      subdistrict_destination: "",
    },
    async init() {
      await this.fetchCart();
    },
    async fetchCart() {
      this.loading = true;
      try {
        const data = await request("cart", { method: "GET" });
        this.items = Array.isArray(data.items) ? data.items : [];
        this.total = Number(data.total || 0);
      } catch (e) {
        this.items = [];
        this.total = 0;
        this.errorMessage = e.message || "Gagal memuat keranjang.";
      } finally {
        this.loading = false;
      }
    },
    async submitOrder() {
      this.errorMessage = "";
      this.successMessage = "";

      if (!this.form.name || !this.form.phone || !this.form.address) {
        this.errorMessage = "Nama, telepon, dan alamat wajib diisi.";
        return;
      }
      if (!Array.isArray(this.items) || this.items.length === 0) {
        this.errorMessage = "Keranjang kosong.";
        return;
      }

      this.submitting = true;
      try {
        const formNode = document.getElementById("vmp-checkout-form");
        const captchaFields = gatherCaptcha(formNode);
        const payload = Object.assign({}, this.form, captchaFields);

        const data = await request("checkout", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });

        this.successMessage = data.message || "Pesanan berhasil dibuat.";
        this.items = [];
        this.total = 0;
        window.dispatchEvent(new CustomEvent("vmp:cart-updated"));

        if (data.redirect) {
          setTimeout(() => {
            window.location.href = data.redirect;
          }, 1200);
        }
      } catch (e) {
        this.errorMessage = e.message || "Gagal membuat pesanan.";
      } finally {
        this.submitting = false;
      }
    },
  });

  const renderMediaPreview = (preview, items, multiple, emptyText) => {
    if (!preview) return;

    if (!Array.isArray(items) || items.length === 0) {
      preview.innerHTML = `<div class="vmp-media-field__empty text-muted small">${emptyText}</div>`;
      return;
    }

    const gridClass = multiple
      ? "vmp-media-field__grid"
      : "vmp-media-field__grid vmp-media-field__grid--single";

    preview.innerHTML = `
      <div class="${gridClass}">
        ${items
          .map(
            (item) => `
              <div class="vmp-media-field__item" data-id="${Number(item.id || 0)}">
                <img src="${String(item.url || "")}" alt="${String(item.title || "")}" class="vmp-media-field__image">
                <button type="button" class="btn-close vmp-media-field__remove" aria-label="Hapus gambar"></button>
              </div>
            `,
          )
          .join("")}
      </div>
    `;
  };

  const initMediaFields = () => {
    if (!window.wp || !wp.media) return;

    document.querySelectorAll(".vmp-media-field").forEach((field) => {
      const input = field.querySelector(".vmp-media-field__input");
      const preview = field.querySelector(".vmp-media-field__preview");
      const openBtn = field.querySelector(".vmp-media-field__open");
      const clearBtn = field.querySelector(".vmp-media-field__clear");
      const multiple = field.dataset.multiple === "1";
      const emptyText =
        preview && preview.dataset.placeholder
          ? preview.dataset.placeholder
          : "Belum ada gambar dipilih.";

      if (!input || !preview || !openBtn || !clearBtn) return;

      const syncButtons = () => {
        clearBtn.disabled = String(input.value || "").trim() === "";
      };

      openBtn.addEventListener("click", (event) => {
        event.preventDefault();

        const frame = wp.media({
          title: openBtn.dataset.title || "Pilih Media",
          button: {
            text: openBtn.dataset.button || "Gunakan file ini",
          },
          multiple: multiple ? "add" : false,
          library: {
            type: "image",
          },
        });

        frame.on("select", () => {
          const selection = frame.state().get("selection");
          const items = [];
          const ids = [];

          selection.each((attachment) => {
            const data = attachment.toJSON();
            const imageUrl =
              (data.sizes &&
                (data.sizes.medium?.url ||
                  data.sizes.thumbnail?.url ||
                  data.sizes.full?.url)) ||
              data.url ||
              "";

            if (!data.id || !imageUrl) return;

            ids.push(Number(data.id));
            items.push({
              id: Number(data.id),
              url: imageUrl,
              title: data.title || "",
            });
          });

          input.value = multiple ? ids.join(",") : String(ids[0] || "");
          renderMediaPreview(preview, multiple ? items : items.slice(0, 1), multiple, emptyText);
          syncButtons();
        });

        frame.open();
      });

      clearBtn.addEventListener("click", (event) => {
        event.preventDefault();
        input.value = "";
        renderMediaPreview(preview, [], multiple, emptyText);
        syncButtons();
      });

      preview.addEventListener("click", (event) => {
        const removeButton = event.target.closest(".vmp-media-field__remove");
        if (!removeButton) return;

        event.preventDefault();

        const item = removeButton.closest(".vmp-media-field__item");
        if (!item) return;

        const itemId = Number(item.dataset.id || 0);
        if (multiple) {
          const ids = String(input.value || "")
            .split(",")
            .map((value) => Number(value.trim()))
            .filter((value) => value > 0 && value !== itemId);
          input.value = ids.join(",");
        } else {
          input.value = "";
        }

        item.remove();
        if (!preview.querySelector(".vmp-media-field__item")) {
          renderMediaPreview(preview, [], multiple, emptyText);
        }
        syncButtons();
      });

      syncButtons();
    });
  };

  const initActionButtons = () => {
    document.addEventListener("click", async (event) => {
      const cartButton = event.target.closest(".vmp-action-add-to-cart");
      if (cartButton) {
        event.preventDefault();

        const productId = Number(cartButton.dataset.productId || 0);
        const basic = String(cartButton.dataset.basic || "").trim();
        const advanced = String(cartButton.dataset.advanced || "").trim();
        const options = {};
        if (basic) options.basic = basic;
        if (advanced) options.advanced = advanced;

        if (productId <= 0) return;

        cartButton.disabled = true;
        try {
          await request("cart", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              id: productId,
              qty: 1,
              options,
            }),
          });
          flashButtonLabel(cartButton, "Ditambahkan");
          window.dispatchEvent(new CustomEvent("vmp:cart-updated"));
        } catch (e) {
          flashButtonLabel(cartButton, "Gagal");
        } finally {
          window.setTimeout(() => {
            cartButton.disabled = false;
          }, 500);
        }
        return;
      }

      const wishlistButton = event.target.closest(".vmp-action-toggle-wishlist");
      if (wishlistButton) {
        event.preventDefault();

        if (!cfg.isLoggedIn) {
          flashButtonLabel(wishlistButton, "Login dulu");
          return;
        }

        const productId = Number(wishlistButton.dataset.productId || 0);
        if (productId <= 0) return;

        wishlistButton.disabled = true;
        try {
          const data = await request("wishlist", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ product_id: productId }),
          });
          const active = !!data.active;
          wishlistButton.classList.toggle("btn-danger", active);
          wishlistButton.classList.toggle("btn-outline-secondary", !active);
          wishlistButton.setAttribute("aria-pressed", active ? "true" : "false");
          flashButtonLabel(wishlistButton, active ? "Tersimpan" : "Dihapus");
        } catch (e) {
          flashButtonLabel(wishlistButton, "Gagal");
        } finally {
          window.setTimeout(() => {
            wishlistButton.disabled = false;
          }, 500);
        }
      }
    });
  };

  window.vmpCatalog = vmpCatalog;
  window.vmpCart = vmpCart;
  window.vmpCheckout = vmpCheckout;

  document.addEventListener("alpine:init", () => {
    Alpine.data("vmpCatalog", vmpCatalog);
    Alpine.data("vmpCart", vmpCart);
    Alpine.data("vmpCheckout", vmpCheckout);
  });

  document.addEventListener("DOMContentLoaded", () => {
    initMediaFields();
    initActionButtons();
  });
})();
