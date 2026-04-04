<?php

namespace VelocityMarketplace\Modules\Cart;

use VelocityMarketplace\Modules\Product\ProductData;
use VelocityMarketplace\Modules\Product\ProductMeta;
use VelocityMarketplace\Support\Contract;
use VelocityMarketplace\Support\Settings;

class CartRepository
{
    const COOKIE_NAME = 'vmp_guest_cart';
    const USER_META_KEY = 'vmp_cart_items';

    public function get_cart_data()
    {
        $items = $this->is_logged_in() ? $this->get_items_from_user_meta() : $this->get_items_from_cookie();

        $total = 0;
        $count = 0;
        foreach ($items as $item) {
            $total += (float) $item['subtotal'];
            $count += (int) $item['qty'];
        }

        return [
            'items' => array_values($items),
            'total' => (float) $total,
            'count' => (int) $count,
        ];
    }

    public function upsert_item($product_id, $qty, $options = [], $cart_key = '')
    {
        $product_id = (int) $product_id;
        $qty = (int) $qty;
        $cart_key = is_string($cart_key) ? trim($cart_key) : '';

        if ($product_id <= 0 || !Contract::is_product($product_id)) {
            return new \WP_Error('invalid_product', 'Produk tidak valid');
        }

        $weight = (float) ProductMeta::get_number($product_id, 'weight', 0);
        if ($weight <= 0) {
            return new \WP_Error(
                'missing_weight',
                sprintf('Produk "%s" belum memiliki berat. Lengkapi berat produk sebelum dimasukkan ke keranjang.', get_the_title($product_id))
            );
        }

        if ($this->is_logged_in()) {
            $this->upsert_user_meta_item($product_id, $qty, $options, $cart_key);
        } else {
            $this->upsert_cookie_item($product_id, $qty, $options, $cart_key);
        }

        return true;
    }

    public function clear()
    {
        if ($this->is_logged_in()) {
            delete_user_meta(get_current_user_id(), self::USER_META_KEY);
            return;
        }

        $this->write_cookie_cart([]);
    }

    private function is_logged_in()
    {
        return is_user_logged_in();
    }

    private function get_items_from_user_meta()
    {
        $value = get_user_meta(get_current_user_id(), self::USER_META_KEY, true);
        if (!is_array($value)) {
            return [];
        }
        return $this->hydrate_items($value);
    }

    private function upsert_user_meta_item($product_id, $qty, $options, $cart_key = '')
    {
        $cart = get_user_meta(get_current_user_id(), self::USER_META_KEY, true);
        if (!is_array($cart)) {
            $cart = [];
        }
        $normalized_options = ProductData::normalize_options($product_id, $options);
        $key = $this->cart_key($product_id, $normalized_options);
        $current_key = ($cart_key !== '' && isset($cart[$cart_key])) ? $cart_key : $key;

        if ($qty <= 0) {
            unset($cart[$current_key]);
            update_user_meta(get_current_user_id(), self::USER_META_KEY, $cart);
            return;
        }

        if ($current_key !== $key) {
            unset($cart[$current_key]);
        }

        $cart[$key] = [
            'id' => (int) $product_id,
            'qty' => (int) $qty,
            'options' => $normalized_options,
        ];
        update_user_meta(get_current_user_id(), self::USER_META_KEY, $cart);
    }

    private function get_items_from_cookie()
    {
        $cart = $this->read_cookie_cart();
        return $this->hydrate_items($cart);
    }

    private function upsert_cookie_item($product_id, $qty, $options, $cart_key = '')
    {
        $options = ProductData::normalize_options($product_id, is_array($options) ? $options : []);
        $cart = $this->read_cookie_cart();
        $key = $this->cart_key($product_id, $options);
        $current_key = ($cart_key !== '' && isset($cart[$cart_key])) ? $cart_key : $key;

        if ($qty <= 0) {
            unset($cart[$current_key]);
            $this->write_cookie_cart($cart);
            return;
        }

        if ($current_key !== $key) {
            unset($cart[$current_key]);
        }

        $cart[$key] = [
            'id' => (int) $product_id,
            'qty' => (int) $qty,
            'options' => $options,
        ];

        $this->write_cookie_cart($cart);
    }

