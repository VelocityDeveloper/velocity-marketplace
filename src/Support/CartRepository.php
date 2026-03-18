<?php

namespace VelocityMarketplace\Support;

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

    public function upsert_item($product_id, $qty, $options = [])
    {
        $product_id = (int) $product_id;
        $qty = (int) $qty;

        if ($product_id <= 0 || get_post_type($product_id) !== 'vmp_product') {
            return new \WP_Error('invalid_product', 'Produk tidak valid');
        }

        if ($this->is_logged_in()) {
            $this->upsert_user_meta_item($product_id, $qty, $options);
        } else {
            $this->upsert_cookie_item($product_id, $qty, $options);
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

    private function upsert_user_meta_item($product_id, $qty, $options)
    {
        $cart = get_user_meta(get_current_user_id(), self::USER_META_KEY, true);
        if (!is_array($cart)) {
            $cart = [];
        }
        $normalized_options = ProductData::normalize_options($product_id, $options);
        $key = $this->cart_key($product_id, $normalized_options);

        if ($qty <= 0) {
            unset($cart[$key]);
            update_user_meta(get_current_user_id(), self::USER_META_KEY, $cart);
            return;
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

    private function upsert_cookie_item($product_id, $qty, $options)
    {
        $options = ProductData::normalize_options($product_id, is_array($options) ? $options : []);
        $cart = $this->read_cookie_cart();
        $key = $this->cart_key($product_id, $options);

        if ($qty <= 0) {
            unset($cart[$key]);
            $this->write_cookie_cart($cart);
            return;
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
        $items = [];
        foreach ($rows as $row) {
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

            $options = ProductData::normalize_options($product_id, $options);
            $adv_label = isset($options['advanced']) ? (string) $options['advanced'] : '';
            $price_override = ProductData::resolve_advanced_price($product_id, $adv_label);
            $price = $price_override !== null ? $price_override : (float) $product['price'];
            $subtotal = $price * $qty;

            $items[] = [
                'id' => $product_id,
                'title' => $product['title'],
                'link' => $product['link'],
                'image' => $product['image'],
                'qty' => $qty,
                'price' => (float) $price,
                'subtotal' => (float) $subtotal,
                'options' => $options,
                'penjual' => (int) $product['author_id'],
                'stock' => $product['stock'],
            ];
        }

        return $items;
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
        setcookie(self::COOKIE_NAME, $value, $expire, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE[self::COOKIE_NAME] = $value;
    }
}
