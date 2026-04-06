<?php
$message_initial = [
    'contacts' => is_array($message_contacts ?? null) ? array_values($message_contacts) : [],
    'thread' => is_array($message_thread ?? null) ? array_values($message_thread) : [],
    'selected_contact' => is_array($selected_message_contact ?? null) ? $selected_message_contact : null,
    'selected_to' => isset($selected_message_to) ? (int) $selected_message_to : 0,
    'selected_order_id' => isset($selected_message_order) ? (int) $selected_message_order : 0,
    'selected_invoice' => isset($selected_message_invoice) ? (string) $selected_message_invoice : '',
];
?>
<div
    class="row g-3"
    x-data="vmpMessagesPanel()"
    x-init="init($el.dataset.initial || '{}')"
    data-initial="<?php echo esc_attr(wp_json_encode($message_initial)); ?>"
>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h3 class="h6 mb-3"><?php echo esc_html__('Daftar Percakapan', 'velocity-marketplace'); ?></h3>
                <div class="alert alert-danger py-2" x-show="error" x-text="error" style="display:none;"></div>

                <template x-if="contacts.length === 0">
                    <div class="small text-muted mb-0"><?php echo esc_html__('Belum ada percakapan. Anda dapat memulai pesan dari detail pesanan atau halaman produk.', 'velocity-marketplace'); ?></div>
                </template>

                <div class="list-group list-group-flush" x-show="contacts.length > 0" style="display:none;">
                    <template x-for="contact in contacts" :key="contact.id">
                        <button type="button" class="list-group-item list-group-item-action border mb-2 px-2 text-start" :class="{ 'active': selectedTo === Number(contact.id) }" @click="openThread(contact.id)">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="min-w-0">
                                    <div class="fw-semibold" x-text="contact.name || '<?php echo esc_js(__('Pengguna', 'velocity-marketplace')); ?>'"></div>
                                    <template x-if="contact.role">
                                        <div class="small" :class="selectedTo === Number(contact.id) ? 'text-white-50' : 'text-muted'" x-text="contact.role"></div>
                                    </template>
                                    <template x-if="contact.last_message">
                                        <div class="small mt-1" :class="selectedTo === Number(contact.id) ? 'text-white-50' : 'text-muted'" x-text="contact.last_message"></div>
                                    </template>
                                    <template x-if="contact.last_order_invoice">
                                        <div class="small mt-1">
                                            <span class="badge" :class="selectedTo === Number(contact.id) ? 'bg-light text-dark' : 'bg-light text-dark border'" x-text="'Invoice: ' + contact.last_order_invoice"></span>
                                        </div>
                                    </template>
                                </div>
                                <div class="text-end">
                                    <template x-if="Number(contact.unread_count || 0) > 0">
                                        <span class="badge bg-danger" x-text="'Belum Dibaca ' + Number(contact.unread_count || 0)"></span>
                                    </template>
                                    <template x-if="contact.last_created_at">
                                        <div class="small mt-1" :class="selectedTo === Number(contact.id) ? 'text-white-50' : 'text-muted'" x-text="formatDate(contact.last_created_at)"></div>
                                    </template>
                                </div>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <template x-if="!selectedContact">
                    <div class="small text-muted mb-0"><?php echo esc_html__('Pilih percakapan untuk melihat riwayat pesan. Kontak akan tersedia setelah transaksi atau setelah pesan dimulai dari halaman produk.', 'velocity-marketplace'); ?></div>
                </template>

                <template x-if="selectedContact">
                    <div>
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-3 flex-wrap">
                            <div>
                                <h3 class="h6 mb-0" x-text="selectedContact.name || '<?php echo esc_js(__('Pengguna', 'velocity-marketplace')); ?>'"></h3>
                                <div class="small text-muted">
                                    <span x-text="selectedContact.role || ''"></span>
                                    <template x-if="selectedInvoice">
                                        <span> | <span x-text="'Invoice: ' + selectedInvoice"></span></span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded p-3 bg-light-subtle mb-3" data-message-thread x-ref="threadBox" style="max-height:540px; overflow:auto;">
                            <template x-if="thread.length === 0">
                                <div class="small text-muted"><?php echo esc_html__('Belum ada pesan di percakapan ini. Kirim pesan pertama melalui formulir di bawah.', 'velocity-marketplace'); ?></div>
                            </template>

                            <div class="d-flex flex-column gap-3" x-show="thread.length > 0" style="display:none;">
                                <template x-for="row in thread" :key="row.id">
                                    <div class="d-flex" :class="Number(row.incoming) ? 'justify-content-start' : 'justify-content-end'">
                                        <div class="border rounded px-3 py-2 bg-white" style="max-width:82%;">
                                            <div class="small fw-semibold mb-1" x-text="Number(row.incoming) ? (row.partner_name || '<?php echo esc_js(__('Pengguna', 'velocity-marketplace')); ?>') : '<?php echo esc_js(__('Saya', 'velocity-marketplace')); ?>'"></div>
                                            <div x-html="formatMessage(row.message)"></div>
                                            <div class="small text-muted mt-2">
                                                <span x-text="formatDate(row.created_at)"></span>
                                                <template x-if="row.order_invoice">
                                                    <span> | <span x-text="'Invoice: ' + row.order_invoice"></span></span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="border rounded p-3">
                            <h4 class="h6 mb-3"><?php echo esc_html__('Pesan Baru', 'velocity-marketplace'); ?></h4>
                            <div class="alert alert-success py-2" x-show="message" x-text="message" style="display:none;"></div>
                            <div class="alert alert-danger py-2" x-show="composerError" x-text="composerError" style="display:none;"></div>
                            <template x-if="selectedInvoice">
                                <div class="form-text mt-0 mb-2" x-text="'Percakapan ini terhubung ke invoice ' + selectedInvoice + '.'"></div>
                            </template>
                            <form class="row g-2" @submit.prevent="sendMessage()">
                                <div class="col-12">
                                    <textarea class="form-control" rows="5" placeholder="<?php echo esc_attr__('Tulis pesan Anda', 'velocity-marketplace'); ?>" required x-model="composer" :disabled="loading"></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-dark" :disabled="loading || !selectedTo">
                                        <span x-show="!loading"><?php echo esc_html__('Kirim Pesan', 'velocity-marketplace'); ?></span>
                                        <span x-show="loading" style="display:none;"><?php echo esc_html__('Mengirim...', 'velocity-marketplace'); ?></span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
  if (window.vmpMessagesPanel) {
    return;
  }

  window.vmpMessagesPanel = () => ({
    contacts: [],
    thread: [],
    selectedContact: null,
    selectedTo: 0,
    selectedOrderId: 0,
    selectedInvoice: '',
    composer: '',
    loading: false,
    error: '',
    message: '',
    composerError: '',
    init(rawInitial) {
      try {
        const parsed = JSON.parse(String(rawInitial || '{}'));
        this.contacts = Array.isArray(parsed.contacts) ? parsed.contacts : [];
        this.thread = Array.isArray(parsed.thread) ? parsed.thread : [];
        this.selectedContact = parsed.selected_contact && typeof parsed.selected_contact === 'object' ? parsed.selected_contact : null;
        this.selectedTo = Number(parsed.selected_to || 0);
        this.selectedOrderId = Number(parsed.selected_order_id || 0);
        this.selectedInvoice = String(parsed.selected_invoice || '');
      } catch (e) {
        this.contacts = [];
      }

      this.dispatchUnread();
      this.syncQuery();
      this.scrollToBottom();
    },
    request(path, options) {
      const shared = window.VMPFrontend;
      if (!shared || typeof shared.request !== 'function') {
        throw new Error('Frontend helper belum siap.');
      }
      return shared.request(path, options);
    },
    formatDate(value) {
      if (!value) return '';
      const date = new Date(String(value).replace(' ', 'T'));
      if (Number.isNaN(date.getTime())) {
        return String(value);
      }
      return date.toLocaleString('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    },
    formatMessage(value) {
      const text = String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\n/g, '<br>');
      return text;
    },
    dispatchUnread() {
      const unreadCount = this.contacts.reduce((carry, row) => carry + Number(row && row.unread_count ? row.unread_count : 0), 0);
      document.dispatchEvent(new CustomEvent('vmp:messages-updated', {
        detail: {
          unreadCount,
        },
      }));
    },
    syncQuery() {
      const url = new URL(window.location.href);
      url.searchParams.set('tab', 'messages');
      if (this.selectedTo > 0) {
        url.searchParams.set('message_to', String(this.selectedTo));
      } else {
        url.searchParams.delete('message_to');
      }
      if (this.selectedOrderId > 0) {
        url.searchParams.set('message_order', String(this.selectedOrderId));
      } else {
        url.searchParams.delete('message_order');
      }
      window.history.replaceState({}, '', url.toString());
    },
    applyState(data) {
      const payload = data && data.data ? data.data : {};
      this.contacts = Array.isArray(payload.contacts) ? payload.contacts : this.contacts;
      this.thread = Array.isArray(payload.thread) ? payload.thread : [];
      this.selectedContact = payload.selected_contact && typeof payload.selected_contact === 'object' ? payload.selected_contact : null;
      this.selectedOrderId = Number(payload.selected_order_id || 0);
      this.selectedInvoice = String(payload.selected_invoice || '');
      this.dispatchUnread();
      this.syncQuery();
      this.scrollToBottom();
    },
    async openThread(contactId, orderId = 0) {
      this.loading = true;
      this.error = '';
      this.message = '';
      this.composerError = '';
      try {
        this.selectedTo = Number(contactId || 0);
        if (orderId) {
          this.selectedOrderId = Number(orderId || 0);
        }
        const query = new URLSearchParams({
          contact_id: String(this.selectedTo),
          order_id: String(this.selectedOrderId || 0),
        });
        const response = await this.request(`messages/thread?${query.toString()}`, { method: 'GET' });
        this.applyState(response);
      } catch (e) {
        this.error = e.message || 'Percakapan tidak dapat dimuat.';
      } finally {
        this.loading = false;
      }
    },
    async sendMessage() {
      this.loading = true;
      this.message = '';
      this.composerError = '';
      try {
        const response = await this.request('messages/send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            recipient_id: this.selectedTo,
            order_id: this.selectedOrderId,
            message: this.composer,
          }),
        });
        this.applyState(response);
        this.composer = '';
        this.message = response.message || 'Pesan berhasil dikirim.';
      } catch (e) {
        this.composerError = e.message || 'Pesan tidak dapat dikirim.';
      } finally {
        this.loading = false;
      }
    },
    scrollToBottom() {
      this.$nextTick(() => {
        const box = this.$refs && this.$refs.threadBox ? this.$refs.threadBox : null;
        if (!box) return;
        box.scrollTop = box.scrollHeight;
      });
    },
  });

  document.addEventListener('alpine:init', () => {
    Alpine.data('vmpMessagesPanel', window.vmpMessagesPanel);
  });
})();
</script>
