<?php
$notification_rows = is_array($notifications ?? null) ? array_values($notifications) : [];
?>
<div
    class="card border-0 shadow-sm"
    x-data="vmpNotificationsPanel()"
    x-init="init($el.dataset.notifications || '[]')"
    data-notifications="<?php echo esc_attr(wp_json_encode($notification_rows)); ?>"
>
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h6 mb-0"><?php echo esc_html__('Notifikasi', 'velocity-marketplace'); ?></h3>
            <button type="button" class="btn btn-sm btn-outline-dark" @click="markAll()" :disabled="loading || unreadCount === 0">
                <span x-show="!loading"><?php echo esc_html__('Tandai Semua Sudah Dibaca', 'velocity-marketplace'); ?></span>
                <span x-show="loading" style="display:none;"><?php echo esc_html__('Memproses...', 'velocity-marketplace'); ?></span>
            </button>
        </div>

        <div class="alert alert-success py-2" x-show="message" x-text="message" style="display:none;"></div>
        <div class="alert alert-danger py-2" x-show="error" x-text="error" style="display:none;"></div>

        <template x-if="items.length === 0">
            <div class="small text-muted"><?php echo esc_html__('Belum ada notifikasi.', 'velocity-marketplace'); ?></div>
        </template>

        <div class="list-group list-group-flush" x-show="items.length > 0" style="display:none;">
            <template x-for="row in items" :key="row.id">
                <div class="list-group-item px-0" :class="{ 'bg-light': !Number(row.is_read) }">
                    <div class="d-flex justify-content-between gap-2 flex-wrap">
                        <div>
                            <div class="fw-semibold" x-text="row.title"></div>
                            <div class="small text-muted" x-text="row.message"></div>
                            <div class="small text-muted" x-text="formatDate(row.created_at)"></div>
                            <template x-if="row.url">
                                <a class="small" :href="row.url"><?php echo esc_html__('Lihat Detail', 'velocity-marketplace'); ?></a>
                            </template>
                        </div>
                        <div class="d-flex gap-1 align-items-start">
                            <template x-if="!Number(row.is_read)">
                                <button type="button" class="btn btn-sm btn-outline-success" @click="markRead(row.id)" :disabled="loading">
                                    <?php echo esc_html__('Tandai Dibaca', 'velocity-marketplace'); ?>
                                </button>
                            </template>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="remove(row.id)" :disabled="loading">
                                <?php echo esc_html__('Hapus', 'velocity-marketplace'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
(() => {
  if (window.vmpNotificationsPanel) {
    return;
  }

  window.vmpNotificationsPanel = () => ({
    items: [],
    loading: false,
    message: '',
    error: '',
    async init(rawItems) {
      try {
        const parsed = JSON.parse(String(rawItems || '[]'));
        this.items = Array.isArray(parsed) ? parsed : [];
      } catch (e) {
        this.items = [];
      }

      this.dispatchUnread(this.unreadCount);

      if (this.items.length === 0) {
        try {
          const response = await this.request('notifications', { method: 'GET' });
          this.sync(response);
        } catch (e) {
          // keep silent here; empty state is enough
        }
      }
    },
    get unreadCount() {
      return this.items.filter((row) => !Number(row && row.is_read)).length;
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
    sync(payload) {
      const data = payload && payload.data ? payload.data : {};
      this.items = Array.isArray(data.items) ? data.items : this.items;
      this.dispatchUnread(data.unread_count);
    },
    dispatchUnread(count) {
      document.dispatchEvent(new CustomEvent('vmp:notifications-updated', {
        detail: {
          unreadCount: Number(count || 0),
        },
      }));
    },
    request(path, options) {
      const shared = window.VMPFrontend;
      if (!shared || typeof shared.request !== 'function') {
        throw new Error('Frontend helper belum siap.');
      }

      return shared.request(path, options);
    },
    async markAll() {
      this.loading = true;
      this.error = '';
      this.message = '';
      try {
        const response = await this.request('notifications/read-all', { method: 'POST' });
        this.sync(response);
        this.message = response.message || 'Semua notifikasi ditandai sudah dibaca.';
      } catch (e) {
        this.error = e.message || 'Notifikasi tidak dapat diproses.';
      } finally {
        this.loading = false;
      }
    },
    async markRead(id) {
      this.loading = true;
      this.error = '';
      this.message = '';
      try {
        const response = await this.request(`notifications/${encodeURIComponent(id)}/read`, { method: 'POST' });
        this.sync(response);
      } catch (e) {
        this.error = e.message || 'Notifikasi tidak dapat diproses.';
      } finally {
        this.loading = false;
      }
    },
    async remove(id) {
      this.loading = true;
      this.error = '';
      this.message = '';
      try {
        const response = await this.request(`notifications/${encodeURIComponent(id)}`, { method: 'DELETE' });
        this.sync(response);
      } catch (e) {
        this.error = e.message || 'Notifikasi tidak dapat dihapus.';
      } finally {
        this.loading = false;
      }
    },
  });

  document.addEventListener('alpine:init', () => {
    Alpine.data('vmpNotificationsPanel', window.vmpNotificationsPanel);
  });
})();
</script>
