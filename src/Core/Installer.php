<?php

namespace VelocityMarketplace\Core;

class Installer
{
    public function activate()
    {
        $this->ensure_roles();
        $this->create_default_pages();
        $this->seed_default_settings();
    }

    private function create_default_pages()
    {
        $pages = [
            'toko' => [
                'title' => __('Toko', 'velocity-marketplace'),
                'slug' => 'store',
                'content' => '[vmp_store_profile]',
            ],
        ];

        $stored = get_option(VMP_PAGES_OPTION, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        $stored = array_intersect_key($stored, $pages);

        foreach ($pages as $slug => $page) {
            $page_slug = isset($page['slug']) ? sanitize_title((string) $page['slug']) : sanitize_title((string) $slug);
            $existing = get_page_by_path($page_slug);
            if ($existing && isset($existing->ID)) {
                $stored[$slug] = (int) $existing->ID;
                continue;
            }

            $post_id = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $page['title'],
                'post_name' => $page_slug,
                'post_content' => $page['content'],
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ]);

            if (!is_wp_error($post_id) && $post_id) {
                $stored[$slug] = (int) $post_id;
            }
        }

        update_option(VMP_PAGES_OPTION, $stored);
    }

    private function seed_default_settings()
    {
        $current = get_option(VMP_SETTINGS_OPTION, []);
        if (!is_array($current)) {
            $current = [];
        }

        $defaults = [
            'default_order_status' => 'pending_payment',
            'seller_product_status' => 'publish',
            'email_admin_recipient' => '',
            'email_template_admin_order' => '',
            'email_template_customer_order' => '',
            'email_template_status_update' => '',
        ];

        update_option(VMP_SETTINGS_OPTION, array_merge($defaults, $current));
    }

    private function ensure_roles()
    {
        remove_role('vmp_customer');
        remove_role('vmp_seller');

        add_role('vmp_member', __('Member Marketplace', 'velocity-marketplace'), [
            'read' => true,
            'upload_files' => true,
        ]);
    }
}
