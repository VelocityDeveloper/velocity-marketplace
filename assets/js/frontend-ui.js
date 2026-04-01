/* Interaksi UI kecil yang tidak bergantung pada satu halaman tertentu. */
(() => {
  const shared = window.VMPFrontend;
  if (!shared) {
    return;
  }

  const { cfg, request, flashButtonLabel, money, wishlistIconSvg } = shared;
  const bootstrapApi = window.bootstrap || window.justg || null;

  const copyText = async (text) => {
    const value = String(text || '').trim();
    if (!value) {
      throw new Error('Tidak ada teks untuk disalin.');
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(value);
      return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', 'readonly');
    textarea.style.position = 'absolute';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
  };

  // Mengambil pilihan opsi produk dari blok inline yang berada satu wrapper dengan tombol add-to-cart.
  const collectInlineOptions = (button) => {
    const block = button.closest('.vmp-add-to-cart-block');
    if (!block) {
      return {};
    }

    const options = {};
    const variantSelect = block.querySelector('[data-vmp-inline-option="variant"]');
    const adjustmentSelect = block.querySelector('[data-vmp-inline-option="price_adjustment"]');

    if (variantSelect && String(variantSelect.value || '').trim() !== '') {
      options.variant = String(variantSelect.value || '').trim();
    }

    if (adjustmentSelect && String(adjustmentSelect.value || '').trim() !== '') {
      options.price_adjustment = String(adjustmentSelect.value || '').trim();
    }

    return options;
  };

  // Membuat dan mengelola modal pilihan produk sebelum item dimasukkan ke keranjang.
  const createCartOptionModal = () => {
    let modal = null;
    let modalInstance = null;
    let activeButton = null;
    let fallbackBackdrop = null;
    const escapeHtml = (value) =>
      String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const ensureModal = () => {
      if (modal) return modal;

      modal = document.createElement('div');
      modal.className = 'modal fade';
      modal.tabIndex = -1;
      modal.setAttribute('aria-hidden', 'true');
      modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 shadow">
            <form>
              <div class="modal-header">
                <div>
                  <div class="text-uppercase text-muted small fw-semibold">Pilih Opsi Produk</div>
                  <h5 class="modal-title mt-1" id="vmp-cart-option-title"></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
              </div>
              <div class="modal-body">
                <div class="vmp-cart-option-modal__fields d-grid gap-3"></div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-dark">Tambah Keranjang</button>
              </div>
            </form>
          </div>
        </div>
      `;

      document.body.appendChild(modal);
      if (bootstrapApi && bootstrapApi.Modal) {
        modalInstance = new bootstrapApi.Modal(modal);
      }

      modal.addEventListener('hidden.bs.modal', () => {
        activeButton = null;
      });

      modal.querySelector('form').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!activeButton) return;

        const productId = Number(activeButton.dataset.productId || 0);
        if (productId <= 0) return;
        const submittedButton = activeButton;

        const form = event.currentTarget;
        const variantSelect = form.querySelector('[name="variant"]');
        const adjustmentSelect = form.querySelector('[name="price_adjustment"]');
        const options = {};

        if (variantSelect && String(variantSelect.value || '').trim() !== '') {
          options.variant = String(variantSelect.value || '').trim();
        }

        if (adjustmentSelect && String(adjustmentSelect.value || '').trim() !== '') {
          options.price_adjustment = String(adjustmentSelect.value || '').trim();
        }

        submittedButton.disabled = true;
        try {
          await request('cart', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              id: productId,
              qty: 1,
              options,
            }),
          });
          flashButtonLabel(submittedButton, 'Ditambahkan');
          window.dispatchEvent(new CustomEvent('vmp:cart-updated'));
          closeModal();
        } catch (e) {
          flashButtonLabel(submittedButton, 'Gagal');
        } finally {
          window.setTimeout(() => {
            submittedButton.disabled = false;
          }, 500);
        }
      });

      return modal;
    };

    const ensureFallbackBackdrop = () => {
      if (fallbackBackdrop) return fallbackBackdrop;

      fallbackBackdrop = document.createElement('div');
      fallbackBackdrop.className = 'modal-backdrop fade';
      fallbackBackdrop.addEventListener('click', () => {
        closeModal();
      });
      return fallbackBackdrop;
    };

    const parseOptions = (button) => {
      try {
        return JSON.parse(String(button?.dataset.productOptions || '{}'));
      } catch (e) {
        return {};
      }
    };

    const hasSelectableOptions = (button) => {
      const payload = parseOptions(button);
      return (
        (Array.isArray(payload.variant_options) && payload.variant_options.length > 0) ||
        (Array.isArray(payload.price_adjustment_options) &&
          payload.price_adjustment_options.length > 0)
      );
    };

    const renderAdjustmentOptions = (rows) =>
      rows
        .map((row, index) => {
          const label = String(row?.label || '').trim();
          if (!label) return '';
          const amount = Number(row?.amount || 0);
          const suffix = amount > 0 ? ` (+${money(amount)})` : '';
          return `<option value="${escapeHtml(label)}" ${index === 0 ? 'selected' : ''}>${escapeHtml(label + suffix)}</option>`;
        })
        .join('');

    const renderVariantOptions = (rows) =>
      rows
        .map((label, index) => {
          const text = String(label || '').trim();
          if (!text) return '';
          return `<option value="${escapeHtml(text)}" ${index === 0 ? 'selected' : ''}>${escapeHtml(text)}</option>`;
        })
        .join('');

    const openModal = (button) => {
      const payload = parseOptions(button);
      const node = ensureModal();
      if (!node) return false;
      const title = node.querySelector('#vmp-cart-option-title');
      const fields = node.querySelector('.vmp-cart-option-modal__fields');
      const parts = [];

      if (Array.isArray(payload.variant_options) && payload.variant_options.length > 0) {
        parts.push(`
          <div>
            <label class="form-label">${escapeHtml(String(payload.variant_name || 'Pilihan Varian'))}</label>
            <select class="form-select" name="variant">
              ${renderVariantOptions(payload.variant_options)}
            </select>
          </div>
        `);
      }

      if (
        Array.isArray(payload.price_adjustment_options) &&
        payload.price_adjustment_options.length > 0
      ) {
        parts.push(`
          <div>
            <label class="form-label">${escapeHtml(String(payload.price_adjustment_name || 'Pilihan Harga'))}</label>
            <select class="form-select" name="price_adjustment">
              ${renderAdjustmentOptions(payload.price_adjustment_options)}
            </select>
            <div class="form-text">Pilihan ini akan menambah harga dari harga dasar produk.</div>
          </div>
        `);
      }

      title.textContent = String(payload.title || 'Pilih Opsi Produk');
      fields.innerHTML = parts.join('');
      activeButton = button;

      if (modalInstance) {
        modalInstance.show();
        return true;
      }

      const backdrop = ensureFallbackBackdrop();
      if (!document.body.contains(backdrop)) {
        document.body.appendChild(backdrop);
      }

      node.style.display = 'block';
      node.removeAttribute('aria-hidden');
      node.classList.add('show');
      backdrop.classList.add('show');
      document.body.classList.add('modal-open');
      return true;
    };

    const closeModal = () => {
      if (modalInstance) {
        modalInstance.hide();
        return;
      }

      if (!modal) return;
      modal.classList.remove('show');
      modal.setAttribute('aria-hidden', 'true');
      modal.style.display = 'none';
      activeButton = null;
      document.body.classList.remove('modal-open');
      if (fallbackBackdrop && fallbackBackdrop.parentNode) {
        fallbackBackdrop.parentNode.removeChild(fallbackBackdrop);
      }
    };

    return {
      hasSelectableOptions,
      openModal,
    };
  };

  // Mengikat tombol global tambah ke keranjang dan toggle wishlist.
  const initActionButtons = () => {
    const optionModal = createCartOptionModal();

    document.addEventListener('click', async (event) => {
      const cartButton = event.target.closest('.vmp-action-add-to-cart');
      if (cartButton) {
        event.preventDefault();

        const productId = Number(cartButton.dataset.productId || 0);
        if (productId <= 0) return;
        const optionStyle = String(cartButton.dataset.optionStyle || 'popup').trim();
        const inlineOptions = optionStyle === 'inline' ? collectInlineOptions(cartButton) : {};

        if (optionStyle !== 'inline' && optionModal.hasSelectableOptions(cartButton)) {
          if (optionModal.openModal(cartButton)) {
            return;
          }
        }

        cartButton.disabled = true;
        try {
          await request('cart', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              id: productId,
              qty: 1,
              options: inlineOptions,
            }),
          });
          flashButtonLabel(cartButton, 'Ditambahkan');
          window.dispatchEvent(new CustomEvent('vmp:cart-updated'));
        } catch (e) {
          flashButtonLabel(cartButton, 'Gagal');
        } finally {
          window.setTimeout(() => {
            cartButton.disabled = false;
          }, 500);
        }
        return;
      }

      const wishlistButton = event.target.closest('.vmp-action-toggle-wishlist');
      if (!wishlistButton) {
        const copyButton = event.target.closest('[data-vmp-copy-text]');
        if (!copyButton) {
          return;
        }

        event.preventDefault();
        try {
          await copyText(copyButton.dataset.vmpCopyText || '');
          flashButtonLabel(copyButton, copyButton.dataset.vmpCopySuccess || 'Tersalin');
        } catch (e) {
          flashButtonLabel(copyButton, 'Gagal');
        }
        return;
      }

      event.preventDefault();
      if (!cfg.isLoggedIn) {
        return;
      }

      const productId = Number(wishlistButton.dataset.productId || 0);
      if (productId <= 0) return;

      wishlistButton.disabled = true;
      try {
        const data = await request('wishlist', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ product_id: productId }),
        });
        const active = !!data.active;
        wishlistButton.classList.toggle('is-active', active);
        wishlistButton.setAttribute('aria-pressed', active ? 'true' : 'false');
        wishlistButton.innerHTML = wishlistIconSvg(active);
      } catch (e) {
        wishlistButton.classList.remove('is-active');
        wishlistButton.innerHTML = wishlistIconSvg(false);
      } finally {
        window.setTimeout(() => {
          wishlistButton.disabled = false;
        }, 500);
      }
    });
  };

  // Mengaktifkan galeri produk, carousel thumbnail, dan lightbox image.
  const initProductGallery = () => {
    const galleries = document.querySelectorAll('.vmp-product-gallery');
    if (!galleries.length) return;

    let lightbox = null;
    let lightboxImage = null;
    let lightboxCounter = null;
    let activeGallery = null;
    let activeIndex = 0;

    // Membuat elemen lightbox sekali lalu dipakai ulang oleh semua galeri.
    const ensureLightbox = () => {
      if (lightbox) return;

      const wrapper = document.createElement('div');
      wrapper.className = 'vmp-lightbox';
      wrapper.innerHTML = `
        <div class="vmp-lightbox__dialog" role="dialog" aria-modal="true" aria-label="Galeri Produk">
          <button type="button" class="vmp-lightbox__close" data-lightbox-close aria-label="Tutup">&times;</button>
          <button type="button" class="vmp-lightbox__nav vmp-lightbox__nav--prev" data-lightbox-prev aria-label="Gambar sebelumnya">&#8249;</button>
          <div class="vmp-lightbox__viewport">
            <img src="" alt="" class="vmp-lightbox__image" data-lightbox-image>
          </div>
          <button type="button" class="vmp-lightbox__nav vmp-lightbox__nav--next" data-lightbox-next aria-label="Gambar berikutnya">&#8250;</button>
          <div class="vmp-lightbox__counter" data-lightbox-counter></div>
        </div>
      `;

      document.body.appendChild(wrapper);
      lightbox = wrapper;
      lightboxImage = wrapper.querySelector('[data-lightbox-image]');
      lightboxCounter = wrapper.querySelector('[data-lightbox-counter]');

      wrapper.addEventListener('click', (event) => {
        if (event.target === wrapper || event.target.closest('[data-lightbox-close]')) {
          closeLightbox();
          return;
        }
        if (event.target.closest('[data-lightbox-prev]')) {
          event.preventDefault();
          stepLightbox(-1);
          return;
        }
        if (event.target.closest('[data-lightbox-next]')) {
          event.preventDefault();
          stepLightbox(1);
        }
      });

      document.addEventListener('keydown', (event) => {
        if (!lightbox || !lightbox.classList.contains('is-open')) return;

        if (event.key === 'Escape') {
          closeLightbox();
        } else if (event.key === 'ArrowLeft') {
          stepLightbox(-1);
        } else if (event.key === 'ArrowRight') {
          stepLightbox(1);
        }
      });
    };

    // Mengambil semua gambar dari satu galeri sebagai sumber stage dan lightbox.
    const getImages = (gallery) =>
      Array.from(gallery.querySelectorAll('[data-gallery-link]')).map((link) => ({
        href: String(link.getAttribute('href') || ''),
        title: String(link.textContent || '').trim(),
      }));

    // Menandai thumbnail aktif dan memastikan item aktif tetap terlihat di track.
    const renderActiveThumb = (gallery, index) => {
      gallery.querySelectorAll('[data-gallery-thumb]').forEach((button) => {
        const isActive = Number(button.dataset.index || 0) === index;
        button.classList.toggle('is-active', isActive);
        if (isActive) {
          button.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
            inline: 'center',
          });
        }
      });
    };

    // Mengganti gambar utama galeri berdasarkan index thumbnail yang dipilih.
    const setGalleryImage = (gallery, index) => {
      const images = getImages(gallery);
      if (!images.length) return;

      const normalizedIndex = Math.max(0, Math.min(index, images.length - 1));
      const main = gallery.querySelector('[data-gallery-main]');
      if (main) {
        main.src = images[normalizedIndex].href;
        main.alt =
          images[normalizedIndex].title ||
          gallery.dataset.galleryTitle ||
          'Gallery image';
      }

      gallery.dataset.activeIndex = String(normalizedIndex);
      renderActiveThumb(gallery, normalizedIndex);
    };

    // Menyalakan atau mematikan tombol panah thumbnail sesuai posisi scroll.
    const updateThumbNav = (gallery) => {
      const track = gallery.querySelector('[data-gallery-track]');
      const prev = gallery.querySelector('[data-gallery-prev]');
      const next = gallery.querySelector('[data-gallery-next]');
      if (!track || !prev || !next) return;

      const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
      prev.disabled = track.scrollLeft <= 4;
      next.disabled = track.scrollLeft >= maxScroll - 4;
    };

    // Membuka lightbox untuk galeri aktif pada index gambar tertentu.
    const openLightbox = (gallery, index) => {
      ensureLightbox();
      activeGallery = gallery;
      activeIndex = index;
      syncLightbox();
      lightbox.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    };

    // Menutup lightbox dan mengembalikan scroll body normal.
    const closeLightbox = () => {
      if (!lightbox) return;
      lightbox.classList.remove('is-open');
      document.body.style.overflow = '';
    };

    // Menyinkronkan isi lightbox dengan gallery aktif dan index saat ini.
    const syncLightbox = () => {
      if (!activeGallery || !lightboxImage) return;

      const images = getImages(activeGallery);
      if (!images.length) return;

      if (activeIndex < 0) activeIndex = images.length - 1;
      if (activeIndex >= images.length) activeIndex = 0;

      const current = images[activeIndex];
      lightboxImage.src = current.href;
      lightboxImage.alt =
        current.title || activeGallery.dataset.galleryTitle || 'Gallery image';
      if (lightboxCounter) {
        lightboxCounter.textContent = `${activeIndex + 1} / ${images.length}`;
      }
      setGalleryImage(activeGallery, activeIndex);
    };

    // Berpindah ke gambar berikutnya atau sebelumnya di lightbox.
    const stepLightbox = (step) => {
      if (!activeGallery) return;
      activeIndex += step;
      syncLightbox();
    };

    galleries.forEach((gallery) => {
      const thumbs = gallery.querySelectorAll('[data-gallery-thumb]');
      const stage = gallery.querySelector('[data-gallery-open]');
      const track = gallery.querySelector('[data-gallery-track]');
      const prev = gallery.querySelector('[data-gallery-prev]');
      const next = gallery.querySelector('[data-gallery-next]');

      gallery.dataset.activeIndex = gallery.dataset.activeIndex || '0';

      thumbs.forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          const nextIndex = Number(button.dataset.index || 0);
          setGalleryImage(gallery, nextIndex);
        });
      });

      if (stage) {
        stage.addEventListener('click', (event) => {
          event.preventDefault();
          openLightbox(gallery, Number(gallery.dataset.activeIndex || 0));
        });
      }

      if (track && prev && next) {
        // Menggeser track thumbnail per langkah agar navigasi terasa seperti carousel.
        const moveThumbs = (direction) => {
          const amount = Math.max(180, Math.floor(track.clientWidth * 0.7));
          track.scrollBy({
            left: direction * amount,
            behavior: 'smooth',
          });
        };

        prev.addEventListener('click', (event) => {
          event.preventDefault();
          moveThumbs(-1);
        });

        next.addEventListener('click', (event) => {
          event.preventDefault();
          moveThumbs(1);
        });

        track.addEventListener('scroll', () => updateThumbNav(gallery), {
          passive: true,
        });
        window.addEventListener('resize', () => updateThumbNav(gallery));
        updateThumbNav(gallery);
      }

      setGalleryImage(gallery, Number(gallery.dataset.activeIndex || 0));
    });
  };

  // Menjalankan helper UI ringan setelah DOM siap dipakai.
  document.addEventListener('DOMContentLoaded', () => {
    initActionButtons();
    initProductGallery();
  });
})();
