<?php

namespace VelocityMarketplace\Modules\Email;

use VelocityMarketplace\Modules\Order\OrderData;
use VelocityMarketplace\Support\Settings;

class EmailTemplateService
{
    public static function default_settings()
    {
        return [
            'email_admin_recipient' => '',
            'email_template_admin_order' => implode("\n\n", [
                'Hallo Admin,',
                'Ada pesanan baru di [nama-toko].',
                'Silakan follow up pesanan sampai transaksi berhasil.',
                'Kode pesanan:',
                '[kode-pesanan]',
                'Waktu pemesanan: [tanggal-order]',
                'Berikut detail pesanan:',
                '[detail-pesanan]',
                'Berikut data pemesan:',
                '[data-pemesan]',
                'Hormat kami,',
                '[nama-toko]',
            ]),
            'email_template_customer_order' => implode("\n\n", [
                'Hallo [nama-pemesan],',
                'Terima kasih telah berbelanja di [nama-toko].',
                'Semoga Anda menyukai produk yang telah Anda beli.',
                'Kode pesanan Anda:',
                '[kode-pesanan]',
                'Waktu pesanan: [tanggal-order]',
                'Berikut konfirmasi pesanan untuk pengiriman barang Anda:',
                '[detail-pesanan]',
                'Silakan transfer [total-order]',
                '[nomor-rekening]',
                'Penting:',
                '<ul><li>Pengiriman baru dapat diproses jika Anda telah melakukan konfirmasi pembayaran.</li><li>Pastikan selalu memasukkan kode pesanan untuk memudahkan proses pesanan.</li></ul>',
                'Hormat kami,',
                '[nama-toko]',
                '[alamat-toko]',
            ]),
            'email_template_status_update' => implode("\n\n", [
                'Hallo [nama-pemesan],',
                'Terima kasih telah berbelanja di [nama-toko].',
                'Perubahan status pesanan #[kode-pesanan] menjadi [status].',
                'Lihat detail di [link]',
                'Hormat kami,',
                '[nama-toko]',
                '[alamat-toko]',
            ]),
        ];
    }

    public function admin_recipient()
    {
        $settings = Settings::all();
        $email = sanitize_email((string) ($settings['email_admin_recipient'] ?? ''));
        if ($email === '') {
            $email = sanitize_email((string) get_option('admin_email'));
        }

        return is_email($email) ? $email : '';
    }

    public function template($key)
    {
        $defaults = self::default_settings();
        $settings = Settings::all();
        $field = $this->field_name($key);
        if ($field === '') {
            return '';
        }

        $template = (string) ($settings[$field] ?? '');
        if ($template === '') {
            $template = (string) ($defaults[$field] ?? '');
        }

        return $template;
    }

    public function send_admin_new_order($order_id)
    {
        $to = $this->admin_recipient();
        if ($to === '') {
            return false;
        }

        $context = $this->build_order_context($order_id);
        $subject = sprintf(__('Pesanan baru %s', 'velocity-marketplace'), (string) ($context['[kode-pesanan]'] ?? ''));

        return $this->send_html_email($to, $subject, $this->render_template($this->template('admin_order'), $context));
    }

    public function send_customer_new_order($order_id)
    {
        $context = $this->build_order_context($order_id);
        $to = sanitize_email((string) ($context['__customer_email'] ?? ''));
        if ($to === '' || !is_email($to)) {
            return false;
        }

        $subject = sprintf(__('Pesanan %s berhasil dibuat', 'velocity-marketplace'), (string) ($context['[kode-pesanan]'] ?? ''));

        return $this->send_html_email($to, $subject, $this->render_template($this->template('customer_order'), $context));
    }

    public function send_customer_status_update($order_id, $status)
    {
        $context = $this->build_order_context($order_id, [
            '[status]' => OrderData::status_label((string) $status),
        ]);
        $to = sanitize_email((string) ($context['__customer_email'] ?? ''));
        if ($to === '' || !is_email($to)) {
            return false;
        }

        $subject = sprintf(
            __('Status pesanan %1$s menjadi %2$s', 'velocity-marketplace'),
            (string) ($context['[kode-pesanan]'] ?? ''),
            (string) ($context['[status]'] ?? '')
        );

        return $this->send_html_email($to, $subject, $this->render_template($this->template('status_update'), $context));
    }

    public function render_template($template, array $context)
    {
        $search = [];
        $replace = [];
        foreach ($context as $token => $value) {
            if (strpos((string) $token, '__') === 0) {
                continue;
            }
            $search[] = (string) $token;
            $replace[] = (string) $value;
        }

        $body = str_replace($search, $replace, (string) $template);
        if (strpos($body, '<') === false) {
            $body = wpautop($body);
        }

        return $body;
    }

    private function send_html_email($to, $subject, $body)
    {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return wp_mail($to, $subject, $body, $headers);
    }

    private function field_name($key)
    {
        $map = [
            'admin_order' => 'email_template_admin_order',
            'customer_order' => 'email_template_customer_order',
            'status_update' => 'email_template_status_update',
        ];

        return isset($map[$key]) ? $map[$key] : '';
    }

