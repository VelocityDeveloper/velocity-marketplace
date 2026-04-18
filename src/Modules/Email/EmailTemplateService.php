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
            'email_from_name' => '',
            'email_from_address' => '',
            'email_reply_to' => '',
            'email_template_admin_order' => implode("\n", [
                '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111;">',
                '<p>Hallo Admin,</p>',
                '<p>Ada pesanan baru di <strong>[nama-toko]</strong>. Silakan follow up pesanan sampai transaksi berhasil.</p>',
                '<p><strong>Kode pesanan:</strong> [kode-pesanan]<br><strong>Waktu pemesanan:</strong> [tanggal-order]</p>',
                '<p><strong>Berikut detail pesanan:</strong></p>',
                '[detail-pesanan]',
                '<p><strong>Berikut data pemesan:</strong></p>',
                '[data-pemesan]',
                '<p>Hormat kami,<br>[nama-toko]</p>',
                '</div>',
            ]),
            'email_template_customer_order' => implode("\n", [
                '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111;">',
                '<p>Hallo [nama-pemesan],</p>',
                '<p>Terima kasih telah berbelanja di <strong>[nama-toko]</strong>. Semoga Anda menyukai produk yang telah Anda beli.</p>',
                '<p><strong>Kode pesanan:</strong> [kode-pesanan]<br><strong>Waktu pesanan:</strong> [tanggal-order]</p>',
                '<p><strong>Berikut konfirmasi pesanan Anda:</strong></p>',
                '[detail-pesanan]',
                '<p><strong>Total pembayaran:</strong> [total-order]</p>',
                '<p><strong>Silakan transfer ke rekening berikut:</strong></p>',
                '[nomor-rekening]',
                '<p><strong>Penting:</strong></p>',
                '<ul><li>Pengiriman baru dapat diproses jika Anda telah melakukan konfirmasi pembayaran.</li><li>Pastikan selalu memasukkan kode pesanan untuk memudahkan proses pesanan.</li></ul>',
                '<p>Hormat kami,<br>[nama-toko]<br>[alamat-toko]</p>',
                '</div>',
            ]),
            'email_template_status_update' => implode("\n", [
                '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111;">',
                '<p>Hallo [nama-pemesan],</p>',
                '<p>Terima kasih telah berbelanja di <strong>[nama-toko]</strong>.</p>',
                '<p>Status pesanan <strong>#[kode-pesanan]</strong> sekarang menjadi <strong>[status]</strong>.</p>',
                '<p>Lihat detail pesanan di [link]</p>',
                '<p>Hormat kami,<br>[nama-toko]<br>[alamat-toko]</p>',
                '</div>',
            ]),
        ];
    }

    public static function normalize_template_markup($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        // Jika editor sudah mengirim block HTML yang benar, jangan diubah lagi.
        if (preg_match('/<(p|div|br|table|tbody|tr|td|th|section|article|h[1-6]|blockquote)\b/i', $value)) {
            return $value;
        }

        // Jika ada list tapi teks di luar list masih plain text, rapikan hanya segmen plain text-nya.
        if (preg_match('/<(ul|ol)\b/i', $value)) {
            $parts = preg_split('/(<(?:ul|ol)\b.*?<\/(?:ul|ol)>)/is', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            if (is_array($parts)) {
                $normalized = [];
                foreach ($parts as $part) {
                    $part = trim((string) $part);
                    if ($part === '') {
                        continue;
                    }

                    if (preg_match('/^<(ul|ol)\b/i', $part)) {
                        $normalized[] = $part;
                        continue;
                    }

                    $plain = trim((string) wp_strip_all_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $part), true));
                    if ($plain === '') {
                        continue;
                    }

                    $normalized[] = trim((string) wpautop($plain));
                }

                return implode("\n", $normalized);
            }
        }

        return trim((string) wpautop(wp_strip_all_tags($value, true)));
    }

    public static function normalize_template_setting($field, $value)
    {
        $field = (string) $field;
        $value = trim((string) $value);
        if ($value === '') {
            return $value;
        }

        $defaults = self::default_settings();
        $default = isset($defaults[$field]) ? trim((string) $defaults[$field]) : '';

        if ($default !== '' && $value === $default) {
            return $default;
        }

        $normalized = self::normalize_template_markup($value);
        if ($default !== '' && self::template_text_signature($normalized) === self::template_text_signature($default)) {
            return $default;
        }

        return $normalized;
    }

    private static function template_text_signature($value)
    {
        $value = (string) $value;
        $value = str_replace(["\r", "\n", "\t", '&nbsp;'], ' ', $value);
        $value = wp_strip_all_tags($value, true);
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $value = strtolower(preg_replace('/\s+/u', ' ', trim($value)));

        return $value;
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

        return self::normalize_template_setting($field, $template);
    }

    public function from_name()
    {
        $settings = Settings::all();
        $name = trim((string) ($settings['email_from_name'] ?? ''));
        if ($name === '') {
            $name = (string) get_bloginfo('name');
        }

        return $name;
    }

    public function from_address()
    {
        $settings = Settings::all();
        $email = sanitize_email((string) ($settings['email_from_address'] ?? ''));
        if ($email === '') {
            $email = sanitize_email((string) get_option('admin_email'));
        }

        return is_email($email) ? $email : '';
    }

    public function reply_to()
    {
        $settings = Settings::all();
        $email = sanitize_email((string) ($settings['email_reply_to'] ?? ''));
        if ($email === '') {
            $email = $this->from_address();
        }

        return is_email($email) ? $email : '';
    }

    public function send_admin_new_order($order_id)
    {
        $to = $this->admin_recipient();
        if ($to === '') {
            return false;
        }

        $context = $this->build_order_context($order_id);
        $subject = sprintf(__('Pesanan baru %s', 'velocity-marketplace'), (string) ($context['[kode-pesanan]'] ?? ''));

        return $this->send_html_email($to, $subject, $this->render_email_shell($subject, $this->render_template($this->template('admin_order'), $context)));
    }

    public function send_customer_new_order($order_id)
    {
        $context = $this->build_order_context($order_id);
        $to = sanitize_email((string) ($context['__customer_email'] ?? ''));
        if ($to === '' || !is_email($to)) {
            return false;
        }

        $subject = sprintf(__('Pesanan %s berhasil dibuat', 'velocity-marketplace'), (string) ($context['[kode-pesanan]'] ?? ''));

        return $this->send_html_email($to, $subject, $this->render_email_shell($subject, $this->render_template($this->template('customer_order'), $context)));
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

        return $this->send_html_email($to, $subject, $this->render_email_shell($subject, $this->render_template($this->template('status_update'), $context)));
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
        $from_name = $this->from_name();
        $from_address = $this->from_address();
        $reply_to = $this->reply_to();

        if ($from_address !== '') {
            $headers[] = sprintf('From: %s <%s>', $from_name !== '' ? $from_name : get_bloginfo('name'), $from_address);
        }
        if ($reply_to !== '') {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        return wp_mail($to, $subject, $body, $headers);
    }

    private function render_email_shell($subject, $body)
    {
        $subject = esc_html((string) $subject);
        $site_name = esc_html((string) get_bloginfo('name'));
        $site_url = esc_url(home_url('/'));
        $body = (string) $body;

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . $subject . '</title></head><body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f5f7fb;margin:0;padding:24px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:680px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">'
            . '<tr><td style="padding:24px 28px;border-bottom:1px solid #e5e7eb;background:#ffffff;">'
            . '<div style="font-size:20px;line-height:1.4;font-weight:700;color:#111827;">' . $site_name . '</div>'
            . '<div style="margin-top:6px;font-size:14px;line-height:1.5;color:#6b7280;">' . $subject . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:28px;font-size:14px;line-height:1.7;color:#1f2937;">' . $body . '</td></tr>'
            . '<tr><td style="padding:20px 28px;border-top:1px solid #e5e7eb;background:#f9fafb;">'
            . '<div style="font-size:13px;line-height:1.6;color:#6b7280;">Email ini dikirim oleh <strong style="color:#111827;">' . $site_name . '</strong>.</div>'
            . '<div style="margin-top:4px;font-size:13px;line-height:1.6;"><a href="' . $site_url . '" style="color:#2563eb;text-decoration:none;">' . $site_url . '</a></div>'
            . '</td></tr>'
            . '</table>'
            . '</td></tr>'
            . '</table>'
            . '</body></html>';
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
        $store_address = home_url('/');
        $tracking_url = Settings::customer_order_url($invoice);

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

        $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;">';
        $html .= '<thead><tr>';
        $html .= '<th style="padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;text-align:left;font-size:13px;">' . esc_html__('Produk', 'velocity-marketplace') . '</th>';
        $html .= '<th style="padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;text-align:center;font-size:13px;width:70px;">' . esc_html__('Qty', 'velocity-marketplace') . '</th>';
        $html .= '<th style="padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;text-align:right;font-size:13px;width:140px;">' . esc_html__('Subtotal', 'velocity-marketplace') . '</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($items as $item) {
            $title = esc_html((string) ($item['title'] ?? __('Produk', 'velocity-marketplace')));
            $qty = (int) ($item['qty'] ?? 0);
            $subtotal = (float) ($item['subtotal'] ?? 0);
            $line = $title;

            if (!empty($item['options']) && is_array($item['options'])) {
                $option_parts = [];
                foreach ($item['options'] as $option_key => $option_value) {
                    $option_parts[] = esc_html(ucfirst((string) $option_key) . ': ' . (string) $option_value);
                }
                if (!empty($option_parts)) {
                    $line .= '<div style="margin-top:4px;font-size:12px;line-height:1.5;color:#6b7280;">' . implode(' | ', $option_parts) . '</div>';
                }
            }

            $html .= '<tr>';
            $html .= '<td style="padding:12px;border-bottom:1px solid #e5e7eb;vertical-align:top;">' . $line . '</td>';
            $html .= '<td style="padding:12px;border-bottom:1px solid #e5e7eb;text-align:center;vertical-align:top;">' . esc_html((string) $qty) . '</td>';
            $html .= '<td style="padding:12px;border-bottom:1px solid #e5e7eb;text-align:right;vertical-align:top;">' . esc_html($this->money($subtotal)) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    private function render_customer_data(array $customer)
    {
        $rows = [];
        if (!empty($customer['name'])) {
            $rows[] = [$this->label_cell(__('Nama', 'velocity-marketplace')), $this->value_cell(esc_html((string) $customer['name']))];
        }
        if (!empty($customer['email'])) {
            $rows[] = [$this->label_cell(__('Email', 'velocity-marketplace')), $this->value_cell(esc_html((string) $customer['email']))];
        }
        if (!empty($customer['phone'])) {
            $rows[] = [$this->label_cell(__('Telepon', 'velocity-marketplace')), $this->value_cell(esc_html((string) $customer['phone']))];
        }
        if (!empty($customer['address'])) {
            $rows[] = [$this->label_cell(__('Alamat', 'velocity-marketplace')), $this->value_cell(nl2br(esc_html((string) $customer['address'])))];
        }

        if (empty($rows)) {
            return '<p>-</p>';
        }

        $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;">';
        foreach ($rows as $row) {
            $html .= '<tr>' . $row[0] . $row[1] . '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    private function render_bank_accounts(array $accounts)
    {
        if (empty($accounts)) {
            return '<p>-</p>';
        }

        $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;">';
        $html .= '<thead><tr>';
        $html .= '<th style="padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;text-align:left;font-size:13px;">' . esc_html__('Bank', 'velocity-marketplace') . '</th>';
        $html .= '<th style="padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;text-align:left;font-size:13px;">' . esc_html__('Nomor Rekening', 'velocity-marketplace') . '</th>';
        $html .= '<th style="padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;text-align:left;font-size:13px;">' . esc_html__('Atas Nama', 'velocity-marketplace') . '</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($accounts as $account) {
            if (!is_array($account)) {
                continue;
            }
            $bank_name = esc_html((string) ($account['bank_name'] ?? '-'));
            $account_number = esc_html((string) ($account['account_number'] ?? '-'));
            $account_holder = esc_html((string) ($account['account_holder'] ?? '-'));
            $html .= '<tr>';
            $html .= '<td style="padding:12px;border-bottom:1px solid #e5e7eb;">' . $bank_name . '</td>';
            $html .= '<td style="padding:12px;border-bottom:1px solid #e5e7eb;">' . $account_number . '</td>';
            $html .= '<td style="padding:12px;border-bottom:1px solid #e5e7eb;">' . $account_holder . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    private function label_cell($label)
    {
        return '<td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;width:160px;vertical-align:top;font-weight:600;">' . esc_html((string) $label) . '</td>';
    }

    private function value_cell($value)
    {
        return '<td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;vertical-align:top;">' . (string) $value . '</td>';
    }

    private function money($value)
    {
        return Settings::currency_symbol() . ' ' . number_format((float) $value, 0, ',', '.');
    }
}


