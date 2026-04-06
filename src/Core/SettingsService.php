<?php

namespace VelocityMarketplace\Core;

use VelocityMarketplace\Modules\Email\EmailTemplateService;

class SettingsService
{
    public function sanitize($input)
    {
        $input = is_array($input) ? $input : [];

        $seller_product_status = isset($input['seller_product_status']) ? sanitize_key((string) $input['seller_product_status']) : 'publish';
        if (!in_array($seller_product_status, ['pending', 'publish'], true)) {
            $seller_product_status = 'publish';
        }
        $email_defaults = EmailTemplateService::default_settings();
        $email_admin_recipient = isset($input['email_admin_recipient']) ? sanitize_email((string) $input['email_admin_recipient']) : '';
        $email_template_admin_order = isset($input['email_template_admin_order']) ? wp_kses_post((string) $input['email_template_admin_order']) : (string) $email_defaults['email_template_admin_order'];
        $email_template_customer_order = isset($input['email_template_customer_order']) ? wp_kses_post((string) $input['email_template_customer_order']) : (string) $email_defaults['email_template_customer_order'];
        $email_template_status_update = isset($input['email_template_status_update']) ? wp_kses_post((string) $input['email_template_status_update']) : (string) $email_defaults['email_template_status_update'];

        return [
            'seller_product_status' => $seller_product_status,
            'email_admin_recipient' => $email_admin_recipient,
            'email_template_admin_order' => $email_template_admin_order,
            'email_template_customer_order' => $email_template_customer_order,
            'email_template_status_update' => $email_template_status_update,
        ];
    }

    public function get_settings_payload()
    {
        $settings = get_option(VMP_SETTINGS_OPTION, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $email_defaults = EmailTemplateService::default_settings();

        return [
            'seller_product_status' => isset($settings['seller_product_status']) ? (string) $settings['seller_product_status'] : 'publish',
            'email_admin_recipient' => isset($settings['email_admin_recipient']) ? (string) $settings['email_admin_recipient'] : '',
            'email_template_admin_order' => isset($settings['email_template_admin_order']) && (string) $settings['email_template_admin_order'] !== ''
                ? (string) $settings['email_template_admin_order']
                : (string) $email_defaults['email_template_admin_order'],
            'email_template_customer_order' => isset($settings['email_template_customer_order']) && (string) $settings['email_template_customer_order'] !== ''
                ? (string) $settings['email_template_customer_order']
                : (string) $email_defaults['email_template_customer_order'],
            'email_template_status_update' => isset($settings['email_template_status_update']) && (string) $settings['email_template_status_update'] !== ''
                ? (string) $settings['email_template_status_update']
                : (string) $email_defaults['email_template_status_update'],
        ];
    }

    public function save_settings($input)
    {
        $sanitized = $this->sanitize($input);
        update_option(VMP_SETTINGS_OPTION, $sanitized);
        return $this->get_settings_payload();
    }
}
