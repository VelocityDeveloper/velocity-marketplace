/* Komponen admin settings untuk page custom marketplace. */
(() => {
  const cfg = window.vmpAdminSettings || {};
  const editorIds = {
    admin: 'vmp_email_template_admin',
    customer: 'vmp_email_template_customer',
    status: 'vmp_email_template_status',
  };

  let toastTimer = null;

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

  const applySettings = (target, settings = {}) => {
    target.form.seller_product_status = String(settings.seller_product_status || 'publish');
    target.form.email_admin_recipient = String(settings.email_admin_recipient || '');
    target.form.email_from_name = String(settings.email_from_name || '');
    target.form.email_from_address = String(settings.email_from_address || '');
    target.form.email_reply_to = String(settings.email_reply_to || '');
    target.form.email_template_admin_order = String(settings.email_template_admin_order || '');
    target.form.email_template_customer_order = String(settings.email_template_customer_order || '');
    target.form.email_template_status_update = String(settings.email_template_status_update || '');
  };

  const readEditorValue = (id) => {
    if (window.tinyMCE && typeof window.tinyMCE.triggerSave === 'function') {
      window.tinyMCE.triggerSave();
    }
    const field = document.getElementById(id);
    return field ? String(field.value || '') : '';
  };

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

  const component = () => ({
    activeTab: 'email',
    saving: false,
    loading: false,
    saveMessage: '',
    saveError: '',
    toastVisible: false,
    toastType: 'success',
    toastMessage: '',
    form: {
      seller_product_status: 'publish',
      email_admin_recipient: '',
      email_from_name: '',
      email_from_address: '',
      email_reply_to: '',
      email_template_admin_order: '',
      email_template_customer_order: '',
      email_template_status_update: '',
    },
    init() {
      applySettings(this, cfg.initialSettings || {});
    },
    showToast(type, message) {
      this.toastType = type === 'error' ? 'error' : 'success';
      this.toastMessage = String(message || '');
      this.toastVisible = true;

      if (toastTimer) {
        window.clearTimeout(toastTimer);
      }

      toastTimer = window.setTimeout(() => {
        this.toastVisible = false;
      }, 3200);
    },
    setTab(tab) {
      this.activeTab = String(tab || 'general');
      if (this.activeTab === 'email') {
        this.$nextTick(() => {
          this.refreshEditors();
        });
      }
    },
    syncEditorsToState() {
      this.form.email_template_admin_order = readEditorValue(editorIds.admin);
      this.form.email_template_customer_order = readEditorValue(editorIds.customer);
      this.form.email_template_status_update = readEditorValue(editorIds.status);
    },
    syncEditorsFromState() {
      writeEditorValue(editorIds.admin, this.form.email_template_admin_order);
      writeEditorValue(editorIds.customer, this.form.email_template_customer_order);
      writeEditorValue(editorIds.status, this.form.email_template_status_update);
    },
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
    async save() {
      this.saving = true;
      this.saveMessage = '';
      this.saveError = '';

      try {
        this.syncEditorsToState();
        const payload = {
          seller_product_status: this.form.seller_product_status,
          email_admin_recipient: this.form.email_admin_recipient,
          email_from_name: this.form.email_from_name,
          email_from_address: this.form.email_from_address,
          email_reply_to: this.form.email_reply_to,
          email_template_admin_order: this.form.email_template_admin_order,
          email_template_customer_order: this.form.email_template_customer_order,
          email_template_status_update: this.form.email_template_status_update,
        };

        const data = await request('POST', payload);
        const saved = data && data.data && data.data.settings ? data.data.settings : null;
        if (saved) {
          applySettings(this, saved);
        }
        this.saveMessage = data.message || 'Pengaturan berhasil disimpan.';
        this.showToast('success', this.saveMessage);
      } catch (error) {
        this.saveError = error.message || 'Pengaturan tidak dapat disimpan.';
        this.showToast('error', this.saveError);
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

