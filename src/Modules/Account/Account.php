<?php

namespace VelocityMarketplace\Modules\Account;

use VelocityMarketplace\Support\Settings;

class Account
{
    public function register()
    {
        add_action('init', [$this, 'handle_actions']);
        add_action('user_register', [$this, 'apply_register_fields']);
        add_action('login_enqueue_scripts', [$this, 'customize_login_logo']);
        add_filter('show_admin_bar', [$this, 'filter_admin_bar']);
        add_filter('login_headerurl', [$this, 'filter_login_header_url']);
        add_filter('login_headertext', [$this, 'filter_login_header_text']);
        add_filter('login_redirect', [$this, 'filter_login_redirect'], 10, 3);
    }

    public static function is_member($user_id = 0)
    {
        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $roles = is_array($user->roles) ? $user->roles : [];
        return in_array('vmp_member', $roles, true) || in_array('administrator', $roles, true);
    }

    public static function can_sell($user_id = 0)
    {
        return self::is_member($user_id);
    }

    public static function user_role_label($user_id = 0)
    {
        $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0) {
            return '';
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return '';
        }

        $roles = is_array($user->roles) ? $user->roles : [];
        if (in_array('administrator', $roles, true)) {
            return 'Admin';
        }
        if (in_array('vmp_member', $roles, true)) {
            return 'Member';
        }

        return 'User';
    }

    public function handle_actions()
    {
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'vmp_last_active_at', current_time('mysql'));
        }

        if (isset($_GET['vmp_logout']) && $_GET['vmp_logout'] === '1') {
            $this->handle_logout();
            return;
        }
    }

    public function apply_register_fields($user_id)
    {
        $user = new \WP_User($user_id);
        $user->set_role('vmp_member');
    }

    public function filter_admin_bar($show)
    {
        if (current_user_can('manage_options')) {
            return $show;
        }

        return false;
    }

    public function filter_login_redirect($redirect_to, $requested_redirect_to, $user)
    {
        if (!($user instanceof \WP_User)) {
            return $redirect_to;
        }

        if (in_array('administrator', (array) $user->roles, true)) {
            return $requested_redirect_to !== '' ? $requested_redirect_to : $redirect_to;
        }

        if (self::is_member((int) $user->ID)) {
            return add_query_arg(['tab' => 'account_profile'], Settings::profile_url());
        }

        return $redirect_to;
    }

    public function customize_login_logo()
    {
        $logo = $this->login_logo_data();
        if (empty($logo['url'])) {
            return;
        }

        $width = max(120, min(320, (int) ($logo['width'] ?? 320)));
        $height = max(60, min(140, (int) ($logo['height'] ?? 100)));
        ?>
        <style>
            body.login div#login h1 a {
                background-image: url('<?php echo esc_url($logo['url']); ?>');
                background-size: contain;
                background-position: center center;
                background-repeat: no-repeat;
                width: <?php echo (int) $width; ?>px;
                height: <?php echo (int) $height; ?>px;
                max-width: 100%;
            }
        </style>
        <?php
    }

    public function filter_login_header_url($url)
    {
        return home_url('/');
    }

    public function filter_login_header_text($text)
    {
        return get_bloginfo('name');
    }

    private function handle_logout()
    {
        $nonce = isset($_GET['vmp_nonce']) ? sanitize_text_field((string) $_GET['vmp_nonce']) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vmp_logout')) {
            return;
        }

        wp_logout();
        $this->redirect_with(['vmp_notice' => 'Logout berhasil.']);
    }

    private function redirect_with($params = [])
    {
        $target = wp_get_referer();
        if (!$target) {
            $target = Settings::profile_url();
        }
        if (!is_array($params)) {
            $params = [];
        }
        $url = add_query_arg($params, $target);
        wp_safe_redirect($url);
        exit;
    }

    private function login_logo_data()
    {
        $logo_id = (int) get_theme_mod('custom_logo');
        if ($logo_id <= 0) {
            return [];
        }

        $image = wp_get_attachment_image_src($logo_id, 'full');
        if (!is_array($image) || empty($image[0])) {
            return [];
        }

        return [
            'url' => (string) $image[0],
            'width' => isset($image[1]) ? (int) $image[1] : 0,
            'height' => isset($image[2]) ? (int) $image[2] : 0,
        ];
    }
}

