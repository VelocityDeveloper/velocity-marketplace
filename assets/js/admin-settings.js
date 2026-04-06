/* Komponen admin settings untuk page custom marketplace. */
(() => {
  const cfg = window.vmpAdminSettings || {};
  const editorIds = {
    admin: 'vmp_email_template_admin',
    customer: 'vmp_email_template_customer',
    status: 'vmp_email_template_status',
  };

  // Mengirim request ke endpoint settings admin dengan nonce WordPress.
  const request = async (method, payload = null) => {
    const options = {
      method,
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': cfg.nonce || '',
      },
    };

    if (payload !== null) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(payload);
    }

    const response = await fetch(cfg.restUrl || '', options);
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.message || 'Permintaan tidak dapat diproses.');
    }

    return data;
  };

  // Menyalin payload settings dari backend ke state Alpine page admin.
  const applySettings = (target, settings = {}) => {
    target.form.seller_product_status = String(settings.seller_product_status || 'publish');
    target.form.email_admin_recipient = String(settings.email_admin_recipient || '');
    target.form.email_template_admin_order = String(settings.email_template_admin_order || '');
    target.form.email_template_customer_order = String(settings.email_template_customer_order || '');
    target.form.email_template_status_update = String(settings.email_template_status_update || '');
  };

  // Membaca isi editor email dari TinyMCE atau textarea fallback.
  const readEditorValue = (id) => {
    if (window.tinyMCE && typeof window.tinyMCE.triggerSave === 'function') {
      window.tinyMCE.triggerSave();
    }
    const field = document.getElementById(id);
    return field ? String(field.value || '') : '';
  };

  // Menulis isi editor email ke TinyMCE dan textarea agar tetap sinkron.
  const writeEditorValue = (id, value) => {
    const content = String(value || '');
    if (window.tinyMCE && typeof window.tinyMCE.get === 'function') {
      const editor = window.tinyMCE.get(id);
      if (editor) {
        editor.setContent(content);
        editor.save();
      }
    }
    const field = document.getElementById(id);
    if (field) {
      field.value = content;
    }
  };

  // Menyediakan state Alpine untuk pengaturan marketplace-specific.
  const component = () => ({
    activeTab: 'general',
    saving: false,
    loading: false,
    saveMessage: '',
    saveError: '',
    form: {
      seller_product_status: 'publish',
      email_admin_recipient: '',
      email_template_admin_order: '',
      email_template_customer_order: '',
      email_template_status_update: '',
    },
    // Mengisi state form dari payload settings awal saat halaman dibuka.
    init() {
      applySettings(this, cfg.initialSettings || {});
      this.$nextTick(() => {
        this.syncEditorsFromState();
      });
    },
    // Mengganti tab aktif tanpa me-reload halaman admin.
    setTab(tab) {
      this.activeTab = String(tab || 'general');
      if (this.activeTab === 'email') {
        this.$nextTick(() => {
          this.refreshEditors();
        });
      }
    },
    // Menyalin isi editor email ke state Alpine sebelum proses simpan.
    syncEditorsToState() {
      this.form.email_template_admin_order = readEditorValue(editorIds.admin);
      this.form.email_template_customer_order = readEditorValue(editorIds.customer);
      this.form.email_template_status_update = readEditorValue(editorIds.status);
    },
    // Menyalin state Alpine ke editor email saat data awal atau hasil simpan diterima.
    syncEditorsFromState() {
      writeEditorValue(editorIds.admin, this.form.email_template_admin_order);
      writeEditorValue(editorIds.customer, this.form.email_template_customer_order);
      writeEditorValue(editorIds.status, this.form.email_template_status_update);
    },
    // Memaksa editor WordPress repaint saat tab email dibuka.
    refreshEditors() {
      if (!window.tinyMCE || typeof window.tinyMCE.get !== 'function') {
        return;
      }
      Object.values(editorIds).forEach((id) => {
        const editor = window.tinyMCE.get(id);
        if (!editor) {
          return;
        }
        editor.save();
        editor.fire('ResizeEditor');
      });
    },
    // Menyimpan seluruh pengaturan custom admin ke backend via REST.
    async save() {
      this.saving = true;
      this.saveMessage = '';
      this.saveError = '';

      try {
        this.syncEditorsToState();
        const payload = {
          seller_product_status: this.form.seller_product_status,
          email_admin_recipient: this.form.email_admin_recipient,
          email_template_admin_order: this.form.email_template_admin_order,
          email_template_customer_order: this.form.email_template_customer_order,
          email_template_status_update: this.form.email_template_status_update,
        };

        const data = await request('POST', payload);
        const saved = data && data.data && data.data.settings ? data.data.settings : null;
        if (saved) {
          applySettings(this, saved);
          this.syncEditorsFromState();
        }
        this.saveMessage = data.message || 'Pengaturan berhasil disimpan.';
      } catch (error) {
        this.saveError = error.message || 'Pengaturan tidak dapat disimpan.';
      } finally {
        this.saving = false;
      }
    },
  });

  window.vmpAdminSettingsPage = component;

  document.addEventListener('alpine:init', () => {
    Alpine.data('vmpAdminSettingsPage', component);
  });
})();

