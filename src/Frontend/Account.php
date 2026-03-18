<?php

namespace VelocityMarketplace\Frontend;

use VelocityMarketplace\Support\Settings;

class Account
{
    public function register()
    {
        add_action('init', [$this, 'handle_actions']);
    }

    public static function is_seller($user_id = 0)
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
        return in_array('vmp_seller', $roles, true) || in_array('administrator', $roles, true);
    }

    public function handle_actions()
    {
        if (isset($_GET['vmp_logout']) && $_GET['vmp_logout'] === '1') {
            $this->handle_logout();
            return;
        }

        if (!isset($_POST['vmp_action'])) {
            return;
        }

        $action = sanitize_key((string) wp_unslash($_POST['vmp_action']));
        if ($action === 'register') {
            $this->handle_register();
            return;
        }
        if ($action === 'login') {
            $this->handle_login();
            return;
        }
    }

    private function handle_register()
    {
        if (!isset($_POST['vmp_register_nonce']) || !wp_verify_nonce($_POST['vmp_register_nonce'], 'vmp_register')) {
            $this->redirect_with(['vmp_error' => 'Nonce registrasi tidak valid.']);
        }

        $username = sanitize_user((string) ($_POST['username'] ?? ''));
        $email = sanitize_email((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
        $phone = sanitize_text_field((string) ($_POST['phone'] ?? ''));
        $role_type = sanitize_key((string) ($_POST['role_type'] ?? 'customer'));

        if ($username === '' || $email === '' || $password === '') {
            $this->redirect_with(['vmp_error' => 'Username, email, dan password wajib diisi.']);
        }
        if (!is_email($email)) {
            $this->redirect_with(['vmp_error' => 'Email tidak valid.']);
        }
        if ($password !== $confirm) {
            $this->redirect_with(['vmp_error' => 'Konfirmasi password tidak sama.']);
        }
        if (username_exists($username)) {
            $this->redirect_with(['vmp_error' => 'Username sudah dipakai.']);
        }
        if (email_exists($email)) {
            $this->redirect_with(['vmp_error' => 'Email sudah terdaftar.']);
        }

        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            $this->redirect_with(['vmp_error' => $user_id->get_error_message()]);
        }

        $role = $role_type === 'seller' ? 'vmp_seller' : 'vmp_customer';
        $user = new \WP_User($user_id);
        $user->set_role($role);

        if ($name !== '') {
            update_user_meta($user_id, 'first_name', $name);
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $name,
            ]);
        }
        if ($phone !== '') {
            update_user_meta($user_id, 'vmp_phone', $phone);
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        $this->redirect_with([
            'vmp_notice' => $role === 'vmp_seller' ? 'Registrasi seller berhasil.' : 'Registrasi berhasil.',
        ]);
    }

    private function handle_login()
    {
        if (!isset($_POST['vmp_login_nonce']) || !wp_verify_nonce($_POST['vmp_login_nonce'], 'vmp_login')) {
            $this->redirect_with(['vmp_error' => 'Nonce login tidak valid.']);
        }

        $username = sanitize_user((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            $this->redirect_with(['vmp_error' => 'Username dan password wajib diisi.']);
        }

        $user = wp_signon([
            'user_login' => $username,
            'user_password' => $password,
            'remember' => true,
        ], is_ssl());

        if (is_wp_error($user)) {
            $this->redirect_with(['vmp_error' => 'Login gagal. Periksa username/password.']);
        }

        $this->redirect_with(['vmp_notice' => 'Login berhasil.']);
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
}