    private function cart_key($product_id, $options = [])
    {
        return md5((string) $product_id . '|' . wp_json_encode($options));
    }

    private function hydrate_items($rows)
    {
        $seller_groups = [];
        $seller_order = [];

        foreach ($rows as $row_key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $product_id = isset($row['id']) ? (int) $row['id'] : 0;
            $qty = isset($row['qty']) ? (int) $row['qty'] : 0;
            $options = isset($row['options']) && is_array($row['options']) ? $row['options'] : [];

            if ($product_id <= 0 || $qty <= 0) {
                continue;
            }

            $product = ProductData::map_post($product_id);
            if (!$product) {
                continue;
            }

            $seller_id = isset($product['author_id']) ? (int) $product['author_id'] : 0;
            $seller_name = $this->seller_name($seller_id);
            $seller_url = $seller_id > 0 ? Settings::store_profile_url($seller_id) : '';

            $options = ProductData::normalize_options($product_id, $options);
            $price_adjustment_label = isset($options['price_adjustment']) ? (string) $options['price_adjustment'] : '';
            $price_adjustment = ProductData::resolve_price_adjustment($product_id, $price_adjustment_label);
            $price = (float) $product['price'] + (float) $price_adjustment;
            $subtotal = $price * $qty;

            $item = [
                'cart_key' => is_string($row_key) ? $row_key : '',
                'id' => $product_id,
                'title' => $product['title'],
                'link' => $product['link'],
                'image' => $product['image'],
                'qty' => $qty,
                'price' => (float) $price,
                'subtotal' => (float) $subtotal,
                'options' => $options,
                'penjual' => $seller_id,
                'seller_id' => $seller_id,
                'seller_name' => $seller_name,
                'seller_url' => $seller_url,
                'stock' => $product['stock'],
                'weight' => isset($product['weight']) ? (float) $product['weight'] : 0,
            ];

            if (!isset($seller_groups[$seller_id])) {
                $seller_groups[$seller_id] = [];
                $seller_order[] = $seller_id;
            }

            $seller_groups[$seller_id][] = $item;
        }

        $items = [];
        foreach ($seller_order as $seller_id) {
            foreach ($seller_groups[$seller_id] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function seller_name($seller_id)
    {
        $seller_id = (int) $seller_id;
        if ($seller_id <= 0) {
            return 'Toko';
        }

        $store_name = (string) get_user_meta($seller_id, 'vmp_store_name', true);
        if ($store_name !== '') {
            return $store_name;
        }

        $seller = get_userdata($seller_id);
        if ($seller && $seller->display_name !== '') {
            return (string) $seller->display_name;
        }

        return 'Toko #' . $seller_id;
    }

    private function read_cookie_cart()
    {
        if (!isset($_COOKIE[self::COOKIE_NAME]) || !is_string($_COOKIE[self::COOKIE_NAME])) {
            return [];
        }

        $decoded = json_decode(wp_unslash($_COOKIE[self::COOKIE_NAME]), true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function write_cookie_cart($cart)
    {
        $cart = is_array($cart) ? $cart : [];
        $value = wp_json_encode($cart);
        $expire = time() + (30 * DAY_IN_SECONDS);
        $_COOKIE[self::COOKIE_NAME] = $value;

        $paths = [defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/'];
        if (defined('SITECOOKIEPATH') && SITECOOKIEPATH && !in_array(SITECOOKIEPATH, $paths, true)) {
            $paths[] = SITECOOKIEPATH;
        }

        $domain = defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
        $options = [
            'expires' => $expire,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if ($domain !== '') {
            $options['domain'] = $domain;
        }

        foreach ($paths as $path) {
            $options['path'] = $path;
            setcookie(self::COOKIE_NAME, $value, $options);
        }
    }
}

