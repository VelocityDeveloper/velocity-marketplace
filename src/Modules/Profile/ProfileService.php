<?php

namespace VelocityMarketplace\Modules\Profile;

class ProfileService
{
    public function get_member_profile($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return [];
        }

        $user = get_userdata($user_id);
        $name = (string) get_user_meta($user_id, 'first_name', true);
        if ($name === '' && $user && $user->display_name !== '') {
            $name = (string) $user->display_name;
        }

        return [
            'name' => $name,
            'email' => $user ? (string) $user->user_email : '',
            'phone' => (string) get_user_meta($user_id, 'vmp_member_phone', true),
            'address' => (string) get_user_meta($user_id, 'vmp_member_address', true),
            'province_id' => (string) get_user_meta($user_id, 'vmp_member_province_id', true),
            'province_name' => (string) get_user_meta($user_id, 'vmp_member_province', true),
            'city_id' => (string) get_user_meta($user_id, 'vmp_member_city_id', true),
            'city_name' => (string) get_user_meta($user_id, 'vmp_member_city', true),
            'subdistrict_id' => (string) get_user_meta($user_id, 'vmp_member_subdistrict_id', true),
            'subdistrict_name' => (string) get_user_meta($user_id, 'vmp_member_subdistrict', true),
            'postcode' => (string) get_user_meta($user_id, 'vmp_member_postcode', true),
        ];
    }

    public function save_member_profile($user_id, array $payload)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return new \WP_Error('invalid_user', 'Akun tidak valid.');
        }

        $name = $this->sanitize_text($payload, ['name', 'customer_name']);
        $phone = $this->sanitize_text($payload, ['phone', 'customer_phone']);
        $address = $this->sanitize_textarea($payload, ['address', 'customer_address']);

        if ($name === '' || $phone === '' || $address === '') {
            return new \WP_Error('invalid_member_profile', 'Nama, telepon, dan alamat wajib diisi.');
        }

        $map = [
            'vmp_member_phone' => $phone,
            'vmp_member_address' => $address,
            'vmp_member_subdistrict_id' => $this->sanitize_text($payload, ['subdistrict_id', 'customer_subdistrict_id']),
            'vmp_member_subdistrict' => $this->sanitize_text($payload, ['subdistrict_name', 'customer_subdistrict_name']),
            'vmp_member_city_id' => $this->sanitize_text($payload, ['city_id', 'customer_city_id']),
            'vmp_member_city' => $this->sanitize_text($payload, ['city_name', 'customer_city_name']),
            'vmp_member_province_id' => $this->sanitize_text($payload, ['province_id', 'customer_province_id']),
            'vmp_member_province' => $this->sanitize_text($payload, ['province_name', 'customer_province_name']),
            'vmp_member_postcode' => $this->sanitize_text($payload, ['postcode', 'customer_postcode']),
        ];

        foreach ($map as $meta_key => $value) {
            update_user_meta($user_id, $meta_key, $value);
        }

        update_user_meta($user_id, 'first_name', $name);
        update_user_meta($user_id, 'nickname', $name);
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
        ]);

        return [
            'message' => 'Profil member berhasil diperbarui.',
            'profile' => $this->get_member_profile($user_id),
        ];
    }

    public function get_store_profile($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return [];
        }

        $couriers = get_user_meta($user_id, 'vmp_couriers', true);
        $cod_city_ids = get_user_meta($user_id, 'vmp_cod_city_ids', true);
        $cod_city_names = get_user_meta($user_id, 'vmp_cod_city_names', true);
        if (!is_array($couriers)) {
            $couriers = [];
        }
        if (!is_array($cod_city_ids)) {
            $cod_city_ids = [];
        }
        if (!is_array($cod_city_names)) {
            $cod_city_names = [];
        }

        return [
            'name' => (string) get_user_meta($user_id, 'vmp_store_name', true),
            'phone' => (string) get_user_meta($user_id, 'vmp_store_phone', true),
            'whatsapp' => (string) get_user_meta($user_id, 'vmp_store_whatsapp', true),
            'address' => (string) get_user_meta($user_id, 'vmp_store_address', true),
            'bank_details' => (string) get_user_meta($user_id, 'vmp_store_bank_details', true),
            'province_id' => (string) get_user_meta($user_id, 'vmp_store_province_id', true),
            'province_name' => (string) get_user_meta($user_id, 'vmp_store_province', true),
            'city_id' => (string) get_user_meta($user_id, 'vmp_store_city_id', true),
            'city_name' => (string) get_user_meta($user_id, 'vmp_store_city', true),
            'subdistrict_id' => (string) get_user_meta($user_id, 'vmp_store_subdistrict_id', true),
            'subdistrict_name' => (string) get_user_meta($user_id, 'vmp_store_subdistrict', true),
            'postcode' => (string) get_user_meta($user_id, 'vmp_store_postcode', true),
            'description' => (string) get_user_meta($user_id, 'vmp_store_description', true),
            'couriers' => array_values(array_map('strval', $couriers)),
            'cod_enabled' => !empty(get_user_meta($user_id, 'vmp_cod_enabled', true)),
            'cod_city_ids' => array_values(array_map('strval', $cod_city_ids)),
            'cod_city_names' => array_values(array_map('strval', $cod_city_names)),
            'avatar_id' => (int) get_user_meta($user_id, 'vmp_store_avatar_id', true),
        ];
    }

    public function save_store_profile($user_id, array $payload)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return new \WP_Error('invalid_user', 'Akun tidak valid.');
        }

        $name = $this->sanitize_text($payload, ['name', 'store_name']);
        $address = $this->sanitize_textarea($payload, ['address', 'store_address']);
        if ($name === '' || $address === '') {
            return new \WP_Error('invalid_store_profile', 'Nama toko dan alamat toko wajib diisi.');
        }

        $map = [
            'vmp_store_name' => $name,
            'vmp_store_phone' => $this->sanitize_text($payload, ['phone', 'store_phone']),
            'vmp_store_whatsapp' => $this->sanitize_text($payload, ['whatsapp', 'store_whatsapp']),
            'vmp_store_address' => $address,
            'vmp_store_bank_details' => $this->sanitize_textarea($payload, ['bank_details', 'store_bank_details']),
            'vmp_store_subdistrict_id' => $this->sanitize_text($payload, ['subdistrict_id', 'store_subdistrict_id']),
            'vmp_store_subdistrict' => $this->sanitize_text($payload, ['subdistrict_name', 'store_subdistrict_name']),
            'vmp_store_city_id' => $this->sanitize_text($payload, ['city_id', 'store_city_id']),
            'vmp_store_city' => $this->sanitize_text($payload, ['city_name', 'store_city_name']),
            'vmp_store_province_id' => $this->sanitize_text($payload, ['province_id', 'store_province_id']),
            'vmp_store_province' => $this->sanitize_text($payload, ['province_name', 'store_province_name']),
            'vmp_store_postcode' => $this->sanitize_text($payload, ['postcode', 'store_postcode']),
            'vmp_store_description' => $this->sanitize_textarea($payload, ['description', 'store_description']),
        ];

        foreach ($map as $meta_key => $value) {
            update_user_meta($user_id, $meta_key, $value);
        }

        $couriers = $this->sanitize_array($payload, ['couriers', 'store_couriers']);
        $couriers = array_values(array_unique(array_filter(array_map('sanitize_key', $couriers), static function ($code) {
            return $code !== '';
        })));
        update_user_meta($user_id, 'vmp_couriers', $couriers);

        $cod_enabled = $this->to_bool($this->pick_value($payload, ['cod_enabled', 'store_cod_enabled']));
        $cod_city_ids = $this->sanitize_array($payload, ['cod_city_ids', 'store_cod_city_ids']);
        $cod_city_names = $this->sanitize_array($payload, ['cod_city_names', 'store_cod_city_names']);
        $normalized_cod_ids = [];
        $normalized_cod_names = [];
        foreach ($cod_city_ids as $index => $city_id) {
            $city_id = sanitize_text_field((string) $city_id);
            if ($city_id === '') {
                continue;
            }
            $normalized_cod_ids[] = $city_id;
            $normalized_cod_names[] = sanitize_text_field((string) ($cod_city_names[$index] ?? ''));
        }
        update_user_meta($user_id, 'vmp_cod_enabled', $cod_enabled ? 1 : 0);
        update_user_meta($user_id, 'vmp_cod_city_ids', array_values($normalized_cod_ids));
        update_user_meta($user_id, 'vmp_cod_city_names', array_values($normalized_cod_names));

        $avatar_value = $this->pick_value($payload, ['avatar_id', 'store_avatar_id'], null);
        $avatar_id = (int) $avatar_value;
        if ($avatar_id > 0) {
            if ($this->attachment_allowed_for_user($avatar_id, $user_id)) {
                update_user_meta($user_id, 'vmp_store_avatar_id', $avatar_id);
            }
        } elseif ($avatar_value !== null) {
            delete_user_meta($user_id, 'vmp_store_avatar_id');
        }

        return [
            'message' => 'Profil toko berhasil diperbarui.',
            'profile' => $this->get_store_profile($user_id),
        ];
    }

    private function sanitize_text(array $payload, array $keys)
    {
        return sanitize_text_field((string) $this->pick_value($payload, $keys, ''));
    }

    private function sanitize_textarea(array $payload, array $keys)
    {
        return sanitize_textarea_field((string) $this->pick_value($payload, $keys, ''));
    }

    private function sanitize_array(array $payload, array $keys)
    {
        $value = $this->pick_value($payload, $keys, []);
        return is_array($value) ? array_values($value) : [];
    }

    private function pick_value(array $payload, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return $payload[$key];
            }
        }

        return $default;
    }

    private function to_bool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower((string) $value);
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function attachment_allowed_for_user($attachment_id, $user_id)
    {
        $attachment_id = (int) $attachment_id;
        $user_id = (int) $user_id;
        if ($attachment_id <= 0 || $user_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        return (int) get_post_field('post_author', $attachment_id) === $user_id;
    }
}
