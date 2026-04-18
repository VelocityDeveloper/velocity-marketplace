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
        $email_from_name = isset($input['email_from_name']) ? sanitize_text_field((string) $input['email_from_name']) : '';
        $email_from_address = isset($input['email_from_address']) ? sanitize_email((string) $input['email_from_address']) : '';
        $email_reply_to = isset($input['email_reply_to']) ? sanitize_email((string) $input['email_reply_to']) : '';
        $email_template_admin_order = isset($input['email_template_admin_order']) ? EmailTemplateService::normalize_template_setting('email_template_admin_order', wp_kses_post((string) $input['email_template_admin_order'])) : (string) $email_defaults['email_template_admin_order'];
        $email_template_customer_order = isset($input['email_template_customer_order']) ? EmailTemplateService::normalize_template_setting('email_template_customer_order', wp_kses_post((string) $input['email_template_customer_order'])) : (string) $email_defaults['email_template_customer_order'];
        $email_template_status_update = isset($input['email_template_status_update']) ? EmailTemplateService::normalize_template_setting('email_template_status_update', wp_kses_post((string) $input['email_template_status_update'])) : (string) $email_defaults['email_template_status_update'];

        return [
            'seller_product_status' => $seller_product_status,
            'email_admin_recipient' => $email_admin_recipient,
            'email_from_name' => $email_from_name,
            'email_from_address' => $email_from_address,
            'email_reply_to' => $email_reply_to,
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

        $admin_template = isset($settings['email_template_admin_order']) && (string) $settings['email_template_admin_order'] !== ''
            ? (string) $settings['email_template_admin_order']
            : (string) $email_defaults['email_template_admin_order'];
        $customer_template = isset($settings['email_template_customer_order']) && (string) $settings['email_template_customer_order'] !== ''
            ? (string) $settings['email_template_customer_order']
            : (string) $email_defaults['email_template_customer_order'];
        $status_template = isset($settings['email_template_status_update']) && (string) $settings['email_template_status_update'] !== ''
            ? (string) $settings['email_template_status_update']
            : (string) $email_defaults['email_template_status_update'];

        return [
            'seller_product_status' => isset($settings['seller_product_status']) ? (string) $settings['seller_product_status'] : 'publish',
            'email_admin_recipient' => isset($settings['email_admin_recipient']) ? (string) $settings['email_admin_recipient'] : '',
            'email_from_name' => isset($settings['email_from_name']) ? (string) $settings['email_from_name'] : '',
            'email_from_address' => isset($settings['email_from_address']) ? (string) $settings['email_from_address'] : '',
            'email_reply_to' => isset($settings['email_reply_to']) ? (string) $settings['email_reply_to'] : '',
            'email_template_admin_order' => EmailTemplateService::normalize_template_setting('email_template_admin_order', $admin_template),
            'email_template_customer_order' => EmailTemplateService::normalize_template_setting('email_template_customer_order', $customer_template),
            'email_template_status_update' => EmailTemplateService::normalize_template_setting('email_template_status_update', $status_template),
        ];
    }

    public function save_settings($input)
    {
        $sanitized = $this->sanitize($input);
        update_option(VMP_SETTINGS_OPTION, $sanitized);
        return $this->get_settings_payload();
    }
}