    private function build_order_context($order_id, array $extra = [])
    {
        $order_id = (int) $order_id;
        $invoice = (string) get_post_meta($order_id, 'vmp_invoice', true);
        $created_at = (string) get_post_meta($order_id, 'vmp_created_at', true);
        $created_at = $created_at !== '' ? mysql2date('d-m-Y H:i', $created_at) : get_the_date('d-m-Y H:i', $order_id);
        $customer = get_post_meta($order_id, 'vmp_customer', true);
        $customer = is_array($customer) ? $customer : [];
        $items = OrderData::get_items($order_id);
        $total = (float) get_post_meta($order_id, 'vmp_total', true);
        $status = (string) get_post_meta($order_id, 'vmp_status', true);
        $bank_accounts = get_post_meta($order_id, 'vmp_bank_accounts', true);
        if (!is_array($bank_accounts) || empty($bank_accounts)) {
            $bank_accounts = Settings::bank_accounts();
        }

        $store_name = (string) get_bloginfo('name');
        $store_address = esc_url(home_url('/'));
        $tracking_url = Settings::tracking_url($invoice);

        $context = [
            '__customer_email' => (string) ($customer['email'] ?? ''),
            '[nama-toko]' => esc_html($store_name),
            '[alamat-toko]' => esc_html($store_address),
            '[kode-pesanan]' => esc_html($invoice),
            '[tanggal-order]' => esc_html($created_at),
            '[detail-pesanan]' => $this->render_order_items($items),
            '[data-pemesan]' => $this->render_customer_data($customer),
            '[nama-pemesan]' => esc_html((string) ($customer['name'] ?? '')),
            '[total-order]' => esc_html($this->money($total)),
            '[nomor-rekening]' => $this->render_bank_accounts($bank_accounts),
            '[status]' => esc_html(OrderData::status_label($status)),
            '[link]' => '<a href="' . esc_url($tracking_url) . '">' . esc_html($tracking_url) . '</a>',
        ];

        foreach ($extra as $token => $value) {
            $context[(string) $token] = (string) $value;
        }

        return $context;
    }

    private function render_order_items(array $items)
    {
        if (empty($items)) {
            return '<p>' . esc_html__('Tidak ada item pesanan.', 'velocity-marketplace') . '</p>';
        }

        $html = '<ul>';
        foreach ($items as $item) {
            $title = esc_html((string) ($item['title'] ?? __('Produk', 'velocity-marketplace')));
            $qty = (int) ($item['qty'] ?? 0);
            $subtotal = (float) ($item['subtotal'] ?? 0);
            $line = $title . ' x ' . $qty . ' - ' . esc_html($this->money($subtotal));

            if (!empty($item['options']) && is_array($item['options'])) {
                $option_parts = [];
                foreach ($item['options'] as $option_key => $option_value) {
                    $option_parts[] = esc_html(ucfirst((string) $option_key) . ': ' . (string) $option_value);
                }
                if (!empty($option_parts)) {
                    $line .= ' (' . implode(', ', $option_parts) . ')';
                }
            }

            $html .= '<li>' . $line . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    private function render_customer_data(array $customer)
    {
        $rows = [];
        if (!empty($customer['name'])) {
            $rows[] = '<div><strong>' . esc_html__('Nama', 'velocity-marketplace') . ':</strong> ' . esc_html((string) $customer['name']) . '</div>';
        }
        if (!empty($customer['email'])) {
            $rows[] = '<div><strong>' . esc_html__('Email', 'velocity-marketplace') . ':</strong> ' . esc_html((string) $customer['email']) . '</div>';
        }
        if (!empty($customer['phone'])) {
            $rows[] = '<div><strong>' . esc_html__('Telepon', 'velocity-marketplace') . ':</strong> ' . esc_html((string) $customer['phone']) . '</div>';
        }
        if (!empty($customer['address'])) {
            $rows[] = '<div><strong>' . esc_html__('Alamat', 'velocity-marketplace') . ':</strong> ' . nl2br(esc_html((string) $customer['address'])) . '</div>';
        }

        return !empty($rows) ? implode('', $rows) : '<p>-</p>';
    }

    private function render_bank_accounts(array $accounts)
    {
        if (empty($accounts)) {
            return '<p>-</p>';
        }

        $html = '<ul>';
        foreach ($accounts as $account) {
            if (!is_array($account)) {
                continue;
            }
            $bank_name = esc_html((string) ($account['bank_name'] ?? '-'));
            $account_number = esc_html((string) ($account['account_number'] ?? '-'));
            $account_holder = esc_html((string) ($account['account_holder'] ?? '-'));
            $html .= '<li><strong>' . $bank_name . '</strong><br>' .
                esc_html__('Nomor Rekening', 'velocity-marketplace') . ': ' . $account_number . '<br>' .
                esc_html__('Atas Nama', 'velocity-marketplace') . ': ' . $account_holder . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    private function money($value)
    {
        return Settings::currency_symbol() . ' ' . number_format((float) $value, 0, ',', '.');
    }
}
