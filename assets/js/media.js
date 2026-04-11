/* Helper media library untuk field gambar custom di dashboard. */
(() => {
  const cfg = window.vmpSettings || {};
  const currentUserId = Number(cfg.currentUserId || 0);
  const canManageOptions = !!cfg.canManageOptions;
  let validationDialog = null;

  const escapeHtml = (value) =>
    String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");

  const ensureValidationDialog = () => {
    if (validationDialog) return validationDialog;

    const dialog = document.createElement("div");
    dialog.className = "vmp-validation-dialog";
    dialog.innerHTML = `
      <div class="vmp-validation-dialog__backdrop" data-vmp-dialog-close="1"></div>
      <div class="vmp-validation-dialog__panel" role="alertdialog" aria-modal="true" aria-labelledby="vmp-validation-dialog-title">
        <div class="vmp-validation-dialog__header">
          <h3 id="vmp-validation-dialog-title" class="vmp-validation-dialog__title">Field wajib belum lengkap</h3>
          <button type="button" class="vmp-validation-dialog__close" aria-label="Tutup" data-vmp-dialog-close="1">&times;</button>
        </div>
        <div class="vmp-validation-dialog__body">
          <p class="vmp-validation-dialog__text">Lengkapi field berikut sebelum menyimpan produk.</p>
          <ul class="vmp-validation-dialog__list"></ul>
        </div>
        <div class="vmp-validation-dialog__footer">
          <button type="button" class="btn btn-dark btn-sm" data-vmp-dialog-close="1">Tutup</button>
        </div>
      </div>
    `;

    dialog.addEventListener("click", (event) => {
      if (event.target && event.target.getAttribute("data-vmp-dialog-close") === "1") {
        dialog.classList.remove("is-open");
      }
    });

    document.body.appendChild(dialog);
    validationDialog = dialog;
    return validationDialog;
  };

  const fieldLabel = (control) => {
    const explicit = String(control.getAttribute("data-field-label") || "").trim();
    if (explicit) return explicit;

    const id = String(control.id || "").trim();
    if (id) {
      const label = document.querySelector(`label[for="${CSS.escape(id)}"]`);
      const text = String(label?.textContent || "").trim().replace(/\s*\*+\s*$/, "");
      if (text) return text;
    }

    const wrap = control.closest("[data-field-required], .col-12, .col-md-6, .form-field, p");
    const text = String(wrap?.querySelector("label")?.textContent || "").trim().replace(/\s*\*+\s*$/, "");
    return text || "Field wajib";
  };

  const isMissingRequired = (control, form) => {
    if (!control || control.disabled || control.type === "hidden") {
      return false;
    }

    const type = String(control.type || "").toLowerCase();
    const tag = String(control.tagName || "").toLowerCase();

    if (type === "checkbox") {
      return !control.checked;
    }

    if (type === "radio") {
      const name = String(control.name || "").trim();
      if (!name) {
        return !control.checked;
      }
      return !form.querySelector(`input[type="radio"][name="${CSS.escape(name)}"]:checked`);
    }

    if (tag === "select") {
      return String(control.value || "").trim() === "";
    }

    return String(control.value || "").trim() === "";
  };

  const collectMissingRequiredFields = (form) => {
    const labels = [];
    const seen = new Set();

    form.querySelectorAll("[data-required='1']").forEach((control) => {
      if (!isMissingRequired(control, form)) {
        control.classList.remove("is-invalid");
        return;
      }

      control.classList.add("is-invalid");
      const label = fieldLabel(control);
      if (!seen.has(label)) {
        labels.push(label);
        seen.add(label);
      }
    });

    return labels;
  };

  const showValidationDialog = (labels) => {
    const dialog = ensureValidationDialog();
    const list = dialog.querySelector(".vmp-validation-dialog__list");
    if (list) {
      list.innerHTML = (Array.isArray(labels) ? labels : [])
        .map((label) => `<li>${escapeHtml(label)}</li>`)
        .join("");
    }
    dialog.classList.add("is-open");
  };

  // Mengubah nilai input hidden menjadi daftar ID attachment yang valid.
  const parseAttachmentIds = (value) =>
    String(value || "")
      .split(",")
      .map((item) => Number(item.trim()))
      .filter((item) => item > 0);

  // Mengambil item preview yang sedang tampil agar bisa dipertahankan saat user menambah galeri.
  const currentPreviewItems = (preview) =>
    Array.from(preview?.querySelectorAll(".vmp-media-field__item") || []).map((item) => ({
      id: Number(item.dataset.id || 0),
      url: String(item.querySelector(".vmp-media-field__image")?.getAttribute("src") || ""),
      title: String(item.querySelector(".vmp-media-field__image")?.getAttribute("alt") || ""),
    })).filter((item) => item.id > 0);

  // Menggabungkan item lama dan baru tanpa menduplikasi attachment yang sama.
  const mergeItemsById = (items) => {
    const map = new Map();
    (Array.isArray(items) ? items : []).forEach((item) => {
      const id = Number(item?.id || 0);
      if (id <= 0) return;
      map.set(id, {
        id,
        url: String(item.url || ""),
        title: String(item.title || ""),
      });
    });
    return Array.from(map.values());
  };

  // Merender ulang preview media berdasarkan attachment yang dipilih user.
  const renderMediaPreview = (
    preview,
    items,
    multiple,
    emptyText,
    showOverlayRemove = true,
    previewRatio = "",
    previewFit = "cover",
  ) => {
    if (!preview) return;

    if (!Array.isArray(items) || items.length === 0) {
      preview.innerHTML = `<div class="vmp-media-field__empty text-muted small">${emptyText}</div>`;
      return;
    }

    const gridClass = multiple
      ? "vmp-media-field__grid"
      : "vmp-media-field__grid vmp-media-field__grid--single";

    const ratioClass = previewRatio ? ` ratio ratio-${previewRatio}` : "";
    const imageClass =
      previewFit === "contain"
        ? "vmp-media-field__image vmp-media-field__image--contain"
        : "vmp-media-field__image";

    preview.innerHTML = `
      <div class="${gridClass}">
        ${items
          .map(
            (item) => `
              <div class="vmp-media-field__item" data-id="${Number(item.id || 0)}">
                <div class="vmp-media-field__frame${ratioClass}">
                  <img src="${String(item.url || "")}" alt="${String(item.title || "")}" class="${imageClass}">
                </div>
                ${showOverlayRemove ? '<button type="button" class="btn-close vmp-media-field__remove" aria-label="Hapus gambar"></button>' : ''}
              </div>
            `,
          )
          .join("")}
      </div>
    `;
  };

  const renderFileLinkPreview = (preview, url, emptyText) => {
    if (!preview) return;

    const value = String(url || "").trim();
    if (!value) {
      preview.innerHTML = `<div class="vmp-file-link-field__empty text-muted small">${emptyText}</div>`;
      return;
    }

    let label = value;
    try {
      const parsed = new URL(value, window.location.origin);
      const parts = String(parsed.pathname || "").split("/").filter(Boolean);
      if (parts.length) {
        label = parts[parts.length - 1];
      }
    } catch (e) {}

    preview.innerHTML = `
      <div class="vmp-file-link-field__summary">
        <div class="vmp-file-link-field__name">${label}</div>
        <a href="${value}" target="_blank" rel="noopener noreferrer" class="vmp-file-link-field__link">${value}</a>
      </div>
    `;
  };

  // Menghubungkan field custom dengan modal media library WordPress.
  const initMediaFields = () => {
    if (!window.wp || !wp.media) return;

    document.querySelectorAll(".vmp-media-field").forEach((field) => {
      const input = field.querySelector(".vmp-media-field__input");
      const preview = field.querySelector(".vmp-media-field__preview");
      const openBtn = field.querySelector(".vmp-media-field__open");
      const clearBtn = field.querySelector(".vmp-media-field__clear");
      const multiple = field.dataset.multiple === "1";
      const showOverlayRemove = field.dataset.overlayRemove !== "0";
      const previewRatio = String(field.dataset.previewRatio || "").trim();
      const previewFit = String(field.dataset.previewFit || "cover").trim().toLowerCase();
      const emptyText =
        preview && preview.dataset.placeholder
          ? preview.dataset.placeholder
          : "Belum ada gambar dipilih.";

      if (!input || !preview || !openBtn || !clearBtn) return;

      // Menyinkronkan status tombol hapus dengan isi field media saat ini.
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
            ...(currentUserId > 0 && !canManageOptions ? { author: currentUserId } : {}),
          },
        });

        frame.on("open", () => {
          if (currentUserId > 0 && !canManageOptions) {
            const library = frame.state().get("library");
            if (library && library.props) {
              library.props.set({
                author: currentUserId,
                type: "image",
              });
            }
          }

          if (multiple) {
            const selection = frame.state().get("selection");
            parseAttachmentIds(input.value).forEach((id) => {
              const attachment = wp.media.attachment(id);
              if (attachment) {
                attachment.fetch();
                selection.add(attachment);
              }
            });
          }
        });

        frame.on("select", () => {
          const selection = frame.state().get("selection");
          const items = multiple ? currentPreviewItems(preview) : [];

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

            items.push({
              id: Number(data.id),
              url: imageUrl,
              title: data.title || "",
            });
          });

          const normalizedItems = multiple ? mergeItemsById(items) : items.slice(0, 1);
          input.value = multiple
            ? normalizedItems.map((item) => item.id).join(",")
            : String(normalizedItems[0]?.id || "");
          renderMediaPreview(
            preview,
            normalizedItems,
            multiple,
            emptyText,
            showOverlayRemove,
            previewRatio,
            previewFit,
          );
          syncButtons();
        });

        frame.open();
      });

      clearBtn.addEventListener("click", (event) => {
        event.preventDefault();
        input.value = "";
        renderMediaPreview(preview, [], multiple, emptyText, showOverlayRemove, previewRatio, previewFit);
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
          renderMediaPreview(preview, [], multiple, emptyText, showOverlayRemove, previewRatio, previewFit);
        }
        syncButtons();
      });

      syncButtons();
    });
  };

  const initFileLinkFields = () => {
    if (!window.wp || !wp.media) return;

    document.querySelectorAll(".vmp-file-link-field").forEach((field) => {
      const input = field.querySelector(".vmp-file-link-field__input");
      const preview = field.querySelector(".vmp-file-link-field__preview");
      const openBtn = field.querySelector(".vmp-file-link-field__open");
      const clearBtn = field.querySelector(".vmp-file-link-field__clear");
      const emptyText =
        preview && preview.dataset.placeholder
          ? preview.dataset.placeholder
          : "Belum ada file dipilih.";

      if (!input || !preview || !openBtn || !clearBtn) return;

      const syncButtons = () => {
        clearBtn.disabled = String(input.value || "").trim() === "";
      };

      const syncPreview = () => {
        renderFileLinkPreview(preview, input.value, emptyText);
        syncButtons();
      };

      openBtn.addEventListener("click", (event) => {
        event.preventDefault();

        const frame = wp.media({
          title: openBtn.dataset.title || "Pilih File",
          button: {
            text: openBtn.dataset.button || "Gunakan file ini",
          },
          multiple: false,
          library: {
            ...(currentUserId > 0 && !canManageOptions ? { author: currentUserId } : {}),
          },
        });

        frame.on("open", () => {
          if (currentUserId > 0 && !canManageOptions) {
            const library = frame.state().get("library");
            if (library && library.props) {
              library.props.set({
                author: currentUserId,
              });
            }
          }
        });

        frame.on("select", () => {
          const attachment = frame.state().get("selection").first();
          if (!attachment) return;

          const data = attachment.toJSON();
          input.value = String(data.url || "");
          syncPreview();
        });

        frame.open();
      });

      clearBtn.addEventListener("click", (event) => {
        event.preventDefault();
        input.value = "";
        syncPreview();
      });

      input.addEventListener("input", syncPreview);
      input.addEventListener("change", syncPreview);
      syncPreview();
    });
  };

  const toggleProductTypeFields = () => {
    const typeSelect = document.querySelector("#_store_product_type");
    if (!typeSelect) return;

    const type = String(typeSelect.value || "physical").trim();
    document.querySelectorAll("[data-show-if-product-type]").forEach((fieldWrap) => {
      const expectedType = String(fieldWrap.getAttribute("data-show-if-product-type") || "").trim();
      if (!expectedType) return;

      const visible = expectedType === type;
      fieldWrap.querySelectorAll("input, select, textarea").forEach((control) => {
        if (control.type === "hidden") {
          return;
        }

        const shouldRequire = visible && control.getAttribute("data-required") === "1";
        control.setAttribute("aria-required", shouldRequire ? "true" : "false");
        control.disabled = !visible;
      });

      fieldWrap.style.display = visible ? "" : "none";
    });
  };

  document.addEventListener("change", (event) => {
    if (event.target && event.target.id === "_store_product_type") {
      toggleProductTypeFields();
    }
  });

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const actionInput = form.querySelector('input[name="vmp_action"][value="seller_save_product"]');
    if (!actionInput) {
      return;
    }

    const missingFields = collectMissingRequiredFields(form);
    if (!missingFields.length) {
      return;
    }

    event.preventDefault();
    showValidationDialog(missingFields);

    const firstInvalid = form.querySelector(".is-invalid");
    if (firstInvalid instanceof HTMLElement) {
      firstInvalid.focus();
    }
  });

  document.addEventListener("DOMContentLoaded", initMediaFields);
  document.addEventListener("DOMContentLoaded", initFileLinkFields);
  document.addEventListener("DOMContentLoaded", toggleProductTypeFields);
})();

